<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/aza.php');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$csrfToken = csrf_token();
$brandDomain = preg_replace('/^www\./', '', $host ?: 'sowwwl.com');
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$ownerSlug = trim((string) ($_GET['u'] ?? $_POST['owner_slug'] ?? ''));
$ownerLand = null;
$directHost = aza_direct_host();
$isDirectRequest = aza_is_direct_request($host);

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

$message = '';
$messageType = 'info';
$imported = null;
$form = [
    'owner_slug' => $ownerSlug,
    'label' => '',
    'source_hint' => 'auto',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['owner_slug'] = trim((string) ($_POST['owner_slug'] ?? ''));
    $form['label'] = trim((string) ($_POST['label'] ?? ''));
    $form['source_hint'] = trim((string) ($_POST['source_hint'] ?? 'auto'));
    $form['notes'] = trim((string) ($_POST['notes'] ?? ''));
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');

    if (!verify_csrf_token($csrfCandidate)) {
        $message = 'Session expirée. Recharge la page et réessaie.';
        $messageType = 'warning';
    } elseif (!isset($_FILES['archive_zip'])) {
        $message = 'Choisis une archive ZIP à déposer.';
        $messageType = 'warning';
    } else {
        try {
            $imported = aza_import_zip_archive($_FILES['archive_zip'], $form);
            $form['owner_slug'] = (string) ($imported['owner_slug'] ?? $form['owner_slug']);
            $message = 'Archive déposée. aZa garde le ZIP et résume sa structure.';
            $messageType = 'success';
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $messageType = 'warning';
        }
    }
}

$rawArchives = $form['owner_slug'] !== ''
    ? aza_list_archives($form['owner_slug'])
    : get_all_aza_archives();
$chronology = aza_prepare_chronology($rawArchives);
$sortedArchives = $chronology['sorted'];
$groupedArchives = $chronology['grouped'];
$chronoSummary = $chronology['summary'];
$sources = aza_supported_sources();
$directUploadUrl = aza_direct_upload_url($form['owner_slug'] !== '' ? $form['owner_slug'] : $ownerSlug);
$ambientLand = $ownerLand ?: current_authenticated_land();
$ambientProfile = $ambientLand ? land_visual_profile($ambientLand) : land_collective_profile('nocturnal');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="aZa — importer des archives ZIP de réseaux existants pour en faire une archive personnelle légère sur <?= h($brandDomain) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>aZa — archive légère — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience aza-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'aza') ?>

