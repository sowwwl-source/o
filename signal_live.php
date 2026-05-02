<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signal_mail.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$land = current_authenticated_land();
if (!$land) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'auth-required',
        'message' => 'Une terre authentifiée est nécessaire pour ouvrir le direct.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!signal_mail_tables_ready()) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'messaging-unavailable',
        'message' => 'La base Signal n’est pas initialisée.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$view = trim((string) ($_GET['view'] ?? 'signal'));
$targetIdentifier = trim((string) ($_GET['u'] ?? ''));

echo json_encode(
    signal_live_payload($land, $targetIdentifier, $view),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
