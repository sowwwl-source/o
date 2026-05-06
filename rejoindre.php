<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

const SIGNUP_ROUTE = '/rejoindre';
const LAND_ROUTE = '/land';

function signup_portal_steps(): array
{
    $steps = [
        ['slug' => '01', 'label' => 'Qui', 'file' => '01-qui-es-tu.html'],
        ['slug' => '02', 'label' => 'Projet', 'file' => '02-projet.html'],
        ['slug' => '03', 'label' => 'Valeurs', 'file' => '03-valeurs.html'],
        ['slug' => '04', 'label' => 'Démarche', 'file' => '04-demarche.html'],
        ['slug' => '05', 'label' => 'Pacte', 'file' => '05-pacte.html'],
    ];

    $portals = [];
    foreach ($steps as $step) {
        $path = __DIR__ . '/aza_portals/' . $step['file'];
        $markup = is_file($path) ? trim((string) file_get_contents($path)) : '';
        if ($markup === '') {
            continue;
        }

        $portals[] = [
            'slug' => $step['slug'],
            'label' => $step['label'],
            'markup' => $markup,
        ];
    }

    return $portals;
}

function signup_stage_link(int $step, array $form): string
{
    $params = [
        'step' => $step,
    ];

    foreach (['username', 'timezone', 'land_program', 'lambda_nm'] as $field) {
        $value = trim((string) ($form[$field] ?? ''));
        if ($value !== '') {
            $params[$field] = $value;
        }
    }

    return SIGNUP_ROUTE . '?' . http_build_query($params);
}

$host = request_host();

$csrfToken = csrf_token();
$authenticatedLand = current_authenticated_land();
$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$originBase = site_origin();
$guideHref = '/0wlslw0';
$signupPortals = signup_portal_steps();
$totalPortalSteps = count($signupPortals);
$configStep = $totalPortalSteps + 1;
$message = '';
$messageType = 'info';
$form = [
    'username' => trim((string) ($_REQUEST['username'] ?? '')),
    'timezone' => trim((string) ($_REQUEST['timezone'] ?? DEFAULT_TIMEZONE)),
    'password' => '',
    'land_program' => trim((string) ($_REQUEST['land_program'] ?? '')),
    'lambda_nm' => trim((string) ($_REQUEST['lambda_nm'] ?? '')),
];

$requestedStep = (int) ($_REQUEST['step'] ?? ($form['username'] !== '' ? 1 : 0));
$currentStep = max(0, min($configStep, $requestedStep));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
    $form['password'] = (string) ($_POST['password'] ?? '');
    $form['land_program'] = trim((string) ($_POST['land_program'] ?? ''));
    $form['lambda_nm'] = trim((string) ($_POST['lambda_nm'] ?? ''));
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');
    $currentStep = $configStep;

    if ($action === 'create') {
        if ($form['username'] === '') {
            $message = 'Écris d’abord le nom de ta terre.';
            $messageType = 'warning';
        } else {
            try {
                guard_land_creation_request($csrfCandidate, $honeypot);
                $land = create_land(
                    $form['username'],
                    $form['timezone'],
                    $form['password'],
                    $form['land_program'] !== '' ? $form['land_program'] : null,
                    $form['lambda_nm'] !== '' ? (int) $form['lambda_nm'] : null
                );
                login_land($land);
                header('Location: ' . LAND_ROUTE . '?u=' . urlencode((string) $land['slug']) . '&created=1&session=1', true, 303);
                exit;
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $message = $exception->getMessage();
                $messageType = 'warning';
            }
        }
    }
}

remember_form_rendered_at();

if ($form['timezone'] === '') {
    $form['timezone'] = DEFAULT_TIMEZONE;
}

if ($form['username'] === '') {
    $currentStep = 0;
} else {
    try {
        normalize_username($form['username']);
    } catch (InvalidArgumentException $exception) {
        $message = $message !== '' ? $message : $exception->getMessage();
        $messageType = 'warning';
        $currentStep = 0;
    }
}

$previewSlug = preview_land_slug($form['username']);
$previewTimezone = $form['timezone'] !== '' ? $form['timezone'] : DEFAULT_TIMEZONE;
$signupPrograms = land_visual_signup_catalog();
$defaultSignupProgram = array_key_first($signupPrograms) ?: 'culbu1on';

