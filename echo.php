<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/signal_mail.php';

$host = request_host();

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$csrfToken = csrf_token();
$guideHref = '/0wlslw0';
$echoGuide = guide_path('echo');

$land = current_authenticated_land();
if (!$land) {
    header('Location: /?error=auth');
    exit;
}

$ambientProfile = land_visual_profile($land);
$myUsername = (string) $land['username'];
$targetIdentifier = trim((string) ($_GET['u'] ?? ''));
$message = '';
$messageType = 'info';
$signalSchemaStatus = signal_mail_schema_status();
$messagingReady = (bool) ($signalSchemaStatus['ready'] ?? false);
$signalSchemaHint = signal_mail_schema_status_hint($signalSchemaStatus);
$targetLand = $targetIdentifier !== '' ? signal_find_land_by_identifier($targetIdentifier) : null;
$targetUsername = trim((string) ($targetLand['username'] ?? ''));
$targetSlug = trim((string) ($targetLand['slug'] ?? ''));

// Traitement de l'envoi d'un écho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postedToken)) {
        $message = "La résonance s'est dissipée. Réessaie.";
        $messageType = 'warning';
    } elseif (!$messagingReady) {
        $message = "Écho attend encore sa base de messagerie. " . $signalSchemaHint;
        $messageType = 'warning';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        $receiver = trim((string) ($_POST['receiver_slug'] ?? $_POST['receiver_username'] ?? ''));
        
        try {
            if ($body !== '' && $receiver !== '') {
                signal_send_message($land, $receiver, '', $body);
                $receiverLand = signal_find_land_by_identifier($receiver);
                $receiverUsername = trim((string) ($receiverLand['username'] ?? $receiver));
                header("Location: /echo?u=" . urlencode($receiverUsername));
                exit;
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $message = $exception->getMessage();
            $messageType = 'warning';
        }
    }
}

// Récupération des contacts (Terres existantes, excluant soi-même)
$contacts = [];
if ($messagingReady) {
    foreach (signal_contact_candidates($land) as $contact) {
        $contacts[] = [
            'username' => trim((string) ($contact['counterpart_username'] ?? '')),
            'slug' => trim((string) ($contact['counterpart_slug'] ?? '')),
            'unread_count' => (int) ($contact['unread_count'] ?? 0),
        ];
    }
}

// Récupération de l'historique si une Terre est ciblée
$history = [];
if ($targetSlug !== '' && $messagingReady) {
    $history = signal_load_conversation($land, $targetSlug);
}

$echoHistoryHtml = signal_render_conversation_html($history, $land, false, 'Le silence règne entre vos deux terres.');
$echoHistoryHash = sha1($echoHistoryHtml);
$echoContactsHtml = signal_render_echo_contacts_html($contacts, $targetUsername);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Écho — résonance directe dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Écho — <?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?= render_pwa_head_tags('main') ?>
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience signal-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'echo') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">
    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>écho</strong> <span>liaison inter-terres</span></p>
        <h1 class="land-title signal-title">
            <strong>Résonance ciblée.</strong>
            <span>Point à point</span>
        </h1>
        <p class="lead">Le signal ne se disperse pas. Il frappe directement le rivage de l'autre.</p>

        <div class="land-meta">
            <a class="meta-pill meta-pill-link" href="/">retour au noyau</a>
            <span class="meta-pill">terre liée : <?= h((string) $land['slug']) ?></span>
            <a class="meta-pill meta-pill-link" href="/signal">signal / boîte</a>
            <a class="meta-pill meta-pill-link" href="<?= h($guideHref) ?>">0wlslw0</a>
        </div>
    </header>

    <section class="panel reveal meaning-panel" aria-labelledby="echo-meaning-title">
        <div class="section-topline">
            <div>
                <h2 id="echo-meaning-title">Pourquoi cette porte existe</h2>
                <p class="panel-copy"><?= h((string) ($echoGuide['copy'] ?? 'Relier deux terres sans passer par le bruit public.')) ?></p>
            </div>
            <a class="ghost-link" href="<?= h($guideHref) ?>">0wlslw0 : me guider</a>
        </div>
    </section>

    <section
        class="echo-grid reveal"
        data-message-live
        data-live-view="echo"
        data-live-api="/signal_live.php"
        data-live-target="<?= h($targetSlug) ?>"
        data-live-interval="2500"
        data-live-hash="<?= h($echoHistoryHash) ?>"
        data-live-message-count="<?= h((string) count($history)) ?>"
    >
        <aside class="echo-contacts" data-echo-contacts-list>
            <?= $echoContactsHtml ?>
        </aside>

        <div class="echo-conversation panel">
            <?php if (!$messagingReady): ?>
                <p class="panel-copy">Écho reste en veille tant que la base SQL unifiée de Signal n’est pas active.</p>
                <p class="panel-copy"><?= h($signalSchemaHint) ?></p>
            <?php elseif ($targetSlug === '' || !$targetLand): ?>
                <div class="echo-empty-state">
                    <p class="panel-copy">Choisis une terre à gauche pour ouvrir la liaison. Si tu préfères commencer par l’adresse et le fil, passe par Signal.</p>
                    <div class="action-row">
                        <a class="pill-link" href="/signal">Ouvrir Signal</a>
                        <a class="ghost-link" href="/str3m">Retour au public</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="section-topline">
                    <div>
                        <h2>Liaison avec <?= h($targetUsername) ?></h2>
                        <p class="panel-copy">Direct, sans détour, sur la même trame que Signal.</p>
                    </div>
                    <span class="badge"><?= h(signal_virtual_address($targetLand)) ?></span>
                    <span class="meta-pill" data-message-live-indicator role="status" aria-live="polite" aria-atomic="true">direct · veille</span>
                </div>
                
                <div class="echo-history" data-message-live-history>
                    <?= $echoHistoryHtml ?>
                </div>

                <form action="/echo?u=<?= rawurlencode($targetUsername) ?>" method="post" class="land-form echo-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="receiver_username" value="<?= h($targetUsername) ?>">
                    <input type="hidden" name="receiver_slug" value="<?= h($targetSlug) ?>">
                    
                    <label>
                        Transmission
                        <textarea name="body" required placeholder="Écrire à cette terre, simplement..."></textarea>
                    </label>
                    
                    <div class="action-row">
                        <button type="submit">Transmettre</button>
                        <a class="ghost-link" href="/signal?u=<?= rawurlencode($targetSlug) ?>">Ouvrir la même liaison dans Signal</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

</main>
</body>
</html>
