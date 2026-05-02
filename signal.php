<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/signal');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$guideHref = '/0wlslw0';
$signalGuide = guide_path('signal');
$csrfToken = csrf_token();
$land = current_authenticated_land();

$verifyToken = trim((string) ($_GET['verify'] ?? ''));
$verifyLand = trim((string) ($_GET['land'] ?? ''));
if ($verifyToken !== '' && $verifyLand !== '') {
    $verified = signal_mail_tables_ready() && signal_verify_identity_token($verifyLand, $verifyToken);
    header('Location: /signal?' . ($verified ? 'status=identity-verified' : 'error=identity-invalid'), true, 303);
    exit;
}

$tablesReady = signal_mail_tables_ready();
$ambientProfile = $land ? land_visual_profile($land) : land_collective_profile('dense');
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$land) {
        header('Location: /signal?error=auth', true, 303);
        exit;
    }

    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        header('Location: /signal?error=csrf', true, 303);
        exit;
    }

    if (!$tablesReady) {
        header('Location: /signal?error=messaging', true, 303);
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? 'send_message'));

    try {
        if ($action === 'request_identity') {
            signal_request_identity_verification($land, (string) ($_POST['notification_email'] ?? ''));
            header('Location: /signal?status=identity-sent', true, 303);
            exit;
        }

        if ($action === 'send_message') {
            $receiverSlug = (string) ($_POST['receiver_slug'] ?? '');
            signal_send_message(
                $land,
                $receiverSlug,
                (string) ($_POST['subject'] ?? ''),
                (string) ($_POST['body'] ?? '')
            );
            header('Location: /signal?u=' . rawurlencode(normalize_username($receiverSlug)) . '&status=message-sent', true, 303);
            exit;
        }

        header('Location: /signal?error=validation', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        header('Location: /signal?error=validation&note=' . rawurlencode($exception->getMessage()), true, 303);
        exit;
    } catch (RuntimeException $exception) {
        header('Location: /signal?error=delivery&note=' . rawurlencode($exception->getMessage()), true, 303);
        exit;
    }
}

$statusCode = trim((string) ($_GET['status'] ?? ''));
$errorCode = trim((string) ($_GET['error'] ?? ''));
$note = trim((string) ($_GET['note'] ?? ''));

if ($statusCode !== '') {
    $messageType = 'success';
    $message = match ($statusCode) {
        'identity-sent' => 'Lien de validation envoyé. La terre pourra recevoir les notifications dès confirmation.',
        'identity-verified' => 'Identité validée. Signal est désormais lié à une adresse vérifiée.',
        'message-sent' => 'Message transmis dans la boîte de réception de l’autre terre.',
        default => '',
    };
}

if ($errorCode !== '') {
    $messageType = 'warning';
    $message = match ($errorCode) {
        'auth' => 'Ouvre une terre pour accéder à la messagerie Signal.',
        'csrf' => 'Le jeton de session a expiré. Recharge la page et réessaie.',
        'messaging' => 'La messagerie Signal n’est pas encore initialisée côté base. Lance la migration SQL Signal du projet pour ouvrir la boîte.',
        'identity-invalid' => 'Le lien de validation est invalide ou expiré.',
        'delivery' => 'Le message ou la validation n’a pas pu être distribué pour le moment.',
        'validation' => 'Les informations transmises sont incomplètes ou invalides.',
        default => 'Signal ne peut pas ouvrir la conversation pour le moment.',
    };

    if ($note !== '') {
        $message .= ' ' . $note;
    }
}

$mailbox = $land && $tablesReady ? signal_mailbox_for_land($land) : [];
$contacts = $land && $tablesReady ? signal_contact_candidates($land) : [];
$recentContacts = array_slice($contacts, 0, 6);
$resonantContacts = array_slice($contacts, 0, 5);
$targetSlug = trim((string) ($_GET['u'] ?? ''));
$targetLand = null;
$conversation = [];
$unreadTotal = 0;

if ($land && $tablesReady) {
    $unreadTotal = signal_unread_total($land);
    if ($targetSlug !== '') {
        $targetLand = signal_find_land_by_slug($targetSlug);
        if ($targetLand) {
            $conversation = signal_load_conversation($land, (string) $targetLand['slug']);
        }
    }
}

