<?php
declare(strict_types=1);

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

function admin_log(string $event, string $email): void
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

    $entry = sprintf(
        "[%s] %s email=%s ip=%s uri=%s ua=%s\n",
        date('c'),
        $event,
        $email,
        trim(explode(',', (string) $ip)[0] ?? ''),
        $uri,
        $userAgent
    );

    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

start_admin_session();

$adminEmail = trim((string) (getenv('SOWWWL_ADMIN_EMAIL') ?: '')) ?: 'pablo@sowwwl.com';
$configuredPin = trim((string) (getenv('SOWWWL_ADMIN_PIN') ?: ''));
$adminPin = ($configuredPin !== '' && !str_starts_with($configuredPin, 'CHANGE_ME')) ? $configuredPin : 'pablo';
$error = '';

if (isset($_GET['logout'])) {
    if (($_SESSION['user_email'] ?? null) === $adminEmail) {
        admin_log('auth.logout', $adminEmail);
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

$isAdmin = (($_SESSION['user_email'] ?? null) === $adminEmail);
$userEmail = $isAdmin ? $adminEmail : 'visitor';
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
    <?php endif; ?>
</body>
</html>
