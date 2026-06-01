<?php
declare(strict_types=1);

function plasma_compact_text(string $value, int $maxLength = 140): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', $value));
    $textLength = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($text === '' || $textLength <= $maxLength) {
        return $text;
    }

    $slice = function_exists('mb_substr')
        ? mb_substr($text, 0, max(0, $maxLength - 1))
        : substr($text, 0, max(0, $maxLength - 1));

    return rtrim((string) $slice) . '...';
}

function plasma_log_dir(): string
{
    $override = trim((string) (getenv('SOWWWL_SENSOR_LOG_DIR') ?: ''));
    if ($override !== '') {
        return rtrim($override, DIRECTORY_SEPARATOR);
    }

    return dirname(LANDS_DIR) . DIRECTORY_SEPARATOR . 'plasma';
}

function plasma_event_log_path(): string
{
    return plasma_log_dir() . DIRECTORY_SEPARATOR . 'sensor-events.jsonl';
}

function plasma_db_path(): string
{
    $override = trim((string) (getenv('SOWWWL_SENSOR_DB_PATH') ?: ''));
    if ($override !== '') {
        return $override;
    }

    return plasma_log_dir() . DIRECTORY_SEPARATOR . 'sensor-events.sqlite3';
}

function plasma_buffer_max_events(): int
{
    $override = (int) (getenv('SOWWWL_PLASMA_BUFFER_MAX_EVENTS') ?: 0);
    if ($override <= 0) {
        return 4096;
    }

    return max(256, min(20000, $override));
}

function plasma_stale_after_seconds(): int
{
    $override = (int) (getenv('SOWWWL_PLASMA_STALE_AFTER_SECONDS') ?: 0);
    if ($override <= 0) {
        return 90;
    }

    return max(15, min(3600, $override));
}

function plasma_shadow_log_max_events(): int
{
    return max(64, min(512, plasma_buffer_max_events()));
}

function plasma_shadow_log_max_bytes(): int
{
    return max(131072, min(2097152, plasma_shadow_log_max_events() * 1024));
}

function plasma_shadow_log_compact_interval(): int
{
    return 24;
}

function plasma_sqlite_available(): bool
{
    return class_exists(PDO::class) && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function plasma_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!plasma_sqlite_available()) {
        throw new RuntimeException('SQLite driver unavailable for plasma buffer.');
    }

    $dir = dirname(plasma_db_path());
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de preparer le buffer plasma.');
    }

    $pdo = new PDO('sqlite:' . plasma_db_path(), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA temp_store = MEMORY');
    $pdo->exec('PRAGMA busy_timeout = 1000');

    plasma_bootstrap_database($pdo);
    plasma_ensure_legacy_shadow_log($pdo);

    return $pdo;
}

