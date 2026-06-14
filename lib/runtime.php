<?php
declare(strict_types=1);

function o_mount_prefix(): string
{
    static $prefix = null;

    if (is_string($prefix)) {
        return $prefix;
    }

    $parentDir = dirname(__DIR__);
    $parentMain = $parentDir . DIRECTORY_SEPARATOR . 'main.js';
    $parentStyles = $parentDir . DIRECTORY_SEPARATOR . 'styles.css';
    $localMain = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'main.js';
    $localStyles = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'styles.css';

    $hasBridgedWrapper = is_file($parentMain)
        && is_file($parentStyles)
        && is_file($localMain)
        && is_file($localStyles)
        && (
            (filesize($parentMain) ?: 0) !== (filesize($localMain) ?: 0)
            || (filesize($parentStyles) ?: 0) !== (filesize($localStyles) ?: 0)
        );

    $prefix = $hasBridgedWrapper ? '/o' : '';

    return $prefix;
}

function o_public_href(string $asset, bool $withVersion = false, ?string $filePath = null): string
{
    $relative = ltrim($asset, '/');
    $prefix = o_mount_prefix();
    $href = ($prefix !== '' ? $prefix : '') . '/' . $relative;

    if (!$withVersion) {
        return $href;
    }

    $resolvedPath = is_string($filePath) && $filePath !== ''
        ? $filePath
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $mtime = @filemtime($resolvedPath) ?: 0;

    if ($mtime > 0) {
        $href .= '?v=' . rawurlencode((string) $mtime);
    }

    return $href;
}

function o_asset_href(string $asset): string
{
    return o_public_href($asset, true);
}

function o_route_path(string $path = '/'): string
{
    $candidate = trim($path);
    if ($candidate === '') {
        $candidate = '/';
    }

    if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $candidate) === 1) {
        return $candidate;
    }

    if ($candidate[0] !== '/') {
        $candidate = '/' . ltrim($candidate, '/');
    }

    $prefix = rtrim(o_mount_prefix(), '/');
    if ($prefix === '') {
        return $candidate;
    }

    if ($candidate === $prefix || str_starts_with($candidate, $prefix . '/')) {
        return $candidate;
    }

    return $candidate === '/' ? $prefix . '/' : $prefix . $candidate;
}

function o_route_href(string $path = '/', array $params = [], ?string $host = null, bool $preservePreview = true): string
{
    $candidate = o_route_path($path);
    if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $candidate) === 1) {
        return $candidate;
    }

    $hash = '';
    $hashIndex = strpos($candidate, '#');
    if ($hashIndex !== false) {
        $hash = substr($candidate, $hashIndex);
        $candidate = substr($candidate, 0, $hashIndex);
    }

    $query = '';
    $queryIndex = strpos($candidate, '?');
    if ($queryIndex !== false) {
        $query = substr($candidate, $queryIndex + 1);
        $candidate = substr($candidate, 0, $queryIndex);
    }

    $resolvedParams = [];
    if ($query !== '') {
        parse_str($query, $resolvedParams);
    }

    if ($preservePreview) {
        $resolvedParams = array_merge($resolvedParams, preview_context_query_params($host));
    }

    foreach ($params as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if ($value === null || $value === '') {
            unset($resolvedParams[$key]);
            continue;
        }

        if (is_bool($value)) {
            $resolvedParams[$key] = $value ? '1' : '0';
            continue;
        }

        if (is_scalar($value)) {
            $resolvedParams[$key] = (string) $value;
        }
    }

    $encodedQuery = http_build_query($resolvedParams, '', '&', PHP_QUERY_RFC3986);

    return $candidate . ($encodedQuery !== '' ? '?' . $encodedQuery : '') . $hash;
}

function o_request_query_params(): array
{
    $params = [];

    foreach ($_GET as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if ($value === null) {
            continue;
        }

        if (is_scalar($value)) {
            $params[$key] = (string) $value;
        }
    }

    return $params;
}

