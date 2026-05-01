<?php
http_response_code(404);
require __DIR__ . '/config.php';
start_secure_session();
$logged_in = isset($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
<title>Page introuvable — O.</title>
<style>
body {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  text-align: center;
}
main { max-width: 480px; padding: 0 1.5rem; }
h1 { font-size: 3rem; margin-bottom: 0.25rem; }
.code { font-size: 0.85rem; letter-spacing: 0.18em; opacity: 0.5; text-transform: uppercase; margin-bottom: 1.5rem; }
p { opacity: 0.7; margin-bottom: 2rem; }
.actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
.actions a {
  padding: 0.6rem 1.4rem;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  text-decoration: none;
  color: inherit;
  font-size: 0.95rem;
}
.actions a:hover { background: var(--o-fg); color: var(--o-bg); border-color: transparent; }
</style>
</head>
<body data-o-recovery-context="404" data-o-recovery-home="<?= htmlspecialchars($logged_in ? '/land' : '/') ?>">
<main>
  <h1>O.</h1>
  <p class="code">404 — Page introuvable</p>
  <p>La page demandée n'existe pas ou a quitté cette couche. Une autre entrée reste possible.</p>
  <div class="actions">
    <?php if ($logged_in): ?>
      <a href="/land" data-o-layer>← Retour à la terre</a>
    <?php else: ?>
      <a href="/" data-o-layer>← Retour à l'accueil</a>
    <?php endif; ?>
    <a href="/aza" data-o-layer>AZA</a>
  </div>
</main>
</body>
</html>
