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
// The guide stays on the current host instead of bouncing to another surface.

$requestPath = o_request_path('/0wlslw0');
if ($requestPath === '/0wlslw0.php') {
    header('Location: ' . o_route_path('/0wlslw0'), true, 302);
    exit;
}

$brandDomain = current_brand_domain($host);
$isSpatialMappingHost = ($brandDomain === 'sowwwl.xyz');
$authenticatedLand = current_authenticated_land();
$ambientProfile = $authenticatedLand ? land_visual_profile($authenticatedLand) : land_collective_profile('calm');
$ambientTokens = visual_profile_tokens($ambientProfile, 'calm');
$guideLandProgram = (string) ($ambientTokens['program'] ?? 'collective');
$guideLandLabel = (string) ($ambientTokens['label'] ?? 'collectif');
$guideLandLambda = (int) ($ambientTokens['lambda'] ?? 548);
$guideLandTone = (string) ($ambientProfile['tone'] ?? 'str3m public');
$agentUrl = trim((string) ((getenv('SOWWWL_0WLSLW0_CHAT_URL') ?: getenv('SOWWWL_0WLSLW0_AGENT_URL')) ?: ''));
$voiceState = guide_voice_browser_state($authenticatedLand);
$voiceUpstreamState = trim((string) ($voiceState['upstream_state'] ?? guide_voice_upstream_state()));
$voiceUpstreamLabel = trim((string) ($voiceState['upstream_label'] ?? guide_voice_upstream_label()));
$guideMode = guide_voice_mode_label();
$siteTitle = defined('SITE_TITLE') ? (string) constant('SITE_TITLE') : 'O. Le réseau minimal';
$guideHref = o_route_path('/0wlslw0');
$openLandHref = $authenticatedLand
    ? o_route_path('/land') . '?u=' . rawurlencode((string) $authenticatedLand['slug'])
    : o_route_path('/rejoindre');
