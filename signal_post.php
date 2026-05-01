<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/signal');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$land = current_authenticated_land();
if (!$land) {
    header('Location: /signal?error=auth', true, 303);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf_token($token)) {
    header('Location: /signal?error=csrf', true, 303);
    exit;
}

try {
    $signal = create_signal($_POST, $land);
    header('Location: /signal_item.php?id=' . rawurlencode((string) $signal['id']) . '&created=1', true, 303);
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: /signal?error=validation', true, 303);
    exit;
} catch (RuntimeException $exception) {
    header('Location: /signal?error=storage', true, 303);
    exit;
}