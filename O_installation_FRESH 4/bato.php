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

$stmt = $pdo->prepare("SELECT * FROM lands WHERE username = ?");
$stmt->execute([$username]);
$land = $stmt->fetch();

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
<title>BATO — O.</title>
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

.bato-status {
  font-size: 1.2rem;
  margin-bottom: 1rem;
  line-height: 1.6;
  opacity: 0.9;
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

.voyage-log {
  background: rgba(var(--o-fg-rgb) / 0.06);
  border: 1px solid var(--o-line);
  padding: 1.5rem;
  border-radius: 8px;
  margin-top: 2rem;
}

.voyage-log p {
  line-height: 1.8;
  opacity: 0.8;
  margin-bottom: 1rem;
}

.coordinates {
  font-size: 0.85rem;
  opacity: 0.5;
  margin-top: 2rem;
}
</style>
</head>

<body>

<section>
  <h1>BATO</h1>
  <p class="bato-status">
    Le bateau tangue doucement.<br>
    L'horizon est calme.<br>
    Tu es seul sur le pont.
  </p>
  
  <div class="nav">
    <a href="land.php" data-o-layer>← Retour à LAND</a>
    <a href="shore.php" data-o-layer>SHORE</a>
    <a href="aza.php" data-o-layer>AZA</a>
    <a href="silence.php" data-o-layer>SILENCE</a>
  </div>
</section>

<section class="voyage-log">
  <h2>Carnet de bord</h2>
  <p>
    <?= date('d/m/Y H:i', strtotime($land['created_at'])) ?> — Première terre posée.<br>
    Fuseau : <?= htmlspecialchars($land['timezone']) ?><br>
    Zone : <?= htmlspecialchars($land['zone_code']) ?>
  </p>
  
  <p class="coordinates">
    Position : <?= htmlspecialchars($username) ?>@<?= htmlspecialchars($land['zone_code']) ?>
  </p>
</section>

</body>
</html>
