<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === 'sowwwl.xyz' || $host === 'www.sowwwl.xyz') {
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/echo.php');
    header('Location: https://sowwwl.com' . $path, true, 302);
    exit;
}

$brandDomain = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$csrfToken = csrf_token();
$guideHref = '/0wlslw0.php';
$echoGuide = guide_path('echo');

$land = current_authenticated_land();
if (!$land) {
    header('Location: /?error=auth');
    exit;
}

$ambientProfile = land_visual_profile($land);
$myUsername = (string) $land['username'];
$targetUsername = trim((string) ($_GET['u'] ?? ''));
$message = '';
$messageType = 'info';

// Traitement de l'envoi d'un écho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postedToken)) {
        $message = "La résonance s'est dissipée. Réessaie.";
        $messageType = 'warning';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        $receiver = trim((string) ($_POST['receiver_username'] ?? ''));
        
        if ($body !== '' && $receiver !== '') {
            $stmt = $pdo->prepare("INSERT INTO echoes (sender_username, receiver_username, body) VALUES (?, ?, ?)");
            $stmt->execute([$myUsername, $receiver, $body]);
            header("Location: /echo.php?u=" . urlencode($receiver));
            exit;
        }
    }
}

// Récupération des contacts (Terres existantes, excluant soi-même)
$stmtContacts = $pdo->prepare("
    SELECT username, slug,
           (SELECT COUNT(*) FROM echoes WHERE sender_username = lands.username AND receiver_username = ? AND is_read = 0) as unread_count
    FROM lands 
    WHERE username != ? 
    ORDER BY created_at DESC
");
$stmtContacts->execute([$myUsername, $myUsername]);
$contacts = $stmtContacts->fetchAll();

// Récupération de l'historique si une Terre est ciblée
$history = [];
if ($targetUsername !== '') {
    // Marquer les messages entrants comme lus
    $stmtRead = $pdo->prepare("UPDATE echoes SET is_read = 1 WHERE sender_username = ? AND receiver_username = ?");
    $stmtRead->execute([$targetUsername, $myUsername]);

    $stmtHistory = $pdo->prepare("
        SELECT * FROM echoes 
        WHERE (sender_username = ? AND receiver_username = ?) 
           OR (sender_username = ? AND receiver_username = ?)
        ORDER BY created_at ASC
    ");
    $stmtHistory->execute([$myUsername, $targetUsername, $targetUsername, $myUsername]);
    $history = $stmtHistory->fetchAll();
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
                <a href="/echo.php?u=<?= rawurlencode((string) $c['username']) ?>" class="echo-contact <?= $c['username'] === $targetUsername ? 'is-active' : '' ?>">
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
            <?php if ($targetUsername === ''): ?>
                <p class="panel-copy">Sélectionne une Terre pour établir la liaison.</p>
            <?php else: ?>
                <div class="section-topline">
                    <h2>Liaison avec <?= h($targetUsername) ?></h2>
                </div>
                
                <div class="echo-history">
                    <?php if (empty($history)): ?>
                        <p class="panel-copy">Le silence règne entre vos deux terres.</p>
                    <?php else: ?>
                        <?php foreach ($history as $msg): ?>
                            <?php $isMe = $msg['sender_username'] === $myUsername; ?>
                            <div class="echo-msg <?= $isMe ? 'echo-msg--sent' : 'echo-msg--received' ?>">
                                <span class="echo-msg-meta"><?= h($msg['sender_username']) ?> · <?= h(human_created_label($msg['created_at'])) ?></span>
                                <?= nl2br(h($msg['body'])) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form action="/echo.php?u=<?= rawurlencode($targetUsername) ?>" method="post" class="land-form echo-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="receiver_username" value="<?= h($targetUsername) ?>">
                    
                    <label>
                        Transmission
                        <textarea name="body" required placeholder="Le signal à envoyer..."></textarea>
                    </label>
                    
                    <div class="action-row">
                        <button type="submit">Émettre l'écho</button>
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
