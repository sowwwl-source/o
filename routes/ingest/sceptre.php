<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/http_json.php';

function sceptre_ingest_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';

    return is_string($value) ? trim($value) : '';
}

if (o_request_method() === 'OPTIONS') {
    o_allow_methods(['POST', 'OPTIONS']);
    http_response_code(204);
    exit;
}

if (o_request_method() !== 'POST') {
    o_allow_methods(['POST', 'OPTIONS']);
    o_json_response(405, ['ok' => false, 'error' => 'method_not_allowed'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || trim($rawBody) === '') {
    o_json_response(400, ['ok' => false, 'error' => 'empty_body'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    o_json_response(400, ['ok' => false, 'error' => 'invalid_json'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

$deviceSlug = normalize_sceptre_slug((string) ($decoded['device'] ?? 'ensemble'));
$expectedToken = trim(sceptre_expected_token_for_device($deviceSlug));
$authHeader = sceptre_ingest_header('Authorization');
$tokenHeader = sceptre_ingest_header('X-Sowwwl-Sceptre-Token');
$providedToken = $tokenHeader;
if ($providedToken === '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $providedToken = trim((string) ($matches[1] ?? ''));
}

if ($expectedToken === '') {
    o_json_response(503, ['ok' => false, 'error' => 'sceptre_ingest_not_configured'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    o_json_response(401, ['ok' => false, 'error' => 'unauthorized'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

$state = sceptre_normalize_state($decoded);
$state['updated_at'] = trim((string) ($decoded['updated_at'] ?? gmdate(DATE_ATOM)));

$stateDir = sceptre_storage_dir();
if (!is_dir($stateDir) && !@mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
    o_json_response(500, ['ok' => false, 'error' => 'state_dir_unavailable'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

$deviceSlug = normalize_sceptre_slug((string) ($state['device'] ?? $deviceSlug));
$statePath = sceptre_state_path($deviceSlug);
$jsonState = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!is_string($jsonState) || @file_put_contents($statePath, $jsonState . PHP_EOL, LOCK_EX) === false) {
    o_json_response(500, ['ok' => false, 'error' => 'state_write_failed'], [
        'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ]);
}

o_json_response(202, [
    'ok' => true,
    'device' => $deviceSlug,
    'stored' => basename($statePath),
], [
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
]);
