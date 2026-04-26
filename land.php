<?php
require __DIR__ . '/config.php';

start_secure_session();

/* ===============================
   CONFIG / DB
   =============================== */

/* ===============================
   AUTH / SESSION
   =============================== */

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$aza_message = '';
$aza_result = null;
$aza_action = '';
$shore_message = '';
$shore_success = false;

function parse_csv_list(string $value): array
{
    $items = array_map('trim', explode(',', $value));
    return array_values(array_filter($items, static fn($item) => $item !== ''));
}

function decode_json_field(string $value, string $label, string &$error): ?array
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = "JSON invalide pour {$label}.";
        return null;
    }

    return $decoded;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shore'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $shore_message = "Session expirée. Réessaie.";
    } else {
        $shore_text = trim($_POST['shore_text'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE lands SET shore_text = :shore_text WHERE username = :username");
            $stmt->execute([
                ':shore_text' => $shore_text,
                ':username' => $username
            ]);
            $shore_success = true;
            $shore_message = "SHORE mis à jour.";
        } catch (PDOException $e) {
            $shore_message = "Erreur lors de la mise à jour.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aza_action'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $aza_message = "Session expirée. Réessaie.";
    } else {
        $aza_action = $_POST['aza_action'] ?? '';
        $error = '';

        if ($aza_action === 'user_score') {
            $delta = (float)($_POST['delta'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $events = decode_json_field($_POST['events'] ?? '', 'events', $error);

            if ($reason === '') {
                $error = "Raison requise.";
            } elseif ($error === '') {
                $payload = [
                    'user_id' => $username,
                    'delta' => $delta,
                    'reason' => $reason,
                ];
                if ($events !== null) {
                    $payload['events'] = $events;
                }

                $aza_result = aza_api_request('/v1/user/score', $payload);
            }
        } elseif ($aza_action === 'voice_render') {
            $text = trim($_POST['text'] ?? '');
            $voice_state = $_POST['voice_state'] ?? 'O';
            $format = $_POST['format'] ?? 'mp3';
            $oscillation = (float)($_POST['oscillation'] ?? 0.5);
            $sample_rate = (int)($_POST['sample_rate'] ?? 48000);

            if ($text === '') {
                $error = "Texte requis.";
            } else {
                if (!in_array($voice_state, ['O', '0'], true)) {
                    $voice_state = 'O';
                }
                if (!in_array($format, ['mp3', 'wav'], true)) {
                    $format = 'mp3';
                }

                $aza_result = aza_api_request('/v1/voice/render', [
                    'text' => $text,
                    'voice_state' => $voice_state,
                    'oscillation' => $oscillation,
                    'format' => $format,
                    'sample_rate' => $sample_rate,
                ]);
            }
        } elseif ($aza_action === 'organize_drive') {
            $workspace_id = trim($_POST['workspace_id'] ?? '');
            $root_prefix = trim($_POST['root_prefix'] ?? '');
            $dry_run = isset($_POST['dry_run']);
            $max_actions = (int)($_POST['max_actions'] ?? 100);
            if ($max_actions <= 0) {
                $max_actions = 100;
            }
            $policy = decode_json_field($_POST['policy'] ?? '', 'policy', $error);

            if ($error === '') {
                $payload = [
                    'user_id' => $username,
                    'dry_run' => $dry_run,
                    'max_actions' => $max_actions,
                ];
                if ($workspace_id !== '') {
                    $payload['workspace_id'] = $workspace_id;
                }
                if ($root_prefix !== '') {
                    $payload['root_prefix'] = $root_prefix;
                }
                if ($policy !== null) {
                    $payload['policy'] = $policy;
                }

                $aza_result = aza_api_request('/v1/organize', $payload);
            }
        } elseif ($aza_action === 'index_object') {
            $object_ids = parse_csv_list($_POST['object_ids'] ?? '');
            $upload_id = trim($_POST['upload_id'] ?? '');
            $max_chunks = (int)($_POST['max_chunks_per_object'] ?? 200);
            if ($max_chunks <= 0) {
                $max_chunks = 200;
            }

            if (empty($object_ids) && $upload_id === '') {
                $error = "Fournis object_ids ou upload_id.";
            } else {
                $payload = [
                    'user_id' => $username,
                    'max_chunks_per_object' => $max_chunks,
                ];
                if (!empty($object_ids)) {
                    $payload['object_ids'] = $object_ids;
                }
                if ($upload_id !== '') {
                    $payload['upload_id'] = $upload_id;
                }

                $aza_result = aza_api_request('/v1/index', $payload);
            }
        } elseif ($aza_action === 'evict_chunks') {
            $to_free_bytes = (int)($_POST['to_free_bytes'] ?? 0);
            $strategy = trim($_POST['strategy'] ?? '');

            if ($to_free_bytes <= 0) {
                $error = "to_free_bytes requis.";
            } else {
                $payload = [
                    'user_id' => $username,
                    'to_free_bytes' => $to_free_bytes,
                ];
                if ($strategy !== '') {
                    $payload['strategy'] = $strategy;
                }

                $aza_result = aza_api_request('/v1/evict', $payload);
            }
        } elseif ($aza_action === 'generate_post') {
            $source_object_ids = parse_csv_list($_POST['source_object_ids'] ?? '');
            $workspace_id = trim($_POST['workspace_id'] ?? '');
            $style_state = $_POST['style_state'] ?? 'O';
            $language = trim($_POST['language'] ?? 'fr');
            $max_per_day = (int)($_POST['max_per_day'] ?? 1);
            if ($max_per_day <= 0) {
                $max_per_day = 1;
            }

            if (empty($source_object_ids)) {
                $error = "source_object_ids requis.";
            } else {
                if (!in_array($style_state, ['O', '0'], true)) {
                    $style_state = 'O';
                }
                if ($language === '') {
                    $language = 'fr';
                }

                $payload = [
                    'user_id' => $username,
                    'source_object_ids' => $source_object_ids,
                    'style_state' => $style_state,
                    'language' => $language,
                    'max_per_day' => $max_per_day,
                ];
                if ($workspace_id !== '') {
                    $payload['workspace_id'] = $workspace_id;
                }

                $aza_result = aza_api_request('/v1/post/generate', $payload);
            }
        } elseif ($aza_action === 'publish_post') {
            $post_id = trim($_POST['post_id'] ?? '');
            $scheduled_at = trim($_POST['scheduled_at'] ?? '');

            if ($post_id === '') {
                $error = "post_id requis.";
            } else {
                $payload = [
                    'post_id' => $post_id,
                ];
                if ($scheduled_at !== '') {
                    $payload['scheduled_at'] = $scheduled_at;
                }

                $aza_result = aza_api_request('/v1/post/publish', $payload);
            }
        } elseif ($aza_action === 'touch_chunks') {
            $chunk_keys = parse_csv_list($_POST['chunk_keys'] ?? '');

            if (empty($chunk_keys)) {
                $error = "chunk_keys requis.";
            } else {
                $aza_result = aza_api_request('/v1/kb/touch', [
                    'user_id' => $username,
                    'chunk_keys' => $chunk_keys,
                ]);
            }
        } elseif ($aza_action === 'upload_file') {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $error = "Fichier requis.";
            } else {
                $workspace_id = trim($_POST['workspace_id'] ?? '');
                $aza_result = aza_api_upload(
                    $_FILES['file']['tmp_name'],
                    $_FILES['file']['name'],
                    $username,
                    $workspace_id !== '' ? $workspace_id : null
                );
            }
        } else {
            $error = "Action inconnue.";
        }

        if ($error !== '') {
            $aza_message = $error;
        } elseif (is_array($aza_result) && isset($aza_result['error']) && $aza_result['error']) {
            $aza_message = $aza_result['error'];
        }
    }
}

/* ===============================
   LAND
   =============================== */

$stmt = $pdo->prepare("SELECT * FROM lands WHERE username = ?");
$stmt->execute([$username]);
$land = $stmt->fetch();

if ($shore_success) {
    $stmt->execute([$username]);
    $land = $stmt->fetch();
}

if (!$land) {
    die('LAND introuvable for ' . htmlspecialchars($username));
}

/* ===============================
   CHALOUPES (placeholder)
   =============================== */

$chaloupes = [
    ['label' => 'SHORE', 'url' => 'shore.php', 'description' => 'Le rivage où tu écris'],
    ['label' => 'BATO', 'url' => 'bato.php', 'description' => 'Le bateau qui tangue'],
    ['label' => 'DASHBOARD', 'url' => 'dashboard.php', 'description' => 'Statistiques et activité'],
    ['label' => 'AZA', 'url' => 'aza.php', 'description' => 'Les portails (entrée machine)'],
    ['label' => 'SILENCE', 'url' => 'silence.php', 'description' => 'Le vide qui respire'],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/global-styles.css')) ?>">
<script src="<?= htmlspecialchars(asset_url('/main.js')) ?>" defer></script>
<title>O.</title>

<style>
/* ===============================
   UX MINIMALE O.
   =============================== */

body {
  max-width: 720px;
  margin: 4rem auto;
  padding: 0 1.5rem;
}

section {
  margin-bottom: 3rem;
}

section:hover {
  background: rgba(var(--o-fg-rgb) / 0.03);
}

h1 {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

h2 {
  font-size: 0.85rem;
  letter-spacing: 0.18em;
  opacity: 0.6;
  margin-bottom: 0.75rem;
}

.land-status {
  font-size: 1.2rem;
  margin-bottom: 0.25rem;
}

.land-meta {
  font-size: 0.85rem;
  opacity: 0.7;
}

.shore p {
  line-height: 1.6;
  opacity: 0.9;
}

.shore form {
  margin-top: 1rem;
}

.shore textarea {
  width: 100%;
  min-height: 120px;
  padding: 0.75rem;
  font-size: 1rem;
  font-family: inherit;
  line-height: 1.6;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  box-sizing: border-box;
  resize: vertical;
}

.shore button {
  margin-top: 0.5rem;
  padding: 0.6rem 1.5rem;
  font-size: 0.9rem;
  background: transparent;
  color: inherit;
  border: 1px solid var(--o-line);
  border-radius: 4px;
  cursor: pointer;
}

.shore button:hover {
  background: var(--o-fg);
  color: var(--o-bg);
  border-color: transparent;
}

.shore .success {
  margin: 0.5rem 0;
  font-size: 0.9rem;
}

.shore .error {
  margin: 0.5rem 0;
  font-size: 0.9rem;
}

.shore .edit-link {
  font-size: 0.85rem;
  opacity: 0.7;
  cursor: pointer;
  text-decoration: underline;
}

.shore .edit-link:hover {
  opacity: 1;
}

.chaloupes ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.chaloupes li {
  margin-bottom: 0.75rem;
}

.chaloupes a {
  display: block;
  padding: 1rem;
  background: var(--o-fill);
  border: 1px solid var(--o-line);
  border-radius: 6px;
  box-shadow: var(--o-shadow);
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
}

.chaloupes a:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 34px rgba(var(--o-fg-rgb) / 0.16);
}

.chaloupes .chaloupe-label {
  font-size: 1rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  margin-bottom: 0.25rem;
}

.chaloupes .chaloupe-desc {
  font-size: 0.85rem;
  opacity: 0.7;
}

.aza-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.aza form {
  margin: 0;
  background: var(--o-fill);
  border: 1px solid var(--o-line);
  padding: 0.9rem;
  border-radius: 8px;
  box-shadow: var(--o-shadow);
}

.aza h3 {
  margin: 0 0 0.4rem;
  font-size: 0.95rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.aza .checkbox {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.9rem;
}

.aza .checkbox input {
  width: auto;
  margin: 0;
}

.aza .hint {
  font-size: 0.8rem;
  opacity: 0.7;
  margin: 0.2rem 0 0.8rem;
}

.aza input,
.aza select,
.aza textarea,
.aza button {
  width: 100%;
  padding: 0.55rem;
  margin: 0.35rem 0;
  font-size: 0.95rem;
  box-sizing: border-box;
}

.aza textarea {
  min-height: 110px;
}

.aza pre {
  background: var(--o-fg);
  color: var(--o-bg);
  padding: 0.75rem;
  border-radius: 6px;
  overflow: auto;
  font-size: 0.85rem;
}

.aza .message {
  padding: 0.75rem 0.9rem;
  border: 1px solid var(--o-line);
  background: var(--o-fill);
  margin: 0.5rem 0 1rem;
}
</style>
</head>

<body>

<!-- ===============================
     LAND
     =============================== -->
<section class="land">
  <h1>0.</h1>
  <p class="land-status">Vous êtes chez vous</p>
  <p class="land-meta">
    Utilisateur : <?= htmlspecialchars($land['username']) ?><br>
    Fuseau horaire : <?= htmlspecialchars($land['timezone']) ?><br>
    Zone : <?= htmlspecialchars($land['zone_code']) ?>
  </p>
  <p class="land-meta"><a href="land.php?logout=1" data-o-nozoom>Se déconnecter</a></p>
</section>

<!-- ===============================
     SHORE
     =============================== -->
<section class="shore">
  <h2>SHORE</h2>
  
  <?php if ($shore_success): ?>
    <p class="success"><?= htmlspecialchars($shore_message) ?></p>
  <?php elseif ($shore_message): ?>
    <p class="error"><?= htmlspecialchars($shore_message) ?></p>
  <?php endif; ?>
  
  <div id="shore-view">
    <p>
      <?= nl2br(htmlspecialchars($land['shore_text'] ?? 'Silence.')) ?>
    </p>
    <p class="edit-link" onclick="toggleShoreEdit(event)">Éditer</p>
  </div>
  
  <form method="post" id="shore-edit" style="display: none;">
    <input type="hidden" name="update_shore" value="1">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <textarea name="shore_text" placeholder="Écrire sur SHORE..."><?= htmlspecialchars($land['shore_text'] ?? '') ?></textarea>
    <button type="submit">Sauvegarder</button>
    <button type="button" onclick="toggleShoreEdit(event)">Annuler</button>
  </form>
</section>

<script>
function toggleShoreEdit(event) {
  if (event) event.stopPropagation();
  const view = document.getElementById('shore-view');
  const edit = document.getElementById('shore-edit');
  if (view.style.display === 'none') {
    view.style.display = 'block';
    edit.style.display = 'none';
  } else {
    view.style.display = 'none';
    edit.style.display = 'block';
  }
}
</script>

<!-- ===============================
     CHALOUPES
     =============================== -->
<section class="chaloupes">
  <h2>CHALOUPES</h2>
  <ul>
    <?php foreach ($chaloupes as $c): ?>
      <li>
        <a href="<?= htmlspecialchars($c['url']) ?>" data-o-layer>
          <div class="chaloupe-label"><?= htmlspecialchars($c['label']) ?></div>
          <div class="chaloupe-desc"><?= htmlspecialchars($c['description']) ?></div>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<!-- ===============================
     AZA API
     =============================== -->
<section class="aza" id="aza">
  <h2>AZA</h2>
  <p class="land-meta">Connexion API via token serveur.</p>
  <p class="hint">Listes séparées par virgules. JSON libre pour policy/events.</p>

  <?php if ($aza_message): ?>
    <p class="message"><?= htmlspecialchars($aza_message) ?></p>
  <?php endif; ?>

  <div class="aza-grid">
    <form method="post">
      <h3>Update score</h3>
      <input type="hidden" name="aza_action" value="user_score">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="number" step="0.01" name="delta" placeholder="Delta (ex: 1.0)" required>
      <input type="text" name="reason" placeholder="Raison" required>
      <textarea name="events" placeholder="Events JSON (optionnel)"></textarea>
      <button type="submit">Envoyer</button>
    </form>

    <form method="post">
      <h3>Voice render</h3>
      <input type="hidden" name="aza_action" value="voice_render">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <textarea name="text" placeholder="Texte à rendre" required></textarea>
      <select name="voice_state">
        <option value="O">O</option>
        <option value="0">0</option>
      </select>
      <input type="number" step="0.01" name="oscillation" placeholder="Oscillation (0-1, défaut 0.5)" value="0.5">
      <select name="format">
        <option value="mp3">mp3</option>
        <option value="wav">wav</option>
      </select>
      <input type="number" name="sample_rate" placeholder="Sample rate (défaut 48000)" value="48000">
      <button type="submit">Rendre</button>
    </form>

    <form method="post">
      <h3>Organize drive</h3>
      <input type="hidden" name="aza_action" value="organize_drive">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="text" name="workspace_id" placeholder="workspace_id (optionnel)">
      <input type="text" name="root_prefix" placeholder="root_prefix (optionnel)">
      <label class="checkbox"><input type="checkbox" name="dry_run" checked> Dry run</label>
      <input type="number" name="max_actions" placeholder="max_actions (défaut 100)" value="100">
      <textarea name="policy" placeholder="Policy JSON (optionnel)"></textarea>
      <button type="submit">Organiser</button>
    </form>

    <form method="post">
      <h3>Index object</h3>
      <input type="hidden" name="aza_action" value="index_object">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="text" name="object_ids" placeholder="object_ids (id1, id2)">
      <input type="text" name="upload_id" placeholder="upload_id (optionnel)">
      <input type="number" name="max_chunks_per_object" placeholder="max_chunks_per_object (défaut 200)" value="200">
      <button type="submit">Indexer</button>
    </form>

    <form method="post">
      <h3>Evict chunks</h3>
      <input type="hidden" name="aza_action" value="evict_chunks">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="number" name="to_free_bytes" placeholder="to_free_bytes" required>
      <input type="text" name="strategy" placeholder="strategy (optionnel)">
      <button type="submit">Evict</button>
    </form>

    <form method="post">
      <h3>Touch chunks</h3>
      <input type="hidden" name="aza_action" value="touch_chunks">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <textarea name="chunk_keys" placeholder="chunk_keys (key1, key2)" required></textarea>
      <button type="submit">Touch</button>
    </form>

    <form method="post">
      <h3>Generate post</h3>
      <input type="hidden" name="aza_action" value="generate_post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="text" name="source_object_ids" placeholder="source_object_ids (id1, id2)" required>
      <input type="text" name="workspace_id" placeholder="workspace_id (optionnel)">
      <select name="style_state">
        <option value="O">O</option>
        <option value="0">0</option>
      </select>
      <input type="text" name="language" placeholder="language (défaut fr)" value="fr">
      <input type="number" name="max_per_day" placeholder="max_per_day (défaut 1)" value="1">
      <button type="submit">Générer</button>
    </form>

    <form method="post">
      <h3>Publish post</h3>
      <input type="hidden" name="aza_action" value="publish_post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="text" name="post_id" placeholder="post_id" required>
      <input type="text" name="scheduled_at" placeholder="scheduled_at ISO 8601 (optionnel)">
      <button type="submit">Publier</button>
    </form>

    <form method="post" enctype="multipart/form-data">
      <h3>Upload file</h3>
      <input type="hidden" name="aza_action" value="upload_file">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="file" name="file" required>
      <input type="text" name="workspace_id" placeholder="workspace_id (optionnel)">
      <button type="submit">Uploader</button>
    </form>
  </div>

  <?php if ($aza_result): ?>
    <pre><?= htmlspecialchars(json_encode([
      'ok' => $aza_result['ok'] ?? false,
      'status' => $aza_result['status'] ?? 0,
      'body' => $aza_result['body'] ?? null,
      'error' => $aza_result['error'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
  <?php endif; ?>
</section>

</body>
</html>
