<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();
require_login();
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$u = $stmt->fetch();
$stmt = $pdo->prepare('SELECT * FROM ports WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user_id]);
$ports = $stmt->fetchAll();
$stmt = $pdo->prepare('SELECT h.*, u.username as to_user FROM hallways h JOIN users u ON h.to_user_id = u.id WHERE h.from_user_id = ?');
$stmt->execute([$user_id]);
$hallways = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><title>H0ME — O.point</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA">
  <h1>H0ME</h1>
  <div><b><?=e($u['username'])?></b></div>
  <div><em><?=e($u['bi'])?></em></div>
  <h2>Mes PORTS</h2>
  <ul>
    <?php foreach($ports as $p): ?>
      <li><?=e($p['content'])?> <small>[<?=e($p['scope'])?>]</small></li>
    <?php endforeach; ?>
  </ul>
  <h2>Hallways ouverts</h2>
  <ul>
    <?php foreach($hallways as $h): ?>
      <li>Vers le h0me de <?=e($h['to_user'])?> (<?= $h['is_open'] ? 'ouvert' : 'fermé' ?>)</li>
    <?php endforeach; ?>
  </ul>
  <a href="o.php">Aller à O</a>
  <div style="margin-top:1.5em;"><a href="messagerie.php" class="btn btn-primary">Messagerie & Appels</a></div>

<script src="/o_point/feedback.js"></script>
</body>
<script src="/o_point/micro.js"></script>
</html>
</body></html>
