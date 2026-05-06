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
            'id' => '/app/main',
            'name' => SITE_TITLE,
            'short_name' => 'O.',
            'description' => 'O. le réseau minimal — un espace vivant, personnel, discret. Pose ta terre et laisse la nuit coder le reste.',
            'start_url' => '/',
            'scope' => '/',
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Signal', 'short_name' => 'Signal', 'url' => '/signal'],
                ['name' => 'Str3m', 'short_name' => 'Str3m', 'url' => '/str3m'],
                ['name' => '0wlslw0', 'short_name' => 'Owl', 'url' => '/0wlslw0'],
            ],
        ],
        'owl' => [
            'id' => '/app/owl',
            'name' => '0wlslw0',
            'short_name' => 'Owl',
            'description' => '0wlslw0 — guide d entree pour comprendre O. et trouver la bonne porte sans se perdre.',
            'start_url' => '/0wlslw0',
            'scope' => '/0wlslw0',
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Retour au noyau', 'short_name' => 'Noyau', 'url' => '/'],
                ['name' => 'Ouvrir Str3m', 'short_name' => 'Str3m', 'url' => '/str3m'],
                ['name' => 'Poser une terre', 'short_name' => 'Terre', 'url' => '/rejoindre.php'],
            ],
        ],
        'xyz' => [
            'id' => '/app/xyz',
            'name' => 'SOWWWL XYZ',
            'short_name' => 'XYZ',
            'description' => 'SOWWWL XYZ — surface torique, carte sensible et seuil d entree dans le tore.',
            'start_url' => '/',
            'scope' => '/',
            'theme_color' => '#09090b',
            'background_color' => '#09090b',
            'shortcuts' => [
                ['name' => 'Ouvrir 0wlslw0', 'short_name' => 'Owl', 'url' => '/0wlslw0'],
                ['name' => 'Lire Str3m', 'short_name' => 'Str3m', 'url' => '/str3m'],
                ['name' => 'Revenir au noyau', 'short_name' => 'Noyau', 'url' => '/'],
            ],
        ],
    ];

    foreach ($catalog as $appId => $config) {
        $catalog[$appId]['lang'] = 'fr';
        $catalog[$appId]['display'] = 'standalone';
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
    <script>window.__O_BRIDGE_PREFIX__ = '{$bridgePrefix}'; window.__O_DISABLE_SW__ = {$disableServiceWorker};</script>
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
    $streamImage = h('/storage/str3m/images/flux-radial.svg');
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
