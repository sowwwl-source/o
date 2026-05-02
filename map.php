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
    <meta name="description" content="Map — surface torique vivante de <?= h(SITE_TITLE) ?>, terres et courants d’activité.">
    <meta name="theme-color" content="#09090b">
    <title>Map — <?= h(SITE_TITLE) ?></title>
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
            background:
                radial-gradient(circle at 50% 50%, rgba(158, 220, 193, 0.12), transparent 34%),
                radial-gradient(circle at 50% 50%, rgba(158, 220, 193, 0.06), transparent 58%),
                linear-gradient(180deg, rgba(6, 10, 14, 0.98), rgba(8, 14, 18, 0.98));
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

        .map-popup .map-popup-tone {
            display: inline-flex;
            margin: 0.35rem 0 0.45rem;
            padding: 0.14rem 0.45rem;
            border-radius: 999px;
            background: rgba(11, 107, 74, 0.12);
            color: #0b6b4a;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="experience map-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="map-shell reveal on">
    <header class="map-head">
        <div>
            <p class="eyebrow"><strong>map</strong> <span>tore vivant / activité</span></p>
            <h1 class="land-title"><strong>Le tore des terres actives</strong> <span>nœuds et courants chauds</span></h1>
        </div>
        <div class="meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="/str3m">str3m</a>
            <span class="meta-pill">surface vivante</span>
        </div>
    </header>

    <div id="sowwwl-map" aria-label="Carte Sowwwl"></div>
    <p class="map-note" id="map-note">Chargement du tore vivant…</p>
</main>

<script>
(() => {
    const map = new maplibregl.Map({
        container: 'sowwwl-map',
        style: {
            version: 8,
            sources: {},
            layers: [
                {
                    id: 'void',
                    type: 'background',
                    paint: {
                        'background-color': '#05080b'
                    }
                }
            ]
        },
        center: [0, 0],
        zoom: 1.6,
        minZoom: 1.15,
        maxZoom: 6.4,
        attributionControl: false
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
        const activityLabel = escapeHtml(properties.activity_label || 'latente');
        const heat = Math.round(Number(properties.activity_heat || 0) * 100);
        const href = properties.land_url || '/land';

        return `
            <div class="map-popup">
                <strong>${name}</strong>
                <span>@${slug}</span><br>
                <span class="map-popup-tone">${activityLabel}</span><br>
                <span>Fuseau: ${timezone}</span><br>
                <span>Signaux publics: ${signalCount}</span><br>
                <span>Chaleur: ${heat}%</span><br>
                <a href="${escapeHtml(href)}">ouvrir la terre</a>
            </div>
        `;
    };

    const currentPopupHtml = (properties = {}) => {
        const from = escapeHtml(properties.from_username || properties.from_slug || 'origine');
        const to = escapeHtml(properties.to_username || properties.to_slug || 'destination');
        const count = Number(properties.passage_count || 0);
        const activityLabel = escapeHtml(properties.activity_label || 'en circulation');

        return `
            <div class="map-popup">
                <strong>Courant chaud</strong>
                <span>${from} → ${to}</span><br>
                <span class="map-popup-tone">${activityLabel}</span><br>
                <span>Passages observés: ${count}</span>
            </div>
        `;
    };

    const fitToLandBounds = (features) => {
        const bounds = new maplibregl.LngLatBounds();
        let rendered = 0;

        features.forEach((feature) => {
            if (feature?.geometry?.type !== 'Point' || feature?.properties?.kind !== 'land') {
                return;
            }

            const geometry = feature?.geometry || {};
            const coordinates = Array.isArray(geometry.coordinates) ? geometry.coordinates : [];
            const lng = Number(coordinates[0]);
            const lat = Number(coordinates[1]);

            if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
                return;
            }

            bounds.extend([lng, lat]);
            rendered += 1;
        });

        if (rendered > 1) {
            map.fitBounds(bounds, { padding: 56, maxZoom: 4.2, duration: 700 });
        }

        return rendered;
    };

    const ensureLayers = () => {
        if (!map.getSource('sowwwl-surface')) {
            map.addSource('sowwwl-surface', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: []
                }
            });
        }

        if (!map.getLayer('currents-glow')) {
            map.addLayer({
                id: 'currents-glow',
                type: 'line',
                source: 'sowwwl-surface',
                filter: ['==', ['get', 'kind'], 'current'],
                paint: {
                    'line-color': '#8ce0b5',
                    'line-opacity': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 0.08, 1, 0.5],
                    'line-width': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 3, 1, 14],
                    'line-blur': 8
                }
            });
        }

        if (!map.getLayer('currents-line')) {
            map.addLayer({
                id: 'currents-line',
                type: 'line',
                source: 'sowwwl-surface',
                filter: ['==', ['get', 'kind'], 'current'],
                paint: {
                    'line-color': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, '#3d7b69', 0.55, '#72d6a7', 1, '#d9fff0'],
                    'line-opacity': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 0.3, 1, 0.9],
                    'line-width': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 1.2, 1, 4.6]
                }
            });
        }

        if (!map.getLayer('lands-glow')) {
            map.addLayer({
                id: 'lands-glow',
                type: 'circle',
                source: 'sowwwl-surface',
                filter: ['==', ['get', 'kind'], 'land'],
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 10, 1, 28],
                    'circle-color': '#a6f1cc',
                    'circle-opacity': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 0.1, 1, 0.26],
                    'circle-blur': 0.9
                }
            });
        }

        if (!map.getLayer('lands-circle')) {
            map.addLayer({
                id: 'lands-circle',
                type: 'circle',
                source: 'sowwwl-surface',
                filter: ['==', ['get', 'kind'], 'land'],
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, 4.5, 1, 11],
                    'circle-color': ['interpolate', ['linear'], ['get', 'activity_heat'], 0.18, '#5f7d71', 0.55, '#7de0ac', 1, '#f2fff9'],
                    'circle-stroke-width': 1,
                    'circle-stroke-color': 'rgba(255,255,255,0.35)'
                }
            });
        }
    };

    const attachInteractions = () => {
        map.on('mouseenter', 'lands-circle', () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', 'lands-circle', () => {
            map.getCanvas().style.cursor = '';
        });

        map.on('mouseenter', 'currents-line', () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', 'currents-line', () => {
            map.getCanvas().style.cursor = '';
        });

        map.on('click', 'lands-circle', (event) => {
            const feature = event.features?.[0];
            if (!feature) {
                return;
            }

            new maplibregl.Popup({ offset: 14 })
                .setLngLat(event.lngLat)
                .setHTML(popupHtml(feature.properties || {}))
                .addTo(map);
        });

        map.on('click', 'currents-line', (event) => {
            const feature = event.features?.[0];
            if (!feature) {
                return;
            }

            new maplibregl.Popup({ offset: 14 })
                .setLngLat(event.lngLat)
                .setHTML(currentPopupHtml(feature.properties || {}))
                .addTo(map);
        });
    };

    let interactionsAttached = false;

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
            const landCount = features.filter((feature) => feature?.properties?.kind === 'land').length;
            const currentCount = features.filter((feature) => feature?.properties?.kind === 'current').length;

            ensureLayers();

            const source = map.getSource('sowwwl-surface');
            if (source) {
                source.setData({
                    type: 'FeatureCollection',
                    features
                });
            }

            if (!interactionsAttached) {
                attachInteractions();
                interactionsAttached = true;
            }

            fitToLandBounds(features);

            if (note) {
                note.textContent = landCount > 0
                    ? `Tore actif: ${landCount} terre(s) et ${currentCount} courant(s) chauds chargés depuis /map/points.`
                    : 'Tore actif: aucune terre publique n’alimente encore la surface.';
            }
        } catch (error) {
            console.error('Impossible de charger les points de carte', error);
            if (note) {
                note.textContent = 'Erreur de chargement du tore vivant. Réessaie dans un instant.';
            }
        }
    };

    map.on('load', loadPoints);
})();
</script>
</body>
</html>