$openLandLabel = $authenticatedLand ? 'Ouvrir ma terre' : 'Poser une terre';
$guidePassageStateShort = match ($voiceUpstreamState) {
    'remote-ready' => 'relais vocal amont configuré',
    'auth-missing' => 'relais repere, autorisation incomplète',
    default => 'guidage local actif',
};
$guidePassageStateLong = match ($voiceUpstreamState) {
    'remote-ready' => 'Le relais vocal est configuré. 0wlslw0 passe par lui quand il répond, puis revient au guidage local si besoin.',
    'auth-missing' => 'Le relais est repéré, mais l’autorisation reste incomplète. 0wlslw0 reste utilisable ici.',
    default => 'Le guidage local reste actif. Si l’amont manque, 0wlslw0 garde le seuil ouvert ici.',
};
$owlDoors = [
    [
        'label' => '01 · ici',
        'title' => 'Parler encore',
        'copy' => 'Préciser la demande avant de bifurquer.',
        'href' => '#guide-voice-title',
        'cta' => 'Continuer ici',
    ],
    [
        'label' => '02 · public',
        'title' => 'Voir le courant',
        'copy' => 'Lire le public avant de choisir une terre.',
        'href' => o_route_path('/str3m'),
        'cta' => 'Ouvrir Str3m',
    ],
    [
        'label' => '03 · terre',
        'title' => $openLandLabel,
        'copy' => $authenticatedLand
            ? 'Revenir vers la terre déjà liée.'
            : 'Nommer, situer et ouvrir une terre.',
        'href' => $openLandHref,
        'cta' => $authenticatedLand ? 'Ouvrir la terre' : 'Commencer',
    ],
];
$guideVoiceNotes = [
    ['input', 'micro navigateur'],
    ['output', 'synthese vocale locale'],
    ['relay', match ($voiceUpstreamState) {
        'remote-ready' => 'agent distant via backend',
        'auth-missing' => 'endpoint distant sans autorisation complete',
        default => 'guide local sans endpoint distant',
    }],
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
    <meta name="description" content="0wlslw0 — guide d entree pour comprendre <?= h($siteTitle) ?> et trouver la bonne porte sans se perdre.">
    <meta name="theme-color" content="#09090b">
    <title>0wlslw0 — <?= h($siteTitle) ?></title>
<?= render_o_page_head_assets('owl') ?>
</head>
<body
    class="experience guide-view<?= $isSpatialMappingHost ? ' mapping-host-view' : '' ?>"
    data-land-program="<?= h($guideLandProgram) ?>"
    data-land-label="<?= h($guideLandLabel) ?>"
    data-land-lambda="<?= h((string) $guideLandLambda) ?>"
    data-land-tone="<?= h($guideLandTone) ?>"
>
<?= render_skip_link() ?>
<?= render_nucleus_banner('0wlslw0') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', '0wlslw0') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>0wlslw0</strong> <span>guide d entree</span></p>
        <h1 class="land-title">
            <strong>Entrer sans se perdre.</strong>
            <span>0wlslw0 / guide des passages</span>
        </h1>
        <p class="lead">Parle, écris, puis prends la bonne porte.</p>

        <div class="land-meta">
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
        <p class="panel-copy guide-hero-note"><?= h($guidePassageStateLong) ?></p>
    </header>

    <section
        class="panel reveal guide-panel guide-voice-shell"
        aria-labelledby="guide-voice-title"
        data-guide-voice
        data-guide-voice-api="<?= h((string) $voiceState['api_path']) ?>"
        data-guide-voice-csrf="<?= h((string) $voiceState['csrf_token']) ?>"
        data-guide-voice-greeting="<?= h((string) $voiceState['greeting']) ?>"
        data-guide-voice-upstream="<?= !empty($voiceState['upstream_configured']) ? '1' : '0' ?>"
        data-guide-voice-upstream-state="<?= h($voiceUpstreamState) ?>"
        data-guide-voice-upstream-label="<?= h($voiceUpstreamLabel) ?>"
        data-guide-voice-chat-url="<?= h((string) $voiceState['chat_url']) ?>"
        data-guide-voice-program="<?= h((string) ($voiceState['land_program'] ?? $guideLandProgram)) ?>"
        data-guide-voice-label="<?= h((string) ($voiceState['land_label'] ?? $guideLandLabel)) ?>"
        data-guide-voice-lambda="<?= h((string) ($voiceState['land_lambda'] ?? $guideLandLambda)) ?>"
        data-guide-voice-tone="<?= h((string) ($voiceState['land_tone'] ?? $guideLandTone)) ?>"
        data-guide-voice-starter-prompts="<?= h((string) json_encode($voiceState['starter_prompts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
    >
        <div class="section-topline">
            <div>
                <h2 id="guide-voice-title">Parler à 0wlslw0</h2>
                <p class="panel-copy">Parle naturellement. Sinon, écris une phrase courte.</p>
            </div>
            <span class="badge">voix + texte</span>
        </div>
        <p class="guide-voice-bridge"><?= h($guidePassageStateShort) ?></p>

        <div class="guide-grid guide-voice-grid">
            <div class="guide-voice-stage">
                <div class="guide-voice-orb" aria-hidden="true">
                    <span class="guide-voice-orb-core"></span>
                    <span class="guide-voice-orb-ring"></span>
                    <span class="guide-voice-breather" data-guide-voice-breather hidden>0</span>
                </div>

                <p class="guide-voice-status" data-guide-voice-status role="status" aria-live="polite" aria-atomic="true">Prêt. Active la voix puis parle naturellement.</p>
                <p class="guide-voice-transcript" data-guide-voice-transcript>Exemples : « ouvre Signal » · « take me to Str3m ».</p>
                <p class="guide-voice-reply" data-guide-voice-reply aria-live="polite" aria-atomic="true">0wlslw0 répondra ici puis lira sa réponse.</p>
                <div class="guide-voice-meta" aria-live="polite">
                    <span class="guide-voice-origin-badge" data-guide-voice-origin data-guide-voice-origin-state="<?= h($voiceUpstreamState) ?>"><?= h($voiceUpstreamLabel) ?></span>
                    <span class="guide-voice-meta-copy">texte de secours · historique court</span>
                </div>
                <ol class="guide-voice-history" data-guide-voice-history aria-label="Historique récent avec 0wlslw0" hidden></ol>
                <form class="guide-voice-form" data-guide-voice-form>
                    <label class="sr-only" for="guide-voice-text-input">Écrire à 0wlslw0</label>
                    <input id="guide-voice-text-input" type="text" name="guide_voice_text" maxlength="280" autocomplete="off" placeholder="Écris ici si tu préfères le silence." data-guide-voice-input>
                    <button type="submit" class="pill-link guide-voice-submit" data-guide-voice-submit>Envoyer</button>
                </form>
                <p class="guide-voice-input-hint" data-guide-voice-input-hint>Le texte reste disponible même si la reconnaissance vocale Web manque ici.</p>
                <div class="guide-voice-suggestions" data-guide-voice-suggestions aria-label="Impulsions proposées par 0wlslw0">
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
                    <span class="guide-voice-mute-indicator" data-guide-voice-mute-indicator>voix active · I inverse + voix</span>
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

            <details class="guide-voice-notes" aria-label="État du passage">
                <summary class="guide-voice-notes__summary">
                    <span class="summary-label">État du passage</span>
                    <strong><?= h($guidePassageStateShort) ?></strong>
                </summary>
                <p class="panel-copy guide-voice-notes__copy"><?= h($guidePassageStateLong) ?></p>
                <div class="guide-console guide-console--encoded guide-voice-console">
                    <?php foreach ($guideVoiceNotes as [$label, $value]): ?>
                        <?= guide_ascii_note((string) $label, (string) $value) ?>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    </section>

    <section class="panel reveal guide-paths-panel" aria-labelledby="guide-paths-title">
        <div class="section-topline">
            <div>
                <h2 id="guide-paths-title">Puis choisir une porte</h2>
                <p class="panel-copy">Quand c’est clair, prends la suite la plus simple.</p>
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

    <details class="panel reveal mapping-panel mapping-panel--genie guide-knowledge-panel" id="mapping" aria-labelledby="mapping-title" data-mapping-genie data-mapping-theme="real">
        <summary class="guide-knowledge-summary">Comprendre le schéma</summary>
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
                        ? 'Le tore lit le monde réel. Le plasma traduit. La surface devient navigable.'
                        : 'Réalité, plasma, tore : trois couches pour lire, filtrer, rendre navigable.' ?>
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
                    aria-controls="mapping-active-display"
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
                    aria-controls="mapping-active-display"
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
                    aria-controls="mapping-active-display"
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

            <aside id="mapping-active-display" class="mapping-chorus" aria-live="polite">
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
            la <span class="mapping-panel__accent mapping-panel__accent--real">réalité</span> traverse le
            <span class="mapping-panel__accent mapping-panel__accent--plasma">plasma</span> et se boucle en
            <span class="mapping-panel__accent mapping-panel__accent--torus">tore</span>.
        </p>
    </details>

</main>

</body>
</html>
