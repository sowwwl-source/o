<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/str3m_media.php';
require_once __DIR__ . '/lib/str3m_daily.php';
require_once __DIR__ . '/lib/signals.php';

function str3m_signal_kind_label(string $kind): string
{
    return match (trim($kind)) {
        'pulse' => 'impulsion',
        'image' => 'image',
        'link' => 'lien',
        'fragment' => 'fragment',
        default => 'note',
    };
}

function str3m_timestamp(?string $value): int
{
    $candidate = trim((string) $value);
    if ($candidate === '') {
        return 0;
    }

    $timestamp = strtotime($candidate);
    return $timestamp !== false ? $timestamp : 0;
}

function str3m_visible_land_state(array $profile): array
{
    $signalCount = (int) ($profile['signal_count'] ?? 0);
    $t0kCount = (int) ($profile['t0k_count'] ?? 0);
    $t0kPendingCount = (int) ($profile['t0k_pending_count'] ?? 0);
    $t0kActiveCount = (int) ($profile['t0k_active_count'] ?? 0);
    $b0t3Count = (int) ($profile['b0t3_count'] ?? 0);
    $latestTs = (int) ($profile['latest_ts'] ?? 0);
    $hoursSinceLatest = $latestTs > 0 ? max(0.0, (time() - $latestTs) / 3600) : 999.0;
    $publicWeight = ($signalCount * 4) + ($t0kActiveCount * 3) + ($t0kPendingCount * 2) + $b0t3Count;

    if ($signalCount > 0 && $hoursSinceLatest <= 72) {
        return [
            'key' => 'present',
            'label' => 'présente',
            'hint' => 'indice public · present',
            'summary' => 'Une trace publique récente tient cette terre ouverte dans le courant.',
        ];
    }

    if ($t0kActiveCount > 0 || ($publicWeight >= 6 && $hoursSinceLatest <= 96)) {
        return [
            'key' => 'near',
            'label' => 'proche',
            'hint' => 'indice public · near',
            'summary' => 'Des gestes actifs et des lignes récentes laissent penser qu’un échange peut reprendre vite.',
        ];
    }

    if ($t0kPendingCount > 0 || $t0kCount > 0 || $b0t3Count > 0) {
        return [
            'key' => 'roaming',
            'label' => 'en dérive',
            'hint' => 'indice public · roaming',
            'summary' => 'La terre circule encore entre gestes, attentes et dépôts, sans ancrage public stable.',
        ];
    }

    return [
        'key' => 'asleep',
        'label' => 'endormie',
        'hint' => 'indice public · asleep',
        'summary' => 'Des traces subsistent, mais la terre repose pour l’instant dans le courant public.',
    ];
}

function str3m_presence_class(array $state): string
{
    return match ((string) ($state['key'] ?? '')) {
        'present' => 'is-presence-present',
        'near' => 'is-presence-near',
        'roaming' => 'is-presence-roaming',
        'asleep' => 'is-presence-asleep',
        default => 'is-presence-unknown',
    };
}

function str3m_fibonacci_value(int $index): int
{
    static $values = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55];

    return $values[$index % count($values)] ?? 1;
}

function str3m_compact_copy(string $text, int $limit = 160): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text));
    $normalized = is_string($normalized) ? $normalized : '';
    if ($normalized === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, max(1, $limit - 1))) . '…';
    }

    if (strlen($normalized) <= $limit) {
        return $normalized;
    }

    return rtrim(substr($normalized, 0, max(1, $limit - 1))) . '…';
}

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSpatialHeadsetMode = $surfaceVariant === 'io' && spatial_preview_mode($host) === 'headset';

$brandDomain = current_brand_domain($host);

$authenticatedLand = current_authenticated_land();
$guideHref = o_route_href('/0wlslw0', [], $host);
$signalHref = o_route_href('/signal', [], $host);
$joinHref = o_route_href('/rejoindre', [], $host);
$landHref = o_route_href('/land', [], $host);
$signalItemHref = static fn (string $id): string => o_route_href('/signal_item.php', ['id' => $id], $host);
$futureShellRoute = o_route_href('/n0de', [], $host);
$shoreHref = o_route_href('/sh0re', [], $host);
$nHref = o_route_href('/n', [], $host);
$str3mHref = o_route_href('/str3m', [], $host);
$landRouteHref = static fn (string $slug): string => o_route_href('/land', ['u' => $slug], $host);
$shoreRouteHref = static fn (string $slug): string => o_route_href('/sh0re', ['u' => $slug], $host);
$tokenRouteHref = static fn (string $token): string => o_route_href('/n', ['t' => $token], $host);
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
$dailyImageAlt = (string) ($dailyImageItem['meta']['alt'] ?? $dailyImageItem['title'] ?? 'Image str3m');
$dailyAudioTitle = $dailyAudioItem ? (string) ($dailyAudioItem['title'] ?? 'Nappe du jour') : 'Nappe en veille';
$dailyAudioCaption = trim((string) (($dailyAudioItem['meta']['excerpt'] ?? '') ?: ($dailyAudioItem['meta']['description'] ?? '')));
$dailyAudioCaption = $dailyAudioCaption !== ''
    ? $dailyAudioCaption
    : ($dailyAudioPath !== ''
        ? 'Lecture continue du courant du jour, avec vitesse, égalisation et navigation intégrées.'
        : 'Le lecteur est prêt, mais aucune nappe publique n’est publiée aujourd’hui.');
