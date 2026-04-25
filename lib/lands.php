<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function string_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function normalize_username(string $username): string
{
    $candidate = trim($username);
    $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate) : $candidate;
    $ascii = is_string($ascii) && $ascii !== '' ? $ascii : $candidate;
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $ascii) ?? '';
    $slug = strtolower(trim($slug, '-'));

    if ($slug === '') {
        throw new InvalidArgumentException('Choisis un nom d’usage lisible en lettres ou en chiffres.');
    }

    return substr($slug, 0, 42);
}

function preview_land_slug(string $username): string
{
    try {
        return normalize_username($username);
    } catch (InvalidArgumentException $exception) {
        return 'terre';
    }
}

function validate_timezone(string $timezone): string
{
    $timezone = trim($timezone);

    if ($timezone === '') {
        throw new InvalidArgumentException('Le fuseau horaire manque encore.');
    }

    try {
        new DateTimeZone($timezone);
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('Ce fuseau horaire ne ressemble pas à un fuseau valide.');
    }

    return $timezone;
}

function lands_dir(): string
{
    return LANDS_DIR;
}

function ensure_lands_dir(): void
{
    $directory = lands_dir();

    if (is_dir($directory)) {
        if (!is_writable($directory)) {
            throw new RuntimeException('Le stockage des terres n’est pas accessible pour le moment.');
        }

        return;
    }

    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de préparer le sol de stockage.');
    }

    if (!is_writable($directory)) {
        throw new RuntimeException('Le stockage des terres n’est pas accessible pour le moment.');
    }
}

function land_file_path(string $slug): string
{
    return lands_dir() . DIRECTORY_SEPARATOR . $slug . '.json';
}

function open_land_file_for_creation(string $path): mixed
{
    if (is_file($path)) {
        throw new RuntimeException('Cette terre existe déjà.');
    }

    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Le stockage des terres n’est pas accessible pour le moment.');
    }

    $handle = @fopen($path, 'x');
    if ($handle !== false) {
        return $handle;
    }

    clearstatcache(true, $path);
    if (is_file($path)) {
        throw new RuntimeException('Cette terre existe déjà.');
    }

    $lastError = error_get_last();
    $lastMessage = strtolower((string) ($lastError['message'] ?? ''));
    if (str_contains($lastMessage, 'permission denied')) {
        throw new RuntimeException('Le stockage des terres n’est pas accessible pour le moment.');
    }

    throw new RuntimeException('Impossible d’écrire cette terre pour le moment.');
}

function create_land(string $username, string $timezone): array
{
    $username = trim(preg_replace('/\s+/u', ' ', trim($username)) ?? trim($username));
    $timezone = validate_timezone($timezone);

    if (string_length($username) < 2 || string_length($username) > 42) {
        throw new InvalidArgumentException('Le nom d’usage doit tenir entre 2 et 42 caractères.');
    }

    $slug = normalize_username($username);
    ensure_lands_dir();

    $land = [
        'username' => $username,
        'slug' => $slug,
        'email_virtual' => $slug . '@o.local',
        'timezone' => $timezone,
        'zone_code' => $timezone,
        'created_at' => gmdate(DATE_ATOM),
    ];

    $path = land_file_path($slug);
    $handle = open_land_file_for_creation($path);

    $encoded = json_encode($land, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($encoded)) {
        fclose($handle);
        @unlink($path);
        throw new RuntimeException('Impossible d’écrire cette terre pour le moment.');
    }

    $written = fwrite($handle, $encoded . PHP_EOL);
    fclose($handle);

    if ($written === false) {
        @unlink($path);
        throw new RuntimeException('Impossible d’écrire cette terre pour le moment.');
    }

    return $land;
}

function find_land(string $identifier): ?array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return null;
    }

    $slug = normalize_username($identifier);
    $path = land_file_path($slug);

    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function land_snapshot(): array
{
    ensure_lands_dir();
    $files = glob(lands_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
    $lands = [];

    foreach ($files as $file) {
        $raw = file_get_contents($file);
        if ($raw === false) {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        $decoded['_mtime'] = filemtime($file) ?: 0;
        $lands[] = $decoded;
    }

    usort(
        $lands,
        static fn (array $left, array $right): int => ($right['_mtime'] ?? 0) <=> ($left['_mtime'] ?? 0)
    );

    return $lands;
}

function human_created_label(?string $createdAt): ?string
{
    if (!$createdAt) {
        return null;
    }

    try {
        $date = new DateTimeImmutable($createdAt);
    } catch (Throwable $exception) {
        return null;
    }

    return $date
        ->setTimezone(new DateTimeZone(DEFAULT_TIMEZONE))
        ->format('d.m.Y, H:i');
}

function land_pulse(): array
{
    $lands = land_snapshot();
    $timezones = [];

    foreach ($lands as $land) {
        if (!empty($land['timezone'])) {
            $timezones[(string) $land['timezone']] = true;
        }
    }

    $latest = $lands[0] ?? null;

    return [
        'count' => count($lands),
        'timezones' => count($timezones),
        'latest_created_label' => human_created_label($latest['created_at'] ?? null),
        'latest_summary' => $latest
            ? 'Dernier seuil depuis ' . (string) ($latest['timezone'] ?? DEFAULT_TIMEZONE) . '.'
            : 'Aucune terre posée pour l’instant.',
    ];
}
