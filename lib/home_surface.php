<?php
declare(strict_types=1);

require_once __DIR__ . '/str3m_media.php';
require_once __DIR__ . '/str3m_daily.php';

function home_surface_signup_portal_steps(): array
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
        $path = dirname(__DIR__) . '/aza_portals/' . $step['file'];
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

function render_home_partial(string $partial, array $state = []): string
{
    if (!preg_match('/^[a-z0-9_-]+$/', $partial)) {
        throw new InvalidArgumentException('Invalid home partial name.');
    }

    $path = dirname(__DIR__) . '/partials/home/' . $partial . '.php';
    if (!is_file($path)) {
        throw new RuntimeException('Home partial not found: ' . $partial);
    }

    ob_start();
    extract($state, EXTR_SKIP);
    require $path;
    return (string) ob_get_clean();
}

function home_surface_request_context(): array
{
    $host = request_host();
    $surfaceVariant = current_surface_variant($host);
    $isSowwwlXyz = $surfaceVariant === 'xyz';
    $isSowwwlIo = $surfaceVariant === 'io';
    $isLabSurface = $surfaceVariant === 'lab';
    $isSpatialSurface = $isSowwwlXyz || $isSowwwlIo;
    $isSpatialHeadsetMode = $isSowwwlIo && spatial_preview_mode($host) === 'headset';
    $showSpatialNativeSimulator = $isSowwwlIo && surface_preview_capable_host($host);
    $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $requestPath = o_request_path('/');
    $userCloudSlug = sowwwl_user_cloud_slug($host);
    $userCloudChamberFlag = (string) ($_GET['o_chamber'] ?? '');
    $isUserCloudChamberEntry = $userCloudSlug !== null
        && ($requestPath === USER_CLOUD_CHAMBER_PATH || $userCloudChamberFlag === '1');

    if ($userCloudSlug !== null && in_array($requestMethod, ['GET', 'HEAD'], true)) {
        if (($requestPath === '/' || $requestPath === '/index.php') && !$isUserCloudChamberEntry) {
            header('Location: ' . sowwwl_user_cloud_home_href($userCloudSlug), true, 302);
            exit;
        }
    }

    if (($host === '0wlslw0.com' || $host === 'www.0wlslw0.com') && ($requestPath === '/' || $requestPath === '/index.php')) {
        require dirname(__DIR__) . '/0wlslw0.php';
        exit;
    }

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
    $authenticatedLand = current_authenticated_land();
    $homeConnectionRequested = ((string) ($_GET['connexion'] ?? '')) === '1';
    $form = [
        'username' => '',
        'timezone' => DEFAULT_TIMEZONE,
        'password' => '',
        'login_identifier' => '',
        'land_program' => '',
        'lambda_nm' => '',
    ];

    if ($requestMethod === 'POST') {
        $action = (string) ($_POST['action'] ?? 'create');
        $form['username'] = trim((string) ($_POST['username'] ?? ''));
        $form['timezone'] = trim((string) ($_POST['timezone'] ?? ''));
        $form['password'] = (string) ($_POST['password'] ?? '');
        $form['login_identifier'] = trim((string) ($_POST['login_identifier'] ?? ''));
        $form['land_program'] = trim((string) ($_POST['land_program'] ?? ''));
        $form['lambda_nm'] = trim((string) ($_POST['lambda_nm'] ?? ''));
        $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
        $honeypot = (string) ($_POST['website'] ?? '');

        if ($action === 'login') {
            if ($form['login_identifier'] === '' || $form['password'] === '') {
                $message = 'Écris le nom de ta terre et son secret.';
                $messageType = 'warning';
            } else {
                try {
                    guard_land_login_request($csrfCandidate);
                    $land = authenticate_land($form['login_identifier'], $form['password']);
                    if (!$land) {
                        throw new RuntimeException('Identifiants incorrects.');
                    }

                    login_land($land);
                    header('Location: ' . o_route_href('/land', ['u' => (string) $land['slug'], 'session' => '1']), true, 303);
                    exit;
                } catch (InvalidArgumentException | RuntimeException $exception) {
                    $message = $exception->getMessage();
                    $messageType = 'warning';
                }
            }
        } else {
            if ($form['username'] === '') {
                $message = 'Écris un nom (2 à 42 caractères) pour créer ta terre.';
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
                    header('Location: ' . o_route_href('/land', ['u' => (string) $land['slug'], 'created' => '1', 'session' => '1']), true, 303);
                    exit;
                } catch (InvalidArgumentException | RuntimeException $exception) {
                    $message = $exception->getMessage();
                    $messageType = 'warning';
                }
            }
        }
    }

    $homeConnectionRequested = $homeConnectionRequested || $requestMethod === 'POST' || $message !== '';
    $shouldRenderHomeLoginForm = !$authenticatedLand && $homeConnectionRequested;
    $csrfToken = $shouldRenderHomeLoginForm ? issue_request_token('land-login', true) : '';

    return [
        'host' => $host,
        'surfaceVariant' => $surfaceVariant,
        'isSowwwlXyz' => $isSowwwlXyz,
        'isSowwwlIo' => $isSowwwlIo,
        'isLabSurface' => $isLabSurface,
        'isSpatialSurface' => $isSpatialSurface,
        'isSpatialHeadsetMode' => $isSpatialHeadsetMode,
        'showSpatialNativeSimulator' => $showSpatialNativeSimulator,
        'requestMethod' => $requestMethod,
        'requestPath' => $requestPath,
        'userCloudSlug' => $userCloudSlug,
        'userCloudChamberFlag' => $userCloudChamberFlag,
        'isUserCloudChamberEntry' => $isUserCloudChamberEntry,
        'message' => $message,
        'messageType' => $messageType,
        'timezoneSuggestions' => $timezoneSuggestions,
        'authenticatedLand' => $authenticatedLand,
        'homeConnectionRequested' => $homeConnectionRequested,
        'form' => $form,
        'shouldRenderHomeLoginForm' => $shouldRenderHomeLoginForm,
        'csrfToken' => $csrfToken,
    ];
}

