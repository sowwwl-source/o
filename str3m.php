<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/str3m_media.php';
require_once __DIR__ . '/lib/str3m_daily.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/str3m');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';

$authenticatedLand = current_authenticated_land();
$guideHref = '/0wlslw0';
$str3mGuide = guide_path('str3m');

// 1. Chargement du courant quotidien (Str3m)
$dailyStream = str3m_build_daily_stream(null);
$dailyTextItem = is_array($dailyStream['items']['text'] ?? null) ? $dailyStream['items']['text'] : null;
$dailyImageItem = is_array($dailyStream['items']['image'] ?? null) ? $dailyStream['items']['image'] : null;
$dailyAudioItem = is_array($dailyStream['items']['audio'] ?? null) ? $dailyStream['items']['audio'] : null;
$dailyTextBody = $dailyTextItem ? str3m_load_text_body($dailyTextItem) : '';
$dailyTextExcerpt = trim((string) (($dailyTextItem['meta']['excerpt'] ?? '') ?: ''));
$dailyImagePath = $dailyImageItem ? str3m_resolve_media_path($dailyImageItem) : '';
$dailyAudioPath = $dailyAudioItem ? str3m_resolve_media_path($dailyAudioItem) : '';

// 2. Découverte de l'Archipel (Terres actives dans le flux)
$publicSignals = list_public_signals();
$activeIslands = [];
foreach ($publicSignals as $signal) {
    $slug = (string) ($signal['land_slug'] ?? '');
    if ($slug !== '' && !isset($activeIslands[$slug])) {
        $activeIslands[$slug] = [
            'username' => (string) ($signal['land_username'] ?? $slug),
            'slug' => $slug,
            'last_active' => (string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? '')),
        ];
    }
}

