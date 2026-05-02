<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$host = request_host();
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/echo');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

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
$messagingReady = signal_mail_tables_ready();
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
        $message = "Écho attend encore sa base de messagerie. Déploie la couche SQL pour ouvrir cette liaison.";
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Écho — résonance directe sur <?= h($brandDomain) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>Écho — <?= h($brandDomain) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
</head>
<body class="experience signal-view">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'dense', 'echo') ?>

<main class="layout page-shell">
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

    <section class="echo-grid reveal">
        <aside class="echo-contacts">
            <div class="section-topline">
                <h2>Archipel connu</h2>
            </div>
            <?php foreach ($contacts as $c): ?>
                <a href="/echo?u=<?= rawurlencode((string) $c['username']) ?>" class="echo-contact <?= $c['username'] === $targetUsername ? 'is-active' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong><?= h((string) $c['username']) ?></strong>
                        <?php if (!empty($c['unread_count'])): ?>
                            <span style="background: rgba(var(--land-secondary-rgb) / 0.8); color: var(--panel-rgb); font-size: 0.75rem; font-weight: 600; padding: 0.1rem 0.4rem; border-radius: 99px;"><?= $c['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="echo-conversation panel">
            <?php if (!$messagingReady): ?>
                <p class="panel-copy">Écho reste en veille tant que la base SQL unifiée de Signal n’est pas active.</p>
            <?php elseif ($targetSlug === '' || !$targetLand): ?>
                <p class="panel-copy">Sélectionne une Terre pour établir la liaison.</p>
            <?php else: ?>
                <div class="section-topline">
                    <h2>Liaison avec <?= h($targetUsername) ?></h2>
                    <span class="badge"><?= h(signal_virtual_address($targetLand)) ?></span>
                </div>
                
                <div class="echo-history">
                    <?php if (empty($history)): ?>
                        <p class="panel-copy">Le silence règne entre vos deux terres.</p>
                    <?php else: ?>
                        <?php foreach ($history as $msg): ?>
                            <?php $isMe = (string) ($msg['sender_land_slug'] ?? '') === (string) $land['slug']; ?>
                            <div class="echo-msg <?= $isMe ? 'echo-msg--sent' : 'echo-msg--received' ?>">
                                <span class="echo-msg-meta"><?= h((string) ($msg['sender_land_username'] ?? 'terre')) ?> · <?= h(human_created_label((string) ($msg['created_at'] ?? '')) ?? 'maintenant') ?></span>
                                <?= nl2br(h((string) ($msg['body'] ?? ''))) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form action="/echo?u=<?= rawurlencode($targetUsername) ?>" method="post" class="land-form echo-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="receiver_username" value="<?= h($targetUsername) ?>">
                    <input type="hidden" name="receiver_slug" value="<?= h($targetSlug) ?>">
                    
                    <label>
                        Transmission
                        <textarea name="body" required placeholder="Le signal à envoyer..."></textarea>
                    </label>
                    
                    <div class="action-row">
                        <button type="submit">Émettre l'écho</button>
                        <a class="ghost-link" href="/signal?u=<?= rawurlencode($targetSlug) ?>">Ouvrir la même liaison dans Signal</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const historyContainer = document.querySelector('.echo-history');
        if (historyContainer) {
            // Fait défiler la vue jusqu'en bas pour voir le dernier message
            historyContainer.scrollTop = historyContainer.scrollHeight;
        }
    });
</script>
</body>
</html>
