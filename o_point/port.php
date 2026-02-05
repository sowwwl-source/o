<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();
require_login();
$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    $scope = $_POST['scope'] ?? 'h0me';
    if ($content) {
        $stmt = $pdo->prepare('INSERT INTO ports (user_id, content, scope) VALUES (?, ?, ?)');
        $stmt->execute([$user_id, $content, $scope]);
        header('Location: h0me.php'); exit;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><title>PORT — O.point</title>
  <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA">
  <h1>Ouvrir une porte</h1>
  <form method="post">
    <textarea name="content" rows="3" cols="40" placeholder="J’ouvre ici une porte sur…"></textarea><br>
    <label>Scope :
      <select name="scope">
        <option value="h0me">H0ME</option>
        <option value="hallway">Hallway</option>
        <option value="o">O</option>
      </select>
    </label><br>
    <button type="submit">Ouvrir la porte</button>
  </form>
  <a href="h0me.php">Retour au h0me</a>
</body>
<script src="/sowwwl_assets/js/o-light.js"></script>
<script src="/o_point/port_feedback.js"></script>
</body>
<script src="/o_point/micro.js"></script>
</html>
</html>
