<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

function membrane_ingest_string(array $payload, string $key, int $maxLength, string $fallback = ''): string
{
    $value = trim((string) ($payload[$key] ?? $fallback));
    if ($value === '') {
        return $fallback;
    }

    return substr($value, 0, $maxLength);
}

function membrane_ingest_metric(array $metrics, string $key, float $min, float $max): ?float
{
    $value = $metrics[$key] ?? null;
    if (!is_numeric($value)) {
        return null;
    }

    $number = (float) $value;
    if ($number < $min) {
        $number = $min;
    } elseif ($number > $max) {
        $number = $max;
    }

    return round($number, 3);
}

$origin = plasma_public_resolve_origin();
if (o_request_method() === 'OPTIONS') {
    if ($origin === null) {
        o_json_response(403, ['ok' => false, 'error' => 'origin_not_allowed']);
    }

    o_json_response(204, ['ok' => true], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

if (o_request_method() !== 'POST') {
    o_allow_methods(['POST', 'OPTIONS']);
    o_json_response(405, ['ok' => false, 'error' => 'method_not_allowed'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

if ($origin === null) {
    o_json_response(403, ['ok' => false, 'error' => 'origin_not_allowed']);
}

try {
    enforce_rate_limit('membrane-bridge', 64, 300);
} catch (RuntimeException $exception) {
    o_json_response(429, ['ok' => false, 'error' => 'rate_limited'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

$rawInput = (string) file_get_contents('php://input');
if ($rawInput === '' || strlen($rawInput) > 16384) {
    o_json_response(400, ['ok' => false, 'error' => 'invalid_body'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    o_json_response(400, ['ok' => false, 'error' => 'invalid_json'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

$eventName = membrane_ingest_string($payload, 'event', 64);
$allowedEvents = ['membrane_open', 'membrane_partial', 'membrane_pulse', 'membrane_close'];
if (!in_array($eventName, $allowedEvents, true)) {
    o_json_response(422, ['ok' => false, 'error' => 'invalid_event'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

$rawMetrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
$metrics = [];
foreach ([
    'presence' => [0.0, 1.0],
    'motion' => [0.0, 1.0],
    'audio' => [0.0, 1.0],
    'light' => [0.0, 1.0],
    'luma' => [0.0, 1.0],
    'device_volume' => [0.0, 1.0],
    'silence_intent' => [0.0, 1.0],
    'native_silence' => [0.0, 1.0],
    'visibility_hidden' => [0.0, 1.0],
    'standalone' => [0.0, 1.0],
    'tilt_x' => [-1.0, 1.0],
    'tilt_y' => [-1.0, 1.0],
] as $key => [$min, $max]) {
    $value = membrane_ingest_metric($rawMetrics, $key, $min, $max);
    if ($value !== null) {
        $metrics[$key] = $value;
    }
}

$surface = membrane_ingest_string($payload, 'surface', 32, 'xyz');
$event = [
    'id' => 'evt_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4)),
    'received_at' => gmdate(DATE_ATOM),
    'source' => 'xyz-web',
    'event' => $eventName,
    'camera' => membrane_ingest_string($payload, 'camera', 64, 'membrane'),
    'land_slug' => membrane_ingest_string($payload, 'land_slug', 64),
    'timestamp' => membrane_ingest_string($payload, 'timestamp', 64, gmdate(DATE_ATOM)),
    'message' => membrane_ingest_string($payload, 'message', 220),
    'metrics' => $metrics,
    'surface' => $surface,
    'origin' => $origin,
    'remote_addr_hash' => hash('sha256', client_ip()),
];

try {
    plasma_append_event($event);
} catch (RuntimeException $exception) {
    error_log('membrane ingest failed: ' . $exception->getMessage());
    o_json_response(500, ['ok' => false, 'error' => 'plasma_log_unavailable'], [
        'origin' => $origin,
        'cors_methods' => ['POST', 'OPTIONS'],
    ]);
}

o_json_response(202, [
    'ok' => true,
    'status' => 'ingested',
    'source' => 'xyz-web',
    'event_id' => $event['id'],
    'received_at' => $event['received_at'],
], [
    'origin' => $origin,
    'cors_methods' => ['POST', 'OPTIONS'],
]);
