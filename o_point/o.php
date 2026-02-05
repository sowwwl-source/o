<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();
$stmt = $pdo->query('SELECT o.*, p.content, u.username FROM o_surface o JOIN ports p ON o.port_id = p.id JOIN users u ON p.user_id = u.id WHERE o.visible_until IS NULL OR o.visible_until > NOW() ORDER BY o.visible_from DESC LIMIT 5');
$ports = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><title>O — O.point</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA">
  <h1>O</h1>
  <div style="margin:2em 0;">
    <?php if (!$ports): ?>
      <div>L’eau dort. Rien n’a traversé aujourd’hui.</div>
    <?php else: foreach($ports as $p): ?>
      <div style="margin-bottom:2em;">
        <blockquote><?=e($p['content'])?></blockquote>
        <div style="font-size:0.9em;opacity:0.7;">par le h0me de <?=e($p['username'])?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
  <a href="h0me.php">Aller à mon h0me</a>

<script src="/o_point/feedback.js"></script>
</body>
<script src="/o_point/micro.js"></script>
</html>
</body></html>
