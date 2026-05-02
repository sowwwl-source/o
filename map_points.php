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

function map_signal_timestamp(array $signal): int
{
    $candidate = (string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''));
    $timestamp = strtotime($candidate);
    return $timestamp !== false ? $timestamp : 0;
}

function map_land_timestamp(array $land): int
{
    $candidate = (string) ($land['created_at'] ?? '');
    $timestamp = strtotime($candidate);
    return $timestamp !== false ? $timestamp : 0;
}

function map_normalize_score(float $score, float $maximum): float
{
    if ($maximum <= 0.0) {
        return 0.18;
    }

    return map_clamp($score / $maximum, 0.18, 1.0);
}

function map_activity_score(array $land, array $stats, int $now): float
{
    $signalCount = (int) ($stats['count'] ?? 0);
    $lastSignal = (int) ($stats['last_signal_at_ts'] ?? 0);
    $landCreatedAt = map_land_timestamp($land);

    $signalDensity = min(8.0, sqrt(max(0, $signalCount)) * 2.4);

    $signalRecency = 0.0;
    if ($lastSignal > 0) {
        $days = max(0.0, ($now - $lastSignal) / 86400);
        $signalRecency = max(0.0, 4.6 - min(4.6, $days * 0.16));
    }

    $landFreshness = 0.0;
    if ($landCreatedAt > 0) {
        $days = max(0.0, ($now - $landCreatedAt) / 86400);
        $landFreshness = max(0.0, 1.6 - min(1.6, $days * 0.03));
    }

    return 1.0 + $signalDensity + $signalRecency + $landFreshness;
}

function map_torus_coordinates(string $slug, int $position, int $total, float $heat): array
{
    $count = max(1, $total);
    $thetaBase = (2 * M_PI * $position) / $count;
    $thetaOffset = (map_hash_unit($slug . '|theta') - 0.5) * ((2 * M_PI) / max($count, 6)) * 0.65;
    $theta = $thetaBase + $thetaOffset;

    $phi = map_hash_unit($slug . '|phi') * (2 * M_PI);
    $majorRadius = 112.0;
    $minorRadius = 18.0 + (20.0 * $heat);
    $tubeRadius = $minorRadius * cos($phi);

    $x = ($majorRadius + $tubeRadius) * cos($theta);
    $y = ($majorRadius + $tubeRadius) * sin($theta) * 0.56;
    $y += sin($phi) * (8.0 + 10.0 * $heat);

    return [
        round(map_clamp($x, -179.0, 179.0), 6),
        round(map_clamp($y, -82.0, 82.0), 6),
        $theta,
        $phi,
    ];
}

function map_current_curve(array $from, array $to, float $strength): array
{
    [$lngA, $latA] = $from;
    [$lngB, $latB] = $to;

    $midLng = ($lngA + $lngB) / 2;
    $midLat = ($latA + $latB) / 2;
    $dx = $lngB - $lngA;
    $dy = $latB - $latA;
    $distance = sqrt(($dx * $dx) + ($dy * $dy));
    $distance = max(1.0, $distance);
    $normalX = -$dy / $distance;
    $normalY = $dx / $distance;
    $amplitude = min(18.0, 5.0 + ($strength * 2.6) + ($distance * 0.06));

    $controlA = [
        round(map_clamp($lngA + ($dx * 0.28) + ($normalX * $amplitude), -179.0, 179.0), 6),
        round(map_clamp($latA + ($dy * 0.28) + ($normalY * $amplitude * 0.45), -82.0, 82.0), 6),
    ];

    $controlB = [
        round(map_clamp($midLng + ($normalX * $amplitude * 1.25), -179.0, 179.0), 6),
        round(map_clamp($midLat + ($normalY * $amplitude * 0.7), -82.0, 82.0), 6),
    ];

    $controlC = [
        round(map_clamp($lngA + ($dx * 0.72) + ($normalX * $amplitude), -179.0, 179.0), 6),
        round(map_clamp($latA + ($dy * 0.72) + ($normalY * $amplitude * 0.45), -82.0, 82.0), 6),
    ];

    return [
        [$lngA, $latA],
        $controlA,
        $controlB,
        $controlC,
        [$lngB, $latB],
    ];
}

