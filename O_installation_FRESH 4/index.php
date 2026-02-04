<?php
require __DIR__ . '/config.php';

start_secure_session();

$message = '';

function invite_codes(): array
{
    $raw = getenv('INVITE_CODES');
    if ($raw === false || trim($raw) === '') {
        $raw = getenv('INVITE_CODE') ?: '';
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function valid_username(string $username): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username);
}

$invite_codes = invite_codes();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $message = "Session expirée. Réessaie.";
    } else {
        $action = $_POST['action'] ?? 'register';

        if ($action === 'register') {
            $username = trim($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $timezone = trim($_POST['timezone'] ?? '');
            $invite_code = trim($_POST['invite_code'] ?? '');

            if (empty($invite_codes)) {
                $message = "Inscription fermée : code d’invitation non configuré.";
            } elseif (!valid_username($username)) {
                $message = "Nom d’usage invalide (3–32 caractères, a-z, 0-9, _ ou -).";
            } elseif (strlen($password) < 8) {
                $message = "Mot de passe trop court (8 caractères minimum).";
            } elseif ($timezone === '' || strlen($timezone) > 64) {
                $message = "Fuseau horaire invalide.";
            } elseif (!in_array($invite_code, $invite_codes, true)) {
                $message = "Code d’invitation invalide.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO lands (username, password_hash, email_virtual, timezone, zone_code)
                        VALUES (:username, :password_hash, :email_virtual, :timezone, :zone_code)
                    ");

                    $email_virtual = $username . '@o.local';
                    $zone_code = $timezone; // abstraction volontaire
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt->execute([
                        ':username' => $username,
                        ':password_hash' => $password_hash,
                        ':email_virtual' => $email_virtual,
                        ':timezone' => $timezone,
                        ':zone_code' => $zone_code
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['username'] = $username;
                    header('Location: land.php');
                    exit;
                } catch (PDOException $e) {
                    if ((string)$e->getCode() === '23000') {
                        $message = "Ce nom d’usage est déjà pris.";
                    } else {
                        $message = "Erreur d’inscription. Réessaie plus tard.";
                    }
                }
            }
        } elseif ($action === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $message = "Identifiants invalides.";
            } else {
                $stmt = $pdo->prepare("SELECT username, password_hash FROM lands WHERE username = ?");
                $stmt->execute([$username]);
                $land = $stmt->fetch();

                if (!$land || !password_verify($password, $land['password_hash'])) {
                    $message = "Identifiants invalides.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['username'] = $land['username'];
                    header('Location: land.php');
                    exit;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
    <script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
<title>O.</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        main {
            text-align: center;
            width: min(720px, 90vw);
        }
        .panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
            margin-top: 1.2rem;
        }
        .panel {
            background: var(--o-fill);
            border: 1px solid var(--o-line);
            padding: 1rem;
            border-radius: 8px;
            box-shadow: var(--o-shadow);
        }
        input, button {
            width: 100%;
            padding: 0.6em;
            margin: 0.4em 0;
            font-size: 1em;
            box-sizing: border-box;
        }
        button {
            cursor: pointer;
        }
        .message {
            margin: 1rem 0;
            padding: 0.75rem 0.9rem;
            border: 1px solid var(--o-line);
            background: var(--o-fill);
        }
        h2 {
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
<main>
    <h1>O.</h1>
    <p>S’installer ici.</p>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="panels">
        <form method="post" class="panel">
            <h2>Inscription</h2>
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="username" placeholder="Nom d’usage" autocomplete="username" required>
            <input type="password" name="password" placeholder="Mot de passe" autocomplete="new-password" required>
            <input type="text" name="timezone" placeholder="Fuseau horaire (ex: Europe/Paris)" required>
            <input type="text" name="invite_code" placeholder="Code d’invitation" autocomplete="one-time-code" required>
            <button type="submit">Poser une terre</button>
        </form>

        <form method="post" class="panel">
            <h2>Connexion</h2>
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="username" placeholder="Nom d’usage" autocomplete="username" required>
            <input type="password" name="password" placeholder="Mot de passe" autocomplete="current-password" required>
            <button type="submit">Entrer</button>
        </form>
    </div>
</main>
</body>
</html>