try {
    $selectedSignupProgram = $form['land_program'] !== ''
        ? validate_land_visual_program($form['land_program'])
        : $defaultSignupProgram;
} catch (InvalidArgumentException $exception) {
    $selectedSignupProgram = $defaultSignupProgram;
}

$signupPreviewSeed = implode('|', [$previewSlug, $previewTimezone, 'signup-preview']);
$selectedSignupDefinition = $signupPrograms[$selectedSignupProgram] ?? land_visual_program_definition($selectedSignupProgram);
[$selectedSignupMinLambda, $selectedSignupMaxLambda] = land_visual_lambda_range($selectedSignupProgram);
$defaultSignupLambda = land_visual_default_lambda($selectedSignupProgram, $signupPreviewSeed);

try {
    $selectedSignupLambda = $form['lambda_nm'] !== ''
        ? validate_land_visual_lambda((int) $form['lambda_nm'], $selectedSignupProgram)
        : $defaultSignupLambda;
} catch (InvalidArgumentException $exception) {
    $selectedSignupLambda = $defaultSignupLambda;
}

$form['land_program'] = $selectedSignupProgram;
$form['lambda_nm'] = (string) $selectedSignupLambda;
$selectedSignupLabel = (string) ($selectedSignupDefinition['label'] ?? $selectedSignupProgram);
$selectedSignupTone = (string) ($selectedSignupDefinition['tone'] ?? '');
$ambientProfile = [
    'program' => $selectedSignupProgram,
    'label' => $selectedSignupLabel,
    'tone' => $selectedSignupTone,
    'lambda_nm' => $selectedSignupLambda,
];
$currentPortal = ($currentStep >= 1 && $currentStep <= $totalPortalSteps) ? ($signupPortals[$currentStep - 1] ?? null) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Rejoindre le peuple de l'O — lecture AzA et configuration complète de la terre.">
    <meta name="theme-color" content="#09090b">
    <title>Rejoindre le peuple de l'O — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body
    class="experience signup-journey-view"
    data-land-program="<?= h($selectedSignupProgram) ?>"
    data-land-label="<?= h($selectedSignupLabel) ?>"
    data-land-lambda="<?= h((string) $selectedSignupLambda) ?>"
    data-land-tone="<?= h($selectedSignupTone) ?>"
