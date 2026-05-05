<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();

$csrfToken = csrf_token();
$brandDomain = preg_replace('/^www\./', '', $host ?: 'sowwwl.com');
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$ownerSlug = trim((string) ($_GET['u'] ?? $_POST['owner_slug'] ?? ''));
$ownerLand = null;
$directHost = aza_direct_host();
$isDirectRequest = aza_is_direct_request($host);
$authenticatedLand = current_authenticated_land();
$guideHref = '/0wlslw0';
$azaGuide = guide_path('aza');

if ($ownerSlug !== '') {
    try {
        $ownerLand = find_land($ownerSlug);
        if ($ownerLand) {
            $ownerSlug = (string) $ownerLand['slug'];
        }
    } catch (InvalidArgumentException $exception) {
        $ownerLand = null;
    }
}

$isAuthenticatedHere = $ownerLand && $authenticatedLand && auth_is_land_session_for((string) $ownerLand['slug']);
$canEditArchives = $authenticatedLand && ($ownerLand === null || $isAuthenticatedHere);
$publicReadOnly = !$canEditArchives;
$editLand = $ownerLand ?: $authenticatedLand;

$message = '';
$messageType = 'info';
$imported = null;
$ingestedFile = null;
$activeTab = 'zip';
$form = [
    'owner_slug' => $ownerSlug,
    'label'      => '',
    'source_hint' => 'auto',
    'notes'      => '',
    'date_hint'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['owner_slug']  = trim((string) ($_POST['owner_slug'] ?? ''));
    $form['label']       = trim((string) ($_POST['label'] ?? ''));
    $form['source_hint'] = trim((string) ($_POST['source_hint'] ?? 'auto'));
    $form['notes']       = trim((string) ($_POST['notes'] ?? ''));
    $form['date_hint']   = trim((string) ($_POST['date_hint'] ?? ''));
    $csrfCandidate       = (string) ($_POST['csrf_token'] ?? '');
    $formAction          = trim((string) ($_POST['form_action'] ?? 'zip'));
    $activeTab           = $formAction === 'file' ? 'file' : 'zip';

    if ($publicReadOnly) {
        $message = $ownerLand
            ? 'Lecture publique seulement. Ouvre la Terre liée pour déposer ou modifier cette mémoire.'
            : 'Lecture publique seulement. Ouvre une Terre pour déposer une archive.';
        $messageType = 'warning';
    } elseif (!verify_csrf_token($csrfCandidate)) {
        $message = 'Session expirée. Recharge la page et réessaie.';
        $messageType = 'warning';
    } elseif ($formAction === 'file') {
        $uploadedFile = $_FILES['ingest_file'] ?? null;
        if (!$uploadedFile || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $message = 'Choisis un fichier à déposer.';
            $messageType = 'warning';
        } else {
            try {
                $ingestedFile = aza_ingest_import_file($uploadedFile, $form);
                $form['owner_slug'] = (string) ($ingestedFile['owner_slug'] ?? $form['owner_slug']);
                $message = 'Fichier déposé — ' . h((string) $ingestedFile['label']) . ' · ' . aza_ingest_family_label((string) $ingestedFile['format_family']) . ' · ' . aza_format_bytes((int) $ingestedFile['size']) . '.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            }
        }
    } else {
        if (($fileZip = $_FILES['archive_zip'] ?? null) === null || ($fileZip['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $message = 'Choisis une archive ZIP à déposer.';
            $messageType = 'warning';
        } else {
            try {
                $imported = aza_import_zip_archive($fileZip, $form);
                $form['owner_slug'] = (string) ($imported['owner_slug'] ?? $form['owner_slug']);
                $message = 'Archive déposée. aZa garde le ZIP et résume sa structure.';
                $messageType = 'success';
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            }
        }
    }
}

$rawArchives = $form['owner_slug'] !== ''
    ? aza_list_archives($form['owner_slug'])
    : get_all_aza_archives();
$chronology      = aza_prepare_chronology($rawArchives);
$sortedArchives  = $chronology['sorted'];
$groupedArchives = $chronology['grouped'];
$chronoSummary   = $chronology['summary'];
$sources         = aza_supported_sources();
$directUploadUrl = aza_direct_upload_url($form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug);

$rawFiles     = aza_ingest_list_files($form['owner_slug'] !== '' ? $form['owner_slug'] : null);
$filesByFamily = aza_ingest_group_by_family($rawFiles);
$filesByFormat = aza_memory_group_files_by_format($rawFiles);
$filesBySize = aza_memory_group_files_by_size($rawFiles);
$memoryViews = aza_memory_allowed_views();
$memoryView = aza_memory_normalize_view((string) ($_GET['view'] ?? 'chrono'));
$memoryBaseQuery = [
    'u' => $form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug,
    'view' => $memoryView,
];
$finderQuery = trim((string) ($_GET['q'] ?? ''));
$finderKind = trim((string) ($_GET['kind'] ?? 'all'));
$finderKind = in_array($finderKind, ['all', 'archive', 'file'], true) ? $finderKind : 'all';
$finderSort = aza_memory_normalize_sort((string) ($_GET['sort'] ?? 'newest'));
$memoryFinderItems = aza_memory_build_items($sortedArchives, $rawFiles, $sources);
$memorySourceGroups = aza_memory_group_items_by_source($memoryFinderItems);
$memoryVisualItems = aza_memory_filter_visual_items($memoryFinderItems);
$finderFamilyOptions = aza_memory_build_family_options($memoryFinderItems);
$finderFamily = trim((string) ($_GET['family'] ?? 'all'));
$finderFamily = $finderFamily === 'all' || array_key_exists($finderFamily, $finderFamilyOptions) ? $finderFamily : 'all';
$finderSource = trim((string) ($_GET['source'] ?? 'all'));
$finderSourceOptions = [];
foreach ($memorySourceGroups as $group) {
    $finderSourceOptions[(string) $group['key']] = (string) $group['label'];
}
$finderSource = $finderSource === 'all' || array_key_exists($finderSource, $finderSourceOptions) ? $finderSource : 'all';
$filteredFinderItems = aza_memory_sort_items(
    aza_memory_filter_items($memoryFinderItems, $finderQuery, $finderKind, $finderFamily, $finderSource),
    $finderSort
);
$finderPreviewItem = $filteredFinderItems[0] ?? null;
$memoryViewCopy = [
    'chrono' => $form['owner_slug'] !== ''
        ? 'Filtre : ' . $form['owner_slug'] . ' · la mémoire se relit par strates temporelles.'
        : 'La mémoire prend date, puis distance.',
    'family' => 'Lecture par grandes familles de fichiers : image, audio, vidéo, document, design, 3D, données.',
    'format' => 'Lecture par extension réelle : ce que la mémoire contient, format par format.',
    'size' => 'Lecture pondérale : du léger au massif, comme un chercheur de fichiers.',
    'source' => 'Lecture par provenance : plateformes d’archive et dépôts libres deviennent des familles de mémoire.',
    'visual' => 'Lecture image-first : ce qui porte le visible, le spatial et les traces exposables.',
    'finder' => 'Lecture transversale : archives ZIP + fichiers libres, avec recherche locale, tri, filtres et aperçu.',
];
$memoryTotals = array_merge([
    'archives' => count($sortedArchives),
    'files' => count($rawFiles),
    'all' => count($memoryFinderItems),
], aza_memory_summarize_items($memoryFinderItems));
$islandProjection = aza_memory_build_island_projection($memoryFinderItems, $form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug);
$islandHref = ($form['owner_slug'] !== '' || $ownerSlug !== '')
    ? '/island?u=' . rawurlencode((string) ($form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug))
    : '';

$ambientLand    = $ownerLand ?: $authenticatedLand;
$ambientProfile = $ambientLand ? land_visual_profile($ambientLand) : land_collective_profile('nocturnal');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ferry 03 — fichiers et sédimentation d'archives dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Fichiers (aZa) — <?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?= render_pwa_head_tags('main') ?>
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience aza-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'aza') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>ferry 03</strong> <span>Fichiers / mémoire légère</span></p>
        <h1 class="land-title">
            <strong>Déposer sans algorithme.</strong>
            <span>I inverse + voix</span>
        </h1>
        <p class="lead">
            Déposer ce qui compte, sans rejouer le bruit.
        </p>

        <div class="land-meta">
            <span class="meta-pill">ZIP · fichiers libres</span>
            <span class="meta-pill"><?= h(aza_format_bytes(AZA_MAX_UPLOAD_BYTES)) ?> max côté app</span>
            <span class="meta-pill">archive légère</span>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($isDirectRequest): ?>
                <span class="meta-pill aza-direct-pill">entrée directe active<?= $directHost ? ' · ' . h($directHost) : '' ?></span>
            <?php elseif ($directUploadUrl): ?>
                <a class="meta-pill meta-pill-link aza-direct-link" href="<?= h($directUploadUrl) ?>">gros ZIP : entrée directe</a>
            <?php endif; ?>
            <?php if ($ownerLand): ?>
                <span class="meta-pill">terre liée : <?= h((string) $ownerLand['slug']) ?></span>
                <?php if ($isAuthenticatedHere): ?>
                    <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">édition Terre</a>
                <?php else: ?>
                    <span class="meta-pill">lecture publique seulement</span>
                    <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">Terre</a>
                <?php endif; ?>
                <?php if ($authenticatedLand && $ownerLand['slug'] !== $authenticatedLand['slug']): ?>
                    <a class="meta-pill meta-pill-link" style="color: rgb(var(--land-secondary-rgb)); border-color: rgba(var(--land-secondary-rgb)/0.5);" href="/echo.php?u=<?= rawurlencode((string) $ownerLand['username']) ?>">écho direct</a>
                <?php endif; ?>
            <?php elseif ($authenticatedLand): ?>
                <span class="meta-pill">session liée : <?= h((string) $authenticatedLand['slug']) ?></span>
                <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $authenticatedLand['slug']) ?>">édition Terre</a>
            <?php else: ?>
                <span class="meta-pill">lecture publique seulement</span>
            <?php endif; ?>
        </div>

        <?php if ($directUploadUrl): ?>
            <p class="panel-copy aza-direct-copy">
                <?php if ($isDirectRequest): ?>
                    Entrée directe active.
                <?php else: ?>
                    Très gros ZIP&nbsp;:
                    <a class="ghost-link" href="<?= h($directUploadUrl) ?>">ouvrir <?= h($directHost ?? "l'entrée directe") ?></a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <section class="panel reveal meaning-panel" aria-labelledby="aza-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="aza-meaning-title">Pourquoi cette porte existe</h2>
                <p class="panel-copy"><?= h((string) ($azaGuide['copy'] ?? 'Déposer, lire et retrouver des archives sans transformer la mémoire en flux opaque.')) ?></p>
            </div>
            <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0 : me guider</a>
        </div>
    </section>

    <section class="panel-shell aza-shell">
        <section class="panel reveal" aria-labelledby="aza-import-title">
            <div class="section-topline">
                <div>
                    <h2 id="aza-import-title">Sédimentation</h2>
                    <p class="panel-copy">
                        <?= $publicReadOnly ? 'La mémoire reste lisible ici. Le dépôt passe par la Terre liée.' : 'Un ZIP entre, une mémoire se forme.' ?>
                    </p>
                </div>
                <a class="ghost-link" href="<?=
                    $editLand
                        ? '/land.php?u=' . rawurlencode((string) $editLand['slug'])
                        : ($form['owner_slug'] !== '' ? '/land.php?u=' . rawurlencode($form['owner_slug']) : '/')
                ?>">
                    <?= $editLand ? 'Terre' : ($form['owner_slug'] !== '' ? 'Retour à la terre' : 'Retour au noyau') ?>
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                    <p><?= h($message) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($publicReadOnly): ?>
                <div class="signup-preview aza-preview">
                    <span class="summary-label">Mode</span>
                    <strong class="preview-title">Lecture publique seulement</strong>
                    <p class="land-note">
                        <?= $ownerLand
                            ? "Cette mémoire reste ouverte à la lecture, mais l'écriture passe par la Terre liée."
                            : "La mémoire collective reste visible ici, mais l'écriture demande une Terre active." ?>
                    </p>
                    <div class="action-row">
                        <?php if ($ownerLand): ?>
                            <a class="pill-link" href="/land.php?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">Terre</a>
                        <?php else: ?>
                            <a class="pill-link" href="/">Ouvrir une Terre</a>
                        <?php endif; ?>
                        <?php if ($authenticatedLand): ?>
                            <a class="ghost-link" href="/land.php?u=<?= rawurlencode((string) $authenticatedLand['slug']) ?>">Retour à mon édition</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="aza-tabs" role="tablist">
                    <button role="tab" class="aza-tab<?= $activeTab === 'zip' ? ' aza-tab-active' : '' ?>" data-tab="aza-tab-zip" aria-selected="<?= $activeTab === 'zip' ? 'true' : 'false' ?>">Archive ZIP</button>
                    <button role="tab" class="aza-tab<?= $activeTab === 'file' ? ' aza-tab-active' : '' ?>" data-tab="aza-tab-file" aria-selected="<?= $activeTab === 'file' ? 'true' : 'false' ?>">Dépôt libre</button>
                </div>

                <div id="aza-tab-zip" class="aza-tab-panel<?= $activeTab === 'zip' ? '' : ' aza-tab-panel-hidden' ?>">
                    <form method="post" enctype="multipart/form-data" class="land-form aza-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="zip">

                        <label>
                            Terre liée
                            <input type="text" name="owner_slug" placeholder="ex: nox" value="<?= h($form['owner_slug']) ?>">
                            <span class="input-hint">Optionnel.</span>
                        </label>

                        <label>
                            Nom de l'archive
                            <input type="text" name="label" placeholder="ex: export-instagram-2024" value="<?= h($form['label']) ?>">
                        </label>

                        <label>
                            Source probable
                            <select name="source_hint">
                                <?php foreach ($sources as $value => $label): ?>
                                    <option value="<?= h($value) ?>" <?= $form['source_hint'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Archive ZIP
                            <input type="file" name="archive_zip" accept=".zip,application/zip" required>
                            <span class="input-hint">ZIP seulement.</span>
                            <?php if ($directUploadUrl && !$isDirectRequest): ?>
                                <span class="input-hint">Pour un très gros ZIP, utilise <a class="ghost-link" href="<?= h($directUploadUrl) ?>">l'entrée directe</a>.</span>
                            <?php endif; ?>
                        </label>

                        <label>
                            Note de contexte
                            <textarea name="notes" rows="4" placeholder="Contexte, provenance, repères utiles."><?= h($form['notes']) ?></textarea>
                        </label>

                        <button type="submit">Sédimenter l'archive</button>
                    </form>
                </div>

                <div id="aza-tab-file" class="aza-tab-panel<?= $activeTab === 'file' ? '' : ' aza-tab-panel-hidden' ?>">
                    <form method="post" enctype="multipart/form-data" class="land-form aza-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="form_action" value="file">

                        <label>
                            Terre liée
                            <input type="text" name="owner_slug" placeholder="ex: nox" value="<?= h($form['owner_slug']) ?>">
                            <span class="input-hint">Optionnel.</span>
                        </label>

                        <label>
                            Nom du fichier
                            <input type="text" name="label" placeholder="ex: collier-prototype-v3" value="<?= h($form['label']) ?>">
                        </label>

                        <label>
                            Époque
                            <input type="text" name="date_hint" placeholder="ex: 2014, 2019-03, automne 2011" value="<?= h($form['date_hint']) ?>">
                            <span class="input-hint">Quand ce fichier a-t-il été créé ? Libre.</span>
                        </label>

                        <label>
                            Fichier
                            <?php $allExts = implode(',', array_map(static fn($e) => '.' . $e, aza_ingest_allowed_extensions())); ?>
                            <input type="file" name="ingest_file" accept="<?= h($allExts) ?>" required>
                            <span class="input-hint">Image, vidéo, audio, PDF, PSD, AI, INDD, OBJ, SKP, STL, GLB…</span>
                        </label>

                        <label>
                            Note de contexte
                            <textarea name="notes" rows="4" placeholder="Contexte, intention, matière, époque."><?= h($form['notes']) ?></textarea>
                        </label>

                        <button type="submit">Déposer le fichier</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($imported): ?>
                <div class="signup-preview aza-preview">
                    <span class="summary-label">Dernier dépôt ZIP</span>
                    <strong class="preview-title"><?= h((string) $imported['label']) ?></strong>
                    <?php if (!empty($imported['human_summary'])): ?>
                        <p class="land-note"><?= h((string) $imported['human_summary']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($imported['memory_note'])): ?>
                        <p class="panel-copy aza-memory-note"><?= h((string) $imported['memory_note']) ?></p>
                    <?php endif; ?>
                    <div class="preview-grid">
                        <p><span>Source</span><code><?= h((string) ($sources[$imported['source']] ?? (string) $imported['source'])) ?></code></p>
                        <p><span>Entrées repérées</span><code><?= h((string) $imported['entries']) ?></code></p>
                        <p><span>Fichier ZIP</span><code>/<?= h((string) $imported['stored_file']) ?></code></p>
                        <?php if (!empty($imported['years']) && is_array($imported['years'])): ?>
                            <p><span>Repères temporels</span><code><?= h(implode(' · ', array_map('strval', $imported['years']))) ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($ingestedFile): ?>
                <div class="signup-preview aza-preview">
                    <span class="summary-label">Dernier dépôt libre</span>
                    <strong class="preview-title"><?= h((string) $ingestedFile['label']) ?></strong>
                    <div class="preview-grid">
                        <p><span>Format</span><code><?= h(strtoupper((string) $ingestedFile['format'])) ?> · <?= h(aza_ingest_family_label((string) $ingestedFile['format_family'])) ?></code></p>
                        <p><span>Taille</span><code><?= h(aza_format_bytes((int) $ingestedFile['size'])) ?></code></p>
                        <?php if (!empty($ingestedFile['date_hint'])): ?>
                            <p><span>Époque</span><code><?= h((string) $ingestedFile['date_hint']) ?></code></p>
                        <?php endif; ?>
                        <?php if (!empty($ingestedFile['meta']['width'])): ?>
                            <p><span>Dimensions</span><code><?= h((string) $ingestedFile['meta']['width']) ?> × <?= h((string) $ingestedFile['meta']['height']) ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <aside class="panel reveal" aria-labelledby="aza-principles-title">
            <h2 id="aza-principles-title">Principe</h2>
            <p class="panel-copy">Ni fil, ni score : une mémoire qu'on peut relire.</p>
            <div class="summary-grid aza-principles-grid">
                <article class="summary-card">
                    <span class="summary-label">01</span>
                    <strong class="summary-value summary-value-small">ZIP</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">02</span>
                    <strong class="summary-value summary-value-small">Fichiers</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">03</span>
                    <strong class="summary-value summary-value-small">Trace</strong>
                </article>
            </div>
        </aside>
    </section>

    <section class="panel reveal" aria-labelledby="aza-memory-title">
        <div class="section-topline aza-timeline-header aza-memory-header">
            <div>
                <h2 id="aza-memory-title">Lecteur mémoriel</h2>
                <p class="panel-copy"><?= h($memoryViewCopy[$memoryView] ?? $memoryViewCopy['chrono']) ?></p>
            </div>
            <div class="aza-stats" aria-label="Statistiques mémoire">
                <span>Archives : <?= h((string) $memoryTotals['archives']) ?></span>
                <span class="separator" aria-hidden="true">|</span>
                <span>Fichiers : <?= h((string) $memoryTotals['files']) ?></span>
                <span class="separator" aria-hidden="true">|</span>
                <span>Total : <?= h((string) $memoryTotals['all']) ?></span>
            </div>
        </div>

        <section class="str3m-island-card aza-island-projection<?= ($islandProjection['status'] ?? '') === 'dense' ? ' is-glowing' : '' ?>" aria-label="Préfiguration d'île">
            <span class="summary-label">Possibilité d’île</span>
            <strong class="summary-value summary-value-small"><?= h((string) ($islandProjection['status_label'] ?? 'Aucune île encore')) ?></strong>
            <p class="island-meta"><?= h((string) ($islandProjection['copy'] ?? '')) ?></p>
            <?php if (!empty($islandProjection['traits']) && is_array($islandProjection['traits'])): ?>
                <div class="aza-meta-list aza-island-traits">
                    <?php foreach ($islandProjection['traits'] as $trait): ?>
                        <span class="meta-pill"><?= h((string) $trait) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="action-row aza-island-actions">
                <?php if (($form['owner_slug'] !== '' || $ownerSlug !== '') && ($islandProjection['status'] ?? '') !== 'void'): ?>
                    <a class="pill-link" href="<?= h($islandHref) ?>">Ouvrir l’île</a>
                    <a class="ghost-link" href="/land.php?u=<?= rawurlencode((string) ($form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug)) ?>">Relire la Terre</a>
                <?php endif; ?>
                <a class="ghost-link" href="<?= h(aza_memory_query_href($memoryBaseQuery, ['view' => 'visual'])) ?>">Voir le visible</a>
                <a class="ghost-link" href="<?= h(aza_memory_query_href($memoryBaseQuery, ['view' => 'source'])) ?>">Voir les provenances</a>
            </div>
        </section>

        <nav class="aza-memory-nav" aria-label="Modes de lecture mémoire">
            <?php foreach ($memoryViews as $viewKey => $viewLabel): ?>
                <a
                    class="aza-memory-nav-link<?= $memoryView === $viewKey ? ' is-active' : '' ?>"
                    href="<?= h(aza_memory_query_href($memoryBaseQuery, ['view' => $viewKey, 'q' => $viewKey === 'finder' ? $finderQuery : null, 'kind' => $viewKey === 'finder' ? $finderKind : null, 'family' => $viewKey === 'finder' ? $finderFamily : null, 'source' => $viewKey === 'finder' ? $finderSource : null, 'sort' => $viewKey === 'finder' ? $finderSort : null])) ?>"
                >
                    <?= h($viewLabel) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($memoryView === 'chrono'): ?>
            <?php if ($sortedArchives): ?>
                <div class="summary-grid aza-chronology-summary">
                    <article class="summary-card">
                        <span class="summary-label">Volume</span>
                        <strong class="summary-value summary-value-small"><?= h((string) $chronoSummary['count']) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Première trace</span>
                        <strong class="summary-value summary-value-small"><?= h((string) ($chronoSummary['first_trace'] ?? '—')) ?></strong>
                    </article>
                    <article class="summary-card">
                        <span class="summary-label">Dernière trace</span>
                        <strong class="summary-value summary-value-small"><?= h((string) ($chronoSummary['last_trace'] ?? '—')) ?></strong>
                    </article>
                </div>
            <?php endif; ?>

            <?php if (!$sortedArchives): ?>
                <p class="panel-copy">La mémoire ZIP est vierge.</p>
            <?php else: ?>
                <div class="aza-timeline" aria-label="Chronologie des archives">
                    <div class="aza-timeline-track">
                        <?php foreach ($groupedArchives as $bucket => $archives): ?>
                            <section class="aza-timeline-group aza-timeline-bucket">
                                <header class="aza-timeline-head">
                                    <p class="aza-timeline-year bucket-marker"><?= h((string) $bucket) ?></p>
                                    <p class="panel-copy aza-timeline-count"><?= count($archives) ?> archive<?= count($archives) > 1 ? 's' : '' ?></p>
                                </header>
                                <div class="aza-archive-grid aza-timeline-grid bucket-content">
                                    <?php foreach ($archives as $archive): ?>
                                        <article class="summary-card aza-archive-card">
                                            <header class="aza-archive-chronology card-header">
                                                <div>
                                                    <span class="summary-label"><?= h((string) ($sources[$archive['source']] ?? $archive['source'])) ?></span>
                                                    <strong class="summary-value summary-value-small"><?= h((string) $archive['label']) ?></strong>
                                                </div>
                                                <span class="meta-pill aza-chronology-pill card-date" title="<?= h((string) ($archive['chronology_origin_label'] ?? 'Date de dépôt')) ?>">
                                                    <?= h((string) ($archive['chronology_label'] ?? 'Atemporel')) ?>
                                                </span>
                                            </header>
                                            <div class="aza-meta-list card-meta">
                                                <span>Déposé le : <?= h(human_created_label((string) ($archive['created_at'] ?? '')) ?? 'maintenant') ?></span>
                                                <span>Entrées : <?= h((string) ($archive['entries'] ?? 0)) ?></span>
                                                <?php if (!empty($archive['media_entries'])): ?>
                                                    <span>Médias : <?= h((string) $archive['media_entries']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($archive['owner_slug'])): ?>
                                                    <span>Terre : <?= h((string) $archive['owner_slug']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="panel-copy aza-chronology-copy">Chronologie : <?= h((string) ($archive['chronology_origin_label'] ?? 'Date de dépôt')) ?></p>
                                            <?php if (!empty($archive['human_summary'])): ?>
                                                <p class="land-note aza-summary card-summary"><?= h((string) $archive['human_summary']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['memory_note'])): ?>
                                                <p class="panel-copy aza-memory-note"><?= h((string) $archive['memory_note']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['memory_types']) && is_array($archive['memory_types'])): ?>
                                                <div class="aza-meta-list">
                                                    <?php foreach ($archive['memory_types'] as $type): ?>
                                                        <span class="meta-pill aza-memory-pill"><?= h(aza_label_memory_type((string) $type)) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['content_families']) && is_array($archive['content_families'])): ?>
                                                <div class="aza-meta-list">
                                                    <?php foreach ($archive['content_families'] as $family): ?>
                                                        <span class="meta-pill"><?= h(aza_label_content_family((string) $family)) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['years']) && is_array($archive['years'])): ?>
                                                <p class="panel-copy">Repères temporels : <?= h(implode(' · ', array_map('strval', $archive['years']))) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['markers']) && is_array($archive['markers'])): ?>
                                                <div class="preview-grid aza-markers">
                                                    <?php foreach ($archive['markers'] as $marker): ?>
                                                        <p><span>Repère</span><code><?= h((string) $marker) ?></code></p>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['notes'])): ?>
                                                <p class="land-note"><?= h((string) $archive['notes']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['top_folders']) && is_array($archive['top_folders'])): ?>
                                                <div class="preview-grid aza-folders">
                                                    <?php foreach ($archive['top_folders'] as $folder => $count): ?>
                                                        <p><span><?= h((string) $folder) ?></span><code><?= h((string) $count) ?> entrées</code></p>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($archive['sample_paths']) && is_array($archive['sample_paths'])): ?>
                                                <details class="aza-details">
                                                    <summary>Voir quelques chemins</summary>
                                                    <pre><?= h(implode("\n", array_slice($archive['sample_paths'], 0, 8))) ?></pre>
                                                </details>
                                            <?php endif; ?>
                                            <div class="card-actions aza-meta-list">
                                                <a class="meta-pill aza-download btn-download" href="/<?= h((string) $archive['stored_file']) ?>" download>Extraire la trace</a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($memoryView === 'family'): ?>
            <?php if (!$filesByFamily): ?>
                <p class="panel-copy">Aucun fichier libre à grouper par famille.</p>
            <?php else: ?>
                <?php foreach ($filesByFamily as $group): ?>
                    <section class="aza-family-group aza-memory-group">
                        <header class="aza-memory-group-head">
                            <h3 class="aza-family-label"><?= h($group['label']) ?></h3>
                            <span class="meta-pill"><?= count($group['items']) ?> fichier<?= count($group['items']) > 1 ? 's' : '' ?></span>
                        </header>
                        <div class="aza-files-grid">
                            <?php foreach ($group['items'] as $file): ?>
                                <article class="aza-file-card">
                                    <?php if (!empty($file['thumbnail'])): ?>
                                        <div class="aza-file-thumb">
                                            <img src="/<?= h((string) $file['thumbnail']) ?>" alt="<?= h((string) $file['label']) ?>" loading="lazy">
                                        </div>
                                    <?php else: ?>
                                        <div class="aza-file-thumb aza-file-thumb-blank">
                                            <span><?= h(strtoupper((string) $file['format'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="aza-file-meta">
                                        <strong class="aza-file-name"><?= h((string) $file['label']) ?></strong>
                                        <span class="aza-file-detail"><?= h(aza_format_bytes((int) $file['size'])) ?></span>
                                        <?php if (!empty($file['date_hint'])): ?>
                                            <span class="aza-file-detail"><?= h((string) $file['date_hint']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($file['notes'])): ?>
                                            <p class="aza-file-notes"><?= h((string) $file['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($memoryView === 'format'): ?>
            <?php if (!$filesByFormat): ?>
                <p class="panel-copy">Aucun fichier libre à grouper par format.</p>
            <?php else: ?>
                <?php foreach ($filesByFormat as $group): ?>
                    <section class="aza-family-group aza-memory-group">
                        <header class="aza-memory-group-head">
                            <h3 class="aza-family-label"><?= h($group['label']) ?></h3>
                            <span class="meta-pill"><?= count($group['items']) ?> fichier<?= count($group['items']) > 1 ? 's' : '' ?></span>
                        </header>
                        <div class="aza-files-grid">
                            <?php foreach ($group['items'] as $file): ?>
                                <article class="aza-file-card">
                                    <?php if (!empty($file['thumbnail'])): ?>
                                        <div class="aza-file-thumb">
                                            <img src="/<?= h((string) $file['thumbnail']) ?>" alt="<?= h((string) $file['label']) ?>" loading="lazy">
                                        </div>
                                    <?php else: ?>
                                        <div class="aza-file-thumb aza-file-thumb-blank">
                                            <span><?= h(strtoupper((string) $file['format'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="aza-file-meta">
                                        <strong class="aza-file-name"><?= h((string) $file['label']) ?></strong>
                                        <span class="aza-file-detail"><?= h(aza_ingest_family_label((string) $file['format_family'])) ?></span>
                                        <span class="aza-file-detail"><?= h(aza_format_bytes((int) $file['size'])) ?></span>
                                        <?php if (!empty($file['notes'])): ?>
                                            <p class="aza-file-notes"><?= h((string) $file['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($memoryView === 'size'): ?>
            <?php if (!$filesBySize): ?>
                <p class="panel-copy">Aucun fichier libre à grouper par taille.</p>
            <?php else: ?>
                <?php foreach ($filesBySize as $group): ?>
                    <section class="aza-family-group aza-memory-group">
                        <header class="aza-memory-group-head">
                            <h3 class="aza-family-label"><?= h($group['label']) ?></h3>
                            <span class="meta-pill"><?= count($group['items']) ?> fichier<?= count($group['items']) > 1 ? 's' : '' ?></span>
                        </header>
                        <div class="aza-files-grid">
                            <?php foreach ($group['items'] as $file): ?>
                                <article class="aza-file-card">
                                    <?php if (!empty($file['thumbnail'])): ?>
                                        <div class="aza-file-thumb">
                                            <img src="/<?= h((string) $file['thumbnail']) ?>" alt="<?= h((string) $file['label']) ?>" loading="lazy">
                                        </div>
                                    <?php else: ?>
                                        <div class="aza-file-thumb aza-file-thumb-blank">
                                            <span><?= h(strtoupper((string) $file['format'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="aza-file-meta">
                                        <strong class="aza-file-name"><?= h((string) $file['label']) ?></strong>
                                        <span class="aza-file-detail"><?= h(aza_format_bytes((int) $file['size'])) ?></span>
                                        <span class="aza-file-detail"><?= h(strtoupper((string) $file['format'])) ?> · <?= h(aza_ingest_family_label((string) $file['format_family'])) ?></span>
                                        <?php if (!empty($file['notes'])): ?>
                                            <p class="aza-file-notes"><?= h((string) $file['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($memoryView === 'source'): ?>
            <?php if (!$memorySourceGroups): ?>
                <p class="panel-copy">Aucune provenance mémoire disponible.</p>
            <?php else: ?>
                <?php foreach ($memorySourceGroups as $group): ?>
                    <section class="aza-family-group aza-memory-group">
                        <header class="aza-memory-group-head">
                            <h3 class="aza-family-label"><?= h($group['label']) ?></h3>
                            <span class="meta-pill"><?= count($group['items']) ?> trace<?= count($group['items']) > 1 ? 's' : '' ?></span>
                        </header>
                        <div class="aza-finder-grid aza-source-grid">
                            <?php foreach (array_slice($group['items'], 0, 8) as $item): ?>
                                <article class="aza-finder-card aza-finder-card-compact">
                                    <div class="aza-file-thumb aza-finder-thumb aza-file-thumb-blank <?= $item['thumbnail_url'] !== '' ? '' : 'aza-finder-thumb-blank' ?>">
                                        <?php if ($item['thumbnail_url'] !== ''): ?>
                                            <img src="<?= h($item['thumbnail_url']) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <span><?= h($item['format_label'] !== '' ? $item['format_label'] : $item['kind_label']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aza-finder-meta">
                                        <strong class="aza-finder-title"><?= h($item['title']) ?></strong>
                                        <p class="aza-finder-summary"><?= h($item['summary'] !== '' ? $item['summary'] : $item['date_label']) ?></p>
                                        <div class="aza-meta-list aza-finder-details">
                                            <span><?= h($item['kind_label']) ?></span>
                                            <span><?= h($item['date_label']) ?></span>
                                            <?php if ($item['owner_slug'] !== ''): ?><span>Terre : <?= h($item['owner_slug']) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($memoryView === 'visual'): ?>
            <?php if (!$memoryVisualItems): ?>
                <p class="panel-copy">Aucune trace visuelle repérée pour le moment.</p>
            <?php else: ?>
                <div class="aza-visual-shell">
                    <?php foreach ($memoryVisualItems as $item): ?>
                        <article class="aza-visual-card">
                            <?php if ($item['thumbnail_url'] !== ''): ?>
                                <div class="aza-visual-thumb">
                                    <img src="<?= h($item['thumbnail_url']) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="aza-visual-thumb aza-file-thumb-blank">
                                    <span><?= h($item['format_label'] !== '' ? $item['format_label'] : $item['kind_label']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="aza-visual-meta">
                                <div class="aza-meta-list">
                                    <span class="meta-pill"><?= h($item['kind_label']) ?></span>
                                    <span class="meta-pill"><?= h($item['source_label']) ?></span>
                                </div>
                                <strong class="aza-finder-title"><?= h($item['title']) ?></strong>
                                <?php if ($item['summary'] !== ''): ?>
                                    <p class="aza-finder-summary"><?= h($item['summary']) ?></p>
                                <?php endif; ?>
                                <div class="aza-meta-list aza-finder-details">
                                    <span><?= h($item['date_label']) ?></span>
                                    <?php foreach ($item['families_labels'] as $familyLabel): ?>
                                        <span><?= h($familyLabel) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($item['href'] !== ''): ?>
                                    <div class="card-actions aza-meta-list">
                                        <a class="meta-pill aza-download btn-download" href="<?= h($item['href']) ?>" download>Télécharger</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form class="aza-finder-toolbar" method="get" action="/aza" role="search" aria-label="Rechercher dans la mémoire">
                <?php if ($form['owner_slug'] !== ''): ?>
                    <input type="hidden" name="u" value="<?= h($form['owner_slug']) ?>">
                <?php endif; ?>
                <input type="hidden" name="view" value="finder">
                <label>
                    Recherche
                    <input type="search" name="q" value="<?= h($finderQuery) ?>" placeholder="titre, note, terre, format, repère…">
                </label>
                <label>
                    Source
                    <select name="kind">
                        <option value="all" <?= $finderKind === 'all' ? 'selected' : '' ?>>Tout</option>
                        <option value="archive" <?= $finderKind === 'archive' ? 'selected' : '' ?>>Archives ZIP</option>
                        <option value="file" <?= $finderKind === 'file' ? 'selected' : '' ?>>Fichiers libres</option>
                    </select>
                </label>
                <label>
                    Famille
                    <select name="family">
                        <option value="all" <?= $finderFamily === 'all' ? 'selected' : '' ?>>Toutes</option>
                        <?php foreach ($finderFamilyOptions as $familyKey => $familyLabel): ?>
                            <option value="<?= h($familyKey) ?>" <?= $finderFamily === $familyKey ? 'selected' : '' ?>><?= h($familyLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Provenance
                    <select name="source">
                        <option value="all" <?= $finderSource === 'all' ? 'selected' : '' ?>>Toutes</option>
                        <?php foreach ($finderSourceOptions as $sourceKey => $sourceLabel): ?>
                            <option value="<?= h($sourceKey) ?>" <?= $finderSource === $sourceKey ? 'selected' : '' ?>><?= h($sourceLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Tri
                    <select name="sort">
                        <option value="newest" <?= $finderSort === 'newest' ? 'selected' : '' ?>>Plus récent</option>
                        <option value="oldest" <?= $finderSort === 'oldest' ? 'selected' : '' ?>>Plus ancien</option>
                        <option value="title" <?= $finderSort === 'title' ? 'selected' : '' ?>>Titre</option>
                        <option value="size_desc" <?= $finderSort === 'size_desc' ? 'selected' : '' ?>>Taille ↓</option>
                        <option value="size_asc" <?= $finderSort === 'size_asc' ? 'selected' : '' ?>>Taille ↑</option>
                    </select>
                </label>
                <div class="aza-finder-actions">
                    <button type="submit">Filtrer</button>
                    <a class="ghost-link" href="<?= h(aza_memory_query_href($memoryBaseQuery, ['view' => 'finder', 'q' => null, 'kind' => null, 'family' => null, 'source' => null, 'sort' => null])) ?>">Réinitialiser</a>
                </div>
            </form>

            <div class="aza-stats aza-finder-stats" aria-label="Résultats finder">
                <span>Résultats : <?= h((string) count($filteredFinderItems)) ?></span>
                <span class="separator" aria-hidden="true">|</span>
                <span>Archives + fichiers</span>
            </div>

            <?php if (!$filteredFinderItems): ?>
                <p class="panel-copy">Aucun item ne correspond à cette lecture.</p>
            <?php else: ?>
                <div class="aza-finder-layout">
                    <aside class="aza-finder-preview" aria-label="Aperçu mémoire">
                        <?php if (is_array($finderPreviewItem)): ?>
                            <p class="summary-label">Aperçu</p>
                            <strong class="aza-finder-title"><?= h((string) $finderPreviewItem['title']) ?></strong>
                            <?php if (($finderPreviewItem['thumbnail_url'] ?? '') !== ''): ?>
                                <div class="aza-file-thumb aza-finder-preview-thumb">
                                    <img src="<?= h((string) $finderPreviewItem['thumbnail_url']) ?>" alt="<?= h((string) $finderPreviewItem['title']) ?>" loading="lazy">
                                </div>
                            <?php endif; ?>
                            <div class="aza-meta-list aza-finder-details">
                                <span><?= h((string) $finderPreviewItem['kind_label']) ?></span>
                                <span><?= h((string) $finderPreviewItem['source_label']) ?></span>
                                <span><?= h((string) $finderPreviewItem['date_label']) ?></span>
                            </div>
                            <?php if (($finderPreviewItem['summary'] ?? '') !== ''): ?>
                                <p class="aza-finder-summary"><?= h((string) $finderPreviewItem['summary']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($finderPreviewItem['families_labels']) && is_array($finderPreviewItem['families_labels'])): ?>
                                <div class="aza-meta-list aza-finder-details">
                                    <?php foreach ($finderPreviewItem['families_labels'] as $familyLabel): ?>
                                        <span><?= h((string) $familyLabel) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </aside>
                    <div class="aza-finder-grid">
                    <?php foreach ($filteredFinderItems as $item): ?>
                        <article class="aza-finder-card">
                            <?php if ($item['thumbnail_url'] !== ''): ?>
                                <div class="aza-file-thumb aza-finder-thumb">
                                    <img src="<?= h($item['thumbnail_url']) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="aza-file-thumb aza-file-thumb-blank aza-finder-thumb aza-finder-thumb-blank">
                                    <span><?= h($item['format_label'] !== '' ? $item['format_label'] : $item['kind_label']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="aza-finder-meta">
                                <div class="aza-meta-list">
                                    <span class="meta-pill"><?= h($item['kind_label']) ?></span>
                                    <span class="meta-pill"><?= h($item['meta_label']) ?></span>
                                    <?php if ($item['owner_slug'] !== ''): ?>
                                        <span class="meta-pill">Terre : <?= h($item['owner_slug']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <strong class="aza-finder-title"><?= h($item['title']) ?></strong>
                                <?php if ($item['summary'] !== ''): ?>
                                    <p class="aza-finder-summary"><?= h($item['summary']) ?></p>
                                <?php endif; ?>
                                <div class="aza-meta-list aza-finder-details">
                                    <?php if ($item['date_label'] !== ''): ?>
                                        <span><?= h($item['date_label']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['size_label'] !== ''): ?>
                                        <span><?= h($item['size_label']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['date_origin'])): ?>
                                        <span><?= h((string) $item['date_origin']) ?></span>
                                    <?php endif; ?>
                                    <?php foreach ($item['families_labels'] as $familyLabel): ?>
                                        <span><?= h($familyLabel) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($item['href'] !== ''): ?>
                                    <div class="card-actions aza-meta-list">
                                        <a class="meta-pill aza-download btn-download" href="<?= h($item['href']) ?>" download>Télécharger</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
