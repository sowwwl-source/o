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

$pickIslandItem = static function (array $items, callable $predicate): ?array {
    foreach ($items as $item) {
        if ($predicate($item)) {
            return $item;
        }
    }

    return null;
};

$islandPreviewText = static function (?string $publicPath, int $maxBytes = 16000, int $maxChars = 1800): string {
    $absolutePath = aza_absolute_storage_path($publicPath);
    if (!is_string($absolutePath) || $absolutePath === '' || !is_file($absolutePath) || !is_readable($absolutePath)) {
        return '';
    }

    $raw = @file_get_contents($absolutePath, false, null, 0, $maxBytes);
    if (!is_string($raw) || $raw === '') {
        return '';
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
    $normalized = trim($normalized);
    if ($normalized === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        $normalized = mb_substr($normalized, 0, $maxChars, 'UTF-8');
    } else {
        $normalized = substr($normalized, 0, $maxChars);
    }

    return $normalized;
};

$buildDesignFallbackLabel = static function (?array $item): string {
    if (!is_array($item)) {
        return 'Design';
    }

    $format = trim((string) ($item['format_label'] ?? ''));
    if ($format !== '') {
        return strtoupper($format);
    }

    return trim((string) ($item['kind_label'] ?? 'Design')) ?: 'Design';
};

$isTextPreviewableFormat = static function (?array $item): bool {
    if (!is_array($item)) {
        return false;
    }

    $format = strtolower(trim((string) ($item['format_label'] ?? '')));
    return in_array($format, ['txt', 'md', 'rtf', 'html', 'htm'], true);
};

$isBrowserPlayableVideoFormat = static function (?array $item): bool {
    if (!is_array($item)) {
        return false;
    }

    $format = strtolower(trim((string) ($item['format_label'] ?? '')));
    return in_array($format, ['mp4', 'webm', 'ogv', 'm4v'], true);
};

$islandAudioItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('audio', $item['families'] ?? [], true));
$islandImageItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('image', $item['families'] ?? [], true) && strtolower((string) ($item['format_label'] ?? '')) !== 'SVG');
$islandVideoItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('video', $item['families'] ?? [], true) && $isBrowserPlayableVideoFormat($item));
$islandVideoFallbackItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('video', $item['families'] ?? [], true));
if ($islandVideoItem === null) {
    $islandVideoItem = $islandVideoFallbackItem;
}
$islandPdfItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('document', $item['families'] ?? [], true) && strtolower((string) ($item['format_label'] ?? '')) === 'pdf');
$islandTextItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('document', $item['families'] ?? [], true) && strtolower((string) ($item['format_label'] ?? '')) !== 'pdf');
$islandSvgItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('image', $item['families'] ?? [], true) && strtolower((string) ($item['format_label'] ?? '')) === 'svg');
$islandDataItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('data', $item['families'] ?? [], true));
$islandDesignItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('design', $item['families'] ?? [], true));
$islandModelItem = $pickIslandItem($islandItems, static fn (array $item): bool => in_array('3d', $item['families'] ?? [], true));
$islandArchiveItem = $pickIslandItem($islandItems, static fn (array $item): bool => (string) ($item['kind'] ?? '') === 'archive');

$islandDataPreview = $islandDataItem ? $islandPreviewText((string) ($islandDataItem['href'] ?? '')) : '';
$islandDesignPreview = $islandDesignItem ? $islandPreviewText((string) ($islandDesignItem['href'] ?? ''), 8000, 640) : '';
$islandTextPreview = ($islandTextItem && $isTextPreviewableFormat($islandTextItem))
    ? $islandPreviewText((string) ($islandTextItem['href'] ?? ''), 16000, 2600)
    : '';

$buildIslandReaderStatus = static function (string $mode, string $state, string $lead, string $copy): array {
    return [
        'mode' => $mode,
        'state' => $state,
        'lead' => $lead,
        'copy' => $copy,
    ];
};

$buildIslandReader = static function (string $key, string $label, string $subtitle, ?array $item): array {
    $isAvailable = is_array($item) && !empty($item['href']);
    $href = $isAvailable ? (string) ($item['href'] ?? '') : '';
    $format = strtoupper((string) ($item['format_label'] ?? $key));

    return [
        'key' => $key,
        'label' => $label,
        'subtitle' => $subtitle,
        'available' => $isAvailable,
        'item' => $item,
        'title' => $isAvailable ? (string) ($item['title'] ?? $label) : $label . ' en veille',
        'summary' => $isAvailable
            ? trim((string) (($item['summary'] ?? '') ?: ($item['date_label'] ?? '') ?: $subtitle))
            : 'Aucune trace de ce type dans l’île pour le moment.',
        'href' => $href,
        'thumbnail' => $isAvailable ? (string) ($item['thumbnail_url'] ?? '') : '',
        'format' => $format,
        'source_label' => $isAvailable ? (string) ($item['source_label'] ?? 'Mémoire') : 'Veille',
        'date_label' => $isAvailable ? (string) ($item['date_label'] ?? 'Atemporel') : 'En attente',
    ];
};

