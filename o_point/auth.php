
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
        // Génère un email unique du type 0.<username>.o@sowwwl.com
        $email_virtual = '0.' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $u) . '.o@sowwwl.com';
        $stmt = $pdo->prepare('INSERT INTO users (username, email_virtual, password_hash, bi) VALUES (?, ?, ?, ?)');
        $stmt->execute([$u, $email_virtual, $hash, $bi]);
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
    <title>Ouvrir la porte — O.point</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        body.AeiouuoieA.auth-page {
            background: linear-gradient(120deg, #101c14 60%, #1e2a22 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2.2em;
        }
        .auth-header .o-icon {
            font-family: 'Orbitra', 'Share Tech Mono', monospace;
            font-size: 3.2em;
            color: #b6ffb3;
            text-shadow: 0 0 18px #b6ffb3, 0 0 44px #ffb34788;
            margin-bottom: 0.2em;
            display: block;
            letter-spacing: 0.04em;
            font-weight: 700;
            animation: o-pulse 2.2s cubic-bezier(.4,2,.6,1) infinite alternate;
        }
        .auth-header h1 {
            font-size: 2.1em;
            font-weight: 400;
            margin: 0.1em 0 0.2em 0;
            letter-spacing: 0.12em;
            color: var(--sowl-txt);
        }
        .auth-header .poem {
            font-size: 1.1em;
            color: var(--sowl-dim);
            font-style: italic;
            margin-bottom: 0.5em;
        }
        .panels {
            display: flex;
            gap: 2.2em;
            justify-content: center;
            flex-wrap: wrap;
        }
        .panel {
            background: rgba(0,0,0,0.22);
            border-radius: 14px;
            box-shadow: 0 8px 32px #000a;
            border: 1.5px solid var(--sowl-dim);
            padding: 2.1em 2.2em 1.5em 2.2em;
            min-width: 260px;
            max-width: 320px;
            flex: 1 1 260px;
            transition: box-shadow 0.4s;
        }
        .panel:hover {
            box-shadow: 0 0 32px #b6ffb355, 0 0 0 #ffb34700;
        }
        .panel h2 {
            font-size: 1.08em;
            letter-spacing: 0.09em;
            color: var(--sowl-txt);
            margin-bottom: 1.1em;
            text-align: center;
        }
        .panel input {
            margin-bottom: 1.1em;
        }
        .panel button {
            width: 100%;
            margin-top: 0.5em;
        }
        .message {
            margin: 1.2em 0 0.5em 0;
            color: #fca5a5;
            text-align: center;
            font-size: 1.08em;
        }
        .meta {
            text-align: center;
            margin-top: 2.2em;
            color: var(--sowl-dim);
            font-size: 0.98em;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 2.2em;
        }
    </style>
</head>
<body class="AeiouuoieA auth-page">
    <main>
        <div class="auth-header">
            <span class="o-icon">O.</span>
            <h1>Ouvrir la porte</h1>
            <div class="poem">«&nbsp;Ici, l’eau relie chaque h0me, sans bruit, sans force, juste présence.&nbsp;»</div>
        </div>
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
        <div class="back-link"><a href="/o_point/o.php" class="btn btn-secondary">Retour à O</a></div>
    </main>
    <script src="/o_point/feedback.js"></script>
    <script src="/o_point/micro.js"></script>
</body>
</html>