$ambientProfile = land_collective_profile((string) ($dailyStream['mood'] ?? 'calm'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Str3m — explorer le courant quotidien et les îles sur <?= h($brandDomain) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Str3m — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience str3m-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, (string) ($dailyStream['mood'] ?? 'calm'), 'str3m') ?>

<main class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>str3m</strong> <span>océan public</span></p>
        <h1 class="land-title signal-title">
            <strong>Le courant et les îles.</strong>
            <span>I inverse</span>
        </h1>
        <p class="lead">Ce qui affleure aujourd'hui, et les terres qui résonnent.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($authenticatedLand): ?>
                <span class="meta-pill">terre liée : <?= h((string) $authenticatedLand['slug']) ?></span>
            <?php endif; ?>
            <span class="meta-pill">humeur : <?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
        </div>
    </header>

    <section class="panel reveal meaning-panel" aria-labelledby="str3m-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-meaning-title">Pourquoi cette porte existe</h2>
                <p class="panel-copy"><?= h((string) ($str3mGuide['copy'] ?? 'Explorer le courant public sans forcer l’entrée.')) ?></p>
            </div>
            <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0 : me guider</a>
        </div>
    </section>

    <section class="panel reveal str3m-panel" aria-labelledby="str3m-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-title">Str3m quotidien</h2>
                <p class="panel-copy">Une présence choisie pour aujourd’hui.</p>
            </div>
            <span class="badge"><?= h((string) ($dailyStream['template'] ?? 'empty')) ?></span>
        </div>

        <div class="str3m-daily-grid">
            <section class="str3m-card str3m-card-text">
                <p class="summary-label">Texte d'ancrage</p>
                <h3><?= $dailyTextItem ? h((string) $dailyTextItem['title']) : 'La surface est vierge' ?></h3>
                <?php if ($dailyTextBody !== ''): ?>
                    <div class="str3m-text-body">
                        <p><?= nl2br(h($dailyTextBody)) ?></p>
                    </div>
                <?php elseif ($dailyTextExcerpt !== ''): ?>
                    <p class="str3m-fallback-copy"><?= h($dailyTextExcerpt) ?></p>
                <?php else: ?>
                    <p class="str3m-fallback-copy">Le str3m attend sa première trace.</p>
                <?php endif; ?>
            </section>

            <section class="str3m-card str3m-card-visual">
                <p class="summary-label">Surface</p>
                <h3><?= $dailyImageItem ? h((string) $dailyImageItem['title']) : 'Surface en suspens' ?></h3>
                <?php if ($dailyImagePath !== ''): ?>
                    <figure class="str3m-figure">
                        <img src="<?= h($dailyImagePath) ?>" alt="<?= h((string) ($dailyImageItem['meta']['alt'] ?? $dailyImageItem['title'] ?? 'Image str3m')) ?>" class="str3m-image" loading="lazy">
                    </figure>
                <?php else: ?>
                    <p class="str3m-fallback-copy">Le str3m visuel attend sa trace.</p>
                <?php endif; ?>

                <?php if ($dailyAudioPath !== ''): ?>
                    <div class="str3m-audio-shell">
                        <p class="summary-label">Nappe</p>
                        <audio controls preload="none" class="str3m-audio">
                            <source src="<?= h($dailyAudioPath) ?>">
                        </audio>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <section class="panel reveal" aria-labelledby="islands-title">
        <div class="section-topline">
            <div>
                <h2 id="islands-title">Archipel</h2>
                <p class="panel-copy">Les terres avec des signaux publics récents.</p>
            </div>
            <span class="badge"><?= count($activeIslands) ?> île<?= count($activeIslands) > 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($activeIslands)): ?>
            <p class="panel-copy">Aucune île n'a émis de signal pour le moment.</p>
        <?php else: ?>
            <?php
            // Calcule des positions 3D en spirale pour l'archipel
            $islandNodes = [];
            $radius = 120;
            
            // Trouver l'île la plus récente
            $newestIslandSlug = '';
            $maxTimestamp = '';
            foreach ($activeIslands as $island) {
                if ($island['last_active'] > $maxTimestamp) {
                    $maxTimestamp = $island['last_active'];
                    $newestIslandSlug = $island['slug'];
                }
            }

            foreach (array_values($activeIslands) as $index => $island) {
                $r = $radius + ($index * 140);
                $a = $index * 2.39996; // Angle d'or pour distribution organique
                $x = (int) (cos($a) * $r);
                $z = (int) (sin($a) * $r);
                $y = rand(-120, 120);
                $islandNodes[] = [
                    'island' => $island,
                    'x' => $x,
                    'y' => $y,
                    'z' => $z
                ];
            }
            ?>
            <div id="archipelago-3d" class="archipelago-3d-container">
                <div class="archipelago-instructions">Glisser pour pivoter · Molette pour avancer</div>
                <div class="archipelago-scene">
                    <?php foreach ($islandNodes as $node): ?>
                        <?php $island = $node['island']; ?>
                        <div class="archipelago-node" style="transform: translate3d(<?= $node['x'] ?>px, <?= $node['y'] ?>px, <?= $node['z'] ?>px);">
                            <div class="archipelago-card-wrapper">
                                <article class="str3m-island-card <?= $island['slug'] === $newestIslandSlug ? 'is-glowing' : '' ?>">
                                    <div>
                                        <span class="summary-label">Terre</span>
                                        <strong class="summary-value"><?= h($island['username']) ?></strong>
                                    </div>
                                    <?php if ($island['last_active']): ?>
                                        <p class="island-meta">Dernière trace : <?= h(human_created_label($island['last_active']) ?? 'récemment') ?></p>
                                    <?php endif; ?>
                                    <a class="pill-link" href="/land.php?u=<?= rawurlencode($island['slug']) ?>">Explorer l'île</a>
                                </article>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const container = document.getElementById('archipelago-3d');
                    if (!container) return;

                    const scene = container.querySelector('.archipelago-scene');
                    const nodes = scene.querySelectorAll('.archipelago-card-wrapper');
                    let rotX = 5, rotY = 0, posZ = -500;
                    let targetRotX = 5, targetRotY = 0, targetPosZ = -500;
                    let isDragging = false;
                    let lastX = 0, lastY = 0;

                    const clamp = (val, min, max) => Math.max(min, Math.min(max, val));

                    const onDragStart = (clientX, clientY, target) => {
                        if (target.closest('a')) return; // Laisse les liens cliquables
                        isDragging = true;
                        lastX = clientX;
                        lastY = clientY;
                        container.style.cursor = 'grabbing';
                    };

                    const onDragMove = (clientX, clientY) => {
                        if (!isDragging) return;
                        targetRotY += (clientX - lastX) * 0.25;
                        targetRotX -= (clientY - lastY) * 0.25;
                        targetRotX = clamp(targetRotX, -25, 25);
                        lastX = clientX;
                        lastY = clientY;
                    };

                    const onDragEnd = () => {
                        isDragging = false;
                        container.style.cursor = 'grab';
                    };

                    // Souris
                    container.addEventListener('mousedown', (e) => onDragStart(e.clientX, e.clientY, e.target));
                    window.addEventListener('mousemove', (e) => onDragMove(e.clientX, e.clientY));
                    window.addEventListener('mouseup', onDragEnd);

                    // Tactile
                    container.addEventListener('touchstart', (e) => onDragStart(e.touches[0].clientX, e.touches[0].clientY, e.target), {passive: true});
                    window.addEventListener('touchmove', (e) => onDragMove(e.touches[0].clientX, e.touches[0].clientY), {passive: true});
                    window.addEventListener('touchend', onDragEnd);

                    // Molette pour avancer/reculer dans la 3D
                    container.addEventListener('wheel', (e) => {
                        e.preventDefault();
                        targetPosZ += e.deltaY * 1.5;
                        targetPosZ = clamp(targetPosZ, -4000, 800);
                    });

                    // Boucle de rendu (Inertie + Billboarding)
                    const render = () => {
                        rotX += (targetRotX - rotX) * 0.08;
                        rotY += (targetRotY - rotY) * 0.08;
                        posZ += (targetPosZ - posZ) * 0.08;

                        scene.style.transform = `translateZ(${posZ}px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;
                        
                        // Billboarding: les îles regardent toujours la caméra
                        nodes.forEach(wrapper => {
                            wrapper.style.transform = `translate(-50%, -50%) rotateY(${-rotY}deg) rotateX(${-rotX}deg)`;
                        });

                        requestAnimationFrame(render);
                    };
                    requestAnimationFrame(render);
                });
            </script>
        <?php endif; ?>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Effet Parallax avec inertie sur l'image du Str3m
        const str3mImage = document.querySelector('.str3m-image');
        const str3mFigure = document.querySelector('.str3m-figure');
        
        if (str3mImage && str3mFigure) {
            let currentY = 0;
            let targetY = 0;
            
            const renderParallax = () => {
                const rect = str3mFigure.getBoundingClientRect();
                // Si l'élément est visible à l'écran
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    const centerOffset = (window.innerHeight / 2) - (rect.top + rect.height / 2);
                    targetY = centerOffset * 0.15; // Intensité du décalage
                }
                currentY += (targetY - currentY) * 0.08; // Facteur d'inertie douce
                str3mImage.style.transform = `translateY(${-currentY}px)`;
                requestAnimationFrame(renderParallax);
            };
            requestAnimationFrame(renderParallax);
        }
    });
</script>
</body>
</html>