function o_current_route_href(array $params = [], ?string $host = null, bool $preservePreview = true): string
{
    $current = o_request_query_params();

    foreach ($params as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        if ($value === null || $value === '') {
            unset($current[$key]);
            continue;
        }

        if (is_bool($value)) {
            $current[$key] = $value ? '1' : '0';
            continue;
        }

        if (is_scalar($value)) {
            $current[$key] = (string) $value;
        }
    }

    return o_route_href(o_request_path(), $current, $host, $preservePreview);
}

function o_request_path(?string $uri = null): string
{
    $path = parse_url((string) ($uri ?? ($_SERVER['REQUEST_URI'] ?? '/')), PHP_URL_PATH);
    $normalized = is_string($path) && $path !== '' ? $path : '/';
    $prefix = rtrim(o_mount_prefix(), '/');

    if ($prefix !== '' && ($normalized === $prefix || str_starts_with($normalized, $prefix . '/'))) {
        $normalized = substr($normalized, strlen($prefix));
        if ($normalized === '') {
            $normalized = '/';
        }
    }

    return $normalized;
}

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $db = getenv('DB_NAME') ?: 'test';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

function request_host(?string $host = null): string
{
    $candidate = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    if ($candidate === '') {
        return '';
    }

    return (string) preg_replace('/:\d+$/', '', $candidate);
}

function sowwwl_user_cloud_slug(?string $host = null): ?string
{
    $resolvedHost = request_host($host);
    if ($resolvedHost === '') {
        return null;
    }

    $suffix = '.' . strtolower(USER_CLOUD_DOMAIN);
    if (!str_ends_with($resolvedHost, $suffix)) {
        return null;
    }

    $slug = substr($resolvedHost, 0, -strlen($suffix));
    if ($slug === '' || str_contains($slug, '.')) {
        return null;
    }

    return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)$/', $slug) === 1
        ? $slug
        : null;
}

function sowwwl_is_user_cloud_host(?string $host = null): bool
{
    return sowwwl_user_cloud_slug($host) !== null;
}

function sowwwl_user_cloud_home_href(string $slug): string
{
    return o_route_href('/island', ['u' => $slug]);
}

function sowwwl_user_cloud_chamber_href(string $slug): string
{
    return o_route_href(USER_CLOUD_CHAMBER_PATH, ['u' => $slug]);
}

function request_scheme(): string
{
    return sowwwl_effective_request_scheme();
}

function request_public_origin(?string $host = null): string
{
    $rawHost = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    $resolvedHost = request_host($rawHost);
    if ($resolvedHost === '') {
        return SITE_ORIGIN;
    }

    $scheme = request_scheme();
    $origin = $scheme . '://' . $resolvedHost;
    $parsed = parse_url($scheme . '://' . $rawHost);
    $port = is_array($parsed) && isset($parsed['port']) ? (int) $parsed['port'] : null;
    $defaultPort = $scheme === 'https' ? 443 : 80;
    if ($port !== null && $port > 0 && $port !== $defaultPort) {
        $origin .= ':' . $port;
    }

    return $origin;
}

function sowwwl_url_origin(?string $url): ?string
{
    $candidate = trim((string) $url);
    if ($candidate === '') {
        return null;
    }

    $parts = parse_url($candidate);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower(trim((string) ($parts['scheme'] ?? '')));
    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($scheme === '' || $host === '') {
        return null;
    }

    $origin = $scheme . '://' . $host;
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);

    if ($port !== null && $port > 0 && $port !== $defaultPort) {
        $origin .= ':' . $port;
    }

    return $origin;
}

function sowwwl_parse_origin_list(?string $raw): array
{
    $origins = [];
    $items = preg_split('/[\s,]+/', trim((string) $raw)) ?: [];

    foreach ($items as $item) {
        $origin = sowwwl_url_origin($item);
        if ($origin !== null) {
            $origins[] = $origin;
        }
    }

    return array_values(array_unique($origins));
}

