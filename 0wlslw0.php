<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/meaning.php';
require_once __DIR__ . '/lib/guide_voice.php';

function guide_ascii_tokenize(string $value): string
{
    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }
    }

    $normalized = strtolower($normalized);
    $normalized = str_replace(['&', '@'], [' et ', ' a '], $normalized);
    $normalized = preg_replace('/[^a-z0-9\/' . preg_quote("<>°'", '/') . ']+/', '.', $normalized) ?? $normalized;
    $normalized = preg_replace('/\.{2,}/', '.', $normalized) ?? $normalized;

    return trim($normalized, '.');
}

function guide_ascii_frame(string $label): array
{
    return match (guide_ascii_tokenize($label)) {
        'input' => ["' O >", "< O '"] ,
        'output' => ['. O <', "> O ."],
        'relay' => ['/ O >', '< o /'],
        'privacy' => ['° O .', '. O °'],
        'fallback' => ["' o >", "< o '"] ,
        'role' => ['° o >', '< o °'],
        'public' => ['. O /', '\\ O .'],
        'signup' => ["' O /", "\\ O '"] ,
        'archives' => ['< O /', '\\ o >'],
        'voice' => ['° O /', '\\ O °'],
        'agent' => ['. o >', '< O .'],
        default => ["' o .", ". o '"] ,
    };
}

function guide_ascii_note(string $label, string $value): string
{
    [$lead, $tail] = guide_ascii_frame($label);
    $asciiLabel = guide_ascii_tokenize($label);
    $asciiValue = guide_ascii_tokenize($value);

    return sprintf(
        '<p class="guide-ascii-note" aria-label="%s : %s"><span class="guide-ascii-note__sigil">%s</span><span class="guide-ascii-note__label">° %s °</span><span class="guide-ascii-note__body">/ %s</span><span class="guide-ascii-note__sigil guide-ascii-note__sigil--tail">%s</span></p>',
        h($label),
        h($value),
        h($lead),
        h($asciiLabel),
        h($asciiValue),
        h($tail)
    );
}

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
$voiceState = guide_voice_browser_state($authenticatedLand);
$guideMode = guide_voice_mode_label();
$canonicalOrigin = rtrim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com'), '/');
$openLandHref = $authenticatedLand
    ? '/land.php?u=' . rawurlencode((string) $authenticatedLand['slug'])
    : $canonicalOrigin . '/#poser';
$openLandLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Poser une terre';
$paths = guide_paths();
$principles = guide_principles();
$thresholdModes = guide_threshold_modes();
$languageDoors = guide_language_doors();
$glossary = guide_glossary();
$steps = guide_creation_steps();
$faq = guide_faq_items();
$promptSeeds = guide_prompt_seeds();
$guideStateNotes = [
    ['role', 'accueil / clarification / orientation douce'],
    ['public', 'oui, lecture / ecoute / observation'],
    ['signup', 'vers le noyau et la creation de terre'],
    ['archives', 'vers aZa en lecture publique'],
    ['voice', guide_voice_upstream_configured() ? 'amont ia relaye' : 'fallback local actif'],
    ['agent', $agentUrl !== '' ? 'relais externe visible' : 'pas de relais externe public'],
];
$guideVoiceNotes = [
    ['input', 'micro navigateur'],
    ['output', 'synthese vocale locale'],
    ['relay', guide_voice_upstream_configured() ? 'agent distant via backend' : 'guide local sans endpoint distant'],
    ['voice', 'fr / en / es / pt / it'],
    ['privacy', 'cle gardee serveur'],
    ['fallback', 'orientation locale si l’amont refuse'],
];
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
            <span>porte oblique / guide des passages</span>
        </h1>
        <p class="lead">0wlslw0 accueille les visiteurs, traduit l’atmosphère du projet en gestes simples, puis oriente vers la bonne porte avant qu’une terre n’apparaisse.</p>

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
                    <p class="panel-copy">Un accueil plus fluide, légèrement mystique, mais assez net pour ne pas perdre l’attention.</p>
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
                    <strong class="summary-value summary-value-small">écouter / orienter</strong>
                </article>
                <article class="summary-card">
                    <span class="summary-label">Ouverture</span>
                    <strong class="summary-value summary-value-small">fr / en / es / pt / it</strong>
                </article>
            </div>

            <ul class="guide-list" aria-label="Roles de 0wlslw0">
                <li>Expliquer O. en langage simple, sans supposer que le visiteur connaît déjà la cosmologie du projet.</li>
                <li>Accueillir aussi les intentions incomplètes : comprendre, lire publiquement, poser une terre, retrouver une terre existante, ou simplement sentir le lieu.</li>
                <li>Envoyer vers la bonne porte avec le moins de friction possible, puis se retirer.</li>
            </ul>

            <div class="action-row">
                <a class="pill-link" href="<?= h($canonicalOrigin . '/#poser') ?>">Commencer la creation</a>
                <a class="ghost-link" href="<?= h($canonicalOrigin . '/str3m') ?>">Visiter publiquement</a>
                <a class="ghost-link" href="<?= h($canonicalOrigin . '/aza') ?>">Lire aZa</a>
                <?php if ($agentUrl !== ''): ?>
                    <a class="ghost-link" href="<?= h($agentUrl) ?>" target="_blank" rel="noopener">Parler a 0wlslw0</a>
                <?php endif; ?>
            </div>
        </section>

        <aside class="panel reveal guide-panel" aria-labelledby="guide-state-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-state-title">Etat du passage</h2>
                    <p class="panel-copy">Une porte pour les hésitations ordinaires, les langues mêlées et les intentions encore floues.</p>
                </div>
            </div>

            <div class="guide-console guide-console--encoded" aria-label="Console de guidage">
                <?php foreach ($guideStateNotes as [$label, $value]): ?>
                    <?= guide_ascii_note((string) $label, (string) $value) ?>
                <?php endforeach; ?>
            </div>

            <p class="panel-copy guide-embed-note">
                <?= guide_voice_upstream_configured()
                    ? 'La voix peut déjà relayer un agent amont côté serveur. La page garde l’orientation locale et protège la clé en backend.'
                    : 'La voix fonctionne déjà en guide local. Quand l’endpoint IA sera accessible côté serveur, le relais se branchera sans exposer la clé au navigateur.' ?>
            </p>
        </aside>
    </section>

    <section
        class="panel reveal guide-panel guide-voice-shell"
        aria-labelledby="guide-voice-title"
        data-guide-voice
        data-guide-voice-api="<?= h((string) $voiceState['api_path']) ?>"
        data-guide-voice-csrf="<?= h((string) $voiceState['csrf_token']) ?>"
        data-guide-voice-greeting="<?= h((string) $voiceState['greeting']) ?>"
        data-guide-voice-upstream="<?= !empty($voiceState['upstream_configured']) ? '1' : '0' ?>"
        data-guide-voice-chat-url="<?= h((string) $voiceState['chat_url']) ?>"
    >
        <div class="section-topline">
            <div>
                <h2 id="guide-voice-title">Accompagnement vocal</h2>
                <p class="panel-copy">Ici, 0wlslw0 écoute, répond à voix haute, puis t’oriente sans champ texte ni chat classique. La voix peut déjà accueillir plusieurs langues d’approche.</p>
            </div>
            <span class="badge">voice only</span>
        </div>

        <div class="guide-grid guide-voice-grid">
            <div class="guide-voice-stage">
                <div class="guide-voice-orb" aria-hidden="true">
                    <span class="guide-voice-orb-core"></span>
                    <span class="guide-voice-orb-ring"></span>
                </div>

                <p class="guide-voice-status" data-guide-voice-status>Prêt. Active la voix puis parle naturellement.</p>
                <p class="guide-voice-transcript" data-guide-voice-transcript>Exemples : « explique O. », “explain O.”, « llévame vers Signal », “leva-me ao Str3m”.</p>
                <p class="guide-voice-reply" data-guide-voice-reply>0wlslw0 répondra ici puis lira sa réponse à voix haute.</p>

                <div class="action-row guide-voice-actions">
                    <button type="button" class="pill-link" data-guide-voice-start>Activer la voix</button>
                    <button type="button" class="ghost-link" data-guide-voice-stop hidden>Couper</button>
                    <?php if ($agentUrl !== ''): ?>
                        <a class="ghost-link" href="<?= h($agentUrl) ?>" target="_blank" rel="noopener">Ouvrir le relais externe</a>
                    <?php endif; ?>
                </div>

                <a class="ghost-link guide-voice-route-link" href="#" data-guide-voice-route hidden>Continuer</a>
            </div>

            <aside class="guide-console guide-console--encoded guide-voice-console" aria-label="Etat du guide vocal">
                <?php foreach ($guideVoiceNotes as [$label, $value]): ?>
                    <?= guide_ascii_note((string) $label, (string) $value) ?>
                <?php endforeach; ?>
            </aside>
        </div>
    </section>

    <section class="guide-grid">
        <section class="panel reveal guide-panel" aria-labelledby="guide-threshold-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-threshold-title">Rythme d’entrée</h2>
                    <p class="panel-copy">Le seuil n’a pas besoin d’être brusque. Il peut instruire, rassurer et n’ouvrir qu’au moment juste.</p>
                </div>
            </div>

            <div class="guide-cards guide-cards--triple guide-cards--compact">
                <?php foreach ($thresholdModes as $mode): ?>
                    <article class="guide-card guide-card-static guide-card-soft">
                        <span class="summary-label"><?= h((string) $mode['label']) ?></span>
                        <strong><?= h((string) $mode['title']) ?></strong>
                        <p><?= h((string) $mode['copy']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="panel reveal guide-panel" aria-labelledby="guide-language-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-language-title">Langues d’approche</h2>
                    <p class="panel-copy">Le lieu pense en français, mais il peut déjà recevoir d’autres voix sans se fermer.</p>
                </div>
            </div>

            <div class="guide-cards guide-cards--compact">
                <?php foreach ($languageDoors as $door): ?>
                    <article class="guide-card guide-card-static guide-card-soft">
                        <span class="summary-label"><?= h((string) $door['label']) ?></span>
                        <strong><?= h((string) $door['title']) ?></strong>
                        <p><?= h((string) $door['copy']) ?></p>
                        <code class="guide-language-sample"><?= h((string) $door['sample']) ?></code>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="panel-copy guide-soft-note">Si la phrase arrive incomplète, 0wlslw0 privilégie d’abord l’intention : comprendre, visiter, écrire, archiver, retrouver.</p>
        </aside>
    </section>

    <section class="panel reveal" aria-labelledby="guide-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-meaning-title">Donner un sens stable</h2>
                <p class="panel-copy">Le projet devient plus lisible quand ses principes sont nommés explicitement, sans perdre leur charge sensible.</p>
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
                <p class="panel-copy">Les trajets à proposer en premier à un visiteur, selon ce qu’il cherche vraiment à faire.</p>
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
                    <p class="panel-copy">Le chemin de création à faire comprendre avant de demander quoi que ce soit au visiteur.</p>
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
                    <p class="panel-copy">Le genre de questions que 0wlslw0 doit absorber sans fatiguer le visiteur ni le noyer de jargon.</p>
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
                <p class="panel-copy">Si tu branches l’agent, ce sont de bons premiers souffles à proposer aux visiteurs.</p>
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