function map_heat_label(float $heat): string
{
    return match (true) {
        $heat >= 0.86 => 'incandescent',
        $heat >= 0.64 => 'très active',
        $heat >= 0.42 => 'en circulation',
        default => 'latente',
    };
}

function map_land_url(string $slug): string
{
    return '/land.php?u=' . rawurlencode($slug);
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
usort(
    $publicSignals,
    static fn (array $left, array $right): int => map_signal_timestamp($right) <=> map_signal_timestamp($left)
);

foreach ($publicSignals as $signal) {
    $landSlug = trim((string) ($signal['land_slug'] ?? ''));
    if ($landSlug === '') {
        continue;
    }

    if (!isset($signalsByLand[$landSlug])) {
        $signalsByLand[$landSlug] = [
            'count' => 0,
            'last_signal_at_ts' => 0,
            'last_signal_at' => '',
        ];
    }

    $signalsByLand[$landSlug]['count']++;

    $signalTimestamp = map_signal_timestamp($signal);
    if ($signalTimestamp > (int) $signalsByLand[$landSlug]['last_signal_at_ts']) {
        $signalsByLand[$landSlug]['last_signal_at_ts'] = $signalTimestamp;
        $signalsByLand[$landSlug]['last_signal_at'] = (string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''));
    }
}

