<?php
declare(strict_types=1);

// Note: this file is included early by `/index.php` for the `sowwwl.com` host.
// It intentionally does not load the main `config.php` bootstrap (which would
// start a different session). Therefore we must set the relevant headers here.

header_remove('X-Powered-By');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../../lib/mailer.php';

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

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('sowwwl_admin_session');
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

function ensure_runtime_dir(string $runtimeDir): bool
{
    return is_dir($runtimeDir) || @mkdir($runtimeDir, 0775, true);
}

function admin_log_path(): string
{
    $runtimeDir = is_dir('/var/www/runtime') ? '/var/www/runtime' : sys_get_temp_dir();
    return rtrim($runtimeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sowwwl_admin.log';
}

function admin_log(string $event, string $email, array $context = []): void
{
    $path = admin_log_path();
    $runtimeDir = dirname($path);

    if (!ensure_runtime_dir($runtimeDir)) {
        return;
    }

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $contextJson = '';
    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        $contextJson = is_string($encoded) ? $encoded : '';
    }

    $entry = sprintf(
        "[%s] %s email=%s ip=%s uri=%s ua=%s context=%s\n",
        date('c'),
        $event,
        $email,
        trim(explode(',', (string) $ip)[0] ?? ''),
        $uri,
        $userAgent,
        $contextJson
    );

    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
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

function runtime_dir(): string
{
    return is_dir('/var/www/runtime') ? '/var/www/runtime' : sys_get_temp_dir();
}

function base_url(): string
{
    $scheme = request_is_secure() ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'sowwwl.com'));
    if ($host === '') {
        $host = 'sowwwl.com';
    }

    return $scheme . '://' . $host;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function parse_email_list(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }

    $parts = preg_split('/[;,\s]+/', $raw) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $candidate = normalize_email((string) $part);
        if ($candidate === '') {
            continue;
        }
        $emails[] = $candidate;
    }

    return array_values(array_unique($emails));
}

function base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function base64url_decode(string $encoded): string
{
    $padded = strtr($encoded, '-_', '+/');
    $padLen = (4 - (strlen($padded) % 4)) % 4;
    $padded .= str_repeat('=', $padLen);
    $decoded = base64_decode($padded, true);
    return is_string($decoded) ? $decoded : '';
}

function magic_links_dir(): string
{
    return rtrim(runtime_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'magic-links';
}

function magic_nonce_path(string $nonce): string
{
    return magic_links_dir() . DIRECTORY_SEPARATOR . sha1($nonce) . '.json';
}

function magic_rate_limit_dir(): string
{
    return rtrim(runtime_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rate-limit' . DIRECTORY_SEPARATOR . 'magic-link';
}

function enforce_rate_limit(string $action, int $maxAttempts, int $windowSeconds): void
{
    $dir = magic_rate_limit_dir();
    if (!ensure_runtime_dir($dir)) {
        throw new RuntimeException('Unable to enforce rate limit.');
    }

    $key = sha1($action . '|' . client_ip());
    $path = $dir . DIRECTORY_SEPARATOR . $key . '.json';
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
        throw new RuntimeException('Too many attempts. Please try again later.');
    }

    $entries[] = $now;
    $encoded = json_encode($entries, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write rate limit state.');
    }
}

function magic_token_create(string $email, string $secret, int $ttlSeconds = 900): string
{
    $email = normalize_email($email);
    $nonce = bin2hex(random_bytes(16));
    $exp = time() + max(60, $ttlSeconds);

    $payload = [
        'e' => $email,
        'exp' => $exp,
        'n' => $nonce,
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        throw new RuntimeException('Unable to create token.');
    }

    $payloadB64 = base64url_encode($payloadJson);
    $sig = hash_hmac('sha256', $payloadB64, $secret, true);
    $sigB64 = base64url_encode($sig);
    $token = $payloadB64 . '.' . $sigB64;

    $dir = magic_links_dir();
    if (!ensure_runtime_dir($dir)) {
        throw new RuntimeException('Unable to store token.');
    }

    $record = json_encode(['email' => $email, 'exp' => $exp], JSON_UNESCAPED_SLASHES);
    if (!is_string($record) || file_put_contents(magic_nonce_path($nonce), $record . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist token.');
    }

    return $token;
}

function magic_token_verify_and_consume(string $token, string $secret): array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Invalid token.');
    }

    [$payloadB64, $sigB64] = $parts;
    if ($payloadB64 === '' || $sigB64 === '') {
        throw new RuntimeException('Invalid token.');
    }

    $expectedSig = hash_hmac('sha256', $payloadB64, $secret, true);
    $providedSig = base64url_decode($sigB64);
    if ($providedSig === '' || !hash_equals($expectedSig, $providedSig)) {
        throw new RuntimeException('Invalid token signature.');
    }

    $payloadJson = base64url_decode($payloadB64);
    $decoded = $payloadJson !== '' ? json_decode($payloadJson, true) : null;
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid token payload.');
    }

    $email = normalize_email((string) ($decoded['e'] ?? ''));
    $exp = (int) ($decoded['exp'] ?? 0);
    $nonce = (string) ($decoded['n'] ?? '');

    if ($email === '' || $nonce === '' || $exp <= 0) {
        throw new RuntimeException('Invalid token payload.');
    }

    if ($exp < time()) {
        throw new RuntimeException('Token expired.');
    }

    $path = magic_nonce_path($nonce);
    if (!is_file($path)) {
        throw new RuntimeException('Token already used or unknown.');
    }

    $raw = file_get_contents($path);
    $record = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($record)) {
        @unlink($path);
        throw new RuntimeException('Token record invalid.');
    }

    $recordEmail = normalize_email((string) ($record['email'] ?? ''));
    $recordExp = (int) ($record['exp'] ?? 0);
    if ($recordEmail !== $email || $recordExp !== $exp) {
        @unlink($path);
        throw new RuntimeException('Token record mismatch.');
    }

    @unlink($path);
    return ['email' => $email, 'exp' => $exp];
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

