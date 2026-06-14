<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

$deviceSlug = normalize_sceptre_slug((string) ($_GET['device'] ?? 'ensemble'));
$payload = sceptre_read_state($deviceSlug);

o_json_response(200, $payload, [
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    'pretty' => true,
    'extra_headers' => ['Pragma: no-cache'],
]);
