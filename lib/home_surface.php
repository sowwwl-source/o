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
