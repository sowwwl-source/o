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

    return rtrim((string) $slice) . '…';
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

function plasma_append_event(array $event): void
{
    $dir = plasma_log_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de préparer le journal plasma.');
    }

    $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false || file_put_contents(plasma_event_log_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Impossible d’écrire le journal plasma.');
    }
}

function plasma_recent_events(int $limit = 5): array
{
    $logFile = plasma_event_log_path();
    if (!is_readable($logFile) || is_dir($logFile)) {
        return [];
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || $lines === []) {
        return [];
    }

    $events = [];
    foreach (array_slice($lines, -$limit) as $line) {
        $decoded = json_decode((string) $line, true);
        if (!is_array($decoded)) {
            continue;
        }

        $events[] = [
            'id' => trim((string) ($decoded['id'] ?? '')),
            'event' => trim((string) ($decoded['event'] ?? 'signal')),
            'source' => trim((string) ($decoded['source'] ?? 'runtime')),
            'camera' => trim((string) ($decoded['camera'] ?? 'unknown')),
            'land_slug' => trim((string) ($decoded['land_slug'] ?? '')),
            'timestamp' => trim((string) ($decoded['timestamp'] ?? '')),
            'received_at' => trim((string) ($decoded['received_at'] ?? '')),
            'message' => plasma_compact_text((string) ($decoded['message'] ?? '')),
            'metrics' => is_array($decoded['metrics'] ?? null) ? $decoded['metrics'] : [],
        ];
    }

    return array_reverse($events);
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

function plasma_weather_from_events(array $events): array
{
    if ($events === []) {
        return [
            'tone' => 'idle',
            'badge' => 'veille',
            'lead' => 'Aucune météo plasma publique n’est remontée pour l’instant.',
            'detail' => 'Le lab attend encore une première membrane distante ou un signal Pi.',
            'energy' => 0.0,
            'count' => 0,
        ];
    }

    $sums = [
        'presence' => 0.0,
        'motion' => 0.0,
        'audio' => 0.0,
        'light' => 0.0,
        'device_volume' => 0.0,
    ];
    $counts = [
        'presence' => 0,
        'motion' => 0,
        'audio' => 0,
        'light' => 0,
        'device_volume' => 0,
    ];
    $sourceLabels = [];

    foreach ($events as $event) {
        $source = trim((string) ($event['source'] ?? 'runtime'));
        if ($source !== '') {
            $sourceLabels[$source] = true;
        }

        $metrics = is_array($event['metrics'] ?? null) ? $event['metrics'] : [];
        foreach (array_keys($sums) as $metricKey) {
            $value = plasma_metric_value($metrics, $metricKey);
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
        $lead = 'Le plasma dérive encore doucement: quelques signes tiennent le champ ouvert.';
    } else {
        $tone = 'idle';
        $badge = 'bas';
        $lead = 'Le plasma reste bas mais non vide: la trace tient plus qu’elle ne pulse.';
    }

    $detailParts = [
        'présence ' . (int) round($averages['presence'] * 100) . '%',
        'mouvement ' . (int) round($averages['motion'] * 100) . '%',
        'souffle ' . (int) round($averages['audio'] * 100) . '%',
        'lumière ' . (int) round($averages['light'] * 100) . '%',
    ];
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
        $detailParts[] = 'app installée';
    }

    if ((plasma_metric_value($latestMetrics, 'visibility_hidden') ?? 0) > 0.5) {
        $detailParts[] = 'hors champ';
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
        'energy' => round($energy, 3),
        'count' => count($events),
    ];
}

function plasma_public_allowed_origins(): array
{
    return [
        'https://sowwwl.xyz',
        'https://www.sowwwl.xyz',
        'https://lab.sowwwl.cloud',
        'https://www.lab.sowwwl.cloud',
    ];
}

function plasma_public_resolve_origin(?string $origin = null): ?string
{
    $candidate = trim((string) ($origin ?? ($_SERVER['HTTP_ORIGIN'] ?? '')));
    if ($candidate === '') {
        return null;
    }

    return in_array($candidate, plasma_public_allowed_origins(), true) ? $candidate : null;
}
