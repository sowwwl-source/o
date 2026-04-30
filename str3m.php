<?php
require __DIR__ . '/config.php';

start_secure_session();

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── All lands (for the full sphere) ──────────────────────────────────────────
$stmt = $pdo->prepare("SELECT username, shore_text, timezone FROM lands WHERE username != ? ORDER BY created_at DESC");
$stmt->execute([$username]);
$all_lands = $stmt->fetchAll();

// ── Liaison states + port slugs ───────────────────────────────────────────────
$liaisons_map = [];
try {
    $stmtL = $pdo->prepare("
        SELECT l.id, l.land_a, l.land_b, l.status, p.slug AS port_slug
        FROM liaisons l
        LEFT JOIN ports p ON p.liaison_id = l.id
        WHERE l.land_a = ? OR l.land_b = ?
    ");
    $stmtL->execute([$username, $username]);
    foreach ($stmtL->fetchAll() as $row) {
        $other = $row['land_a'] === $username ? $row['land_b'] : $row['land_a'];
        $liaisons_map[$other] = [
            'id'        => (int)$row['id'],
            'status'    => $row['status'],
            'is_sender' => $row['land_a'] === $username,
            'port_slug' => $row['port_slug'],
        ];
    }
} catch (\Exception $e) {}

// ── c0re ZIP files per liaisOn land ──────────────────────────────────────────
// For each active liaison, collect ZIP files uploaded by the other land.
$zips_map = []; // username → [{name, url}]
try {
    $on_ports = array_filter($liaisons_map, fn($l) => $l['status'] === 'on' && $l['port_slug']);
    if (!empty($on_ports)) {
        $port_slugs = array_column(array_values($on_ports), 'port_slug');
        $in = implode(',', array_fill(0, count($port_slugs), '?'));
        $stmtZ = $pdo->prepare("
            SELECT pf.original_name, pf.stored_name, pf.uploaded_by, p.id AS port_id
            FROM port_files pf
            JOIN ports p ON p.id = pf.port_id
            WHERE p.slug IN ($in)
            ORDER BY pf.created_at DESC
        ");
        $stmtZ->execute($port_slugs);
        foreach ($stmtZ->fetchAll() as $z) {
            $by = $z['uploaded_by'];
            if ($by === $username) continue; // own uploads — show under other land
            if (!isset($zips_map[$by])) $zips_map[$by] = [];
            $zips_map[$by][] = [
                'name' => $z['original_name'],
                'url'  => 'uploads/ports/' . $z['port_id'] . '/' . $z['stored_name'],
            ];
        }
    }
} catch (\Exception $e) {}

// ── Saved fl0ws ───────────────────────────────────────────────────────────────
$saved_flows = [];
try {
    $stmtF = $pdo->prepare("
        SELECT f.slug, f.name,
               GROUP_CONCAT(fs.land_username ORDER BY fs.position SEPARATOR ',') AS steps_csv
        FROM flows f
        JOIN flow_steps fs ON fs.flow_id = f.id
        WHERE f.username = ?
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ");
    $stmtF->execute([$username]);
    foreach ($stmtF->fetchAll() as $row) {
        $saved_flows[] = [
            'slug'  => $row['slug'],
            'name'  => $row['name'] ?? '',
            'steps' => explode(',', $row['steps_csv']),
        ];
    }
} catch (\Exception $e) {}

$count              = count($all_lands);
$lands_json         = json_encode($all_lands,   JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$liaisons_json      = json_encode($liaisons_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$zips_json          = json_encode($zips_map,     JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$saved_flows_json   = json_encode($saved_flows,  JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$csrf_json          = json_encode($csrf_token);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<title>STR3M — O.</title>
<style>
html, body { margin: 0; overflow: hidden; width: 100vw; height: 100vh; }
#str3m-canvas { display: block; width: 100%; height: 100%; cursor: grab; }
#str3m-canvas:active { cursor: grabbing; }

/* ── HUD ── */
#str3m-hud {
  position: fixed; top: 1.5rem; left: 1.5rem;
  pointer-events: none; user-select: none;
}
#str3m-hud h1 { font-size: 1.4rem; margin: 0 0 0.15rem; letter-spacing: 0.18em; text-shadow: 0 0 0.8rem rgba(var(--o-fg-rgb)/.28); }
.str3m-meta { font-size: 0.72rem; letter-spacing: 0.18em; opacity: 0.45; text-transform: uppercase; }

#str3m-back {
  position: fixed; top: 1.5rem; right: 1.5rem;
  font-size: 0.78rem; letter-spacing: 0.14em; opacity: 0.55;
  padding: 0.4rem 0.75rem; border: 1px solid var(--o-line); border-radius: 3px;
  background: rgba(var(--o-bg-rgb)/.72); backdrop-filter: blur(4px);
  text-decoration: none; color: inherit; transition: opacity 140ms, background 140ms;
}
#str3m-back:hover { opacity: 1; background: var(--o-fg); color: var(--o-bg); border-color: transparent; }

/* ── fl0w mode button ── */
#flow-mode-btn {
  position: fixed; bottom: 1.5rem; right: 1.5rem;
  font-size: 0.78rem; letter-spacing: 0.2em; padding: 0.45rem 0.9rem;
  border: 1px solid var(--o-line); border-radius: 3px;
  background: rgba(var(--o-bg-rgb)/.72); backdrop-filter: blur(4px);
  color: inherit; cursor: pointer; text-transform: uppercase;
  transition: background 140ms, color 140ms, border-color 140ms;
}
#flow-mode-btn:hover, #flow-mode-btn.active {
  background: var(--o-fg); color: var(--o-bg); border-color: transparent;
}

/* ── fl0w build panel ── */
#flow-build-panel {
  position: fixed; top: 1.5rem; left: 50%; transform: translateX(-50%);
  width: min(360px, calc(100vw - 3rem));
  background: rgba(var(--o-bg-rgb)/.93); backdrop-filter: blur(10px);
  border: 1px solid var(--o-line); border-radius: 6px; padding: 1rem 1.1rem 1.1rem;
  box-shadow: 0 12px 40px rgba(var(--o-fg-rgb)/.12);
  z-index: 200; transition: opacity 200ms;
}
#flow-build-panel.is-hidden { opacity: 0; pointer-events: none; }
.fbp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.6rem; }
.fbp-title { font-size: 0.82rem; letter-spacing: 0.2em; text-transform: uppercase; font-weight: bold; }
#flow-build-close { background: none; border: none; cursor: pointer; opacity: 0.4; font-size: 1.05rem; padding: 0; color: inherit; }
#flow-build-close:hover { opacity: 1; background: none; }
.fbp-hint { font-size: 0.74rem; opacity: 0.5; margin: 0 0 0.75rem; letter-spacing: 0.05em; }
#flow-steps-list { list-style: none; padding: 0; margin: 0 0 0.75rem; max-height: 180px; overflow-y: auto; }
#flow-steps-list li {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.3rem 0; border-bottom: 1px solid var(--o-line);
  font-size: 0.83rem; letter-spacing: 0.05em;
}
#flow-steps-list li .step-num { opacity: 0.45; margin-right: 0.4rem; font-size: 0.72rem; }
#flow-steps-list li button { background: none; border: none; cursor: pointer; opacity: 0.35; font-size: 0.9rem; padding: 0; color: inherit; }
#flow-steps-list li button:hover { opacity: 1; background: none; }
.fbp-empty { font-size: 0.78rem; opacity: 0.4; padding: 0.4rem 0; }
#flow-name-input {
  width: 100%; padding: 0.5rem 0.6rem; font-size: 0.83rem; margin-bottom: 0.65rem;
  background: var(--o-fill); border: 1px solid var(--o-line); color: inherit;
  font-family: inherit; box-sizing: border-box; border-radius: 3px;
}
.fbp-actions { display: flex; gap: 0.5rem; }
.fbp-actions button { flex: 1; padding: 0.45rem 0.6rem; font-size: 0.78rem; letter-spacing: 0.1em; }
#flow-save-btn:disabled, #flow-launch-btn:disabled { opacity: 0.35; cursor: default; }
.fbp-saved { margin-top: 0.85rem; border-top: 1px solid var(--o-line); padding-top: 0.75rem; }
.fbp-saved-title { font-size: 0.7rem; letter-spacing: 0.18em; opacity: 0.5; text-transform: uppercase; margin-bottom: 0.4rem; }
.fbp-saved-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.28rem 0; font-size: 0.8rem;
}
.fbp-saved-item button { background: none; border: none; cursor: pointer; font-size: 0.75rem; color: inherit; padding: 0.15rem 0.45rem; border: 1px solid var(--o-line); border-radius: 2px; }
.fbp-saved-item .del-btn { opacity: 0.3; margin-left: 0.3rem; border: none; padding: 0; font-size: 0.85rem; }
.fbp-saved-item .del-btn:hover { opacity: 0.9; }

