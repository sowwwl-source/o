<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

function sensor_ingest_expected_token(): string
{
    return trim((string) (getenv('SOWWWL_PI_TOKEN') ?: ''));
}

function sensor_ingest_authorization_token(): string
{
    $authorization = trim((string) (
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? ''
    ));

    if ($authorization === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $authorization = trim((string) $value);
                    break;
                }
            }
        }
    }

    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    return '';
}

function sensor_ingest_string(array $payload, string $key, int $maxLength, string $fallback = ''): string
{
    $value = trim((string) ($payload[$key] ?? $fallback));
    if ($value === '') {
        return $fallback;
    }

    return substr($value, 0, $maxLength);
}

function sensor_ingest_append_event(array $event): void
{
    plasma_append_event($event);
}

if (o_request_method() !== 'POST') {
    o_allow_methods(['POST']);
    o_json_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$expectedToken = sensor_ingest_expected_token();
if ($expectedToken === '') {
    o_json_response(503, ['ok' => false, 'error' => 'sensor_ingest_not_configured']);
}

$providedToken = sensor_ingest_authorization_token();
if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    o_json_response(401, ['ok' => false, 'error' => 'unauthorized']);
}

$rawInput = (string) file_get_contents('php://input');
if ($rawInput === '' || strlen($rawInput) > 65536) {
    o_json_response(400, ['ok' => false, 'error' => 'invalid_body']);
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    o_json_response(400, ['ok' => false, 'error' => 'invalid_json']);
}

$eventName = sensor_ingest_string($payload, 'event', 64);
if ($eventName === '') {
    o_json_response(422, ['ok' => false, 'error' => 'missing_event']);
}

$now = gmdate(DATE_ATOM);
$event = [
    'id' => 'evt_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4)),
    'received_at' => $now,
    'source' => 'raspberry-pi',
    'event' => $eventName,
    'camera' => sensor_ingest_string($payload, 'camera', 64, 'unknown'),
    'land_slug' => sensor_ingest_string($payload, 'land_slug', 64),
    'timestamp' => sensor_ingest_string($payload, 'timestamp', 64, $now),
    'message' => sensor_ingest_string($payload, 'message', 280),
    'metrics' => is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [],
    'remote_addr_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
];

try {
    sensor_ingest_append_event($event);
} catch (RuntimeException $exception) {
    error_log('sensor ingest failed: ' . $exception->getMessage());
    o_json_response(500, ['ok' => false, 'error' => 'plasma_log_unavailable']);
}

o_json_response(202, [
    'ok' => true,
    'status' => 'ingested',
    'event_id' => $event['id'],
    'received_at' => $event['received_at'],
]);
