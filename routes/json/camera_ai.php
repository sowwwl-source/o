<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

$cameraSlug = normalize_camera_state_slug((string) ($_GET['camera'] ?? pocket_camera_slug()));
$payload = camera_ai_default_state($cameraSlug);
$statePath = camera_ai_state_path($cameraSlug);

if (is_file($statePath) && is_readable($statePath)) {
    $rawState = @file_get_contents($statePath);
    if (is_string($rawState) && $rawState !== '') {
        $decoded = json_decode($rawState, true);
        if (is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
            $payload['ok'] = true;
            $payload['camera'] = normalize_camera_state_slug((string) ($payload['camera'] ?? $cameraSlug));
        }
    }
}

$payload = camera_ai_normalize_freshness($payload);

o_json_response(200, $payload, [
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    'pretty' => true,
    'extra_headers' => ['Pragma: no-cache'],
]);
