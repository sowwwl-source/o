<?php
declare(strict_types=1);
session_start();

$adminLogFile = __DIR__ . '/admin.log';
$visitorLogFile = __DIR__ . '/visitor.log';
$adminEmail = 'pablo@sowwwl.com';
$adminPin = 'pablo'; // Simplified password

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $adminPin) {
        $_SESSION['user_email'] = $adminEmail;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $error = "Incorrect password.";
    }
}

$userEmail = $_SESSION['user_email'] ?? 'visitor';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$timestamp = date('c');

$logEntry = sprintf("[%s] %s %s %s %s\n", $timestamp, $userEmail, $ip, $uri, $userAgent);

if ($userEmail === $adminEmail) {
    file_put_contents($adminLogFile, $logEntry, FILE_APPEND | LOCK_EX);
} else {
    file_put_contents($visitorLogFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>sowwwl.com - Logs</title>
    <style>body { font-family: monospace; padding: 2rem; }</style>
</head>
<body>
    <?php if ($userEmail === $adminEmail): ?>
        <h2>Hello <?php echo htmlspecialchars($userEmail); ?> (Admin)</h2>
        <p>Your visit has been logged to the <strong>admin log</strong>.</p>
        <a href="?logout=1">Logout</a>
    <?php else: ?>
        <h2>Hello Visitor</h2>
        <p>Your visit has been logged to the <strong>visitor log</strong>.</p>
        <hr>
        <h3>Admin Access</h3>
        <?php if (!empty($error)) echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>'; ?>
        <form method="post">
            <input type="text" name="password" placeholder="Admin username/password" autocomplete="off" required>
            <button type="submit">Login</button>
        </form>
    <?php endif; ?>
</body>
</html>
