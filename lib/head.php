<?php
declare(strict_types=1);

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

    $resolvedHost = request_host();
    $isGuideOwnerHost = in_array($resolvedHost, ['0wlslw0.com', 'www.0wlslw0.com'], true);
    $owlStartUrl = $isGuideOwnerHost ? o_route_href('/') : o_route_href('/0wlslw0');
    $owlScope = $isGuideOwnerHost ? o_route_path('/') : o_route_path('/0wlslw0');

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
                ['name' => 'Instrument', 'short_name' => 'IO', 'url' => sowwwl_instrument_href()],
                ['name' => '0wlslw0', 'short_name' => '0wlslw0', 'url' => o_route_href('/0wlslw0')],
            ],
        ],
        'owl' => [
            'id' => o_route_path('/app/owl'),
            'name' => '0wlslw0',
            'short_name' => '0wlslw0',
            'description' => '0wlslw0 — guide d entree pour comprendre O. et trouver la bonne porte sans se perdre.',
            'start_url' => $owlStartUrl,
            'scope' => $owlScope,
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
                ['name' => 'Ouvrir l’instrument', 'short_name' => 'IO', 'url' => sowwwl_instrument_href()],
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
                ['name' => 'Ouvrir l’instrument', 'short_name' => 'Instrument', 'url' => o_route_href('/#xyz-panel-instrument')],
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

    $root = dirname(__DIR__);
    $manifestMtime = @filemtime($root . '/manifest.php') ?: 0;
    $configMtime = @filemtime($root . '/config.php') ?: 0;
    $headMtime = @filemtime(__FILE__) ?: 0;
    $version = (string) max($manifestMtime, $configMtime, $headMtime, 1);

    return $version;
}

function spatial_native_contract_version(): string
{
    return '2026-05-26';
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

function absolute_public_href(string $asset, ?string $host = null, bool $withVersion = false, ?string $filePath = null): string
{
    return request_public_origin($host) . o_public_href($asset, $withVersion, $filePath);
}

function discovery_origin_from_href(string $href, ?string $host = null): string
{
    $scheme = parse_url($href, PHP_URL_SCHEME);
    $originHost = parse_url($href, PHP_URL_HOST);
    $port = parse_url($href, PHP_URL_PORT);

    if (is_string($scheme) && $scheme !== '' && is_string($originHost) && $originHost !== '') {
        $origin = strtolower($scheme) . '://' . strtolower($originHost);
        if (is_int($port) && $port > 0) {
            $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);
            if ($defaultPort === null || $port !== $defaultPort) {
                $origin .= ':' . $port;
            }
        }

        return $origin;
    }

    return request_public_origin($host);
}

function default_discovery_image_href(string $canonicalHref, ?string $host = null): string
{
    $origin = discovery_origin_from_href($canonicalHref, $host);
    return $origin . o_public_href('icons/icon-512.png', true, dirname(__DIR__) . '/icons/icon-512.png');
}

function render_discovery_json_ld(string $title, string $description, string $canonicalHref, ?string $host = null, array $options = []): string
{
    $siteName = trim((string) ($options['site_name'] ?? 'SOWWWL'));
    $locale = trim((string) ($options['locale'] ?? 'fr_FR'));
    $schemaType = trim((string) ($options['schema_type'] ?? 'WebPage'));
    $siteUrl = trim((string) ($options['site_url'] ?? ''));
    if ($siteUrl === '') {
        $siteUrl = rtrim(discovery_origin_from_href($canonicalHref, $host), '/') . '/';
    }

    $websiteId = rtrim($siteUrl, '/') . '#website';
    $schema = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'name' => $siteName,
            'url' => $siteUrl,
            'inLanguage' => str_replace('_', '-', $locale),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => $schemaType !== '' ? $schemaType : 'WebPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonicalHref,
            'isPartOf' => ['@id' => $websiteId],
            'inLanguage' => str_replace('_', '-', $locale),
        ],
    ];

    $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json) || $json === '') {
        return '';
    }

    return '<script type="application/ld+json">' . $json . '</script>';
}

function current_public_page_href(?string $host = null, array $canonicalParams = []): string
{
    $href = request_public_origin($host) . o_request_path();
    if ($canonicalParams === []) {
        return $href;
    }

    $resolvedParams = [];
    foreach ($canonicalParams as $key) {
        if (!is_string($key) || $key === '') {
            continue;
        }

        $value = $_GET[$key] ?? null;
        if ($value === null || !is_scalar($value)) {
            continue;
        }

        $candidate = trim((string) $value);
        if ($candidate === '') {
            continue;
        }

        $resolvedParams[$key] = $candidate;
    }

    $query = http_build_query($resolvedParams, '', '&', PHP_QUERY_RFC3986);

    return $href . ($query !== '' ? '?' . $query : '');
}

