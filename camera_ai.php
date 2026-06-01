<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

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

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