/* ── Tour controls (bottom bar) ── */
#flow-tour-controls {
  position: fixed; bottom: 0; left: 0; right: 0;
  max-width: 520px; margin: 0 auto;
  background: rgba(var(--o-bg-rgb)/.93); backdrop-filter: blur(10px);
  border-top: 1px solid var(--o-line);
  padding: 0.85rem 1.25rem;
  display: flex; align-items: center; gap: 0.75rem;
  z-index: 150;
  transition: transform 200ms;
}
#flow-tour-controls.is-hidden { transform: translateY(110%); pointer-events: none; }
#flow-prev-btn, #flow-next-btn {
  padding: 0.35rem 0.7rem; font-size: 1rem;
  flex-shrink: 0;
}
#flow-prev-btn:disabled, #flow-next-btn:disabled { opacity: 0.25; cursor: default; }
.flow-tour-center { flex: 1; min-width: 0; }
#flow-tour-land { font-size: 0.9rem; font-weight: bold; letter-spacing: 0.08em; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#flow-tour-progress { font-size: 0.7rem; letter-spacing: 0.18em; opacity: 0.45; }
#flow-tour-exit { padding: 0.3rem 0.6rem; font-size: 0.85rem; opacity: 0.5; flex-shrink: 0; }
#flow-tour-exit:hover { opacity: 1; }

