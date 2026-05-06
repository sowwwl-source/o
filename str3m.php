<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/str3m_media.php';
require_once __DIR__ . '/lib/str3m_daily.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Str3m — explorer le courant quotidien et les îles dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Str3m — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body class="experience str3m-view">
<?= render_skip_link() ?>
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
                                    <p><span>Source</span><strong data-str3m-player-source><?= $dailyAudioHasSource ? h($dailyAudioTitle) : 'aucune nappe' ?></strong></p>
                                    <p><span>Vitesse</span><strong data-str3m-player-rate-state>1.00×</strong></p>
                                    <p><span>EQ</span><strong data-str3m-player-summary>plat</strong></p>
                                    <p><span>Raccourcis</span><strong>Espace · ← →</strong></p>
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
            <div id="archipelago-3d" class="archipelago-3d-container" data-str3m-archipelago>
                <div class="archipelago-instructions" data-str3m-archipelago-hint>Appui long puis glisse · Molette pour avancer</div>
                <div class="archipelago-scene">
                    <?php foreach ($islandNodes as $node): ?>
                        <?php $island = $node['island']; ?>
                        <div
                            class="archipelago-node"
                            data-archipelago-x="<?= h((string) $node['x']) ?>"
                            data-archipelago-y="<?= h((string) $node['y']) ?>"
                            data-archipelago-z="<?= h((string) $node['z']) ?>"
                        >
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
        <?php endif; ?>
    </section>

    <?php if ($recentT0ks): ?>
    <section class="panel reveal str3m-t0ks" aria-labelledby="str3m-t0ks-title">
        <div class="section-topline">
            <div>
                <h2 id="str3m-t0ks-title">T0ks dans le courant</h2>
                <p class="panel-copy">Les gestes publics. Voulez-vous grandir avec moi ?</p>
            </div>
            <a class="ghost-link" href="/sh0re">Mon sh0re</a>
        </div>
        <div class="t0k-stream">
            <?php foreach ($recentT0ks as $t0k): ?>
                <article class="t0k-stream-item t0k-stream-<?= h((string) $t0k['status']) ?>">
                    <a class="t0k-stream-main" href="/n?t=<?= h((string) $t0k['token']) ?>">
                        <span class="t0k-stream-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></span>
                    </a>
                    <span class="t0k-stream-route">
                        <a class="ghost-link" href="/sh0re?u=<?= rawurlencode((string) $t0k['from_land']) ?>"><?= h((string) $t0k['from_land']) ?></a>
                        <span>→</span>
                        <a class="ghost-link" href="/sh0re?u=<?= rawurlencode((string) $t0k['to_land']) ?>"><?= h((string) $t0k['to_land']) ?></a>
                    </span>
                    <span class="t0k-stream-status">
                        <a class="ghost-link t0k-stream-status-link" href="/n?t=<?= h((string) $t0k['token']) ?>"><?= h(t0k_status_label((string) $t0k['status'])) ?></a>
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
                <p class="panel-copy">Lignes déposées. Touche long ou clic pour déformer.</p>
            </div>
        </div>
        <div class="b0t3-stream-field">
            <?php foreach ($recentB0t3s as $b0t3): ?>
                <a class="b0t3-stream-line"
                   href="/sh0re.php?u=<?= rawurlencode((string) $b0t3['target_land']) ?>"
                   data-b0t3="<?= h((string) $b0t3['text']) ?>"
                   data-b0t3-instability="<?= h((string) $b0t3['instability']) ?>"
                   title="<?= h((string) $b0t3['target_land']) ?> · <?= h((string) $b0t3['kind']) ?>"
                ><?= h((string) $b0t3['text']) ?></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>
</body>
</html>
