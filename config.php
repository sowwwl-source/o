<?php
declare(strict_types=1);

function load_dotenv(string $path): void
{
    if (!is_readable($path) || is_dir($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        if (strncmp($line, 'export ', 7) === 0) {
            $line = trim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
            continue;
        }

        if (getenv($key) !== false) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if ($value !== '') {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }
        }

        if (function_exists('putenv')) {
            @putenv($key . '=' . $value);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_dotenv(dirname(__DIR__) . '/.env');
load_dotenv(__DIR__ . '/.env');

require_once __DIR__ . '/lib/request_trust.php';

const SITE_DOMAIN = 'sowwwl.xyz';
const SITE_TITLE = 'O. Le réseau minimal';
const SITE_TAGLINE = 'Just the Three of Us';
const DEFAULT_TIMEZONE = 'Europe/Paris';
const CREATE_LAND_RATE_LIMIT_MAX_ATTEMPTS = 6;
const CREATE_LAND_RATE_LIMIT_WINDOW_SECONDS = 600;
const AUTH_MIN_PASSWORD_LENGTH = 8;
const LAND_LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LAND_LOGIN_RATE_LIMIT_WINDOW_SECONDS = 900;
const DEFAULT_AZA_MAX_UPLOAD_BYTES = 2147483648;

$publicOriginOverride = trim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: ''));
$rateLimitOverride = trim((string) (getenv('SOWWWL_RATE_LIMIT_DIR') ?: ''));
$storageOverride = trim((string) (getenv('SOWWWL_STORAGE_DIR') ?: ''));
$azaStorageOverride = trim((string) (getenv('SOWWWL_AZA_STORAGE_DIR') ?: ''));
$azaUploadOverride = trim((string) (getenv('SOWWWL_AZA_MAX_UPLOAD_BYTES') ?: ''));
$azaDirectOriginOverride = trim((string) (getenv('SOWWWL_AZA_DIRECT_ORIGIN') ?: ''));
define(
    'SITE_ORIGIN',
    $publicOriginOverride !== ''
        ? rtrim($publicOriginOverride, '/')
        : 'https://' . SITE_DOMAIN
);
define(
    'LANDS_DIR',
    $storageOverride !== ''
        ? rtrim($storageOverride, DIRECTORY_SEPARATOR)
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'lands'
);

define(
    'RATE_LIMIT_DIR',
    $rateLimitOverride !== ''
        ? rtrim($rateLimitOverride, DIRECTORY_SEPARATOR)
        : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'rate-limit'
);

define(
    'AZA_STORAGE_DIR',
    $azaStorageOverride !== ''
        ? rtrim($azaStorageOverride, DIRECTORY_SEPARATOR)
        : (is_dir('/var/www/runtime')
            ? '/var/www/runtime/aza'
            : __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'aza')
);

define(
    'T0K_STORAGE_DIR',
    dirname(LANDS_DIR) . DIRECTORY_SEPARATOR . 't0k'
);

define(
    'N0DE_STORAGE_DIR',
    dirname(LANDS_DIR) . DIRECTORY_SEPARATOR . 'n0des'
);

define(
    'B0T3_STORAGE_DIR',
    dirname(LANDS_DIR) . DIRECTORY_SEPARATOR . 'b0t3'
);

define(
    'AZA_MAX_UPLOAD_BYTES',
    ctype_digit($azaUploadOverride)
        ? max(16 * 1024 * 1024, (int) $azaUploadOverride)
        : DEFAULT_AZA_MAX_UPLOAD_BYTES
);

define(
    'AZA_DIRECT_ORIGIN',
    $azaDirectOriginOverride !== ''
        ? rtrim($azaDirectOriginOverride, '/')
        : ''
);

function aza_public_storage_root(): string
{
    return 'storage/aza';
}

function aza_public_storage_path(string $suffix = ''): string
{
    $normalizedSuffix = ltrim(str_replace('\\', '/', $suffix), '/');
    $base = aza_public_storage_root();

    return $normalizedSuffix !== ''
        ? $base . '/' . $normalizedSuffix
        : $base;
}

function aza_absolute_storage_path(?string $publicPath): ?string
{
    $publicPath = trim((string) $publicPath);
    if ($publicPath === '') {
        return null;
    }

    $query = parse_url($publicPath, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $params = [];
        parse_str($query, $params);
        $requestedFile = trim((string) ($params['f'] ?? ''));
        if ($requestedFile !== '') {
            $publicPath = $requestedFile;
        } else {
            $path = parse_url($publicPath, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $publicPath = $path;
            }
        }
    } elseif (str_contains($publicPath, '://') || str_contains($publicPath, '?')) {
        $path = parse_url($publicPath, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $publicPath = $path;
        }
    }

    $normalized = ltrim(str_replace('\\', '/', $publicPath), '/');
    $mountPrefix = trim(o_mount_prefix(), '/');
    if ($mountPrefix !== '' && ($normalized === $mountPrefix || str_starts_with($normalized, $mountPrefix . '/'))) {
        $normalized = ltrim(substr($normalized, strlen($mountPrefix)), '/');
    }
    $publicRoot = aza_public_storage_root();

    if ($normalized === $publicRoot) {
        return AZA_STORAGE_DIR;
    }

    $prefix = $publicRoot . '/';
    if (str_starts_with($normalized, $prefix)) {
        $relative = substr($normalized, strlen($prefix));
        return AZA_STORAGE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    return __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
}

function o_mount_prefix(): string
{
    static $prefix = null;

    if (is_string($prefix)) {
        return $prefix;
    }

    $parentDir = dirname(__DIR__);
    $parentMain = $parentDir . DIRECTORY_SEPARATOR . 'main.js';
    $parentStyles = $parentDir . DIRECTORY_SEPARATOR . 'styles.css';
    $localMain = __DIR__ . DIRECTORY_SEPARATOR . 'main.js';
    $localStyles = __DIR__ . DIRECTORY_SEPARATOR . 'styles.css';

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
        : __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
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

function surface_brand_label(?string $host = null): string
{
    return match (current_surface_variant($host)) {
        'xyz' => 'sowwwl.xyz',
        'io' => 'sowwwl.io',
        'lab' => 'lab.sowwwl.cloud',
        default => current_brand_domain($host),
    };
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

function plasma_bridge_url(): string
{
    return sowwwl_runtime_url('SOWWWL_MEMBRANE_BRIDGE_URL', '/ingest/membrane');
}

function plasma_feed_url(): string
{
    return sowwwl_runtime_url('SOWWWL_PLASMA_FEED_URL', '/plasma/recent');
}

function plasma_configured_allowed_origins(): array
{
    return sowwwl_parse_origin_list((string) (getenv('SOWWWL_PLASMA_ALLOWED_ORIGINS') ?: ''));
}

function plasma_connect_src_origins(?string $host = null): array
{
    $resolvedHost = request_host($host);
    $surfaceVariant = current_surface_variant($resolvedHost);
    if (!in_array($surfaceVariant, ['xyz', 'io', 'lab'], true)) {
        return [];
    }

    $origins = [];
    $currentOrigin = request_public_origin($resolvedHost);

    foreach ([plasma_bridge_url(), plasma_feed_url()] as $url) {
        $origin = sowwwl_url_origin($url);
        if ($origin !== null && $origin !== $currentOrigin) {
            $origins[] = $origin;
        }
    }

    if ($surfaceVariant === 'lab') {
        $origins[] = 'https://api.lab.sowwwl.cloud';
    }

    return array_values(array_unique($origins));
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

$pdo = null;
try {
    $pdo = get_pdo();
} catch (Throwable $exception) {
    $pdo = null;
}

require_once __DIR__ . '/lib/lands.php';
require_once __DIR__ . '/lib/aza_archive.php';
require_once __DIR__ . '/lib/aza_ingest.php';
require_once __DIR__ . '/lib/aza_memory.php';
require_once __DIR__ . '/lib/t0k.php';
require_once __DIR__ . '/lib/n0de.php';
require_once __DIR__ . '/lib/b0t3.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/plasma_bridge.php';
require_once __DIR__ . '/lib/meaning.php';
require_once __DIR__ . '/lib/signal_mail.php';

function pwa_app_catalog(): array
{
    static $catalog = null;

    if (is_array($catalog)) {
        return $catalog;
    }

    $icons = [
        [
            'src' => o_public_href('icons/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => o_public_href('icons/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => o_public_href('icons/icon-mask-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src' => o_public_href('icons/icon-mask-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
        [
            'src' => o_public_href('icons/icon.svg'),
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src' => o_public_href('icons/icon-mask.svg'),
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'maskable',
        ],
    ];

    $catalog = [
        'main' => [
            'id' => o_route_path('/app/main'),
            'name' => SITE_TITLE,
            'short_name' => 'O.',
            'description' => 'O. Le réseau minimal — un espace vivant, personnel, discret. Pose ta terre et laisse la nuit coder le reste.',
            'start_url' => o_route_href('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Signal', 'short_name' => 'Signal', 'url' => o_route_href('/signal')],
                ['name' => 'Str3m', 'short_name' => 'Str3m', 'url' => o_route_href('/str3m')],
                ['name' => '0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_href('/0wlslw0')],
            ],
        ],
        'owl' => [
            'id' => o_route_path('/app/owl'),
            'name' => '0wlslw0',
            'short_name' => '0wlslw0',
            'description' => '0wlslw0 — guide d entree pour comprendre O. et trouver la bonne porte sans se perdre.',
            'start_url' => o_route_href('/0wlslw0'),
            'scope' => o_route_path('/0wlslw0'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Retour au noyau', 'short_name' => 'Noyau', 'url' => o_route_href('/')],
                ['name' => 'Ouvrir Str3m', 'short_name' => 'Str3m', 'url' => o_route_href('/str3m')],
                ['name' => 'Poser une terre', 'short_name' => 'Terre', 'url' => o_route_href('/rejoindre')],
            ],
        ],
        'xyz' => [
            'id' => o_route_path('/app/xyz'),
            'name' => 'SOWWWL XYZ',
            'short_name' => 'XYZ',
            'description' => 'SOWWWL XYZ — surface torique, carte sensible et seuil d entree dans le tore.',
            'start_url' => o_route_href('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Ouvrir 0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_href('/0wlslw0')],
                ['name' => 'Lire Str3m', 'short_name' => 'Str3m', 'url' => o_route_href('/str3m')],
                ['name' => 'Revenir au noyau', 'short_name' => 'Noyau', 'url' => o_route_href('/')],
            ],
        ],
        'io' => [
            'id' => o_route_path('/app/io'),
            'name' => 'SOWWWL IO',
            'short_name' => 'IO',
            'description' => 'SOWWWL IO — surface spatiale pour visionOS, casques XR et tore situe dans l espace.',
            'start_url' => o_route_href('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'orientation' => 'any',
            'shortcuts' => [
                ['name' => 'Ouvrir 0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_href('/0wlslw0')],
                ['name' => 'Lire Str3m', 'short_name' => 'Str3m', 'url' => o_route_href('/str3m')],
                ['name' => 'Voir la carte', 'short_name' => 'Carte', 'url' => o_route_href('/map')],
            ],
        ],
        'lab' => [
            'id' => o_route_path('/app/lab'),
            'name' => 'O. Lab',
            'short_name' => 'Lab',
            'description' => 'O. Lab — atelier mobile du tore pour capteurs, pocket, plasma et livraison differee.',
            'start_url' => o_route_href('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Activer les capteurs', 'short_name' => 'Capteurs', 'url' => o_route_href('/#atelier')],
                ['name' => 'QA island', 'short_name' => 'QA', 'url' => o_route_href('/island', ['u' => 'qa-multimatiere'])],
                ['name' => '0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_href('/0wlslw0')],
            ],
        ],
    ];

    foreach ($catalog as $appId => $config) {
        $catalog[$appId]['lang'] = (string) ($config['lang'] ?? 'fr');
        $catalog[$appId]['display'] = (string) ($config['display'] ?? 'standalone');
        $catalog[$appId]['display_override'] = is_array($config['display_override'] ?? null)
            ? $config['display_override']
            : ['window-controls-overlay', 'standalone', 'browser'];
        $catalog[$appId]['orientation'] = (string) ($config['orientation'] ?? 'portrait');
        $catalog[$appId]['icons'] = $icons;
    }

    return $catalog;
}

function pwa_default_app_id(?string $host = null): string
{
    $resolvedHost = request_host($host);

    if (in_array($resolvedHost, ['0wlslw0.com', 'www.0wlslw0.com'], true)) {
        return 'owl';
    }

    return match (current_surface_variant($resolvedHost)) {
        'xyz' => 'xyz',
        'io' => 'io',
        'lab' => 'lab',
        default => 'main',
    };
}

function pwa_app_config(?string $preferred = null, ?string $host = null): array
{
    $catalog = pwa_app_catalog();
    $resolvedId = is_string($preferred) && isset($catalog[$preferred])
        ? $preferred
        : pwa_default_app_id($host);

    return $catalog[$resolvedId] ?? $catalog['main'];
}

function pwa_manifest_version(): string
{
    static $version = null;

    if (is_string($version) && $version !== '') {
        return $version;
    }

    $manifestMtime = @filemtime(__DIR__ . '/manifest.php') ?: 0;
    $configMtime = @filemtime(__FILE__) ?: 0;
    $version = (string) max($manifestMtime, $configMtime, 1);

    return $version;
}

function pwa_manifest_href(?string $preferred = null, ?string $host = null): string
{
    $catalog = pwa_app_catalog();
    $appId = is_string($preferred) && isset($catalog[$preferred])
        ? $preferred
        : pwa_default_app_id($host);

    $params = array_merge(
        ['app' => $appId, 'v' => pwa_manifest_version()],
        preview_context_query_params($host)
    );

    return o_public_href('manifest.php') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function render_pwa_head_tags(?string $preferred = null, ?string $host = null): string
{
    $config = pwa_app_config($preferred, $host);
    $manifestHref = h(pwa_manifest_href($preferred, $host));
    $appName = h((string) ($config['name'] ?? SITE_TITLE));
    $shortName = h((string) ($config['short_name'] ?? 'O.'));
    $appleIcon = h(o_public_href('apple-touch-icon.png', true));

    return <<<HTML
    <link rel="manifest" href="{$manifestHref}">
    <meta name="application-name" content="{$appName}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{$shortName}">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="{$appleIcon}">
HTML;
}

function render_o_page_head_assets(?string $preferred = null, ?string $host = null): string
{
    $bridgePrefix = h(o_mount_prefix());
    $disableServiceWorker = o_mount_prefix() !== '' ? 'true' : 'false';
    $faviconHref = h(o_public_href('favicon.svg'));
    $pwaHead = render_pwa_head_tags($preferred, $host);
    $stylesHref = h(o_asset_href('styles.css'));
    $scriptHref = h(o_asset_href('main.js'));

    return <<<HTML
    <meta name="o-bridge-prefix" content="{$bridgePrefix}">
    <meta name="o-disable-sw" content="{$disableServiceWorker}">
    <link rel="icon" href="{$faviconHref}" type="image/svg+xml">
{$pwaHead}
    <link rel="stylesheet" href="{$stylesHref}">
    <script defer src="{$scriptHref}"></script>
HTML;
}

function render_skip_link(string $targetId = 'main-content', string $label = 'Aller au contenu'): string
{
    $target = htmlspecialchars($targetId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $copy = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<a class="skip-link" href="#' . $target . '" data-skip-link>' . $copy . '</a>';
}

function render_nucleus_banner(string $currentLabel = 'surface', string $href = '/'): string
{
    $label = htmlspecialchars(trim($currentLabel) !== '' ? trim($currentLabel) : 'surface', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $target = htmlspecialchars(o_route_href($href), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $aria = htmlspecialchars(
        'Retour au noyau. Réalité traverse le plasma, se boucle en tore, puis revient au noyau. Appui long tactile puis glisse pour naviguer dans le tore. Surface actuelle : ' . $currentLabel . '.',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return <<<HTML
    <a class="nucleus-banner" href="{$target}" data-nucleus-banner aria-label="{$aria}">
        <span class="nucleus-banner__art" aria-hidden="true">
            <span class="nucleus-banner__orbit nucleus-banner__orbit--outer"></span>
            <span class="nucleus-banner__orbit nucleus-banner__orbit--inner"></span>
            <span class="nucleus-banner__core"></span>
            <span class="nucleus-banner__flux nucleus-banner__flux--left"></span>
            <span class="nucleus-banner__flux nucleus-banner__flux--right"></span>
            <span class="nucleus-banner__spark nucleus-banner__spark--one"></span>
            <span class="nucleus-banner__spark nucleus-banner__spark--two"></span>
        </span>
        <span class="nucleus-banner__copy">
            <span class="nucleus-banner__eyebrow">commande noyau</span>
            <strong class="nucleus-banner__title">Réalité -> plasma -> tore -> noyau</strong>
            <span class="nucleus-banner__note" data-nucleus-banner-note>clic : noyau · appui long tactile : glisse</span>
        </span>
        <span class="nucleus-banner__state">
            <span class="nucleus-banner__state-label">actuel</span>
            <strong>{$label}</strong>
        </span>
    </a>
HTML;
}

function render_spatial_context_bar(string $view = 'surface', ?string $host = null, ?string $note = null): string
{
    $resolvedHost = request_host($host);
    if (current_surface_variant($resolvedHost) !== 'io') {
        return '';
    }

    $viewKey = strtolower(trim($view));
    $viewCatalog = [
        'guide' => [
            'label' => '0wlslw0',
            'title' => 'Le seuil spatial reste ouvert.',
            'copy' => 'Terre ouvre l orientation. Mine garde la relance courte avant de pousser ailleurs.',
            'dominant' => 'terre',
            'note' => 'Le guide garde la mémoire du basculement écran / casque pour ne pas perdre le passage.',
        ],
        'map' => [
            'label' => 'map',
            'title' => 'La carte cadre le volume actif.',
            'copy' => 'Terre pose les repères et Mine suit les courants, les écarts et les foyers de lecture.',
            'dominant' => 'terre',
            'note' => 'La carte reste lente et lisible : elle prépare le regard avant la future couche native.',
        ],
        'signal' => [
            'label' => 'signal',
            'title' => 'Le fil direct tient dans la main mine.',
            'copy' => 'Mine ouvre la conversation, Terre garde l adresse, la reprise et le retour vers la terre liée.',
            'dominant' => 'mine',
            'note' => 'Le fil garde son contexte pendant la bascule pour éviter de casser l écriture en route.',
        ],
        'echo' => [
            'label' => 'echo',
            'title' => 'La liaison courte reste en visée directe.',
            'copy' => 'Mine pousse le message sans détour et Terre garde le retour vers Signal, le noyau et la terre active.',
            'dominant' => 'mine',
            'note' => 'Echo reste court, stable et lisible pour un casque: une cible, une réponse, un retour clair.',
        ],
        'str3m' => [
            'label' => 'str3m',
            'title' => 'Le courant public garde sa houle.',
            'copy' => 'Terre cadre les présences visibles et Mine capte les gestes, les preuves et les bifurcations rapides.',
            'dominant' => 'terre',
            'note' => 'Str3m reste la grande nappe publique: lecture lente, repères clairs, bifurcation rapide vers les terres.',
        ],
        'island' => [
            'label' => 'island',
            'title' => 'L ile garde une lecture situee.',
            'copy' => 'Terre cadre le relief, Mine choisit la matiere la plus juste avant de pousser plus loin dans la memoire.',
            'dominant' => 'terre',
            'note' => 'L ile reste stable pendant que la surface spatiale decide quelle matiere doit prendre la main.',
        ],
        'surface' => [
            'label' => 'surface',
            'title' => 'La surface spatiale garde son axe.',
            'copy' => 'Terre tient l orientation générale et Mine relance les gestes courts, les fils et les détours.',
            'dominant' => 'terre',
            'note' => 'Le mode casque web reste une maquette stable avant le vrai client spatial.',
        ],
    ];

    $viewConfig = $viewCatalog[$viewKey] ?? $viewCatalog['surface'];
    $isHeadsetMode = spatial_preview_mode($resolvedHost) === 'headset';
    $modeLabel = $isHeadsetMode ? 'mode casque web' : 'projection ecran';
    $modeCopy = $isHeadsetMode
        ? 'focus large, lecture stable et routes visibles avant regard + pinch natifs'
        : 'preview souple pour cadrer, écrire et régler le parcours avant le casque';
    $resolvedNote = trim($note ?? '') !== '' ? trim((string) $note) : (string) $viewConfig['note'];
    $dominantHand = (string) ($viewConfig['dominant'] ?? 'terre');
    $surfaceLabel = surface_brand_label($resolvedHost);
    $modeScreenHref = o_current_route_href(['spatial' => null], $resolvedHost, false);
    $modeHeadsetHref = o_current_route_href(['spatial' => 'headset'], $resolvedHost, false);
    $homeHref = o_route_href('/', [], $resolvedHost);

    $routeGroups = [
        'terre' => [
            'title' => 'main terre',
            'copy' => 'ouvrir, cadrer, porter',
            'links' => [
                ['key' => 'guide', 'label' => '0wlslw0', 'href' => o_route_href('/0wlslw0', [], $resolvedHost)],
                ['key' => 'map', 'label' => 'Map', 'href' => o_route_href('/map', [], $resolvedHost)],
            ],
        ],
        'mine' => [
            'title' => 'main mine',
            'copy' => 'inciser, relancer, ecrire',
            'links' => [
                ['key' => 'signal', 'label' => 'Signal', 'href' => o_route_href('/signal', [], $resolvedHost)],
                ['key' => 'str3m', 'label' => 'Str3m', 'href' => o_route_href('/str3m', [], $resolvedHost)],
            ],
        ],
    ];

    ob_start();
    ?>
    <section class="spatial-context reveal" data-spatial-context data-spatial-view="<?= h($viewKey) ?>" data-spatial-dominant="<?= h($dominantHand) ?>" aria-label="Contexte spatial de sowwwl.io">
        <div class="spatial-context__head">
            <p class="spatial-context__eyebrow"><strong><?= h($surfaceLabel) ?></strong> <span><?= h($modeLabel) ?></span></p>
            <h2 class="spatial-context__title"><?= h((string) $viewConfig['title']) ?></h2>
            <p class="spatial-context__copy"><?= h((string) $viewConfig['copy']) ?></p>
        </div>
        <div class="spatial-context__grid">
            <article class="spatial-context__panel spatial-context__panel--mode" data-spatial-panel="mode">
                <span class="spatial-context__tag">mode spatial</span>
                <strong><?= h($modeLabel) ?></strong>
                <p><?= h($modeCopy) ?></p>
                <div class="xyz-surface-route-links xyz-surface-route-links--mode" aria-label="Basculer le mode spatial">
                    <a class="ghost-link" data-spatial-link href="<?= h($modeScreenHref) ?>"<?= $isHeadsetMode ? '' : ' aria-current="page"' ?>>Projection écran</a>
                    <a class="ghost-link" data-spatial-link href="<?= h($modeHeadsetHref) ?>"<?= $isHeadsetMode ? ' aria-current="page"' : '' ?>>Mode casque web</a>
                    <a class="ghost-link" data-spatial-link href="<?= h($homeHref) ?>">Noyau</a>
                </div>
                <p class="spatial-context__ra" data-spatial-ra-note>Le tore garde ici le dernier régime utile, même quand tu quittes la membrane.</p>
                <div class="xyz-surface-route-links spatial-context__ra-actions" data-spatial-ra-actions aria-label="Prises recommandées par la modulation" hidden>
                    <a class="ghost-link" data-spatial-ra-primary href="<?= h($homeHref) ?>">Revenir au noyau</a>
                    <a class="ghost-link" data-spatial-ra-secondary href="<?= h($homeHref) ?>">Revenir au noyau</a>
                </div>
                <div class="spatial-context__world" data-spatial-world>
                    <div class="spatial-context__world-grid" aria-label="Mémoire du monde instrument">
                        <p><span>vue</span><strong data-spatial-world-view>mémoire calme</strong></p>
                        <p><span>focus</span><strong data-spatial-world-focus>aucune prise récente</strong></p>
                        <p><span>corps</span><strong data-spatial-world-body>corps en réserve</strong></p>
                        <p><span>mains</span><strong data-spatial-world-hands>terre et mine au repos</strong></p>
                        <p><span>lumière</span><strong data-spatial-world-light>lueur en mémoire</strong></p>
                    </div>
                    <p class="spatial-context__world-copy" data-spatial-world-copy>Le dernier monde instrument rejouable se posera ici quand la membrane aura parlé.</p>
                </div>
            </article>
            <?php foreach ($routeGroups as $hand => $group): ?>
                <article class="spatial-context__panel spatial-context__panel--hand" data-spatial-panel="<?= h($hand) ?>" data-spatial-hand="<?= h($hand) ?>">
                    <span class="spatial-context__tag"><?= h((string) $group['title']) ?></span>
                    <strong><?= h((string) $group['copy']) ?></strong>
                    <div class="xyz-surface-route-links" aria-label="<?= h('Routes ' . (string) $group['title']) ?>">
                        <?php foreach ($group['links'] as $link): ?>
                            <a class="ghost-link" data-spatial-link data-spatial-link-key="<?= h((string) $link['key']) ?>" href="<?= h((string) $link['href']) ?>"<?= $viewKey === (string) $link['key'] ? ' aria-current="page"' : '' ?>><?= h((string) $link['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="spatial-context__note"><?= h($resolvedNote) ?></p>
        <p class="spatial-context__hint">Mode casque web : fleches pour circuler, 1 2 3 pour viser un panneau, Entree pour ouvrir.</p>
    </section>
    <?php

    return trim((string) ob_get_clean());
}

function main_landmark_attrs(string $id = 'main-content'): string
{
    $target = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return 'id="' . $target . '" tabindex="-1" data-skip-target';
}

function visual_profile_tokens(?array $visualProfile = null, string $streamMood = 'calm'): array
{
    $resolvedMood = trim($streamMood) !== '' ? trim($streamMood) : 'calm';
    $profile = is_array($visualProfile) ? $visualProfile : land_collective_profile($resolvedMood);

    return [
        'profile' => $profile,
        'mood' => $resolvedMood,
        'program' => trim((string) ($profile['program'] ?? 'collective')) ?: 'collective',
        'label' => trim((string) ($profile['label'] ?? 'collectif')) ?: 'collectif',
        'lambda' => (int) ($profile['lambda_nm'] ?? 548),
    ];
}

function render_negative_merge_overlay(?array $visualProfile = null, string $streamMood = 'calm', string $view = 'generic'): string
{
    $tokens = visual_profile_tokens($visualProfile, $streamMood);
    $program = h($tokens['program']);
    $label = h($tokens['label']);
    $lambda = $tokens['lambda'];
    $viewToken = h(preg_replace('/[^a-z0-9_-]+/i', '-', trim($view)) ?: 'generic');
    $streamImage = h(o_public_href('storage/str3m/images/flux-radial.svg'));
    $mood = h($tokens['mood']);

    return <<<HTML
<div class="page-ambient-merge page-ambient-merge--{$viewToken}" aria-hidden="true">
    <div class="page-ambient-merge__stream page-ambient-merge__stream--primary">
        <img src="{$streamImage}" alt="" decoding="async">
    </div>
    <div class="page-ambient-merge__stream page-ambient-merge__stream--secondary">
        <img src="{$streamImage}" alt="" decoding="async">
    </div>
    <div class="page-ambient-merge__torus-shell">
        <canvas
            class="page-ambient-merge__torus"
            data-torus-cloud
            data-torus-passive="1"
            data-land-type="{$program}"
            data-land-label="{$label}"
            data-lambda="{$lambda}"
            data-stream-mood="{$mood}"
        ></canvas>
    </div>
</div>
HTML;
}

if (!defined('SOWWWL_SKIP_BOOTSTRAP_REQUEST') || SOWWWL_SKIP_BOOTSTRAP_REQUEST !== true) {
    bootstrap_request();
}
