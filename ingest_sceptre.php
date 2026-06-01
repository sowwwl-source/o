<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function sceptre_ingest_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sceptre_ingest_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';

    return is_string($value) ? trim($value) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Allow: POST, OPTIONS');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    sceptre_ingest_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || trim($rawBody) === '') {
    sceptre_ingest_json(400, ['ok' => false, 'error' => 'empty_body']);
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    sceptre_ingest_json(400, ['ok' => false, 'error' => 'invalid_json']);
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
    sceptre_ingest_json(503, ['ok' => false, 'error' => 'sceptre_ingest_not_configured']);
}

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    sceptre_ingest_json(401, ['ok' => false, 'error' => 'unauthorized']);
}

$state = sceptre_normalize_state($decoded);
$state['updated_at'] = trim((string) ($decoded['updated_at'] ?? gmdate(DATE_ATOM)));

$stateDir = sceptre_storage_dir();
if (!is_dir($stateDir) && !@mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
    sceptre_ingest_json(500, ['ok' => false, 'error' => 'state_dir_unavailable']);
}

$deviceSlug = normalize_sceptre_slug((string) ($state['device'] ?? $deviceSlug));
$statePath = sceptre_state_path($deviceSlug);
$jsonState = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!is_string($jsonState) || @file_put_contents($statePath, $jsonState . PHP_EOL, LOCK_EX) === false) {
    sceptre_ingest_json(500, ['ok' => false, 'error' => 'state_write_failed']);
}

sceptre_ingest_json(202, [
    'ok' => true,
    'device' => $deviceSlug,
    'stored' => basename($statePath),
]);
