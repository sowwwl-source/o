<?php
require __DIR__ . '/config.php';

start_secure_session();

/* ===============================
   CONFIG / DB
   =============================== */

/* ===============================
   AUTH / SESSION
   =============================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        http_response_code(400);
        die('Session expirée. <a href="/login">Se reconnecter</a>');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /world');
    exit;
}

if (!isset($_SESSION['username'])) {
    die('Non connecté. <a href="/login">S\'inscrire</a>');
}

$username = $_SESSION['username'];

/* ===============================
   LAND (load early for identity)
   =============================== */

$stmt = $pdo->prepare("SELECT * FROM lands WHERE username = ?");
$stmt->execute([$username]);
$land = $stmt->fetch();

if (!$land) {
    die('LAND introuvable for ' . htmlspecialchars($username));
}

$user_id = (int) $land['id'];

$identity_message = '';
$identity_dev_hint = '';

function normalize_phone(string $value): string
{
    $trimmed = trim($value);
    $trimmed = preg_replace('/[^0-9+]/', '', $trimmed);
    return $trimmed ?: '';
}

function load_identity(PDO $pdo, int $user_id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM identity_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ensure_identity(PDO $pdo, int $user_id): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO identity_profiles (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
}

function issue_postal_code(PDO $pdo, int $user_id, array $identity, ?string &$dev_code = null): bool
{
    $code = generate_verification_code(6);
    $hash = hash_verification_code($code);
    $expires = time() + (30 * 24 * 60 * 60);

    $stmt = $pdo->prepare("UPDATE identity_profiles
        SET postal_code_hash = ?, postal_code_sent_at = NOW(), postal_code_expires_at = FROM_UNIXTIME(?)
        WHERE user_id = ?
    ");
    $stmt->execute([$hash, $expires, $user_id]);

    $address = [
        'line1' => $identity['address_line1'] ?? '',
        'line2' => $identity['address_line2'] ?? '',
        'city' => $identity['city'] ?? '',
        'region' => $identity['region'] ?? '',
        'postal_code' => $identity['postal_code'] ?? '',
        'country' => $identity['country'] ?? '',
    ];

    $sent = send_postal_code($address, $code);
    if (!$sent && identity_dev_mode()) {
        $dev_code = $code;
    }

    return $sent;
}

$aza_message = '';
$aza_result = null;
$aza_action = '';

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
   IDENTITY / CLONE FLOW
   =============================== */

$identity = load_identity($pdo, $user_id);
$identity = $identity ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identity_action'])) {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_token)) {
        $identity_message = "Session expirée. Réessaie.";
    } else {
        ensure_identity($pdo, $user_id);
        $identity = load_identity($pdo, $user_id) ?: [];
        $action = $_POST['identity_action'] ?? '';

        if ($action === 'request_phone_code') {
            $phone = normalize_phone($_POST['phone'] ?? '');
            if ($phone === '') {
                $identity_message = "Numéro invalide.";
            } else {
                $code = generate_verification_code(6);
                $hash = hash_verification_code($code);
                $expires = time() + 600;

                $stmt = $pdo->prepare("UPDATE identity_profiles SET phone_e164 = ? WHERE user_id = ?");
                $stmt->execute([$phone, $user_id]);

                $stmt = $pdo->prepare("INSERT INTO identity_phone_otps (user_id, phone_e164, code_hash, expires_at)
                    VALUES (?, ?, ?, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$user_id, $phone, $hash, $expires]);

                $sent = send_sms_code($phone, $code);
                if ($sent) {
                    $identity_message = "Code envoyé.";
                } else {
                    $identity_message = "Code généré. Envoi SMS en attente de configuration.";
                    if (identity_dev_mode()) {
                        $identity_dev_hint = "Code SMS (DEV) : {$code}";
                    }
                }
            }
        } elseif ($action === 'verify_phone_code') {
            $code = trim($_POST['phone_code'] ?? '');
            if ($code === '') {
                $identity_message = "Code requis.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM identity_phone_otps
                    WHERE user_id = ? AND expires_at > NOW()
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $otp = $stmt->fetch();
                if (!$otp) {
                    $identity_message = "Code expiré ou introuvable.";
                } elseif (!verify_verification_code($code, $otp['code_hash'])) {
                    $stmt = $pdo->prepare("UPDATE identity_phone_otps SET attempts = attempts + 1 WHERE id = ?");
                    $stmt->execute([$otp['id']]);
                    $identity_message = "Code invalide.";
                } else {
                    $stmt = $pdo->prepare("UPDATE identity_profiles SET phone_verified_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $stmt = $pdo->prepare("DELETE FROM identity_phone_otps WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $identity_message = "Téléphone validé.";
                }
            }
        } elseif ($action === 'submit_address') {
            if (empty($identity['phone_verified_at'])) {
                $identity_message = "Valide le téléphone avant l'adresse.";
            } else {
                $line1 = trim($_POST['address_line1'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $postal = trim($_POST['postal_code'] ?? '');
                $country = strtoupper(trim($_POST['country'] ?? ''));
                if ($line1 === '' || $city === '' || $postal === '' || $country === '') {
                    $identity_message = "Adresse incomplète.";
                } elseif (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
                    $identity_message = "Justificatif requis.";
                } else {
                    $file = $_FILES['proof_file'];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowed = [
                        'application/pdf' => 'pdf',
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png'
                    ];
                    if (!isset($allowed[$mime])) {
                        $identity_message = "Format invalide (pdf/jpg/png).";
                    } else {
                        $dir = __DIR__ . '/identity_uploads';
                        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                            $identity_message = "Erreur stockage justificatif.";
                        } else {
                            $filename = 'proof_' . $user_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$mime];
                            $target = $dir . '/' . $filename;
                            if (!move_uploaded_file($file['tmp_name'], $target)) {
                                $identity_message = "Upload échoué.";
                            } else {
                                $stmt = $pdo->prepare("UPDATE identity_profiles
                                    SET address_line1 = ?, address_line2 = ?, city = ?, region = ?, postal_code = ?, country = ?,
                                        proof_file = ?, proof_status = 'pending'
                                    WHERE user_id = ?
                                ");
                                $stmt->execute([
                                    $line1,
                                    trim($_POST['address_line2'] ?? ''),
                                    $city,
                                    trim($_POST['region'] ?? ''),
                                    $postal,
                                    $country,
                                    'identity_uploads/' . $filename,
                                    $user_id
                                ]);

                                if (identity_auto_approve()) {
                                    $stmt = $pdo->prepare("UPDATE identity_profiles
                                        SET proof_status = 'approved', address_verified_at = NOW()
                                        WHERE user_id = ?
                                    ");
                                    $stmt->execute([$user_id]);
                                    $identity = load_identity($pdo, $user_id) ?: [];
                                    $sent = issue_postal_code($pdo, $user_id, $identity, $identity_dev_hint);
                                    $identity_message = $sent
                                        ? "Adresse validée. Courrier envoyé."
                                        : "Adresse validée. Envoi courrier en attente de configuration.";
                                } else {
                                    $identity_message = "Justificatif reçu. Vérification en cours.";
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($action === 'resend_postal_code') {
            if (empty($identity['address_verified_at'])) {
                $identity_message = "Adresse non validée.";
            } else {
                $identity = load_identity($pdo, $user_id) ?: [];
                $sent = issue_postal_code($pdo, $user_id, $identity, $identity_dev_hint);
                $identity_message = $sent
                    ? "Courrier relancé."
                    : "Courrier relancé (configuration manquante).";
            }
        } elseif ($action === 'verify_postal_code') {
            $code = trim($_POST['postal_code'] ?? '');
            if ($code === '') {
                $identity_message = "Code courrier requis.";
            } else {
                $identity = load_identity($pdo, $user_id) ?: [];
                if (empty($identity['postal_code_hash']) || empty($identity['postal_code_expires_at'])) {
                    $identity_message = "Aucun code actif.";
                } else {
                    $expires = strtotime($identity['postal_code_expires_at']);
                    if ($expires !== false && $expires < time()) {
                        $identity_message = "Code expiré.";
                    } elseif (!verify_verification_code($code, $identity['postal_code_hash'])) {
                        $identity_message = "Code invalide.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE identity_profiles
                            SET postal_verified_at = NOW(), clone_activated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user_id]);
                        $identity_message = "Identité validée. Clone activé.";
                    }
                }
            }
        } else {
            $identity_message = "Action inconnue.";
        }
    }

    $identity = load_identity($pdo, $user_id);
}

/* ===============================
   CHALOUPES (placeholder)
   =============================== */

$chaloupes = [
    ['label' => '— vers SHORE'],
    ['label' => '— vers BATO'],
    ['label' => '— silence'],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>O.</title>
<link rel="stylesheet" href="/styles.css">
<script src="/main.js" defer></script>
</head>

<body class="AeiouuoieA land-page">

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
  <div class="land-meta">
    <form method="post" class="logout-form">
      <input type="hidden" name="logout" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <button type="submit" class="logout-btn">Se déconnecter</button>
    </form>
    <div style="margin-top: 1rem;">
      <a href="/feed">Accéder au Flux</a>
    </div>
  </div>
</section>

<!-- ===============================
     SHORE
     =============================== -->
<section class="shore">
  <h2>SHORE</h2>
  <p>
    <?= nl2br(htmlspecialchars($land['shore_text'] ?? 'Silence.')) ?>
  </p>
</section>

<!-- ===============================
     CHALOUPES
     =============================== -->
<section class="chaloupes">
  <h2>CHALOUPES</h2>
  <ul>
    <?php foreach ($chaloupes as $c): ?>
      <li><?= htmlspecialchars($c['label']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<!-- ===============================
         IDENTITÉ / CLONE
         =============================== -->
<section class="identity">
    <h2>IDENTITÉ / CLONE</h2>
    <p class="land-meta">
        Téléphone : <?= !empty($identity['phone_verified_at']) ? 'validé' : 'non validé' ?> ·
        Adresse : <?= !empty($identity['address_verified_at']) ? 'validée' : ($identity['proof_status'] ?? '') ?> ·
        Courrier : <?= !empty($identity['postal_verified_at']) ? 'validé' : (!empty($identity['postal_code_sent_at']) ? 'envoyé' : 'non envoyé') ?> ·
        Clone : <?= !empty($identity['clone_activated_at']) ? 'actif' : 'inactif' ?>
    </p>

    <?php if ($identity_message): ?>
        <p class="message"><?= htmlspecialchars($identity_message) ?></p>
    <?php endif; ?>
    <?php if ($identity_dev_hint): ?>
        <p class="message"><?= htmlspecialchars($identity_dev_hint) ?></p>
    <?php endif; ?>

    <div class="aza-grid">
        <form method="post">
            <h3>Téléphone</h3>
            <input type="hidden" name="identity_action" value="request_phone_code">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="phone" placeholder="+33612345678" required>
            <button type="submit">Recevoir un code</button>
        </form>

        <form method="post">
            <h3>Valider le code</h3>
            <input type="hidden" name="identity_action" value="verify_phone_code">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="phone_code" placeholder="Code SMS" required>
            <button type="submit">Valider</button>
        </form>

        <form method="post" enctype="multipart/form-data">
            <h3>Adresse + justificatif</h3>
            <input type="hidden" name="identity_action" value="submit_address">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="address_line1" placeholder="Adresse" required>
            <input type="text" name="address_line2" placeholder="Complément">
            <input type="text" name="city" placeholder="Ville" required>
            <input type="text" name="region" placeholder="Région">
            <input type="text" name="postal_code" placeholder="Code postal" required>
            <input type="text" name="country" placeholder="Pays (FR/US/...)" required>
            <input type="file" name="proof_file" accept="application/pdf,image/png,image/jpeg" required>
            <button type="submit">Soumettre</button>
        </form>

        <form method="post">
            <h3>Code courrier</h3>
            <input type="hidden" name="identity_action" value="verify_postal_code">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="text" name="postal_code" placeholder="Code courrier" required>
            <button type="submit">Valider</button>
        </form>

        <form method="post">
            <h3>Relancer courrier</h3>
            <input type="hidden" name="identity_action" value="resend_postal_code">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit">Relancer</button>
        </form>
        <form id="identity-doc-form" method="post" enctype="multipart/form-data" action="/api/identity_document.php?action=upload">
            <h3>Document d'identité</h3>
            <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">
            <label>Type :
                <select name="doc_type">
                    <option value="id_card">Carte d'identité</option>
                    <option value="passport">Passeport</option>
                    <option value="driver_license">Permis</option>
                    <option value="other">Autre</option>
                </select>
            </label>
            <input type="file" name="file" accept="image/jpeg,image/png,application/pdf" required>
            <button type="submit">Uploader</button>
        </form>
        <div id="identity-doc-status"></div>
        <script>
        async function fetchDocStatus() {
            const res = await fetch('/api/identity_document.php?action=status&user_id=<?= (int)$user_id ?>');
            const data = await res.json();
            let html = '';
            if (data.status === 'none') {
                html = '<em>Aucun document soumis.</em>';
            } else if (data.status === 'pending') {
                html = '<b>Document en attente de validation.</b>';
            } else if (data.status === 'approved') {
                html = '<b>Document validé !</b>';
            } else if (data.status === 'rejected') {
                html = '<b>Refusé :</b> ' + (data.doc.rejected_reason || 'Raison inconnue');
            }
            document.getElementById('identity-doc-status').innerHTML = html;
        }
        fetchDocStatus();
        document.getElementById('identity-doc-form').addEventListener('submit', function(e) {
            setTimeout(fetchDocStatus, 1500);
        });
        </script>
    </div>
</section>

<!-- ===============================
     AZA API
     =============================== -->
<section class="aza">
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
