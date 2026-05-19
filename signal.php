<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signal_mail.php';
require_once __DIR__ . '/lib/signals.php';

$host = request_host();
$surfaceVariant = current_surface_variant($host);
$isSpatialHeadsetMode = $surfaceVariant === 'io' && spatial_preview_mode($host) === 'headset';

$brandDomain = current_brand_domain($host);
$guideHref = o_route_href('/0wlslw0', [], $host);
$signalHref = o_route_href('/signal', [], $host);
$str3mHref = o_route_href('/str3m', [], $host);
$echoHref = o_route_href('/echo', [], $host);
$joinHref = o_route_href('/rejoindre', [], $host);
$homeHref = o_route_href('/', [], $host);
$signalLiveHref = o_route_href('/signal_live.php', [], $host);
$signalThreadHref = static fn (string $slug): string => o_route_href('/signal', ['u' => normalize_username($slug)], $host);
$echoThreadHref = static fn (string $username): string => o_route_href('/echo', ['u' => trim($username)], $host);
$signalGuide = guide_path('signal');
$csrfToken = csrf_token();
$land = current_authenticated_land();
$signalSchemaStatus = signal_mail_schema_status();

$verifyToken = trim((string) ($_GET['verify'] ?? ''));
$verifyLand = trim((string) ($_GET['land'] ?? ''));
if ($verifyToken !== '' && $verifyLand !== '') {
    $verified = signal_mail_tables_ready() && signal_verify_identity_token($verifyLand, $verifyToken);
    header('Location: ' . o_route_href('/signal', [$verified ? 'status' : 'error' => $verified ? 'identity-verified' : 'identity-invalid'], $host), true, 303);
    exit;
}

$tablesReady = (bool) ($signalSchemaStatus['ready'] ?? false);
$ambientProfile = $land ? land_visual_profile($land) : land_collective_profile('dense');
$message = '';
$messageType = 'info';
$signalSchemaHint = signal_mail_schema_status_hint($signalSchemaStatus);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$land) {
        header('Location: ' . o_route_href('/signal', ['error' => 'auth'], $host), true, 303);
        exit;
    }

    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        header('Location: ' . o_route_href('/signal', ['error' => 'csrf'], $host), true, 303);
        exit;
    }

    if (!$tablesReady) {
        header('Location: ' . o_route_href('/signal', ['error' => 'messaging'], $host), true, 303);
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? 'send_message'));

    try {
        if ($action === 'request_identity') {
            signal_request_identity_verification($land, (string) ($_POST['notification_email'] ?? ''));
            header('Location: ' . o_route_href('/signal', ['status' => 'identity-sent'], $host), true, 303);
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
            header('Location: ' . o_route_href('/signal', ['u' => normalize_username($receiverSlug), 'status' => 'message-sent'], $host), true, 303);
            exit;
        }

        header('Location: ' . o_route_href('/signal', ['error' => 'validation'], $host), true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        header('Location: ' . o_route_href('/signal', ['error' => 'validation', 'note' => $exception->getMessage()], $host), true, 303);
        exit;
    } catch (RuntimeException $exception) {
        header('Location: ' . o_route_href('/signal', ['error' => 'delivery', 'note' => $exception->getMessage()], $host), true, 303);
        exit;
    } catch (Throwable $exception) {
        error_log('[sowwwl][signal] ' . $exception->getMessage());
        header('Location: ' . o_route_href('/signal', ['error' => 'messaging'], $host), true, 303);
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
        'session' => 'La session liée à la terre a expiré ou a été interrompue. Rouvre ta terre puis reprends le fil.',
        'csrf' => 'Le jeton de session a expiré. Recharge la page et réessaie.',
        'messaging' => 'La messagerie Signal n’est pas encore prête côté base. ' . $signalSchemaHint,
        'identity-invalid' => 'Le lien de validation est invalide ou expiré.',
        'delivery' => 'Le message ou la validation n’a pas pu être distribué pour le moment.',
        'validation' => 'Les informations transmises sont incomplètes ou invalides.',
        default => 'Signal ne peut pas ouvrir la conversation pour le moment.',
    };

    if ($note !== '') {
        $message .= ' ' . $note;
    }
}

$mailbox = [];
$contacts = [];
$recentContacts = [];
$resonantContacts = [];
$targetSlug = trim((string) ($_GET['u'] ?? ''));
$targetLand = null;
$conversation = [];
$unreadTotal = 0;