$dailyAudioHasSource = $dailyAudioPath !== '';

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

// T0ks visibles dans le courant
$recentT0ks = t0k_recent_public(12);

// B0t3s visibles dans le courant
$recentB0t3s = b0t3_recent_public(20);
$publicSignalCount = count($publicSignals);
$publicSignalPreview = array_slice($publicSignals, 0, 6);
$recentT0kCount = count($recentT0ks);
$recentB0t3Count = count($recentB0t3s);
$str3mPublicGestureCount = $recentT0kCount + $recentB0t3Count;
$visibleLands = [];

foreach ($publicSignals as $signal) {
    $slug = trim((string) ($signal['land_slug'] ?? ''));
    if ($slug === '') {
        continue;
    }

    if (!isset($visibleLands[$slug])) {
        $visibleLands[$slug] = [
            'slug' => $slug,
            'username' => trim((string) ($signal['land_username'] ?? $slug)),
            'signal_count' => 0,
            't0k_count' => 0,
            't0k_pending_count' => 0,
            't0k_active_count' => 0,
            'b0t3_count' => 0,
            'latest_at' => '',
            'latest_ts' => 0,
        ];
    }

    $visibleLands[$slug]['signal_count']++;
    $publishedAt = trim((string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? '')));
    $publishedTs = str3m_timestamp($publishedAt);
    if ($publishedTs > ($visibleLands[$slug]['latest_ts'] ?? 0)) {
        $visibleLands[$slug]['latest_ts'] = $publishedTs;
        $visibleLands[$slug]['latest_at'] = $publishedAt;
    }
}

foreach ($recentT0ks as $t0k) {
    foreach (['from_land', 'to_land'] as $role) {
        $slug = trim((string) ($t0k[$role] ?? ''));
        if ($slug === '') {
            continue;
        }

        if (!isset($visibleLands[$slug])) {
            $visibleLands[$slug] = [
                'slug' => $slug,
                'username' => $slug,
                'signal_count' => 0,
                't0k_count' => 0,
                't0k_pending_count' => 0,
                't0k_active_count' => 0,
                'b0t3_count' => 0,
                'latest_at' => '',
                'latest_ts' => 0,
            ];
        }

        $visibleLands[$slug]['t0k_count']++;
        $t0kStatus = strtolower(trim((string) ($t0k['status'] ?? '')));
        if ($t0kStatus === 'active') {
            $visibleLands[$slug]['t0k_active_count']++;
        } elseif ($t0kStatus === 'pending') {
            $visibleLands[$slug]['t0k_pending_count']++;
        }
        $t0kAt = trim((string) (($t0k['formed_at'] ?? '') ?: ($t0k['sent_at'] ?? '')));
        $t0kTs = str3m_timestamp($t0kAt);
        if ($t0kTs > ($visibleLands[$slug]['latest_ts'] ?? 0)) {
            $visibleLands[$slug]['latest_ts'] = $t0kTs;
            $visibleLands[$slug]['latest_at'] = $t0kAt;
        }
    }
}

foreach ($recentB0t3s as $b0t3) {
    $slug = trim((string) ($b0t3['target_land'] ?? ''));
    if ($slug === '') {
        continue;
    }

    if (!isset($visibleLands[$slug])) {
        $visibleLands[$slug] = [
            'slug' => $slug,
            'username' => $slug,
            'signal_count' => 0,
            't0k_count' => 0,
            't0k_pending_count' => 0,
            't0k_active_count' => 0,
            'b0t3_count' => 0,
            'latest_at' => '',
            'latest_ts' => 0,
        ];
    }

    $visibleLands[$slug]['b0t3_count']++;
    $b0t3At = trim((string) ($b0t3['created_at'] ?? ''));
    $b0t3Ts = str3m_timestamp($b0t3At);
    if ($b0t3Ts > ($visibleLands[$slug]['latest_ts'] ?? 0)) {
        $visibleLands[$slug]['latest_ts'] = $b0t3Ts;
        $visibleLands[$slug]['latest_at'] = $b0t3At;
    }
}

foreach ($visibleLands as $slug => &$profile) {
    try {
        $land = find_land($slug);
    } catch (Throwable $exception) {
        $land = null;
    }

    if (is_array($land)) {
        $profile['username'] = trim((string) ($land['username'] ?? $profile['username'])) ?: $profile['username'];
    }

    $profile['state'] = str3m_visible_land_state($profile);
}
unset($profile);

$visibleLandIndex = $visibleLands;

foreach ($activeIslands as $slug => &$island) {
    $matchedProfile = $visibleLandIndex[$slug] ?? null;
    $island['state'] = is_array($matchedProfile['state'] ?? null)
        ? $matchedProfile['state']
        : [
            'key' => 'present',
            'label' => 'présente',
            'hint' => 'indice public · present',
            'summary' => 'Cette terre a laissé un signal public récent dans l’archipel.',
        ];
}
unset($island);

usort(
    $visibleLands,
    static function (array $left, array $right): int {
        $leftScore = ((int) ($left['signal_count'] ?? 0) * 10) + ((int) ($left['t0k_count'] ?? 0) * 3) + ((int) ($left['b0t3_count'] ?? 0) * 2);
        $rightScore = ((int) ($right['signal_count'] ?? 0) * 10) + ((int) ($right['t0k_count'] ?? 0) * 3) + ((int) ($right['b0t3_count'] ?? 0) * 2);
        $scoreComparison = $rightScore <=> $leftScore;
        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        return ((int) ($right['latest_ts'] ?? 0)) <=> ((int) ($left['latest_ts'] ?? 0));
    }
);

