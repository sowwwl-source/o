<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function plasma_recent_json(int $status, array $payload, ?string $origin = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if ($origin !== null) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$origin = plasma_public_resolve_origin();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin === null) {
        plasma_recent_json(403, ['ok' => false, 'error' => 'origin_not_allowed']);
    }

    plasma_recent_json(204, ['ok' => true], $origin);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');
    plasma_recent_json(405, ['ok' => false, 'error' => 'method_not_allowed'], $origin);
}

$limit = (int) ($_GET['limit'] ?? 6);
$limit = max(1, min(12, $limit));
$events = plasma_recent_events($limit);
$weather = plasma_weather_from_events($events);

plasma_recent_json(200, [
    'ok' => true,
    'weather' => $weather,
    'events' => $events,
], $origin);
