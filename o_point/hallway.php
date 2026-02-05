<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();
require_login();
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_user'])) {
    $to = intval($_POST['to_user']);
    if ($to && $to !== $user_id) {
        $stmt = $pdo->prepare('INSERT INTO hallways (from_user_id, to_user_id, is_open) VALUES (?, ?, TRUE)');
        $stmt->execute([$user_id, $to]);
    }
}
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ?');
$stmt->execute([$user_id]);
$users = $stmt->fetchAll();
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><title>HALLWAY — O.point</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA">
  <h1>Ouvrir un passage</h1>
  <form method="post">
    <label>Vers le h0me de :
      <select name="to_user">
        <?php foreach($users as $u): ?>
          <option value="<?=e($u['id'])?>"><?=e($u['username'])?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Ouvrir le hallway</button>
  </form>
  <a href="h0me.php">Retour au h0me</a>

<script src="/o_point/feedback.js"></script>
</body>
<script src="/o_point/micro.js"></script>
</html>
</body></html>
