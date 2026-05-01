<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/0wlslw0');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/0wlslw0'), PHP_URL_PATH) ?: '/0wlslw0';
if ($requestPath === '/0wlslw0.php') {
    header('Location: /0wlslw0', true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$authenticatedLand = current_authenticated_land();
$ambientProfile = $authenticatedLand ? land_visual_profile($authenticatedLand) : land_collective_profile('calm');
$agentUrl = trim((string) ((getenv('SOWWWL_0WLSLW0_CHAT_URL') ?: getenv('SOWWWL_0WLSLW0_AGENT_URL')) ?: ''));
$guideMode = $agentUrl !== '' ? 'agent relaye' : 'guide local';
$canonicalOrigin = rtrim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com'), '/');
$openLandHref = $authenticatedLand
    ? '/land.php?u=' . rawurlencode((string) $authenticatedLand['slug'])
    : $canonicalOrigin . '/#poser';
$openLandLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Poser une terre';
$paths = guide_paths();
$principles = guide_principles();
$glossary = guide_glossary();
$steps = guide_creation_steps();
$faq = guide_faq_items();
$promptSeeds = guide_prompt_seeds();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="0wlslw0 — concierge d entree pour comprendre <?= h($brandDomain) ?> et poser une terre sans se perdre.">
    <meta name="theme-color" content="#09090b">
    <title>0wlslw0 — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience guide-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', '0wlslw0') ?>

<main class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>0wlslw0</strong> <span>concierge d entree</span></p>
        <h1 class="land-title">
            <strong>Entrer sans se perdre.</strong>
            <span>porte oblique / guide de creation</span>
        </h1>
        <p class="lead">0wlslw0 accueille les visiteurs, explique le projet, puis les oriente vers la bonne porte avant qu’ils n’ouvrent une terre.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="<?= h($canonicalOrigin . '/') ?>">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="<?= h($openLandHref) ?>"><?= h($openLandLabel) ?></a>
            <?php if ($authenticatedLand): ?>
                <span class="meta-pill">terre liee : <?= h((string) $authenticatedLand['slug']) ?></span>
            <?php else: ?>
                <span class="meta-pill">visite publique</span>
            <?php endif; ?>
            <span class="meta-pill"><?= h($guideMode) ?></span>
            <?php if ($agentUrl !== ''): ?>
                <a class="meta-pill meta-pill-link" href="<?= h($agentUrl) ?>" target="_blank" rel="noopener">ouvrir l agent</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="guide-grid">
        <section class="panel reveal guide-panel" aria-labelledby="guide-role-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-role-title">Ce que fait 0wlslw0</h2>
                    <p class="panel-copy">Un accueil plus clair que mystique, sans perdre la tonalité du projet.</p>
                </div>
                <span class="badge"><?= h($guideMode) ?></span>
            </div>

            <div class="summary-grid guide-summary-grid">
                <article class="summary-card">
                    <span class="summary-label">Projet</span>
                    <strong class="summary-value summary-value-small">O.</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">Mission</span>
                    <strong class="summary-value summary-value-small">orienter</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">Issue</span>
                    <strong class="summary-value summary-value-small">poser une terre</strong>
                </article>
            </div>

            <ul class="guide-list" aria-label="Roles de 0wlslw0">
                <li>Expliquer O. en langage simple, sans supposer que le visiteur connaît déjà la cosmologie du projet.</li>
                <li>Qualifier l’intention du visiteur : comprendre, lire publiquement, poser une terre, ou retrouver une terre existante.</li>
                <li>Envoyer vers la bonne porte avec le moins de friction possible.</li>
            </ul>

            <div class="action-row">
                <a class="pill-link" href="<?= h($canonicalOrigin . '/#poser') ?>">Commencer la creation</a>
                <a class="ghost-link" href="<?= h($canonicalOrigin . '/str3m') ?>">Visiter publiquement</a>
                <a class="ghost-link" href="<?= h($canonicalOrigin . '/aza.php') ?>">Lire aZa</a>
                <?php if ($agentUrl !== ''): ?>
                    <a class="ghost-link" href="<?= h($agentUrl) ?>" target="_blank" rel="noopener">Parler a 0wlslw0</a>
                <?php endif; ?>
            </div>
        </section>

        <aside class="panel reveal guide-panel" aria-labelledby="guide-state-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-state-title">Etat du passage</h2>
                    <p class="panel-copy">Une porte pour les hésitations ordinaires.</p>
                </div>
            </div>

            <div class="guide-console" aria-label="Console de guidage">
                <p>[role] accueil / clarification / orientation</p>
                <p>[public] oui, lecture et guidage</p>
                <p>[signup] vers le noyau et la creation de terre</p>
                <p>[archives] vers aZa en lecture publique</p>
                <p>[agent] <?= h($agentUrl !== '' ? 'relié au public' : 'encore privé ou non branché') ?></p>
            </div>

            <p class="panel-copy guide-embed-note">
                <?= $agentUrl !== ''
                    ? 'L’agent public est déjà relié. Cette page garde la logique d’orientation et ouvre le relais conversationnel.'
                    : 'La page fonctionne déjà seule. Quand l’agent DigitalOcean sera public, il suffira de relier son URL pour ajouter le relais conversationnel.' ?>
            </p>
        </aside>
    </section>

    <section class="panel reveal" aria-labelledby="guide-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-meaning-title">Donner un sens stable</h2>
                <p class="panel-copy">Le projet devient plus lisible quand ses principes sont nommés explicitement.</p>
            </div>
        </div>

        <div class="guide-cards">
            <?php foreach ($principles as $principle): ?>
                <article class="guide-card guide-card-static">
                    <span class="summary-label"><?= h((string) $principle['label']) ?></span>
                    <strong><?= h((string) $principle['title']) ?></strong>
                    <p><?= h((string) $principle['copy']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel reveal" aria-labelledby="guide-paths-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-paths-title">Choisir un passage</h2>
                <p class="panel-copy">Les trajets à proposer en premier à un visiteur.</p>
            </div>
        </div>

        <div class="guide-cards">
            <?php foreach ($paths as $path): ?>
                <?php
                $pathHref = (string) ($path['href'] ?? '/');
                if ($pathHref !== '' && $pathHref[0] === '/') {
                    $pathHref = $canonicalOrigin . $pathHref;
                }
                ?>
                <a class="guide-card" href="<?= h($pathHref) ?>">
                    <span class="summary-label"><?= h($path['label']) ?></span>
                    <strong><?= h($path['title']) ?></strong>
                    <p><?= h($path['copy']) ?></p>
                    <span class="ghost-link"><?= h($path['cta']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="guide-grid">
        <section class="panel reveal guide-panel" aria-labelledby="guide-steps-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-steps-title">Poser une terre</h2>
                    <p class="panel-copy">Le chemin de création à faire comprendre avant de demander quoi que ce soit.</p>
                </div>
            </div>

            <ol class="guide-steps">
                <?php foreach ($steps as $step): ?>
                    <li>
                        <span class="summary-label"><?= h($step['label']) ?></span>
                        <strong><?= h($step['title']) ?></strong>
                        <p><?= h($step['copy']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>

            <div class="action-row">
                <a class="pill-link" href="<?= h($canonicalOrigin . '/#poser') ?>">Ouvrir le formulaire</a>
                <?php if ($authenticatedLand): ?>
                    <a class="ghost-link" href="<?= h($openLandHref) ?>">Retour à ma terre</a>
                <?php else: ?>
                    <a class="ghost-link" href="<?= h($canonicalOrigin . '/') ?>">Voir le noyau avant de choisir</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel reveal guide-panel" aria-labelledby="guide-faq-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-faq-title">Questions frequentes</h2>
                    <p class="panel-copy">Le genre de questions que 0wlslw0 doit absorber sans fatiguer le visiteur.</p>
                </div>
            </div>

            <div class="guide-faq">
                <?php foreach ($faq as $item): ?>
                    <details class="aza-details">
                        <summary><?= h($item['question']) ?></summary>
                        <p class="panel-copy"><?= h($item['answer']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    </section>

    <section class="panel reveal" aria-labelledby="guide-glossary-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-glossary-title">Glossaire court</h2>
                <p class="panel-copy">Le vocabulaire minimum pour que le projet ne ressemble pas à une énigme inutile.</p>
            </div>
        </div>

        <div class="guide-glossary-grid">
            <?php foreach ($glossary as $entry): ?>
                <article class="summary-card guide-glossary-card">
                    <span class="summary-label"><?= h((string) $entry['term']) ?></span>
                    <p><?= h((string) $entry['meaning']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel reveal guide-panel" aria-labelledby="guide-prompts-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-prompts-title">Phrases de départ</h2>
                <p class="panel-copy">Si tu branches l’agent, ce sont de bons premiers messages proposés aux visiteurs.</p>
            </div>
        </div>

        <ul class="guide-prompt-list">
            <?php foreach ($promptSeeds as $prompt): ?>
                <li><code><?= h($prompt) ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
