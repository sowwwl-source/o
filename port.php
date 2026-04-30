<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
$slug     = trim($_GET['slug'] ?? '');

if (!preg_match('/^[a-f0-9]{12}$/', $slug)) {
    header('Location: land.php');
    exit;
}

$stmtPort = $pdo->prepare("SELECT * FROM ports WHERE slug = ?");
$stmtPort->execute([$slug]);
$port = $stmtPort->fetch();

if (!$port) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmtMem = $pdo->prepare("SELECT * FROM port_members WHERE port_id = ? AND username = ?");
$stmtMem->execute([$port['id'], $username]);
$my_member = $stmtMem->fetch();

if (!$my_member) {
    header('Location: land.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$back_url = 'port.php?slug=' . urlencode($slug);
$msg_port = '';

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted)) {
        header('Location: ' . $back_url);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'message') {
        $body = trim($_POST['body'] ?? '');
        if ($body !== '') {
            $pdo->prepare("INSERT INTO port_messages (port_id, username, body, type) VALUES (?, ?, ?, 'text')")
                ->execute([$port['id'], $username, $body]);
        }
        header('Location: ' . $back_url . '#coeur');
        exit;
    }

    if ($action === 'html_update') {
        $html = $_POST['html_body'] ?? '';
        $pdo->prepare("UPDATE ports SET html_body = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$html, $port['id']]);
        $pdo->prepare("INSERT INTO port_messages (port_id, username, body, type) VALUES (?, ?, 'conteneur mis à jour', 'html_update')")
            ->execute([$port['id'], $username]);
        header('Location: ' . $back_url . '#coeur');
        exit;
    }

    if ($action === 'update_cou12') {
        $text = trim($_POST['cou12_text'] ?? '');
        $pdo->prepare("UPDATE port_members SET cou12_text = ? WHERE port_id = ? AND username = ?")
            ->execute([$text ?: null, $port['id'], $username]);
        header('Location: ' . $back_url . '#cou12');
        exit;
    }

    if ($action === 'file_upload') {
        $file     = $_FILES['zipfile'] ?? null;
        $max_size = 20 * 1024 * 1024; // 20 MB

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if ($file['size'] <= $max_size && $ext === 'zip') {
                $remote_key = 'ports/' . $port['id'] . '/' . bin2hex(random_bytes(10)) . '.zip';
                $result     = spaces_upload($file['tmp_name'], $remote_key);

                if ($result['ok']) {
                    $pdo->prepare("
                        INSERT INTO port_files (port_id, uploaded_by, original_name, stored_name, size_bytes)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$port['id'], $username, basename((string)$file['name']), $remote_key, (int)$file['size']]);
                    $pdo->prepare("
                        INSERT INTO port_messages (port_id, username, body, type) VALUES (?, ?, ?, 'file_upload')
                    ")->execute([$port['id'], $username, basename((string)$file['name'])]);
                }
            }
        }
        header('Location: ' . $back_url . '#core');
        exit;
    }

    header('Location: ' . $back_url);
    exit;
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$stmtLiaison = $pdo->prepare("SELECT * FROM liaisons WHERE id = ?");
$stmtLiaison->execute([$port['liaison_id']]);
$liaison = $stmtLiaison->fetch();

$stmtMembers = $pdo->prepare("SELECT * FROM port_members WHERE port_id = ?");
$stmtMembers->execute([$port['id']]);
$members = $stmtMembers->fetchAll();

$stmtMsgs = $pdo->prepare("SELECT * FROM port_messages WHERE port_id = ? ORDER BY created_at ASC");
$stmtMsgs->execute([$port['id']]);
$messages = $stmtMsgs->fetchAll();

$stmtFiles = $pdo->prepare("SELECT * FROM port_files WHERE port_id = ? ORDER BY created_at DESC");
$stmtFiles->execute([$port['id']]);
$files = $stmtFiles->fetchAll();

// Members keyed by username for quick lookup
$members_map = [];
foreach ($members as $m) {
    $members_map[$m['username']] = $m;
}

$port_name = $port['name'] ?: (
    $liaison
        ? htmlspecialchars($liaison['land_a']) . ' × ' . htmlspecialchars($liaison['land_b'])
        : 'p0rt'
);

function fmt_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' o';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' Ko';
    return round($bytes / 1048576, 1) . ' Mo';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<title>P0RT — O.</title>
<style>
body { max-width: 800px; margin: 3rem auto; padding: 0 1.5rem; }

.port-header { margin-bottom: 2rem; }
.port-header h1 { font-size: 2.2rem; margin-bottom: 0.15rem; }
.port-header .port-name { font-size: 0.8rem; letter-spacing: 0.2em; opacity: 0.55; text-transform: uppercase; }
.port-back { font-size: 0.82rem; opacity: 0.55; margin-bottom: 1.5rem; display: block; }

