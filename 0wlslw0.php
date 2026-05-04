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
// sowwwl.xyz is now allowed to render the mapping UI directly—no redirect!

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/0wlslw0'), PHP_URL_PATH) ?: '/0wlslw0';
if ($requestPath === '/0wlslw0.php') {
    header('Location: /0wlslw0', true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$isSpatialMappingHost = ($brandDomain === 'sowwwl.xyz');
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$authenticatedLand = current_authenticated_land();
$ambientProfile = $authenticatedLand ? land_visual_profile($authenticatedLand) : land_collective_profile('calm');
$ambientTokens = visual_profile_tokens($ambientProfile, 'calm');
$guideLandProgram = (string) ($ambientTokens['program'] ?? 'collective');
$guideLandLabel = (string) ($ambientTokens['label'] ?? 'collectif');
$guideLandLambda = (int) ($ambientTokens['lambda'] ?? 548);
$guideLandTone = (string) ($ambientProfile['tone'] ?? 'str3m public');
$agentUrl = trim((string) ((getenv('SOWWWL_0WLSLW0_CHAT_URL') ?: getenv('SOWWWL_0WLSLW0_AGENT_URL')) ?: ''));
$voiceState = guide_voice_browser_state($authenticatedLand);
$guideMode = guide_voice_mode_label();
$siteTitle = defined('SITE_TITLE') ? (string) constant('SITE_TITLE') : 'O. le réseau minimal';
$canonicalOrigin = rtrim((string) (getenv('SOWWWL_PUBLIC_ORIGIN') ?: 'https://sowwwl.com'), '/');
$guideHref = '/0wlslw0';
$openLandHref = $authenticatedLand
    ? '/land.php?u=' . rawurlencode((string) $authenticatedLand['slug'])
    : '/rejoindre.php';
$openLandLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Poser une terre';
$promptSeeds = guide_prompt_seeds();
$owlDoors = [
    [
        'label' => '01 · comprendre',
        'title' => 'Comprendre O. vite',
        'copy' => 'En deux ou trois phrases, Owl clarifie le projet et te donne une première orientation.',
        'href' => $guideHref,
        'cta' => 'Rester avec Owl',
    ],
    [
        'label' => '02 · public',
        'title' => 'Entrer publiquement',
        'copy' => 'Aller vers Str3m pour voir le courant avant de choisir une terre.',
        'href' => '/str3m',
        'cta' => 'Ouvrir Str3m',
    ],
    [
        'label' => '03 · terre',
        'title' => 'Poser une terre',
        'copy' => 'Passer par le parcours complet : nom, lecture, configuration, scellement.',
        'href' => '/rejoindre.php',
        'cta' => 'Commencer',
    ],
];
$guideStateNotes = [
    ['role', 'ecouter / clarifier / orienter'],
    ['public', 'str3m / lecture / observation'],
    ['signup', 'rejoindre / terre / scellement'],
    ['voice', guide_voice_upstream_configured() ? 'relais amont actif' : 'fallback local actif'],
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
    <meta name="description" content="0wlslw0 — Owl, guide d entree pour comprendre <?= h($siteTitle) ?> et trouver la bonne porte sans se perdre.">
    <meta name="theme-color" content="#09090b">
    <title>0wlslw0 — <?= h($siteTitle) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body
    class="experience guide-view<?= $isSpatialMappingHost ? ' mapping-host-view' : '' ?>"
    data-land-program="<?= h($guideLandProgram) ?>"
    data-land-label="<?= h($guideLandLabel) ?>"
    data-land-lambda="<?= h((string) $guideLandLambda) ?>"
    data-land-tone="<?= h($guideLandTone) ?>"
>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', '0wlslw0') ?>

<main class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>0wlslw0 (Owl)</strong> <span>guide d entree</span></p>
        <h1 class="land-title">
            <strong>Entrer sans se perdre.</strong>
            <span>Owl / guide des passages</span>
        </h1>
        <p class="lead">Owl accueille, clarifie en quelques phrases, puis envoie vers la bonne porte. Pas de détour : juste le bon seuil.</p>

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

    <section class="panel reveal mapping-panel mapping-panel--genie" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real">
        <div class="mapping-panel__veil" aria-hidden="true">
            <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--outer"></span>
            <span class="mapping-panel__veil-orbit mapping-panel__veil-orbit--inner"></span>
            <span class="mapping-panel__veil-glow"></span>
        </div>

        <div class="section-topline mapping-panel__topline">
            <div>
                <p class="eyebrow mapping-panel__eyebrow">
                    <strong><?= $isSpatialMappingHost ? 'sowwwl.xyz' : 'surface sensible' ?></strong>
                    <span><?= $isSpatialMappingHost ? 'monde reel / tore actif' : 'projection / transit / lecture' ?></span>
                </p>
                <h2 id="mapping-title">Le projet en un schéma</h2>
                <p class="panel-copy mapping-panel__copy">
                    <?= $isSpatialMappingHost
                        ? 'Le tore lit le monde réel. Le plasma traduit, puis la surface devient navigable.'
                        : 'Réalité, plasma, tore : trois couches pour comprendre comment O. lit, filtre et rend navigable.' ?>
                </p>
            </div>
            <span class="badge mapping-panel__badge"><?= $isSpatialMappingHost ? 'real-world map' : 'genie view' ?></span>
        </div>

        <div class="mapping-panel__scene">
            <div class="mapping-genie" role="list" aria-label="Cartographie du tore" data-mapping-genie-list>
                <button
                    type="button"
                    class="mapping-genie-card mapping-genie-card--real is-active"
                    data-mapping-card
                    data-mapping-tone="real"
                    data-mapping-label="Réalité"
                    data-mapping-whisper="Le monde brut pulse avant toute lecture."
                    data-mapping-summary="Matière, présence, climat, gestes, voix, lumière. Tout ce qui existe avant d’être interprété."
                    aria-expanded="true"
                >
                    <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                    <span class="mapping-genie-card__sigil" aria-hidden="true">🌍</span>
                    <span class="mapping-genie-card__head">
                        <span class="summary-label">plan 01</span>
                        <strong>Réalité</strong>
                    </span>
                    <span class="mapping-genie-card__body">
                        Matière, présence, climat, gestes, voix, lumière. Tout ce qui existe avant d’être interprété.
                    </span>
                </button>

                <div class="mapping-genie-link" aria-hidden="true">
                    <span class="mapping-genie-link__line"></span>
                    <span class="mapping-genie-link__label">traduction</span>
                </div>

                <button
                    type="button"
                    class="mapping-genie-card mapping-genie-card--plasma"
                    data-mapping-card
                    data-mapping-tone="plasma"
                    data-mapping-label="Plasma"
                    data-mapping-whisper="Le flux mémorise, filtre et transmet."
                    data-mapping-summary="Couche fluide de mémoire, de calcul et de transmission. Le plasma convertit le réel en intensités lisibles."
                    aria-expanded="false"
                >
                    <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                    <span class="mapping-genie-card__sigil" aria-hidden="true">💧</span>
                    <span class="mapping-genie-card__head">
                        <span class="summary-label">plan 02</span>
                        <strong>Plasma</strong>
                    </span>
                    <span class="mapping-genie-card__body">
                        Couche fluide de mémoire, de calcul et de transmission. Le plasma convertit le réel en intensités lisibles.
                    </span>
                </button>

                <div class="mapping-genie-link" aria-hidden="true">
                    <span class="mapping-genie-link__line"></span>
                    <span class="mapping-genie-link__label">déploiement</span>
                </div>

                <button
                    type="button"
                    class="mapping-genie-card mapping-genie-card--torus"
                    data-mapping-card
                    data-mapping-tone="torus"
                    data-mapping-label="Tore"
                    data-mapping-whisper="La surface devient orientation, seuil et dérive."
                    data-mapping-summary="Surface navigable de Sowwwl : une peau torique où les flux se déposent, se relient et deviennent orientation."
                    aria-expanded="false"
                >
                    <span class="mapping-genie-card__mist" aria-hidden="true"></span>
                    <span class="mapping-genie-card__sigil" aria-hidden="true">🌀</span>
                    <span class="mapping-genie-card__head">
                        <span class="summary-label">plan 03</span>
                        <strong>Tore</strong>
                    </span>
                    <span class="mapping-genie-card__body">
                        Surface navigable de Sowwwl : une peau torique où les flux se déposent, se relient et deviennent orientation.
                    </span>
                </button>
            </div>

            <aside class="mapping-chorus" aria-live="polite">
                <span class="summary-label">écho actif</span>
                <strong class="mapping-chorus__title" data-mapping-active-label>Réalité</strong>
                <p class="mapping-chorus__whisper" data-mapping-active-whisper>Le monde brut pulse avant toute lecture.</p>
                <p class="mapping-chorus__summary" data-mapping-active-summary>Matière, présence, climat, gestes, voix, lumière. Tout ce qui existe avant d’être interprété.</p>
                <div class="mapping-chorus__meter" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </aside>
        </div>

        <p class="mapping-panel__reading">
            <strong>Lecture&nbsp;:</strong>
            le <span class="mapping-panel__accent mapping-panel__accent--plasma">plasma</span> fait le lien entre
            <span class="mapping-panel__accent mapping-panel__accent--real">la réalité</span> et
            <span class="mapping-panel__accent mapping-panel__accent--torus">la surface torique</span>.
            Sur tactile ou pointeur, chaque carte s’ouvre comme une petite apparition.
        </p>
    </section>

    <section class="guide-grid">
        <section class="panel reveal guide-panel" aria-labelledby="guide-role-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-role-title">Ce que fait Owl</h2>
                    <p class="panel-copy">Un accueil bref, orientant, assez clair pour qu’on n’ait pas à avaler tout le projet d’un coup.</p>
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
                <li>Dire simplement ce qu’est O., sans supposer une connaissance préalable du vocabulaire.</li>
                <li>Recevoir des intentions floues : comprendre, visiter, poser une terre, retrouver une terre.</li>
                <li>Pointer vite vers la bonne porte, puis disparaître du chemin.</li>
            </ul>
        </section>

        <aside class="panel reveal guide-panel" aria-labelledby="guide-state-title">
            <div class="section-topline">
                <div>
                    <h2 id="guide-state-title">État du passage</h2>
                    <p class="panel-copy">Une porte pour les hésitations ordinaires, sans transformer l’entrée en encyclopédie.</p>
                </div>
            </div>

            <div class="guide-console guide-console--encoded" aria-label="Console de guidage">
                <?php foreach ($guideStateNotes as [$label, $value]): ?>
                    <?= guide_ascii_note((string) $label, (string) $value) ?>
                <?php endforeach; ?>
            </div>

            <p class="panel-copy guide-embed-note">
                <?= guide_voice_upstream_configured()
                    ? 'La voix peut déjà relayer un agent amont côté serveur. Owl garde la clé côté backend.'
                    : 'La voix fonctionne déjà en guide local. Le relais amont pourra se brancher plus tard sans exposer la clé au navigateur.' ?>
            </p>
        </aside>
    </section>

    <section class="panel reveal" aria-labelledby="guide-paths-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-paths-title">Trois portes</h2>
                <p class="panel-copy">Si tu ne sais pas encore quoi faire, commence ici. Owl s’occupe surtout de ce premier embranchement.</p>
            </div>
        </div>

        <div class="guide-cards guide-cards--triple guide-cards--compact">
            <?php foreach ($owlDoors as $door): ?>
                <a class="guide-card" href="<?= h((string) $door['href']) ?>">
                    <span class="summary-label"><?= h((string) $door['label']) ?></span>
                    <strong><?= h((string) $door['title']) ?></strong>
                    <p><?= h((string) $door['copy']) ?></p>
                    <span class="ghost-link"><?= h((string) $door['cta']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
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
        data-guide-voice-program="<?= h((string) ($voiceState['land_program'] ?? $guideLandProgram)) ?>"
        data-guide-voice-label="<?= h((string) ($voiceState['land_label'] ?? $guideLandLabel)) ?>"
        data-guide-voice-lambda="<?= h((string) ($voiceState['land_lambda'] ?? $guideLandLambda)) ?>"
        data-guide-voice-tone="<?= h((string) ($voiceState['land_tone'] ?? $guideLandTone)) ?>"
        data-guide-voice-starter-prompts="<?= h((string) json_encode($voiceState['starter_prompts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
    >
        <div class="section-topline">
            <div>
                <h2 id="guide-voice-title">Accompagnement vocal</h2>
                <p class="panel-copy">Ici, Owl écoute, répond à voix haute, puis oriente sans chat classique. <strong>I</strong> inverse et coupe aussi la voix ; sur tactile, un appui long reprend le même geste.</p>
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
                <p class="guide-voice-transcript" data-guide-voice-transcript>Exemples : « explique O. », « ouvre Signal », “take me to Str3m”.</p>
                <p class="guide-voice-reply" data-guide-voice-reply>Owl répondra ici puis lira sa réponse à voix haute.</p>
                <div class="guide-voice-suggestions" data-guide-voice-suggestions aria-label="Impulsions proposées par Owl">
                    <?php foreach (($voiceState['starter_prompts'] ?? []) as $prompt): ?>
                        <?php
                        $promptUtterance = trim((string) ($prompt['utterance'] ?? ''));
                        $promptLabel = trim((string) ($prompt['label'] ?? $promptUtterance));
                        if ($promptUtterance === '' || $promptLabel === '') {
                            continue;
                        }
                        ?>
                        <button type="button" class="guide-voice-suggestion" data-guide-voice-suggestion data-utterance="<?= h($promptUtterance) ?>"><?= h($promptLabel) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="guide-voice-signature" aria-live="polite">
                    <span class="summary-label">Signature vocale</span>
                    <strong data-guide-voice-signature>Voix spectrale · λ <?= h((string) $guideLandLambda) ?> nm</strong>
                    <span class="guide-voice-profile" data-guide-voice-profile>tempo ajusté · <?= h($guideLandLabel) ?></span>
                    <span class="guide-voice-mute-indicator" data-guide-voice-mute-indicator>voix active · I inverse + voix · appui long tactile</span>
                </div>

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

    <section class="panel reveal guide-panel" aria-labelledby="guide-prompts-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-prompts-title">Phrases de départ</h2>
                <p class="panel-copy">Quelques souffles de départ si tu veux demander à Owl sans tourner autour du seuil.</p>
            </div>
        </div>

        <ul class="guide-prompt-list">
            <?php foreach (array_slice($promptSeeds, 0, 4) as $prompt): ?>
                <li><code><?= h($prompt) ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
