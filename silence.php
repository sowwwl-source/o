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
<link rel="stylesheet" href="global-styles.css">
<title>SILENCE — O.</title>
<style>
body {
  font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
  background: #000;
  color: #222;
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
  color: #111;
  font-weight: 100;
  letter-spacing: 0.5em;
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
  color: #333;
  text-decoration: none;
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
  <a href="land.php">LAND</a>
  <a href="shore.php">SHORE</a>
  <a href="bato.php">BATO</a>
</div>

</body>
</html>
