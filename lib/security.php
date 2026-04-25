<?php
declare(strict_types=1);

function bootstrap_request(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    start_secure_session();
    send_security_headers();
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('sowwwl_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => request_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function request_is_secure(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === 'https') {
        return true;
    }

    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    return str_contains($cfVisitor, '"scheme":"https"');
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Vary: Cookie');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
}

function site_origin(): string
{
    $publicOriginOverride = trim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: ''));
    if ($publicOriginOverride !== '') {
        return rtrim($publicOriginOverride, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return SITE_ORIGIN;
    }

    return (request_is_secure() ? 'https://' : 'http://') . $host;
}

function remember_form_rendered_at(): void
{
    $_SESSION['form_rendered_at'] = time();
}

function form_was_rendered_recently(int $minimumSeconds = 2): bool
{
    $renderedAt = (int) ($_SESSION['form_rendered_at'] ?? 0);
    return $renderedAt > 0 && (time() - $renderedAt) >= $minimumSeconds;
}

function csrf_token(): string
{
    $token = (string) ($_SESSION['csrf_token'] ?? '');

    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

function verify_csrf_token(?string $token): bool
{
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
}

function auth_land_slug(): ?string
{
    $slug = trim((string) ($_SESSION['auth_land_slug'] ?? ''));
    return $slug !== '' ? $slug : null;
}

function auth_is_land_session_for(?string $slug = null): bool
{
    $sessionSlug = auth_land_slug();
    if ($sessionSlug === null) {
        return false;
    }

    if ($slug === null || trim($slug) === '') {
        return true;
    }

    return hash_equals($sessionSlug, normalize_username($slug));
}

function current_authenticated_land(): ?array
{
    $slug = auth_land_slug();
    if ($slug === null) {
        return null;
    }

    try {
        $land = find_land($slug);
    } catch (InvalidArgumentException $exception) {
        $land = null;
    }

    if (!$land) {
        logout_land();
        return null;
    }

    return $land;
}

function login_land(array $land): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_secure_session();
    }

    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['auth_land_slug'] = (string) ($land['slug'] ?? '');
    $_SESSION['auth_land_username'] = (string) ($land['username'] ?? '');
    $_SESSION['auth_logged_in_at'] = time();
}

function logout_land(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    unset(
        $_SESSION['logged_in'],
        $_SESSION['auth_land_slug'],
        $_SESSION['auth_land_username'],
        $_SESSION['auth_logged_in_at']
    );

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    session_regenerate_id(true);
}

function client_ip(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $first = trim(explode(',', $candidate)[0] ?? '');
        if ($first === '') {
            continue;
        }

        $sanitized = preg_replace('/[^a-fA-F0-9:\.]/', '', $first) ?? '';
        if ($sanitized !== '') {
            return $sanitized;
        }
    }

    return 'unknown';
}

function ensure_rate_limit_dir(): void
{
    rate_limit_dir();
}

function rate_limit_dir(): string
{
    static $resolved = null;

    if (is_string($resolved) && $resolved !== '') {
        return $resolved;
    }

    $candidates = [
        RATE_LIMIT_DIR,
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sowwwl' . DIRECTORY_SEPARATOR . 'rate-limit',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (is_dir($candidate) && is_writable($candidate)) {
            $resolved = $candidate;
            return $resolved;
        }

        if (@mkdir($candidate, 0775, true) && is_dir($candidate)) {
            $resolved = $candidate;
            return $resolved;
        }
    }

    throw new RuntimeException('Impossible de préparer les garde-fous du formulaire.');
}

function rate_limit_file_path(string $action): string
{
    return rate_limit_dir() . DIRECTORY_SEPARATOR . sha1($action . '|' . client_ip()) . '.json';
}

function enforce_rate_limit(string $action, int $maxAttempts, int $windowSeconds): void
{
    ensure_rate_limit_dir();

    $path = rate_limit_file_path($action);
    $entries = [];

    if (is_file($path)) {
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($decoded)) {
            $entries = array_values(array_filter(
                $decoded,
                static fn ($timestamp): bool => is_int($timestamp) || ctype_digit((string) $timestamp)
            ));
        }
    }

    $now = time();
    $threshold = $now - $windowSeconds;
    $entries = array_values(array_filter($entries, static fn ($timestamp): bool => (int) $timestamp >= $threshold));

    if (count($entries) >= $maxAttempts) {
        throw new RuntimeException('Trop de tentatives depuis cette connexion. Réessaie dans quelques minutes.');
    }

    $entries[] = $now;

    $encoded = json_encode($entries, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d’écrire le garde-fou du formulaire.');
    }
}

function guard_land_creation_request(?string $csrfToken, string $honeypot): void
{
    if (trim($honeypot) !== '') {
        throw new RuntimeException('Impossible de valider la demande. Réessaie.');
    }

    if (!verify_csrf_token($csrfToken)) {
        throw new RuntimeException('Session expirée. Recharge la page et réessaie.');
    }

    enforce_rate_limit(
        'create-land',
        CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS,
        CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS
    );
}

function guard_land_login_request(?string $csrfToken): void
{
    if (!verify_csrf_token($csrfToken)) {
        throw new RuntimeException('Session expirée. Recharge la page et réessaie.');
    }

    enforce_rate_limit(
        'land-login',
        LAND_LOGIN_RATE_LIMIT_MAX_ATTEMPTS,
        LAND_LOGIN_RATE_LIMIT_WINDOW_SECONDS
    );
}