function plasma_bootstrap_database(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS plasma_events (
            id TEXT PRIMARY KEY,
            event TEXT NOT NULL,
            source TEXT NOT NULL,
            camera TEXT NOT NULL,
            land_slug TEXT NOT NULL,
            timestamp TEXT NOT NULL,
            received_at TEXT NOT NULL,
            message TEXT NOT NULL,
            metrics_json TEXT NOT NULL,
            remote_addr_hash TEXT NOT NULL DEFAULT ""
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_plasma_events_received_at ON plasma_events(received_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_plasma_events_land_slug ON plasma_events(land_slug)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_plasma_events_camera ON plasma_events(camera)');
}

function plasma_database_event_count(PDO $pdo): int
{
    $count = $pdo->query('SELECT COUNT(*) FROM plasma_events')->fetchColumn();

    return max(0, (int) $count);
}

function plasma_import_legacy_log(PDO $pdo): int
{
    static $imported = false;

    if ($imported) {
        return 0;
    }
    $imported = true;

    $count = plasma_database_event_count($pdo);
    if ($count > 0) {
        return 0;
    }

    $legacyPath = plasma_event_log_path();
    if (!is_readable($legacyPath) || is_dir($legacyPath)) {
        return 0;
    }

    $lines = plasma_tail_lines($legacyPath, min(plasma_buffer_max_events(), 2000));
    if ($lines === []) {
        return 0;
    }

    $importedCount = 0;
    $pdo->beginTransaction();
    try {
        foreach ($lines as $line) {
            $decoded = json_decode((string) $line, true);
            if (!is_array($decoded)) {
                continue;
            }

            plasma_insert_event_into_database($pdo, $decoded);
            $importedCount += 1;
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('plasma legacy import skipped: ' . $exception->getMessage());
        return 0;
    }

    try {
        plasma_sync_legacy_shadow_log($pdo, true);
    } catch (Throwable $exception) {
        error_log('plasma legacy shadow sync skipped: ' . $exception->getMessage());
    }

    return $importedCount;
}

function plasma_insert_event_into_database(PDO $pdo, array $event): void
{
    static $statement = null;

    if (!$statement instanceof PDOStatement) {
        $statement = $pdo->prepare(
            'INSERT OR REPLACE INTO plasma_events (
                id, event, source, camera, land_slug, timestamp, received_at, message, metrics_json, remote_addr_hash
            ) VALUES (
                :id, :event, :source, :camera, :land_slug, :timestamp, :received_at, :message, :metrics_json, :remote_addr_hash
            )'
        );
    }

    $metrics = is_array($event['metrics'] ?? null) ? $event['metrics'] : [];
    $metricsJson = json_encode($metrics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($metricsJson)) {
        $metricsJson = '{}';
    }

    $statement->execute([
        ':id' => trim((string) ($event['id'] ?? ('evt_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4))))),
        ':event' => trim((string) ($event['event'] ?? 'signal')),
        ':source' => trim((string) ($event['source'] ?? 'runtime')),
        ':camera' => trim((string) ($event['camera'] ?? 'unknown')),
        ':land_slug' => trim((string) ($event['land_slug'] ?? '')),
        ':timestamp' => trim((string) ($event['timestamp'] ?? gmdate(DATE_ATOM))),
        ':received_at' => trim((string) ($event['received_at'] ?? gmdate(DATE_ATOM))),
        ':message' => trim((string) ($event['message'] ?? '')),
        ':metrics_json' => $metricsJson,
        ':remote_addr_hash' => trim((string) ($event['remote_addr_hash'] ?? '')),
    ]);
}

function plasma_prune_database(PDO $pdo): void
{
    static $pruneStatement = null;

    if (!$pruneStatement instanceof PDOStatement) {
        $pruneStatement = $pdo->prepare(
            'DELETE FROM plasma_events
             WHERE rowid IN (
                SELECT rowid FROM plasma_events
                ORDER BY received_at DESC, rowid DESC
                LIMIT -1 OFFSET :offset
             )'
        );
    }

    $pruneStatement->bindValue(':offset', plasma_buffer_max_events(), PDO::PARAM_INT);
    $pruneStatement->execute();
}

function plasma_append_event(array $event): void
{
    $pdo = null;

    if (plasma_sqlite_available()) {
        try {
            $pdo = plasma_connection();
            $pdo->beginTransaction();
            plasma_insert_event_into_database($pdo, $event);
            plasma_prune_database($pdo);
            $pdo->commit();
            try {
                plasma_append_shadow_log_event($event);
            } catch (Throwable $exception) {
                error_log('plasma shadow append skipped: ' . $exception->getMessage());
            }
            try {
                plasma_sync_legacy_shadow_log($pdo);
            } catch (Throwable $exception) {
                error_log('plasma shadow sync skipped: ' . $exception->getMessage());
            }
            return;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('plasma sqlite fallback: ' . $exception->getMessage());
        }
    }

    $dir = plasma_log_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de preparer le journal plasma.');
    }

    plasma_append_shadow_log_event($event);

    try {
        plasma_sync_legacy_shadow_log(null);
    } catch (Throwable $exception) {
        error_log('plasma fallback compaction skipped: ' . $exception->getMessage());
    }
}

function plasma_recent_events(int $limit = 5, ?string $cameraSlug = null): array
{
    $safeLimit = max(1, $limit);

    if (plasma_sqlite_available()) {
        try {
            return plasma_recent_events_from_database($safeLimit, $cameraSlug);
        } catch (Throwable $exception) {
            error_log('plasma sqlite read fallback: ' . $exception->getMessage());
        }
    }

    return plasma_recent_events_from_legacy_log($safeLimit, $cameraSlug);
}

function plasma_recent_events_from_database(int $limit, ?string $cameraSlug = null): array
{
    $pdo = plasma_connection();
    $slug = strtolower(trim((string) $cameraSlug));

    $sql = 'SELECT id, event, source, camera, land_slug, timestamp, received_at, message, metrics_json
            FROM plasma_events';
    if ($slug !== '') {
        $sql .= ' WHERE land_slug = :slug COLLATE NOCASE
                  OR source = :slug COLLATE NOCASE
                  OR camera = :slug COLLATE NOCASE';
    }
    $sql .= ' ORDER BY received_at DESC, rowid DESC LIMIT :limit';

    $statement = $pdo->prepare($sql);
    if ($slug !== '') {
        $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $events = [];
    foreach ($statement->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $events[] = plasma_event_from_record($row);
    }

    return $events;
}

function plasma_recent_events_from_legacy_log(int $limit, ?string $cameraSlug = null): array
{
    $logFile = plasma_event_log_path();
    if (!is_readable($logFile) || is_dir($logFile)) {
        return [];
    }

    $lines = plasma_tail_lines($logFile, max(1, $limit * ($cameraSlug !== null && trim($cameraSlug) !== '' ? 6 : 1)));
    if ($lines === []) {
        return [];
    }

    $events = [];
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode((string) $line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $event = plasma_event_from_record([
            'id' => $decoded['id'] ?? '',
            'event' => $decoded['event'] ?? 'signal',
            'source' => $decoded['source'] ?? 'runtime',
            'camera' => $decoded['camera'] ?? 'unknown',
            'land_slug' => $decoded['land_slug'] ?? '',
            'timestamp' => $decoded['timestamp'] ?? '',
            'received_at' => $decoded['received_at'] ?? '',
            'message' => $decoded['message'] ?? '',
            'metrics_json' => json_encode($decoded['metrics'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);

        if ($cameraSlug !== null && trim($cameraSlug) !== '' && !plasma_event_matches_camera_slug($event, $cameraSlug)) {
            continue;
        }

        $events[] = $event;
        if (count($events) >= $limit) {
            break;
        }
    }

    return $events;
}

function plasma_event_from_record(array $row): array
{
    $decodedMetrics = json_decode((string) ($row['metrics_json'] ?? '{}'), true);

    return [
        'id' => trim((string) ($row['id'] ?? '')),
        'event' => trim((string) ($row['event'] ?? 'signal')),
        'source' => trim((string) ($row['source'] ?? 'runtime')),
        'camera' => trim((string) ($row['camera'] ?? 'unknown')),
        'land_slug' => trim((string) ($row['land_slug'] ?? '')),
        'timestamp' => trim((string) ($row['timestamp'] ?? '')),
        'received_at' => trim((string) ($row['received_at'] ?? '')),
        'message' => plasma_compact_text((string) ($row['message'] ?? '')),
        'metrics' => is_array($decodedMetrics) ? $decodedMetrics : [],
    ];
}

function plasma_sync_legacy_shadow_log(?PDO $pdo = null, bool $force = false): void
{
    static $writeCounter = 0;

    $path = plasma_event_log_path();
    $needsCompaction = plasma_shadow_log_needs_compaction($path);
    if (!$force) {
        $writeCounter += 1;
        clearstatcache(true, $path);
        $size = is_file($path) ? filesize($path) : 0;
        $size = is_int($size) ? $size : 0;
        if ($writeCounter < plasma_shadow_log_compact_interval() && $size > 0 && !$needsCompaction) {
            return;
        }
    }

    $writeCounter = 0;
    $lines = plasma_shadow_log_lines_for_sync($pdo);

    plasma_write_shadow_log_lines($lines);
}

function plasma_ensure_legacy_shadow_log(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }
    $checked = true;

    $path = plasma_event_log_path();
    clearstatcache(true, $path);
    $size = is_file($path) ? filesize($path) : 0;
    $size = is_int($size) ? $size : 0;
    if ($size > 0 && !plasma_shadow_log_needs_compaction($path)) {
        return;
    }

    try {
        plasma_sync_legacy_shadow_log($pdo, true);
    } catch (Throwable $exception) {
        error_log('plasma startup shadow sync skipped: ' . $exception->getMessage());
    }
}

function plasma_shadow_log_needs_compaction(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return true;
    }

    clearstatcache(true, $path);
    $size = filesize($path);
    if (is_int($size) && $size > plasma_shadow_log_max_bytes()) {
        return true;
    }

    return count(plasma_tail_lines($path, plasma_shadow_log_max_events() + 1)) > plasma_shadow_log_max_events();
}

function plasma_shadow_log_lines_for_sync(?PDO $pdo = null): array
{
    if ($pdo instanceof PDO && plasma_database_event_count($pdo) > 0) {
        return plasma_shadow_log_lines_from_database($pdo);
    }

    return plasma_shadow_log_lines_from_file();
}

function plasma_shadow_log_lines_from_database(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT id, event, source, camera, land_slug, timestamp, received_at, message, metrics_json
         FROM plasma_events
         ORDER BY received_at DESC, rowid DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', plasma_shadow_log_max_events(), PDO::PARAM_INT);
    $statement->execute();

    $events = [];
    foreach ($statement->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $events[] = plasma_event_from_record($row);
    }

    $events = array_reverse($events);
    $lines = [];
    foreach ($events as $event) {
        $line = plasma_event_json_line($event);
        if ($line !== null) {
            $lines[] = $line;
        }
    }

    return $lines;
}

function plasma_shadow_log_lines_from_file(): array
{
    $path = plasma_event_log_path();
    if (!is_readable($path) || is_dir($path)) {
        return [];
    }

    return plasma_tail_lines($path, plasma_shadow_log_max_events());
}

function plasma_event_json_line(array $event): ?string
{
    $payload = [
        'id' => trim((string) ($event['id'] ?? '')),
        'event' => trim((string) ($event['event'] ?? 'signal')),
        'source' => trim((string) ($event['source'] ?? 'runtime')),
        'camera' => trim((string) ($event['camera'] ?? 'unknown')),
        'land_slug' => trim((string) ($event['land_slug'] ?? '')),
        'timestamp' => trim((string) ($event['timestamp'] ?? '')),
        'received_at' => trim((string) ($event['received_at'] ?? '')),
        'message' => trim((string) ($event['message'] ?? '')),
        'metrics' => is_array($event['metrics'] ?? null) ? $event['metrics'] : [],
    ];

    $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($line) ? $line : null;
}

function plasma_write_shadow_log_lines(array $lines): void
{
    $dir = plasma_log_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de preparer le journal plasma.');
    }

    $path = plasma_event_log_path();
    $tempPath = $path . '.tmp';
    $filtered = array_values(array_filter(
        array_map(
            static fn ($line): string => trim((string) $line),
            $lines
        ),
        static fn (string $line): bool => $line !== ''
    ));
    $payload = $filtered === [] ? '' : implode(PHP_EOL, $filtered) . PHP_EOL;

    if (file_put_contents($tempPath, $payload, LOCK_EX) === false || !rename($tempPath, $path)) {
        @unlink($tempPath);
        throw new RuntimeException('Impossible de compacter le journal plasma.');
    }
}

function plasma_append_shadow_log_event(array $event): void
{
    $line = plasma_event_json_line($event);
    if ($line === null) {
        throw new RuntimeException('Impossible d encoder le journal plasma.');
    }

    $dir = plasma_log_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de preparer le journal plasma.');
    }

    if (file_put_contents(plasma_event_log_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Impossible d ecrire le journal plasma.');
    }
}

function plasma_tail_lines(string $path, int $limit): array
{
    $lineLimit = max(1, $limit);
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return [];
    }

    $buffer = '';
    $chunkSize = 8192;
    fseek($handle, 0, SEEK_END);
    $position = ftell($handle);
    if (!is_int($position)) {
        fclose($handle);
        return [];
    }

    while ($position > 0 && substr_count($buffer, "\n") <= $lineLimit) {
        $readSize = min($chunkSize, $position);
        $position -= $readSize;
        fseek($handle, $position);
        $chunk = fread($handle, $readSize);
        if (!is_string($chunk) || $chunk === '') {
            break;
        }
        $buffer = $chunk . $buffer;
    }

    fclose($handle);

    $lines = preg_split('/\r\n|\n|\r/', $buffer);
    if (!is_array($lines)) {
        return [];
    }

    $lines = array_values(array_filter(
        $lines,
        static fn (string $line): bool => trim($line) !== ''
    ));

    return array_slice($lines, -$lineLimit);
}

function plasma_event_matches_camera_slug(array $event, string $cameraSlug): bool
{
    $slug = strtolower(trim($cameraSlug));
    if ($slug === '') {
        return false;
    }

    foreach (['land_slug', 'source', 'camera'] as $key) {
        $value = strtolower(trim((string) ($event[$key] ?? '')));
        if ($value === $slug) {
            return true;
        }
    }

    return false;
}

function plasma_metric_value(array $metrics, string $key): ?float
{
    if (!array_key_exists($key, $metrics)) {
        return null;
    }

    $value = $metrics[$key];
    if (!is_numeric($value)) {
        return null;
    }

    return max(0.0, min(1.0, (float) $value));
}

function plasma_camera_metric_value(array $metrics, string $key): ?float
{
    if (!array_key_exists($key, $metrics) || !is_numeric($metrics[$key])) {
        return null;
    }

    $value = (float) $metrics[$key];
    if ($key === 'frame_luma') {
        return max(0.0, min(1.0, $value));
    }

    if ($key === 'contour_count') {
        return max(0.0, min(1.0, $value / 6.0));
    }

    if ($key === 'largest_area') {
        return max(0.0, min(1.0, sqrt(max(0.0, $value) / 48000.0)));
    }

    return null;
}

function plasma_event_epoch_seconds(array $event): ?int
{
    foreach (['timestamp', 'received_at'] as $key) {
        $value = trim((string) ($event[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return (int) $timestamp;
        }
    }

    return null;
}

function plasma_event_age_seconds(array $event): ?int
{
    $epoch = plasma_event_epoch_seconds($event);
    if ($epoch === null) {
        return null;
    }

    return max(0, time() - $epoch);
}

function plasma_age_label(?int $ageSeconds): string
{
    if ($ageSeconds === null) {
        return 'temps inconnu';
    }

    if ($ageSeconds < 60) {
        return $ageSeconds . 's';
    }

    if ($ageSeconds < 3600) {
        return (int) floor($ageSeconds / 60) . 'min';
    }

    return (int) floor($ageSeconds / 3600) . 'h';
}

function plasma_events_freshness(array $events): array
{
    if ($events === []) {
        return [
            'freshness' => 'idle',
            'stale' => false,
            'age_seconds' => null,
            'latest_at' => null,
            'stale_after_seconds' => plasma_stale_after_seconds(),
        ];
    }

    $latest = $events[0];
    $ageSeconds = plasma_event_age_seconds($latest);
    $staleAfter = plasma_stale_after_seconds();
    $stale = $ageSeconds !== null && $ageSeconds > $staleAfter;

    return [
        'freshness' => $stale ? 'stale' : 'fresh',
        'stale' => $stale,
        'age_seconds' => $ageSeconds,
        'latest_at' => trim((string) (($latest['timestamp'] ?? '') !== '' ? $latest['timestamp'] : ($latest['received_at'] ?? ''))) ?: null,
        'stale_after_seconds' => $staleAfter,
    ];
}

function plasma_weather_from_events(array $events): array
{
    $freshness = plasma_events_freshness($events);
    if ($events === []) {
        return [
            'tone' => 'idle',
            'badge' => 'veille',
            'lead' => 'Aucune meteo plasma publique n est remontee pour l instant.',
            'detail' => 'Le lab attend encore une premiere membrane distante ou un signal Pi.',
            'energy' => 0.0,
            'count' => 0,
            'freshness' => $freshness['freshness'],
            'stale' => $freshness['stale'],
            'age_seconds' => $freshness['age_seconds'],
            'latest_at' => $freshness['latest_at'],
            'stale_after_seconds' => $freshness['stale_after_seconds'],
        ];
    }

    $sums = [
        'presence' => 0.0,
        'motion' => 0.0,
        'audio' => 0.0,
        'light' => 0.0,
        'device_volume' => 0.0,
        'camera_area' => 0.0,
        'camera_contours' => 0.0,
        'camera_luma' => 0.0,
    ];
    $counts = [
        'presence' => 0,
        'motion' => 0,
        'audio' => 0,
        'light' => 0,
        'device_volume' => 0,
        'camera_area' => 0,
        'camera_contours' => 0,
        'camera_luma' => 0,
    ];
    $sourceLabels = [];

    foreach ($events as $event) {
        $source = trim((string) ($event['source'] ?? 'runtime'));
        if ($source !== '') {
            $sourceLabels[$source] = true;
        }

        $metrics = is_array($event['metrics'] ?? null) ? $event['metrics'] : [];
        foreach (array_keys($sums) as $metricKey) {
            $value = str_starts_with($metricKey, 'camera_')
                ? plasma_camera_metric_value($metrics, match ($metricKey) {
                    'camera_area' => 'largest_area',
                    'camera_contours' => 'contour_count',
                    'camera_luma' => 'frame_luma',
                    default => '',
                })
                : plasma_metric_value($metrics, $metricKey);
            if ($value === null) {
                continue;
            }

            $sums[$metricKey] += $value;
            $counts[$metricKey] += 1;
        }
    }

    $averages = [];
    foreach ($sums as $metricKey => $sum) {
        $averages[$metricKey] = $counts[$metricKey] > 0 ? $sum / $counts[$metricKey] : 0.0;
    }

    $energy = ($averages['presence'] * 0.34)
        + ($averages['motion'] * 0.28)
        + ($averages['audio'] * 0.2)
        + ($averages['light'] * 0.18);
    $cameraEnergy = ($averages['camera_area'] * 0.48)
        + ($averages['camera_contours'] * 0.22)
        + ($averages['camera_luma'] * 0.2)
        + (min(1.0, count($events) / 4.0) * 0.1);

    if ($energy < $cameraEnergy) {
        $energy = $cameraEnergy;
    }

    if ($energy >= 0.7) {
        $tone = 'live';
        $badge = 'surge';
        $lead = 'Le plasma monte haut: membrane, souffle et mouvement laissent une houle nette.';
    } elseif ($energy >= 0.42) {
        $tone = 'live';
        $badge = 'actif';
        $lead = 'Le plasma reste actif et lisible: plusieurs flux traversent encore le tore.';
    } elseif ($energy >= 0.18) {
        $tone = 'replay';
        $badge = 'drift';
        $lead = 'Le plasma derive encore doucement: quelques signes tiennent le champ ouvert.';
    } else {
        $tone = 'idle';
        $badge = 'bas';
        $lead = 'Le plasma reste bas mais non vide: la trace tient plus qu elle ne pulse.';
    }

    if ($freshness['stale']) {
        $tone = 'stale';
        $badge = 'stale';
        $lead = 'Les traces existent encore, mais leur chaleur est tombee.';
    }

    $detailParts = [
        'presence ' . (int) round($averages['presence'] * 100) . '%',
        'mouvement ' . (int) round($averages['motion'] * 100) . '%',
        'souffle ' . (int) round($averages['audio'] * 100) . '%',
        'lumiere ' . (int) round($averages['light'] * 100) . '%',
    ];
    if ($counts['camera_area'] > 0 || $counts['camera_contours'] > 0 || $counts['camera_luma'] > 0) {
        $detailParts[] = 'cadre ' . (int) round($averages['camera_area'] * 100) . '%';
        $detailParts[] = 'contours ' . (int) round($averages['camera_contours'] * 100) . '%';
        $detailParts[] = 'luma cam ' . (int) round($averages['camera_luma'] * 100) . '%';
    }
    if ($counts['device_volume'] > 0) {
        $detailParts[] = 'niveau ' . (int) round($averages['device_volume'] * 100) . '%';
    }

    $latestMetrics = is_array($events[0]['metrics'] ?? null) ? $events[0]['metrics'] : [];
    if ((plasma_metric_value($latestMetrics, 'native_silence') ?? 0) > 0.5) {
        $detailParts[] = 'silence natif';
    } elseif ((plasma_metric_value($latestMetrics, 'silence_intent') ?? 0) > 0.5) {
        $detailParts[] = 'silence web';
    }

    if ((plasma_metric_value($latestMetrics, 'standalone') ?? 0) > 0.5) {
        $detailParts[] = 'app installee';
    }

    if ((plasma_metric_value($latestMetrics, 'visibility_hidden') ?? 0) > 0.5) {
        $detailParts[] = 'hors champ';
    }

    if ($freshness['age_seconds'] !== null) {
        $detailParts[] = 'dernier passage ' . plasma_age_label($freshness['age_seconds']);
    }

    $sourceList = implode(', ', array_keys($sourceLabels));
    $detail = implode(' · ', $detailParts);
    if ($sourceList !== '') {
        $detail .= ' · sources ' . $sourceList;
    }

    return [
        'tone' => $tone,
        'badge' => $badge,
        'lead' => $lead,
        'detail' => $detail,
        'energy' => round($freshness['stale'] ? min($energy * 0.24, 0.18) : $energy, 3),
        'count' => count($events),
        'freshness' => $freshness['freshness'],
        'stale' => $freshness['stale'],
        'age_seconds' => $freshness['age_seconds'],
        'latest_at' => $freshness['latest_at'],
        'stale_after_seconds' => $freshness['stale_after_seconds'],
    ];
}

function plasma_public_allowed_origins(): array
{
    $origins = plasma_configured_allowed_origins();
    $origins[] = request_public_origin();

    return array_values(array_unique(array_filter(
        $origins,
        static fn ($origin): bool => is_string($origin) && $origin !== ''
    )));
}

function plasma_public_resolve_origin(?string $origin = null): ?string
{
    $candidate = trim((string) ($origin ?? ($_SERVER['HTTP_ORIGIN'] ?? '')));
    if ($candidate === '') {
        return null;
    }

    return in_array($candidate, plasma_public_allowed_origins(), true) ? $candidate : null;
}
