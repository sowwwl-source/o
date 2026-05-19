#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$options = getopt('', ['host:', 'mode:', 'json', 'require-ready']);
$host = strtolower(trim((string) ($options['host'] ?? 'sowwwl.io')));
$mode = strtolower(trim((string) ($options['mode'] ?? 'headset')));
$asJson = array_key_exists('json', $options);
$requireReady = array_key_exists('require-ready', $options);

if (!in_array($mode, ['screen', 'headset'], true)) {
    $mode = 'headset';
}

$surfaceVariant = current_surface_variant($host);
$query = [];
if ($surfaceVariant !== 'io') {
    $query['surface'] = 'io';
}
if ($mode === 'headset') {
    $query['spatial'] = 'headset';
}
$queryString = $query !== [] ? ('?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)) : '';

/**
 * @return array{status_code:int, body:string, headers:list<string>, error:string}
 */
function spatial_fetch_local(string $host, string $path): array
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

    $body = '';
    $stream = @fopen('http://127.0.0.1' . $path, 'r', false, $context);
    if (is_resource($stream)) {
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        $rawHeaders = $metadata['wrapper_data'] ?? [];
        if (is_array($rawHeaders)) {
            $responseHeaders = array_values(array_map('strval', $rawHeaders));
        }
        fclose($stream);
    }

    $statusCode = 0;
    if ($responseHeaders !== []) {
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                break;
            }
        }
    }

    $error = '';
    if (!is_resource($stream) && $body === '') {
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

$routes = [
    'home' => [
        'path' => '/' . $queryString,
        'markers' => [
            'data-spatial-context',
            'data-xyz-instrument-stage',
            'data-xyz-camera-facing-button="environment"',
            'monde instrument',
            'Mode casque web',
        ],
    ],
    'guide' => [
        'path' => '/0wlslw0' . $queryString,
        'markers' => [
            'data-spatial-context',
            'sowwwl.io',
        ],
    ],
    'map' => [
        'path' => '/map' . $queryString,
        'markers' => [
            'data-spatial-context',
            'Map',
        ],
    ],
    'signal' => [
        'path' => '/signal' . $queryString,
        'markers' => [
            'data-spatial-context',
            'Signal',
        ],
    ],
    'str3m' => [
        'path' => '/str3m' . $queryString,
        'markers' => [
            'data-spatial-context',
            'Str3m',
        ],
    ],
    'echo' => [
        'path' => '/echo' . $queryString,
        'markers' => [
            'data-spatial-context',
            'Echo',
        ],
    ],
];

$payload = [
    'generated_at' => gmdate(DATE_ATOM),
    'host' => $host,
    'mode' => $mode,
    'surface_variant' => $surfaceVariant,
    'surface_ready' => $surfaceVariant === 'io',
    'routes' => [],
    'manifest' => [
        'ready' => false,
        'issues' => [],
    ],
];

$issues = [];

foreach ($routes as $routeName => $routeConfig) {
    $result = spatial_fetch_local($host, (string) $routeConfig['path']);
    $routeIssues = [];
    if ($result['status_code'] !== 200) {
        $routeIssues[] = 'http-' . ($result['status_code'] ?: 'unreachable');
    }

    foreach ((array) ($routeConfig['markers'] ?? []) as $marker) {
        $markerValue = (string) $marker;
        if ($markerValue === '') {
            continue;
        }
        if ($result['body'] === '' || strpos($result['body'], $markerValue) === false) {
            $routeIssues[] = 'missing:' . $markerValue;
        }
    }

    if ($result['error'] !== '') {
        $routeIssues[] = $result['error'];
    }

    if ($routeIssues !== []) {
        $issues[] = $routeName . ':' . implode(',', $routeIssues);
    }

    $payload['routes'][$routeName] = [
        'path' => (string) $routeConfig['path'],
        'status_code' => $result['status_code'],
        'ready' => $routeIssues === [],
        'issues' => $routeIssues,
    ];
}

$manifestPath = '/manifest.php?app=io' . ($queryString !== '' ? '&' . ltrim($queryString, '?') : '');
$manifestResult = spatial_fetch_local($host, $manifestPath);
$manifestIssues = [];
if ($manifestResult['status_code'] !== 200) {
    $manifestIssues[] = 'http-' . ($manifestResult['status_code'] ?: 'unreachable');
}

$manifestData = null;
if ($manifestResult['body'] !== '') {
    $manifestData = json_decode($manifestResult['body'], true);
}
if (!is_array($manifestData)) {
    $manifestIssues[] = 'invalid-json';
} else {
    if ((string) ($manifestData['name'] ?? '') !== 'SOWWWL IO') {
        $manifestIssues[] = 'name-mismatch';
    }
    if ((string) ($manifestData['short_name'] ?? '') !== 'IO') {
        $manifestIssues[] = 'short-name-mismatch';
    }
    $startUrl = (string) ($manifestData['start_url'] ?? '');
    if ($startUrl === '' || strpos($startUrl, 'spatial=headset') === false) {
        $manifestIssues[] = 'start-url-missing-headset';
    }
}

if ($manifestResult['error'] !== '') {
    $manifestIssues[] = $manifestResult['error'];
}

if ($manifestIssues !== []) {
    $issues[] = 'manifest:' . implode(',', $manifestIssues);
}

$payload['manifest'] = [
    'path' => $manifestPath,
    'status_code' => $manifestResult['status_code'],
    'ready' => $manifestIssues === [],
    'issues' => $manifestIssues,
];

$payload['ready'] = ($payload['surface_ready'] ?? false) && $issues === [];
if (($payload['surface_ready'] ?? false) === false) {
    $issues[] = 'surface-variant-not-io';
}
$payload['issues'] = $issues;

if ($asJson) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} else {
    echo "Spatial surface check\n";
    echo "=====================\n\n";
    printf("host              : %s\n", $host);
    printf("mode              : %s\n", $mode);
    printf("surface variant   : %s\n", $surfaceVariant !== '' ? $surfaceVariant : 'unknown');
    printf("surface ready     : %s\n", ($payload['surface_ready'] ?? false) ? 'yes' : 'no');
    echo "\nRoutes\n";
    echo "------\n";
    foreach ((array) $payload['routes'] as $routeName => $routeData) {
        printf("%-17s %s\n", $routeName, (($routeData['ready'] ?? false) ? 'ok' : 'no'));
        printf("  path            : %s\n", (string) ($routeData['path'] ?? ''));
        printf("  http status     : %s\n", (string) ($routeData['status_code'] ?? 0));
        if (!empty($routeData['issues'])) {
            printf("  issues          : %s\n", implode(', ', array_map('strval', (array) $routeData['issues'])));
        }
    }
    echo "\nManifest\n";
    echo "--------\n";
    printf("ready             : %s\n", ($payload['manifest']['ready'] ?? false) ? 'yes' : 'no');
    printf("path              : %s\n", (string) ($payload['manifest']['path'] ?? ''));
    printf("http status       : %s\n", (string) ($payload['manifest']['status_code'] ?? 0));
    if (!empty($payload['manifest']['issues'])) {
        printf("issues            : %s\n", implode(', ', array_map('strval', (array) $payload['manifest']['issues'])));
    }
    echo "\nOverall\n";
    echo "-------\n";
    printf("ready             : %s\n", ($payload['ready'] ?? false) ? 'yes' : 'no');
    if ($issues !== []) {
        printf("issues            : %s\n", implode(', ', $issues));
    }
}

if ($requireReady && !($payload['ready'] ?? false)) {
    fwrite(STDERR, 'Spatial surface check failed: ' . implode(', ', $issues !== [] ? $issues : ['not-ready']) . PHP_EOL);
    exit(2);
}

exit(0);
