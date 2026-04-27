<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/signal.php');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$csrfToken = csrf_token();
$land = current_authenticated_land();
$ambientProfile = $land ? land_visual_profile($land) : land_collective_profile('dense');
$publicSignals = list_public_signals();
$mySignals = $land ? list_signals((string) $land['slug']) : [];

$errorCode = trim((string) ($_GET['error'] ?? ''));
$message = '';
$messageType = 'info';

if ($errorCode !== '') {
    $messageType = 'warning';
    $message = match ($errorCode) {
        'auth' => 'Ouvre une terre pour transmettre un signal.',
        'csrf' => 'Le jeton de session a expiré. Recharge la page et réessaie.',
        'validation' => 'Le signal n’a pas pu être validé. Vérifie le contenu.',
        'storage' => 'Le signal n’a pas pu être écrit pour le moment.',
        default => 'Le signal n’a pas pu être transmis.',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Signal — flux de transmission minimal sur <?= h($brandDomain) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Signal — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience signal-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'signal') ?>

<main class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>signal</strong> <span>transmission située</span></p>
        <h1 class="land-title signal-title">
            <strong>Émettre. Recevoir. Laisser trace.</strong>
            <span>I inverse</span>
        </h1>
        <p class="lead">Une phrase peut circuler, rester close, ou se déposer au large d’une terre.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <?php if ($land): ?>
                <span class="meta-pill">terre liée : <?= h((string) $land['slug']) ?></span>
                <a class="meta-pill meta-pill-link" href="/land.php?u=<?= rawurlencode((string) $land['slug']) ?>">ouvrir la terre</a>
            <?php else: ?>
                <span class="meta-pill">lecture publique seulement</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <section class="panel reveal">
            <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                <p><?= h($message) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="signal-grid">
        <section class="panel reveal signal-col" aria-labelledby="signal-compose-title">
            <div class="section-topline">
                <div>
                    <h2 id="signal-compose-title">Émettre</h2>
                    <p class="panel-copy"><?= $land ? 'Depuis ta terre, sans bruit inutile.' : 'Une terre ouvre la voix d’émission.' ?></p>
                </div>
                <?php if ($land): ?>
                    <span class="badge">session active</span>
                <?php endif; ?>
            </div>

            <?php if ($land): ?>
                <form action="/signal_post.php" method="post" class="land-form signal-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                    <label>
                        Titre
                        <input type="text" name="title" maxlength="120" placeholder="Nouvelle trace…">
                        <span class="input-hint">Optionnel.</span>
                    </label>

                    <label>
                        Contenu
                        <textarea name="body" rows="6" required placeholder="L'empreinte à laisser."></textarea>
                    </label>

                    <div class="signal-form-grid">
                        <label>
                            Type
                            <select name="kind">
                                <?php foreach (SIGNAL_KINDS as $kind): ?>
                                    <option value="<?= h($kind) ?>"><?= h($kind) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Visibilité
                            <select name="visibility">
                                <option value="public">public</option>
                                <option value="unlisted">non répertorié</option>
                                <option value="private" selected>privé</option>
                            </select>
                        </label>

                        <label>
                            Statut
                            <select name="status">
                                <option value="draft" selected>brouillon</option>
                                <option value="published">publié</option>
                            </select>
                        </label>
                    </div>

                    <label>
                        Tags
                        <input type="text" name="tags" placeholder="archive, note, rive, ...">
                        <span class="input-hint">Sépare-les par des virgules.</span>
                    </label>

                    <button type="submit">Transmettre</button>
                </form>

                <div class="signal-list-block">
                    <div class="section-topline signal-subhead">
                        <div>
                            <h2>Mes traces</h2>
                            <p class="panel-copy">Brouillons, retraits, publications : un même courant, plusieurs intensités.</p>
                        </div>
                        <span class="badge"><?= h((string) count($mySignals)) ?> entrée<?= count($mySignals) > 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (!$mySignals): ?>
                        <p class="panel-copy">Aucune trace émise.</p>
                    <?php else: ?>
                        <div class="signal-cards">
                            <?php foreach ($mySignals as $signal): ?>
                                <article class="signal-card">
                                    <div class="signal-card-topline">
                                        <span class="summary-label"><?= h((string) $signal['kind']) ?></span>
                                        <span class="signal-state">[<?= h((string) $signal['visibility']) ?> / <?= h((string) $signal['status']) ?>]</span>
                                    </div>
                                    <h3><a href="/signal_item.php?id=<?= rawurlencode((string) $signal['id']) ?>"><?= h((string) $signal['title']) ?></a></h3>
                                    <?php if (!empty($signal['excerpt'])): ?>
                                        <p class="signal-card-copy"><?= h((string) $signal['excerpt']) ?></p>
                                    <?php endif; ?>
                                    <p class="signal-card-meta"><?= h(human_created_label((string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''))) ?? 'maintenant') ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="panel-copy">Le flux public s'écoule. Pose une terre pour y laisser une trace.</p>
                <div class="action-row">
                    <a class="pill-link" href="/">Ouvrir une terre</a>
                </div>
            <?php endif; ?>
        </section>

        <aside class="panel reveal signal-col" aria-labelledby="signal-public-title">
            <div class="section-topline">
                <div>
                    <h2 id="signal-public-title">Flux public</h2>
                    <p class="panel-copy">Ce qui circule sans adresse fermée.</p>
                </div>
                <span class="badge"><?= h((string) count($publicSignals)) ?> visible<?= count($publicSignals) > 1 ? 's' : '' ?></span>
            </div>

            <?php if (!$publicSignals): ?>
                <p class="panel-copy">Le flux est silencieux pour l’instant.</p>
            <?php else: ?>
                <div class="signal-cards">
                    <?php foreach ($publicSignals as $signal): ?>
                        <article class="signal-card">
                            <div class="signal-card-topline">
                                <span class="summary-label"><?= h((string) $signal['kind']) ?></span>
                                <span class="signal-state"><?= h((string) $signal['land_username']) ?></span>
                            </div>
                            <h3><a href="/signal_item.php?id=<?= rawurlencode((string) $signal['id']) ?>"><?= h((string) $signal['title']) ?></a></h3>
                            <?php if (!empty($signal['excerpt'])): ?>
                                <p class="signal-card-copy"><?= h((string) $signal['excerpt']) ?></p>
                            <?php endif; ?>
                            <p class="signal-card-meta"><?= h(human_created_label((string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''))) ?? 'maintenant') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </section>
</main>
</body>
</html>
