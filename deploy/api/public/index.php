<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$baseUrl = rtrim((string)(getenv('API_PUBLIC_BASE_URL') ?: 'https://api.sowwwl.cloud'), '/');
$bearerToken = (string)(getenv('API_BEARER_TOKEN') ?: '');
$allowedOrigins = array_values(array_filter(array_map(
    static fn(string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string)(getenv('API_ALLOWED_ORIGINS') ?: implode(',', [
        'https://sowwwl.cloud',
        'https://sowwwl.me',
        'https://sowwwl.com',
        'https://0.user.o.sowwwl.cloud',
        'https://sowwwl.org',
        'https://0wlslw0.com',
        'https://0wlslw0.fr',
        'https://sowwwl.art',
    ])))
)));

header('Vary: Origin');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$requestOrigin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}

if (!in_array($method, ['GET', 'POST', 'OPTIONS'], true)) {
    json_response(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
        'method' => $method,
    ]);
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function html_response(int $status, string $html): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
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
    $payload = read_json_body();
    json_response(501, [
        'ok' => false,
        'error' => 'not_implemented',
        'service' => 'api.sowwwl.cloud',
        'path' => $path,
        'method' => $method,
        'received' => $payload,
        'message' => 'Stub endpoint active. Replace with the production AzA service when ready.',
        'timestamp' => gmdate(DATE_ATOM),
    ]);
}

$publicPaths = [
    '/',
    '/healthz',
    '/docs',
    '/docs/AzA_v0.7_openapi.min.yaml',
    '/v1/status',
];

if (!in_array($path, $publicPaths, true)) {
    $token = bearer_from_request();
    if ($bearerToken !== '' && !hash_equals($bearerToken, $token)) {
        json_response(401, [
            'ok' => false,
            'error' => 'unauthorized',
            'message' => 'Missing or invalid bearer token.',
        ]);
    }
}

if ($path === '/') {
    html_response(200, '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>api.sowwwl.cloud</title><style>body{margin:0;background:#08111b;color:#e9f2ff;font-family:Menlo,Consolas,monospace}main{max-width:860px;margin:0 auto;padding:56px 24px}a{color:#9cd0ff}code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px}</style></head><body><main><h1>api.sowwwl.cloud</h1><p>Minimal AzA API stub running.</p><p>Health: <a href="/healthz">/healthz</a></p><p>Docs: <a href="/docs">/docs</a></p><p>Status: <a href="/v1/status">/v1/status</a></p><p>Protected endpoints return <code>501 not_implemented</code> until the production service is wired.</p></main></body></html>');
}

if ($path === '/healthz') {
    json_response(200, [
        'ok' => true,
        'service' => 'api.sowwwl.cloud',
        'mode' => 'stub',
        'time' => gmdate(DATE_ATOM),
    ]);
}

if ($path === '/v1/status') {
    json_response(200, [
        'ok' => true,
        'service' => 'api.sowwwl.cloud',
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
    $file = __DIR__ . '/docs/AzA_v0.7_openapi.min.yaml';
    if (!is_file($file)) {
        json_response(404, ['ok' => false, 'error' => 'not_found']);
    }
    header('Content-Type: application/yaml; charset=utf-8');
    readfile($file);
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
