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

const SITE_DOMAIN = 'sowwwl.xyz';
const SITE_TITLE = 'O. le réseau minimal';
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
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto !== '') {
        $parts = preg_split('/\s*,\s*/', $forwardedProto) ?: [];
        $candidate = strtolower(trim((string) ($parts[0] ?? $forwardedProto)));
        if ($candidate === 'https') {
            return 'https';
        }
        if ($candidate === 'http') {
            return 'http';
        }
    }

    $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
    if (str_contains($cfVisitor, '"scheme":"https"')) {
        return 'https';
    }

    return 'http';
}

function request_public_origin(?string $host = null): string
{
    $resolvedHost = request_host($host);
    if ($resolvedHost === '') {
        return SITE_ORIGIN;
    }

    return request_scheme() . '://' . $resolvedHost;
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
    if (!in_array($resolvedHost, ['sowwwl.xyz', 'www.sowwwl.xyz', 'lab.sowwwl.cloud', 'www.lab.sowwwl.cloud'], true)) {
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

    if (in_array($resolvedHost, ['lab.sowwwl.cloud', 'www.lab.sowwwl.cloud'], true)) {
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
            'description' => 'O. le réseau minimal — un espace vivant, personnel, discret. Pose ta terre et laisse la nuit coder le reste.',
            'start_url' => o_route_path('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Signal', 'short_name' => 'Signal', 'url' => o_route_path('/signal')],
                ['name' => 'Str3m', 'short_name' => 'Str3m', 'url' => o_route_path('/str3m')],
                ['name' => '0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_path('/0wlslw0')],
            ],
        ],
        'owl' => [
            'id' => o_route_path('/app/owl'),
            'name' => '0wlslw0',
            'short_name' => '0wlslw0',
            'description' => '0wlslw0 — guide d entree pour comprendre O. et trouver la bonne porte sans se perdre.',
            'start_url' => o_route_path('/0wlslw0'),
            'scope' => o_route_path('/0wlslw0'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Retour au noyau', 'short_name' => 'Noyau', 'url' => o_route_path('/')],
                ['name' => 'Ouvrir Str3m', 'short_name' => 'Str3m', 'url' => o_route_path('/str3m')],
                ['name' => 'Poser une terre', 'short_name' => 'Terre', 'url' => o_route_path('/rejoindre')],
            ],
        ],
        'xyz' => [
            'id' => o_route_path('/app/xyz'),
            'name' => 'SOWWWL XYZ',
            'short_name' => 'XYZ',
            'description' => 'SOWWWL XYZ — surface torique, carte sensible et seuil d entree dans le tore.',
            'start_url' => o_route_path('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Ouvrir 0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_path('/0wlslw0')],
                ['name' => 'Lire Str3m', 'short_name' => 'Str3m', 'url' => o_route_path('/str3m')],
                ['name' => 'Revenir au noyau', 'short_name' => 'Noyau', 'url' => o_route_path('/')],
            ],
        ],
        'lab' => [
            'id' => o_route_path('/app/lab'),
            'name' => 'O. Lab',
            'short_name' => 'Lab',
            'description' => 'O. Lab — atelier mobile du tore pour capteurs, pocket, plasma et livraison differee.',
            'start_url' => o_route_path('/'),
            'scope' => o_route_path('/'),
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Activer les capteurs', 'short_name' => 'Capteurs', 'url' => o_route_path('/') . '#atelier'],
                ['name' => 'QA island', 'short_name' => 'QA', 'url' => o_route_path('/island') . '?u=qa-multimatiere'],
                ['name' => '0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_path('/0wlslw0')],
            ],
        ],
    ];

    foreach ($catalog as $appId => $config) {
        $catalog[$appId]['lang'] = 'fr';
        $catalog[$appId]['display'] = 'standalone';
        $catalog[$appId]['display_override'] = ['window-controls-overlay', 'standalone', 'browser'];
        $catalog[$appId]['orientation'] = 'portrait';
        $catalog[$appId]['icons'] = $icons;
    }

    return $catalog;
}

function pwa_default_app_id(?string $host = null): string
{
    $resolvedHost = request_host($host);

    return match ($resolvedHost) {
        '0wlslw0.com', 'www.0wlslw0.com' => 'owl',
        'sowwwl.xyz', 'www.sowwwl.xyz' => 'xyz',
        'lab.sowwwl.cloud', 'www.lab.sowwwl.cloud' => 'lab',
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

    return o_public_href('manifest.php')
        . '?app=' . rawurlencode($appId)
        . '&v=' . rawurlencode(pwa_manifest_version());
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
    $target = htmlspecialchars(o_route_path($href), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

bootstrap_request();
