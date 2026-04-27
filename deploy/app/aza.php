<?php
// AzA entry: guided portals (must be opened in order).
//
// Note: the "layer" illusion is handled by main.js via parity (data-o-parity / body.o-inverted).
// Here we only decide which portal content to render in the viewer.

$portal = (int)($_GET['p'] ?? 0);
if ($portal < 0) $portal = 0;
if ($portal > 5) $portal = 5;

// Palette: alternate on each portal.
// We want portal 2/4 (even) to be inverted (green background, cream text),
// and portal 1/3/5 (odd) to be normal (cream background, green text).
$o_parity = ($portal >= 1 && ($portal % 2 === 0)) ? '1' : '0';

$portals = [
  1 => [
    'domain' => 'IDENTITE',
    'title' => 'Qui es-tu ?',
    'file' => __DIR__ . '/aza_portals/01-qui-es-tu.html',
  ],
  2 => [
    'domain' => 'PROJET',
    'title' => 'Le projet',
    'file' => __DIR__ . '/aza_portals/02-projet.html',
  ],
  3 => [
    'domain' => 'VALEURS',
    'title' => 'Valeurs',
    'file' => __DIR__ . '/aza_portals/03-valeurs.html',
  ],
  4 => [
    'domain' => 'DEMARCHE',
    'title' => 'Démarche',
    'file' => __DIR__ . '/aza_portals/04-demarche.html',
  ],
  5 => [
    'domain' => 'PACTE',
    'title' => 'Pacte',
    'file' => __DIR__ . '/aza_portals/05-pacte.html',
  ],
];

if ($portal === 0) {
  echo "<h1>Bienvenue sur AzA</h1>";
  echo "<ul>";
  foreach ($portals as $i => $p) {
    echo "<li><a href='?p=$i'>{$p['title']}</a></li>";
  }
  echo "</ul>";
  exit;
}

if (!isset($portals[$portal])) {
  http_response_code(404);
  echo "<h1>Portail inconnu</h1>";
  exit;
}

$p = $portals[$portal];
if (!file_exists($p['file'])) {
  http_response_code(404);
  echo "<h1>Contenu manquant</h1>";
  exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($p['title']) ?> - AzA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
    .portal { max-width: 600px; margin: 40px auto; padding: 2em; border-radius: 8px; background: <?= $o_parity === '1' ? '#2e7d32' : '#fffde7' ?>; color: <?= $o_parity === '1' ? '#fffde7' : '#2e7d32' ?>; }
    a { color: inherit; text-decoration: underline; }
  </style>
</head>
<body data-o-parity="<?= $o_parity ?>">
  <div class="portal">
    <h1><?= htmlspecialchars($p['title']) ?></h1>
    <?php include $p['file']; ?>
    <p><a href="?p=<?= $portal - 1 ?>">&larr; Précédent</a> | <a href="?p=<?= $portal + 1 ?>">Suivant &rarr;</a></p>
    <p><a href="?p=0">Retour à l'accueil AzA</a></p>
  </div>
</body>
</html>