$islandReaders = [
    $buildIslandReader('audio', 'Audio', 'Écoute orbitale, vitesse et égalisation intégrées.', $islandAudioItem),
    $buildIslandReader('image', 'Image', 'Surface visuelle haute présence, focus et extraction.', $islandImageItem),
    $buildIslandReader('video', 'Vidéo', 'Lecture vidéo embarquée, directe et située.', $islandVideoItem),
    $buildIslandReader('pdf', 'PDF', 'Lecture documentaire intégrée, page visible sans quitter l’île.', $islandPdfItem),
    $buildIslandReader('text', 'Texte', 'Lecture éditoriale et markdown dans la continuité de l’île.', $islandTextItem),
    $buildIslandReader('svg', 'SVG', 'Lecture vectorielle nette, zoomable et fidèle au tracé.', $islandSvgItem),
    $buildIslandReader('data', 'Data', 'Lecture brute des structures JSON, CSV, XML ou YAML.', $islandDataItem),
    $buildIslandReader('design', 'Design', 'Prévisualisation et extraction des sources de design.', $islandDesignItem),
    $buildIslandReader('model', '3D', 'Objet navigable ou fallback détaillé selon le format disponible.', $islandModelItem),
    $buildIslandReader('archive', 'ZIP', 'Exploration synthétique d’une archive mémoire et de ses strates.', $islandArchiveItem),
];

$activeIslandReader = 'audio';
foreach ($islandReaders as $reader) {
    if ($reader['available']) {
        $activeIslandReader = (string) $reader['key'];
        break;
    }
}

$islandReaderCount = count(array_filter($islandReaders, static fn (array $reader): bool => !empty($reader['available'])));
$islandDormantReaderCount = count($islandReaders) - $islandReaderCount;
$availableIslandReaders = array_values(array_filter($islandReaders, static fn (array $reader): bool => !empty($reader['available'])));
$activeIslandReaderIndex = 0;
foreach ($availableIslandReaders as $index => $reader) {
    if ((string) ($reader['key'] ?? '') === $activeIslandReader) {
        $activeIslandReaderIndex = $index;
        break;
    }
}
$activeIslandReaderData = $availableIslandReaders[$activeIslandReaderIndex] ?? ($islandReaders[0] ?? null);
$islandReaderSequenceCount = count($availableIslandReaders);
$nextIslandReaderData = $islandReaderSequenceCount > 0
    ? $availableIslandReaders[($activeIslandReaderIndex + 1) % $islandReaderSequenceCount]
    : null;
$previousIslandReaderData = $islandReaderSequenceCount > 0
    ? $availableIslandReaders[($activeIslandReaderIndex - 1 + $islandReaderSequenceCount) % $islandReaderSequenceCount]
    : null;
$islandCuratorProgressLabel = $islandReaderSequenceCount > 0
    ? str_pad((string) ($activeIslandReaderIndex + 1), 2, '0', STR_PAD_LEFT) . ' / ' . str_pad((string) $islandReaderSequenceCount, 2, '0', STR_PAD_LEFT)
    : '00 / 00';
$islandCuratorRecommendation = is_array($nextIslandReaderData)
    ? 'Ensuite : ' . (string) ($nextIslandReaderData['label'] ?? 'matière') . ' · ' . (string) ($nextIslandReaderData['format'] ?? 'format')
    : 'Aucune matière active recommandée pour l’instant.';
$islandModelFormat = strtolower((string) (($islandModelItem['format_label'] ?? '')));
$islandNeedsModelViewer = in_array($islandModelFormat, ['glb', 'gltf'], true);
$islandVideoItems = array_values(array_filter(
    $islandItems,
    static fn (array $item): bool => in_array('video', $item['families'] ?? [], true)
));
$islandVideoPoster = $islandVideoItem ? (string) (($islandVideoItem['thumbnail_url'] ?? '') ?: '') : '';
$islandVideoFormat = strtolower((string) (($islandVideoItem['format_label'] ?? '')));
$islandVideoIsBrowserPlayable = $isBrowserPlayableVideoFormat($islandVideoItem);
$islandVideoItemCount = count($islandVideoItems);
$islandVideoPlayableCount = count(array_filter($islandVideoItems, static fn (array $item): bool => $isBrowserPlayableVideoFormat($item)));
$islandVideoNonPlayableCount = $islandVideoItemCount - $islandVideoPlayableCount;
$islandVideoCompatibilityLabel = $islandVideoIsBrowserPlayable ? 'lecture web native' : 'lecture externe requise';
$islandVideoSelectionLabel = $islandVideoIsBrowserPlayable ? 'format priorisé' : 'fallback vidéo';
$islandVideoSelectionNote = 'Lecture vidéo directe dans l’île, sans rupture de navigation.';
if ($islandVideoIsBrowserPlayable && $islandVideoNonPlayableCount > 0) {
    $islandVideoSelectionNote = 'Cette île contient aussi un format moins lisible dans le navigateur. La station active ici la variante la plus compatible pour une lecture immédiate.';
} elseif ($islandVideoIsBrowserPlayable && $islandVideoItemCount > 1) {
    $islandVideoSelectionNote = 'Plusieurs traces vidéo coexistent sur l’île. La station conserve la variante la plus lisible côté navigateur.';
} elseif (!$islandVideoIsBrowserPlayable && $islandVideoItem !== null) {
    $islandVideoSelectionNote = 'Aucun format vidéo web-compatible n’a été repéré dans cette île. La trace reste accessible, mais doit être ouverte hors du lecteur intégré ou convertie.';
}
$islandVideoStatusCopy = $islandVideoIsBrowserPlayable
    ? 'Le lecteur intégré utilise directement le format ' . strtoupper($islandVideoFormat !== '' ? $islandVideoFormat : 'vidéo') . '.'
    : 'Le format ' . strtoupper($islandVideoFormat !== '' ? $islandVideoFormat : 'vidéo') . ' n’est pas garanti en lecture inline sur navigateur.';
