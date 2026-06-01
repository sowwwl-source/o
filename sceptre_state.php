<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$deviceSlug = normalize_sceptre_slug((string) ($_GET['device'] ?? 'ensemble'));
$payload = sceptre_read_state($deviceSlug);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
