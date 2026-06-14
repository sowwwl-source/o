<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

$origin = plasma_public_resolve_origin();
if (o_request_method() === 'OPTIONS') {
    if ($origin === null) {
        o_json_response(403, ['ok' => false, 'error' => 'origin_not_allowed']);
    }

    o_json_response(204, ['ok' => true], [
        'origin' => $origin,
        'cors_methods' => ['GET', 'OPTIONS'],
    ]);
}

if (o_request_method() !== 'GET') {
    o_allow_methods(['GET', 'OPTIONS']);
    o_json_response(405, ['ok' => false, 'error' => 'method_not_allowed'], [
        'origin' => $origin,
        'cors_methods' => ['GET', 'OPTIONS'],
    ]);
}

$limit = (int) ($_GET['limit'] ?? 6);
$limit = max(1, min(12, $limit));
$cameraSlug = trim((string) ($_GET['land_slug'] ?? $_GET['camera_slug'] ?? $_GET['camera'] ?? ''));
$scanLimit = $limit;

if ($cameraSlug !== '') {
    $requestedScanLimit = (int) ($_GET['scan'] ?? 48);
    $scanLimit = max($limit, min(120, max($requestedScanLimit, $limit * 4)));
}

$events = plasma_recent_events($scanLimit, $cameraSlug !== '' ? $cameraSlug : null);
$events = array_slice($events, 0, $limit);
$weather = plasma_weather_from_events($events);
$freshness = plasma_events_freshness($events);

o_json_response(200, [
    'ok' => true,
    'weather' => $weather,
    'events' => $events,
    'freshness' => $freshness,
], [
    'origin' => $origin,
    'cors_methods' => ['GET', 'OPTIONS'],
]);