function home_surface_profile_context(
    array $form,
    ?array $authenticatedLand,
    bool $isSpatialSurface,
    bool $isLabSurface,
    string $requestMethod,
    bool $homeConnectionRequested,
    string $message,
    ?string $userCloudSlug
): array {
    $pulse = land_pulse();
    $previewSlug = preview_land_slug($form['username']);
    $previewTimezone = $form['timezone'] !== '' ? $form['timezone'] : DEFAULT_TIMEZONE;
    $signupPrograms = land_visual_signup_catalog();
    $signupPortals = home_surface_signup_portal_steps();
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

    $selectedSignupLabel = (string) ($selectedSignupDefinition['label'] ?? $selectedSignupProgram);
    $selectedSignupTone = (string) ($selectedSignupDefinition['tone'] ?? '');
    $homeVisualOnly = $isSpatialSurface || $isLabSurface;
    $dailyStream = str3m_build_daily_stream(null);
    $dailyTextItem = is_array($dailyStream['items']['text'] ?? null) ? $dailyStream['items']['text'] : null;
    $dailyImageItem = is_array($dailyStream['items']['image'] ?? null) ? $dailyStream['items']['image'] : null;
    $dailyAudioItem = is_array($dailyStream['items']['audio'] ?? null) ? $dailyStream['items']['audio'] : null;
    $dailyTextBody = $dailyTextItem ? str3m_load_text_body($dailyTextItem) : '';
    $dailyTextExcerpt = trim((string) (($dailyTextItem['meta']['excerpt'] ?? '') ?: ''));
    $dailyImagePath = $dailyImageItem ? str3m_resolve_media_path($dailyImageItem) : '';
    $dailyAudioPath = $dailyAudioItem ? str3m_resolve_media_path($dailyAudioItem) : '';
    $activeVisualProfile = $authenticatedLand
        ? land_visual_profile($authenticatedLand)
        : land_collective_profile((string) ($dailyStream['mood'] ?? 'calm'));
    $activeLandProgram = (string) ($activeVisualProfile['program'] ?? 'collective');
    $activeLandLabel = (string) ($activeVisualProfile['label'] ?? 'collectif');
    $activeLandTone = (string) ($activeVisualProfile['tone'] ?? 'str3m public');
    $activeLambda = (int) ($activeVisualProfile['lambda_nm'] ?? 548);
    $activeLandSlug = $authenticatedLand ? (string) ($authenticatedLand['slug'] ?? '') : '';
    $activeLandUsername = $authenticatedLand ? (string) ($authenticatedLand['username'] ?? '') : '';
    $connectionNeedleAngle = $authenticatedLand
        ? (int) round(-34 + (($activeLambda - 380) / 400) * 68)
        : -38;
    $connectionNeedleAngle = max(-44, min(44, $connectionNeedleAngle));
    $connectionNeedleClass = $connectionNeedleAngle < -12
        ? ' connection-meter--low'
        : ($connectionNeedleAngle > 12 ? ' connection-meter--high' : ' connection-meter--mid');
    $homeHeroVuState = $connectionNeedleAngle < -12
        ? 'low'
        : ($connectionNeedleAngle > 12 ? 'high' : 'mid');
    $connectionStatusText = $authenticatedLand
        ? 'terre liée 3h33'
        : ($homeConnectionRequested ? 'connexion ouverte' : 'surface publique');
    $connectionDockOpen = $authenticatedLand || $message !== '' || $homeConnectionRequested;
    $homeUsesPublicShell = !$isSpatialSurface && !$isLabSurface && $userCloudSlug === null;
    if (
        $homeUsesPublicShell
        && in_array($requestMethod, ['GET', 'HEAD'], true)
        && !$authenticatedLand
        && !$homeConnectionRequested
        && !has_secure_session_cookie()
    ) {
        mark_public_response_cacheable(300);
    }

    return [
        'pulse' => $pulse,
        'previewSlug' => $previewSlug,
        'previewTimezone' => $previewTimezone,
        'signupPrograms' => $signupPrograms,
        'signupPortals' => $signupPortals,
        'defaultSignupProgram' => $defaultSignupProgram,
        'selectedSignupProgram' => $selectedSignupProgram,
        'signupPreviewSeed' => $signupPreviewSeed,
        'selectedSignupDefinition' => $selectedSignupDefinition,
        'selectedSignupMinLambda' => $selectedSignupMinLambda,
        'selectedSignupMaxLambda' => $selectedSignupMaxLambda,
        'defaultSignupLambda' => $defaultSignupLambda,
        'selectedSignupLambda' => $selectedSignupLambda,
        'selectedSignupLabel' => $selectedSignupLabel,
        'selectedSignupTone' => $selectedSignupTone,
        'homeVisualOnly' => $homeVisualOnly,
        'dailyStream' => $dailyStream,
        'dailyTextItem' => $dailyTextItem,
        'dailyImageItem' => $dailyImageItem,
        'dailyAudioItem' => $dailyAudioItem,
        'dailyTextBody' => $dailyTextBody,
        'dailyTextExcerpt' => $dailyTextExcerpt,
        'dailyImagePath' => $dailyImagePath,
        'dailyAudioPath' => $dailyAudioPath,
        'activeVisualProfile' => $activeVisualProfile,
        'activeLandProgram' => $activeLandProgram,
        'activeLandLabel' => $activeLandLabel,
        'activeLandTone' => $activeLandTone,
        'activeLambda' => $activeLambda,
        'activeLandSlug' => $activeLandSlug,
        'activeLandUsername' => $activeLandUsername,
        'connectionNeedleAngle' => $connectionNeedleAngle,
        'connectionNeedleClass' => $connectionNeedleClass,
        'homeHeroVuState' => $homeHeroVuState,
        'connectionStatusText' => $connectionStatusText,
        'connectionDockOpen' => $connectionDockOpen,
        'homeUsesPublicShell' => $homeUsesPublicShell,
    ];
}

