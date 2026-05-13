<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();
$signalHref = o_route_path('/signal');
$signalItemHref = o_route_path('/signal_item.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$land = current_authenticated_land();
if (!$land) {
    header('Location: ' . $signalHref . '?error=auth', true, 303);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (!verify_csrf_token($token)) {
    header('Location: ' . $signalHref . '?error=csrf', true, 303);
    exit;
}

try {
    $signal = create_signal($_POST, $land);
    header('Location: ' . $signalItemHref . '?id=' . rawurlencode((string) $signal['id']) . '&created=1', true, 303);
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: ' . $signalHref . '?error=validation', true, 303);
    exit;
} catch (RuntimeException $exception) {
    header('Location: ' . $signalHref . '?error=storage', true, 303);
    exit;
}