if ($land && $tablesReady) {
    try {
        $mailbox = signal_mailbox_for_land($land);
        $contacts = signal_contact_candidates($land);
        $recentContacts = array_slice($contacts, 0, 6);
        $resonantContacts = array_slice($contacts, 0, 5);
        $unreadTotal = signal_unread_total($land);
        if ($targetSlug !== '') {
            $targetLand = signal_find_land_by_slug($targetSlug);
            if ($targetLand) {
                $conversation = signal_load_conversation($land, (string) $targetLand['slug']);
            }
        }
    } catch (Throwable $exception) {
        error_log('[sowwwl][signal.load] ' . $exception->getMessage());
        $tablesReady = false;
        $signalSchemaHint = 'La couche SQL de Signal a répondu avec une erreur au chargement. Vérifie la base et rejoue le contrôle de schéma.';
        if ($message === '') {
            $messageType = 'warning';
            $message = 'Signal n’a pas pu relire sa base pour le moment. ' . $signalSchemaHint;
        }
    }
}

$identityStatus = trim((string) ($mailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
$notificationEmail = trim((string) ($mailbox['notification_email'] ?? ''));
$identityHint = $mailbox ? signal_identity_status_hint($mailbox) : '';
$identityDeliveryStatus = function_exists('signal_identity_delivery_status')
    ? signal_identity_delivery_status()
    : ['mode' => 'mail', 'ready' => false, 'issues' => ['missing-helper'], 'details' => ['Le statut de livraison n’est pas disponible.']];
$identityDeliveryHint = function_exists('signal_identity_delivery_status_hint')
    ? signal_identity_delivery_status_hint($identityDeliveryStatus)
    : 'Le statut de livraison n’est pas disponible.';
$identityResendWait = $mailbox ? signal_identity_seconds_until_resend($mailbox) : 0;
$identitySubmitLabel = $identityStatus === SIGNAL_IDENTITY_VERIFIED ? 'Changer l’adresse validée' : ($identityStatus === SIGNAL_IDENTITY_PENDING ? 'Renvoyer le lien' : 'Valider cette identité');
$identitySubmitDisabled = $identityResendWait > 0;
$virtualAddress = $land ? signal_virtual_address($land) : '';
$currentDraftScope = $targetLand
    ? 'thread:' . (string) ($targetLand['slug'] ?? '')
    : 'new';
$signalLiveTarget = $targetLand ? (string) ($targetLand['slug'] ?? '') : '';
$signalHistoryHtml = signal_render_conversation_html($conversation, $land ?? [], true, 'Aucune trace encore entre vos deux boîtes.');
$signalHistoryHash = sha1($signalHistoryHtml);
$activeContact = null;

if ($targetLand) {
    foreach ($contacts as $contact) {
        if ((string) ($contact['counterpart_slug'] ?? '') === (string) ($targetLand['slug'] ?? '')) {
            $activeContact = $contact;
            break;
        }
    }
}

$activeContactPhase = (string) ($activeContact['resonance_phase'] ?? 'drift');
$activeContactPhaseLabel = (string) ($activeContact['resonance_label'] ?? 'déphasage léger');
$activeContactSummary = (string) ($activeContact['resonance_summary'] ?? 'Le fil est ouvert. Tu peux écrire sans perdre le contexte.');
$activeContactLambda = (int) ($activeContact['counterpart_lambda_nm'] ?? 548);
$activeContactGap = (int) ($activeContact['resonance_gap_nm'] ?? 0);
$activeContactProgramLabel = (string) ($activeContact['counterpart_program_label'] ?? ($activeContact['counterpart_program'] ?? 'collectif'));
$activeContactLastLabel = human_created_label((string) ($activeContact['last_message_at'] ?? '')) ?? '';
$activeConversationCount = count($conversation);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Signal — boîte de réception située dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Signal — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets(pwa_default_app_id($host), $host) ?>
</head>
<body class="experience signal-view<?= $surfaceVariant === 'io' ? ' io-surface-view' : '' ?><?= $isSpatialHeadsetMode ? ' io-headset-mode' : '' ?>">
<?= render_skip_link() ?>
<?= render_nucleus_banner('signal') ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'signal') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>signal</strong> <span>messagerie située</span></p>
        <h1 class="land-title signal-title">
            <strong>Boîte aux lettres de terre.</strong>
            <span><?= $land ? h($virtualAddress) : 'liaison réservée aux terres' ?></span>
        </h1>
        <p class="lead">Ici, on écrit à une terre et on garde le fil.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="<?= h($str3mHref) ?>">courant public</a>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
            <?php if ($land): ?>
                <span class="meta-pill">terre liée : <?= h((string) $land['slug']) ?></span>
                <?php if ($tablesReady): ?>
                    <span class="meta-pill"><?= h(signal_identity_status_label($identityStatus)) ?></span>
                    <span class="meta-pill" data-signal-unread-label><?= $unreadTotal ?> message<?= $unreadTotal > 1 ? 's' : '' ?> non lu<?= $unreadTotal > 1 ? 's' : '' ?></span>
                    <span class="meta-pill" data-message-live-indicator role="status" aria-live="polite" aria-atomic="true"><?= $signalLiveTarget !== '' ? 'direct · veille' : 'direct · en attente' ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="meta-pill">lecture de principe</span>
            <?php endif; ?>
        </div>
    </header>

    <?= render_spatial_context_bar('signal', $host) ?>

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
                    <p class="panel-copy">La boîte existe seulement avec une terre. Sans terre, Str3m reste public.</p>
                </div>
            </div>
            <div class="action-row">
                <a class="pill-link" href="<?= h($joinHref) ?>">Poser une terre</a>
                <a class="ghost-link" href="<?= h($str3mHref) ?>">Aller vers Str3m</a>
            </div>
        </section>
    <?php elseif (!$tablesReady): ?>
        <section class="panel reveal">
            <div class="section-topline">
                <div>
                    <h2>Messagerie non initialisée</h2>
                    <p class="panel-copy"><?= h($signalSchemaHint) ?></p>
                    <p class="panel-copy">Si la base vient de revenir, rejoue au besoin la migration Signal du projet (<code>../migrations/2026_05_02_signal_mail.sql</code> depuis le dossier <code>o/</code>) puis redémarre l’app.</p>
                </div>
                <span class="badge"><?= ($signalSchemaStatus['database_available'] ?? false) ? 'schéma à compléter' : 'sql indisponible' ?></span>
            </div>
        </section>
    <?php else: ?>
        <section
            class="signal-grid reveal"
            data-message-live
            data-live-view="signal"
            data-live-api="<?= h($signalLiveHref) ?>"
            data-live-target="<?= h($signalLiveTarget) ?>"
            data-live-interval="2500"
            data-live-hash="<?= h($signalHistoryHash) ?>"
            data-live-message-count="<?= h((string) count($conversation)) ?>"
        >
            <section class="panel signal-col" aria-labelledby="signal-mailbox-title">
                <div class="section-topline">
                    <div>
                        <h2 id="signal-mailbox-title">Boîte · choisir une terre</h2>
                        <p class="panel-copy">Reprends un fil existant ou prépare la destination avant d’écrire.</p>
                    </div>
                    <span class="badge"><?= h((string) count($contacts)) ?> terre<?= count($contacts) > 1 ? 's' : '' ?></span>
                </div>

                <div class="signal-list-block">
                    <div class="signal-flow-shell">
                        <form action="<?= h($signalHref) ?>" method="get" class="land-form signal-form signal-open-form" data-signal-open-form>
                            <label>
                                Trouver une terre
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
                                <span class="input-hint" data-signal-recipient-hint>Tape un nom ou un slug. Signal reconnaît la terre avant l’ouverture.</span>
                            </label>
                            <div class="action-row signal-flow-actions">
                                <button type="submit">Ouvrir le fil</button>
                                <span class="signal-flow-hint">Entrée ouvre la conversation</span>
                            </div>

                            <div
                                class="signal-preview-card is-empty"
                                data-signal-recipient-preview
                                data-preview-empty-title="Aucune terre retenue"
                                data-preview-empty-copy="Choisis une terre pour voir sa résonance, ouvrir le fil ou basculer en direct."
                                data-preview-signal-base="<?= h($signalHref) ?>"
                                data-preview-echo-base="<?= h($echoHref) ?>"
                            >
                                <p class="signal-preview-kicker" data-signal-preview-kicker>Aperçu de liaison</p>
                                <strong data-signal-preview-title>Aucune terre retenue</strong>
                                <p class="signal-card-meta" data-signal-preview-copy>Choisis une terre pour voir sa résonance, ouvrir le fil ou basculer en direct.</p>
                                <div class="signal-card-spectrum" data-signal-preview-spectrum hidden>
                                    <span class="signal-spectrum-pill" data-signal-preview-lambda></span>
                                    <span class="signal-spectrum-pill" data-signal-preview-phase></span>
                                    <span class="signal-spectrum-pill" data-signal-preview-gap></span>
                                </div>
                                <div class="signal-preview-actions" data-signal-preview-actions hidden>
                                    <a class="ghost-link" href="<?= h($signalHref) ?>" data-signal-preview-open>Ouvrir le fil</a>
                                    <a class="ghost-link" href="<?= h($echoHref) ?>" data-signal-preview-echo>Passer en direct</a>
                                </div>
                            </div>

                            <details class="signal-advanced">
                                <summary class="signal-secondary-summary">Affiner l’ouverture</summary>
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

                                <?php if (!empty($resonantContacts)): ?>
                                    <div class="signal-suggestion-shell">
                                        <span class="signal-flow-hint">Trajectoires suggérées</span>
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
                            </details>
                        </form>

                        <?php if (!empty($recentContacts)): ?>
                            <div class="signal-inline-note">
                                <strong>Raccourcis de boîte</strong>
                                <span>Reprendre vite un fil existant avant de parcourir tout le carnet.</span>
                            </div>
                            <div class="signal-plasma-row" aria-label="Liaisons rapides">
                                <?php foreach ($recentContacts as $contact): ?>
                                    <a class="signal-plasma-pill signal-plasma-pill--<?= h((string) ($contact['resonance_phase'] ?? 'drift')) ?>" href="<?= h($signalThreadHref((string) $contact['counterpart_slug'])) ?>" title="<?= h((string) ($contact['resonance_summary'] ?? '')) ?>">
                                        <?= h((string) $contact['counterpart_username']) ?>
                                        <?php if ((int) ($contact['unread_count'] ?? 0) > 0): ?>
                                            <span><?= (int) $contact['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

                    <details class="signal-secondary-block signal-directory-block"<?= $targetLand ? '' : ' open' ?>>
                        <summary class="signal-secondary-summary">Ouvrir tout le carnet de terres</summary>
                        <div class="signal-directory-shell">
                            <label class="signal-filter-label">
                                Filtrer le carnet
                                <input
                                    type="search"
                                    placeholder="Rechercher une terre ou un fil"
                                    autocomplete="off"
                                    data-signal-contact-filter
                                >
                            </label>
                            <p class="panel-copy signal-directory-copy">Le carnet complet reste ici si tu préfères parcourir avant d’ouvrir.</p>

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
                                        href="<?= h($signalThreadHref($contactSlug)) ?>"
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
                    </details>
                </div>

                <div class="signal-identity-block">
                    <details class="signal-secondary-block">
                        <summary class="signal-secondary-summary">Adresse réelle et notifications</summary>
                        <div class="signal-identity-status signal-identity-status--<?= h($identityStatus) ?>">
                            <span class="summary-label"><?= h(signal_identity_status_label($identityStatus)) ?></span>
                            <strong><?= h($identityHint !== '' ? $identityHint : 'Validation non initialisée.') ?></strong>
                        </div>
                        <p class="panel-copy">Mode de livraison : <strong><?= h((string) ($identityDeliveryStatus['mode'] ?? 'mail')) ?></strong>. <?= h($identityDeliveryHint) ?></p>
                        <p class="panel-copy">Adresse interne : <?= h($virtualAddress) ?>.</p>

                        <form action="<?= h($signalHref) ?>" method="post" class="land-form signal-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="request_identity">

                            <label>
                                Email de notification / validation
                                <input type="email" name="notification_email" value="<?= h($notificationEmail) ?>" placeholder="vous@domaine.tld" required>
                                <span class="input-hint">Un lien de validation y sera envoyé. Tant qu’il n’est pas confirmé, la terre reste en attente.</span>
                            </label>

                            <button type="submit"<?= $identitySubmitDisabled ? ' disabled' : '' ?>><?= h($identitySubmitLabel) ?></button>
                            <?php if ($identityResendWait > 0): ?>
                                <p class="input-hint">Renvoyable dans <?= h((string) $identityResendWait) ?> secondes.</p>
                            <?php endif; ?>
                        </form>
                    </details>
                </div>
            </section>

            <aside class="panel signal-col" aria-labelledby="signal-inbox-title">
                    <div class="section-topline">
                        <div>
                            <h2 id="signal-inbox-title"><?= $targetLand ? 'Fil de boîte' : 'Écrire dans Signal' ?></h2>
                            <p class="panel-copy">
                                <?php if ($targetLand): ?>
                                    Fil ouvert avec <?= h((string) $targetLand['username']) ?> via <?= h(signal_virtual_address($targetLand)) ?>. Reste ici pour garder le contexte, ou passe en Écho pour répondre en direct.
                                <?php else: ?>
                                    Choisis une terre ici ou dans le carnet, puis écris sans quitter la page. Signal ouvrira le fil au premier envoi.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($targetLand): ?>
                            <span class="badge"><?= h(signal_virtual_address($targetLand)) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!$targetLand): ?>
                    <div class="signal-empty-state">
                        <div class="signal-compose-head">
                            <p class="panel-copy">Une destination claire, une impulsion, puis le fil se forme.</p>
                            <p class="signal-compare-note" data-signal-ra-compose-note>Si la terre est déjà reconnue, tu peux aussi ouvrir le fil avant d’envoyer. Quand la destination est évidente, <a href="<?= h($echoHref) ?>">Écho va droit au direct</a>.</p>
                        </div>

                        <form action="<?= h($signalHref) ?>" method="post" class="land-form signal-form signal-compose-form" data-signal-compose data-draft-scope="new">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="send_message">

                            <label>
                                Destination
                                <input type="text" name="receiver_slug" list="signal-contact-options" placeholder="slug ou nom d’une terre en douceur" required data-signal-recipient-input data-placeholder-default="slug ou nom d’une terre">
                                <span class="input-hint" data-signal-recipient-hint>Choisis une terre puis appuie sur Entrée pour passer directement au message.</span>
                            </label>

                            <div
                                class="signal-preview-card is-empty"
                                data-signal-recipient-preview
                                data-preview-empty-title="Destination à préciser"
                                data-preview-empty-copy="Dès qu’une terre est reconnue, son accord apparaît ici avec les chemins possibles."
                                data-preview-signal-base="<?= h($signalHref) ?>"
                                data-preview-echo-base="<?= h($echoHref) ?>"
                            >
                                <p class="signal-preview-kicker" data-signal-preview-kicker>Terre reconnue</p>
                                <strong data-signal-preview-title>Destination à préciser</strong>
                                <p class="signal-card-meta" data-signal-preview-copy>Dès qu’une terre est reconnue, son accord apparaît ici avec les chemins possibles.</p>
                                <div class="signal-card-spectrum" data-signal-preview-spectrum hidden>
                                    <span class="signal-spectrum-pill" data-signal-preview-lambda></span>
                                    <span class="signal-spectrum-pill" data-signal-preview-phase></span>
                                    <span class="signal-spectrum-pill" data-signal-preview-gap></span>
                                </div>
                                <div class="signal-preview-actions" data-signal-preview-actions hidden>
                                    <a class="ghost-link" href="<?= h($signalHref) ?>" data-signal-preview-open>Ouvrir le fil</a>
                                    <a class="ghost-link" href="<?= h($echoHref) ?>" data-signal-preview-echo>Passer en direct</a>
                                </div>
                            </div>

                            <label>
                                Première impulsion
                                <textarea name="body" rows="7" required placeholder="Écrire à une terre sans casser l’élan..." data-signal-draft-body data-placeholder-default="Écrire à une terre sans casser l’élan..."></textarea>
                            </label>

                            <details class="signal-advanced">
                                <summary class="signal-secondary-summary">Affiner ce premier message</summary>
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
                            </details>

                        <div class="action-row signal-flow-actions">
                            <button type="submit">Ouvrir et transmettre</button>
                            <span class="signal-flow-hint" data-signal-draft-status role="status" aria-live="polite" aria-atomic="true">Signal créera le fil au premier envoi. ⌘/Ctrl + Entrée envoie.</span>
                            <a class="ghost-link" href="<?= h($echoHref) ?>">Aller vers Écho</a>
                        </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="signal-thread-context">
                        <div class="signal-thread-head">
                            <div>
                                <p class="signal-preview-kicker">Fil actif</p>
                                <h3><?= h((string) ($targetLand['username'] ?? $targetLand['slug'])) ?></h3>
                                <p class="signal-card-meta">@<?= h((string) ($targetLand['slug'] ?? '')) ?> · <?= h($activeContactProgramLabel) ?></p>
                            </div>
                            <div class="signal-card-spectrum" aria-hidden="true">
                                <span class="signal-spectrum-pill">λ <?= h((string) $activeContactLambda) ?> nm</span>
                                <span class="signal-spectrum-pill signal-spectrum-pill--<?= h($activeContactPhase) ?>"><?= h($activeContactPhaseLabel) ?></span>
                                <span class="signal-spectrum-pill">Δ <?= h((string) $activeContactGap) ?> nm</span>
                            </div>
                        </div>
                        <p class="signal-card-copy"><?= h($activeContactSummary) ?></p>
                        <div class="signal-thread-stats" aria-label="Contexte du fil">
                            <span class="signal-thread-stat"><?= h((string) $activeConversationCount) ?> message<?= $activeConversationCount > 1 ? 's' : '' ?> dans ce fil</span>
                            <?php if ($activeContactLastLabel !== ''): ?>
                                <span class="signal-thread-stat">dernier passage <?= h($activeContactLastLabel) ?></span>
                            <?php endif; ?>
                            <span class="signal-thread-stat"><?= h(signal_virtual_address($targetLand)) ?></span>
                        </div>
                        <div class="signal-thread-actions">
                            <a class="ghost-link" href="<?= h($signalHref) ?>">Changer de terre</a>
                            <a class="ghost-link" href="<?= h($echoThreadHref((string) ($targetLand['username'] ?? ''))) ?>">Passer en direct dans Écho</a>
                        </div>
                    </div>

                    <div class="signal-history-shell">
                        <div class="signal-history-toolbar" aria-label="Navigation du fil">
                            <span class="signal-flow-hint">Navigation du fil</span>
                            <div class="signal-history-toolbar-actions">
                                <button type="button" class="signal-history-jump" data-signal-history-jump="first">Premier message</button>
                                <button type="button" class="signal-history-jump" data-signal-history-jump="latest">Dernier message</button>
                                <button type="button" class="signal-history-jump" data-signal-history-jump="composer">Répondre</button>
                            </div>
                        </div>

                        <div class="echo-history" id="signal-history" data-message-live-history>
                            <?= $signalHistoryHtml ?>
                        </div>
                    </div>

                    <form action="<?= h($signalThreadHref((string) $targetLand['slug'])) ?>" method="post" class="land-form signal-form" data-signal-compose data-draft-scope="<?= h($currentDraftScope) ?>">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="send_message">
                        <input type="hidden" name="receiver_slug" value="<?= h((string) $targetLand['slug']) ?>">

                        <label>
                            Message
                            <textarea name="body" rows="7" required placeholder="Écrire à cette terre en cherchant l’accord..." data-signal-draft-body data-placeholder-default="Écrire à cette terre..." data-signal-history-composer></textarea>
                        </label>

                        <details class="signal-advanced">
                            <summary class="signal-secondary-summary">Affiner ce message</summary>
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
                        </details>

                        <div class="action-row">
                            <button type="submit">Transmettre</button>
                            <span class="signal-flow-hint" data-signal-draft-status role="status" aria-live="polite" aria-atomic="true">Brouillon gardé localement. ⌘/Ctrl + Entrée envoie.</span>
                            <a class="ghost-link" href="<?= h($echoThreadHref((string) $targetLand['username'])) ?>">Passer en direct dans Écho</a>
                        </div>
                    </form>
                <?php endif; ?>
            </aside>
        </section>
    <?php endif; ?>

    <section class="panel reveal signal-mode-panel" aria-labelledby="signal-mode-title">
        <div class="section-topline">
            <div>
                <h2 id="signal-mode-title">Signal / Écho</h2>
                <p class="panel-copy" data-signal-ra-note>Signal garde le fil. Écho reprend la même liaison en direct.</p>
            </div>
            <a class="ghost-link" href="<?= h($echoHref) ?>">Ouvrir Écho</a>
        </div>
        <div class="signal-mode-grid">
            <article class="signal-mode-card signal-mode-card--primary" data-signal-ra-card="signal">
                <p class="signal-mode-kicker">Signal · boîte</p>
                <h3>Ouvrir, relire, garder le fil</h3>
                <p class="panel-copy">Choisir une terre, relire, garder le fil.</p>
            </article>
            <article class="signal-mode-card" data-signal-ra-card="echo">
                <p class="signal-mode-kicker">Écho · direct</p>
                <h3>Toucher une terre sans détour</h3>
                <p class="panel-copy">Quand la destination est claire, passe en direct.</p>
            </article>
        </div>
    </section>
</main>
</body>
</html>