$visibleLandPreview = array_slice($visibleLands, 0, 6);
$archipelagoLands = array_slice($visibleLands, 0, 13);
$archipelagoHasPublishedSignal = false;

foreach ($archipelagoLands as &$land) {
    $land['has_public_signal'] = ((int) ($land['signal_count'] ?? 0)) > 0;
    $land['last_active'] = (string) ($land['latest_at'] ?? '');
    if ($land['has_public_signal']) {
        $archipelagoHasPublishedSignal = true;
    }
}
unset($land);

$visibleLandPreviewCount = count($visibleLandPreview);
$archipelagoLandCount = count($archipelagoLands);
$str3mModeLabel = $authenticatedLand ? 'présence liée' : 'lecture publique';
$str3mTodayTitle = $dailyTextItem
    ? (string) $dailyTextItem['title']
    : ($dailyAudioHasSource ? $dailyAudioTitle : 'Courant en veille');
$str3mTodayCopy = $dailyTextBody !== ''
    ? str3m_compact_copy($dailyTextBody, 180)
    : ($dailyTextExcerpt !== ''
        ? str3m_compact_copy($dailyTextExcerpt, 180)
        : str3m_compact_copy($dailyAudioCaption, 180));
if ($str3mTodayCopy === '') {
    $str3mTodayCopy = 'Le courant du jour reste disponible, même quand aucune matière publique n’a encore été choisie.';
}

$str3mVisibleTitle = 'Le courant attend une première preuve';
$str3mVisibleCopy = 'Aucune terre n’a encore laissé assez de trace publique pour tenir la surface.';
$str3mVisibleHref = $authenticatedLand ? $signalHref : $joinHref;
$str3mVisibleLinkLabel = $authenticatedLand ? 'Ouvrir Signal' : 'Poser une terre';

if ($publicSignalCount > 0) {
    $str3mVisibleTitle = 'Des traces tiennent la surface';
    $str3mVisibleCopy = $publicSignalCount . ' signal' . ($publicSignalCount > 1 ? 's' : '') . ' public' . ($publicSignalCount > 1 ? 's' : '') . ' donne' . ($publicSignalCount > 1 ? 'nt' : '') . ' déjà une lecture explicite du courant.';
    $str3mVisibleHref = '#str3m-signals-title';
    $str3mVisibleLinkLabel = 'Lire les signaux';
} elseif ($visibleLandPreviewCount > 0) {
    $str3mVisibleTitle = 'Des terres deviennent lisibles';
    $str3mVisibleCopy = $visibleLandPreviewCount . ' terre' . ($visibleLandPreviewCount > 1 ? 's' : '') . ' affleurent déjà avec des indices publics, même avant un signal durable.';
    $str3mVisibleHref = '#str3m-visible-lands-title';
    $str3mVisibleLinkLabel = 'Voir les terres visibles';
} elseif (($recentT0kCount + $recentB0t3Count) > 0) {
    $str3mVisibleTitle = 'Le courant bouge sans signal durable';
    $str3mVisibleCopy = 'Des gestes et dépôts publics circulent déjà, mais sans encore tenir une lecture stable de la surface.';
    $str3mVisibleHref = '#str3m-proof-title';
    $str3mVisibleLinkLabel = 'Relire les preuves';
}

$str3mLabTitle = $archipelagoLandCount > 0 ? 'Archipel et shell fantôme' : 'Lab encore discret';
$str3mLabCopy = $archipelagoLandCount > 0
    ? 'La couche exploratoire est déjà là : archipel en 3D, tactilité publique et pont vers n0de, sans devenir la porte principale.'
    : 'Le lab restera calme jusqu’à ce que des traces publiques suffisent à ouvrir l’archipel et ses objets portés.';