/* ── Tabs ── */
.port-tabs {
  display: flex; gap: 0; margin-bottom: 2rem;
  border-bottom: 1px solid var(--o-line);
}
.port-tabs button {
  background: none; border: none; border-bottom: 2px solid transparent;
  padding: 0.55rem 1.1rem; font-size: 0.82rem; letter-spacing: 0.16em;
  text-transform: uppercase; cursor: pointer; color: inherit; opacity: 0.5;
  margin-bottom: -1px;
  transition: opacity 140ms, border-color 140ms;
}
.port-tabs button.active { opacity: 1; border-bottom-color: var(--o-fg); }
.port-tabs button:hover { opacity: 0.85; background: none; }

/* ── Tab panels ── */
.port-tab { display: none; }
.port-tab.active { display: block; }

/* ── c0u12 ── */
.cou12-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
.cou12-card {
  background: var(--o-fill); border: 1px solid var(--o-line);
  padding: 1.1rem; border-radius: 6px;
}
.cou12-card h3 { margin: 0 0 0.5rem; font-size: 0.95rem; letter-spacing: 0.08em; }
.cou12-card p { font-size: 0.88rem; opacity: 0.75; margin: 0 0 0.75rem; line-height: 1.6; }
.cou12-card textarea {
  width: 100%; min-height: 80px; resize: vertical;
  font-size: 0.88rem; font-family: inherit; line-height: 1.5;
  padding: 0.55rem; box-sizing: border-box;
}
.cou12-card button { margin-top: 0.5rem; padding: 0.4rem 1rem; font-size: 0.85rem; }

/* ── c0eur ── */
.coeur-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
@media (max-width: 600px) { .coeur-layout { grid-template-columns: 1fr; } }

.coeur-chat { display: flex; flex-direction: column; gap: 0.75rem; }
.chat-history {
  border: 1px solid var(--o-line); border-radius: 6px; background: var(--o-fill);
  max-height: 360px; overflow-y: auto;
  padding: 0.85rem; display: flex; flex-direction: column; gap: 0.5rem;
}
.chat-msg { padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.88rem; line-height: 1.5; border: 1px solid var(--o-line); }
.chat-msg--sent { align-self: flex-end; background: var(--o-fg); color: var(--o-bg); border-color: var(--o-fg); }
.chat-msg--recv { align-self: flex-start; background: var(--o-bg); }
.chat-msg--event { align-self: center; opacity: 0.4; font-size: 0.75rem; border: none; background: none; }
.chat-meta { display: block; font-size: 0.7rem; opacity: 0.55; margin-bottom: 0.2rem; }
.chat-form textarea { min-height: 70px; resize: vertical; font-size: 0.88rem; }
.chat-form button { margin-top: 0.4rem; padding: 0.4rem 1rem; font-size: 0.85rem; }

.coeur-container {}
.coeur-container h3 { font-size: 0.8rem; letter-spacing: 0.18em; opacity: 0.6; text-transform: uppercase; margin-bottom: 0.5rem; }
.container-iframe {
  width: 100%; height: 280px; border: 1px solid var(--o-line); border-radius: 6px;
  background: var(--o-fill); margin-bottom: 0.75rem;
}
.container-editor textarea {
  min-height: 120px; resize: vertical; font-size: 0.82rem;
  font-family: 'Share Tech Mono', monospace;
}
.container-editor button { margin-top: 0.4rem; padding: 0.4rem 1rem; font-size: 0.85rem; }

/* ── c0re ── */
.core-files { list-style: none; padding: 0; margin: 0 0 1.5rem; }
.core-files li {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.65rem 0; border-bottom: 1px solid var(--o-line); gap: 1rem; flex-wrap: wrap;
}
.core-files .file-name { font-size: 0.9rem; }
.core-files .file-meta { font-size: 0.75rem; opacity: 0.5; }
.core-files a { font-size: 0.8rem; }
.core-upload h3 { font-size: 0.8rem; letter-spacing: 0.18em; opacity: 0.6; text-transform: uppercase; margin-bottom: 0.5rem; }
.core-upload input[type=file] { border: 1px dashed var(--o-line); padding: 0.75rem; background: var(--o-fill); width: 100%; box-sizing: border-box; cursor: pointer; }
.core-upload button { margin-top: 0.5rem; padding: 0.4rem 1rem; font-size: 0.85rem; }
.core-upload .hint { font-size: 0.75rem; opacity: 0.5; margin: 0.35rem 0 0; }

.empty-state { opacity: 0.45; font-size: 0.88rem; padding: 0.75rem 0; }
</style>
</head>
<body>

<div class="port-header">
  <a class="port-back" href="land.php">← retour à la terre</a>
  <h1>P0RT</h1>
  <div class="port-name"><?= $port_name ?></div>
</div>

<nav class="port-tabs">
  <button class="active" data-tab="cou12">c0u12</button>
  <?php if ($port['has_coeur']): ?>
    <button data-tab="coeur">c0eur</button>
  <?php endif; ?>
  <?php if ($port['has_core']): ?>
    <button data-tab="core">c0re</button>
  <?php endif; ?>
</nav>

<!-- ══════════════════════════════════════════
     C0U12 — Profil / présentation