<main class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>aZa</strong> <span>mémoire légère</span></p>
        <h1 class="land-title">
            <strong>Déposer sans algorithme.</strong>
            <span>I inverse</span>
        </h1>
        <p class="lead">
            Déposer ce qui compte, sans rejouer le bruit.
        </p>

        <div class="land-meta">
            <span class="meta-pill">ZIP seulement</span>
            <span class="meta-pill"><?= h(aza_format_bytes(AZA_MAX_UPLOAD_BYTES)) ?> max côté app</span>
            <span class="meta-pill">archive légère</span>
            <?php if ($isDirectRequest): ?>
                <span class="meta-pill aza-direct-pill">entrée directe active<?= $directHost ? ' · ' . h($directHost) : '' ?></span>
            <?php elseif ($directUploadUrl): ?>
                <a class="meta-pill meta-pill-link aza-direct-link" href="<?= h($directUploadUrl) ?>">gros ZIP : entrée directe</a>
            <?php endif; ?>
            <?php if ($ownerLand): ?>
                <span class="meta-pill">terre liée : <?= h((string) $ownerLand['slug']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($directUploadUrl): ?>
            <p class="panel-copy aza-direct-copy">
                <?php if ($isDirectRequest): ?>
                    Entrée directe active.
                <?php else: ?>
                    Très gros ZIP&nbsp;:
                    <a class="ghost-link" href="<?= h($directUploadUrl) ?>">ouvrir <?= h($directHost ?? 'l’entrée directe') ?></a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <section class="panel-shell aza-shell">
        <section class="panel reveal" aria-labelledby="aza-import-title">
            <div class="section-topline">
                <div>
                    <h2 id="aza-import-title">Sédimentation</h2>
                    <p class="panel-copy">Un ZIP entre, une mémoire se forme.</p>
                </div>
                <a class="ghost-link" href="<?= $form['owner_slug'] !== '' ? '/land.php?u=' . rawurlencode($form['owner_slug']) : '/' ?>">
                    <?= $form['owner_slug'] !== '' ? 'Retour à la terre' : 'Retour au noyau' ?>
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                    <p><?= h($message) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="land-form aza-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                <label>
                    Terre liée
                    <input type="text" name="owner_slug" placeholder="ex: nox" value="<?= h($form['owner_slug']) ?>">
                    <span class="input-hint">Optionnel.</span>
                </label>

                <label>
                    Nom de l’archive
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
                        <span class="input-hint">Pour un très gros ZIP, utilise <a class="ghost-link" href="<?= h($directUploadUrl) ?>">l’entrée directe</a>.</span>
                    <?php endif; ?>
                </label>

                <label>
                    Note de contexte
                    <textarea name="notes" rows="4" placeholder="Contexte, provenance, repères utiles."><?= h($form['notes']) ?></textarea>
                </label>

                <button type="submit">Sédimenter l'archive</button>
            </form>

            <?php if ($imported): ?>
                <div class="signup-preview aza-preview">
                    <span class="summary-label">Dernier dépôt</span>
                    <strong class="preview-title"><?= h((string) $imported['label']) ?></strong>
                    <?php if (!empty($imported['human_summary'])): ?>
                        <p class="land-note"><?= h((string) $imported['human_summary']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($imported['memory_note'])): ?>
                        <p class="panel-copy aza-memory-note"><?= h((string) $imported['memory_note']) ?></p>
                    <?php endif; ?>
                    <div class="preview-grid">
                        <p><span>Source</span><code><?= h((string) $sources[$imported['source']] ?? (string) $imported['source']) ?></code></p>
                        <p><span>Entrées repérées</span><code><?= h((string) $imported['entries']) ?></code></p>
                        <p><span>Fichier ZIP</span><code>/<?= h((string) $imported['stored_file']) ?></code></p>
                        <?php if (!empty($imported['years']) && is_array($imported['years'])): ?>
                            <p><span>Repères temporels</span><code><?= h(implode(' · ', array_map('strval', $imported['years']))) ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <aside class="panel reveal" aria-labelledby="aza-principles-title">
            <h2 id="aza-principles-title">Principe</h2>
            <p class="panel-copy">Ni fil, ni score : une mémoire qu’on peut relire.</p>
            <div class="summary-grid aza-principles-grid">
                <article class="summary-card">
                    <span class="summary-label">01</span>
                    <strong class="summary-value summary-value-small">ZIP</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">02</span>
                    <strong class="summary-value summary-value-small">Index</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">03</span>
                    <strong class="summary-value summary-value-small">Trace</strong>
                </article>
            </div>
        </aside>
    </section>

    <section class="panel reveal" aria-labelledby="aza-list-title">
        <div class="section-topline aza-timeline-header">
            <div>
                <h2 id="aza-list-title">Chronologie</h2>
                <p class="panel-copy">
                    <?= $form['owner_slug'] !== ''
                        ? 'Filtre : ' . h($form['owner_slug']) . ' · la mémoire se resserre sur cette terre.'
                        : 'La mémoire prend date, puis distance.' ?>
                </p>
            </div>
            <div class="aza-stats" aria-label="Statistiques temporelles">
                <span>Volume : <?= h((string) $chronoSummary['count']) ?> archive<?= $chronoSummary['count'] > 1 ? 's' : '' ?></span>
                <?php if (!empty($chronoSummary['first_trace'])): ?>
                    <span class="separator" aria-hidden="true">|</span>
                    <span>Amplitude : <?= h((string) $chronoSummary['first_trace']) ?> → <?= h((string) ($chronoSummary['last_trace'] ?? $chronoSummary['first_trace'])) ?></span>
                <?php endif; ?>
            </div>
        </div>

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
            <p class="panel-copy">La mémoire est vierge.</p>
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
    </section>
</main>
</body>
</html>
