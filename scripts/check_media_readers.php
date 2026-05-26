#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', ['host:', 'island-slug:', 'base-url:', 'json', 'require-ready']);
$host = strtolower(trim((string) ($options['host'] ?? 'sowwwl.com')));
$requestedIslandSlug = trim((string) ($options['island-slug'] ?? ''));
$baseUrl = media_normalize_base_url((string) ($options['base-url'] ?? 'http://127.0.0.1'));
$asJson = array_key_exists('json', $options);
$requireReady = array_key_exists('require-ready', $options);

/**
 * @return array{status_code:int, body:string, headers:list<string>, error:string}
 */
function media_normalize_base_url(string $baseUrl): string
{
    $candidate = rtrim(trim($baseUrl), '/');
    if ($candidate === '') {
        return 'http://127.0.0.1';
    }

    if (preg_match('~^https?://~i', $candidate) !== 1) {
        $candidate = 'http://' . ltrim($candidate, '/');
    }

    return $candidate;
}

function media_resolve_default_island_slug(string $requestedSlug): array
{
    $requestedSlug = trim($requestedSlug);
    if ($requestedSlug !== '') {
        return [$requestedSlug, false];
    }

    $defaultSlug = 'pablo-espallergues';
    if (find_land($defaultSlug) !== null) {
        return [$defaultSlug, false];
    }

    foreach (land_snapshot() as $land) {
        $candidate = trim((string) ($land['slug'] ?? ''));
        if ($candidate !== '') {
            return [$candidate, true];
        }
    }

    return [$defaultSlug, false];
}

/**
 * @return array{status_code:int, body:string, headers:list<string>, error:string}
 */
function media_fetch_local(string $baseUrl, string $host, string $path): array
{
    $responseHeaders = [];
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => "Host: {$host}\r\nConnection: close\r\n",
            'timeout' => 8,
        ],
    ]);

    $url = $baseUrl . (str_starts_with($path, '/') ? $path : ('/' . $path));
    $stream = @fopen($url, 'r', false, $context);
    $body = '';
    if (is_resource($stream)) {
        $body = stream_get_contents($stream) ?: '';
        $metadata = stream_get_meta_data($stream);
        $rawHeaders = $metadata['wrapper_data'] ?? [];
        if (is_array($rawHeaders)) {
            $responseHeaders = array_values(array_map('strval', $rawHeaders));
        }
        fclose($stream);
    }

    $statusCode = 0;
    foreach ($responseHeaders as $headerLine) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $headerLine, $matches) === 1) {
            $statusCode = (int) $matches[1];
            break;
        }
    }

    $error = '';
    if ($body === '' && $statusCode === 0) {
        $lastError = error_get_last();
        $error = trim((string) ($lastError['message'] ?? 'HTTP fetch failed.'));
    }

    return [
        'status_code' => $statusCode,
        'body' => $body,
        'headers' => $responseHeaders,
        'error' => $error,
    ];
}

[$islandSlug, $islandSlugFallbackUsed] = media_resolve_default_island_slug($requestedIslandSlug);

$routes = [
    'str3m' => [
        'path' => '/str3m',
        'markers' => [
            'data-str3m-player',
            'data-continuity-dome',
            'data-str3m-player-engine',
            'data-str3m-player-output',
            'data-str3m-player-source-state',
            'data-str3m-player-listening-preset',
            'data-str3m-player-open',
            'data-str3m-player-retry',
        ],
    ],
    'island' => [
        'path' => '/island?u=' . rawurlencode($islandSlug),
        'markers' => [
            'data-island-reader-shell',
            'data-continuity-dome',
            'data-island-reader-panel',
            'data-island-reader-nav',
            'data-island-reader-current-label',
            'data-island-reader-autoplay',
        ],
        'conditional_markers' => [
            'data-str3m-player' => [
                'data-str3m-player-engine',
                'data-str3m-player-listening-preset',
                'data-str3m-player-open',
                'data-str3m-player-retry',
            ],
        ],
    ],
    'aza_asset_missing' => [
        'path' => '/aza/asset?f=storage%2Faza%2Ffiles%2F__missing_media_probe__.mp3',
        'expected_statuses' => [404],
        'markers' => [],
    ],
];

$payload = [
    'generated_at' => gmdate(DATE_ATOM),
    'host' => $host,
    'base_url' => $baseUrl,
    'requested_island_slug' => $requestedIslandSlug !== '' ? $requestedIslandSlug : null,
    'island_slug' => $islandSlug,
    'island_slug_fallback_used' => $islandSlugFallbackUsed,
    'routes' => [],
    'ready' => false,
];

$issues = [];

foreach ($routes as $name => $route) {
    $result = media_fetch_local($baseUrl, $host, (string) $route['path']);
    $routeIssues = [];
    $expectedStatuses = array_map('intval', (array) ($route['expected_statuses'] ?? [200]));
    if (!in_array($result['status_code'], $expectedStatuses, true)) {
        $routeIssues[] = 'http-' . ($result['status_code'] ?: 'unreachable');
    }

    foreach ((array) ($route['markers'] ?? []) as $marker) {
        $markerValue = trim((string) $marker);
        if ($markerValue === '') {
            continue;
        }
        if ($result['body'] === '' || strpos($result['body'], $markerValue) === false) {
            $routeIssues[] = 'missing:' . $markerValue;
        }
    }

    foreach ((array) ($route['conditional_markers'] ?? []) as $trigger => $markers) {
        $triggerValue = trim((string) $trigger);
        if ($triggerValue === '' || $result['body'] === '' || strpos($result['body'], $triggerValue) === false) {
            continue;
        }

        foreach ((array) $markers as $marker) {
            $markerValue = trim((string) $marker);
            if ($markerValue === '') {
                continue;
            }

            if (strpos($result['body'], $markerValue) === false) {
                $routeIssues[] = 'missing:' . $markerValue;
            }
        }
    }

    if ($result['error'] !== '') {
        $routeIssues[] = $result['error'];
    }

    if ($routeIssues !== []) {
        $issues[] = $name . ':' . implode(',', $routeIssues);
    }

    $payload['routes'][$name] = [
        'path' => (string) $route['path'],
        'status_code' => $result['status_code'],
        'expected_statuses' => $expectedStatuses,
        'ready' => $routeIssues === [],
        'issues' => $routeIssues,
    ];
}

$payload['ready'] = $issues === [];

if ($asJson) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, "Media readers status\n");
    fwrite(STDOUT, "====================\n\n");
    foreach ($payload['routes'] as $name => $route) {
        fwrite(STDOUT, strtoupper((string) $name) . "\n");
        fwrite(STDOUT, "------\n");
        fwrite(STDOUT, 'path        : ' . (string) ($route['path'] ?? '') . PHP_EOL);
        fwrite(STDOUT, 'http status : ' . (string) ($route['status_code'] ?? 0) . PHP_EOL);
        fwrite(STDOUT, 'ready       : ' . (($route['ready'] ?? false) ? 'yes' : 'no') . PHP_EOL);
        if (($route['issues'] ?? []) !== []) {
            fwrite(STDOUT, 'issues      : ' . implode(', ', (array) $route['issues']) . PHP_EOL);
        }
        fwrite(STDOUT, PHP_EOL);
    }
    fwrite(STDOUT, 'overall ready: ' . ($payload['ready'] ? 'yes' : 'no') . PHP_EOL);
}

if ($requireReady && !$payload['ready']) {
    fwrite(STDERR, "Media readers check failed: " . implode(' | ', $issues) . PHP_EOL);
    exit(1);
}

exit(0);
