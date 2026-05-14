<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function membrane_ingest_json(int $status, array $payload, ?string $origin = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if ($origin !== null) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin === null) {
        membrane_ingest_json(403, ['ok' => false, 'error' => 'origin_not_allowed']);
    }

    membrane_ingest_json(204, ['ok' => true], $origin);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    membrane_ingest_json(405, ['ok' => false, 'error' => 'method_not_allowed'], $origin);
}

if ($origin === null) {
    membrane_ingest_json(403, ['ok' => false, 'error' => 'origin_not_allowed']);
}

try {
    enforce_rate_limit('membrane-bridge', 64, 300);
} catch (RuntimeException $exception) {
    membrane_ingest_json(429, ['ok' => false, 'error' => 'rate_limited'], $origin);
}

$rawInput = (string) file_get_contents('php://input');
if ($rawInput === '' || strlen($rawInput) > 16384) {
    membrane_ingest_json(400, ['ok' => false, 'error' => 'invalid_body'], $origin);
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    membrane_ingest_json(400, ['ok' => false, 'error' => 'invalid_json'], $origin);
}

$eventName = membrane_ingest_string($payload, 'event', 64);
$allowedEvents = ['membrane_open', 'membrane_partial', 'membrane_pulse', 'membrane_close'];
if (!in_array($eventName, $allowedEvents, true)) {
    membrane_ingest_json(422, ['ok' => false, 'error' => 'invalid_event'], $origin);
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
    membrane_ingest_json(500, ['ok' => false, 'error' => 'plasma_log_unavailable'], $origin);
}

membrane_ingest_json(202, [
    'ok' => true,
    'status' => 'ingested',
    'source' => 'xyz-web',
    'event_id' => $event['id'],
    'received_at' => $event['received_at'],
], $origin);