$islandTextStatus = $buildIslandReaderStatus(
    $islandTextPreview !== '' ? 'prévisualisation textuelle' : 'extraction seule',
    $islandTextPreview !== '' ? 'lecture continue' : 'conversion requise',
    $islandTextPreview !== ''
        ? 'Le lecteur texte affiche un extrait directement dans l’île.'
        : 'Le document texte existe, mais il ne peut pas être déroulé ici en lecture continue.',
    $islandTextPreview !== ''
        ? 'Quand le format est lisible sans ambiguïté, la station garde une lecture éditoriale inline avec extraction disponible en parallèle.'
        : 'La trace reste accessible, mais son format ou son encodage demande une ouverture externe pour une lecture fiable.'
);
$islandDataStatus = $buildIslandReaderStatus(
    $islandDataPreview !== '' ? 'aperçu brut' : 'extraction seule',
    $islandDataPreview !== '' ? 'structure lisible' : 'prévisualisation indisponible',
    $islandDataPreview !== ''
        ? 'Le lecteur data a pu prélever un extrait brut directement dans le fichier.'
        : 'La donnée est bien présente, mais aucun extrait brut sûr n’a pu être affiché dans la station.',
    $islandDataPreview !== ''
        ? 'Ce mode convient aux JSON, CSV, XML, YAML ou autres structures textuelles assez propres pour une lecture immédiate.'
        : 'Le fichier demande une ouverture externe ou un traitement complémentaire avant d’être relu confortablement.'
);
$islandDesignStatusMode = 'badge source';
$islandDesignStatusState = 'aperçu minimal';
$islandDesignStatusLead = 'La station expose la source design, mais sans aperçu riche natif.';
$islandDesignStatusCopy = 'Le fichier reste disponible à l’ouverture et à l’extraction ; le lecteur garde ici un mode de consultation léger.';
if (($islandDesignItem['thumbnail_url'] ?? '') !== '') {
    $islandDesignStatusMode = 'miniature native';
    $islandDesignStatusState = 'aperçu visuel';
    $islandDesignStatusLead = 'Le lecteur design dispose d’une miniature exploitable directement dans l’île.';
    $islandDesignStatusCopy = 'Quand une miniature existe, la station la privilégie pour garder un accès visuel immédiat à la source de design.';
} elseif ($islandDesignPreview !== '') {
    $islandDesignStatusMode = 'aperçu textuel';
    $islandDesignStatusState = 'lecture source';
    $islandDesignStatusLead = 'Le lecteur design a basculé en aperçu textuel de la source.';
    $islandDesignStatusCopy = 'Ce mode sert de filet utile quand aucune miniature n’existe mais qu’un extrait brut peut encore être lu dans l’île.';
}
$islandDesignStatus = $buildIslandReaderStatus(
    $islandDesignStatusMode,
    $islandDesignStatusState,
    $islandDesignStatusLead,
    $islandDesignStatusCopy
);
$islandModelStatus = $buildIslandReaderStatus(
    $islandNeedsModelViewer ? 'viewer 3d natif' : (($islandModelItem['thumbnail_url'] ?? '') !== '' ? 'miniature 3d' : 'extraction seule'),
    $islandNeedsModelViewer ? 'navigation active' : (($islandModelItem['thumbnail_url'] ?? '') !== '' ? 'aperçu statique' : 'format externe'),
    $islandNeedsModelViewer
        ? 'Le lecteur 3D active le viewer natif pour ce format.'
        : (($islandModelItem['thumbnail_url'] ?? '') !== ''
            ? 'Le lecteur 3D garde une miniature quand le viewer natif n’est pas disponible.'
            : 'Le format 3D est conservé comme objet externe sans viewer intégré.'),
    $islandNeedsModelViewer
        ? 'Le fichier peut être tourné, cadré et relu directement dans la station.'
        : (($islandModelItem['thumbnail_url'] ?? '') !== ''
            ? 'Cette île dispose d’un aperçu statique, mais l’exploration complète de l’objet demande une ouverture dédiée.'
            : 'L’objet reste consultable via ouverture ou extraction, mais ne peut pas être prévisualisé ici dans un viewer fiable.')
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $land ? h((string) $land['username']) . ' — île classique en devenir dans ' . h(SITE_TITLE) : 'Île introuvable — ' . h(SITE_TITLE) ?>">
    <meta name="theme-color" content="#09090b">
    <title><?= $land ? h((string) $land['username']) . ' — Île classique — ' . h(SITE_TITLE) : 'Île introuvable — ' . h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
<?php if ($islandNeedsModelViewer): ?>
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
<?php endif; ?>
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

        <section class="panel reveal island-reader-station" aria-labelledby="island-reader-title">
            <div class="section-topline aza-timeline-header">
                <div>
                    <h2 id="island-reader-title">Station de lecture</h2>
                    <p class="panel-copy">Même logique que `sowwwl.digital`, mais distribuée par familles de matière : audio, image, vidéo, PDF, SVG, data, design et 3D, directement depuis l’île — avec maintenant un parcours curatoriel continu.</p>
                </div>
                <div class="aza-meta-list">
                    <span class="meta-pill"><?= h((string) $islandReaderCount) ?> lecteur<?= $islandReaderCount > 1 ? 's' : '' ?> actif<?= $islandReaderCount > 1 ? 's' : '' ?></span>
                    <span class="meta-pill"><?= h((string) $islandDormantReaderCount) ?> veille<?= $islandDormantReaderCount > 1 ? 's' : '' ?></span>
                    <a class="ghost-link" href="<?= h(aza_memory_query_href($islandBaseQuery, ['view' => 'family'])) ?>">aZa · familles</a>
                </div>
            </div>

            <div class="island-reader-shell" data-island-reader-shell>
                <div class="island-reader-curator" aria-label="Parcours curatoriel de l’île">
                    <div class="island-reader-curator__summary">
                        <p class="summary-label">V5 · parcours</p>
                        <div class="island-reader-curator__headline">
                            <strong data-island-reader-current-label><?= h((string) ($activeIslandReaderData['label'] ?? 'Veille')) ?></strong>
                            <span data-island-reader-counter><?= h($islandCuratorProgressLabel) ?></span>
                        </div>
                        <p class="island-reader-curator__meta" data-island-reader-current-meta><?= h((string) (($activeIslandReaderData['format'] ?? 'veille') . ' · ' . ($activeIslandReaderData['source_label'] ?? 'Veille'))) ?></p>
                        <p class="island-reader-curator__copy">Le parcours avance sur les lecteurs disponibles, garde une continuité de lecture, et peut tourner en mode autonome.</p>
                    </div>

                    <div class="island-reader-curator__controls action-row">
                        <button type="button" class="ghost-link island-reader-curator__button" data-island-reader-prev<?= $islandReaderSequenceCount > 1 ? '' : ' disabled' ?>>précédent</button>
                        <button type="button" class="pill-link island-reader-curator__button" data-island-reader-next<?= $islandReaderSequenceCount > 1 ? '' : ' disabled' ?>>suivant</button>
                        <button type="button" class="ghost-link island-reader-curator__button" data-island-reader-autoplay aria-pressed="false"<?= $islandReaderSequenceCount > 1 ? '' : ' disabled' ?>>parcours auto · off</button>
                    </div>

                    <aside class="island-reader-curator__recommendation">
                        <p class="summary-label">Recommandation</p>
                        <strong data-island-reader-recommendation-label><?= h((string) ($nextIslandReaderData['label'] ?? 'Aucune suite')) ?></strong>
                        <p data-island-reader-recommendation-copy><?= h($islandCuratorRecommendation) ?></p>
                        <?php if (is_array($previousIslandReaderData)): ?>
                            <span class="island-reader-curator__trail">trace précédente · <?= h((string) ($previousIslandReaderData['label'] ?? '—')) ?></span>
                        <?php endif; ?>
                    </aside>
                </div>

                <div class="island-reader-layout">
                    <aside class="island-reader-playlist" aria-labelledby="island-reader-playlist-title">
                        <div class="island-reader-playlist__head">
                            <div>
                                <p class="summary-label">Curation</p>
                                <h3 id="island-reader-playlist-title">Playlist matière</h3>
                            </div>
                            <p class="island-reader-playlist__copy">Une lecture rapide de toutes les surfaces disponibles, avec statut, format et provenance.</p>
                        </div>

                        <div class="island-reader-playlist__stats">
                            <p><span>actifs</span><strong><?= h((string) $islandReaderCount) ?></strong></p>
                            <p><span>veille</span><strong><?= h((string) $islandDormantReaderCount) ?></strong></p>
                            <p><span>matières</span><strong><?= h((string) count($islandReaders)) ?></strong></p>
                        </div>

                        <nav class="island-reader-playlist__nav" aria-label="Naviguer dans les lecteurs de l’île">
                            <?php foreach ($islandReaders as $reader): ?>
                                <?php
                                $readerId = 'island-reader-' . $reader['key'];
                                $isActivePlaylistItem = $reader['key'] === $activeIslandReader;
                                ?>
                                <button
                                    type="button"
                                    class="island-reader-playlist__item<?= $isActivePlaylistItem ? ' is-active' : '' ?>"
                                    data-island-reader-nav="<?= h((string) $reader['key']) ?>"
                                    aria-controls="<?= h($readerId) ?>"
                                    aria-current="<?= $isActivePlaylistItem ? 'true' : 'false' ?>"
                                    <?= $reader['available'] ? '' : 'data-island-reader-empty="1"' ?>
                                >
                                    <span class="island-reader-playlist__line">
                                        <strong><?= h((string) $reader['label']) ?></strong>
                                        <em><?= $reader['available'] ? 'actif' : 'veille' ?></em>
                                    </span>
                                    <span class="island-reader-playlist__line island-reader-playlist__line--meta">
                                        <small><?= h((string) $reader['format']) ?></small>
                                        <small><?= h((string) $reader['source_label']) ?></small>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        </nav>
                    </aside>

                    <div class="island-reader-content">
                        <div class="island-reader-tabs" role="tablist" aria-label="Choisir un lecteur">
                            <?php foreach ($islandReaders as $reader): ?>
                                <?php $readerId = 'island-reader-' . $reader['key']; ?>
                                <button
                                    type="button"
                                    class="island-reader-tab<?= $reader['key'] === $activeIslandReader ? ' is-active' : '' ?>"
                                    role="tab"
                                    id="<?= h($readerId) ?>-tab"
                                    aria-controls="<?= h($readerId) ?>"
                                    aria-selected="<?= $reader['key'] === $activeIslandReader ? 'true' : 'false' ?>"
                                    data-island-reader-tab="<?= h((string) $reader['key']) ?>"
                                    <?= $reader['available'] ? '' : 'data-island-reader-empty="1"' ?>
                                >
                                    <span><?= h((string) $reader['label']) ?></span>
                                    <small><?= $reader['available'] ? h((string) $reader['format']) : 'veille' ?></small>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($islandReaders as $reader): ?>
                            <?php
                            $readerId = 'island-reader-' . $reader['key'];
                            $readerItem = is_array($reader['item'] ?? null) ? $reader['item'] : null;
                            $readerHref = (string) ($reader['href'] ?? '');
                            $readerTitle = (string) ($reader['title'] ?? $reader['label']);
                            $readerSummary = (string) ($reader['summary'] ?? '');
                            $readerThumb = (string) ($reader['thumbnail'] ?? '');
                            $isOpen = $reader['key'] === $activeIslandReader;
                            $readerFullscreenLabel = 'Passer en mode immersion pour ' . $readerTitle;
                            ?>
                            <section
                                class="island-reader-panel<?= $isOpen ? ' is-open' : '' ?>"
                                id="<?= h($readerId) ?>"
                                role="tabpanel"
                                aria-labelledby="<?= h($readerId) ?>-tab"
                                <?= $isOpen ? '' : 'hidden' ?>
                                data-island-reader-panel="<?= h((string) $reader['key']) ?>"
                            >
                                <div class="island-reader-stage<?= $reader['available'] ? '' : ' is-empty' ?>">
                            <header class="island-reader-stage__head">
                                <div>
                                    <p class="summary-label">Lecteur <?= h((string) $reader['label']) ?></p>
                                    <h3><?= h($readerTitle) ?></h3>
                                    <p class="island-reader-stage__copy"><?= h($readerSummary) ?></p>
                                </div>
                                <div class="island-reader-stage__head-tools">
                                    <div class="island-reader-stage__meta aza-meta-list">
                                        <span class="meta-pill"><?= h((string) $reader['source_label']) ?></span>
                                        <span class="meta-pill"><?= h((string) $reader['date_label']) ?></span>
                                        <span class="meta-pill"><?= h((string) $reader['format']) ?></span>
                                    </div>
                                    <button type="button" class="ghost-link island-reader-fullscreen" data-island-reader-fullscreen aria-label="<?= h($readerFullscreenLabel) ?>">plein cadre</button>
                                </div>
                            </header>

                            <?php if (!$reader['available']): ?>
                                <div class="island-reader-fallback">
                                    <strong>Aucune matière <?= h(strtolower((string) $reader['label'])) ?> publique pour cette île.</strong>
                                    <p>Le shell du lecteur est prêt ; il s’activera dès qu’une trace compatible sera déposée.</p>
                                </div>
                            <?php elseif ($reader['key'] === 'audio'): ?>
                                <div class="island-reader-grid island-reader-grid--audio">
                                    <div class="island-reader-surface">
                                        <div class="island-reader-wave">
                                            <span></span><span></span><span></span><span></span><span></span>
                                        </div>
                                        <p>nappe orbitale</p>
                                    </div>
                                    <section
                                        class="str3m-player island-reader-audio-player"
                                        data-str3m-player
                                        data-str3m-player-has-source="1"
                                        data-str3m-player-title="<?= h($readerTitle) ?>"
                                        data-str3m-player-mood="island"
                                        data-str3m-player-template="audio"
                                        tabindex="0"
                                        aria-label="Lecteur audio de l’île"
                                    >
                                        <div class="str3m-player__hero">
                                            <div>
                                                <p class="summary-label">Audio</p>
                                                <h4><?= h($readerTitle) ?></h4>
                                                <p class="str3m-player__copy"><?= h($readerSummary) ?></p>
                                            </div>
                                        </div>
                                        <div class="str3m-player__dock">
                                            <section class="str3m-player__transport" aria-labelledby="<?= h($readerId) ?>-transport-title">
                                                <div class="str3m-player__section-topline">
                                                    <h5 id="<?= h($readerId) ?>-transport-title">Transport</h5>
                                                    <span class="str3m-player__state" data-str3m-player-status>prêt</span>
                                                </div>
                                                <div class="str3m-player__buttons">
                                                    <button type="button" class="str3m-player__button" data-str3m-player-back aria-label="Reculer de cinq secondes">−5 s</button>
                                                    <button type="button" class="str3m-player__button str3m-player__button--primary" data-str3m-player-toggle aria-label="Lecture ou pause">lecture</button>
                                                    <button type="button" class="str3m-player__button" data-str3m-player-forward aria-label="Avancer de cinq secondes">+5 s</button>
                                                </div>
                                                <label class="str3m-player__range-wrap">
                                                    <span class="sr-only">Progression audio</span>
                                                    <input type="range" min="0" max="1" step="0.001" value="0" data-str3m-player-progress>
                                                </label>
                                                <div class="str3m-player__times" aria-live="polite">
                                                    <span data-str3m-player-current>00:00</span>
                                                    <span data-str3m-player-duration>00:00</span>
                                                </div>
                                            </section>
                                            <section class="str3m-player__controls" aria-labelledby="<?= h($readerId) ?>-controls-title">
                                                <div class="str3m-player__section-topline">
                                                    <h5 id="<?= h($readerId) ?>-controls-title">Lecture</h5>
                                                    <output class="str3m-player__rate" data-str3m-player-rate-output>1.00×</output>
                                                </div>
                                                <div class="str3m-player__buttons str3m-player__buttons--compact">
                                                    <button type="button" class="str3m-player__button" data-str3m-player-rate-step="-0.25">−</button>
                                                    <button type="button" class="str3m-player__button" data-str3m-player-rate-step="0.25">+</button>
                                                    <button type="button" class="str3m-player__button" data-str3m-player-reset>reset</button>
                                                </div>
                                                <label class="str3m-player__toggle">
                                                    <input type="checkbox" data-str3m-player-preserve-pitch checked>
                                                    <span>Conserver la hauteur</span>
                                                </label>
                                            </section>
                                            <section class="str3m-player__eq" aria-labelledby="<?= h($readerId) ?>-eq-title">
                                                <div class="str3m-player__section-topline">
                                                    <h5 id="<?= h($readerId) ?>-eq-title">EQ audio</h5>
                                                    <span class="str3m-player__eq-state" data-str3m-player-eq-state>actif</span>
                                                </div>
                                                <div class="str3m-player__sliders">
                                                    <label class="str3m-player__slider">
                                                        <span>Bass <output data-str3m-player-bass-value>0.0 dB</output></span>
                                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-bass>
                                                    </label>
                                                    <label class="str3m-player__slider">
                                                        <span>Mid <output data-str3m-player-mid-value>0.0 dB</output></span>
                                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-mid>
                                                    </label>
                                                    <label class="str3m-player__slider">
                                                        <span>Treble <output data-str3m-player-treble-value>0.0 dB</output></span>
                                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-treble>
                                                    </label>
                                                    <label class="str3m-player__slider">
                                                        <span>Gain <output data-str3m-player-gain-value>100%</output></span>
                                                        <input type="range" min="0" max="150" step="1" value="100" data-str3m-player-gain>
                                                    </label>
                                                </div>
                                            </section>
                                            <section class="str3m-player__status-panel" aria-labelledby="<?= h($readerId) ?>-status-title">
                                                <div class="str3m-player__section-topline">
                                                    <h5 id="<?= h($readerId) ?>-status-title">État</h5>
                                                </div>
                                                <div class="str3m-player__status-grid">
                                                    <p><span>Source</span><strong data-str3m-player-source><?= h($readerTitle) ?></strong></p>
                                                    <p><span>Vitesse</span><strong data-str3m-player-rate-state>1.00×</strong></p>
                                                    <p><span>EQ</span><strong data-str3m-player-summary>plat</strong></p>
                                                    <p><span>Raccourcis</span><strong>Espace · ← →</strong></p>
                                                </div>
                                            </section>
                                        </div>
                                        <audio preload="metadata" class="str3m-player__native" data-str3m-player-audio>
                                            <source src="<?= h($readerHref) ?>">
                                        </audio>
                                    </section>
                                </div>
                            <?php elseif ($reader['key'] === 'image'): ?>
                                <div class="island-reader-grid">
                                    <figure class="island-reader-visual-surface">
                                        <?php if ($readerThumb !== ''): ?>
                                            <img src="<?= h($readerThumb) ?>" alt="<?= h($readerTitle) ?>" loading="lazy">
                                        <?php else: ?>
                                            <img src="<?= h($readerHref) ?>" alt="<?= h($readerTitle) ?>" loading="lazy">
                                        <?php endif; ?>
                                    </figure>
                                    <div class="island-reader-sidecar">
                                        <p>Lecture image intégrée, sans sortie de contexte.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir l’image</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'video'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-video-stage">
                                        <div class="island-reader-video-frame">
                                        <?php if ($islandVideoIsBrowserPlayable): ?>
                                            <video controls playsinline preload="metadata"<?= $islandVideoPoster !== '' ? ' poster="' . h($islandVideoPoster) . '"' : '' ?>>
                                                <source src="<?= h($readerHref) ?>">
                                            </video>
                                        <?php else: ?>
                                            <div class="island-reader-model-fallback-copy">
                                                <strong>Vidéo disponible, mais pas lisible directement ici</strong>
                                                <p>Le format <?= h(strtoupper($islandVideoFormat !== '' ? $islandVideoFormat : 'vidéo')) ?> n’est pas garanti en lecture navigateur. Ouverture externe et extraction restent disponibles.</p>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                        <div class="island-reader-video-note">
                                            <span class="island-reader-note-eyebrow">diagnostic de lecture</span>
                                            <div class="island-reader-video-note__pills aza-meta-list" aria-label="État de la lecture vidéo">
                                                <span class="meta-pill"><?= h(strtoupper($islandVideoFormat !== '' ? $islandVideoFormat : 'VIDÉO')) ?></span>
                                                <span class="meta-pill"><?= h($islandVideoCompatibilityLabel) ?></span>
                                                <span class="meta-pill"><?= h($islandVideoSelectionLabel) ?></span>
                                            </div>
                                            <p class="island-reader-video-note__lead"><?= h($islandVideoStatusCopy) ?></p>
                                            <p class="island-reader-video-note__copy"><?= h($islandVideoSelectionNote) ?></p>
                                        </div>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p><?= h($islandVideoSelectionNote) ?></p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir la vidéo</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'pdf'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-document-frame">
                                        <iframe src="<?= h($readerHref) ?>#view=FitH" title="<?= h($readerTitle) ?>" loading="lazy"></iframe>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Le document reste lisible dans l’île, avec possibilité d’ouverture externe si besoin.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir le PDF</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>télécharger</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'text'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-reader-stack">
                                    <div class="island-reader-text-frame">
                                        <?php if ($islandTextPreview !== ''): ?>
                                            <article class="island-reader-text-body">
                                                <pre><?= h($islandTextPreview) ?></pre>
                                            </article>
                                        <?php else: ?>
                                            <div class="island-reader-model-fallback-copy">
                                                <strong>Prévisualisation textuelle indisponible</strong>
                                                <p>Le document existe, mais son format n’est pas lisible ici sans conversion. L’extraction reste disponible.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                        <div class="island-reader-status-note">
                                            <span class="island-reader-note-eyebrow">diagnostic de lecture</span>
                                            <div class="island-reader-status-note__pills aza-meta-list">
                                                <span class="meta-pill"><?= h($islandTextStatus['mode']) ?></span>
                                                <span class="meta-pill"><?= h($islandTextStatus['state']) ?></span>
                                            </div>
                                            <p class="island-reader-status-note__lead"><?= h($islandTextStatus['lead']) ?></p>
                                            <p class="island-reader-status-note__copy"><?= h($islandTextStatus['copy']) ?></p>
                                        </div>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Lecture continue pour texte, markdown ou document léger, sans quitter la station.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir le texte</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'svg'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-vector-frame">
                                        <object data="<?= h($readerHref) ?>" type="image/svg+xml" aria-label="<?= h($readerTitle) ?>">
                                            <img src="<?= h($readerHref) ?>" alt="<?= h($readerTitle) ?>" loading="lazy">
                                        </object>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Le tracé vectoriel est rendu en natif, net sur toutes les échelles.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir le SVG</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'data'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-reader-stack">
                                    <div class="island-reader-code-frame">
                                        <?php if ($islandDataPreview !== ''): ?>
                                            <pre><?= h($islandDataPreview) ?></pre>
                                        <?php else: ?>
                                            <div class="island-reader-model-fallback-copy">
                                                <strong>Aperçu brut indisponible</strong>
                                                <p>Le fichier existe, mais il n’a pas pu être prélevé en prévisualisation textuelle sûre.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                        <div class="island-reader-status-note">
                                            <span class="island-reader-note-eyebrow">diagnostic de lecture</span>
                                            <div class="island-reader-status-note__pills aza-meta-list">
                                                <span class="meta-pill"><?= h($islandDataStatus['mode']) ?></span>
                                                <span class="meta-pill"><?= h($islandDataStatus['state']) ?></span>
                                            </div>
                                            <p class="island-reader-status-note__lead"><?= h($islandDataStatus['lead']) ?></p>
                                            <p class="island-reader-status-note__copy"><?= h($islandDataStatus['copy']) ?></p>
                                        </div>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Lecture orientée structure : utile pour JSON, CSV, XML, YAML ou autres fichiers de données lisibles.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir la donnée</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'design'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-reader-stack">
                                    <div class="island-reader-design-frame<?= $readerThumb === '' ? ' is-fallback' : '' ?>">
                                        <?php if ($readerThumb !== ''): ?>
                                            <img src="<?= h($readerThumb) ?>" alt="<?= h($readerTitle) ?>" loading="lazy">
                                        <?php elseif ($islandDesignPreview !== ''): ?>
                                            <pre><?= h($islandDesignPreview) ?></pre>
                                        <?php else: ?>
                                            <div class="island-reader-design-badge">
                                                <strong><?= h($buildDesignFallbackLabel($readerItem)) ?></strong>
                                                <span>source design</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                        <div class="island-reader-status-note">
                                            <span class="island-reader-note-eyebrow">diagnostic de lecture</span>
                                            <div class="island-reader-status-note__pills aza-meta-list">
                                                <span class="meta-pill"><?= h($islandDesignStatus['mode']) ?></span>
                                                <span class="meta-pill"><?= h($islandDesignStatus['state']) ?></span>
                                            </div>
                                            <p class="island-reader-status-note__lead"><?= h($islandDesignStatus['lead']) ?></p>
                                            <p class="island-reader-status-note__copy"><?= h($islandDesignStatus['copy']) ?></p>
                                        </div>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Source de design lisible quand une miniature ou un aperçu existe ; sinon on garde un accès direct au fichier maître.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir la source</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'archive'): ?>
                                <?php
                                $archiveRaw = is_array($readerItem['raw'] ?? null) ? $readerItem['raw'] : [];
                                $archiveFamilies = array_values(array_filter(array_map('strval', $archiveRaw['content_families'] ?? [])));
                                $archiveYears = array_values(array_filter(array_map('intval', $archiveRaw['years'] ?? [])));
                                $archiveTopFolders = is_array($archiveRaw['top_folders'] ?? null) ? $archiveRaw['top_folders'] : [];
                                $archiveSamplePaths = array_values(array_filter(array_map('strval', $archiveRaw['sample_paths'] ?? [])));
                                ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-archive-frame">
                                        <div class="island-reader-archive-stats">
                                            <p><span>Entrées</span><strong><?= h((string) ($archiveRaw['entries'] ?? '0')) ?></strong></p>
                                            <p><span>Médias</span><strong><?= h((string) ($archiveRaw['media_entries'] ?? '0')) ?></strong></p>
                                            <p><span>Période</span><strong><?= $archiveYears ? h((string) min($archiveYears) . ' → ' . (string) max($archiveYears)) : 'Atemporel' ?></strong></p>
                                        </div>
                                        <?php if ($archiveFamilies): ?>
                                            <div class="aza-meta-list">
                                                <?php foreach (array_slice($archiveFamilies, 0, 6) as $family): ?>
                                                    <span class="meta-pill"><?= h(aza_memory_family_label($family)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($archiveTopFolders): ?>
                                            <div class="island-reader-archive-folders">
                                                <?php foreach (array_slice($archiveTopFolders, 0, 5, true) as $folder => $count): ?>
                                                    <p><span><?= h((string) $folder) ?></span><strong><?= h((string) $count) ?></strong></p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($archiveSamplePaths): ?>
                                            <pre class="island-reader-archive-sample"><?= h(implode("\n", array_slice($archiveSamplePaths, 0, 8))) ?></pre>
                                        <?php endif; ?>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Lecture synthétique d’une archive mémoire : densité, familles, dossiers dominants et quelques chemins repères.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir l’archive</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($reader['key'] === 'model'): ?>
                                <div class="island-reader-grid">
                                    <div class="island-reader-reader-stack">
                                    <div class="island-reader-model-frame<?= $islandNeedsModelViewer ? '' : ' is-fallback' ?>">
                                        <?php if ($islandNeedsModelViewer): ?>
                                            <model-viewer
                                                src="<?= h($readerHref) ?>"
                                                camera-controls
                                                auto-rotate
                                                shadow-intensity="0.9"
                                                exposure="1.1"
                                                ar
                                                loading="lazy"
                                                alt="<?= h($readerTitle) ?>"
                                            ></model-viewer>
                                        <?php elseif ($readerThumb !== ''): ?>
                                            <img src="<?= h($readerThumb) ?>" alt="<?= h($readerTitle) ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="island-reader-model-fallback-copy">
                                                <strong>Format 3D non prévisualisable ici</strong>
                                                <p>Le viewer détaillé peut s’activer pour `GLB` / `GLTF`. Pour ce dépôt, on garde l’objet disponible à l’extraction.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                        <div class="island-reader-status-note">
                                            <span class="island-reader-note-eyebrow">diagnostic de lecture</span>
                                            <div class="island-reader-status-note__pills aza-meta-list">
                                                <span class="meta-pill"><?= h($islandModelStatus['mode']) ?></span>
                                                <span class="meta-pill"><?= h($islandModelStatus['state']) ?></span>
                                            </div>
                                            <p class="island-reader-status-note__lead"><?= h($islandModelStatus['lead']) ?></p>
                                            <p class="island-reader-status-note__copy"><?= h($islandModelStatus['copy']) ?></p>
                                        </div>
                                    </div>
                                    <div class="island-reader-sidecar">
                                        <p>Lecture 3D intégrée quand le format le permet, sinon fallback propre et extraction directe.</p>
                                        <div class="action-row">
                                            <a class="pill-link" href="<?= h($readerHref) ?>" target="_blank" rel="noreferrer">ouvrir l’objet</a>
                                            <a class="ghost-link" href="<?= h($readerHref) ?>" download>extraire</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
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
