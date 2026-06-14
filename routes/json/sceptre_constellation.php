<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

$primaryDeviceSlug = normalize_sceptre_slug((string) ($_GET['primary'] ?? sceptre_primary_device_slug()));
$payload = sceptre_constellation_payload($primaryDeviceSlug);

o_json_response(200, $payload, [
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    'pretty' => true,
    'extra_headers' => ['Pragma: no-cache'],
]);
