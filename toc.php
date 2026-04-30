<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: land.php');
    exit;
}

$username    = $_SESSION['username'];
$csrf_token  = $_SESSION['csrf_token'] ?? '';
$posted      = $_POST['csrf_token'] ?? '';

if (!hash_equals($csrf_token, $posted)) {
    header('Location: land.php');
    exit;
}

$action = $_POST['action'] ?? '';

// ── SEND T0C ─────────────────────────────────────────────────────────────────
if ($action === 'send') {
    $target = trim($_POST['target'] ?? '');

    if ($target !== '' && $target !== $username) {
        // Check that target land exists
        $stmtCheck = $pdo->prepare("SELECT username FROM lands WHERE username = ?");
        $stmtCheck->execute([$target]);

        if ($stmtCheck->fetch()) {
            // No existing liaison in either direction
            $stmtExist = $pdo->prepare("
                SELECT id, status FROM liaisons
                WHERE (land_a = ? AND land_b = ?) OR (land_a = ? AND land_b = ?)
            ");
            $stmtExist->execute([$username, $target, $target, $username]);
            $existing = $stmtExist->fetch();

            if (!$existing) {
                $pdo->prepare("INSERT INTO liaisons (land_a, land_b, status) VALUES (?, ?, 'pending')")
                    ->execute([$username, $target]);

                // Email notification
                $stmtEmail = $pdo->prepare("SELECT notification_email FROM lands WHERE username = ?");
                $stmtEmail->execute([$target]);
                $notifEmail = (string)($stmtEmail->fetchColumn() ?: '');
                if ($notifEmail !== '') {
                    send_toc_notification($notifEmail, $username, $target);
                }
            }
        }
    }

    header('Location: str3m.php');
    exit;
}

// ── ACCEPT T0C ───────────────────────────────────────────────────────────────
if ($action === 'accept') {
    $liaison_id = (int)($_POST['liaison_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT * FROM liaisons
        WHERE id = ? AND land_b = ? AND status = 'pending'
    ");
    $stmt->execute([$liaison_id, $username]);
    $liaison = $stmt->fetch();

    if ($liaison) {
        $pdo->prepare("UPDATE liaisons SET status = 'on' WHERE id = ?")
            ->execute([$liaison_id]);

        // Create p0rt with all three forms enabled
        $slug = bin2hex(random_bytes(6)); // 12-char hex
        $pdo->prepare("
            INSERT INTO ports (slug, liaison_id, has_cou12, has_coeur, has_core)
            VALUES (?, ?, 1, 1, 1)
        ")->execute([$slug, $liaison_id]);
        $port_id = (int)$pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO port_members (port_id, username) VALUES (?, ?), (?, ?)
        ")->execute([$port_id, $liaison['land_a'], $port_id, $username]);

        // Notify the t0c sender
        $stmtEmail = $pdo->prepare("SELECT notification_email FROM lands WHERE username = ?");
        $stmtEmail->execute([$liaison['land_a']]);
        $notifEmail = (string)($stmtEmail->fetchColumn() ?: '');
        if ($notifEmail !== '') {
            send_liaison_accepted_notification($notifEmail, $username, $liaison['land_a'], $slug);
        }
    }

    header('Location: land.php');
    exit;
}

// ── DECLINE T0C ──────────────────────────────────────────────────────────────
if ($action === 'decline') {
    $liaison_id = (int)($_POST['liaison_id'] ?? 0);
    $pdo->prepare("
        UPDATE liaisons SET status = 'off'
        WHERE id = ? AND land_b = ? AND status = 'pending'
    ")->execute([$liaison_id, $username]);

    header('Location: land.php');
    exit;
}

// ── CUT LIAISON ──────────────────────────────────────────────────────────────
if ($action === 'cut') {
    $liaison_id = (int)($_POST['liaison_id'] ?? 0);
    $pdo->prepare("
        UPDATE liaisons SET status = 'off'
        WHERE id = ? AND (land_a = ? OR land_b = ?) AND status = 'on'
    ")->execute([$liaison_id, $username, $username]);

    header('Location: land.php');
    exit;
}

header('Location: land.php');
exit;


// ── HELPERS ──────────────────────────────────────────────────────────────────

function send_toc_notification(string $to, string $sender, string $receiver): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $domain  = getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com';
    $link    = rtrim($domain, '/') . '/land';
    $subject = '=?UTF-8?B?' . base64_encode($sender . ' frappe à ta porte sur O.') . '?=';
    $body    = "{$sender} t'envoie un t0c.\n\nAccepte ou décline depuis ta terre :\n{$link}\n\n—\nO. réseau minimal";
    $headers = implode("\r\n", [
        'From: noreply@sowwwl.com',
        'Reply-To: noreply@sowwwl.com',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: O.',
    ]);
    @mail($to, $subject, $body, $headers);
}

function send_liaison_accepted_notification(string $to, string $accepter, string $sender, string $slug): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    $domain  = getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com';
    $link    = rtrim($domain, '/') . '/port/' . $slug;
    $subject = '=?UTF-8?B?' . base64_encode($accepter . ' a accepté ta liaison sur O.') . '?=';
    $body    = "{$accepter} a accepté ton t0c. Le p0rt est ouvert :\n{$link}\n\n—\nO. réseau minimal";
    $headers = implode("\r\n", [
        'From: noreply@sowwwl.com',
        'Reply-To: noreply@sowwwl.com',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: O.',
    ]);
    @mail($to, $subject, $body, $headers);
}
