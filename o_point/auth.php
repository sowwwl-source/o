<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();

// Inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $bi = trim($_POST['bi'] ?? '');
    if ($u && $p) {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email_virtual, password_hash, bi) VALUES (?, ?, ?, ?)');
        $stmt->execute([$u, strtolower($u).'@o.local', $hash, $bi]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        header('Location: h0me.php'); exit;
    }
}
// Connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$u]);
    $user = $stmt->fetch();
    if ($user && password_verify($p, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: h0me.php'); exit;
    }
}

// Déconnexion
if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: login.php'); exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><title>Connexion — O.point</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA">
    <h1>Connexion / Inscription</h1>
    <form method="post">
        <input type="text" name="username" placeholder="Nom d'utilisateur" required><br>
        <input type="password" name="password" placeholder="Mot de passe" required><br>
        <input type="text" name="bi" placeholder="Bio (optionnel)"><br>
        <button type="submit" name="login">Connexion</button>
        <button type="submit" name="register">Inscription</button>
    </form>
    <a href="/o_point/o.php">Retour à O</a>
    <script src="/o_point/feedback.js"></script>
</body>
<script src="/o_point/micro.js"></script>
</html>
</body></html>
