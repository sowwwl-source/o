<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode([
        'error' => 'method_not_allowed',
        'message' => 'Use GET to retrieve map points.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

function map_timezone_center(string $timezone): array
{
    $timezone = trim($timezone);

    $exact = [
        'Europe/Paris' => [2.3522, 48.8566],
        'Europe/London' => [-0.1276, 51.5072],
        'Europe/Berlin' => [13.4050, 52.5200],
        'Europe/Madrid' => [-3.7038, 40.4168],
        'Europe/Rome' => [12.4964, 41.9028],
        'America/New_York' => [-74.0060, 40.7128],
        'America/Los_Angeles' => [-118.2437, 34.0522],
        'America/Chicago' => [-87.6298, 41.8781],
        'America/Toronto' => [-79.3832, 43.6532],
        'America/Sao_Paulo' => [-46.6333, -23.5505],
        'Africa/Cairo' => [31.2357, 30.0444],
        'Africa/Johannesburg' => [28.0473, -26.2041],
        'Asia/Dubai' => [55.2708, 25.2048],
        'Asia/Tokyo' => [139.6917, 35.6895],
        'Asia/Singapore' => [103.8198, 1.3521],
        'Asia/Shanghai' => [121.4737, 31.2304],
        'Asia/Kolkata' => [77.1025, 28.7041],
        'Australia/Sydney' => [151.2093, -33.8688],
        'Pacific/Auckland' => [174.7633, -36.8485],
    ];

    if (isset($exact[$timezone])) {
        return $exact[$timezone];
    }

    $continent = strtolower((string) strtok($timezone, '/'));

    return match ($continent) {
        'europe' => [10.4515, 51.1657],
        'africa' => [20.9394, 6.6111],
        'america' => [-73.0, 22.0],
        'asia' => [95.0, 34.0],
        'australia' => [133.7751, -25.2744],
        'pacific' => [166.0, -18.0],
        default => [2.2137, 46.2276],
    };
}

function map_hash_unit(string $seed): float
{
    $hash = hash('sha256', $seed);
    $value = hexdec(substr($hash, 0, 8)) % 10001;
    return $value / 10000;
}

function map_clamp(float $value, float $minimum, float $maximum): float
{
    return max($minimum, min($maximum, $value));
}

function map_approx_coordinates(string $slug, string $timezone): array
{
    [$baseLng, $baseLat] = map_timezone_center($timezone);

    $lngJitter = (map_hash_unit($slug . '|lng') * 2 - 1) * 1.8;
    $latJitter = (map_hash_unit($slug . '|lat') * 2 - 1) * 1.2;

    $lng = map_clamp((float) $baseLng + $lngJitter, -179.0, 179.0);
    $lat = map_clamp((float) $baseLat + $latJitter, -80.0, 80.0);

    return [round($lng, 6), round($lat, 6)];
}

$lands = [];
try {
    $lands = array_slice(land_snapshot(), 0, 500);
} catch (Throwable $exception) {
    $lands = [];
}

$publicSignals = [];
try {
    $publicSignals = list_public_signals();
} catch (Throwable $exception) {
    $publicSignals = [];
}

$signalsByLand = [];
foreach ($publicSignals as $signal) {
    $landSlug = trim((string) ($signal['land_slug'] ?? ''));
    if ($landSlug === '') {
        continue;
    }

    if (!isset($signalsByLand[$landSlug])) {
        $signalsByLand[$landSlug] = 0;
    }

    $signalsByLand[$landSlug]++;
}

$features = [];
foreach ($lands as $land) {
    $slug = trim((string) ($land['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }

    $username = trim((string) ($land['username'] ?? $slug));
    $timezone = trim((string) ($land['timezone'] ?? DEFAULT_TIMEZONE));
    [$lng, $lat] = map_approx_coordinates($slug, $timezone);

    $features[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [$lng, $lat],
        ],
        'properties' => [
            'kind' => 'land',
            'slug' => $slug,
            'username' => $username,
            'timezone' => $timezone,
            'created_at' => (string) ($land['created_at'] ?? ''),
            'signal_public_count' => (int) ($signalsByLand[$slug] ?? 0),
            'land_url' => '/land.php?u=' . rawurlencode($slug),
            'source' => 'lands',
            'precision' => 'approximate',
        ],
    ];
}

$payload = [
    'type' => 'FeatureCollection',
    'generated_at' => gmdate(DATE_ATOM),
    'meta' => [
        'precision' => 'approximate',
        'strategy' => 'timezone-center-plus-deterministic-jitter',
    ],
    'total' => count($features),
    'features' => $features,
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
