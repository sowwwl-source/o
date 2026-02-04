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
    'title' => 'Qu’est-ce que O. ?',
    'file' => __DIR__ . '/aza_portals/02-projet.html',
  ],
  3 => [
    'domain' => 'VALEURS',
    'title' => 'Valeurs et refus',
    'file' => __DIR__ . '/aza_portals/03-valeurs.html',
  ],
  4 => [
    'domain' => 'DEMARCHE',
    'title' => 'Comment avancer',
    'file' => __DIR__ . '/aza_portals/04-demarche.html',
  ],
  5 => [
    'domain' => 'PACTE',
    'title' => 'Entrer (sceller)',
    'file' => __DIR__ . '/aza_portals/05-pacte.html',
  ],
];

function asset_url(string $path): string
{
  $path = '/' . ltrim($path, '/');
  $qpos = strpos($path, '?');
  $clean = $qpos === false ? $path : substr($path, 0, $qpos);

  $fs_path = __DIR__ . $clean;
  if (!is_file($fs_path)) return $path;

  $mtime = @filemtime($fs_path);
  if (!$mtime) return $path;

  $sep = $qpos === false ? '?' : '&';
  return $path . $sep . 'v=' . $mtime;
}

function render_portal_content(array $portals, int $portal): void
{
  if ($portal <= 0) {
    echo "<h2>Seuil</h2>";
    echo "<p>Il n’y a pas de menu. Il y a un ordre.</p>";
    echo "<p>Ouvre les portails, un par un. Le reste viendra.</p>";
    return;
  }

  $p = $portals[$portal] ?? null;
  if (!$p) {
    echo "<h2>Inconnu</h2>";
    echo "<p>Ce portail n’existe pas.</p>";
    return;
  }

  if (!is_file($p['file'])) {
    echo "<h2>" . htmlspecialchars($p['title']) . "</h2>";
    echo "<p>Contenu à venir.</p>";
    return;
  }

  readfile($p['file']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AZA — O.</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/aza-entry.css')) ?>">
  <script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
  <script src="<?= htmlspecialchars(asset_url('/aza-portals.js')) ?>" defer></script>
</head>
<body
  class="o-signal<?= $o_parity === '1' ? ' o-inverted' : '' ?>"
  data-o-parity="<?= htmlspecialchars($o_parity) ?>"
  data-aza-count="5"
  data-aza-portal="<?= htmlspecialchars((string)$portal) ?>"
>
  <main class="hero">
    <h1>AZA</h1>
    <p class="lede">
      Entrée. Salle des machines.<br>
      Il faut ouvrir cinq portails — dans l’ordre.
    </p>

    <div class="portals" role="list" aria-label="Portails">
      <?php foreach ($portals as $i => $p): ?>
        <?php $active = ($portal === $i); ?>
        <a
          class="portal<?= $active ? ' is-active' : '' ?>"
          href="/aza/<?= (int)$i ?>"
          data-aza-portal="<?= (int)$i ?>"
          data-o-layer
        >
          <div>
            <div class="p-num"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></div>
          </div>
          <div>
            <div class="p-domain"><?= htmlspecialchars($p['domain']) ?></div>
            <div class="p-title"><?= htmlspecialchars($p['title']) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <section class="viewer" aria-live="polite" aria-label="Lecture">
      <div class="viewer-inner">
        <?php render_portal_content($portals, $portal); ?>
      </div>
    </section>

    <div class="footer">
      <a class="pill" href="/install" data-o-layer>S’installer</a>
      <a class="pill" href="/land" data-o-layer>Aller à LAND</a>
      <a class="pill" href="/land#aza" data-aza-requires="complete" data-o-layer>Entrer en machine</a>
    </div>
  </main>
</body>
</html>
