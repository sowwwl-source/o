<?php
require __DIR__ . '/config.php';

// Traitement du formulaire
$message = '';
$messageType = 'info';
$nextUrl = null;

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
            $messageType = 'success';
            $nextUrl = 'land.php?u=' . urlencode($username);
        } catch (PDOException $e) {
            $message = "Cette terre existe déjà.";
            $messageType = 'warning';
        }
    } else {
        $message = "Rien n’est obligatoire, mais quelque chose est nécessaire.";
        $messageType = 'warning';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="sowwwl.xyz — Just the Three of Us. O.n0uSnoImenT.">
    <meta name="theme-color" content="#09090b">
    <title>sowwwl.xyz — O.</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/styles.css">
    <script defer src="/main.js"></script>
</head>
<body class="experience">
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>

<main class="layout">
    <header class="hero reveal">
        <p class="eyebrow">sowwwl.xyz</p>
        <h1><span>Just the Three of Us</span> <em>O.n0uSnoImenT</em></h1>
        <p class="lead">
            Un espace vivant, personnel, discret. Pose ta terre, choisis ton rythme,
            et laisse la nuit coder le reste.
        </p>
    </header>

    <section class="panel reveal" aria-labelledby="install-title">
        <h2 id="install-title">Poser une terre</h2>
        <p class="panel-copy">Crée ton point d’ancrage. On te redirige ensuite vers ton espace.</p>

        <?php if ($message): ?>
            <div class="flash flash-<?= htmlspecialchars($messageType) ?>">
                <p><?= htmlspecialchars($message) ?></p>
                <?php if ($nextUrl): ?>
                    <a class="next-link" href="<?= htmlspecialchars($nextUrl) ?>">Aller chez vous</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="land-form" autocomplete="off">
            <label>
                Nom d’usage
                <input type="text" name="username" placeholder="ex: nox" required minlength="2" maxlength="42">
            </label>

            <label>
                Fuseau horaire
                <input type="text" name="timezone" placeholder="ex: Europe/Paris" required>
            </label>

            <button type="submit">Entrer dans O.</button>
        </form>
    </section>

    <section class="panel reveal split" aria-labelledby="signals-title">
        <div>
            <h2 id="signals-title">Signal vivant</h2>
            <p class="panel-copy">Prévisualisation locale de ton temps selon le fuseau saisi.</p>
        </div>
        <div class="clock" aria-live="polite">
            <p id="tz-label">Fuseau : —</p>
            <p id="tz-time">--:--:--</p>
        </div>
    </section>
</main>
</body>
</html>
