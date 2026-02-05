
<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/utils.php';
start_secure_session();
$msg = '';
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
        } else {
                $msg = "Merci de remplir tous les champs.";
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
        } else {
                $msg = "Identifiants incorrects.";
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
    <meta charset="UTF-8">
    <title>Connexion — O.point</title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body class="AeiouuoieA auth-page">
    <main>
        <h1>Ouvrir la porte</h1>
        <div class="panels">
            <div class="panel">
                <h2>Connexion</h2>
                <form method="post" autocomplete="on">
                    <input type="text" name="username" placeholder="Nom d'utilisateur" required autofocus>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    <button type="submit" name="login">Entrer</button>
                </form>
            </div>
            <div class="panel">
                <h2>Créer un h0me</h2>
                <form method="post" autocomplete="on">
                    <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    <input type="text" name="bi" placeholder="Bio (optionnel)">
                    <button type="submit" name="register">S'inscrire</button>
                </form>
            </div>
        </div>
        <?php if ($msg): ?>
            <div class="message"><?=e($msg)?></div>
        <?php endif; ?>
        <div class="meta">O.point — Un réseau habitable, poétique, sans bruit.</div>
        <div style="margin-top:1.5em;"><a href="/o_point/o.php" class="btn btn-secondary">Retour à O</a></div>
    </main>
    <script src="/o_point/feedback.js"></script>
    <script src="/o_point/micro.js"></script>
</body>
</html>
