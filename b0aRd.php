<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];

$stmt = $pdo->prepare("SELECT * FROM lands WHERE username = ?");
$stmt->execute([$username]);
$land = $stmt->fetch();

if (!$land) {
    die('LAND introuvable');
}

// Statistiques
$account_age_days = floor((time() - strtotime($land['created_at'])) / 86400);
$shore_word_count = str_word_count($land['shore_text'] ?? '');
$shore_char_count = mb_strlen($land['shore_text'] ?? '');

// Stats globales (tous les utilisateurs)
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM lands");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as users_with_shore FROM lands WHERE shore_text IS NOT NULL AND shore_text != ''");
$users_with_shore = $stmt->fetch()['users_with_shore'];

// Dernières inscriptions
$stmt = $pdo->query("SELECT username, created_at FROM lands ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="global-styles.css">
<title>Dashboard — O.</title>
<style>
body {
  font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
  background: #fafafa;
  color: #111;
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 1.5rem;
}

h1 {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

h2 {
  font-size: 0.85rem;
  letter-spacing: 0.18em;
  opacity: 0.6;
  margin: 2rem 0 1rem;
  text-transform: uppercase;
}

.nav {
  display: flex;
  gap: 1.5rem;
  margin: 2rem 0;
  font-size: 0.9rem;
  flex-wrap: wrap;
}

.nav a {
  color: #111;
  text-decoration: none;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.nav a:hover {
  opacity: 1;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
  margin: 2rem 0;
}

.stat-card {
  background: #fff;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 600;
  margin: 0.5rem 0;
  color: #111;
}

.stat-label {
  font-size: 0.85rem;
  opacity: 0.7;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.stat-sublabel {
  font-size: 0.8rem;
  opacity: 0.5;
  margin-top: 0.25rem;
}

.chart-container {
  background: #fff;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.06);
  margin: 2rem 0;
}

.bar {
  height: 30px;
  background: linear-gradient(90deg, #111 0%, #666 100%);
  border-radius: 4px;
  margin: 0.5rem 0;
  position: relative;
  transition: all 0.3s;
}

.bar:hover {
  transform: scaleX(1.02);
  transform-origin: left;
}

.bar-label {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #fff;
  font-size: 0.85rem;
  font-weight: 500;
}

.recent-list {
  background: #fff;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}

.recent-item {
  padding: 0.75rem 0;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.recent-item:last-child {
  border-bottom: none;
}

.recent-username {
  font-weight: 500;
}

.recent-date {
  font-size: 0.85rem;
  opacity: 0.6;
}

.progress-ring {
  display: inline-block;
  width: 120px;
  height: 120px;
  margin: 1rem auto;
}

.progress-container {
  text-align: center;
  padding: 1rem;
}

.progress-label {
  font-size: 1.5rem;
  font-weight: 600;
  margin-top: 0.5rem;
}
</style>
</head>

<body>

<h1>Dashboard</h1>

<div class="nav">
  <a href="land.php">← LAND</a>
  <a href="shore.php">SHORE</a>
  <a href="bato.php">BATO</a>
  <a href="silence.php">SILENCE</a>
</div>

<h2>Mes Statistiques</h2>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Âge du compte</div>
    <div class="stat-value"><?= $account_age_days ?></div>
    <div class="stat-sublabel">jours</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-label">SHORE</div>
    <div class="stat-value"><?= $shore_word_count ?></div>
    <div class="stat-sublabel"><?= $shore_char_count ?> caractères</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-label">Zone</div>
    <div class="stat-value" style="font-size: 1.2rem;"><?= htmlspecialchars($land['zone_code']) ?></div>
    <div class="stat-sublabel"><?= htmlspecialchars($land['timezone']) ?></div>
  </div>
  
  <div class="stat-card">
    <div class="stat-label">Membre depuis</div>
    <div class="stat-value" style="font-size: 1.2rem;">
      <?= date('d/m/Y', strtotime($land['created_at'])) ?>
    </div>
    <div class="stat-sublabel"><?= date('H:i', strtotime($land['created_at'])) ?></div>
  </div>
</div>

<h2>Statistiques Globales</h2>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total utilisateurs</div>
    <div class="stat-value"><?= $total_users ?></div>
    <div class="stat-sublabel">terres posées</div>
  </div>
  
  <div class="stat-card">
    <div class="stat-label">SHORE actifs</div>
    <div class="stat-value"><?= $users_with_shore ?></div>
    <div class="stat-sublabel"><?= round(($users_with_shore / max($total_users, 1)) * 100) ?>% des utilisateurs</div>
  </div>
</div>

<h2>Activité de SHORE</h2>

<div class="chart-container">
  <div class="bar" style="width: <?= min(100, ($shore_word_count / 100) * 100) ?>%;">
    <span class="bar-label">Mots: <?= $shore_word_count ?></span>
  </div>
  <div class="bar" style="width: <?= min(100, ($shore_char_count / 500) * 100) ?>%;">
    <span class="bar-label">Caractères: <?= $shore_char_count ?></span>
  </div>
</div>

<h2>Dernières Inscriptions</h2>

<div class="recent-list">
  <?php foreach ($recent_users as $user): ?>
    <div class="recent-item">
      <span class="recent-username"><?= htmlspecialchars($user['username']) ?></span>
      <span class="recent-date">
        <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
      </span>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>
