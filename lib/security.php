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
    return SITE_ORIGIN;
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
    if (is_dir(RATE_LIMIT_DIR)) {
        return;
    }

    if (!mkdir(RATE_LIMIT_DIR, 0775, true) && !is_dir(RATE_LIMIT_DIR)) {
        throw new RuntimeException('Impossible de préparer les garde-fous du formulaire.');
    }
}

function rate_limit_file_path(string $action): string
{
    return RATE_LIMIT_DIR . DIRECTORY_SEPARATOR . sha1($action . '|' . client_ip()) . '.json';
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

    enforce_rate_limit(
        'create-land',
        CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS,
        CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS
    );
}
