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

$shore_message = '';
$shore_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shore'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $shore_message = "Session expirée. Réessaie.";
    } else {
        $shore_text = trim($_POST['shore_text'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE lands SET shore_text = :shore_text WHERE username = :username");
            $stmt->execute([
                ':shore_text' => $shore_text,
                ':username' => $username
            ]);
            $shore_success = true;
            $shore_message = "SHORE mis à jour.";
        } catch (PDOException $e) {
            $shore_message = "Erreur lors de la mise à jour.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM lands WHERE username = ?");
$stmt->execute([$username]);
$land = $stmt->fetch();

if ($shore_success) {
    $stmt->execute([$username]);
    $land = $stmt->fetch();
}

if (!$land) {
    die('LAND introuvable');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
<title>SHORE — O.</title>
<style>
body {
  max-width: 720px;
  margin: 4rem auto;
  padding: 0 1.5rem;
}

section {
  margin-bottom: 3rem;
}

h1 {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

h2 {
  font-size: 0.85rem;
  letter-spacing: 0.18em;
  opacity: 0.6;
  margin-bottom: 0.75rem;
  text-transform: uppercase;
}

.shore-content {
  background: var(--o-fill);
  border: 1px solid var(--o-line);
  padding: 2rem;
  border-radius: 8px;
  box-shadow: var(--o-shadow);
  margin: 2rem 0;
}

.shore-content p {
  line-height: 1.8;
  opacity: 0.9;
  white-space: pre-wrap;
}

.nav {
  display: flex;
  gap: 1.5rem;
  margin-top: 2rem;
  font-size: 0.9rem;
}

.nav a {
  opacity: 0.7;
  transition: opacity 0.2s;
}

.nav a:hover {
  opacity: 1;
}

.edit-link {
  font-size: 0.85rem;
  opacity: 0.7;
  cursor: pointer;
  text-decoration: underline;
  margin-top: 1rem;
  display: inline-block;
}

.edit-link:hover {
  opacity: 1;
}

textarea {
  width: 100%;
  min-height: 200px;
  padding: 1rem;
  font-size: 1rem;
  font-family: inherit;
  line-height: 1.8;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  box-sizing: border-box;
  resize: vertical;
}

button {
  margin-top: 0.75rem;
  margin-right: 0.5rem;
  padding: 0.7rem 1.5rem;
  font-size: 0.9rem;
  background: transparent;
  color: inherit;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background: var(--o-fg);
  color: var(--o-bg);
  border-color: transparent;
}

button[type="button"] {
  opacity: 0.8;
}

button[type="button"]:hover {
  opacity: 1;
}

.success {
  margin: 0.5rem 0;
  font-size: 0.9rem;
}

.error {
  margin: 0.5rem 0;
  font-size: 0.9rem;
}
</style>
</head>

<body>

<section>
  <h1>SHORE</h1>
  <h2>Le rivage</h2>
  
  <?php if ($shore_success): ?>
    <p class="success"><?= htmlspecialchars($shore_message) ?></p>
  <?php elseif ($shore_message): ?>
    <p class="error"><?= htmlspecialchars($shore_message) ?></p>
  <?php endif; ?>
  
  <div class="shore-content">
    <div id="shore-view">
      <p><?= nl2br(htmlspecialchars($land['shore_text'] ?? 'Silence.')) ?></p>
      <p class="edit-link" onclick="toggleShoreEdit(event)">Éditer</p>
    </div>
    
    <form method="post" id="shore-edit" style="display: none;">
      <input type="hidden" name="update_shore" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <textarea name="shore_text" placeholder="Écrire sur SHORE..."><?= htmlspecialchars($land['shore_text'] ?? '') ?></textarea>
      <button type="submit">Sauvegarder</button>
      <button type="button" onclick="toggleShoreEdit(event)">Annuler</button>
    </form>
  </div>
  
  <div class="nav">
    <a href="land.php" data-o-layer>← Retour à LAND</a>
    <a href="bato.php" data-o-layer>BATO</a>
    <a href="aza.php" data-o-layer>AZA</a>
    <a href="silence.php" data-o-layer>SILENCE</a>
  </div>
</section>

<script>
function toggleShoreEdit(event) {
  if (event) event.stopPropagation();
  const view = document.getElementById('shore-view');
  const edit = document.getElementById('shore-edit');
  if (view.style.display === 'none') {
    view.style.display = 'block';
    edit.style.display = 'none';
  } else {
    view.style.display = 'none';
    edit.style.display = 'block';
  }
}
</script>

</body>
</html>
