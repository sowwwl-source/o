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

function write_land(array $land): void
{
    $slug = normalize_username((string) ($land['slug'] ?? ''));
    ensure_lands_dir();

    $path = land_file_path($slug);
    $encoded = json_encode($land, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (!is_string($encoded) || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Impossible de mettre à jour cette terre pour le moment.');
    }
}

function validate_land_password(string $password): string
{
    $password = (string) $password;

    if (strlen($password) < AUTH_MIN_PASSWORD_LENGTH) {
        throw new InvalidArgumentException('Le secret doit contenir au moins ' . AUTH_MIN_PASSWORD_LENGTH . ' caractères.');
    }

    return $password;
}

function land_has_password_hash(array $land): bool
{
    $hash = trim((string) ($land['password_hash'] ?? ''));
    return $hash !== '';
}

function land_visual_catalog(): array
{
    return [
        'culbu1on' => [
            'label' => '(c)ulbu1o(n)',
            'tone' => 'équilibre · oscillation',
            'lambda_range' => [440, 560],
        ],
        'dur3rb' => [
            'label' => '(d)ur3r(b)',
            'tone' => 'référence · mesure',
            'lambda_range' => [400, 700],
        ],
        'tocu' => [
            'label' => 't(o)C.u',
            'tone' => 'décision · tension',
            'lambda_range' => [610, 690],
        ],
        'collective' => [
            'label' => 'collectif',
            'tone' => 'str3m public',
            'lambda_range' => [500, 580],
        ],
    ];
}

function land_visual_seed(array $land): string
{
    $slug = trim((string) ($land['slug'] ?? ''));
    $timezone = trim((string) ($land['timezone'] ?? ''));
    $createdAt = trim((string) ($land['created_at'] ?? ''));
    $username = trim((string) ($land['username'] ?? ''));

    return implode('|', [
        $slug,
        $timezone,
        $createdAt,
        $username,
        'visual-v1',
    ]);
}

function land_visual_program_from_seed(string $seed): string
{
    $catalog = land_visual_catalog();
    $programs = ['culbu1on', 'dur3rb', 'tocu'];
    $hash = hash('sha256', $seed . '|program');
    $bucket = hexdec(substr($hash, 0, 8)) % count($programs);
    $program = $programs[$bucket] ?? 'culbu1on';

    return isset($catalog[$program]) ? $program : 'culbu1on';
}

function land_visual_lambda_from_seed(string $seed, string $program): int
{
    $catalog = land_visual_catalog();
    $definition = $catalog[$program] ?? $catalog['collective'];
    [$minimum, $maximum] = $definition['lambda_range'];
    $spread = max(1, (int) $maximum - (int) $minimum);
    $hash = hash('sha256', $seed . '|lambda');
    $value = hexdec(substr($hash, 8, 8)) % ($spread + 1);

    return (int) $minimum + (int) $value;
}

function land_visual_profile(array $land): array
{
    $catalog = land_visual_catalog();
    $storedProgram = trim((string) ($land['land_program'] ?? ''));
    $seed = land_visual_seed($land);
    $program = isset($catalog[$storedProgram]) && $storedProgram !== 'collective'
        ? $storedProgram
        : land_visual_program_from_seed($seed);
    $storedLambda = (int) ($land['lambda_nm'] ?? 0);
    $lambda = $storedLambda >= 380 && $storedLambda <= 780
        ? $storedLambda
        : land_visual_lambda_from_seed($seed, $program);
    $definition = $catalog[$program] ?? $catalog['culbu1on'];

    return [
        'program' => $program,
        'label' => (string) ($definition['label'] ?? $program),
        'tone' => (string) ($definition['tone'] ?? ''),
        'lambda_nm' => $lambda,
    ];
}

function land_collective_profile(?string $mood = null): array
{
    $catalog = land_visual_catalog();
    $moodKey = trim((string) $mood);
    $lambda = match ($moodKey) {
        'nocturnal' => 468,
        'dense' => 515,
        default => 548,
    };
    $definition = $catalog['collective'];

    return [
        'program' => 'collective',
        'label' => (string) ($definition['label'] ?? 'collectif'),
        'tone' => (string) ($definition['tone'] ?? 'str3m public'),
        'lambda_nm' => $lambda,
    ];
}

function create_land(string $username, string $timezone, ?string $password = null): array
{
    $username = trim(preg_replace('/\s+/u', ' ', trim($username)) ?? trim($username));
    $timezone = validate_timezone($timezone);
    $passwordHash = '';

    if ($password !== null) {
        $passwordHash = password_hash(validate_land_password($password), PASSWORD_DEFAULT);
    }

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
        'password_hash' => $passwordHash,
        'created_at' => gmdate(DATE_ATOM),
    ];

    $visualProfile = land_visual_profile($land);
    $land['land_program'] = (string) $visualProfile['program'];
    $land['lambda_nm'] = (int) $visualProfile['lambda_nm'];

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

function authenticate_land(string $identifier, string $password): ?array
{
    $land = find_land($identifier);
    if (!$land || !land_has_password_hash($land)) {
        return null;
    }

    $hash = (string) ($land['password_hash'] ?? '');
    if (!password_verify($password, $hash)) {
        return null;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $land['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        write_land($land);
    }

    return $land;
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
