<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();

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
$brandDomain = preg_replace('/^www\./', '', $host ?: 'sowwwl.com');
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
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
$landVisualItems = array_slice(aza_memory_filter_visual_items($landMemoryItems), 0, 4);
$landSourceGroups = array_slice(aza_memory_group_items_by_source($landMemoryItems), 0, 4);
$landIslandProjection = aza_memory_build_island_projection($landMemoryItems, $land ? (string) $land['slug'] : '');
$authenticatedLand = current_authenticated_land();
$isAuthenticatedHere = $land && $authenticatedLand && auth_is_land_session_for((string) $land['slug']);
$azaLandHref = $land ? '/aza?u=' . rawurlencode((string) $land['slug']) : '/aza';
$islandLandHref = $land ? '/island?u=' . rawurlencode((string) $land['slug']) : '/island';
$azaLandBaseQuery = $land ? ['u' => (string) $land['slug']] : [];
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $land ? h((string) $land['username']) . ' — espace personnel dans ' . h(SITE_TITLE) : 'Terre introuvable — ' . h(SITE_TITLE) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — ' . h(SITE_TITLE) : 'Terre introuvable — ' . h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?= render_pwa_head_tags('main') ?>
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
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
                        <h2 id="clock-title">Temps</h2>
                        <p class="panel-copy">Local.</p>
                    </div>
                    <?php if ($created): ?>
                        <span class="badge">terre posée</span>
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

                <div class="summary-grid">
                    <article class="summary-card">
                        <span class="summary-label">Zone</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $land['zone_code']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Ouverture</span>
                        <strong class="summary-value summary-value-small"><?= h(human_created_label((string) ($land['created_at'] ?? '')) ?? 'maintenant') ?></strong>
                    </article>
                </div>
            </section>

            <aside class="panel reveal" aria-labelledby="ritual-title">
                <h2 id="ritual-title">Liaisons</h2>
                <p class="panel-copy">Réseau local.</p>
                <p class="land-note">
                    Emprunter un ferry ou copier les coordonnées de l'île.
                </p>
                <div class="action-row">
                    <a class="pill-link" href="/">Retour au noyau</a>
                    <?php if ($isAuthenticatedHere): ?>
                        <a class="ghost-link" href="/signal">Ferry 01 : Signal<?= $signalUnread > 0 ? ' · ' . $signalUnread . ' non lu' . ($signalUnread > 1 ? 's' : '') : '' ?></a>
                        <a class="ghost-link" href="<?= h($azaLandHref) ?>"><?= h($azaLandLinkLabel) ?></a>
                        <a class="ghost-link" href="<?= h($islandLandHref) ?>">Île classique</a>
                        <a class="ghost-link" href="/echo.php">Ferry 04 : Écho</a>
                        <a class="ghost-link" href="/sh0re">Sh0re · n0us</a>
                        <a class="ghost-link" href="/0wlslw0">0wlslw0</a>
                        <a class="ghost-link" href="/logout.php">Retirer sa présence</a>
                    <?php else: ?>
                        <a class="ghost-link" href="<?= h($azaLandHref) ?>"><?= h($azaLandLinkLabel) ?></a>
                        <a class="ghost-link" href="<?= h($islandLandHref) ?>">Île classique</a>
                        <a class="ghost-link" href="/echo.php?u=<?= rawurlencode((string) $land['username']) ?>">Ferry 04 : Envoyer un écho</a>
                        <a class="ghost-link" href="/sh0re?u=<?= rawurlencode((string) $land['slug']) ?>">Sh0re de <?= h((string) $land['username']) ?></a>
                        <a class="ghost-link" href="/0wlslw0">0wlslw0 : se repérer</a>
                    <?php endif; ?>
                    <button
                        type="button"
                        class="copy-button"
                        data-copy-link="<?= h($shareUrl) ?>"
                    >Copier les coordonnées</button>
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
                <section class="str3m-island-card c0r3-island-card<?= ($landIslandProjection['status'] ?? '') === 'dense' ? ' is-glowing' : '' ?>" aria-label="Île possible depuis cette Terre">
                    <span class="summary-label">Île possible</span>
                    <strong class="summary-value summary-value-small"><?= h((string) ($landIslandProjection['status_label'] ?? 'Aucune île encore')) ?></strong>
                    <p class="island-meta"><?= h((string) ($landIslandProjection['copy'] ?? '')) ?></p>
                    <?php if (!empty($landIslandProjection['traits']) && is_array($landIslandProjection['traits'])): ?>
                        <div class="aza-meta-list aza-island-traits">
                            <?php foreach ($landIslandProjection['traits'] as $trait): ?>
                                <span class="meta-pill"><?= h((string) $trait) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="action-row aza-island-actions">
                        <a class="pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'finder'])) ?>">Explorer la matière</a>
                        <a class="ghost-link" href="<?= h($islandLandHref) ?>">Ouvrir l’île</a>
                        <a class="ghost-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'visual'])) ?>">Voir le visible</a>
                    </div>
                </section>

                <nav class="c0r3-nav" aria-label="Lectures mémoire liées à aZa">
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'chrono'])) ?>">chrono</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'source'])) ?>">source</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'visual'])) ?>">visuel</a>
                    <a class="meta-pill meta-pill-link" href="<?= h(aza_memory_query_href($azaLandBaseQuery, ['view' => 'finder'])) ?>">finder</a>
                    <a class="meta-pill meta-pill-link" href="<?= h($islandLandHref) ?>">île</a>
                </nav>

                <div class="summary-grid c0r3-summary-grid">
                    <article class="summary-card">
                        <span class="summary-label">Total</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $landMemorySummary['count']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Visuel</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $landMemorySummary['visual_count']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Sources</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $landMemorySummary['source_count']) ?></strong>
                    </article>
                </div>

                <div class="c0r3-panels">
                    <section class="c0r3-panel-block">
                        <h3 class="c0r3-panel-title">Dernières traces</h3>
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

                    <section class="c0r3-panel-block">
                        <h3 class="c0r3-panel-title">Provenances</h3>
                        <div class="c0r3-source-strip">
                            <?php foreach ($landSourceGroups as $group): ?>
                                <article class="c0r3-source-card">
                                    <strong><?= h((string) $group['label']) ?></strong>
                                    <span><?= count($group['items']) ?> trace<?= count($group['items']) > 1 ? 's' : '' ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <?php if ($landVisualItems): ?>
                        <section class="c0r3-panel-block">
                            <h3 class="c0r3-panel-title">Extraits visuels</h3>
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