function render_o_discovery_head_tags(string $title, string $description, ?string $host = null, array $options = []): string
{
    $canonicalParams = is_array($options['canonical_params'] ?? null)
        ? $options['canonical_params']
        : [];
    $canonicalHref = trim((string) ($options['canonical_href'] ?? ''));
    if ($canonicalHref === '') {
        $canonicalHref = current_public_page_href($host, $canonicalParams);
    }

    $type = trim((string) ($options['type'] ?? 'website'));
    $siteName = trim((string) ($options['site_name'] ?? 'SOWWWL'));
    $locale = trim((string) ($options['locale'] ?? 'fr_FR'));
    $imageHref = trim((string) ($options['image_href'] ?? ''));
    if ($imageHref === '') {
        $imageHref = default_discovery_image_href($canonicalHref, $host);
    }
    $imageAlt = trim((string) ($options['image_alt'] ?? $title));
    $structuredData = trim((string) ($options['structured_data'] ?? ''));
    if ($structuredData === '') {
        $structuredData = render_discovery_json_ld($title, $description, $canonicalHref, $host, $options);
    }

    $safeCanonicalHref = h($canonicalHref);
    $safeTitle = h($title);
    $safeDescription = h($description);
    $safeType = h($type);
    $safeSiteName = h($siteName);
    $safeLocale = h($locale);
    $safeImageHref = h($imageHref);
    $safeImageAlt = h($imageAlt);

    return <<<HTML
    <link rel="canonical" href="{$safeCanonicalHref}">
    <meta property="og:type" content="{$safeType}">
    <meta property="og:site_name" content="{$safeSiteName}">
    <meta property="og:locale" content="{$safeLocale}">
    <meta property="og:title" content="{$safeTitle}">
    <meta property="og:description" content="{$safeDescription}">
    <meta property="og:url" content="{$safeCanonicalHref}">
    <meta property="og:image" content="{$safeImageHref}">
    <meta property="og:image:alt" content="{$safeImageAlt}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{$safeTitle}">
    <meta name="twitter:description" content="{$safeDescription}">
    <meta name="twitter:image" content="{$safeImageHref}">
    <meta name="twitter:image:alt" content="{$safeImageAlt}">
    {$structuredData}
HTML;
}

function render_o_page_head_assets(?string $preferred = null, ?string $host = null, array $options = []): string
{
    $scriptBundle = strtolower(trim((string) ($options['script_bundle'] ?? 'main')));
    $scriptAsset = $scriptBundle === 'public-shell' ? 'public-shell.js' : 'main.js';
    $loadPageAdapters = $scriptBundle !== 'public-shell';
    $bridgePrefix = h(o_mount_prefix());
    $disableServiceWorker = o_mount_prefix() !== '' ? 'true' : 'false';
    $faviconHref = h(o_public_href('favicon.svg'));
    $pwaHead = render_pwa_head_tags($preferred, $host);
    $spatialContractVersion = h(spatial_native_contract_version());
    $stylesHref = h(o_asset_href('styles.css'));
    $scriptHref = h(o_asset_href($scriptAsset));
    $apparitionsHref = h(o_asset_href('apparitions.js'));
    $mainBundleHref = h(o_asset_href('main.js'));
    $mainStr3mBundleHref = h(o_asset_href('main.str3m.js'));
    $mainSceptreBundleHref = h(o_asset_href('main.sceptre.js'));
    $mainLandscapeBundleHref = h(o_asset_href('main.landscape.js'));
    $mainIslandBundleHref = h(o_asset_href('main.island.js'));
    $mainPagesBundleHref = h(o_asset_href('main.pages.js'));
    $str3mBundleScriptTag = $loadPageAdapters
        ? "\n    <script defer src=\"{$mainStr3mBundleHref}\"></script>"
        : '';
    $sceptreBundleScriptTag = $loadPageAdapters
        ? "\n    <script defer src=\"{$mainSceptreBundleHref}\"></script>"
        : '';
    $landscapeBundleScriptTag = $loadPageAdapters
        ? "\n    <script defer src=\"{$mainLandscapeBundleHref}\"></script>"
        : '';
    $islandBundleScriptTag = $loadPageAdapters
        ? "\n    <script defer src=\"{$mainIslandBundleHref}\"></script>"
        : '';
    $pageAdapterScriptTag = $loadPageAdapters
        ? "\n    <script defer src=\"{$mainPagesBundleHref}\"></script>"
        : '';

    return <<<HTML
    <meta name="o-bridge-prefix" content="{$bridgePrefix}">
    <meta name="o-disable-sw" content="{$disableServiceWorker}">
    <meta name="o-spatial-native-contract" content="{$spatialContractVersion}">
    <meta name="o-spatial-native-event" content="o:native-spatial-state">
    <meta name="o-main-bundle" content="{$mainBundleHref}">
    <meta name="o-main-str3m-bundle" content="{$mainStr3mBundleHref}">
    <meta name="o-main-sceptre-bundle" content="{$mainSceptreBundleHref}">
    <meta name="o-main-landscape-bundle" content="{$mainLandscapeBundleHref}">
    <meta name="o-main-island-bundle" content="{$mainIslandBundleHref}">
    <meta name="o-main-pages-bundle" content="{$mainPagesBundleHref}">
    <link rel="icon" href="{$faviconHref}" type="image/svg+xml">
{$pwaHead}
    <link rel="stylesheet" href="{$stylesHref}">
    <script defer src="{$scriptHref}"></script>
    <script defer src="{$apparitionsHref}"></script>{$str3mBundleScriptTag}{$sceptreBundleScriptTag}{$landscapeBundleScriptTag}{$islandBundleScriptTag}{$pageAdapterScriptTag}
HTML;
}