/* ── Land panel ── */
#str3m-panel {
  position: fixed; bottom: 0; left: 0; right: 0; max-width: 600px; margin: 0 auto;
  background: rgba(var(--o-bg-rgb)/.93); backdrop-filter: blur(10px);
  border-top: 1px solid var(--o-line);
  padding: 1.2rem 1.5rem 1.6rem;
  box-shadow: 0 -12px 40px rgba(var(--o-fg-rgb)/.08);
  transition: transform 220ms ease;
  z-index: 100;
}
#str3m-panel.is-hidden { transform: translateY(110%); pointer-events: none; }
.sp-header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 0.4rem; }
.sp-username { font-size: 1.05rem; letter-spacing: 0.1em; font-weight: bold; }
#str3m-close { font-size: 1.1rem; opacity: 0.4; cursor: pointer; background: none; border: none; padding: 0; color: inherit; }
#str3m-close:hover { opacity: 1; background: none; }
.sp-shore { font-size: 0.86rem; opacity: 0.7; line-height: 1.55; max-height: 4rem; overflow: hidden; margin-bottom: 0.75rem; }

/* ZIP list in panel */
.sp-zips { margin: 0.5rem 0 0.75rem; }
.sp-zips-title { font-size: 0.7rem; letter-spacing: 0.18em; opacity: 0.45; text-transform: uppercase; margin-bottom: 0.3rem; }
.sp-zip-link { display: inline-block; font-size: 0.75rem; padding: 0.2rem 0.55rem; border: 1px solid var(--o-line); border-radius: 2px; margin: 0.15rem 0.2rem 0 0; text-decoration: none; color: inherit; }
.sp-zip-link:hover { background: var(--o-fg); color: var(--o-bg); border-color: transparent; }

.sp-actions { display: flex; gap: 0.55rem; flex-wrap: wrap; align-items: center; }
.sp-actions a, .sp-actions button {
  font-size: 0.76rem; letter-spacing: 0.12em;
  padding: 0.35rem 0.7rem; border: 1px solid var(--o-line); border-radius: 3px;
  text-decoration: none; color: inherit; background: transparent;
  cursor: pointer; font-family: inherit;
  transition: background 140ms, color 140ms, border-color 140ms;
}
.sp-actions a:hover, .sp-actions button:hover { background: var(--o-fg); color: var(--o-bg); border-color: transparent; }
.sp-status { font-size: 0.72rem; letter-spacing: 0.14em; opacity: 0.5; text-transform: uppercase; }