>
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'calm', 'land') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell signup-journey-shell">
    <header class="hero page-header reveal signup-journey-header">
        <p class="eyebrow"><strong><?= h($brandDomain) ?></strong> <span>rejoindre / lecture AzA / configuration</span></p>
        <h1 class="land-title signup-journey-title">
            <strong>Rejoindre le peuple de l'O.</strong>
            <span>Nommer, lire, régler, sceller.</span>
        </h1>
        <p class="lead signup-journey-lead">Le nom de la terre ouvre maintenant un vrai passage : lecture d'AzA d'abord, configuration ensuite, création enfin.</p>

        <ol class="signup-journey-progress" aria-label="Progression du parcours d'entrée">
            <li class="<?= $currentStep === 0 ? 'is-current' : ($currentStep > 0 ? 'is-done' : '') ?>">Nom</li>
            <?php foreach ($signupPortals as $index => $portal): ?>
                <?php $stepNumber = $index + 1; ?>
                <li class="<?= $currentStep === $stepNumber ? 'is-current' : ($currentStep > $stepNumber ? 'is-done' : '') ?>"><?= h((string) $portal['label']) ?></li>
            <?php endforeach; ?>
            <li class="<?= $currentStep === $configStep ? 'is-current' : ($currentStep > $configStep ? 'is-done' : '') ?>">Configuration</li>
        </ol>
    </header>

    <?php if ($message !== ''): ?>
        <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
            <p><?= h($message) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($currentStep === 0): ?>
        <section class="panel reveal signup-journey-stage signup-journey-stage--name" aria-labelledby="signup-name-title">
            <div class="section-topline">
                <div>
                    <span class="summary-label">Seuil 00</span>
                    <h2 id="signup-name-title">Choisir le nom de la terre</h2>
                    <p class="panel-copy">On valide d'abord le nom, puis on traverse les pages complètes d'AzA avant de sceller la terre.</p>
                </div>
                <a class="ghost-link" href="/">Retour au noyau</a>
            </div>

            <div class="signup-journey-grid signup-journey-grid--name">
                <form method="get" action="<?= h(SIGNUP_ROUTE) ?>" class="land-form signup-journey-form" autocomplete="off">
                    <input type="hidden" name="step" value="1">

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
                        <span class="input-hint">Le nom devient le seuil. Ensuite, lecture et réglage prennent toute la page.</span>
                    </label>

                    <label>
                        Fuseau initial
                        <input
                            type="text"
                            name="timezone"
                            value="<?= h($previewTimezone) ?>"
                            placeholder="ex: Europe/Paris"
                            data-timezone-input
                        >
                    </label>

                    <div class="timezone-picks" aria-label="Fuseaux suggérés">
                        <?php foreach (['Europe/Paris', 'Europe/London', 'America/New_York', 'Asia/Tokyo'] as $suggestedTimezone): ?>
                            <button type="button" class="timezone-chip" data-timezone-chip="<?= h($suggestedTimezone) ?>"><?= h($suggestedTimezone) ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="ghost-link inline-action" data-use-local-timezone>utiliser le fuseau local</button>
                    </div>

                    <p class="field-status" data-timezone-status>Le fuseau sera repris dans la configuration finale.</p>

                    <button type="submit">Valider le nom et entrer dans AzA</button>
                </form>

                <aside class="signup-preview signup-journey-preview" data-origin-base="<?= h($originBase) ?>" data-preview-shell>
                    <span class="summary-label">Aperçu immédiat</span>
                    <strong class="preview-title" data-slug-output><?= h($previewSlug) ?></strong>
                    <div class="preview-grid">
                        <p><span>Lien</span><code data-land-link-output><?= h($originBase . LAND_ROUTE . '?u=' . $previewSlug) ?></code></p>
                        <p><span>Email virtuel</span><code data-email-output><?= h($previewSlug . '@o.local') ?></code></p>
                        <p><span>Fuseau</span><strong data-preview-timezone><?= h($previewTimezone) ?></strong></p>
                    </div>
                    <p class="panel-copy">Une fois ce nom validé, le parcours s'ouvre en lecture pleine page pour qu'AzA soit vraiment lisible.</p>
                </aside>
            </div>
        </section>

    <?php elseif ($currentPortal): ?>
        <section class="panel reveal signup-journey-stage signup-journey-stage--reading" aria-labelledby="signup-reading-title">
            <div class="section-topline">
                <div>
                    <span class="summary-label">Passage AzA <?= h((string) $currentPortal['slug']) ?></span>
                    <h2 id="signup-reading-title"><?= h((string) $currentPortal['label']) ?></h2>
                    <p class="panel-copy">Lecture complète avant création : on laisse à ce passage la place d'être lu, pas juste survolé.</p>
                </div>
                <a class="ghost-link" href="/aza">Lire aZa au complet</a>
            </div>

            <div class="signup-journey-grid signup-journey-grid--reading">
                <article class="signup-journey-reading">
                    <div class="signup-journey-reading__copy signup-portal-copy">
                        <?= $currentPortal['markup'] ?>
                    </div>
                </article>

                <aside class="signup-journey-aside">
                    <div class="signup-preview signup-journey-preview" data-origin-base="<?= h($originBase) ?>" data-preview-shell>
                        <span class="summary-label">Terre en préparation</span>
                        <strong class="preview-title" data-slug-output><?= h($previewSlug) ?></strong>
                        <div class="preview-grid">
                            <p><span>Lien</span><code data-land-link-output><?= h($originBase . LAND_ROUTE . '?u=' . $previewSlug) ?></code></p>
                            <p><span>Email virtuel</span><code data-email-output><?= h($previewSlug . '@o.local') ?></code></p>
                            <p><span>Fuseau</span><strong data-preview-timezone><?= h($previewTimezone) ?></strong></p>
                        </div>
                    </div>

                    <div class="signup-journey-nav">
                        <p class="panel-copy">Étape <?= h((string) $currentStep) ?> sur <?= h((string) $configStep) ?>.</p>
                        <div class="action-row">
                            <a class="ghost-link" href="<?= h(signup_stage_link(max(0, $currentStep - 1), $form)) ?>">Précédent</a>
                            <a class="pill-link" href="<?= h(signup_stage_link(min($configStep, $currentStep + 1), $form)) ?>"><?= $currentStep === $totalPortalSteps ? 'Aller à la configuration' : 'Continuer' ?></a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

    <?php else: ?>
        <section class="panel reveal signup-journey-stage signup-journey-stage--config" aria-labelledby="signup-config-title">
            <div class="section-topline">
                <div>
                    <span class="summary-label">Scellement</span>
                    <h2 id="signup-config-title">Configurer la terre avant de la sceller</h2>
                    <p class="panel-copy">La lecture est faite. Ici, on règle la signature native, le secret et le fuseau dans une page entière, sans compresser la lecture d'AzA.</p>
                </div>
                <a class="ghost-link" href="<?= h(signup_stage_link(max(1, $totalPortalSteps), $form)) ?>">Relire le pacte</a>
            </div>

            <div class="signup-journey-grid signup-journey-grid--config">
                <form method="post" action="<?= h(SIGNUP_ROUTE) ?>" class="land-form signup-journey-form" autocomplete="off">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="step" value="<?= h((string) $configStep) ?>">
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
                        <span class="input-hint">Tu peux encore ajuster le nom avant le scellement.</span>
                    </label>

                    <section class="signup-portal-ritual" aria-labelledby="signup-portal-recap-title">
                        <div class="signup-head signup-portal-head">
                            <div>
                                <span class="summary-label">Lecture AzA</span>
                                <h3 id="signup-portal-recap-title">Les passages relus avant l’ouverture</h3>
                                <p class="panel-copy">Tu peux revenir à chaque page si besoin ; rien n'est caché derrière un seul pli de formulaire.</p>
                            </div>
                        </div>

                        <ol class="signup-portal-grid" aria-label="Rappels des passages AzA">
                            <?php foreach ($signupPortals as $index => $portal): ?>
                                <li class="signup-portal-card signup-journey-portal-card">
                                    <span class="summary-label">Portail <?= h((string) $portal['slug']) ?> · <?= h((string) $portal['label']) ?></span>
                                    <div class="signup-portal-copy signup-journey-portal-summary">
                                        <?= $portal['markup'] ?>
                                    </div>
                                    <a class="ghost-link" href="<?= h(signup_stage_link($index + 1, $form)) ?>">Relire cette page</a>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>

                    <section class="signup-spectrum" aria-labelledby="signup-spectrum-title">
                        <div class="signup-head signup-spectrum-head">
                            <div>
                                <span class="summary-label">Signature native</span>
                                <h3 id="signup-spectrum-title">Programme et longueur d’onde</h3>
                                <p class="panel-copy">Le réglage ici devient l'identité durable de la terre.</p>
                            </div>
                        </div>

                        <div class="signup-program-grid" role="radiogroup" aria-label="Choisir un programme de terre">
                            <?php foreach ($signupPrograms as $programKey => $programDefinition): ?>
                                <?php [$programMin, $programMax] = land_visual_lambda_range($programKey); ?>
                                <?php $programDefaultLambda = land_visual_default_lambda($programKey, $signupPreviewSeed); ?>
                                <label class="signup-program-card" data-signup-program-card>
                                    <input
                                        type="radio"
                                        name="land_program"
                                        value="<?= h($programKey) ?>"
                                        <?= $selectedSignupProgram === $programKey ? 'checked' : '' ?>
                                        data-signup-program-input
                                        data-program-label="<?= h((string) ($programDefinition['label'] ?? $programKey)) ?>"
                                        data-program-tone="<?= h((string) ($programDefinition['tone'] ?? '')) ?>"
                                        data-lambda-min="<?= h((string) $programMin) ?>"
                                        data-lambda-max="<?= h((string) $programMax) ?>"
                                        data-lambda-default="<?= h((string) $programDefaultLambda) ?>"
                                    >
                                    <span class="summary-label"><?= h($programKey) ?></span>
                                    <strong><?= h((string) ($programDefinition['label'] ?? $programKey)) ?></strong>
                                    <span><?= h((string) ($programDefinition['tone'] ?? '')) ?></span>
                                    <span>λ <?= h((string) $programMin) ?>–<?= h((string) $programMax) ?> nm</span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <label class="signup-lambda-field">
                            <span class="input-hint">Amplitude choisie pour la terre</span>
                            <strong><span data-signup-program-label><?= h($selectedSignupLabel) ?></span> · λ <span data-signup-lambda-value><?= h((string) $selectedSignupLambda) ?></span> nm</strong>
                            <input
                                type="range"
                                name="lambda_nm"
                                min="<?= h((string) $selectedSignupMinLambda) ?>"
                                max="<?= h((string) $selectedSignupMaxLambda) ?>"
                                step="1"
                                value="<?= h((string) $selectedSignupLambda) ?>"
                                data-signup-lambda-input
                            >
                            <span class="signup-lambda-range"><span data-signup-program-tone><?= h($selectedSignupTone) ?></span> · plage <span data-signup-lambda-range><?= h((string) $selectedSignupMinLambda) ?>–<?= h((string) $selectedSignupMaxLambda) ?> nm</span></span>
                        </label>
                    </section>

                    <label>
                        Fuseau
                        <input
                            type="text"
                            name="timezone"
                            value="<?= h($previewTimezone) ?>"
                            placeholder="ex: Europe/Paris"
                            data-timezone-input
                        >
                        <span class="input-hint">Le temps prend terre ici aussi.</span>
                    </label>

                    <div class="timezone-picks" aria-label="Fuseaux suggérés">
                        <?php foreach (['Europe/Paris', 'Europe/London', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo'] as $suggestedTimezone): ?>
                            <button type="button" class="timezone-chip" data-timezone-chip="<?= h($suggestedTimezone) ?>"><?= h($suggestedTimezone) ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="ghost-link inline-action" data-use-local-timezone>utiliser le fuseau local</button>
                    </div>
                    <p class="field-status" data-timezone-status>Le fuseau règle l'heure située de ta terre.</p>

                    <label>
                        Secret
                        <input
                            type="password"
                            name="password"
                            placeholder="8 caractères minimum"
                            required
                            minlength="<?= AUTH_MIN_PASSWORD_LENGTH ?>"
                            value="<?= h($form['password']) ?>"
                            autocomplete="new-password"
                        >
                        <span class="input-hint">Il protège la terre et scelle l'entrée.</span>
                    </label>

                    <div class="action-row">
                        <a class="ghost-link" href="<?= h(signup_stage_link(max(1, $totalPortalSteps), $form)) ?>">Relire avant de sceller</a>
                        <button type="submit">Rejoindre le peuple de l'O</button>
                    </div>
                </form>

                <aside class="signup-preview signup-journey-preview" data-origin-base="<?= h($originBase) ?>" data-preview-shell>
                    <span class="summary-label">Terre scellée à venir</span>
                    <strong class="preview-title" data-slug-output><?= h($previewSlug) ?></strong>
                    <div class="preview-grid">
                        <p><span>Lien</span><code data-land-link-output><?= h($originBase . LAND_ROUTE . '?u=' . $previewSlug) ?></code></p>
                        <p><span>Email virtuel</span><code data-email-output><?= h($previewSlug . '@o.local') ?></code></p>
                        <p><span>Fuseau</span><strong data-preview-timezone><?= h($previewTimezone) ?></strong></p>
                        <p><span>Programme</span><strong data-preview-program-label><?= h($selectedSignupLabel) ?></strong></p>
                        <p><span>Tonalité</span><strong data-preview-program-tone><?= h($selectedSignupTone) ?></strong></p>
                        <p><span>Signature</span><strong>λ <span data-signup-lambda-value><?= h((string) $selectedSignupLambda) ?></span> nm</strong></p>
                    </div>
                    <p class="panel-copy">Une fois créée, la terre sera immédiatement liée à ta session et t'emmènera vers son espace situé.</p>
                    <?php if ($authenticatedLand): ?>
                        <div class="action-row">
                            <a class="ghost-link" href="<?= h(LAND_ROUTE) ?>?u=<?= rawurlencode((string) ($authenticatedLand['slug'] ?? '')) ?>">Retour à ma terre actuelle</a>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