function sowwwl_runtime_url(string $envKey, string $defaultPath): string
{
    $override = trim((string) (getenv($envKey) ?: ''));
    if ($override !== '') {
        if (in_array(strtolower($override), ['0', 'false', 'off', 'disabled', 'none'], true)) {
            return '';
        }

        if (preg_match('~^(?:https?:)?//~i', $override) === 1) {
            return $override;
        }

        return request_public_origin() . o_route_path($override);
    }

    return request_public_origin() . o_route_path($defaultPath);
}

function sowwwl_runtime_href(string $envKey, string $defaultPath): string
{
    $override = trim((string) (getenv($envKey) ?: ''));
    if ($override !== '') {
        if (in_array(strtolower($override), ['0', 'false', 'off', 'disabled', 'none'], true)) {
            return '';
        }

        return o_route_href($override);
    }

    return o_route_href($defaultPath);
}

function is_local_preview_host(?string $host = null): bool
{
    return in_array(request_host($host), ['127.0.0.1', 'localhost', '[::1]'], true);
}

function sowwwl_is_private_ipv4_host(string $host): bool
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $long = ip2long($host);
    if ($long === false) {
        return false;
    }

    $ranges = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['169.254.0.0', '169.254.255.255'],
    ];

    foreach ($ranges as [$start, $end]) {
        $startLong = ip2long($start);
        $endLong = ip2long($end);
        if ($startLong !== false && $endLong !== false && $long >= $startLong && $long <= $endLong) {
            return true;
        }
    }

    return false;
}

function sowwwl_is_preview_ipv6_host(string $host): bool
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
        return false;
    }

    $normalized = strtolower($host);

    return $normalized === '::1'
        || str_starts_with($normalized, 'fc')
        || str_starts_with($normalized, 'fd')
        || str_starts_with($normalized, 'fe80:');
}

function surface_preview_capable_host(?string $host = null): bool
{
    $resolvedHost = request_host($host);
    if ($resolvedHost === '') {
        return false;
    }

    if (is_local_preview_host($resolvedHost)) {
        return true;
    }

    if (sowwwl_is_private_ipv4_host($resolvedHost) || sowwwl_is_preview_ipv6_host($resolvedHost)) {
        return true;
    }

    $publicPreviewHosts = array_values(array_filter(array_map(
        static fn ($value): string => strtolower(trim((string) $value)),
        explode(',', (string) (getenv('SOWWWL_SURFACE_PREVIEW_HOSTS') ?: ''))
    )));
    if (in_array(strtolower($resolvedHost), $publicPreviewHosts, true)) {
        return true;
    }

    return preg_match('/(?:^|\.)(?:local|localhost|test|internal|lan|home|invalid)$/i', $resolvedHost) === 1;
}

function sowwwl_preview_cookie_name(string $suffix): string
{
    return 'o_preview_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($suffix));
}

function sowwwl_sync_preview_cookie(string $suffix, ?string $value): void
{
    static $synced = [];

    $cookieName = sowwwl_preview_cookie_name($suffix);
    $cacheKey = $cookieName . '|' . ($value ?? '__clear__');
    if (isset($synced[$cacheKey])) {
        return;
    }
    $synced[$cacheKey] = true;

    if (headers_sent()) {
        if ($value === null) {
            unset($_COOKIE[$cookieName]);
        } else {
            $_COOKIE[$cookieName] = $value;
        }
        return;
    }

    $options = [
        'expires' => $value === null ? time() - 3600 : time() + (60 * 60 * 24 * 30),
        'path' => '/',
        'secure' => request_scheme() === 'https',
        'httponly' => false,
        'samesite' => 'Lax',
    ];

    setcookie($cookieName, $value ?? '', $options);

    if ($value === null) {
        unset($_COOKIE[$cookieName]);
    } else {
        $_COOKIE[$cookieName] = $value;
    }
}

