<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$identifier = (string) ($_GET['u'] ?? '');
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

$created = isset($_GET['created']) && $_GET['created'] === '1';
$sessionBound = isset($_GET['session']) && $_GET['session'] === '1';
$sharePath = $land ? '/land?u=' . rawurlencode((string) $land['slug']) : '/';
$shareUrl = site_origin() . $sharePath;
$landRawArchives = $land ? get_archives_for_land((string) $land['slug']) : [];
$landChronology = aza_prepare_chronology($landRawArchives);
$landSorted = $landChronology['sorted'];
$landGrouped = $landChronology['grouped'];
$landSummary = $landChronology['summary'];
$archiveSources = aza_supported_sources();
$landFiles = $land ? aza_ingest_list_files((string) $land['slug']) : [];
$landMemoryItems = $land ? aza_memory_build_items($landSorted, $landFiles, $archiveSources) : [];
$landMemorySummary = aza_memory_summarize_items($landMemoryItems);
$landRecentItems = array_slice($landMemoryItems, 0, 6);
$landPreviewItems = array_slice(aza_memory_filter_previewable_file_items($landMemoryItems), 0, 3);
$landVisualItems = array_slice(aza_memory_filter_visual_items($landMemoryItems), 0, 4);
$landSourceGroups = array_slice(aza_memory_group_items_by_source($landMemoryItems), 0, 4);
$landIslandProjection = aza_memory_build_island_projection($landMemoryItems, $land ? (string) $land['slug'] : '');
$c0r3LeadItem = $landRecentItems[0] ?? null;
$c0r3LeadSource = $landSourceGroups[0] ?? null;
$c0r3DensityLabel = match (true) {
    ($landMemorySummary['count'] ?? 0) <= 0 => 'vierge',
    ($landMemorySummary['count'] ?? 0) < 4 => 'germe',
    ($landMemorySummary['count'] ?? 0) < 12 => 'prise',
    default => 'dense',
};
$c0r3DensityCopy = match ($c0r3DensityLabel) {
    'vierge' => 'Aucune mémoire déposée pour le moment.',
    'germe' => 'Quelques traces suffisent déjà à orienter la lecture.',
    'prise' => 'Le noyau mémoriel commence à offrir plusieurs chemins d’entrée.',
    default => 'Le noyau mémoriel peut déjà soutenir une lecture dense, visuelle et située.',
};
$authenticatedLand = current_authenticated_land();
$isAuthenticatedHere = $land && $authenticatedLand && auth_is_land_session_for((string) $land['slug']);
$azaLandHref = $land ? '/aza?u=' . rawurlencode((string) $land['slug']) : '/aza';
$islandLandHref = $land ? '/island?u=' . rawurlencode((string) $land['slug']) : '/island';
$azaLandBaseQuery = $land ? ['u' => (string) $land['slug']] : [];
$c0r3NextHref = aza_memory_query_href($azaLandBaseQuery, ['view' => 'finder']);
$c0r3NextLabel = 'Explorer la matière';
$c0r3NextCopy = 'Entrer par le finder pour sentir les liens avant de tout parcourir.';
if (($landMemorySummary['visual_count'] ?? 0) > 0) {
    $c0r3NextHref = aza_memory_query_href($azaLandBaseQuery, ['view' => 'visual']);
    $c0r3NextLabel = 'Voir le visible';
    $c0r3NextCopy = 'Commencer par les extraits visuels les plus lisibles de cette mémoire.';
} elseif (($landMemorySummary['source_count'] ?? 0) > 1) {
    $c0r3NextHref = aza_memory_query_href($azaLandBaseQuery, ['view' => 'source']);
    $c0r3NextLabel = 'Comparer les sources';
    $c0r3NextCopy = 'Lire les provenances avant de descendre dans chaque trace.';
}
$azaLandLinkLabel = $isAuthenticatedHere ? 'Ferry 03 : aZa' : 'Ferry 03 : aZa · lecture publique';
$azaLandEditLabel = $isAuthenticatedHere ? 'Édition Terre via aZa' : 'aZa · lecture publique seulement';
$visualProfile = $land ? land_visual_profile($land) : null;
$ambientProfile = $visualProfile ?? land_collective_profile('calm');
$signalTablesReady = false;
$signalUnread = 0;
$signalIdentityLabel = '';
if ($land && $isAuthenticatedHere) {
    try {
        $signalTablesReady = signal_mail_tables_ready();
        if ($signalTablesReady) {
            $signalMailbox = signal_mailbox_for_land($land);
            $signalUnread = signal_unread_total($land);
            $signalIdentityLabel = signal_identity_status_label((string) ($signalMailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
        }
    } catch (Throwable $exception) {
        $signalTablesReady = false;
        $signalUnread = 0;
        $signalIdentityLabel = '';
    }
}
$shareLabel = preg_replace('#^https?://#', '', $shareUrl);
$publicShoreHref = $land
    ? ($isAuthenticatedHere ? '/sh0re' : '/sh0re?u=' . rawurlencode((string) $land['slug']))
    : '/sh0re';
$publicEchoHref = $land ? '/echo?u=' . rawurlencode((string) $land['username']) : '/echo';
$landViewLabel = $isAuthenticatedHere ? 'présence liée' : 'lecture publique';
$landSignalStatusCopy = $signalTablesReady
    ? ($signalIdentityLabel !== '' ? $signalIdentityLabel : 'Signal prêt')
    : 'Signal en attente';
$landIdentitySummary = $land
    ? '@' . (string) $land['slug'] . ' · ' . (string) $land['timezone'] . ' · ' . (string) $land['email_virtual']
    : '';
if ($land && $visualProfile) {
    $landIdentitySummary .= ' · ' . (string) ($visualProfile['label'] ?? 'collectif') . ' · λ ' . (string) ($visualProfile['lambda_nm'] ?? '548') . ' nm';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $land ? h((string) $land['username']) . ' — espace personnel dans ' . h(SITE_TITLE) : 'Terre introuvable — ' . h(SITE_TITLE) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — ' . h(SITE_TITLE) : 'Terre introuvable — ' . h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body class="experience land-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', 'land') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <?php if ($land): ?>
        <header class="hero page-header reveal">
            <p class="eyebrow"><strong>terre active</strong> <span><?= h((string) $land['slug']) ?></span></p>
            <h1 class="land-title">
                <strong><?= h((string) $land['username']) ?></strong>
                <span>I inverse + voix</span>
            </h1>
            <p class="lead">
                Terre posée. Fuseau gardé.
            </p>

            <div class="land-meta">
                <span class="meta-pill"><?= h((string) $land['timezone']) ?></span>
                <span class="meta-pill"><?= h((string) $land['email_virtual']) ?></span>
                <?php if ($visualProfile): ?>
                    <span class="meta-pill"><?= h((string) ($visualProfile['label'] ?? 'collectif')) ?></span>
                    <span class="meta-pill">λ <?= h((string) ($visualProfile['lambda_nm'] ?? '548')) ?> nm</span>
                <?php endif; ?>
                <span class="meta-pill"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></span>
                <?php if ($isAuthenticatedHere): ?>
                    <span class="meta-pill aza-direct-pill">session liée</span>
                    <?php if ($signalTablesReady && $signalIdentityLabel !== ''): ?>
                        <span class="meta-pill"><?= h($signalIdentityLabel) ?></span>
                        <span class="meta-pill"><?= $signalUnread ?> signal<?= $signalUnread > 1 ? 's' : '' ?> non lu<?= $signalUnread > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <section class="panel-shell">
            <section class="panel reveal" aria-labelledby="clock-title">
                <div class="section-topline">
                    <div>
                        <h2 id="clock-title">Identité située</h2>
                        <p class="panel-copy">Heure locale, fuseau, zone et état de présence pour cette terre.</p>
                    </div>
                    <?php if ($created): ?>
                        <span class="badge">terre posée</span>
                    <?php else: ?>
                        <span class="badge"><?= h($landViewLabel) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($created): ?>
                    <div class="flash flash-success" aria-live="polite">
                        <p>Votre terre est posée.</p>
                    </div>
                <?php endif; ?>

                <?php if ($sessionBound && $isAuthenticatedHere): ?>
                    <div class="flash flash-success" aria-live="polite">
                        <p>Session ouverte sur cette terre.</p>
                    </div>
                <?php endif; ?>

                <div
                    class="clock"
                    aria-live="polite"
                    data-live-clock
                    data-timezone="<?= h((string) $land['timezone']) ?>"
                >
                    <p class="clock-label" data-clock-label>Fuseau : —</p>
                    <p class="clock-time" data-clock-time>--:--:--</p>
                    <p class="clock-date" data-clock-date>--</p>
                </div>

                <div class="summary-grid land-summary-grid">
                    <article class="summary-card">
                        <span class="summary-label">Zone</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $land['zone_code']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Ouverture</span>
                        <strong class="summary-value summary-value-small"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Vue</span>
                        <strong class="summary-value summary-value-small"><?= h($landViewLabel) ?></strong>
                    </article>
                </div>
            </section>

            <section class="panel reveal" aria-labelledby="land-focus-title">
                <div class="section-topline">
                    <div>
                        <h2 id="land-focus-title">Repères</h2>
                        <p class="panel-copy">Cette page distingue ce qui décrit la terre, ce qui reste visible au public et ce qui demande une présence liée.</p>
                    </div>
                    <span class="badge"><?= h((string) $landMemorySummary['count']) ?> trace<?= $landMemorySummary['count'] > 1 ? 's' : '' ?></span>
                </div>

                <div class="land-focus-grid">
                    <article class="land-focus-card">
                        <p class="land-card-kicker">identité</p>
                        <h3><?= h((string) $land['username']) ?></h3>
                        <p class="land-card-copy"><?= h($landIdentitySummary) ?></p>
                    </article>

                    <article class="land-focus-card">
                        <p class="land-card-kicker">ouvert</p>
                        <h3>Lecture, île, partage</h3>
                        <p class="land-card-copy">aZa en lecture, l’île, Sh0re et les coordonnées de cette terre peuvent circuler depuis l’extérieur.</p>
                    </article>

                    <article class="land-focus-card">
                        <p class="land-card-kicker">réservé</p>
                        <h3><?= $isAuthenticatedHere ? 'Boîte et édition ouvertes' : 'Boîte et édition protégées' ?></h3>
                        <p class="land-card-copy">
                            <?php if ($isAuthenticatedHere): ?>
                                Signal, Écho complet et l’édition Terre sont actifs dans cette session. <?= h($landSignalStatusCopy) ?><?= $signalUnread > 0 ? ' · ' . $signalUnread . ' non lu' . ($signalUnread > 1 ? 's' : '') : '' ?>.
                            <?php else: ?>
                                Signal, Écho complet et l’édition Terre restent liés à la présence qui a ouvert cette terre.
                            <?php endif; ?>
                        </p>
                    </article>
                </div>
            </section>

            <aside class="panel reveal" aria-labelledby="ritual-title">
                <div class="section-topline">
                    <div>
                        <h2 id="ritual-title">Passages</h2>
                        <p class="panel-copy">Entrer par le bon niveau: continuer ici, montrer cette terre, ou gérer la présence.</p>
                    </div>
                    <a class="ghost-link" href="/">Retour au noyau</a>
                </div>

                <div class="land-route-grid">
                    <section class="land-route-card">
                        <p class="land-card-kicker">continuer ici</p>
                        <h3><?= $isAuthenticatedHere ? 'Outils liés à cette terre' : 'Portes ouvertes depuis l’extérieur' ?></h3>
                        <p class="land-card-copy">
                            <?php if ($isAuthenticatedHere): ?>
                                Reprendre la boîte, la mémoire et la projection sans ressortir de cette session.
                            <?php else: ?>
                                Lire, visiter ou écrire à cette terre sans ouvrir ses passages privés.
                            <?php endif; ?>
                        </p>
                        <div class="land-route-links">
                            <?php if ($isAuthenticatedHere): ?>
                                <a class="ghost-link" href="/signal">Signal · boîte<?= $signalUnread > 0 ? ' · ' . $signalUnread . ' non lu' . ($signalUnread > 1 ? 's' : '') : '' ?></a>
                                <a class="ghost-link" href="<?= h($azaLandHref) ?>">aZa · édition Terre</a>
                                <a class="ghost-link" href="/echo">Écho · direct</a>
                                <a class="ghost-link" href="<?= h($islandLandHref) ?>">Île classique</a>
                            <?php else: ?>
                                <a class="ghost-link" href="<?= h($azaLandHref) ?>"><?= h($azaLandLinkLabel) ?></a>
                                <a class="ghost-link" href="<?= h($islandLandHref) ?>">Île classique</a>
                                <a class="ghost-link" href="<?= h($publicEchoHref) ?>">Envoyer un écho</a>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="land-route-card">
                        <p class="land-card-kicker">montrer</p>
                        <h3>Sortie publique de la terre</h3>
                        <p class="land-card-copy">Surface, lien partageable et lecture publique restent les passages les plus simples à transmettre.</p>
                        <div class="land-route-links">
                            <a class="ghost-link" href="<?= h($publicShoreHref) ?>"><?= $isAuthenticatedHere ? 'Sh0re · n0us' : 'Sh0re de ' . h((string) $land['username']) ?></a>
                            <a class="ghost-link" href="<?= h($azaLandHref) ?>"><?= h($azaLandLinkLabel) ?></a>
                            <button
                                type="button"
                                class="copy-button"
                                data-copy-link="<?= h($shareUrl) ?>"
                            >Copier les coordonnées</button>
                        </div>
                        <p class="land-share-note"><?= h($shareLabel) ?></p>
                    </section>

                    <section class="land-route-card">
                        <p class="land-card-kicker">présence</p>
                        <h3><?= $isAuthenticatedHere ? 'Tenir ou retirer la session' : 'Se repérer ou ouvrir sa propre terre' ?></h3>
                        <p class="land-card-copy">
                            <?php if ($isAuthenticatedHere): ?>
                                Le guide reste là si tu veux te recadrer. Tu peux aussi fermer proprement cette présence.
                            <?php else: ?>
                                Depuis l’extérieur, tu peux encore te repérer ou ouvrir une autre terre pour retrouver les passages privés.
                            <?php endif; ?>
                        </p>
                        <div class="land-route-links">
                            <a class="ghost-link" href="/0wlslw0"><?= $isAuthenticatedHere ? '0wlslw0 · me guider' : '0wlslw0 · se repérer' ?></a>
                            <?php if ($isAuthenticatedHere): ?>
                                <a class="ghost-link" href="/logout.php">Retirer sa présence</a>
                            <?php else: ?>
                                <a class="ghost-link" href="/rejoindre">Poser une terre</a>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </aside>
        </section>

        <section id="c0r3" class="panel reveal c0r3-shell land-c0r3-section" aria-labelledby="c0r3-title">
            <header class="section-topline c0r3-header">
                <div>
                    <h2 id="c0r3-title">c0r3 <span class="c0r3-subtitle">// Noyau mémoriel</span></h2>
                    <?php if ($landMemorySummary['count'] > 0): ?>
                        <p class="c0r3-summary-text">
                            [ <?= h((string) $landMemorySummary['count']) ?> trace<?= $landMemorySummary['count'] > 1 ? 's' : '' ?> ]
                            <?php if (!empty($landMemorySummary['first_trace'])): ?>
                                ~ De <?= h((string) $landMemorySummary['first_trace']) ?> à <?= h((string) ($landMemorySummary['last_trace'] ?? $landMemorySummary['first_trace'])) ?>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p class="c0r3-summary-text">[ Aucune mémoire sédimentée ]</p>
                    <?php endif; ?>
                </div>
                <a class="ghost-link" href="<?= h($azaLandHref) ?>"><?= h($azaLandEditLabel) ?></a>
            </header>

            <?php if (!$landMemorySummary['count']): ?>
                <p class="panel-copy">La mémoire du c0r3 est vierge. La première archive attend de sédimenter.</p>
            <?php else: ?>
                <div class="c0r3-overview-grid">
                    <section class="c0r3-overview-card c0r3-overview-card--status<?= ($landIslandProjection['status'] ?? '') === 'dense' ? ' is-glowing' : '' ?>" aria-label="État actuel du noyau mémoriel">
                        <span class="summary-label">état</span>
                        <strong class="summary-value summary-value-small"><?= h((string) ($landIslandProjection['status_label'] ?? 'Aucune île encore')) ?></strong>
                        <p class="c0r3-overview-copy"><?= h((string) ($landIslandProjection['copy'] ?? $c0r3DensityCopy)) ?></p>
                        <?php if (!empty($landIslandProjection['traits']) && is_array($landIslandProjection['traits'])): ?>
                            <div class="aza-meta-list aza-island-traits">
                                <?php foreach ($landIslandProjection['traits'] as $trait): ?>
                                    <span class="meta-pill"><?= h((string) $trait) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="action-row aza-island-actions">
                            <a class="pill-link" href="<?= h($c0r3NextHref) ?>"><?= h($c0r3NextLabel) ?></a>
                            <a class="ghost-link" href="<?= h($islandLandHref) ?>">Ouvrir l’île</a>
                        </div>
                    </section>

                    <section class="c0r3-overview-card" aria-label="Trace la plus proche">
                        <span class="summary-label">trace la plus proche</span>
                        <?php if ($c0r3LeadItem): ?>
                            <strong class="summary-value summary-value-small"><?= h((string) $c0r3LeadItem['title']) ?></strong>
                            <p class="c0r3-overview-copy"><?= h((string) ($c0r3LeadItem['summary'] ?? 'Cette trace est la meilleure porte d’entrée immédiate.')) ?></p>
                            <p class="c0r3-overview-meta"><?= h((string) $c0r3LeadItem['source_label']) ?> · <?= h((string) ($c0r3LeadItem['date_label'] ?? 'Atemporel')) ?></p>
                            <a href="<?= h((string) $c0r3LeadItem['href']) ?>" class="c0r3-download" download>[ extraire ]</a>
                        <?php else: ?>
                            <strong class="summary-value summary-value-small">Aucune trace encore</strong>
                            <p class="c0r3-overview-copy">Le noyau mémoriel ne contient pas encore de preuve immédiatement relisible.</p>
                        <?php endif; ?>
                    </section>

                    <section class="c0r3-overview-card" aria-label="Lecture recommandée">
                        <span class="summary-label">prise</span>
                        <strong class="summary-value summary-value-small"><?= h($c0r3DensityLabel) ?></strong>
                        <p class="c0r3-overview-copy"><?= h($c0r3NextCopy) ?></p>
                        <div class="c0r3-mini-stats" aria-label="Résumé mémoire">
                            <article class="c0r3-mini-stat">
                                <span>Total</span>
                                <strong><?= h((string) $landMemorySummary['count']) ?></strong>
                            </article>
                            <article class="c0r3-mini-stat">
                                <span>Visuel</span>
                                <strong><?= h((string) $landMemorySummary['visual_count']) ?></strong>
                            </article>
                            <article class="c0r3-mini-stat">
                                <span>Sources</span>
                                <strong><?= h((string) $landMemorySummary['source_count']) ?></strong>
                            </article>
                        </div>
                        <?php if ($c0r3LeadSource): ?>
                            <p class="c0r3-overview-meta">Source dominante · <?= h((string) $c0r3LeadSource['label']) ?> · <?= count($c0r3LeadSource['items']) ?> trace<?= count($c0r3LeadSource['items']) > 1 ? 's' : '' ?></p>
                        <?php endif; ?>
                    </section>
                </div>

                <nav class="c0r3-nav" aria-label="Lectures mémoire liées à aZa">
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'chrono'])) ?>">chrono</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'source'])) ?>">source</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'visual'])) ?>">visuel</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'finder'])) ?>">finder</a>
                    <a class="meta-pill meta-pill-link" href="<?= h($islandLandHref) ?>">île</a>
                </nav>

                <div class="c0r3-memory-flow">
                    <section class="c0r3-panel-block c0r3-panel-block--latest">
                        <h3 class="c0r3-panel-title">Dernières traces</h3>
                        <p class="c0r3-panel-copy">Les preuves les plus proches, pour relire vite sans ouvrir toute l’archive.</p>
                        <div class="c0r3-cards-list">
                            <?php foreach ($landRecentItems as $item): ?>
                                <article class="c0r3-card-light">
                                    <div class="c0r3-card-main">
                                        <span class="c0r3-source"><?= h((string) $item['source_label']) ?></span>
                                        <span class="c0r3-label" title="<?= h((string) ($item['date_origin'] ?? 'Date mémoire')) ?>"><?= h((string) ($item['date_label'] ?? 'Atemporel')) ?></span>
                                    </div>
                                    <strong><?= h((string) $item['title']) ?></strong>
                                    <?php if (!empty($item['summary'])): ?>
                                        <p class="c0r3-note"><?= h((string) $item['summary']) ?></p>
                                    <?php endif; ?>
                                    <a href="<?= h((string) $item['href']) ?>" class="c0r3-download" download>
                                        [ extraire ]
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="c0r3-side-column">
                        <section class="c0r3-panel-block">
                            <h3 class="c0r3-panel-title">Provenances</h3>
                            <p class="c0r3-panel-copy">Les sources dominantes de cette mémoire, avant d’entrer dans chaque fichier.</p>
                            <div class="c0r3-source-strip">
                                <?php foreach ($landSourceGroups as $group): ?>
                                    <article class="c0r3-source-card">
                                        <strong><?= h((string) $group['label']) ?></strong>
                                        <span><?= count($group['items']) ?> trace<?= count($group['items']) > 1 ? 's' : '' ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <?php if ($landPreviewItems): ?>
                            <section class="c0r3-panel-block">
                                <h3 class="c0r3-panel-title">Préviews déposées</h3>
                                <p class="c0r3-panel-copy">Les fichiers aZa qui peuvent maintenant se relire directement depuis la Terre, sans passer par l’île.</p>
                                <div class="c0r3-preview-grid">
                                    <?php foreach ($landPreviewItems as $item): ?>
                                        <?php $preview = aza_memory_item_preview_payload($item); ?>
                                        <article class="c0r3-preview-card">
                                            <div class="c0r3-preview-frame c0r3-preview-frame--<?= h((string) $preview['mode']) ?>">
                                                <?php if ($preview['mode'] === 'image'): ?>
                                                    <img src="<?= h((string) $preview['display_src']) ?>" alt="<?= h((string) $item['title']) ?>" loading="lazy">
                                                <?php elseif ($preview['mode'] === 'video'): ?>
                                                    <video controls playsinline preload="metadata"<?= (string) ($preview['poster'] ?? '') !== '' ? ' poster="' . h((string) $preview['poster']) . '"' : '' ?>>
                                                        <source src="<?= h((string) $preview['display_src']) ?>">
                                                    </video>
                                                <?php elseif ($preview['mode'] === 'audio'): ?>
                                                    <div class="c0r3-preview-audio-shell">
                                                        <span class="c0r3-preview-badge"><?= h((string) $preview['fallback_label']) ?></span>
                                                        <audio controls preload="none">
                                                            <source src="<?= h((string) $preview['display_src']) ?>">
                                                        </audio>
                                                    </div>
                                                <?php elseif ($preview['mode'] === 'pdf'): ?>
                                                    <iframe src="<?= h((string) $preview['display_src']) ?>#view=FitH" title="<?= h((string) $item['title']) ?>" loading="lazy"></iframe>
                                                <?php elseif ($preview['mode'] === 'text'): ?>
                                                    <pre class="c0r3-preview-text c0r3-preview-text--<?= h((string) ($preview['text_kind'] ?? 'text')) ?>"><?= h((string) ($preview['text'] ?? '')) ?></pre>
                                                <?php else: ?>
                                                    <div class="c0r3-preview-fallback">
                                                        <span class="c0r3-preview-badge"><?= h((string) $preview['fallback_label']) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="c0r3-preview-meta">
                                                <strong><?= h((string) $item['title']) ?></strong>
                                                <span class="aza-file-detail"><?= h((string) $item['meta_label']) ?> · <?= h((string) $item['date_label']) ?></span>
                                                <?php if (!empty($item['summary'])): ?>
                                                    <p class="c0r3-note"><?= h((string) $item['summary']) ?></p>
                                                <?php endif; ?>
                                                <div class="c0r3-preview-actions">
                                                    <a class="ghost-link" href="<?= h((string) $item['href']) ?>" target="_blank" rel="noreferrer">ouvrir</a>
                                                    <a href="<?= h((string) $item['href']) ?>" class="c0r3-download" download>[ extraire ]</a>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php elseif ($landVisualItems): ?>
                            <section class="c0r3-panel-block">
                                <h3 class="c0r3-panel-title">Extraits visuels</h3>
                                <p class="c0r3-panel-copy">Quelques prises visibles, pour éviter de fouiller tout le noyau d’un coup.</p>
                                <div class="aza-files-grid c0r3-visual-grid">
                                    <?php foreach ($landVisualItems as $item): ?>
                                        <article class="aza-file-card">
                                            <?php if (!empty($item['thumbnail_url'])): ?>
                                                <div class="aza-file-thumb">
                                                    <img src="<?= h((string) $item['thumbnail_url']) ?>" alt="<?= h((string) $item['title']) ?>" loading="lazy">
                                                </div>
                                            <?php else: ?>
                                                <div class="aza-file-thumb aza-file-thumb-blank">
                                                    <span><?= h((string) ($item['format_label'] ?: $item['kind_label'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="aza-file-meta">
                                                <strong class="aza-file-name"><?= h((string) $item['title']) ?></strong>
                                                <span class="aza-file-detail"><?= h((string) $item['source_label']) ?></span>
                                                <span class="aza-file-detail"><?= h((string) $item['date_label']) ?></span>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="hero page-header reveal">
            <p class="eyebrow">terre introuvable</p>
            <h1>Cette porte ne mène nulle part.</h1>
            <p class="lead">
                Rien ici.
            </p>
            <div class="hero-actions">
                <a class="pill-link" href="/">Revenir à l’accueil</a>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
