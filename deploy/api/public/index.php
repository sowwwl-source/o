<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

require_once __DIR__ . '/request_trust.php';

function request_is_secure(): bool
{
    return sowwwl_request_is_secure();
}

function request_public_base_url(): string
{
    $override = trim((string) (getenv('API_PUBLIC_BASE_URL') ?: ''));
    if ($override !== '') {
        return rtrim($override, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'api.sowwwl.cloud'));
    $scheme = request_is_secure() ? 'https' : 'http';

    return $scheme . '://' . $host;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$baseUrl = request_public_base_url();
$bearerToken = (string)(getenv('API_BEARER_TOKEN') ?: '');
$parsedBaseHost = parse_url($baseUrl, PHP_URL_HOST);
$serviceLabel = is_string($parsedBaseHost) && $parsedBaseHost !== ''
    ? strtolower($parsedBaseHost)
    : trim((string) ($_SERVER['HTTP_HOST'] ?? 'api.sowwwl.cloud'));
header('X-Content-Type-Options: nosniff');

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function html_response(int $status, string $html): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function bearer_from_request(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }
    return '';
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function stub_response(string $path, string $method): void
{
    global $serviceLabel;

    $payload = read_json_body();
    json_response(501, [
        'ok' => false,
        'error' => 'not_implemented',
        'service' => $serviceLabel,
        'path' => $path,
        'method' => $method,
        'received' => $payload,
        'message' => 'Stub endpoint active. Replace with the production AzA service when ready.',
        'timestamp' => gmdate(DATE_ATOM),
    ]);
}

function api_allowed_origins(): array
{
    $configured = trim((string) (getenv('API_ALLOWED_ORIGINS') ?: ''));
    $candidates = $configured !== ''
        ? (preg_split('/[\s,]+/', $configured) ?: [])
        : [
            'https://sowwwl.com',
            'https://www.sowwwl.com',
            'https://sowwwl.cloud',
            'https://www.sowwwl.cloud',
            'https://api.sowwwl.cloud',
            'https://lab.sowwwl.cloud',
            'https://www.lab.sowwwl.cloud',
            'https://api.lab.sowwwl.cloud',
        ];

    $origins = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }

        $parts = parse_url($candidate);
        $scheme = strtolower(trim((string) ($parts['scheme'] ?? '')));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($scheme === '' || $host === '') {
            continue;
        }

        $origin = $scheme . '://' . $host;
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);
        if ($port !== null && $port > 0 && $port !== $defaultPort) {
            $origin .= ':' . $port;
        }

        $origins[] = $origin;
    }

    return array_values(array_unique($origins));
}

function api_send_cors_headers(): bool
{
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') {
        return true;
    }

    if (!in_array($origin, api_allowed_origins(), true)) {
        return false;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    return true;
}

function api_bearer_token_is_configured(string $token): bool
{
    return $token !== '' && !str_starts_with($token, 'CHANGE_ME');
}

function api_allow_unauthenticated_stub(): bool
{
    $raw = strtolower(trim((string) (getenv('API_ALLOW_UNAUTHENTICATED_STUB') ?: '')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

$publicPaths = [
    '/',
    '/healthz',
    '/docs',
    '/docs/AzA_v0.7_openapi.min.yaml',
    '/v1/status',
];

$corsAllowed = api_send_cors_headers();
if (!$corsAllowed) {
    json_response(403, [
        'ok' => false,
        'error' => 'origin_not_allowed',
    ]);
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($path, $publicPaths, true)) {
    $token = bearer_from_request();
    if (!api_bearer_token_is_configured($bearerToken) && !api_allow_unauthenticated_stub()) {
        json_response(503, [
            'ok' => false,
            'error' => 'service_unavailable',
            'message' => 'Protected stub routes are disabled until API_BEARER_TOKEN is configured.',
        ]);
    }

    if (api_bearer_token_is_configured($bearerToken) && !hash_equals($bearerToken, $token)) {
        json_response(401, [
            'ok' => false,
            'error' => 'unauthorized',
            'message' => 'Missing or invalid bearer token.',
        ]);
    }
}

if ($path === '/') {
    $safeServiceLabel = htmlspecialchars($serviceLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    html_response(200, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $safeServiceLabel . '</title><style>body{margin:0;background:#08111b;color:#e9f2ff;font-family:Menlo,Consolas,monospace}main{max-width:860px;margin:0 auto;padding:56px 24px}a{color:#9cd0ff}code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px}</style></head><body><main><h1>' . $safeServiceLabel . '</h1><p>Minimal AzA API stub running.</p><p>Health: <a href="/healthz">/healthz</a></p><p>Docs: <a href="/docs">/docs</a></p><p>Status: <a href="/v1/status">/v1/status</a></p><p>Protected endpoints return <code>501 not_implemented</code> until the production service is wired.</p></main></body></html>');
}

if ($path === '/healthz') {
    json_response(200, [
        'ok' => true,
        'service' => $serviceLabel,
        'mode' => 'stub',
        'time' => gmdate(DATE_ATOM),
    ]);
}

if ($path === '/v1/status') {
    json_response(200, [
        'ok' => true,
        'service' => $serviceLabel,
        'mode' => 'stub',
        'docs' => $baseUrl . '/docs',
        'openapi' => $baseUrl . '/docs/AzA_v0.7_openapi.min.yaml',
        'time' => gmdate(DATE_ATOM),
    ]);
}

if ($path === '/docs') {
    html_response(200, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>AzA API Docs</title><style>body{margin:0;background:#f4efe7;color:#102033;font-family:Georgia,serif}main{max-width:860px;margin:0 auto;padding:56px 24px}a{color:#0c4c7f}</style></head><body><main><h1>AzA API Docs</h1><p>This host currently serves a minimal compatibility layer for the O. application.</p><p>OpenAPI file: <a href="/docs/AzA_v0.7_openapi.min.yaml">AzA_v0.7_openapi.min.yaml</a></p><p>Health endpoint: <a href="/healthz">/healthz</a></p><p>Implementation status endpoint: <a href="/v1/status">/v1/status</a></p></main></body></html>');
}

if ($path === '/docs/AzA_v0.7_openapi.min.yaml') {
    $fileCandidates = [
        __DIR__ . '/docs/AzA_v0.7_openapi.min.yaml',
        dirname(__DIR__) . '/docs/AzA_v0.7_openapi.min.yaml',
    ];
    $file = '';
    foreach ($fileCandidates as $candidate) {
        if (is_file($candidate)) {
            $file = $candidate;
            break;
        }
    }

    if ($file === '') {
        json_response(404, ['ok' => false, 'error' => 'not_found']);
    }

    $yaml = file_get_contents($file);
    if ($yaml === false) {
        json_response(500, ['ok' => false, 'error' => 'read_failed']);
    }

    $yaml = str_replace(
        ['{{API_PUBLIC_BASE_URL}}', '{{API_SERVICE_HOST}}', 'https://api.sowwwl.cloud', 'api.sowwwl.cloud'],
        [$baseUrl, $serviceLabel, $baseUrl, $serviceLabel],
        $yaml
    );

    header('Content-Type: application/yaml; charset=utf-8');
    echo $yaml;
    exit;
}

$stubbedRoutes = [
    '/v1/user/score',
    '/v1/voice/render',
    '/v1/organize',
    '/v1/index',
    '/v1/evict',
    '/v1/post/generate',
    '/v1/post/publish',
    '/v1/kb/touch',
    '/upload',
];

if (in_array($path, $stubbedRoutes, true)) {
    stub_response($path, $method);
}

json_response(404, [
    'ok' => false,
    'error' => 'not_found',
    'path' => $path,
]);