$identityStatus = trim((string) ($mailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
$notificationEmail = trim((string) ($mailbox['notification_email'] ?? ''));
$virtualAddress = $land ? signal_virtual_address($land) : '';
$currentDraftScope = $targetLand
    ? 'thread:' . (string) ($targetLand['slug'] ?? '')
    : 'new';
$signalLiveTarget = $targetLand ? (string) ($targetLand['slug'] ?? '') : '';
$signalHistoryHtml = signal_render_conversation_html($conversation, $land ?? [], true, 'Aucune trace encore entre vos deux boîtes.');
$signalHistoryHash = sha1($signalHistoryHtml);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Signal — boîte de réception située dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Signal — <?= h(SITE_TITLE) ?></title>
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
        <p class="eyebrow"><strong>signal</strong> <span>messagerie située</span></p>
        <h1 class="land-title signal-title">
            <strong>Boîte aux lettres de terre.</strong>
            <span><?= $land ? h($virtualAddress) : 'liaison réservée aux terres' ?></span>
        </h1>
        <p class="lead">Signal devient l’adresse, l’inbox et la preuve légère qu’une terre existe. Le public reste dans Str3m ; ici, on parle à quelqu’un.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <a class="meta-pill meta-pill-link" href="/str3m">courant public</a>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($land): ?>
                <span class="meta-pill">terre liée : <?= h((string) $land['slug']) ?></span>
                <?php if ($tablesReady): ?>
                    <span class="meta-pill"><?= h(signal_identity_status_label($identityStatus)) ?></span>
                    <span class="meta-pill" data-signal-unread-label><?= $unreadTotal ?> message<?= $unreadTotal > 1 ? 's' : '' ?> non lu<?= $unreadTotal > 1 ? 's' : '' ?></span>
                    <span class="meta-pill" data-message-live-indicator><?= $signalLiveTarget !== '' ? 'direct · veille' : 'direct · en attente' ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="meta-pill">lecture de principe</span>
            <?php endif; ?>
        </div>
    </header>

    <section class="panel reveal meaning-panel" aria-labelledby="signal-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="signal-meaning-title">Pourquoi cette porte existe</h2>
                <p class="panel-copy"><?= h((string) ($signalGuide['copy'] ?? 'Une adresse située pour écrire à une autre terre, sans bruit public.')) ?></p>
            </div>
            <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0 : me guider</a>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <section class="panel reveal">
            <div class="flash flash-<?= h($messageType) ?>" aria-live="polite">
                <p><?= h($message) ?></p>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!$land): ?>
        <section class="panel reveal">
            <div class="section-topline">
                <div>
                    <h2>Signal demande une terre</h2>
                    <p class="panel-copy">La boîte existe seulement quand une terre a un nom, un fuseau et une adresse virtuelle. Sans terre, tu peux encore lire Str3m publiquement.</p>
                </div>
            </div>
            <div class="action-row">
                <a class="pill-link" href="/">Poser une terre</a>
                <a class="ghost-link" href="/str3m">Aller vers Str3m</a>
            </div>
        </section>
    <?php elseif (!$tablesReady): ?>
        <section class="panel reveal">
            <div class="section-topline">
                <div>
                    <h2>Messagerie non initialisée</h2>
                    <p class="panel-copy">Le code est prêt, mais les tables SQL de Signal ne sont pas encore présentes. Exécute la migration Signal du projet (<code>../migrations/2026_05_02_signal_mail.sql</code> depuis le dossier <code>o/</code>) pour activer durablement la boîte et Écho.</p>
                </div>
                <span class="badge">migration requise</span>
            </div>
        </section>
    <?php else: ?>
        <section
            class="signal-grid reveal"
            data-message-live
            data-live-view="signal"
            data-live-api="/signal_live.php"
            data-live-target="<?= h($signalLiveTarget) ?>"
            data-live-interval="2500"
            data-live-hash="<?= h($signalHistoryHash) ?>"
            data-live-message-count="<?= h((string) count($conversation)) ?>"
        >
            <section class="panel signal-col" aria-labelledby="signal-mailbox-title">
                <div class="section-topline">
                    <div>
                        <h2 id="signal-mailbox-title">Boîte de la terre</h2>
                        <p class="panel-copy">Adresse interne : <?= h($virtualAddress) ?></p>
                    </div>
                    <span class="badge"><?= h(signal_identity_status_label($identityStatus)) ?></span>
                </div>

                <p class="panel-copy">L’adresse virtuelle identifie la terre à l’intérieur de l’archipel. Pour relier ce Signal à une présence réelle, ajoute une adresse de notification et valide-la.</p>

                <form action="/signal" method="post" class="land-form signal-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="request_identity">

                    <label>
                        Email de notification / validation
                        <input type="email" name="notification_email" value="<?= h($notificationEmail) ?>" placeholder="vous@domaine.tld" required>
                        <span class="input-hint">Un lien de validation y sera envoyé. Tant qu’il n’est pas confirmé, la terre reste en attente.</span>
                    </label>

                    <button type="submit">Valider cette identité</button>
                </form>

                <div class="signal-list-block">
                    <div class="section-topline signal-subhead">
                        <div>
                            <h2>Contacts</h2>
                            <p class="panel-copy">Terres connues, conversations ouvertes et messages non lus.</p>
                        </div>
                        <span class="badge"><?= h((string) count($contacts)) ?> terre<?= count($contacts) > 1 ? 's' : '' ?></span>
                    </div>

                    <div class="signal-flow-shell">
                        <form action="/signal" method="get" class="land-form signal-form signal-open-form" data-signal-open-form>
                            <div class="signal-algora-shell" data-signal-algora>
                                <div>
                                    <span class="signal-flow-hint">algoRa · tonalité</span>
                                    <p class="signal-card-meta signal-card-meta--resonance">Préfigure un réglage personnel de l’algorithme : douceur, confrontation, écoute.</p>
                                </div>
                                <div class="signal-algora-row" role="group" aria-label="Tonalité algoRa">
                                    <button type="button" class="signal-algora-chip is-active" data-signal-algora-choice data-algora-mode="douceur">douceur</button>
                                    <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="confrontation">confrontation</button>
                                    <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="ecoute">écoute</button>
                                </div>
                            </div>
                            <label>
                                Ouvrir une liaison
                                <input
                                    type="text"
                                    name="u"
                                    list="signal-contact-options"
                                    placeholder="slug ou nom d’une terre en douceur"
                                    autocomplete="off"
                                    data-signal-open-input
                                    data-signal-recipient-input
                                    data-placeholder-default="slug ou nom d’une terre"
                                >
                                <span class="input-hint" data-signal-recipient-hint>Tape un nom de terre, ouvre un fil, puis laisse le message couler.</span>
                            </label>
                            <?php if (!empty($resonantContacts)): ?>
                                <div class="signal-suggestion-shell">
                                    <span class="signal-flow-hint">Trajectoires en phase</span>
                                    <div class="signal-suggestion-row" data-signal-recipient-choices>
                                        <?php foreach ($resonantContacts as $contact): ?>
                                            <?php
                                            $suggestionValue = (string) ($contact['counterpart_slug'] ?? '');
                                            $suggestionLabel = (string) ($contact['counterpart_username'] ?? $suggestionValue);
                                            $suggestionPhase = (string) ($contact['resonance_phase'] ?? 'drift');
                                            $suggestionSummary = (string) ($contact['resonance_summary'] ?? '');
                                            $suggestionLambda = (int) ($contact['counterpart_lambda_nm'] ?? 548);
                                            ?>
                                            <button
                                                type="button"
                                                class="signal-suggestion-chip signal-suggestion-chip--<?= h($suggestionPhase) ?>"
                                                data-signal-recipient-choice
                                                data-recipient-value="<?= h($suggestionValue) ?>"
                                                data-recipient-search="<?= h(strtolower($suggestionValue . ' ' . $suggestionLabel)) ?>"
                                                title="<?= h($suggestionSummary) ?>"
                                            >
                                                <strong><?= h($suggestionLabel) ?></strong>
                                                <span>λ <?= h((string) $suggestionLambda) ?> nm</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="action-row signal-flow-actions">
                                <button type="submit">Ouvrir le fil</button>
                                <span class="signal-flow-hint">Entrée ouvre la conversation</span>
                            </div>
                        </form>

                        <?php if (!empty($recentContacts)): ?>
                            <div class="signal-plasma-row" aria-label="Liaisons rapides">
                                <?php foreach ($recentContacts as $contact): ?>
                                    <a class="signal-plasma-pill signal-plasma-pill--<?= h((string) ($contact['resonance_phase'] ?? 'drift')) ?>" href="/signal?u=<?= rawurlencode((string) $contact['counterpart_slug']) ?>" title="<?= h((string) ($contact['resonance_summary'] ?? '')) ?>">
                                        <?= h((string) $contact['counterpart_username']) ?>
                                        <?php if ((int) ($contact['unread_count'] ?? 0) > 0): ?>
                                            <span><?= (int) $contact['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <label class="signal-filter-label">
                            Filtrer le plasma de contact
                            <input
                                type="search"
                                placeholder="Rechercher une terre ou un fil"
                                autocomplete="off"
                                data-signal-contact-filter
                            >
                        </label>
                    </div>

                    <datalist id="signal-contact-options">
                        <?php foreach ($contacts as $contact): ?>
                            <option
                                value="<?= h((string) $contact['counterpart_slug']) ?>"
                                data-slug="<?= h((string) $contact['counterpart_slug']) ?>"
                                data-username="<?= h((string) $contact['counterpart_username']) ?>"
                                data-phase="<?= h((string) ($contact['resonance_phase'] ?? 'drift')) ?>"
                                data-phase-label="<?= h((string) ($contact['resonance_label'] ?? 'déphasage léger')) ?>"
                                data-summary="<?= h((string) ($contact['resonance_summary'] ?? '')) ?>"
                                data-lambda="<?= h((string) ($contact['counterpart_lambda_nm'] ?? 548)) ?>"
                                data-gap="<?= h((string) ($contact['resonance_gap_nm'] ?? 0)) ?>"
                            ><?= h((string) $contact['counterpart_username']) ?></option>
                            <option
                                value="<?= h((string) $contact['counterpart_username']) ?>"
                                data-slug="<?= h((string) $contact['counterpart_slug']) ?>"
                                data-username="<?= h((string) $contact['counterpart_username']) ?>"
                                data-phase="<?= h((string) ($contact['resonance_phase'] ?? 'drift')) ?>"
                                data-phase-label="<?= h((string) ($contact['resonance_label'] ?? 'déphasage léger')) ?>"
                                data-summary="<?= h((string) ($contact['resonance_summary'] ?? '')) ?>"
                                data-lambda="<?= h((string) ($contact['counterpart_lambda_nm'] ?? 548)) ?>"
                                data-gap="<?= h((string) ($contact['resonance_gap_nm'] ?? 0)) ?>"
                            ><?= h((string) $contact['counterpart_slug']) ?></option>
                        <?php endforeach; ?>
                    </datalist>

                    <div class="echo-contacts" data-signal-contact-list>
                        <?php foreach ($contacts as $contact): ?>
                            <?php
                            $contactUsername = (string) ($contact['counterpart_username'] ?? '');
                            $contactSlug = (string) ($contact['counterpart_slug'] ?? '');
                            $lastSnippet = !empty($contact['last_subject'])
                                ? (string) $contact['last_subject']
                                : (!empty($contact['last_body']) ? signal_excerpt((string) $contact['last_body'], 90) : 'Aucune conversation ouverte.');
                            $contactHeat = (string) ($contact['activity_heat'] ?? 'dormant');
                            $contactHeatLabel = (string) ($contact['activity_label'] ?? 'latente');
                            $lastMessageLabel = human_created_label((string) ($contact['last_message_at'] ?? '')) ?? '';
                            $contactPhase = (string) ($contact['resonance_phase'] ?? 'drift');
                            $contactPhaseLabel = (string) ($contact['resonance_label'] ?? 'déphasage léger');
                            $contactLambda = (int) ($contact['counterpart_lambda_nm'] ?? 548);
                            $contactGap = (int) ($contact['resonance_gap_nm'] ?? 0);
                            $contactProgramLabel = (string) ($contact['counterpart_program_label'] ?? ($contact['counterpart_program'] ?? 'collectif'));
                            $contactNameFilter = function_exists('mb_strtolower')
                                ? mb_strtolower($contactUsername, 'UTF-8')
                                : strtolower($contactUsername);
                            $contactLastFilter = function_exists('mb_strtolower')
                                ? mb_strtolower($lastSnippet, 'UTF-8')
                                : strtolower($lastSnippet);
                            ?>
                            <a
                                href="/signal?u=<?= rawurlencode($contactSlug) ?>"
                                class="echo-contact signal-contact signal-contact--<?= h($contactHeat) ?> signal-contact--<?= h($contactPhase) ?> <?= $contactSlug === (string) ($targetLand['slug'] ?? '') ? 'is-active' : '' ?>"
                                data-signal-contact-item
                                data-signal-contact-name="<?= h($contactNameFilter) ?>"
                                data-signal-contact-slug="<?= h(strtolower($contactSlug)) ?>"
                                data-signal-contact-last="<?= h($contactLastFilter) ?>"
                            >
                                <div class="signal-card-topline">
                                    <strong><?= h($contactUsername) ?></strong>
                                    <?php if ((int) ($contact['unread_count'] ?? 0) > 0): ?>
                                        <span class="badge"><?= (int) $contact['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="signal-card-spectrum" aria-hidden="true">
                                    <span class="signal-spectrum-pill">λ <?= h((string) $contactLambda) ?> nm</span>
                                    <span class="signal-spectrum-pill signal-spectrum-pill--<?= h($contactPhase) ?>"><?= h($contactPhaseLabel) ?></span>
                                    <span class="signal-spectrum-pill">Δ <?= h((string) $contactGap) ?> nm</span>
                                </div>
                                <p class="signal-card-meta">@<?= h($contactSlug) ?> · <?= h($contactProgramLabel) ?> · <?= h($contactHeatLabel) ?><?= $lastMessageLabel !== '' ? ' · ' . h($lastMessageLabel) : '' ?></p>
                                <?php if (!empty($contact['last_subject'])): ?>
                                    <p class="signal-card-copy"><?= h((string) $contact['last_subject']) ?></p>
                                <?php elseif (!empty($contact['last_body'])): ?>
                                    <p class="signal-card-copy"><?= h(signal_excerpt((string) $contact['last_body'], 90)) ?></p>
                                <?php else: ?>
                                    <p class="signal-card-copy">Aucune conversation ouverte.</p>
                                <?php endif; ?>
                                <p class="signal-card-meta signal-card-meta--resonance"><?= h((string) ($contact['resonance_summary'] ?? '')) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <aside class="panel signal-col" aria-labelledby="signal-inbox-title">
                <div class="section-topline">
                    <div>
                        <h2 id="signal-inbox-title">Conversation</h2>
                        <p class="panel-copy">
                            <?php if ($targetLand): ?>
                                Liaison avec <?= h((string) $targetLand['username']) ?> via <?= h(signal_virtual_address($targetLand)) ?>.
                            <?php else: ?>
                                Choisis une terre pour ouvrir le fil.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($targetLand): ?>
                        <span class="badge"><?= h(signal_virtual_address($targetLand)) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!$targetLand): ?>
                    <div class="signal-empty-state">
                        <p class="panel-copy">Signal n’est plus un mur public. Ouvre une terre à gauche, ou amorce directement une première liaison ici.</p>

                        <form action="/signal" method="post" class="land-form signal-form signal-compose-form" data-signal-compose data-draft-scope="new">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="send_message">

                            <div class="signal-algora-shell" data-signal-algora>
                                <div>
                                    <span class="signal-flow-hint">algoRa · thème d’écriture</span>
                                    <p class="signal-card-meta signal-card-meta--resonance">Choisis comment l’algorithme t’oriente : adoucir, confronter, écouter.</p>
                                </div>
                                <div class="signal-algora-row" role="group" aria-label="Thème algoRa de composition">
                                    <button type="button" class="signal-algora-chip is-active" data-signal-algora-choice data-algora-mode="douceur">douceur</button>
                                    <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="confrontation">confrontation</button>
                                    <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="ecoute">écoute</button>
                                </div>
                            </div>

                            <label>
                                Destination
                                <input type="text" name="receiver_slug" list="signal-contact-options" placeholder="slug ou nom d’une terre en douceur" required data-signal-recipient-input data-placeholder-default="slug ou nom d’une terre">
                                <span class="input-hint" data-signal-recipient-hint>Choisis une terre proche, contrastée ou lointaine — Signal indiquera la phase.</span>
                            </label>

                            <?php if (!empty($resonantContacts)): ?>
                                <div class="signal-suggestion-shell">
                                    <span class="signal-flow-hint">Destinations suggérées</span>
                                    <div class="signal-suggestion-row" data-signal-recipient-choices>
                                        <?php foreach ($resonantContacts as $contact): ?>
                                            <?php
                                            $suggestionValue = (string) ($contact['counterpart_slug'] ?? '');
                                            $suggestionLabel = (string) ($contact['counterpart_username'] ?? $suggestionValue);
                                            $suggestionPhase = (string) ($contact['resonance_phase'] ?? 'drift');
                                            $suggestionSummary = (string) ($contact['resonance_summary'] ?? '');
                                            $suggestionLambda = (int) ($contact['counterpart_lambda_nm'] ?? 548);
                                            ?>
                                            <button
                                                type="button"
                                                class="signal-suggestion-chip signal-suggestion-chip--<?= h($suggestionPhase) ?>"
                                                data-signal-recipient-choice
                                                data-recipient-value="<?= h($suggestionValue) ?>"
                                                data-recipient-search="<?= h(strtolower($suggestionValue . ' ' . $suggestionLabel)) ?>"
                                                title="<?= h($suggestionSummary) ?>"
                                            >
                                                <strong><?= h($suggestionLabel) ?></strong>
                                                <span>λ <?= h((string) $suggestionLambda) ?> nm</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <label>
                                Sujet
                                <input type="text" name="subject" maxlength="180" placeholder="Premier contact en douceur (optionnel)" data-signal-draft-subject data-placeholder-default="Premier contact (optionnel)">
                            </label>

                            <label>
                                Première impulsion
                                <textarea name="body" rows="7" required placeholder="Écrire à une terre sans casser l’élan..." data-signal-draft-body data-placeholder-default="Écrire à une terre sans casser l’élan..."></textarea>
                            </label>

                            <div class="action-row signal-flow-actions">
                                <button type="submit">Ouvrir et transmettre</button>
                                <span class="signal-flow-hint" data-signal-draft-status>Signal créera le fil au premier envoi. ⌘/Ctrl + Entrée envoie.</span>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="echo-history" id="signal-history" data-message-live-history>
                        <?= $signalHistoryHtml ?>
                    </div>

                    <form action="/signal?u=<?= rawurlencode((string) $targetLand['slug']) ?>" method="post" class="land-form signal-form" data-signal-compose data-draft-scope="<?= h($currentDraftScope) ?>">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="receiver_slug" value="<?= h((string) $targetLand['slug']) ?>">

                        <div class="signal-algora-shell" data-signal-algora>
                            <div>
                                <span class="signal-flow-hint">algoRa · thème d’écriture</span>
                                <p class="signal-card-meta signal-card-meta--resonance">Ajuste la trajectoire du message selon la nature du lien en cours.</p>
                            </div>
                            <div class="signal-algora-row" role="group" aria-label="Thème algoRa du fil">
                                <button type="button" class="signal-algora-chip is-active" data-signal-algora-choice data-algora-mode="douceur">douceur</button>
                                <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="confrontation">confrontation</button>
                                <button type="button" class="signal-algora-chip" data-signal-algora-choice data-algora-mode="ecoute">écoute</button>
                            </div>
                        </div>

                        <label>
                            Sujet
                            <input type="text" name="subject" maxlength="180" placeholder="Objet du message en douceur (optionnel)" data-signal-draft-subject data-placeholder-default="Objet du message (optionnel)">
                        </label>

                        <label>
                            Message
                            <textarea name="body" rows="7" required placeholder="Écrire à cette terre en cherchant l’accord..." data-signal-draft-body data-placeholder-default="Écrire à cette terre..."></textarea>
                        </label>

                        <div class="action-row">
                            <button type="submit">Transmettre</button>
                            <span class="signal-flow-hint" data-signal-draft-status>Brouillon gardé localement. ⌘/Ctrl + Entrée envoie.</span>
                            <a class="ghost-link" href="/echo?u=<?= rawurlencode((string) $targetLand['username']) ?>">Basculer vers Écho</a>
                        </div>
                    </form>
                <?php endif; ?>
            </aside>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
