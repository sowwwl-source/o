<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
$identifier = trim((string) ($_GET['u'] ?? ''));
$land = null;
$notFound = false;

try {
    $land = find_land($identifier);
    $notFound = $land === null;
} catch (InvalidArgumentException $exception) {
    $notFound = true;
}

if ($notFound) {
    http_response_code(404);
}

$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$authenticatedLand = current_authenticated_land();
$isAuthenticatedHere = $land && $authenticatedLand && auth_is_land_session_for((string) $land['slug']);
$visualProfile = $land ? land_visual_profile($land) : null;
$ambientProfile = $visualProfile ?? land_collective_profile('calm');
$archiveSources = aza_supported_sources();

$islandArchives = $land ? get_archives_for_land((string) $land['slug']) : [];
$islandChronology = aza_prepare_chronology($islandArchives);
$islandSortedArchives = $islandChronology['sorted'];
$islandFiles = $land ? aza_ingest_list_files((string) $land['slug']) : [];
$islandItems = $land ? aza_memory_build_items($islandSortedArchives, $islandFiles, $archiveSources) : [];
$islandSummary = aza_memory_summarize_items($islandItems);
$islandProjection = aza_memory_build_island_projection($islandItems, $land ? (string) $land['slug'] : '');
$islandSourceGroups = array_slice(aza_memory_group_items_by_source($islandItems), 0, 6);
$islandVisualItems = array_slice(aza_memory_filter_visual_items($islandItems), 0, 6);
$islandRecentItems = array_slice($islandItems, 0, 8);
$islandBaseQuery = $land ? ['u' => (string) $land['slug']] : [];
$landHref = $land ? '/land?u=' . rawurlencode((string) $land['slug']) : '/';
$azaHref = $land ? '/aza?u=' . rawurlencode((string) $land['slug']) : '/aza';
$sharePath = $land ? '/island?u=' . rawurlencode((string) $land['slug']) : '/island';
$shareUrl = site_origin() . $sharePath;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $land ? h((string) $land['username']) . ' — île classique en devenir dans ' . h(SITE_TITLE) : 'Île introuvable — ' . h(SITE_TITLE) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — Île classique — ' . h(SITE_TITLE) : 'Île introuvable — ' . h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?= render_pwa_head_tags('main') ?>
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience island-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', 'land') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <?php if ($land): ?>
        <header class="hero page-header reveal">
            <p class="eyebrow"><strong>île classique</strong> <span><?= h((string) $land['slug']) ?></span></p>
            <h1 class="land-title">
                <strong><?= h((string) ($islandProjection['title'] ?? ((string) $land['username']))) ?></strong>
                <span>lecture située · classique pour l’instant</span>
            </h1>
            <p class="lead"><?= h((string) ($islandProjection['copy'] ?? 'Une île lisible à partir de la mémoire déjà déposée.')) ?></p>

            <div class="land-meta">
                <span class="meta-pill"><?= h((string) ($islandProjection['status_label'] ?? 'Aucune île encore')) ?></span>
                <span class="meta-pill"><?= h((string) $islandSummary['count']) ?> trace<?= $islandSummary['count'] > 1 ? 's' : '' ?></span>
                <span class="meta-pill"><?= h((string) $islandSummary['visual_count']) ?> visuelle<?= $islandSummary['visual_count'] > 1 ? 's' : '' ?></span>
                <span class="meta-pill"><?= h((string) $islandSummary['source_count']) ?> provenance<?= $islandSummary['source_count'] > 1 ? 's' : '' ?></span>
                <?php if ($visualProfile): ?>
                    <span class="meta-pill">λ <?= h((string) ($visualProfile['lambda_nm'] ?? '548')) ?> nm</span>
                <?php endif; ?>
            </div>

            <div class="action-row">
                <a class="pill-link" href="<?= h($landHref) ?>">Retour à la Terre</a>
                <a class="ghost-link" href="<?= h($azaHref) ?>">aZa complet</a>
                <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'finder'])) ?>">Finder mémoire</a>
                <button type="button" class="copy-button" data-copy-link="<?= h($shareUrl) ?>">Copier l’URL de l’île</button>
            </div>
        </header>

        <section class="panel-shell island-shell reveal">
            <section class="panel island-main-panel" aria-labelledby="island-relief-title">
                <div class="section-topline">
                    <div>
                        <h2 id="island-relief-title">Relief</h2>
                        <p class="panel-copy">Une lecture classique du relief mémoriel : matières, sources, visible, temps.</p>
                    </div>
                    <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'source'])) ?>">Voir les sources</a>
                </div>

                <div class="island-grid island-grid--memory" aria-label="Relief de l’île">
                    <article class="summary-card island-card">
                        <span class="summary-label">Densité</span>
                        <strong><?= h((string) $islandSummary['count']) ?> traces</strong>
                        <span><?= h((string) ($islandSummary['first_trace'] ?? 'Aucune date')) ?> → <?= h((string) ($islandSummary['last_trace'] ?? ($islandSummary['first_trace'] ?? '—'))) ?></span>
                    </article>
                    <article class="summary-card island-card">
                        <span class="summary-label">Visible</span>
                        <strong><?= h((string) $islandSummary['visual_count']) ?> éléments</strong>
                        <span>Images, vidéos, objets 3D ou archives à dominante visuelle déjà lisibles sans spatialisation.</span>
                    </article>
                    <article class="summary-card island-card">
                        <span class="summary-label">Provenances</span>
                        <strong><?= h((string) $islandSummary['source_count']) ?> origines</strong>
                        <span><?= h(implode(' · ', array_slice(array_map(static fn (array $group): string => (string) $group['label'], $islandSourceGroups), 0, 3))) ?></span>
                    </article>
                </div>
            </section>

            <aside class="panel reveal" aria-labelledby="island-notes-title">
                <h2 id="island-notes-title">Notes</h2>
                <p class="panel-copy">Ici, l’île reste entièrement classique : pas de shell WEB3, pas de Raspberry Pi, pas de conteneur actif.</p>
                <div class="aza-meta-list aza-island-traits">
                    <?php foreach (($islandProjection['traits'] ?? []) as $trait): ?>
                        <span class="meta-pill"><?= h((string) $trait) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="land-note">Cette page sert de préfiguration lisible et déployable vite. Le spatial viendra plus tard, si la matière tient vraiment.</p>
            </aside>
        </section>

        <section class="panel reveal" aria-labelledby="island-sources-title">
            <div class="section-topline aza-timeline-header">
                <div>
                    <h2 id="island-sources-title">Provenances</h2>
                    <p class="panel-copy">Les origines principales de l’île, relues comme un petit archipel de mémoire.</p>
                </div>
                <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'source'])) ?>">aZa · vue source</a>
            </div>
            <div class="c0r3-source-strip">
                <?php foreach ($islandSourceGroups as $group): ?>
                    <article class="c0r3-source-card">
                        <strong><?= h((string) $group['label']) ?></strong>
                        <span><?= count($group['items']) ?> trace<?= count($group['items']) > 1 ? 's' : '' ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($islandVisualItems): ?>
            <section class="panel reveal" aria-labelledby="island-visual-title">
                <div class="section-topline aza-timeline-header">
                    <div>
                        <h2 id="island-visual-title">Visible</h2>
                        <p class="panel-copy">Ce que l’île donne déjà à voir avant toute mise en espace expérimentale.</p>
                    </div>
                    <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'visual'])) ?>">aZa · vue visuelle</a>
                </div>
                <div class="aza-visual-shell">
                    <?php foreach ($islandVisualItems as $item): ?>
                        <article class="aza-visual-card">
                            <?php if (($item['thumbnail_url'] ?? '') !== ''): ?>
                                <div class="aza-visual-thumb">
                                    <img src="<?= h((string) $item['thumbnail_url']) ?>" alt="<?= h((string) $item['title']) ?>" loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="aza-visual-thumb aza-file-thumb-blank">
                                    <span><?= h((string) (($item['format_label'] ?? '') !== '' ? $item['format_label'] : ($item['kind_label'] ?? 'Trace'))) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="aza-visual-meta">
                                <div class="aza-meta-list">
                                    <span class="meta-pill"><?= h((string) ($item['source_label'] ?? 'Mémoire')) ?></span>
                                    <span class="meta-pill"><?= h((string) ($item['date_label'] ?? 'Atemporel')) ?></span>
                                </div>
                                <strong class="aza-finder-title"><?= h((string) ($item['title'] ?? 'Trace')) ?></strong>
                                <?php if (!empty($item['summary'])): ?>
                                    <p class="aza-finder-summary"><?= h((string) $item['summary']) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel reveal" aria-labelledby="island-recent-title">
            <div class="section-topline aza-timeline-header">
                <div>
                    <h2 id="island-recent-title">Dernières traces</h2>
                    <p class="panel-copy">Les éléments les plus récents de l’île, encore proches de leur dépôt.</p>
                </div>
                <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'finder'])) ?>">aZa · finder</a>
            </div>
            <?php if (!$islandRecentItems): ?>
                <p class="panel-copy">Aucune trace encore.</p>
            <?php else: ?>
                <div class="c0r3-cards-list island-recent-grid">
                    <?php foreach ($islandRecentItems as $item): ?>
                        <article class="c0r3-card-light">
                            <div class="c0r3-card-main">
                                <span class="c0r3-source"><?= h((string) ($item['source_label'] ?? 'Mémoire')) ?></span>
                                <span class="c0r3-label" title="<?= h((string) ($item['date_origin'] ?? 'Date mémoire')) ?>"><?= h((string) ($item['date_label'] ?? 'Atemporel')) ?></span>
                            </div>
                            <strong><?= h((string) ($item['title'] ?? 'Trace')) ?></strong>
                            <?php if (!empty($item['summary'])): ?>
                                <p class="c0r3-note"><?= h((string) $item['summary']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item['href'])): ?>
                                <a href="<?= h((string) $item['href']) ?>" class="c0r3-download" download>[ extraire ]</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="hero page-header reveal">
            <p class="eyebrow">île introuvable</p>
            <h1>Aucune île ne se laisse lire ici.</h1>
            <p class="lead">Il faut une Terre existante pour tenter une lecture d’île.</p>
            <div class="hero-actions">
                <a class="pill-link" href="/">Revenir à l’accueil</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