/* ── Hint ── */
#str3m-hint {
  position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
  font-size: 0.7rem; letter-spacing: 0.18em; opacity: 0.32; text-transform: uppercase;
  pointer-events: none; transition: opacity 600ms; white-space: nowrap;
}
#str3m-hint.is-hidden { opacity: 0; }

/* ── Empty ── */
#str3m-empty {
  position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
  text-align: center; opacity: 0.38; pointer-events: none;
  font-size: 0.82rem; letter-spacing: 0.18em; text-transform: uppercase;
}
</style>
</head>
<body>

<canvas id="str3m-canvas"></canvas>

<div id="str3m-hud">
  <h1>SIGNAL</h1>
  <div class="str3m-meta"><?= $count ?> terre<?= $count !== 1 ? 's' : '' ?></div>
</div>

<a id="str3m-back" href="land.php">← terre</a>

<?php if ($count === 0): ?>
  <div id="str3m-empty">Aucune autre terre.</div>
<?php else: ?>
  <div id="str3m-hint">Glisse · clique une terre · fl0w</div>
<?php endif; ?>

<!-- ── fl0w build panel ── -->
<div id="flow-build-panel" class="is-hidden">
  <div class="fbp-header">
    <span class="fbp-title">FL0W</span>
    <button id="flow-build-close">×</button>
  </div>
  <p class="fbp-hint">Clique les terres dans l'ordre de la visite.</p>
  <ol id="flow-steps-list"><li class="fbp-empty">Aucune terre sélectionnée.</li></ol>
  <input id="flow-name-input" type="text" placeholder="Nommer ce fl0w..." maxlength="80">
  <div class="fbp-actions">
    <button id="flow-save-btn" disabled>Sauvegarder</button>
    <button id="flow-launch-btn" disabled>Lancer →</button>
  </div>

  <?php if (!empty($saved_flows)): ?>
  <div class="fbp-saved">
    <div class="fbp-saved-title">Fl0ws sauvegardés</div>
    <?php foreach ($saved_flows as $sf): ?>
      <div class="fbp-saved-item" data-slug="<?= htmlspecialchars($sf['slug']) ?>">
        <span><?= htmlspecialchars($sf['name'] ?: implode(' → ', array_slice($sf['steps'], 0, 3)) . (count($sf['steps']) > 3 ? '…' : '')) ?></span>
        <span>
          <button class="play-saved-btn">▶</button>
          <button class="del-btn">×</button>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Tour controls ── -->
<div id="flow-tour-controls" class="is-hidden">
  <button id="flow-prev-btn">←</button>
  <div class="flow-tour-center">
    <span id="flow-tour-land"></span>
    <span id="flow-tour-progress"></span>
  </div>
  <button id="flow-next-btn">→</button>
  <button id="flow-tour-exit">×</button>
</div>

<!-- ── Land panel ── -->
<div id="str3m-panel" class="is-hidden">
  <div class="sp-header">
    <span class="sp-username"></span>
    <button id="str3m-close" aria-label="Fermer">×</button>
  </div>
  <p class="sp-shore"></p>
  <div class="sp-zips" id="sp-zips" style="display:none">
    <div class="sp-zips-title">c0re</div>
    <div id="sp-zips-list"></div>
  </div>
  <div class="sp-actions">
    <span class="sp-status" id="sp-status"></span>
    <form method="post" action="toc.php" id="sp-toc-form" style="display:none">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="action" value="send">
      <input type="hidden" name="target" id="sp-toc-target">
      <button type="submit">t0c</button>
    </form>
    <a id="sp-echo-link" href="#" style="display:none">écho →</a>
    <a id="sp-port-link" href="#" style="display:none">→ p0rt</a>
  </div>
</div>

<button id="flow-mode-btn">fl0w</button>

<script>
window.STR3M_LANDS    = <?= $lands_json ?>;
window.STR3M_LIAISONS = <?= $liaisons_json ?>;
window.STR3M_ZIPS     = <?= $zips_json ?>;
window.STR3M_FLOWS    = <?= $saved_flows_json ?>;
window.STR3M_CSRF     = <?= $csrf_json ?>;
window.STR3M_ME       = <?= json_encode($username) ?>;
</script>
<script src="<?= htmlspecialchars(asset_url('/str3m.js')) ?>" defer></script>

</body>
</html>