$str3mLabHref = $archipelagoLandCount > 0 ? '#islands-title' : $futureShellRoute;
$str3mLabLinkLabel = $archipelagoLandCount > 0 ? 'Explorer l’archipel' : 'Voir n0de';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Str3m — explorer le courant quotidien et les îles dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Str3m — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets(pwa_default_app_id($host), $host) ?>
</head>
<body class="experience str3m-view<?= $surfaceVariant === 'io' ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?>">
<?= render_skip_link() ?>
<?= render_nucleus_banner('str3m') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, (string) ($dailyStream['mood'] ?? 'calm'), 'str3m') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>str3m</strong> <span>océan public</span></p>
        <h1 class="land-title signal-title">
            <strong>Le courant et les îles.</strong>
            <span>I inverse + voix</span>
        </h1>
        <p class="lead">Le courant du jour et les terres visibles.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($authenticatedLand): ?>
                <span class="meta-pill">terre liée : <?= h((string) $authenticatedLand['slug']) ?></span>
            <?php endif; ?>
            <span class="meta-pill">humeur : <?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
        </div>
    </header>

    <?= render_spatial_context_bar('str3m', $host) ?>

    <section class="panel reveal str3m-panel" aria-labelledby="str3m-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-title">Str3m quotidien</h2>
                <p class="panel-copy" data-str3m-ra-note>Une présence pour aujourd’hui.</p>
            </div>
            <span class="badge"><?= h((string) ($dailyStream['template'] ?? 'empty')) ?></span>
        </div>

        <div class="public-entry-grid public-entry-grid--dense str3m-surface-grid" aria-label="Surface en ce moment">
            <article class="public-entry-card" data-str3m-ra-card="signals">
                <strong><?= h((string) $publicSignalCount) ?> signal<?= $publicSignalCount > 1 ? 's' : '' ?> public<?= $publicSignalCount > 1 ? 's' : '' ?></strong>
                <span><?= $publicSignalCount > 0 ? 'Des traces lisibles tiennent déjà le courant.' : 'Le courant attend encore sa première preuve publique.' ?></span>
            </article>
            <article class="public-entry-card" data-str3m-ra-card="lands">
                <strong><?= h((string) $visibleLandPreviewCount) ?> terre<?= $visibleLandPreviewCount > 1 ? 's' : '' ?> visible<?= $visibleLandPreviewCount > 1 ? 's' : '' ?></strong>
                <span><?= $visibleLandPreviewCount > 0 ? 'Des présences deviennent déjà relisibles sans porte privée.' : 'Aucune terre n’affleure encore assez pour tenir la surface.' ?></span>
            </article>
            <article class="public-entry-card" data-str3m-ra-card="gestures">
                <strong><?= h((string) $str3mPublicGestureCount) ?> geste<?= $str3mPublicGestureCount > 1 ? 's' : '' ?> en circulation</strong>
                <span><?= $str3mPublicGestureCount > 0 ? 't0ks et b0t3s bougent déjà dans le champ public.' : 'Le bord public reste calme pour le moment.' ?></span>
            </article>
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
                <div class="str3m-media-stage<?= $dailyAudioHasSource ? '' : ' is-passive' ?>">
                    <?php if ($dailyImagePath !== ''): ?>
                        <figure class="str3m-figure str3m-figure--player">
                            <img src="<?= h($dailyImagePath) ?>" alt="<?= h($dailyImageAlt) ?>" class="str3m-image" loading="lazy">
                        </figure>
                    <?php else: ?>
                        <div class="str3m-figure str3m-figure--player str3m-figure--void" aria-hidden="true">
                            <span class="str3m-void-orbit"></span>
                            <span class="str3m-void-pulse"></span>
                            <strong>surface latente</strong>
                        </div>
                    <?php endif; ?>

                    <section
                        class="str3m-player<?= $dailyAudioHasSource ? '' : ' is-empty' ?>"
                        data-str3m-player
                        data-str3m-player-has-source="<?= $dailyAudioHasSource ? '1' : '0' ?>"
                        data-str3m-player-source-url="<?= h($dailyAudioPath) ?>"
                        data-str3m-player-title="<?= h($dailyAudioTitle) ?>"
                        data-str3m-player-mood="<?= h((string) ($dailyStream['mood'] ?? 'calm')) ?>"
                        data-str3m-player-template="<?= h((string) ($dailyStream['template'] ?? 'empty')) ?>"
                        tabindex="0"
                        aria-label="Lecteur intégré du str3m quotidien"
                    >
                        <div class="str3m-player__hero">
                            <div>
                                <p class="summary-label">Lecteur</p>
                                <h4><?= h($dailyAudioTitle) ?></h4>
                                <p class="str3m-player__copy"><?= h($dailyAudioCaption) ?></p>
                                <p class="str3m-player__ra-note" data-str3m-player-ra-note>Le tore peut encore accorder la tenue de lecture selon la couche dominante.</p>
                            </div>
                            <div class="str3m-player__hero-meta" aria-label="État du lecteur">
                                <span class="meta-pill">mood : <?= h((string) ($dailyStream['mood'] ?? 'calm')) ?></span>
                                <span class="meta-pill">template : <?= h((string) ($dailyStream['template'] ?? 'empty')) ?></span>
                            </div>
                        </div>

                        <div class="str3m-player__dock">
                            <section class="str3m-player__transport" aria-labelledby="str3m-player-transport-title">
                                <div class="str3m-player__section-topline">
                                    <h5 id="str3m-player-transport-title">Transport</h5>
                                    <span class="str3m-player__state" data-str3m-player-status><?= $dailyAudioHasSource ? 'prêt' : 'veille' ?></span>
                                </div>

                                <div class="str3m-player__buttons">
                                    <button type="button" class="str3m-player__button" data-str3m-player-back aria-label="Reculer de cinq secondes"<?= $dailyAudioHasSource ? '' : ' disabled' ?>>−5 s</button>
                                    <button type="button" class="str3m-player__button str3m-player__button--primary" data-str3m-player-toggle aria-label="Lecture ou pause"<?= $dailyAudioHasSource ? '' : ' disabled' ?>>lecture</button>
                                    <button type="button" class="str3m-player__button" data-str3m-player-forward aria-label="Avancer de cinq secondes"<?= $dailyAudioHasSource ? '' : ' disabled' ?>>+5 s</button>
                                </div>

                                <label class="str3m-player__range-wrap">
                                    <span class="sr-only">Progression du str3m</span>
                                    <input type="range" min="0" max="1" step="0.001" value="0" data-str3m-player-progress<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                </label>

                                <div class="str3m-player__times" aria-live="polite">
                                    <span data-str3m-player-current>00:00</span>
                                    <span data-str3m-player-duration>00:00</span>
                                </div>
                            </section>

                            <section class="str3m-player__controls" aria-labelledby="str3m-player-controls-title">
                                <div class="str3m-player__section-topline">
                                    <h5 id="str3m-player-controls-title">Lecture</h5>
                                    <output class="str3m-player__rate" data-str3m-player-rate-output>1.00×</output>
                                </div>

                                <div class="str3m-player__buttons str3m-player__buttons--compact">
                                    <button type="button" class="str3m-player__button" data-str3m-player-rate-step="-0.25"<?= $dailyAudioHasSource ? '' : ' disabled' ?>>−</button>
                                    <button type="button" class="str3m-player__button" data-str3m-player-rate-step="0.25"<?= $dailyAudioHasSource ? '' : ' disabled' ?>>+</button>
                                    <button type="button" class="str3m-player__button" data-str3m-player-reset<?= $dailyAudioHasSource ? '' : ' disabled' ?>>reset</button>
                                </div>

                                <label class="str3m-player__toggle">
                                    <input type="checkbox" data-str3m-player-preserve-pitch checked<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                    <span>Conserver la hauteur</span>
                                </label>
                            </section>

                            <section class="str3m-player__eq" aria-labelledby="str3m-player-eq-title">
                                <div class="str3m-player__section-topline">
                                    <h5 id="str3m-player-eq-title">EQ audio</h5>
                                    <span class="str3m-player__eq-state" data-str3m-player-eq-state><?= $dailyAudioHasSource ? 'actif' : 'hors source' ?></span>
                                </div>

                                <div class="str3m-player__sliders">
                                    <label class="str3m-player__slider">
                                        <span>Bass <output data-str3m-player-bass-value>0.0 dB</output></span>
                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-bass<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                    </label>
                                    <label class="str3m-player__slider">
                                        <span>Mid <output data-str3m-player-mid-value>0.0 dB</output></span>
                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-mid<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                    </label>
                                    <label class="str3m-player__slider">
                                        <span>Treble <output data-str3m-player-treble-value>0.0 dB</output></span>
                                        <input type="range" min="-12" max="12" step="0.5" value="0" data-str3m-player-treble<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                    </label>
                                    <label class="str3m-player__slider">
                                        <span>Gain <output data-str3m-player-gain-value>100%</output></span>
                                        <input type="range" min="0" max="150" step="1" value="100" data-str3m-player-gain<?= $dailyAudioHasSource ? '' : ' disabled' ?>>
                                    </label>
                                </div>
                            </section>

                            <section class="str3m-player__status-panel" aria-labelledby="str3m-player-status-title">
                                <div class="str3m-player__section-topline">
                                    <h5 id="str3m-player-status-title">État</h5>
                                </div>
                                <div class="str3m-player__status-grid">
                                    <p><span>Moteur</span><strong data-str3m-player-engine><?= $dailyAudioHasSource ? 'web en attente' : 'veille' ?></strong></p>
                                    <p><span>Sortie</span><strong data-str3m-player-output><?= $dailyAudioHasSource ? 'intégrée' : 'veille' ?></strong></p>
                                    <p><span>Média</span><strong data-str3m-player-source-state><?= $dailyAudioHasSource ? 'annoncée' : 'aucune source' ?></strong></p>
                                    <p><span>Source</span><strong data-str3m-player-source><?= $dailyAudioHasSource ? h($dailyAudioTitle) : 'aucune nappe' ?></strong></p>
                                    <p><span>Vitesse</span><strong data-str3m-player-rate-state>1.00×</strong></p>
                                    <p><span>EQ</span><strong data-str3m-player-summary>plat</strong></p>
                                    <p><span>Raccourcis</span><strong>Espace · ← → · ±</strong></p>
                                </div>
                                <div class="str3m-player__status-actions">
                                    <a
                                        class="str3m-player__status-link"
                                        data-str3m-player-open
                                        href="<?= $dailyAudioHasSource ? h($dailyAudioPath) : '#' ?>"
                                        target="_blank"
                                        rel="noreferrer"
                                        <?= $dailyAudioHasSource ? '' : 'hidden aria-hidden="true"' ?>
                                    >ouvrir la source</a>
                                    <button type="button" class="str3m-player__button" data-str3m-player-retry<?= $dailyAudioHasSource ? '' : ' disabled' ?>>relancer EQ</button>
                                </div>
                            </section>
                        </div>

                        <audio preload="metadata" class="str3m-player__native" data-str3m-player-audio<?= $dailyAudioHasSource ? '' : ' aria-hidden="true"' ?>>
                            <?php if ($dailyAudioHasSource): ?>
                                <source src="<?= h($dailyAudioPath) ?>">
                            <?php endif; ?>
                        </audio>
                    </section>
                </div>
            </section>
        </div>
    </section>

    <section class="panel reveal" aria-labelledby="str3m-signals-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-signals-title">Signaux publics récents</h2>
                <p class="panel-copy">Les traces qu’une terre rend visibles.</p>
            </div>
            <span class="badge"><?= h((string) $publicSignalCount) ?> visible<?= $publicSignalCount > 1 ? 's' : '' ?></span>
        </div>

        <?php if ($publicSignalPreview !== []): ?>
            <div class="public-entry-grid">
                <?php foreach ($publicSignalPreview as $signal): ?>
                    <a class="public-entry-card" href="<?= h($signalItemHref((string) ($signal['id'] ?? ''))) ?>">
                        <strong><?= h((string) ($signal['title'] ?? 'Signal sans titre')) ?></strong>
                        <span><?= h((string) ($signal['excerpt'] ?? 'Trace visible dans le courant.')) ?></span>
                        <span><?= h((string) ($signal['land_username'] ?? $signal['land_slug'] ?? 'terre')) ?> · <?= h(str3m_signal_kind_label((string) ($signal['kind'] ?? 'note'))) ?> · <?= h(human_created_label((string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''))) ?? 'récemment') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="panel-copy">Aucun signal public n’est encore publié. Il suffit d’un premier signal pour faire apparaître une terre ici.</p>
            <div class="action-row">
                <a class="pill-link" href="<?= h($joinHref) ?>">Poser une terre</a>
                <a class="ghost-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
                <a class="ghost-link" href="<?= h($signalHref) ?>">Voir Signal</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel reveal" aria-labelledby="str3m-visible-lands-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-visible-lands-title">Terres visibles maintenant</h2>
                <p class="panel-copy">Les terres qui affleurent déjà dans le public.</p>
            </div>
            <span class="badge"><?= h((string) count($visibleLandPreview)) ?> visible<?= count($visibleLandPreview) > 1 ? 's' : '' ?></span>
        </div>

        <?php if ($visibleLandPreview !== []): ?>
            <div class="public-entry-grid">
                <?php foreach ($visibleLandPreview as $landProfile): ?>
                    <?php $state = (array) ($landProfile['state'] ?? []); ?>
                    <article
                        class="public-entry-card public-entry-card--presence <?= h(str3m_presence_class($state)) ?>"
                        data-presence="<?= h((string) ($state['key'] ?? 'unknown')) ?>"
                        data-shell-future="land"
                        data-shell-source="str3m-visible"
                        data-land-slug="<?= h((string) ($landProfile['slug'] ?? '')) ?>"
                        data-land-label="<?= h((string) ($landProfile['username'] ?? $landProfile['slug'] ?? 'terre')) ?>"
                        data-shell-route="<?= h($landRouteHref((string) ($landProfile['slug'] ?? ''))) ?>"
                        data-shell-manifest-route="<?= h($futureShellRoute) ?>"
                        data-shell-state="<?= h((string) ($state['key'] ?? 'unknown')) ?>"
                    >
                        <strong><?= h((string) ($landProfile['username'] ?? $landProfile['slug'] ?? 'terre')) ?></strong>
                        <span class="presence-chip">
                            <span class="presence-chip__pulse" aria-hidden="true"></span>
                            <span><?= h((string) ($state['label'] ?? 'visible')) ?></span>
                        </span>
                        <span><?= h((string) ($state['summary'] ?? 'Trace visible dans le courant.')) ?></span>
                        <span class="presence-hint"><?= h((string) ($state['hint'] ?? 'indice public')) ?></span>
                        <span class="presence-ledger"><?= h((string) ($landProfile['signal_count'] ?? 0)) ?> signal<?= ((int) ($landProfile['signal_count'] ?? 0)) > 1 ? 'aux' : '' ?> · <?= h((string) ($landProfile['t0k_active_count'] ?? 0)) ?> t0k actif<?= ((int) ($landProfile['t0k_active_count'] ?? 0)) > 1 ? 's' : '' ?> · <?= h((string) ($landProfile['t0k_pending_count'] ?? 0)) ?> en chemin · <?= h((string) ($landProfile['b0t3_count'] ?? 0)) ?> b0t3<?= ((int) ($landProfile['b0t3_count'] ?? 0)) > 1 ? 's' : '' ?><?= !empty($landProfile['latest_at']) ? ' · ' . h(human_created_label((string) $landProfile['latest_at']) ?? 'récemment') : '' ?></span>
                        <span>
                            <a class="pill-link" href="<?= h($landRouteHref((string) ($landProfile['slug'] ?? ''))) ?>">Explorer l'île</a>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="panel-copy">Aucune terre n’affleure encore assez pour cette couche.</p>
            <div class="action-row">
                <a class="pill-link" href="<?= h($guideHref) ?>">Passer par 0wlslw0</a>
                <a class="ghost-link" href="<?= h($joinHref) ?>">Poser une terre</a>
            </div>
        <?php endif; ?>
    </section>

    <details class="panel reveal str3m-exploratory-panel" aria-labelledby="str3m-exploratory-title">
        <summary class="str3m-layer-summary">
            <span class="summary-label">exploratoire</span>
            <strong id="str3m-exploratory-title">Autres couches du courant</strong>
            <span class="str3m-layer-summary__meta"><?= h((string) $archipelagoLandCount) ?> île<?= $archipelagoLandCount > 1 ? 's' : '' ?> · shell · <?= h((string) $recentT0kCount) ?> t0k<?= $recentT0kCount > 1 ? 's' : '' ?> · <?= h((string) $recentB0t3Count) ?> b0t3<?= $recentB0t3Count > 1 ? 's' : '' ?></span>
        </summary>

    <section class="panel reveal" aria-labelledby="str3m-shell-future-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-shell-future-title">Lab · shell porté</h2>
                <p class="panel-copy">Couche exploratoire : tactilité publique et pont vers n0de.</p>
            </div>
            <a class="ghost-link" href="<?= h($futureShellRoute) ?>">Voir n0de</a>
        </div>

        <div class="public-entry-grid">
            <article class="public-entry-card public-entry-card--future-shell">
                <strong>tactilité</strong>
                <span>Hover, focus et appui distinguent déjà les états publics.</span>
            </article>
            <article class="public-entry-card public-entry-card--future-shell">
                <strong>hooks</strong>
                <span>Chaque terre visible expose des métadonnées pour un shell futur.</span>
            </article>
            <article class="public-entry-card public-entry-card--future-shell">
                <strong>n0de</strong>
                <span>Le pont naturel reste l’objet porté : manifest, sync, shell de relation.</span>
            </article>
        </div>
    </section>

    <section class="panel reveal" aria-labelledby="islands-title">
        <div class="section-topline">
            <div>
                <h2 id="islands-title">Archipel</h2>
                <p class="panel-copy">Les terres qui affleurent dans le courant.</p>
            </div>
            <span class="badge"><?= count($archipelagoLands) ?> île<?= count($archipelagoLands) > 1 ? 's' : '' ?></span>
        </div>

        <?php if ($archipelagoLands === []): ?>
            <p class="panel-copy">Aucune terre n’a encore assez laissé de traces pour faire apparaître l’archipel.</p>
        <?php else: ?>
            <?php
            // Calcule des positions 3D en spirale Fibonacci pour l'archipel
            $islandNodes = [];
            $radius = 108;
            
            // Trouver l'île la plus récente
            $newestIslandSlug = '';
            $maxTimestamp = 0;
            foreach ($archipelagoLands as $island) {
                $islandLatestTs = (int) ($island['latest_ts'] ?? 0);
                if ($islandLatestTs > $maxTimestamp) {
                    $maxTimestamp = $islandLatestTs;
                    $newestIslandSlug = $island['slug'];
                }
            }

            foreach (array_values($archipelagoLands) as $index => $island) {
                $fib = str3m_fibonacci_value($index);
                $ring = intdiv($index, 10);
                $stateKey = (string) (($island['state']['key'] ?? ''));
                $stateLift = match ($stateKey) {
                    'present' => -18,
                    'near' => 12,
                    'roaming' => 38,
                    'asleep' => -42,
                    default => 0,
                };
                $r = $radius + min(320, $fib * 9) + ($ring * 96);
                $a = $index * 2.39996; // Angle d'or pour distribution organique
                $x = (int) (cos($a) * $r);
                $z = (int) (sin($a) * $r);
                $y = (int) (((($index % 2) === 0) ? 1 : -1) * min(132, 14 + ($fib * 3)) + $stateLift);
                $islandNodes[] = [
                    'island' => $island,
                    'x' => $x,
                    'y' => $y,
                    'z' => $z
                ];
            }
            ?>
            <div id="archipelago-3d" class="archipelago-3d-container" data-str3m-archipelago>
                <div class="archipelago-instructions" data-str3m-archipelago-hint>Appui long puis glisse · Molette pour avancer · 1 1 2 3 5 8 13</div>
                <div class="archipelago-scene">
                    <?php foreach ($islandNodes as $node): ?>
                        <?php $island = $node['island']; ?>
                        <?php $state = (array) ($island['state'] ?? []); ?>
                        <div
                            class="archipelago-node"
                            data-archipelago-x="<?= h((string) $node['x']) ?>"
                            data-archipelago-y="<?= h((string) $node['y']) ?>"
                            data-archipelago-z="<?= h((string) $node['z']) ?>"
                            data-presence="<?= h((string) ($state['key'] ?? 'unknown')) ?>"
                        >
                            <div class="archipelago-card-wrapper">
                                <article
                                    class="str3m-island-card <?= $island['slug'] === $newestIslandSlug ? 'is-glowing ' : '' ?><?= h(str3m_presence_class($state)) ?>"
                                    data-presence="<?= h((string) ($state['key'] ?? 'unknown')) ?>"
                                    data-shell-future="land"
                                    data-shell-source="archipelago"
                                    data-land-slug="<?= h((string) ($island['slug'] ?? '')) ?>"
                                    data-land-label="<?= h((string) ($island['username'] ?? $island['slug'] ?? 'terre')) ?>"
                                    data-shell-route="<?= h($landRouteHref((string) ($island['slug'] ?? ''))) ?>"
                                    data-shell-manifest-route="<?= h($futureShellRoute) ?>"
                                    data-shell-state="<?= h((string) ($state['key'] ?? 'unknown')) ?>"
                                >
                                    <div>
                                        <span class="summary-label">Terre</span>
                                        <strong class="summary-value"><?= h($island['username']) ?></strong>
                                    </div>
                                    <p class="island-presence">
                                        <span class="presence-chip">
                                            <span class="presence-chip__pulse" aria-hidden="true"></span>
                                            <span><?= h((string) ($state['label'] ?? 'présente')) ?></span>
                                        </span>
                                    </p>
                                    <p class="island-presence-hint"><?= h((string) ($state['hint'] ?? 'indice public · present')) ?></p>
                                    <p class="island-ledger">
                                        <?= h((string) ($island['signal_count'] ?? 0)) ?> signal<?= ((int) ($island['signal_count'] ?? 0)) > 1 ? 'aux' : '' ?>
                                        · <?= h((string) ($island['t0k_active_count'] ?? 0)) ?> t0k actif<?= ((int) ($island['t0k_active_count'] ?? 0)) > 1 ? 's' : '' ?>
                                        · <?= h((string) ($island['t0k_pending_count'] ?? 0)) ?> en chemin
                                        · <?= h((string) ($island['b0t3_count'] ?? 0)) ?> b0t3<?= ((int) ($island['b0t3_count'] ?? 0)) > 1 ? 's' : '' ?>
                                    </p>
                                    <?php if ($island['last_active']): ?>
                                        <p class="island-meta">Dernière trace : <?= h(human_created_label($island['last_active']) ?? 'récemment') ?></p>
                                    <?php endif; ?>
                                    <a class="pill-link" href="<?= h($landRouteHref((string) $island['slug'])) ?>">Explorer l'île</a>
                                </article>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!$archipelagoHasPublishedSignal): ?>
                <p class="panel-copy">Aucune de ces terres n’a encore publié de signal durable. L’archipel montre donc le visible avant publication.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($recentT0ks): ?>
    <section class="panel reveal str3m-t0ks" aria-labelledby="str3m-t0ks-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-t0ks-title">T0ks dans le courant</h2>
                <p class="panel-copy">Les gestes publics.</p>
            </div>
            <a class="ghost-link" href="<?= h($shoreHref) ?>">Mon sh0re</a>
        </div>
        <div class="t0k-stream">
            <?php foreach ($recentT0ks as $t0k): ?>
                <article class="t0k-stream-item t0k-stream-<?= h((string) $t0k['status']) ?>">
                    <a class="t0k-stream-main" href="<?= h($tokenRouteHref((string) $t0k['token'])) ?>">
                        <span class="t0k-stream-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></span>
                    </a>
                    <span class="t0k-stream-route">
                        <a class="ghost-link" href="<?= h($shoreRouteHref((string) $t0k['from_land'])) ?>"><?= h((string) $t0k['from_land']) ?></a>
                        <span>→</span>
                        <a class="ghost-link" href="<?= h($shoreRouteHref((string) $t0k['to_land'])) ?>"><?= h((string) $t0k['to_land']) ?></a>
                    </span>
                    <span class="t0k-stream-status">
                        <a class="ghost-link t0k-stream-status-link" href="<?= h($tokenRouteHref((string) $t0k['token'])) ?>"><?= h(t0k_status_label((string) $t0k['status'])) ?></a>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if ($recentB0t3s): ?>
    <section class="panel reveal str3m-b0t3s" aria-labelledby="str3m-b0t3-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-b0t3-title">B0t3s dans le courant</h2>
                <p class="panel-copy">Lignes déposées.</p>
            </div>
        </div>
        <div class="b0t3-stream-field">
            <?php foreach ($recentB0t3s as $b0t3): ?>
                <a class="b0t3-stream-line"
                   href="<?= h($shoreRouteHref((string) $b0t3['target_land'])) ?>"
                   data-b0t3="<?= h((string) $b0t3['text']) ?>"
                   data-b0t3-instability="<?= h((string) $b0t3['instability']) ?>"
                   title="<?= h((string) $b0t3['target_land']) ?> · <?= h((string) $b0t3['kind']) ?>"
                ><?= h((string) $b0t3['text']) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    </details>

    <aside class="str3m-shell-ghost-dock" data-str3m-shell-ghost hidden aria-live="polite" aria-label="Shell fantôme en devenir">
        <div class="str3m-shell-ghost-dock__head">
            <div>
                <p class="eyebrow">shell fantôme</p>
                <p class="str3m-shell-ghost-dock__kicker">piloter l’oiseau du shell</p>
            </div>
            <span class="str3m-shell-ghost-dock__mode" data-str3m-shell-ghost-mode>au repos</span>
        </div>
        <div class="str3m-shell-ghost-bird" data-str3m-shell-ghost-bird aria-hidden="true">
            <span class="str3m-shell-ghost-bird__glyph str3m-shell-ghost-bird__glyph--wing" data-boussole="direction">&gt;</span>
            <span class="str3m-shell-ghost-bird__glyph str3m-shell-ghost-bird__glyph--eye" data-boussole="energy-left">°</span>
            <span class="str3m-shell-ghost-bird__glyph str3m-shell-ghost-bird__glyph--beak" data-boussole="mood">v</span>
            <span class="str3m-shell-ghost-bird__glyph str3m-shell-ghost-bird__glyph--eye" data-boussole="energy-right">°</span>
            <span class="str3m-shell-ghost-bird__glyph str3m-shell-ghost-bird__glyph--wing" data-boussole="return">&lt;</span>
        </div>
        <div class="str3m-shell-ghost-dock__body">
            <strong data-str3m-shell-ghost-label>aucune terre armée</strong>
            <p class="str3m-shell-ghost-dock__state" data-str3m-shell-ghost-state>Survole ou touche une terre visible pour préparer un futur shell porté.</p>
            <p class="str3m-shell-ghost-dock__meta" data-str3m-shell-ghost-meta>manifest n0de · route · état public</p>
            <div class="str3m-shell-ghost-dock__actions">
                <a class="pill-link" href="<?= h($futureShellRoute) ?>" data-str3m-shell-ghost-manifest>Voir n0de</a>
                <a class="ghost-link" href="<?= h($str3mHref) ?>" data-str3m-shell-ghost-route>Rester dans le courant</a>
            </div>
        </div>
    </aside>

</main>
</body>
</html>