start_admin_session();

$adminEmail = trim((string) (getenv('SOWWWL_ADMIN_EMAIL') ?: '')) ?: 'pablo@sowwwl.com';
$magicAllowedEmailsRaw = trim((string) (getenv('SOWWWL_MAGIC_LINK_EMAILS') ?: ''));
$magicAllowedEmails = $magicAllowedEmailsRaw !== ''
    ? parse_email_list($magicAllowedEmailsRaw)
    : parse_email_list($adminEmail . ',0wlslw0@protonmail.com');
$adminEmailsRaw = trim((string) (getenv('SOWWWL_ADMIN_EMAILS') ?: ''));
$adminEmails = $adminEmailsRaw !== '' ? parse_email_list($adminEmailsRaw) : array_values(array_unique(array_merge([$adminEmail], $magicAllowedEmails)));

$magicSecret = trim((string) (getenv('SOWWWL_MAGIC_LINK_SECRET') ?: ''));
$magicDelivery = strtolower(trim((string) (getenv('SOWWWL_MAGIC_LINK_DELIVERY') ?: 'log')));
$magicFrom = trim((string) (getenv('SOWWWL_MAGIC_LINK_FROM') ?: ''));
$magicError = '';
$magicInfo = '';

$action = (string) ($_GET['action'] ?? '');
$magicToken = (string) ($_GET['token'] ?? '');

if ($action === 'magic_login' && $magicToken !== '') {
    try {
        if ($magicSecret === '' || str_starts_with($magicSecret, 'CHANGE_ME')) {
            throw new RuntimeException('Magic link is disabled.');
        }

        $result = magic_token_verify_and_consume($magicToken, $magicSecret);
        $email = normalize_email((string) ($result['email'] ?? ''));
        if ($email === '' || !in_array($email, $magicAllowedEmails, true)) {
            throw new RuntimeException('This magic link is not allowed.');
        }

        session_regenerate_id(true);
        $_SESSION['user_email'] = $email;
        admin_log('auth.magic.consume', $email);
        header('Location: /', true, 303);
        exit;
    } catch (Throwable $e) {
        $magicError = $e->getMessage();
        admin_log('auth.magic.consume.failure', $adminEmail, ['error' => $magicError]);
    }
}
$configuredPin = trim((string) (getenv('SOWWWL_ADMIN_PIN') ?: ''));
$adminPin = ($configuredPin !== '' && !str_starts_with($configuredPin, 'CHANGE_ME')) ? $configuredPin : 'pablo';
$error = '';