$now = time();
$landEntries = [];
foreach ($lands as $land) {
    $slug = trim((string) ($land['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }

    $username = trim((string) ($land['username'] ?? $slug));
    $timezone = trim((string) ($land['timezone'] ?? DEFAULT_TIMEZONE));
    $stats = $signalsByLand[$slug] ?? [
        'count' => 0,
        'last_signal_at_ts' => 0,
        'last_signal_at' => '',
    ];
    $score = map_activity_score($land, $stats, $now);

    $landEntries[] = [
        'land' => $land,
        'slug' => $slug,
        'username' => $username,
        'timezone' => $timezone,
        'stats' => $stats,
        'score' => $score,
    ];
}

usort(
    $landEntries,
    static function (array $left, array $right): int {
        $scoreComparison = ($right['score'] <=> $left['score']);
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        return strcmp((string) $left['slug'], (string) $right['slug']);
    }
);

$maxScore = 0.0;
foreach ($landEntries as $entry) {
    $maxScore = max($maxScore, (float) ($entry['score'] ?? 0.0));
}

$landNodes = [];
$landCoordinates = [];
$features = [];
foreach ($landEntries as $index => $entry) {
    $land = $entry['land'];
    $slug = (string) $entry['slug'];
    $username = (string) $entry['username'];
    $timezone = (string) $entry['timezone'];
    $stats = is_array($entry['stats'] ?? null) ? $entry['stats'] : [];
    $heat = map_normalize_score((float) ($entry['score'] ?? 0.0), $maxScore);
    [$lng, $lat, $theta, $phi] = map_torus_coordinates($slug, $index, count($landEntries), $heat);

    $landCoordinates[$slug] = [$lng, $lat];
    $landNodes[$slug] = [
        'slug' => $slug,
        'username' => $username,
        'heat' => $heat,
        'score' => (float) ($entry['score'] ?? 0.0),
        'coordinates' => [$lng, $lat],
    ];

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
            'signal_public_count' => (int) ($stats['count'] ?? 0),
            'last_signal_at' => (string) ($stats['last_signal_at'] ?? ''),
            'activity_score' => round((float) ($entry['score'] ?? 0.0), 4),
            'activity_heat' => round($heat, 4),
            'activity_label' => map_heat_label($heat),
            'torus_theta' => round($theta, 6),
            'torus_phi' => round($phi, 6),
            'land_url' => map_land_url($slug),
            'source' => 'lands',
            'precision' => 'torus-activity',
        ],
    ];
}

$currentsByPair = [];
$recentSignals = array_slice($publicSignals, 0, 120);
for ($index = 0, $length = count($recentSignals) - 1; $index < $length; $index++) {
    $fromSlug = trim((string) ($recentSignals[$index]['land_slug'] ?? ''));
    $toSlug = trim((string) ($recentSignals[$index + 1]['land_slug'] ?? ''));

    if ($fromSlug === '' || $toSlug === '' || $fromSlug === $toSlug) {
        continue;
    }

    if (!isset($landCoordinates[$fromSlug], $landCoordinates[$toSlug])) {
        continue;
    }

    $key = $fromSlug . '>' . $toSlug;
    if (!isset($currentsByPair[$key])) {
        $currentsByPair[$key] = [
            'from' => $fromSlug,
            'to' => $toSlug,
            'count' => 0,
            'last_seen_ts' => 0,
        ];
    }

    $currentsByPair[$key]['count']++;
    $currentsByPair[$key]['last_seen_ts'] = max(
        (int) $currentsByPair[$key]['last_seen_ts'],
        map_signal_timestamp($recentSignals[$index])
    );
}

foreach ($landEntries as $index => $entry) {
    if ($index >= 10) {
        break;
    }

    $fromSlug = (string) $entry['slug'];
    $toEntry = $landEntries[($index + 1) % max(1, count($landEntries))] ?? null;
    $toSlug = is_array($toEntry) ? (string) ($toEntry['slug'] ?? '') : '';

    if ($toSlug === '' || $toSlug === $fromSlug || !isset($landCoordinates[$fromSlug], $landCoordinates[$toSlug])) {
        continue;
    }

    $key = $fromSlug . '>' . $toSlug;
    if (!isset($currentsByPair[$key])) {
        $currentsByPair[$key] = [
            'from' => $fromSlug,
            'to' => $toSlug,
            'count' => 1,
            'last_seen_ts' => max(
                (int) (($entry['stats']['last_signal_at_ts'] ?? 0)),
                (int) (($toEntry['stats']['last_signal_at_ts'] ?? 0))
            ),
        ];
    }
}

usort(
    $landEntries,
    static fn (array $left, array $right): int => strcmp((string) $left['slug'], (string) $right['slug'])
);

$currentFeatures = [];
foreach ($currentsByPair as $current) {
    $fromSlug = (string) ($current['from'] ?? '');
    $toSlug = (string) ($current['to'] ?? '');
    if (!isset($landNodes[$fromSlug], $landNodes[$toSlug])) {
        continue;
    }

    $fromNode = $landNodes[$fromSlug];
    $toNode = $landNodes[$toSlug];
    $baseStrength = (float) ($current['count'] ?? 0);
    $strength = $baseStrength + (($fromNode['heat'] + $toNode['heat']) * 1.35);
    $heat = map_clamp($strength / 7.5, 0.18, 1.0);

    $currentFeatures[] = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => map_current_curve($fromNode['coordinates'], $toNode['coordinates'], $strength),
        ],
        'properties' => [
            'kind' => 'current',
            'from_slug' => $fromSlug,
            'to_slug' => $toSlug,
            'from_username' => (string) ($fromNode['username'] ?? $fromSlug),
            'to_username' => (string) ($toNode['username'] ?? $toSlug),
            'passage_count' => (int) ($current['count'] ?? 0),
            'activity_heat' => round($heat, 4),
            'activity_label' => map_heat_label($heat),
            'last_seen_at' => ((int) ($current['last_seen_ts'] ?? 0)) > 0
                ? gmdate(DATE_ATOM, (int) $current['last_seen_ts'])
                : '',
            'source' => 'signals-sequence',
            'precision' => 'torus-activity',
        ],
    ];
}

usort(
    $currentFeatures,
    static function (array $left, array $right): int {
        $leftHeat = (float) ($left['properties']['activity_heat'] ?? 0.0);
        $rightHeat = (float) ($right['properties']['activity_heat'] ?? 0.0);
        return $rightHeat <=> $leftHeat;
    }
);

$currentFeatures = array_slice($currentFeatures, 0, 36);
$features = array_merge($currentFeatures, $features);

$payload = [
    'type' => 'FeatureCollection',
    'generated_at' => gmdate(DATE_ATOM),
    'meta' => [
        'precision' => 'torus-activity',
        'strategy' => 'torus-nodes-and-hot-currents',
        'lands' => count($landNodes),
        'currents' => count($currentFeatures),
    ],
    'total' => count($features),
    'features' => $features,
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
