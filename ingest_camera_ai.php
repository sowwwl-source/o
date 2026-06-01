<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function camera_ai_ingest_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function camera_ai_ingest_header(string $name): string
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
    camera_ai_ingest_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$expectedToken = camera_ai_ingest_token();
$expectedToken = trim($expectedToken);
$authConfigured = $expectedToken !== '';
$errorNotConfigured = ['ok' => false, 'error' => 'camera_ai_ingest_not_configured'];
$errorUnauthorized = ['ok' => false, 'error' => 'unauthorized'];
$authHeader = camera_ai_ingest_header('Authorization');
$tokenHeader = camera_ai_ingest_header('X-Sowwwl-Ai-Token');
$providedToken = $tokenHeader;
if ($providedToken === '' && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $providedToken = trim((string) ($matches[1] ?? ''));
}

if (!$authConfigured) {
    camera_ai_ingest_json(503, $errorNotConfigured);
}

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    camera_ai_ingest_json(401, $errorUnauthorized);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || trim($rawBody) === '') {
    camera_ai_ingest_json(400, ['ok' => false, 'error' => 'empty_body']);
}

$decoded = json_decode($rawBody, true);
if (!is_array($decoded)) {
    camera_ai_ingest_json(400, ['ok' => false, 'error' => 'invalid_json']);
}

$cameraSlug = normalize_camera_state_slug((string) ($decoded['camera'] ?? pocket_camera_slug()));
$state = camera_ai_default_state($cameraSlug);
$state['scene'] = trim((string) ($decoded['scene'] ?? $state['scene'])) ?: $state['scene'];
$state['lead'] = trim((string) ($decoded['lead'] ?? $state['lead'])) ?: $state['lead'];
$state['summary'] = trim((string) ($decoded['summary'] ?? $state['summary'])) ?: $state['summary'];
$state['model'] = trim((string) ($decoded['model'] ?? ''));
$state['dominant_label'] = trim((string) ($decoded['dominant_label'] ?? ''));
$state['dominant_score'] = max(0.0, min(1.0, (float) ($decoded['dominant_score'] ?? 0.0)));
$state['attention'] = max(0.0, min(1.0, (float) ($decoded['attention'] ?? 0.0)));
$state['movement'] = max(0.0, min(1.0, (float) ($decoded['movement'] ?? 0.0)));
$state['density'] = max(0.0, min(1.0, (float) ($decoded['density'] ?? 0.0)));
$state['object_count'] = max(0, (int) ($decoded['object_count'] ?? 0));
$state['person_count'] = max(0, (int) ($decoded['person_count'] ?? 0));
$state['vehicle_count'] = max(0, (int) ($decoded['vehicle_count'] ?? 0));
$state['animal_count'] = max(0, (int) ($decoded['animal_count'] ?? 0));
$state['updated_at'] = trim((string) ($decoded['updated_at'] ?? gmdate(DATE_ATOM)));

$detections = [];
if (isset($decoded['detections']) && is_array($decoded['detections'])) {
    foreach (array_slice($decoded['detections'], 0, 16) as $detection) {
        if (!is_array($detection)) {
            continue;
        }

        $bbox = isset($detection['bbox']) && is_array($detection['bbox']) ? array_values($detection['bbox']) : [];
        $bbox = array_map(
            static fn ($value): float => max(0.0, min(1.0, (float) $value)),
            array_slice($bbox, 0, 4)
        );

        $detections[] = [
            'label' => trim((string) ($detection['label'] ?? '')),
            'score' => max(0.0, min(1.0, (float) ($detection['score'] ?? 0.0))),
            'bbox' => $bbox,
            'area' => max(0.0, min(1.0, (float) ($detection['area'] ?? 0.0))),
            'center' => isset($detection['center']) && is_array($detection['center'])
                ? array_map(
                    static fn ($value): float => max(0.0, min(1.0, (float) $value)),
                    array_slice(array_values($detection['center']), 0, 2)
                )
                : [],
        ];
    }
}
$state['detections'] = $detections;

$stateDir = camera_ai_storage_dir();
if (!is_dir($stateDir) && !@mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
    camera_ai_ingest_json(500, ['ok' => false, 'error' => 'state_dir_unavailable']);
}

$statePath = camera_ai_state_path($cameraSlug);
$jsonState = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (!is_string($jsonState) || @file_put_contents($statePath, $jsonState . PHP_EOL, LOCK_EX) === false) {
    camera_ai_ingest_json(500, ['ok' => false, 'error' => 'state_write_failed']);
}

camera_ai_ingest_json(202, [
    'ok' => true,
    'camera' => $cameraSlug,
    'stored' => basename($statePath),
]);
