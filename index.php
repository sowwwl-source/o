<?php
require __DIR__ . '/config.php';

// Traitement du formulaire
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $timezone = trim($_POST['timezone'] ?? '');

    if ($username && $timezone) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO lands (username, email_virtual, timezone, zone_code)
                VALUES (:username, :email_virtual, :timezone, :zone_code)
            ");

            $email_virtual = $username . '@o.local';
            $zone_code = $timezone; // abstraction volontaire

            $stmt->execute([
                ':username' => $username,
                ':email_virtual' => $email_virtual,
                ':timezone' => $timezone,
                ':zone_code' => $zone_code
            ]);

            $message = "Votre terre est posée.";
$message .= ' <a href="land.php?u=' . urlencode($username) . '">Aller chez vous</a>';
        } catch (PDOException $e) {
            $message = "Cette terre existe déjà.";
        }
    } else {
        $message = "Rien n’est obligatoire, mais quelque chose est nécessaire.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>O.</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f5f5f5;
            color: #111;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        main {
            text-align: center;
        }
        input, button {
            padding: 0.6em;
            margin: 0.4em;
            font-size: 1em;
        }
        button {
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <h1>O.</h1>
    <p>S’installer ici.</p>

    <?php if ($message): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="username" placeholder="Nom d’usage" required>
        <input type="text" name="timezone" placeholder="Fuseau horaire (ex: Europe/Paris)" required>
        <br>
        <button type="submit">Poser une terre</button>
    </form>
</main>
</body>
</html>