function home_surface_signal_context(?array $authenticatedLand): array
{
    $signalReady = false;
    $unreadSignal = 0;
    $signalIdentityLabel = '';

    if ($authenticatedLand) {
        try {
            $signalReady = signal_mail_tables_ready();
            if ($signalReady) {
                $unreadSignal = signal_unread_total($authenticatedLand);
                $signalMailbox = signal_mailbox_for_land($authenticatedLand);
                $signalIdentityLabel = signal_identity_status_label((string) ($signalMailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
            }
        } catch (Throwable $exception) {
            $signalReady = false;
            $unreadSignal = 0;
            $signalIdentityLabel = '';
        }
    }

    return [
        'signalReady' => $signalReady,
        'unreadSignal' => $unreadSignal,
        'signalIdentityLabel' => $signalIdentityLabel,
    ];
}

function home_surface_view_context(array $state): array
{
    extract($state, EXTR_SKIP);
    unset($state);

    $homeStatusLabel = $authenticatedLand ? 'terre liée' : 'réseau minimal';
    $homeLead = $authenticatedLand
        ? 'Le tore suit la fréquence de ta terre. Ouvrir, écrire, dériver.'
        : 'Un réseau minimal : courant public, terre personnelle, guide discret.';
    $homePrimaryActionHref = $authenticatedLand
        ? o_route_href('/land', ['u' => $activeLandSlug])
        : o_route_href('/rejoindre');
    $guideHref = guide_public_href($host);
    $homeHref = o_route_href('/');
    $homeConnectionHref = o_route_href('/', ['connexion' => '1']) . '#connexion';
    $signalHref = o_route_href('/signal');
    $str3mHref = o_route_href('/str3m');
    $mapHref = o_route_href('/map');
    $azaHref = o_route_href('/aza');
    $joinHref = o_route_href('/rejoindre');
    $logoutHref = o_route_href('/logout.php');
    $publicAzaHref = 'https://sowwwl.com/aza';
    $publicStr3mHref = 'https://sowwwl.com/str3m';
    $publicGuideHref = guide_owner_origin() . '/';
    $publicInstrumentHref = sowwwl_instrument_href();
    $publicXyzHref = 'https://sowwwl.xyz/';
    $surfaceAzaHref = $isSowwwlIo ? $azaHref : $publicAzaHref;
    $surfaceStr3mHref = $isSowwwlIo ? $str3mHref : $publicStr3mHref;
    $surfaceGuideHref = $isSowwwlIo ? $guideHref : $publicGuideHref;
    $surfaceCounterpartHref = $isSowwwlIo ? $publicXyzHref : $publicInstrumentHref;
    $surfaceCounterpartLabel = $isSowwwlIo ? 'xyz' : 'io';
    $surfaceRouteClusterCopy = $isSowwwlIo
        ? 'Quand le centre a fini de respirer, la sortie reste dans la même surface : matière, guide et courant restent ici; xyz garde la membrane sœur.'
        : 'Quand le centre a fini de respirer, la sortie ne se disperse pas : matière sur sowwwl.com, guide sur 0wlslw0.com, appareillage entre io et xyz.';
    $surfaceWayfinderAxes = [
        [
            'axis' => 'matter',
            'kicker' => 'axe 01',
            'title' => $isSowwwlIo ? 'Str3m / aZa' : 'aZa / Str3m',
            'copy' => $isSowwwlIo
                ? 'Lire le courant, déposer une matière, puis revenir sans quitter la surface.'
                : 'Lire, déposer, relier la matière publique.',
            'href' => $isSowwwlIo ? $str3mHref : $surfaceStr3mHref,
            'signal' => $isSowwwlIo ? 'courant' : 'sowwwl.com',
        ],
        [
            'axis' => 'guide',
            'kicker' => 'axe 02',
            'title' => $isSowwwlIo ? '0wlslw0 / Signal' : '0wlslw0',
            'copy' => $isSowwwlIo
                ? 'Clarifier la prochaine porte, puis entrer dans la liaison sans perdre le volume.'
                : 'Choisir la route sans forcer la connexion.',
            'href' => $surfaceGuideHref,
            'signal' => $isSowwwlIo ? 'liaison' : 'guide',
        ],
        [
            'axis' => 'device',
            'kicker' => 'axe 03',
            'title' => $isSowwwlIo ? 'sowwwl.xyz' : 'sowwwl.io',
            'copy' => $isSowwwlIo
                ? 'Retrouver la membrane sœur, téléphone en main, quand le volume doit redevenir peau.'
                : 'Ouvrir l’instrument spatial du monde.',
            'href' => $surfaceCounterpartHref,
            'signal' => $isSowwwlIo ? 'membrane sœur' : 'instrument',
        ],
    ];
    $surfaceLandHref = $authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : $joinHref;
    $surfaceIslandHref = $authenticatedLand ? o_route_href('/island', ['u' => $activeLandSlug]) : $joinHref;
    $surfaceShoreHref = o_route_href('/sh0re');
    $surfaceEchoHref = $authenticatedLand && $activeLandUsername !== '' ? o_route_href('/echo', ['u' => $activeLandUsername]) : $signalHref;
    $surfaceSceptreHref = sceptre_view_href(sceptre_primary_device_slug(), $host);
    $spatialReadingOrderCards = $isSowwwlIo
        ? [
            [
                'kicker' => '01 terre',
                'title' => $authenticatedLand ? 'La terre garde l adresse.' : 'La terre donne l adresse.',
                'copy' => $authenticatedLand
                    ? 'Fuseau, fréquence, identité et partage repartent d ici avant toute lecture plus profonde.'
                    : 'Sans terre, le volume reste ouvert mais impersonnel. La terre lui donne une adresse stable.',
                'meta' => $authenticatedLand ? '@' . $activeLandSlug . ' · fuseau · partage' : 'nom · fuseau · ouverture',
                'links' => [
                    ['label' => $authenticatedLand ? 'Ouvrir la terre' : 'Poser une terre', 'href' => $surfaceLandHref],
                    ['label' => 'Carte', 'href' => $mapHref],
                ],
            ],
            [
                'kicker' => '02 memoire',
                'title' => 'La mémoire rend le volume relisible.',
                'copy' => $authenticatedLand
                    ? 'aZa dépose, l île relit, puis le courant peut revenir sans perdre sa provenance.'
                    : 'aZa prépare déjà la matière; une terre active permettra ensuite à l île de la relire calmement.',
                'meta' => 'aza · ile · provenance',
                'links' => [
                    ['label' => 'aZa', 'href' => $surfaceAzaHref],
                    ['label' => $authenticatedLand ? 'Île' : 'Ouvrir l île', 'href' => $surfaceIslandHref],
                ],
            ],
            [
                'kicker' => '03 capteurs',
                'title' => 'Les capteurs branchent le réel.',
                'copy' => 'Fenêtre écoute le paysage. Sceptre donne une main physique. Le tout reste local avant traduction.',
                'meta' => 'lumiere · geste · climat',
                'links' => [
                    ['label' => 'Fenêtre', 'href' => pocket_camera_view_href(null, $host)],
                    ['label' => 'Sceptre', 'href' => $surfaceSceptreHref],
                ],
            ],
            [
                'kicker' => '04 presence',
                'title' => 'La présence règle la distance.',
                'copy' => $authenticatedLand
                    ? 'Signal, Echo et Sh0re disent si l on écrit, si l on répond ou si l on laisse seulement un bord public.'
                    : 'Le volume reste public tant qu aucune terre ne répond. Ensuite viennent boîte, rivage et reprises plus directes.',
                'meta' => 'signal · sh0re · reponse',
                'links' => [
                    ['label' => 'Signal', 'href' => $signalHref],
                    ['label' => 'Sh0re', 'href' => $surfaceShoreHref],
                    ['label' => $authenticatedLand ? 'Echo' : '0wlslw0', 'href' => $authenticatedLand ? $surfaceEchoHref : $guideHref],
                ],
            ],
        ]
        : [];
    $promptSeeds = guide_prompt_seeds();
    $homeHeroLineOne = $authenticatedLand ? 'Ta terre' : 'Réseau';
    $homeHeroLineTwo = $authenticatedLand ? 'module le tore.' : 'minimal.';
    $homeThresholdHint = $authenticatedLand
        ? 'Le noyau reste simple : terre, adresse, courant.'
        : 'Un point d’entrée simple, sans forcer l’identification.';
    $homeStreamMood = (string) ($dailyStream['mood'] ?? 'calm');
    $homeStreamTemplate = (string) ($dailyStream['template'] ?? 'empty');
    $homeDailyTitle = $dailyTextItem
        ? (string) ($dailyTextItem['title'] ?? 'Texte du jour')
        : ($dailyAudioItem ? (string) ($dailyAudioItem['title'] ?? 'Nappe du jour') : 'Courant public en veille');
    $homeDailyCopySource = $dailyTextBody !== ''
        ? $dailyTextBody
        : ($dailyTextExcerpt !== ''
            ? $dailyTextExcerpt
            : (string) ($dailyAudioItem['meta']['excerpt'] ?? $dailyAudioItem['meta']['description'] ?? 'Le courant du jour relie les portes publiques, les terres et le guide sans forcer le passage.'));
    $homeDailyCopy = plasma_compact_text($homeDailyCopySource, 220);
    $homeDailyAudioTitle = $dailyAudioItem ? (string) ($dailyAudioItem['title'] ?? 'Nappe du jour') : 'Nappe en veille';
    $homeDailyImageTitle = $dailyImageItem ? (string) ($dailyImageItem['title'] ?? 'Surface du jour') : 'Surface en attente';
    $homeSignalState = $authenticatedLand
        ? ($unreadSignal > 0 ? $unreadSignal . ' en attente' : 'boîte claire')
        : 'liaison possible';
    $homeSurfaceProofs = [
        ['label' => 'lambda', 'value' => 'λ ' . $activeLambda . ' nm'],
        ['label' => 'mood', 'value' => $homeStreamMood],
        ['label' => 'terres', 'value' => (string) (int) ($pulse['count'] ?? 0)],
        ['label' => 'fuseaux', 'value' => (string) (int) ($pulse['timezones'] ?? 0)],
    ];
    $homeHeroPrimaryLabel = $authenticatedLand ? 'Rouvrir ma terre' : 'Poser une terre';
    $homeHeroSecondaryHref = $authenticatedLand ? $signalHref : $guideHref;
    $homeHeroSecondaryLabel = $authenticatedLand
        ? ($unreadSignal > 0 ? 'Écrire · ' . $unreadSignal . ' en attente' : 'Écrire maintenant')
        : 'Passer par 0wlslw0';
    $homeHeroQuickFacts = [
        ['label' => 'lambda', 'value' => 'λ ' . $activeLambda . ' nm'],
        ['label' => 'mood', 'value' => $homeStreamMood],
        [
            'label' => $authenticatedLand ? 'signal' : 'terres',
            'value' => $authenticatedLand ? $homeSignalState : (string) (int) ($pulse['count'] ?? 0),
        ],
    ];
    $homeEntryCards = $authenticatedLand
        ? [
            [
                'href' => $homePrimaryActionHref,
                'kicker' => '01 · terre',
                'title' => 'Rouvrir ma terre',
                'copy' => 'Revenir immédiatement à ton noyau situé.',
                'hint' => 'Dire : « ouvre ma terre »',
                'state' => $activeLandSlug !== '' ? '@' . $activeLandSlug : $activeLandLabel,
                'class' => 'entry-card entry-card--primary entry-card--land',
            ],
            [
                'href' => $signalHref,
                'kicker' => '02 · adresse',
                'title' => 'Écrire maintenant',
                'copy' => 'Aller droit vers Signal' . ($unreadSignal > 0 ? ' · ' . $unreadSignal . ' en attente' : '') . '.',
                'hint' => 'Dire : « ouvre Signal »',
                'state' => $homeSignalState,
                'class' => 'entry-card entry-card--signal',
            ],
            [
                'href' => $str3mHref,
                'kicker' => '03 · public',
                'title' => 'Relire le public',
                'copy' => 'Voir le courant avant de replonger dans ta terre.',
                'hint' => 'Dire : « ramène-moi vers Str3m »',
                'state' => $homeStreamMood,
                'class' => 'entry-card',
            ],
            [
                'href' => $publicInstrumentHref,
                'kicker' => '04 · instrument',
                'title' => 'Jouer l’instrument',
                'copy' => 'Ouvrir sowwwl.io pour Terre, Mine, visage et paysage.',
                'hint' => 'Dire : « ouvre l’instrument »',
                'state' => 'sowwwl.io',
                'class' => 'entry-card entry-card--instrument',
            ],
        ]
        : [
            [
                'href' => $str3mHref,
                'kicker' => '01 · public',
                'title' => 'Voir d’abord',
                'copy' => 'Entrer publiquement dans Str3m et sentir le courant.',
                'hint' => 'Dire : « je veux visiter publiquement »',
                'state' => $homeStreamMood,
                'class' => 'entry-card entry-card--primary',
            ],
            [
                'href' => $joinHref,
                'kicker' => '02 · terre',
                'title' => 'Poser une terre',
                'copy' => 'Ouvrir un lieu à toi, situé, avec sa fréquence.',
                'hint' => 'Dire : « je veux poser une terre »',
                'state' => 'adresse située',
                'class' => 'entry-card entry-card--land',
            ],
            [
                'href' => $guideHref,
                'kicker' => '03 · 0wlslw0',
                'title' => 'Me faire guider',
                'copy' => 'Passer par 0wlslw0 pour clarifier vite, puis continuer.',
                'hint' => 'Dire : « aide-moi à choisir »',
                'state' => 'guide',
                'class' => 'entry-card entry-card--guide',
            ],
            [
                'href' => $publicInstrumentHref,
                'kicker' => '04 · instrument',
                'title' => 'Jouer l’instrument',
                'copy' => 'Ouvrir sowwwl.io sans compte pour tester Terre, Mine et le monde.',
                'hint' => 'Dire : « je veux jouer »',
                'state' => 'sowwwl.io',
                'class' => 'entry-card entry-card--instrument',
            ],
        ];
    $homeRouteNodes = [
        [
            'index' => '01',
            'kicker' => 'str3m',
            'title' => 'Lire le courant',
            'copy' => 'La matière publique du jour, accordée au mood ' . $homeStreamMood . '.',
            'href' => $str3mHref,
            'signal' => $homeStreamTemplate,
        ],
        [
            'index' => '02',
            'kicker' => 'terre',
            'title' => $authenticatedLand ? 'Rouvrir ta terre' : 'Poser une terre',
            'copy' => $authenticatedLand
                ? 'Revenir au noyau ' . ($activeLandSlug !== '' ? $activeLandSlug : $activeLandLabel) . ', avec sa fréquence située.'
                : 'Créer un point stable dans le tore, lisible sans perdre la douceur du seuil.',
            'href' => $homePrimaryActionHref,
            'signal' => $activeLandLabel,
        ],
        [
            'index' => '03',
            'kicker' => 'signal',
            'title' => 'Écrire juste',
            'copy' => $authenticatedLand
                ? 'La boîte reste disponible pour relier, répondre, préciser.'
                : 'La porte d’adresse attend une terre pour devenir vraiment personnelle.',
            'href' => $signalHref,
            'signal' => $homeSignalState,
        ],
        [
            'index' => '04',
            'kicker' => '0wlslw0',
            'title' => 'Se faire guider',
            'copy' => 'Un guide bref pour choisir la prochaine entrée sans casser le fil.',
            'href' => $guideHref,
            'signal' => 'guide',
        ],
        [
            'index' => '05',
            'kicker' => 'instrument',
            'title' => 'Jouer le monde',
            'copy' => 'La porte sowwwl.io reste ouverte pour Terre, Mine, caméra et paysage.',
            'href' => $publicInstrumentHref,
            'signal' => 'sowwwl.io',
        ],
    ];
    $membraneBridgeHref = plasma_bridge_url();
    $labSensorEndpointHref = o_route_href('/ingest/sensor');
    $labPublicPlasmaFeedHref = plasma_feed_url();
    $pocketCameraStreamHref = pocket_camera_stream_url();
    $pocketCameraSnapshotHref = pocket_camera_snapshot_url();
    $pocketCameraLabel = pocket_camera_label();
    $pocketCameraAvailable = $pocketCameraStreamHref !== '' || $pocketCameraSnapshotHref !== '';
    $sceptreDeviceSlug = sceptre_primary_device_slug();
    $sceptreFeedHref = sceptre_feed_href($sceptreDeviceSlug, $host);
    $sceptreConstellationFeedHref = sceptre_constellation_feed_href($host);
    $sceptreViewHref = sceptre_view_href($sceptreDeviceSlug, $host);
    $labQaIslandHref = o_route_href('/island', ['u' => 'qa-multimatiere']);
    $labPocketHref = 'https://pocket.lab.sowwwl.cloud/';
    $labApiHealthHref = 'https://api.lab.sowwwl.cloud/healthz';
    $labSensorConfigured = trim((string) (getenv('SOWWWL_PI_TOKEN') ?: '')) !== '';
    $labRecentPlasmaEvents = $isLabSurface ? plasma_recent_events(6) : [];
    $labPlasmaWeather = plasma_weather_from_events($labRecentPlasmaEvents);
    $spatialSurfaceHostLabel = surface_brand_label($host);
    $spatialSurfaceEyebrow = $isSowwwlIo ? 'surface spatiale / terre / memoire / capteurs / presence' : 'surface torique / monde reel';
    $spatialSurfaceTitle = $isSowwwlIo ? 'Le tore s ouvre dans l espace.' : 'Le tore écoute le monde réel.';
    $spatialSurfaceLead = $isSowwwlIo
        ? 'Ici, O. devient interface volumique. Terre donne l adresse, la mémoire garde la matière, les capteurs ouvrent le réel et la présence règle la distance.'
        : 'Ici, la surface devient membrane. Mouvement, souffle, lumière et grain entrent, puis le tore les rend lisibles.';
    $spatialMappingModeLabel = $isSowwwlIo ? 'espace / plasma / tore' : 'réalité / plasma / tore';
    $spatialMappingTitle = $isSowwwlIo ? 'Le volume filtre ce qu il reçoit.' : 'La peau filtre ce qu’elle reçoit.';
    $spatialMappingCopy = $isSowwwlIo
        ? 'Le monde touche, le plasma traduit, puis le tore ouvre une lecture située dans le volume.'
        : 'Le réel touche, le plasma traduit, le tore ouvre la lecture.';
    $spatialMappingRaNote = $isSowwwlIo
        ? 'Quand la couche spatiale s ouvre, la couche dominante peut reprendre la main ici pour garder la lecture située.'
        : 'Quand la membrane s ouvre, la couche dominante peut reprendre la main ici pour garder la lecture située.';
    $spatialMappingReading = $isSowwwlIo
        ? 'Le plasma fait le lien entre la réalité et le volume spatial. Le tore n’est pas au-dessus du monde : il donne au volume sa prise lisible.'
        : 'Le plasma fait le lien entre la réalité et la surface torique. Le tore n’est pas au-dessus du monde : il s’y branche.';
    $spatialMappingBadge = $isSowwwlIo ? 'spatial preview' : 'real-world map';
    $spatialCameraTitle = $isSowwwlIo ? 'La couche spatiale attend un premier accord.' : 'La membrane attend un geste.';
    $spatialCameraStatus = $isSowwwlIo
        ? 'Active la couche pour ouvrir mouvement, voix, lumière, caméra et veille active, puis préparer une lecture spatiale du tore. Terre et Mine permet aussi de tester la montée sans capteurs. Aucune image brute n est envoyée. Si le pont plasma est actif, seuls des signaux synthétiques quittent cette couche.'
        : 'Active la membrane pour ouvrir mouvement, voix, lumière, caméra et veille active, puis laisser le téléphone jouer un thérémin local et accorder légèrement la voix. Terre et Mine permet aussi de tester la montée sans capteurs. Aucune image brute n est envoyée. Si le pont plasma est actif, seuls des signaux synthétiques quittent cette couche.';
    $spatialSensorPanelLabel = $isSowwwlIo ? 'capteurs & lisiere' : 'rituel & capteurs';
    $spatialSensorPanelTitle = $isSowwwlIo ? 'Capteurs & lisiere physique' : 'Rituel & capteurs';
    $spatialSensorPanelMeta = $isSowwwlIo ? 'camera, mouvement, lumiere, sceptre' : 'camera, mouvement, lumiere, veille';
    $spatialSceptreState = $isSowwwlIo ? 'Le sceptre dort encore dans le volume.' : 'Le sceptre dort encore dans le tore.';
    $spatialSceptreCopy = $isSowwwlIo
        ? 'Le Pi 3 B+ et son Sensor HAT peuvent devenir une main, un climat et un rythme pour le volume.'
        : 'Le Pi 3 B+ et son Sensor HAT peuvent devenir une main, un climat et un rythme pour la surface.';
    $spatialDeviceNote = $isSowwwlIo
        ? 'Le web pilote ici silence, niveau, partage, mode app et pont natif. Un client visionOS ou Quest pourra ensuite relier ce même tore à des permissions spatiales plus fines.'
        : 'Le web pilote ici silence, niveau, haptique, partage et mode app. Un wrapper natif pourra ensuite donner le silence et le volume réels du téléphone.';
    $spatialDeviceSummaryLabel = $isSowwwlIo ? '02 presence' : '02 appareil';
    $spatialDevicePanelTitle = $isSowwwlIo ? 'Presence, partage, pont' : 'Sortie, partage, pont';
    $spatialDevicePanelMeta = $isSowwwlIo ? 'niveau O., partage, natif' : 'niveau O., silence, natif';
    $spatialWorldPanelLabel = $isSowwwlIo ? 'presence jouable' : 'monde instrument';
    $spatialWorldSummaryLabel = $isSowwwlIo ? '03 presence' : '03 monde';
    $spatialWorldPanelTitle = $isSowwwlIo ? 'Presence jouable' : 'Monde instrument';
    $spatialWorldHeadLabel = $isSowwwlIo ? 'presence jouable' : 'monde instrument';
    $spatialWorldStageAria = $isSowwwlIo
        ? 'Volume de jeu Terre et Mine, jouable au doigt, au pointeur et au clavier'
        : 'Surface de jeu Terre et Mine, jouable au doigt, au pointeur et au clavier';
    $spatialWorldStaticCopy = $isSowwwlIo
        ? 'Le monde devient présence jouable: visage, corps, lumière, paysage et toucher peuvent tous nourrir le volume.'
        : 'Le monde reste un instrument: visage, corps, lumière, paysage et toucher peuvent tous nourrir le tore.';
    $spatialGestureTitle = $isSowwwlIo
        ? 'Traverse, puis laisse regard, geste et appareil infléchir le volume.'
        : 'Traverse, puis laisse le téléphone infléchir le tore.';
    $spatialGestureCopy = $isSowwwlIo
        ? ($isSpatialHeadsetMode
            ? 'Mode casque web: Tab, flèches, focus large et clic gardent la lecture stable. Le regard, le pinch et l ancrage spatial viendront avec le client natif.'
            : 'Mode écran: glisse ou pointe pour pivoter, puis ouvre les routes avant de basculer en mode casque web. Le centre et le geste en O ouvrent toujours 0wlslw0.')
        : 'Glisse pour pivoter. Sur mobile, la glisse garde maintenant vraiment la prise du tore; un appui long ouvre les routes cardinales, gauche vers Signal, haut vers Str3m, droite vers aZa, bas vers le noyau. Le centre ou un geste en O ouvrent aussi 0wlslw0.';
    $torusAriaLabel = $isSowwwlIo
        ? ($isSpatialHeadsetMode
            ? 'Torus ambiant : tabulation, flèches, focus large et clic gardent la dérive stable en mode casque web. 0wlslw0 reste au centre comme porte rapide.'
            : 'Torus ambiant : pointe ou glisse pour prendre un repère en mode écran, puis flèches pour dériver au clavier. 0wlslw0 reste au centre comme porte rapide.')
        : 'Torus ambiant : glisser pour pivoter, roulette pour traverser, flèches pour dériver. Sur mobile, la glisse fait pivoter le tore et un appui long arme les routes : gauche vers Signal, haut vers Str3m, droite vers aZa, bas vers le noyau. Le centre ou un geste en O ouvrent aussi 0wlslw0.';
    $spatialModeScreenHref = $isSowwwlIo ? o_current_route_href(['spatial' => null], $host, false) : '';
    $spatialModeHeadsetHref = $isSowwwlIo ? o_current_route_href(['spatial' => 'headset'], $host, false) : '';
    $spatialModeTitle = $isSowwwlIo
        ? ($isSpatialHeadsetMode ? 'Mode casque web actif.' : 'Mode écran actif.')
        : '';
    $spatialModeCopy = $isSowwwlIo
        ? ($isSpatialHeadsetMode
            ? 'Cette passe privilégie le focus large, le clavier et les actions franches pour tester un casque dès maintenant, sans promettre encore le vrai passthrough ni les gestes natifs.'
            : 'Cette passe garde une lecture écran plus souple pour maquetter, puis permet de basculer explicitement en mode casque quand on veut tester le parcours spatial.')
        : '';
    $spatialActivationLabel = $isSowwwlIo ? 'Activer la couche spatiale' : 'Activer la membrane';
    $spatialReleaseLabel = $isSowwwlIo ? 'Relâcher la couche' : 'Relâcher la membrane';
    $spatialWayfinderAria = $isSowwwlIo ? 'Routes rapides du volume spatial' : 'Routes rapides de la surface';
    $spatialWayfinderIntroLabel = $isSowwwlIo ? 'routes spatiales' : 'routes lisibles';
    $spatialTorusBodyCopy = $isSowwwlIo
        ? 'Un volume navigable où les intensités deviennent lecture, interface et dérive située.'
        : 'Une membrane navigable où les intensités deviennent lecture, interface et dérive située.';
    $spatialSensorSummaryLabel = $isSowwwlIo ? '01 capteurs' : '01 membrane';
    $spatialSensorAriaLabel = $isSowwwlIo ? 'État direct de la couche spatiale' : 'État direct de la membrane';
    $spatialWorkshopLabel = $isSowwwlIo ? 'memoire & atelier spatial' : 'atelier membrane';
    $spatialWorkshopTitle = $isSowwwlIo ? 'Memoire & atelier spatial' : 'Atelier membrane';
    $spatialMusicSummaryLabel = $isSowwwlIo ? '04 memoire' : '04 atelier';
    $spatialMusicPanelMeta = $isSowwwlIo ? 'memoire, voyage, motif, prises' : 'lecture, voyage, motif, prises';
    $spatialMusicControlsAria = $isSowwwlIo ? 'Réglages musicaux du volume spatial' : 'Réglages musicaux de la membrane';
    $spatialMusicScenesAria = $isSowwwlIo ? 'Scènes du volume spatial' : 'Scènes de la membrane';
    $spatialMusicGuideGridAria = $isSowwwlIo ? 'Lecture musicale du volume spatial' : 'Lecture musicale du tore';
    $spatialMusicDeskAria = $isSowwwlIo ? 'Console musicale du volume spatial' : 'Console musicale membrane';
    $spatialWorkshopCopy = $isSowwwlIo
        ? 'Enchaîne des scènes mémorisées sur plusieurs mesures pour transformer le volume en forme jouable, enregistrable et partageable.'
        : 'Enchaîne des scènes mémorisées sur plusieurs mesures pour transformer la membrane en forme jouable et enregistrable.';
    $spatialMusicFxCopy = $isSowwwlIo
        ? 'Ouvre l espace, la repetition, le grain et l air du master pour transformer le volume en chambre, verriere, brume ou braise.'
        : 'Ouvre l espace, la repetition, le grain et l air du master pour transformer la membrane en chambre, vitre, brume ou braise.';
    $spatialMusicMemoryTitle = $isSowwwlIo ? 'memoire situee' : 'memoire du tore';
    $spatialMasterCopy = $isSowwwlIo
        ? 'Le bus final du volume, celui qui part vers l oreille, les prises et le futur pont natif.'
        : 'Le bus final de la membrane, celui qui part vers l oreille et les prises.';
    $spatialMusicMixerAria = $isSowwwlIo ? 'Mixer spatial' : 'Mixer membrane';
    $spatialTrackTerreCopy = $isSowwwlIo ? 'La charpente stable, la gravite et le corps du volume.' : 'La charpente stable, la gravite et le corps du tore.';
    $spatialArTitle = $isSowwwlIo ? 'Le volume se pose sur le monde.' : 'Le tore se pose sur le monde.';
    $spatialArStatus = $isSowwwlIo
        ? 'La réalité garde encore la main. Active la couche spatiale pour laisser les trois couches se répartir.'
        : 'La réalité garde encore la main. Active la membrane pour laisser les trois couches se répartir.';
    $spatialArDirective = $isSowwwlIo
        ? 'Directive: garder les plans du monde lisibles, laisser le plasma annoter, puis ouvrir le volume seulement là où il doit prendre.'
        : 'Directive: garder les plans du monde lisibles, laisser le plasma annoter, puis ouvrir le tore seulement là où il doit prendre.';
    $spatialArPilotTitle = $isSowwwlIo ? 'Prise active: cadrer la presence.' : 'Prise active: cadrer le volume.';
    $spatialArUsage = $isSowwwlIo
        ? 'Raccourcis: R ancre, P traduit, T boucle, M tresse. En mode casque web, le volume peut changer de régime sans perdre la lecture située.'
        : 'Raccourcis: R ancre, P traduit, T boucle, M tresse. En mode casque web, le tore peut changer de régime sans perdre la lecture située.';
    $spatialRoutesTitle = $isSowwwlIo ? 'Passages & appareillage' : 'Sorties & appareillage';
    $spatialRoutesMeta = $isSowwwlIo ? 'matiere, guide, presence' : 'matière, guide, membrane';
    $spatialMappingTorusWhisper = $isSowwwlIo
        ? 'Le volume devient seuil, navigation, orientation.'
        : 'La surface devient seuil, navigation, orientation.';
    $spatialMappingTorusSummary = $isSowwwlIo
        ? 'Le tore donne au volume sa peau lisible, ses prises et sa dérive située.'
        : 'Le tore est la peau visible de Sowwwl. Il accueille la projection du réel et permet d’entrer dans le réseau par dérive, lecture et résonance.';
    $ioSpatialVolumeNodes = [];
    if ($isSowwwlIo) {
        $ioSpatialVolumeNodes = [
            [
                'key' => 'guide',
                'label' => '0wlslw0',
                'layer' => 'seuil',
                'tone' => 'guide',
                'href' => $guideHref,
                'x' => 0,
                'y' => -3.8,
                'z' => 9.4,
                'copy' => 'Le centre de clarification. On y revient pour nommer la prochaine porte avant de dériver.',
            ],
            [
                'key' => 'land',
                'label' => $authenticatedLand ? 'Terre' : 'Rejoindre',
                'layer' => 'ancre',
                'tone' => 'land',
                'href' => $authenticatedLand ? o_route_href('/land', ['u' => $activeLandSlug]) : $joinHref,
                'x' => 0,
                'y' => 2.1,
                'z' => 1.2,
                'copy' => $authenticatedLand ? 'L identité active module déjà le volume et la fréquence de lecture.' : 'La terre donne une adresse au volume avant les lectures plus profondes.',
            ],
            [
                'key' => 'aza',
                'label' => 'aZa',
                'layer' => 'matiere',
                'tone' => 'aza',
                'href' => $authenticatedLand ? o_route_href('/aza', ['u' => $activeLandSlug]) : $azaHref,
                'x' => 7.6,
                'y' => 4.2,
                'z' => -2.6,
                'copy' => 'La matière déposée devient strate, source, fichier, indice et future île lisible.',
            ],
            [
                'key' => 'island',
                'label' => 'Île',
                'layer' => 'lecture',
                'tone' => 'island',
                'href' => $authenticatedLand ? o_route_href('/island', ['u' => $activeLandSlug]) : $joinHref,
                'x' => -3.5,
                'y' => 8.2,
                'z' => -8.4,
                'copy' => $authenticatedLand ? 'L île relit les matières en lecteurs situés, au calme, avec reprise possible.' : 'Une terre active rendra l île lisible et partageable.',
            ],
            [
                'key' => 'map',
                'label' => 'Map',
                'layer' => 'orientation',
                'tone' => 'map',
                'href' => $mapHref,
                'x' => -8.5,
                'y' => -1.2,
                'z' => 4.6,
                'copy' => 'La carte garde les nœuds, les courants et la géométrie du tore visibles.',
            ],
            [
                'key' => 'signal',
                'label' => 'Signal',
                'layer' => 'relation',
                'tone' => 'signal',
                'href' => $signalHref,
                'x' => 8.4,
                'y' => -0.8,
                'z' => 4.1,
                'copy' => 'La boîte tient les fils, le contexte et les reprises entre présences.',
            ],
            [
                'key' => 'str3m',
                'label' => 'Str3m',
                'layer' => 'courant',
                'tone' => 'str3m',
                'href' => $str3mHref,
                'x' => -7.2,
                'y' => 4.6,
                'z' => -2.9,
                'copy' => 'Le courant public montre ce qui circule maintenant et ce qui peut devenir prise.',
            ],
            [
                'key' => 'echo',
                'label' => 'Echo',
                'layer' => 'réponse',
                'tone' => 'echo',
                'href' => o_route_href('/echo'),
                'x' => 3.8,
                'y' => 8.1,
                'z' => -8.1,
                'copy' => 'L écho relance une présence en direct sans perdre la mémoire de Signal.',
            ],
            [
                'key' => 'camera',
                'label' => 'Fenêtre',
                'layer' => 'capteur',
                'tone' => 'lab',
                'href' => pocket_camera_view_href(null, $host),
                'x' => 0,
                'y' => -7.7,
                'z' => -6.3,
                'copy' => 'La fenêtre harmonique donne au volume une prise physique: paysage, lumière, climat et présence restent lisibles sans sortir de sowwwl.io.',
            ],
            [
                'key' => 'sceptre',
                'label' => 'Sceptre',
                'layer' => 'geste',
                'tone' => 'lab',
                'href' => $sceptreViewHref,
                'x' => 7.1,
                'y' => -6.1,
                'z' => -5.8,
                'copy' => 'Le sceptre donne une main physique au volume: mouvement, halo, percussion et climat peuvent piloter le tore depuis la même surface.',
            ],
        ];
    }
    $pageHeadVariant = $isSowwwlIo ? 'io' : ($isSowwwlXyz ? 'xyz' : ($isLabSurface ? 'lab' : 'main'));
    $pageTitle = $isLabSurface
        ? 'O. Lab · atelier mobile'
        : ($isSowwwlIo
            ? 'sowwwl.io · instrument spatial'
            : ($isSowwwlXyz ? 'sowwwl.xyz · membrane musicale' : SITE_TITLE));
    $pageDescription = $isLabSurface
        ? 'O. Lab — atelier mobile du tore pour capteurs, pocket, plasma et livraison différée.'
        : ($isSowwwlIo
            ? 'SOWWWL IO — surface spatiale du tore pour Vision Pro, casques XR et lecture située.'
            : ($isSowwwlXyz
                ? 'SOWWWL XYZ — membrane musicale du tore pour téléphone, capteurs, monde instrument et gestes situés.'
                : (SITE_TITLE . ' — entrer publiquement, poser une terre, ou passer par 0wlslw0.')));
    $pageScriptBundle = $homeUsesPublicShell ? 'public-shell' : 'main';

    return get_defined_vars();
}
