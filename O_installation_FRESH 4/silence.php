<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
<title>SILENCE — O.</title>
<style>
body {
  max-width: 720px;
  margin: 4rem auto;
  padding: 0 1.5rem;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.silence-container {
  text-align: center;
}

h1 {
  font-size: 5rem;
  margin-bottom: 3rem;
  font-weight: 100;
  letter-spacing: 0.5em;
  opacity: 0.25;
}

.breath {
  font-size: 1.2rem;
  opacity: 0.3;
  margin: 2rem 0;
  animation: breathe 8s ease-in-out infinite;
}

@keyframes breathe {
  0%, 100% { opacity: 0.1; }
  50% { opacity: 0.4; }
}

.nav {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 2rem;
  font-size: 0.85rem;
}

.nav a {
  opacity: 0.4;
  transition: opacity 0.3s;
}

.nav a:hover {
  opacity: 0.8;
}
</style>
</head>

<body>

<div class="silence-container">
  <h1>O</h1>
  <p class="breath">...</p>
</div>

<div class="nav">
  <a href="land.php" data-o-layer>LAND</a>
  <a href="shore.php" data-o-layer>SHORE</a>
  <a href="bato.php" data-o-layer>BATO</a>
  <a href="aza.php" data-o-layer>AZA</a>
</div>

</body>
</html>
