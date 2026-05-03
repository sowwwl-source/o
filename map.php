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

        .map-fallback {
            min-height: 72vh;
            display: grid;
            gap: 1rem;
            align-content: start;
            padding: 0.9rem;
            border: 1px solid rgba(158, 220, 193, 0.1);
            border-radius: 18px;
            overflow: hidden;
            box-shadow:
                0 1.5rem 4rem rgba(0, 0, 0, 0.28),
                inset 0 0 4rem rgba(159, 226, 195, 0.025);
            background:
                radial-gradient(circle at 36% 38%, rgba(220, 255, 244, 0.11), transparent 28%),
                radial-gradient(circle at 66% 58%, rgba(120, 205, 185, 0.08), transparent 36%),
                linear-gradient(180deg, rgba(5, 8, 11, 0.985), rgba(7, 12, 15, 0.99));
        }

        .map-fallback__frame {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(158, 220, 193, 0.11);
            border-radius: 18px;
            background:
                radial-gradient(circle at 52% 48%, rgba(227, 255, 245, 0.11), transparent 24%),
                radial-gradient(circle at 50% 54%, rgba(158, 220, 193, 0.05), transparent 56%),
                linear-gradient(180deg, rgba(3, 6, 8, 0.96), rgba(5, 9, 12, 0.985));
        }

        .map-fallback__frame::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 50% 50%, transparent 0 23%, rgba(255, 255, 255, 0.018) 23.4% 23.8%, transparent 24.2%),
                radial-gradient(circle at 50% 50%, transparent 0 44%, rgba(255, 255, 255, 0.014) 44.3% 44.7%, transparent 45.2%),
                linear-gradient(90deg, transparent 0%, rgba(159, 226, 195, 0.018) 48%, transparent 100%);
            mix-blend-mode: plus-lighter;
            pointer-events: none;
        }

        .map-fallback__svg {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 16 / 9;
            filter: saturate(1.03) contrast(1.04);
        }

        .map-fallback__svg .map-line-ghost {
            opacity: 0.18;
            mix-blend-mode: screen;
        }

        .map-fallback__svg .map-current-field {
            mix-blend-mode: screen;
            opacity: 0.84;
        }

        .map-fallback__svg .map-density-field {
            mix-blend-mode: plus-lighter;
        }

        .map-fallback__svg .map-particle {
            animation: mapParticlePulse 8.4s ease-in-out infinite;
            transform-origin: center;
        }

        .map-fallback__svg .map-particle--slow {
            animation-duration: 9.2s;
        }

        .map-fallback__svg .map-particle--fast {
            animation-duration: 4.8s;
        }

        .map-fallback__svg .map-current-particle {
            animation: mapCurrentDrift 8.8s ease-in-out infinite;
            transform-origin: center;
        }

        .map-fallback__svg .map-core-node {
            transition: transform 220ms ease, filter 220ms ease, opacity 220ms ease;
        }

        .map-fallback__svg a:hover .map-core-node,
        .map-fallback__svg a:focus-visible .map-core-node {
            transform: scale(1.08);
            filter: drop-shadow(0 0 0.95rem rgba(208, 255, 238, 0.54));
            opacity: 1;
        }

        .map-fallback__legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem 1rem;
            align-items: center;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(228, 247, 239, 0.74);
        }

        .map-fallback__legend strong {
            color: #e8fff4;
        }

        .map-fallback__dot,
        .map-fallback__line {
            display: inline-block;
            flex: 0 0 auto;
            vertical-align: middle;
        }

        .map-fallback__dot {
            width: 0.72rem;
            height: 0.72rem;
            border-radius: 50%;
            background: radial-gradient(circle, #f1fff8, #9fe2c3 58%, rgba(159, 226, 195, 0.18));
            box-shadow: 0 0 1.2rem rgba(159, 226, 195, 0.34);
        }

        .map-fallback__line {
            width: 1.4rem;
            height: 2px;
            border-radius: 999px;
            background: linear-gradient(90deg, rgba(126, 224, 172, 0.02), rgba(217, 255, 240, 0.56), rgba(126, 224, 172, 0.02));
            box-shadow: 0 0 0.9rem rgba(126, 224, 172, 0.18);
        }

        .map-fallback__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .map-fallback__card {
            padding: 0.8rem 0.9rem;
            border: 1px solid rgba(158, 220, 193, 0.14);
            border-radius: 12px;
            background: rgba(8, 14, 18, 0.76);
        }

        .map-fallback__card strong {
            display: block;
            margin-bottom: 0.2rem;
            color: #f2fff9;
        }

        .map-fallback__card p {
            margin: 0;
            font-size: 0.84rem;
            line-height: 1.45;
            color: rgba(228, 247, 239, 0.76);
        }

        .map-fallback__lists {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .map-fallback__list {
            padding: 0.8rem 0.9rem;
            border: 1px solid rgba(158, 220, 193, 0.14);
            border-radius: 12px;
            background: rgba(8, 14, 18, 0.76);
        }

        .map-fallback__list h2 {
            margin: 0 0 0.75rem;
            font-size: 0.86rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .map-fallback__items {
            display: grid;
            gap: 0.55rem;
        }

        .map-fallback__item {
            display: grid;
            gap: 0.16rem;
            padding-top: 0.55rem;
            border-top: 1px solid rgba(158, 220, 193, 0.1);
        }

        .map-fallback__item:first-child {
            padding-top: 0;
            border-top: none;
        }

        .map-fallback__item a {
            color: #f2fff9;
            text-decoration: none;
        }

        .map-fallback__item a:hover,
        .map-fallback__item a:focus-visible {
            text-decoration: underline;
        }

        .map-fallback__item strong {
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .map-fallback__item p {
            margin: 0;
            color: rgba(228, 247, 239, 0.74);
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .map-fallback__empty {
            margin: 0;
            font-size: 0.92rem;
            color: rgba(228, 247, 239, 0.72);
        }

        .map-note {
            margin: 0;
            opacity: 0.72;
            font-size: 0.86rem;
        }

        @keyframes mapParticlePulse {
            0%,
            100% {
                opacity: 0.34;
                transform: scale(0.9);
            }
            50% {
                opacity: 0.88;
                transform: scale(1.14);
            }
        }

        @keyframes mapCurrentDrift {
            0% {
                opacity: 0.16;
                transform: scale(0.92);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.08);
            }
            100% {
                opacity: 0.16;
                transform: scale(0.92);
            }
        }

        @media (max-width: 780px) {
            .map-fallback__grid,
            .map-fallback__lists {
                grid-template-columns: 1fr;
            }
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

    <div id="sowwwl-map-surface" class="map-fallback" aria-live="polite"></div>
    <p class="map-note" id="map-note">Chargement du tore vivant…</p>
</main>

<script>
(() => {
    const pointsUrl = '/map/points';
    const surfaceRoot = document.getElementById('sowwwl-map-surface');
    const note = document.getElementById('map-note');

    const clamp = (value, minimum, maximum) => Math.max(minimum, Math.min(maximum, value));

    const hashSeed = (value) => {
        const input = String(value || 'o-map');
        let hash = 2166136261;
        for (let index = 0; index < input.length; index += 1) {
            hash ^= input.charCodeAt(index);
            hash = Math.imul(hash, 16777619);
        }
        return hash >>> 0;
    };

    const makeRng = (seed) => {
        let state = hashSeed(seed) || 1;
        return () => {
            state = (Math.imul(state, 1664525) + 1013904223) >>> 0;
            return state / 4294967295;
        };
    };

    const lerp = (left, right, factor) => left + ((right - left) * factor);

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatPercent = (value) => `${Math.round(Number(value || 0) * 100)}%`;

    const fetchPoints = async () => {
        const response = await fetch(pointsUrl, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    };

    const projectPoint = (lng, lat, width, height) => {
        const safeLng = Number.isFinite(Number(lng)) ? Number(lng) : 0;
        const safeLat = Number.isFinite(Number(lat)) ? Number(lat) : 0;
        const x = ((safeLng + 180) / 360) * width;
        const y = ((90 - safeLat) / 180) * height;
        return [Math.max(0, Math.min(width, x)), Math.max(0, Math.min(height, y))];
    };

    const buildTorusDust = (seed, width, height, count = 540) => {
        const rng = makeRng(`torus-dust|${seed}`);
        const centerX = width / 2;
        const centerY = height / 2;
        const particles = [];

        for (let index = 0; index < count; index += 1) {
            const theta = rng() * Math.PI * 2;
            const phi = rng() * Math.PI * 2;
            const majorRadius = 242 + ((rng() - 0.5) * 44);
            const minorRadius = 72 + (rng() * 48);
            const x = centerX + Math.cos(theta) * (majorRadius + (Math.cos(phi) * minorRadius * 0.46));
            const y = centerY + (Math.sin(theta) * 126) + (Math.sin(phi) * (36 + rng() * 20));
            const size = 0.34 + (rng() * 1.18);
            const opacity = 0.025 + (rng() * 0.18);
            const hueShift = Math.round(180 + (rng() * 35));
            const speedClass = index % 5 === 0 ? 'map-particle--fast' : (index % 3 === 0 ? 'map-particle--slow' : '');
            particles.push(`<circle class="map-particle ${speedClass}" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${size.toFixed(2)}" fill="hsla(${hueShift}, 72%, 84%, ${opacity.toFixed(3)})" />`);
        }

        return particles.join('');
    };

    const densityAnchorsForKind = (kind) => {
        switch (kind) {
            case 'person':
                return [
                    [0, -0.96, 0.18],
                    [0, -0.58, 0.22],
                    [-0.34, -0.26, 0.2],
                    [0.34, -0.26, 0.2],
                    [0, 0.06, 0.26],
                    [-0.18, 0.58, 0.18],
                    [0.18, 0.58, 0.18],
                    [-0.62, -0.06, 0.14],
                    [0.62, -0.06, 0.14],
                ];
            case 'place':
                return [
                    [0, -0.94, 0.12],
                    [-0.62, -0.34, 0.15],
                    [0.62, -0.34, 0.15],
                    [-0.52, 0.2, 0.22],
                    [0.52, 0.2, 0.22],
                    [0, 0.44, 0.24],
                    [0, 0.02, 0.16],
                    [0, 0.72, 0.18],
                ];
            default:
                return [
                    [-0.72, -0.08, 0.14],
                    [-0.4, -0.42, 0.16],
                    [0, -0.52, 0.18],
                    [0.42, -0.26, 0.16],
                    [0.7, 0.04, 0.14],
                    [0.42, 0.34, 0.16],
                    [0, 0.5, 0.18],
                    [-0.42, 0.34, 0.16],
                    [0, 0.02, 0.22],
                ];
        }
    };

    const buildDensityFigure = (kind, centerX, centerY, heat, seed) => {
        const rng = makeRng(`density-figure|${kind}|${seed}`);
        const anchors = densityAnchorsForKind(kind);
        const count = Math.round(56 + (heat * 120));
        const scaleX = 18 + (heat * 28);
        const scaleY = 24 + (heat * 34);
        const particles = [];

        for (let index = 0; index < count; index += 1) {
            const anchor = anchors[Math.floor(rng() * anchors.length)] || anchors[0];
            const spread = anchor[2] || 0.18;
            const jitterX = (rng() - 0.5) * scaleX * spread * 2.4;
            const jitterY = (rng() - 0.5) * scaleY * spread * 2.4;
            const x = centerX + (anchor[0] * scaleX) + jitterX;
            const y = centerY + (anchor[1] * scaleY) + jitterY;
            const size = 0.55 + (rng() * 1.45) + (heat * 0.72);
            const opacity = 0.12 + (rng() * 0.34) + (heat * 0.16);
            const color = kind === 'person'
                ? `rgba(255, 245, 214, ${opacity.toFixed(3)})`
                : (kind === 'place'
                    ? `rgba(159, 226, 195, ${opacity.toFixed(3)})`
                    : `rgba(194, 232, 255, ${opacity.toFixed(3)})`);
            const speedClass = index % 4 === 0 ? 'map-particle--fast' : '';
            particles.push(`<circle class="map-particle ${speedClass}" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${size.toFixed(2)}" fill="${color}" />`);
        }

        return particles.join('');
    };

    const buildLandParticleCloud = (lands, width, height) => {
        const cloud = [];
        const figures = [];
        const kinds = ['person', 'place', 'object'];

        lands.forEach((feature, index) => {
            const properties = feature?.properties || {};
            const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
            const [x, y] = projectPoint(coords[0], coords[1], width, height);
            const heat = clamp(Number(properties.activity_heat || 0.18), 0.18, 1);
            const rng = makeRng(`land-cloud|${properties.slug || index}`);
            const count = Math.round(96 + (heat * 260));
            const radiusX = 16 + (heat * 48);
            const radiusY = 11 + (heat * 34);

            for (let particleIndex = 0; particleIndex < count; particleIndex += 1) {
                const angle = rng() * Math.PI * 2;
                const radius = Math.pow(rng(), 1.85);
                const orbit = 1 + (Math.sin(angle * 3 + rng() * 2) * 0.08);
                const driftX = Math.cos(angle) * radiusX * radius * orbit;
                const driftY = Math.sin(angle) * radiusY * radius;
                const px = x + driftX;
                const py = y + driftY;
                const coreBias = 1 - radius;
                const size = 0.28 + (rng() * 1.25) + (coreBias * heat * 1.45);
                const opacity = 0.045 + (rng() * 0.22) + (coreBias * heat * 0.4);
                const speedClass = particleIndex % 6 === 0 ? 'map-particle--slow' : '';
                cloud.push(`<circle class="map-particle ${speedClass}" cx="${px.toFixed(2)}" cy="${py.toFixed(2)}" r="${size.toFixed(2)}" fill="rgba(191, 255, 228, ${opacity.toFixed(3)})" />`);
            }

            const kind = kinds[hashSeed(properties.slug || String(index)) % kinds.length] || 'object';
            figures.push(buildDensityFigure(kind, x, y - (10 + heat * 18), heat, properties.slug || index));
        });

        return {
            cloud: cloud.join(''),
            figures: figures.join(''),
        };
    };

    const buildCurrentParticleCloud = (currents, width, height) => {
        const particles = [];
        const veils = [];

        currents.forEach((feature, currentIndex) => {
            const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
            const projected = coords
                .filter((point) => Array.isArray(point) && point.length >= 2)
                .map((point) => projectPoint(point[0], point[1], width, height));

            if (projected.length < 2) {
                return;
            }

            const heat = clamp(Number(feature?.properties?.activity_heat || 0.18), 0.18, 1);
            const rng = makeRng(`current-cloud|${feature?.properties?.from_slug || currentIndex}|${feature?.properties?.to_slug || currentIndex}`);
            const count = Math.round(88 + (heat * 230));

            for (let particleIndex = 0; particleIndex < count; particleIndex += 1) {
                const segmentIndex = Math.min(projected.length - 2, Math.floor(rng() * (projected.length - 1)));
                const start = projected[segmentIndex];
                const end = projected[segmentIndex + 1];
                const factor = rng();
                const baseX = lerp(start[0], end[0], factor);
                const baseY = lerp(start[1], end[1], factor);
                const dx = end[0] - start[0];
                const dy = end[1] - start[1];
                const length = Math.max(1, Math.hypot(dx, dy));
                const normalX = -dy / length;
                const normalY = dx / length;
                const centerPull = Math.pow(rng(), 2.35);
                const spread = (rng() - 0.5) * (10 + heat * 34) * centerPull;
                const px = baseX + (normalX * spread);
                const py = baseY + (normalY * spread);
                const size = 0.22 + (rng() * 1.2) + ((1 - centerPull) * heat * 0.9);
                const opacity = 0.035 + (rng() * 0.22) + ((1 - centerPull) * heat * 0.28);
                particles.push(`<circle class="map-current-particle" cx="${px.toFixed(2)}" cy="${py.toFixed(2)}" r="${size.toFixed(2)}" fill="rgba(217, 255, 240, ${opacity.toFixed(3)})" />`);
            }

            projected.forEach((point, pointIndex) => {
                if (pointIndex % 2 !== 0) {
                    return;
                }

                const veilRadius = (14 + heat * 32 + rng() * 18).toFixed(2);
                const veilOpacity = (0.018 + heat * 0.055).toFixed(3);
                veils.push(`<circle cx="${point[0].toFixed(2)}" cy="${point[1].toFixed(2)}" r="${veilRadius}" fill="rgba(217,255,240,${veilOpacity})" />`);
            });
        });

        return {
            particles: particles.join(''),
            veils: veils.join(''),
        };
    };

    const renderSurface = (payload) => {
        if (!(surfaceRoot instanceof HTMLElement)) {
            return;
        }

        const features = Array.isArray(payload?.features) ? payload.features : [];
        const lands = features.filter((feature) => feature?.properties?.kind === 'land');
        const currents = features.filter((feature) => feature?.properties?.kind === 'current');
        const svgWidth = 960;
        const svgHeight = 540;
        const dust = buildTorusDust(`${lands.length}|${currents.length}`, svgWidth, svgHeight);
        const landParticles = buildLandParticleCloud(lands, svgWidth, svgHeight);
        const currentParticles = buildCurrentParticleCloud(currents, svgWidth, svgHeight);

        const currentPaths = currents.map((feature) => {
            const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
            if (!coords.length) {
                return '';
            }

            const [firstLng, firstLat] = Array.isArray(coords[0]) ? coords[0] : [0, 0];
            const [startX, startY] = projectPoint(firstLng, firstLat, svgWidth, svgHeight);
            const segments = coords.slice(1).map((point) => {
                const [lng, lat] = Array.isArray(point) ? point : [0, 0];
                const [x, y] = projectPoint(lng, lat, svgWidth, svgHeight);
                return `L ${x.toFixed(2)} ${y.toFixed(2)}`;
            }).join(' ');
            const heat = Math.max(0.18, Math.min(1, Number(feature?.properties?.activity_heat || 0.18)));
            const opacity = (0.025 + heat * 0.08).toFixed(3);
            const strokeWidth = (0.5 + heat * 1.35).toFixed(2);
            return `<path class="map-line-ghost" d="M ${startX.toFixed(2)} ${startY.toFixed(2)} ${segments}" fill="none" stroke="rgba(217,255,240,${opacity})" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-linejoin="round" />`;
        }).join('');

        const landDots = lands.map((feature) => {
            const coords = Array.isArray(feature?.geometry?.coordinates) ? feature.geometry.coordinates : [];
            const [lng, lat] = coords;
            const [x, y] = projectPoint(lng, lat, svgWidth, svgHeight);
            const heat = Math.max(0.18, Math.min(1, Number(feature?.properties?.activity_heat || 0.18)));
            const radius = (2.2 + heat * 4.2).toFixed(2);
            const glow = (18 + heat * 42).toFixed(2);
            const slug = escapeHtml(feature?.properties?.slug || 'terre');
            const username = escapeHtml(feature?.properties?.username || slug);
            return `
                <g>
                    <circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${glow}" fill="rgba(159,226,195,${(0.028 + heat * 0.055).toFixed(3)})" />
                    <a href="${escapeHtml(feature?.properties?.land_url || '/land')}" aria-label="ouvrir la terre ${username}">
                        <circle class="map-core-node" cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${radius}" fill="rgba(236,255,248,0.74)" stroke="rgba(255,255,255,0.2)" stroke-width="0.8" />
                    </a>
                    <title>${username} · @${slug}</title>
                </g>
            `;
        }).join('');

        const topLands = lands
            .slice()
            .sort((left, right) => Number(right?.properties?.activity_heat || 0) - Number(left?.properties?.activity_heat || 0))
            .slice(0, 6)
            .map((feature) => {
                const properties = feature?.properties || {};
                return `
                    <article class="map-fallback__item">
                        <a href="${escapeHtml(properties.land_url || '/land')}"><strong>${escapeHtml(properties.username || properties.slug || 'Terre')} · @${escapeHtml(properties.slug || 'inconnue')}</strong></a>
                        <p>${escapeHtml(properties.activity_label || 'latente')} · chaleur ${formatPercent(properties.activity_heat)} · ${Number(properties.signal_public_count || 0)} signal(s) public(s)</p>
                        <p>Fuseau · ${escapeHtml(properties.timezone || 'n/a')}</p>
                    </article>
                `;
            }).join('');

        const hotCurrents = currents
            .slice()
            .sort((left, right) => Number(right?.properties?.activity_heat || 0) - Number(left?.properties?.activity_heat || 0))
            .slice(0, 6)
            .map((feature) => {
                const properties = feature?.properties || {};
                return `
                    <article class="map-fallback__item">
                        <strong>${escapeHtml(properties.from_username || properties.from_slug || 'origine')} → ${escapeHtml(properties.to_username || properties.to_slug || 'destination')}</strong>
                        <p>${escapeHtml(properties.activity_label || 'en circulation')} · chaleur ${formatPercent(properties.activity_heat)}</p>
                        <p>${Number(properties.passage_count || 0)} passage(s) observé(s)</p>
                    </article>
                `;
            }).join('');

        surfaceRoot.innerHTML = lands.length > 0
            ? `
                <div class="map-fallback__legend">
                    <span><span class="map-fallback__dot"></span> <strong>${lands.length}</strong> terre(s)</span>
                    <span><span class="map-fallback__line"></span> <strong>${currents.length}</strong> courant(s)</span>
                    <span>rendu local autonome</span>
                </div>
                <div class="map-fallback__frame">
                    <svg class="map-fallback__svg" viewBox="0 0 ${svgWidth} ${svgHeight}" role="img" aria-label="Vue torique simplifiée des terres actives">
                        <defs>
                            <radialGradient id="torusCore" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(159,226,195,0.18)" />
                                <stop offset="55%" stop-color="rgba(159,226,195,0.05)" />
                                <stop offset="100%" stop-color="rgba(159,226,195,0)" />
                            </radialGradient>
                            <radialGradient id="torusDenseGlow" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="rgba(220,255,244,0.22)" />
                                <stop offset="100%" stop-color="rgba(220,255,244,0)" />
                            </radialGradient>
                        </defs>
                        <rect width="${svgWidth}" height="${svgHeight}" fill="rgba(4,7,9,0.88)" />
                        <rect width="${svgWidth}" height="${svgHeight}" fill="url(#torusDenseGlow)" opacity="0.65" />
                        <ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="300" ry="124" fill="none" stroke="rgba(217,255,240,0.045)" stroke-width="1" />
                        <ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="188" ry="68" fill="none" stroke="rgba(217,255,240,0.028)" stroke-width="0.8" />
                        <ellipse cx="${svgWidth / 2}" cy="${svgHeight / 2}" rx="156" ry="54" fill="url(#torusCore)" />
                        ${dust}
                        <g class="map-current-field">
                            ${currentParticles.veils}
                            ${currentPaths}
                            ${currentParticles.particles}
                        </g>
                        <g class="map-density-field">
                            ${landParticles.figures}
                            ${landParticles.cloud}
                        </g>
                        ${landDots}
                    </svg>
                </div>
                <div class="map-fallback__lists">
                    <section class="map-fallback__list" aria-labelledby="map-top-lands-title">
                        <h2 id="map-top-lands-title">Terres chaudes</h2>
                        <div class="map-fallback__items">${topLands || '<p class="map-fallback__empty">Aucune terre publique visible.</p>'}</div>
                    </section>
                    <section class="map-fallback__list" aria-labelledby="map-top-currents-title">
                        <h2 id="map-top-currents-title">Courants chauds</h2>
                        <div class="map-fallback__items">${hotCurrents || '<p class="map-fallback__empty">Aucun courant observé pour l’instant.</p>'}</div>
                    </section>
                </div>
            `
            : `<p class="map-fallback__empty">Aucune terre publique n’alimente encore la surface.</p>`;

        if (note) {
            note.textContent = lands.length > 0
                ? `Tore local dense : ${lands.length} terre(s), ${currents.length} courant(s), figures et présences dessinées par densité depuis ${pointsUrl}.`
                : 'Tore local actif, mais aucune terre publique n’alimente encore la surface.';
        }
    };

    const bootSurface = async () => {
        try {
            const payload = await fetchPoints();
            renderSurface(payload);
        } catch (error) {
            console.error('Impossible de charger la surface torique locale', error);
            if (surfaceRoot instanceof HTMLElement) {
                surfaceRoot.innerHTML = '<p class="map-fallback__empty">Le tore local n’a pas pu se déplier. Réessaie dans un instant.</p>';
            }
            if (note) {
                note.textContent = 'Erreur de chargement du tore vivant. Réessaie dans un instant.';
            }
        }
    };

    bootSurface();
})();
</script>
</body>
</html>