══════════════════════════════════════════ -->
<section class="port-tab active" id="tab-cou12">
  <div class="cou12-cards">
    <?php foreach ($members as $m): ?>
      <div class="cou12-card">
        <h3><?= htmlspecialchars($m['username']) ?></h3>
        <?php if ($m['username'] === $username): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="update_cou12">
            <textarea name="cou12_text" placeholder="Présente-toi en quelques mots..."><?= htmlspecialchars($m['cou12_text'] ?? '') ?></textarea>
            <button type="submit">Sauvegarder</button>
          </form>
        <?php else: ?>
          <p><?= $m['cou12_text'] ? nl2br(htmlspecialchars($m['cou12_text'])) : '<span style="opacity:.4">Silence.</span>' ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══════════════════════════════════════════
     C0EUR — Salon + conteneur HTML
══════════════════════════════════════════ -->
<?php if ($port['has_coeur']): ?>
<section class="port-tab" id="tab-coeur">
  <div class="coeur-layout">

    <!-- Discussion -->
    <div class="coeur-chat">
      <div class="chat-history" id="chat-history">
        <?php if (empty($messages)): ?>
          <p class="empty-state">Silence dans le p0rt.</p>
        <?php else: ?>
          <?php foreach ($messages as $msg): ?>
            <?php if ($msg['type'] !== 'text'): ?>
              <div class="chat-msg chat-msg--event"><?= htmlspecialchars($msg['username']) ?> — <?= htmlspecialchars($msg['body']) ?></div>
            <?php else: ?>
              <?php $sent = $msg['username'] === $username; ?>
              <div class="chat-msg <?= $sent ? 'chat-msg--sent' : 'chat-msg--recv' ?>">
                <span class="chat-meta"><?= htmlspecialchars($msg['username']) ?> · <?= htmlspecialchars(date('d/m H:i', strtotime($msg['created_at']))) ?></span>
                <?= nl2br(htmlspecialchars($msg['body'])) ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form method="post" class="chat-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="message">
        <textarea name="body" placeholder="Message..." required></textarea>
        <button type="submit">Émettre</button>
      </form>
    </div>

    <!-- Conteneur HTML live -->
    <div class="coeur-container">
      <h3>Conteneur</h3>
      <iframe
        id="port-iframe"
        class="container-iframe"
        sandbox="allow-scripts allow-forms"
        srcdoc="<?= htmlspecialchars($port['html_body'] ?? '') ?>"
      ></iframe>
      <div class="container-editor">
        <form method="post" id="container-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="action" value="html_update">
          <textarea
            name="html_body"
            id="container-src"
            placeholder="<!-- HTML embarqué -->"
          ><?= htmlspecialchars($port['html_body'] ?? '') ?></textarea>
          <button type="submit">Déployer</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════
     C0RE — Fichiers ZIP partagés
══════════════════════════════════════════ -->
<?php if ($port['has_core']): ?>
<section class="port-tab" id="tab-core">

  <?php if (empty($files)): ?>
    <p class="empty-state">Aucun fichier partagé pour l'instant.</p>
  <?php else: ?>
    <ul class="core-files">
      <?php foreach ($files as $f): ?>
        <li>
          <span>
            <span class="file-name"><?= htmlspecialchars($f['original_name']) ?></span>
            <span class="file-meta"> · par <?= htmlspecialchars($f['uploaded_by']) ?> · <?= fmt_size((int)$f['size_bytes']) ?> · <?= htmlspecialchars(date('d/m/Y', strtotime($f['created_at']))) ?></span>
          </span>
          <a href="<?= htmlspecialchars(spaces_url($f['stored_name'])) ?>" download="<?= htmlspecialchars($f['original_name']) ?>" target="_blank" rel="noopener">↓ télécharger</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div class="core-upload">
    <h3>Partager un fichier</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="action" value="file_upload">
      <input type="file" name="zipfile" accept=".zip" required>
      <p class="hint">Format ZIP uniquement · 20 Mo max</p>
      <button type="submit">Envoyer</button>
    </form>
  </div>
</section>
<?php endif; ?>

<script>
// ── Tab switching ──
document.querySelectorAll('.port-tabs button').forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.dataset.tab;
    document.querySelectorAll('.port-tabs button').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.port-tab').forEach(s => s.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tab)?.classList.add('active');
    history.replaceState(null, '', '#' + tab);
  });
});

// Restore tab from URL hash
(function () {
  const tab = (location.hash || '').replace('#', '');
  if (tab) {
    const btn = document.querySelector(`.port-tabs button[data-tab="${tab}"]`);
    if (btn) btn.click();
  }
})();

// ── Auto-scroll chat ──
const hist = document.getElementById('chat-history');
if (hist) hist.scrollTop = hist.scrollHeight;

// ── Live iframe preview ──
const src = document.getElementById('container-src');
const ifr = document.getElementById('port-iframe');
if (src && ifr) {
  src.addEventListener('input', () => { ifr.srcdoc = src.value; });
}
</script>
</body>
</html>
