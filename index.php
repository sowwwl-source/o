<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$message = '';
$messageType = 'info';
$timezoneSuggestions = [
    'Europe/Paris',
    'Europe/London',
    'America/New_York',
    'America/Los_Angeles',
    'America/Montreal',
    'Africa/Casablanca',
    'Asia/Tokyo',
    'Asia/Bangkok',
];
$csrfToken = csrf_token();
$form = [
    'username' => '',
    'timezone' => DEFAULT_TIMEZONE,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');

    if ($form['username'] === '' || $form['timezone'] === '') {
        $message = 'Rien n’est obligatoire, mais quelque chose est nécessaire.';
        $messageType = 'warning';
    } else {
        try {
            guard_land_creation_request($csrfCandidate, $honeypot);
            $land = create_land($form['username'], $form['timezone']);
            header('Location: /land.php?u=' . urlencode((string) $land['slug']) . '&created=1', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $messageType = 'warning';
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $messageType = 'warning';
        }
    }
}

remember_form_rendered_at();

$pulse = land_pulse();
$previewSlug = preview_land_slug($form['username']);
$previewTimezone = $form['timezone'] !== '' ? $form['timezone'] : DEFAULT_TIMEZONE;
$originBase = site_origin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="sowwwl.xyz — Just the Three of Us. O.n0uSnoImenT.">
    <meta name="theme-color" content="#09090b">
    <title>sowwwl.xyz — O.</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/styles.css">
    <script defer src="/main.js"></script>
</head>
<body class="experience home">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout">
    <header class="hero reveal">
        <span class="eyebrow eyebrow-pill">sowwwl.xyz / user ingress</span>
        <div class="hero-grid">
            <section class="hero-copy">
                <h1><span>Pose ta terre.</span> <em>Garde ton rythme.</em></h1>
                <p class="vortex" aria-hidden="true">(.0.)</p>
                <p class="lead">
                    Une porte plus intime que sowwwl.cloud, plus simple qu’un produit complet.
                    Tu poses un nom, tu fixes ton fuseau, et ton espace existe déjà.
                </p>
                <div class="hero-actions">
                    <a class="pill-link" href="#poser">Créer mon espace</a>
                    <a class="ghost-link" href="#surface">Voir la surface</a>
                </div>

                <nav class="hero-nav" aria-label="Promesse du shell">
                    <a class="nav-card" href="#poser">
                        <strong>2 champs</strong>
                        <span>Nom d’usage et fuseau. Rien de plus pour entrer.</span>
                    </a>
                    <a class="nav-card" href="#surface">
                        <strong>Temps vivant</strong>
                        <span>Le shell te montre immédiatement ton heure locale.</span>
                    </a>
                    <a class="nav-card" href="#pulse">
                        <strong>Lien direct</strong>
                        <span>Une terre personnelle et partageable, prête tout de suite.</span>
                    </a>
                </nav>
            </section>

            <aside class="hero-aside">
                <div class="status-card status-card-primary">
                    <div class="status-label">Mode actif</div>
                    <div class="status-value"><strong>Private shell</strong> sans tunnel inutile</div>
                    <p class="status-meta">
                        Inspiré par sowwwl.cloud pour la clarté et 0.user.o.sowwwl.cloud
                        pour l’intimité du shell. Ici, l’inscription est la page.
                    </p>
                </div>

                <section class="signup-shell" id="poser" aria-labelledby="install-title">
                    <div class="signup-head">
                        <div>
                            <h2 id="install-title">Inscription calme</h2>
                            <p class="panel-copy">Une terre légère, une porte propre, un rythme à toi.</p>
                        </div>
                        <span class="badge badge-warm">sans mot de passe</span>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                            <p><?= h($message) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="land-form" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

                        <div class="form-trap" aria-hidden="true">
                            <label>
                                Site web
                                <input type="text" name="website" tabindex="-1" autocomplete="off">
                            </label>
                        </div>

                        <label>
                            Nom d’usage
                            <input
                                type="text"
                                name="username"
                                placeholder="ex: nox"
                                required
                                minlength="2"
                                maxlength="42"
                                value="<?= h($form['username']) ?>"
                                data-username-input
                            >
                            <span class="input-hint">Choisis un nom simple, mémorable, vivant.</span>
                        </label>

                        <label>
                            Fuseau horaire
                            <div class="input-stack">
                                <input
                                    type="text"
                                    name="timezone"
                                    placeholder="ex: Europe/Paris"
                                    required
                                    value="<?= h($previewTimezone) ?>"
                                    data-timezone-input
                                    list="timezone-suggestions"
                                >
                                <button type="button" class="inline-action" data-use-local-timezone>Utiliser mon fuseau</button>
                            </div>
                            <span class="input-hint">Le shell l’utilise pour animer ton temps local.</span>
                            <span class="field-status" data-timezone-status>Choisis un fuseau IANA valide ou utilise la détection locale.</span>
                        </label>

                        <div class="timezone-picks" aria-label="Fuseaux fréquents">
                            <?php foreach ($timezoneSuggestions as $timezoneSuggestion): ?>
                                <button
                                    type="button"
                                    class="timezone-chip"
                                    data-timezone-chip="<?= h($timezoneSuggestion) ?>"
                                ><?= h($timezoneSuggestion) ?></button>
                            <?php endforeach; ?>
                        </div>

                        <datalist id="timezone-suggestions">
                            <?php foreach ($timezoneSuggestions as $timezoneSuggestion): ?>
                                <option value="<?= h($timezoneSuggestion) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <button type="submit">Entrer dans O.</button>
                    </form>

                    <div
                        class="signup-preview"
                        data-origin-base="<?= h($originBase) ?>"
                        data-preview-shell
                    >
                        <span class="summary-label">Aperçu immédiat</span>
                        <strong class="preview-title" data-slug-output><?= h($previewSlug) ?></strong>
                        <div class="preview-grid">
                            <p><span>Lien</span><code data-land-link-output><?= h($originBase . '/land.php?u=' . $previewSlug) ?></code></p>
                            <p><span>Email virtuel</span><code data-email-output><?= h($previewSlug . '@o.local') ?></code></p>
                            <p><span>Fuseau</span><strong data-preview-timezone><?= h($previewTimezone) ?></strong></p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </header>

    <section class="panel reveal surface-panel" id="surface" aria-labelledby="surface-title">
        <div class="section-topline">
            <div>
                <h2 id="surface-title">Surface de contrôle</h2>
                <p class="panel-copy">Une lecture simple du noyau, du temps et de la promesse d’entrée.</p>
            </div>
            <span class="badge"><?= h(SITE_DOMAIN) ?></span>
        </div>

        <div class="surface-grid">
            <section class="telemetry-block" aria-labelledby="telemetry-title">
                <h3 id="telemetry-title">Noyau</h3>
                <div class="data-grid telemetry-grid">
                    <p>&gt; INITIALISATION : <span class="highlight">H.°bO</span></p>
                    <p>&gt; DOMAINE : <span class="highlight"><?= h(SITE_DOMAIN) ?></span></p>
                    <p>&gt; PASSERELLE : <span class="highlight">0.user.o.sowwwl.cloud → sowwwl.xyz</span></p>
                    <p>&gt; MODE : <span class="highlight">file-backed constellation</span> | SÉCURITÉ : <span class="highlight">xXx</span></p>
                    <p class="bootline" id="bootline">[ L'aspiration est en cours... George Duke is ON. ]</p>
                </div>
            </section>

            <section class="clock-shell" aria-labelledby="signals-title">
                <div>
                    <h3 id="signals-title">Signal vivant</h3>
                    <p class="panel-copy">Prévisualisation locale du temps selon le fuseau saisi.</p>
                </div>
                <div
                    class="clock"
                    aria-live="polite"
                    data-live-clock
                    data-preview-clock
                    data-timezone="<?= h($previewTimezone) ?>"
                >
                    <p class="clock-label" data-clock-label>Fuseau : —</p>
                    <p class="clock-time" data-clock-time>--:--:--</p>
                    <p class="clock-date" data-clock-date>--</p>
                </div>
            </section>
        </div>
    </section>

    <section class="panel reveal flow-panel" aria-labelledby="flow-title">
        <div class="section-topline">
            <div>
                <h2 id="flow-title">Ce que l’inscription fait vraiment</h2>
                <p class="panel-copy">On enlève le bruit: juste les éléments nécessaires pour qu’une terre existe.</p>
            </div>
        </div>

        <div class="steps-grid">
            <article class="step-card">
                <span class="step-index">01</span>
                <h3>Nommer</h3>
                <p>Ton nom d’usage devient un slug propre et une porte stable dans le shell.</p>
            </article>
            <article class="step-card">
                <span class="step-index">02</span>
                <h3>Cadencer</h3>
                <p>Le fuseau donne un rythme vivant à la page et prépare l’espace à t’accueillir.</p>
            </article>
            <article class="step-card">
                <span class="step-index">03</span>
                <h3>Entrer</h3>
                <p>Une fois posée, la terre a déjà son lien, son temps local et son identité minimale.</p>
            </article>
        </div>
    </section>

    <section class="panel reveal pulse" id="pulse" aria-labelledby="pulse-title">
        <div class="section-topline">
            <div>
                <h2 id="pulse-title">Pouls de la constellation</h2>
                <p class="panel-copy">Une mesure légère, sans base de données, juste assez pour sentir la présence.</p>
            </div>
            <span class="badge"><?= (int) $pulse['count'] ?> terres</span>
        </div>

        <div class="metric-grid">
            <article class="metric-card">
                <span class="metric-label">Terres posées</span>
                <strong class="metric-value"><?= (int) $pulse['count'] ?></strong>
                <p>Le réseau reste petit, mais il tient.</p>
            </article>
            <article class="metric-card">
                <span class="metric-label">Fuseaux actifs</span>
                <strong class="metric-value"><?= (int) $pulse['timezones'] ?></strong>
                <p>Chaque terre garde son propre rythme.</p>
            </article>
            <article class="metric-card">
                <span class="metric-label">Dernier signal</span>
                <strong class="metric-value metric-value-small">
                    <?= h($pulse['latest_created_label'] ?? 'en attente') ?>
                </strong>
                <p><?= h($pulse['latest_summary']) ?></p>
            </article>
        </div>
    </section>

    <footer class="site-footer reveal">
        <p>sowwwl.xyz tient maintenant comme une vraie porte d’entrée: plus clair, plus désirable, plus prêt à être partagé.</p>
    </footer>
</main>
</body>
</html>