function surface_preview_variant(?string $host = null): ?string
{
    if (!surface_preview_capable_host($host)) {
        return null;
    }

    $surface = strtolower(trim((string) ($_GET['surface'] ?? '')));
    if (in_array($surface, ['xyz', 'io', 'lab'], true)) {
        sowwwl_sync_preview_cookie('surface', $surface);
        return $surface;
    }

    $cookieValue = strtolower(trim((string) ($_COOKIE[sowwwl_preview_cookie_name('surface')] ?? '')));

    return in_array($cookieValue, ['xyz', 'io', 'lab'], true) ? $cookieValue : null;
}

function current_surface_variant(?string $host = null): ?string
{
    $previewVariant = surface_preview_variant($host);
    if ($previewVariant !== null) {
        return $previewVariant;
    }

    return match (request_host($host)) {
        'sowwwl.xyz', 'www.sowwwl.xyz' => 'xyz',
        'sowwwl.io', 'www.sowwwl.io' => 'io',
        'lab.sowwwl.cloud', 'www.lab.sowwwl.cloud' => 'lab',
        default => null,
    };
}

function surface_is_mapping_host(?string $host = null): bool
{
    return in_array(current_surface_variant($host), ['xyz', 'io'], true);
}

function current_brand_domain(?string $host = null): string
{
    $resolvedHost = preg_replace('/^www\./', '', request_host($host));
    if (is_string($resolvedHost) && $resolvedHost !== '') {
        return $resolvedHost;
    }

    $originHost = parse_url(SITE_ORIGIN, PHP_URL_HOST);
    if (is_string($originHost) && $originHost !== '') {
        return strtolower($originHost);
    }

    return SITE_DOMAIN;
}

function surface_brand_label(?string $host = null): string
{
    return match (current_surface_variant($host)) {
        'xyz' => 'sowwwl.xyz',
        'io' => 'sowwwl.io',
        'lab' => 'lab.sowwwl.cloud',
        default => current_brand_domain($host),
    };
}

function guide_owner_origin(): string
{
    return 'https://0wlslw0.com';
}

function guide_public_href(?string $host = null, bool $preservePreview = true): string
{
    if (current_surface_variant($host) !== null) {
        return o_route_href('/0wlslw0', [], $host, $preservePreview);
    }

    $resolvedHost = request_host($host);
    if (in_array($resolvedHost, ['0wlslw0.com', 'www.0wlslw0.com'], true)) {
        return o_route_href('/', [], $host, $preservePreview);
    }

    return guide_owner_origin() . '/';
}

function guide_canonical_href(?string $host = null): string
{
    if (current_surface_variant($host) !== null) {
        return current_public_page_href($host);
    }

    return guide_owner_origin() . '/';
}

function sowwwl_instrument_href(?string $host = null): string
{
    $resolvedHost = request_host($host);
    if (current_surface_variant($resolvedHost) === 'io') {
        return o_route_href('/#xyz-panel-instrument', [], $resolvedHost);
    }

    return 'https://sowwwl.io/#xyz-panel-instrument';
}

function spatial_preview_mode(?string $host = null): string
{
    if (current_surface_variant($host) !== 'io') {
        return 'screen';
    }

    $queryMode = strtolower(trim((string) ($_GET['spatial'] ?? '')));
    if (in_array($queryMode, ['screen', 'headset'], true)) {
        sowwwl_sync_preview_cookie('spatial', $queryMode);
        return $queryMode;
    }

    $cookieValue = strtolower(trim((string) ($_COOKIE[sowwwl_preview_cookie_name('spatial')] ?? '')));

    return in_array($cookieValue, ['screen', 'headset'], true) ? $cookieValue : 'screen';
}

function preview_context_query_params(?string $host = null): array
{
    $params = [];
    $previewVariant = surface_preview_variant($host);
    if ($previewVariant !== null) {
        $params['surface'] = $previewVariant;
    }

    if (current_surface_variant($host) === 'io' && spatial_preview_mode($host) === 'headset') {
        $params['spatial'] = 'headset';
    }

    return $params;
}
