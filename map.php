<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/map');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Carte expérimentale de <?= h($brandDomain) ?> — version MVP.">
    <meta name="theme-color" content="#09090b">
    <title>Map — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>

    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.3.2/dist/maplibre-gl.css">
    <script src="https://unpkg.com/maplibre-gl@4.3.2/dist/maplibre-gl.js"></script>

    <style>
        .map-shell {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 1rem;
            width: min(1200px, 96vw);
            margin: 0 auto;
            padding: 1rem 0 1.5rem;
        }

        .map-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .map-head .meta {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        #sowwwl-map {
            min-height: 72vh;
            border: 1px solid var(--o-line);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--o-shadow);
        }

        .map-note {
            margin: 0;
            opacity: 0.72;
            font-size: 0.86rem;
        }

        .map-popup {
            color: #101218;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .map-popup strong {
            display: block;
            margin-bottom: 0.2rem;
        }
    </style>
</head>
<body class="experience map-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="map-shell reveal on">
    <header class="map-head">
        <div>
            <p class="eyebrow"><strong>map</strong> <span>mvp / étape 2</span></p>
            <h1 class="land-title"><strong>Sowwwl visible en carte</strong> <span>base OSM / MapLibre</span></h1>
        </div>
        <div class="meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="/str3m">str3m</a>
            <span class="meta-pill">preview géo</span>
        </div>
    </header>

    <div id="sowwwl-map" aria-label="Carte Sowwwl"></div>
    <p class="map-note" id="map-note">Chargement des points publics…</p>
</main>

<script>
(() => {
    const map = new maplibregl.Map({
        container: 'sowwwl-map',
        style: {
            version: 8,
            sources: {
                osm: {
                    type: 'raster',
                    tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
                    tileSize: 256,
                    attribution: '&copy; OpenStreetMap contributors'
                }
            },
            layers: [
                {
                    id: 'osm-base',
                    type: 'raster',
                    source: 'osm'
                }
            ]
        },
        center: [2.2137, 46.2276],
        zoom: 4.6
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: true }), 'top-right');

    const note = document.getElementById('map-note');

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const popupHtml = (properties = {}) => {
        const name = escapeHtml(properties.username || properties.slug || 'Terre');
        const slug = escapeHtml(properties.slug || 'inconnue');
        const timezone = escapeHtml(properties.timezone || 'n/a');
        const signalCount = Number(properties.signal_public_count || 0);
        const href = properties.land_url || '/land';

        return `
            <div class="map-popup">
                <strong>${name}</strong>
                <span>@${slug}</span><br>
                <span>Fuseau: ${timezone}</span><br>
                <span>Signaux publics: ${signalCount}</span><br>
                <a href="${escapeHtml(href)}">ouvrir la terre</a>
            </div>
        `;
    };

    const addMarkersFromFeatures = (features) => {
        const bounds = new maplibregl.LngLatBounds();
        let rendered = 0;

        features.forEach((feature) => {
            const geometry = feature?.geometry || {};
            const coordinates = Array.isArray(geometry.coordinates) ? geometry.coordinates : [];
            const lng = Number(coordinates[0]);
            const lat = Number(coordinates[1]);

            if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
                return;
            }

            const properties = feature?.properties || {};
            const signalCount = Number(properties.signal_public_count || 0);
            const markerColor = signalCount > 0 ? '#0b6b4a' : '#5f7d71';
            const markerLabel = properties.username || properties.slug || 'point';

            const marker = new maplibregl.Marker({ color: markerColor })
                .setLngLat([lng, lat])
                .setPopup(new maplibregl.Popup({ offset: 16 }).setHTML(popupHtml(properties)))
                .addTo(map);

            marker.getElement().setAttribute('aria-label', String(markerLabel));
            bounds.extend([lng, lat]);
            rendered += 1;
        });

        if (rendered > 1) {
            map.fitBounds(bounds, { padding: 48, maxZoom: 9, duration: 700 });
        }

        return rendered;
    };

    const loadPoints = async () => {
        try {
            const response = await fetch('/map/points', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            const features = Array.isArray(payload?.features) ? payload.features : [];
            const count = addMarkersFromFeatures(features);

            if (note) {
                note.textContent = count > 0
                    ? `Étape 2 active: ${count} point(s) chargé(s) depuis /map/points.`
                    : 'Étape 2 active: aucun point public pour le moment.';
            }
        } catch (error) {
            console.error('Impossible de charger les points de carte', error);
            if (note) {
                note.textContent = 'Erreur de chargement des points. Réessaie dans un instant.';
            }
        }
    };

    loadPoints();
})();
</script>
</body>
</html>