if (isset($_GET['logout'])) {
    $logoutEmail = normalize_email((string) ($_SESSION['user_email'] ?? ''));
    if ($logoutEmail !== '' && in_array($logoutEmail, $adminEmails, true)) {
        admin_log('auth.logout', $logoutEmail);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
    header('Location: /', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $submittedPassword = (string) $_POST['password'];

    if (hash_equals($adminPin, $submittedPassword)) {
        session_regenerate_id(true);
        $_SESSION['user_email'] = $adminEmail;
        admin_log('auth.login.success', $adminEmail);
        header('Location: ' . strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?'), true, 303);
        exit;
    }

    admin_log('auth.login.failure', $adminEmail);
    $error = 'Incorrect password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'magic_request')) {
    try {
        enforce_rate_limit('magic-request', 5, 600);

        if ($magicSecret === '' || str_starts_with($magicSecret, 'CHANGE_ME')) {
            throw new RuntimeException('Magic link is disabled on this server.');
        }

        $requestedEmail = normalize_email((string) ($_POST['email'] ?? ''));
        if ($requestedEmail === '' || !filter_var($requestedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email.');
        }

        // Always respond generically to avoid leaking allowlist state.
        $genericOk = 'If this email is allowed, a magic link will be delivered shortly.';

        if (!in_array($requestedEmail, $magicAllowedEmails, true)) {
            admin_log('auth.magic.request.denied', $requestedEmail);
            $magicInfo = $genericOk;
        } else {
            $token = magic_token_create($requestedEmail, $magicSecret, 15 * 60);
            $link = base_url() . '/?action=magic_login&token=' . rawurlencode($token);

            if ($magicDelivery === 'mail') {
                $sendError = null;
                $ok = sowwwl_send_magic_link_email($requestedEmail, $link, $sendError);
                if ($ok) {
                    admin_log('auth.magic.request.sent', $requestedEmail, ['delivery' => 'mail']);
                } else {
                    admin_log('auth.magic.request.send_failure', $requestedEmail, ['delivery' => 'mail', 'error' => (string) $sendError]);
                }
                $magicInfo = $genericOk;
            } elseif ($magicDelivery === 'display') {
                admin_log('auth.magic.request.generated', $requestedEmail, ['delivery' => 'display']);
                $magicInfo = 'Magic link generated (display mode): ' . $link;
            } else {
                admin_log('auth.magic.request.generated', $requestedEmail, ['delivery' => 'log', 'link' => $link]);
                $magicInfo = $genericOk;
            }
        }
    } catch (Throwable $e) {
        $magicError = $e->getMessage();
    }
}

$sessionEmail = normalize_email((string) ($_SESSION['user_email'] ?? ''));
$isAdmin = ($sessionEmail !== '' && in_array($sessionEmail, $adminEmails, true));
$userEmail = $isAdmin ? $sessionEmail : 'visitor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>sowwwl.com - Logs</title>
    <style>body { font-family: monospace; padding: 2rem; }</style>
</head>
<body>
    <?php if ($isAdmin): ?>
        <h2>Hello <?php echo h($userEmail); ?> (Admin)</h2>
        <p>Authentication events are written to the <strong>admin log</strong>.</p>
        <a href="?logout=1">Logout</a>
    <?php else: ?>
        <h2>Hello Visitor</h2>
        <p>Visitor traffic is no longer logged automatically.</p>
        <hr>
        <h3>Admin Access</h3>
        <?php if ($error !== ''): ?>
            <p style="color:red;"><?php echo h($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" placeholder="Admin username/password" autocomplete="current-password" required>
            <button type="submit">Login</button>
        </form>

        <hr>
        <h3>Magic Link</h3>
        <?php if ($magicError !== ''): ?>
            <p style="color:red;">Magic link error: <?php echo h($magicError); ?></p>
        <?php endif; ?>
        <?php if ($magicInfo !== ''): ?>
            <p><?php echo h($magicInfo); ?></p>
        <?php endif; ?>
        <form method="post" action="?action=magic_request">
            <input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
            <button type="submit">Send magic link</button>
        </form>
    <?php endif; ?>
</body>
</html>
