<?php
// Interface admin c0nsOwwwl : gestion des documents d'identité
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config.php';

session_start();
$admin_pass = getenv('ADMIN_CONSOWWWL_PASS') ?: 'changeme';

// Auth simple (session)
if (isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === $admin_pass) {
        $_SESSION['c0nsOwwwl'] = true;
        header('Location: c0nsOwwwl.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect';
        // Log tentative échouée
        $log = sprintf("[%s] REFUSED admin login from %s | UA: %s\n", date('c'), $_SERVER['REMOTE_ADDR'] ?? '-', $_SERVER['HTTP_USER_AGENT'] ?? '-');
        file_put_contents(__DIR__ . '/../logs/security.log', $log, FILE_APPEND);
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['c0nsOwwwl']);
    header('Location: c0nsOwwwl.php');
    exit;
}
if (empty($_SESSION['c0nsOwwwl'])) {
    // Log accès refusé (hors POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $log = sprintf("[%s] REFUSED admin access from %s | UA: %s\n", date('c'), $_SERVER['REMOTE_ADDR'] ?? '-', $_SERVER['HTTP_USER_AGENT'] ?? '-');
        file_put_contents(__DIR__ . '/../logs/security.log', $log, FILE_APPEND);
    }
    echo '<form method="post"><h2>Admin c0nsOwwwl</h2><input type="password" name="admin_pass" placeholder="Mot de passe admin"><button type="submit">Connexion</button>';
    if (!empty($error)) echo '<p style="color:red">' . htmlspecialchars($error) . '</p>';
    echo '</form>';
    exit;
}

$pdo = $GLOBALS['pdo'];

// Validation/refus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'], $_POST['action'])) {
    $doc_id = intval($_POST['doc_id']);
    if ($_POST['action'] === 'approve') {
        $pdo->prepare('UPDATE identity_documents SET status="approved", validated_at=NOW(), rejected_reason=NULL WHERE id=?')->execute([$doc_id]);
    } elseif ($_POST['action'] === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $pdo->prepare('UPDATE identity_documents SET status="rejected", rejected_reason=? WHERE id=?')->execute([$reason, $doc_id]);
    }
    header('Location: c0nsOwwwl.php');
    exit;
}

// Liste des documents en attente
$docs = $pdo->query('SELECT d.*, l.username, l.email_virtual FROM identity_documents d JOIN lands l ON d.user_id=l.id WHERE d.status="pending" ORDER BY d.uploaded_at DESC')->fetchAll(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>c0nsOwwwl - Admin identité</title>
    <style>body{font-family:sans-serif;background:#111;color:#eee}table{width:100%;border-collapse:collapse}th,td{border:1px solid #333;padding:8px}th{background:#222}tr:nth-child(even){background:#181818}input,button{font-size:1em}</style>
</head>
<body>
<h1>c0nsOwwwl - Admin identité</h1>
<a href="?logout=1">Déconnexion</a>
<table>
    <tr><th>ID</th><th>User</th><th>Email</th><th>Type</th><th>Fichier</th><th>Date</th><th>Action</th></tr>
    <?php foreach($docs as $doc): ?>
    <tr>
        <td><?= (int)$doc['id'] ?></td>
        <td><?= htmlspecialchars($doc['username']) ?></td>
        <td><?= htmlspecialchars($doc['email_virtual']) ?></td>
        <td><?= htmlspecialchars($doc['doc_type']) ?></td>
        <td><a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">Voir</a></td>
        <td><?= htmlspecialchars($doc['uploaded_at']) ?></td>
        <td>
            <form method="post" style="display:inline">
                <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                <button name="action" value="approve">Valider</button>
            </form>
            <form method="post" style="display:inline">
                <input type="hidden" name="doc_id" value="<?= (int)$doc['id'] ?>">
                <input type="text" name="reason" placeholder="Motif refus" required>
                <button name="action" value="reject">Refuser</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
