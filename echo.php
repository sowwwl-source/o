<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$target = trim($_GET['u'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $message = "Session expirée. Réessaie.";
    } else {
        $body    = trim($_POST['body'] ?? '');
        $receiver = trim($_POST['receiver_username'] ?? '');

        if ($body !== '' && $receiver !== '' && $receiver !== $username) {
            $stmt = $pdo->prepare("INSERT INTO echoes (sender_username, receiver_username, body) VALUES (?, ?, ?)");
            $stmt->execute([$username, $receiver, $body]);

            // Notification email si le destinataire en a configuré une
            $stmtEmail = $pdo->prepare("SELECT notification_email FROM lands WHERE username = ?");
            $stmtEmail->execute([$receiver]);
            $notifEmail = (string) ($stmtEmail->fetchColumn() ?: '');
            if ($notifEmail !== '') {
                send_echo_notification($notifEmail, $username, $receiver);
            }

            header('Location: echo.php?u=' . rawurlencode($receiver));
            exit;
        }
    }
}

// Contacts avec compteur d'échos non lus
$stmtContacts = $pdo->prepare("
    SELECT l.username,
           COALESCE(SUM(e.is_read = 0 AND e.receiver_username = :me), 0) AS unread_count
    FROM lands l
    LEFT JOIN echoes e ON e.sender_username = l.username AND e.receiver_username = :me2
    WHERE l.username != :me3
    GROUP BY l.username
    ORDER BY unread_count DESC, l.created_at DESC
");
$stmtContacts->execute([':me' => $username, ':me2' => $username, ':me3' => $username]);
$contacts = $stmtContacts->fetchAll();

// Historique avec la terre ciblée
$history = [];
if ($target !== '') {
    $pdo->prepare("UPDATE echoes SET is_read = 1 WHERE sender_username = ? AND receiver_username = ?")
        ->execute([$target, $username]);

    $stmtHistory = $pdo->prepare("
        SELECT * FROM echoes
        WHERE (sender_username = ? AND receiver_username = ?)
           OR (sender_username = ? AND receiver_username = ?)
        ORDER BY created_at ASC
    ");
    $stmtHistory->execute([$username, $target, $target, $username]);
    $history = $stmtHistory->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<title>Écho — O.</title>
<style>
body {
  max-width: 900px;
  margin: 4rem auto;
  padding: 0 1.5rem;
}
h1 { font-size: 3rem; margin-bottom: 0.25rem; }
h2 {
  font-size: 0.85rem;
  letter-spacing: 0.18em;
  opacity: 0.6;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
}
.back { font-size: 0.85rem; opacity: 0.6; margin-bottom: 2rem; display: block; }
.echo-grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
  align-items: start;
}
.echo-contacts { border-right: 1px solid var(--o-line); padding-right: 1.5rem; }
.echo-contact {
  display: block;
  padding: 0.65rem 0.75rem;
  border-radius: 6px;
  text-decoration: none;
  color: inherit;
  font-size: 0.95rem;
  margin-bottom: 0.35rem;
  border: 1px solid transparent;
}
.echo-contact:hover { background: var(--o-fill); border-color: var(--o-line); }
.echo-contact.is-active { background: var(--o-fill); border-color: var(--o-line); font-weight: 600; }
.echo-badge {
  display: inline-block;
  background: var(--o-fg);
  color: var(--o-bg);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.05rem 0.4rem;
  border-radius: 99px;
  margin-left: 0.3rem;
  vertical-align: middle;
}
.echo-conversation {
  background: var(--o-fill);
  border: 1px solid var(--o-line);
  border-radius: 8px;
  padding: 1.25rem;
  box-shadow: var(--o-shadow);
}
.echo-history {
  max-height: 420px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin-bottom: 1rem;
  padding-right: 0.25rem;
}
.echo-msg {
  padding: 0.6rem 0.85rem;
  border-radius: 8px;
  font-size: 0.93rem;
  line-height: 1.55;
  max-width: 85%;
  border: 1px solid var(--o-line);
}
.echo-msg--sent {
  align-self: flex-end;
  background: var(--o-fg);
  color: var(--o-bg);
  border-color: var(--o-fg);
}
.echo-msg--received { align-self: flex-start; background: var(--o-bg); }
.echo-msg-meta {
  display: block;
  font-size: 0.72rem;
  opacity: 0.55;
  margin-bottom: 0.25rem;
}
.echo-form textarea {
  width: 100%;
  min-height: 80px;
  padding: 0.65rem;
  font-size: 0.95rem;
  font-family: inherit;
  line-height: 1.5;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  box-sizing: border-box;
  resize: vertical;
}
.echo-form button {
  margin-top: 0.5rem;
  padding: 0.55rem 1.2rem;
  font-size: 0.95rem;
  cursor: pointer;
}
.empty-state { opacity: 0.5; font-size: 0.9rem; padding: 1rem 0; }
.message { margin: 0.75rem 0; padding: 0.65rem 0.85rem; border: 1px solid var(--o-line); background: var(--o-fill); border-radius: 4px; font-size: 0.9rem; }
@media (max-width: 580px) {
  .echo-grid { grid-template-columns: 1fr; }
  .echo-contacts { border-right: none; border-bottom: 1px solid var(--o-line); padding-right: 0; padding-bottom: 1rem; }
}
</style>
</head>
<body>

<h1>Écho</h1>
<a class="back" href="land.php">← retour à la terre</a>

<?php if ($message): ?>
<p class="message"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<div class="echo-grid">
  <aside class="echo-contacts">
    <h2>Archipel</h2>
    <?php if (empty($contacts)): ?>
      <p class="empty-state">Aucune autre terre pour l'instant.</p>
    <?php else: ?>
      <?php foreach ($contacts as $c): ?>
        <a href="echo.php?u=<?= rawurlencode((string) $c['username']) ?>"
           class="echo-contact <?= $c['username'] === $target ? 'is-active' : '' ?>">
          <?= htmlspecialchars((string) $c['username']) ?>
          <?php if ($c['unread_count'] > 0): ?>
            <span class="echo-badge"><?= (int) $c['unread_count'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </aside>

  <div class="echo-conversation">
    <?php if ($target === ''): ?>
      <p class="empty-state">Sélectionne une terre pour commencer.</p>
    <?php else: ?>
      <h2>Liaison avec <?= htmlspecialchars($target) ?></h2>

      <div class="echo-history" id="echo-history">
        <?php if (empty($history)): ?>
          <p class="empty-state">Silence entre vos deux terres.</p>
        <?php else: ?>
          <?php foreach ($history as $msg): ?>
            <?php $sent = $msg['sender_username'] === $username; ?>
            <div class="echo-msg <?= $sent ? 'echo-msg--sent' : 'echo-msg--received' ?>">
              <span class="echo-msg-meta">
                <?= htmlspecialchars($msg['sender_username']) ?>
                · <?= htmlspecialchars(date('d/m H:i', strtotime($msg['created_at']))) ?>
              </span>
              <?= nl2br(htmlspecialchars($msg['body'])) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="post" class="echo-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="receiver_username" value="<?= htmlspecialchars($target) ?>">
        <textarea name="body" placeholder="Écrire un écho..." required></textarea>
        <button type="submit">Émettre</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
const h = document.getElementById('echo-history');
if (h) h.scrollTop = h.scrollHeight;
</script>
</body>
</html>
