<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host              = request_host();
$csrfToken         = csrf_token();
$brandDomain       = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
$stylesVersion     = is_file(__DIR__ . '/styles.css') ? (string) filemtime(__DIR__ . '/styles.css') : '1';
$scriptVersion     = is_file(__DIR__ . '/main.js') ? (string) filemtime(__DIR__ . '/main.js') : '1';
$authenticatedLand = current_authenticated_land();

// ─── Manifest download (SD card export) ──────────────────────────────────────

$syncToken = trim((string) ($_GET['sync'] ?? ''));
if ($syncToken !== '') {
    $n0de = n0de_find_by_token($syncToken);
    if ($n0de) {
        $land = null;
        try { $land = find_land((string) $n0de['land_slug']); } catch (Throwable) {}
        $manifest = n0de_build_manifest($n0de, $land ?? []);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="n0de-' . n0de_normalize_token($syncToken) . '.json"');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'n0de inconnu']);
    exit;
}

// ─── POST handler ────────────────────────────────────────────────────────────

$message     = '';
$messageType = 'info';
$registered  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $authenticatedLand) {
    $csrfCandidate = (string) ($_POST['csrf_token'] ?? '');
    $action        = trim((string) ($_POST['action'] ?? ''));
    $mySlug        = (string) $authenticatedLand['slug'];

    if (!verify_csrf_token($csrfCandidate)) {
        $message     = 'Session expirée. Recharge la page.';
        $messageType = 'warning';
    } else {
        try {
            switch ($action) {
                case 'register':
                    $kind  = trim((string) ($_POST['kind'] ?? 'mixed'));
                    $label = trim((string) ($_POST['label'] ?? ''));
                    $t0kId = trim((string) ($_POST['t0k_id'] ?? ''));
                    $registered  = n0de_register($mySlug, $kind, $label, $t0kId);
                    $message     = 'N0de enregistré : ' . h(n0de_format_token((string) $registered['token']));
                    $messageType = 'success';
                    break;

                case 'delete':
                    $n0deId = trim((string) ($_POST['n0de_id'] ?? ''));
                    n0de_delete($n0deId, $mySlug);
                    $message     = 'N0de retiré.';
                    $messageType = 'info';
                    break;
            }
        } catch (Throwable $e) {
            $message     = $e->getMessage();
            $messageType = 'warning';
        }
    }
}

// ─── Data ────────────────────────────────────────────────────────────────────

$myN0des    = $authenticatedLand ? n0de_list_for_land((string) $authenticatedLand['slug']) : [];
$myT0ks     = $authenticatedLand ? t0k_active_for_land((string) $authenticatedLand['slug']) : [];
$n0deKinds  = n0de_kinds();
$ambientProfile = $authenticatedLand
    ? land_visual_profile($authenticatedLand)
    : land_collective_profile('nocturnal');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="N0des — objets physiques porteurs dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>N0des — <?= h(SITE_TITLE) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
<?= render_pwa_head_tags('main') ?>
    <link rel="stylesheet" href="/styles.css?v=<?= h($stylesVersion) ?>">
    <script defer src="/main.js?v=<?= h($scriptVersion) ?>"></script>
    <script defer src="/qr.js"></script>
</head>
<body class="experience n0de-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'n0de') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">

    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>n0des</strong> <span>objets physiques porteurs</span></p>
        <h1 class="land-title">
            <strong>NFC · QR · SD</strong>
            <span>le n0us dans la matière</span>
        </h1>
        <p class="lead">Chaque objet porte un fragment du 3ternet. Il pointe, il héberge, il existe sans connexion.</p>
        <div class="land-meta">
            <?php if ($authenticatedLand): ?>
                <span class="meta-pill"><?= h((string) $authenticatedLand['username']) ?></span>
                <a class="meta-pill meta-pill-link" href="/sh0re.php">Sh0re</a>
            <?php else: ?>
                <a class="meta-pill meta-pill-link" href="/">Ouvrir une land</a>
            <?php endif; ?>
            <a class="meta-pill meta-pill-link" href="/str3m">Str3m</a>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="flash flash-<?= h($messageType) ?>" aria-live="polite"><p><?= $message ?></p></div>
    <?php endif; ?>

    <?php if ($registered): ?>
    <section class="panel reveal n0de-registered">
        <h2>N0de créé</h2>
        <div class="n0de-card n0de-card-new">
            <div class="n0de-token-display">
                <span class="n0de-token"><?= h(n0de_format_token((string) $registered['token'])) ?></span>
                <span class="n0de-kind-badge"><?= h(n0de_kind_label((string) $registered['kind'])) ?></span>
            </div>
            <p class="n0de-label"><?= h((string) $registered['label']) ?></p>

            <?php $nfcUrl = n0de_nfc_url($registered); ?>

            <div class="n0de-physical-row">
                <div class="n0de-physical-block">
                    <p class="n0de-physical-title">NFC / QR</p>
                    <p class="n0de-physical-url"><?= h($nfcUrl) ?></p>
                    <canvas class="n0de-qr-canvas" data-qr="<?= h($nfcUrl) ?>" width="180" height="180"></canvas>
                    <button class="ghost-link" data-copy-link="<?= h($nfcUrl) ?>">Copier l'URL</button>
                </div>

                <?php if (in_array($registered['kind'], ['sd', 'mixed'], true)): ?>
                <div class="n0de-physical-block">
                    <p class="n0de-physical-title">Carte SD · Manifest</p>
                    <p class="n0de-physical-url">n0de-<?= h(n0de_normalize_token((string) $registered['token'])) ?>.json</p>
                    <a class="pill-link"
                       href="/n0de.php?sync=<?= h((string) $registered['token']) ?>"
                       download="n0de-<?= h(n0de_normalize_token((string) $registered['token'])) ?>.json">
                        Télécharger le manifest SD
                    </a>
                    <p class="input-hint">Copie ce fichier à la racine de la carte SD. L'objet est prêt.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!$authenticatedLand): ?>
    <section class="panel reveal">
        <h2>Une land est requise</h2>
        <p class="panel-copy">Les objets physiques sont ancrés à une land. Commence par en ouvrir une.</p>
        <div class="action-row">
            <a class="pill-link" href="/">Ouvrir une land</a>
        </div>
    </section>

    <?php else: ?>

    <section class="panel-shell n0de-shell">

        <section class="panel reveal" aria-labelledby="n0de-register-title">
            <h2 id="n0de-register-title">Enregistrer un objet</h2>
            <p class="panel-copy">Un objet = un token d'hébergement. NFC et QR pointent vers ce token. La SD l'héberge.</p>
            <form method="post" class="land-form n0de-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="register">

                <label>
                    Type d'objet
                    <select name="kind">
                        <?php foreach ($n0deKinds as $value => $kindLabel): ?>
                            <option value="<?= h($value) ?>"><?= h($kindLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Nom de l'objet
                    <input type="text" name="label" placeholder="ex: Collier cuivre v1, Portefeuille atelier…">
                    <span class="input-hint">Ce nom reste privé.</span>
                </label>

                <?php if ($myT0ks): ?>
                <label>
                    Lier à un n0us (facultatif)
                    <select name="t0k_id">
                        <option value="">— aucun n0us lié —</option>
                        <?php foreach ($myT0ks as $t0k): ?>
                            <option value="<?= h((string) $t0k['id']) ?>">
                                <?= h(t0k_format_token((string) $t0k['token'])) ?>
                                · <?= h(t0k_partner_slug($t0k, (string) $authenticatedLand['slug'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="input-hint">L'objet portera aussi la relation.</span>
                </label>
                <?php endif; ?>

                <button type="submit">Créer le n0de</button>
            </form>
        </section>

        <aside class="panel reveal" aria-labelledby="n0de-explain-title">
            <h2 id="n0de-explain-title">Comment ça marche</h2>
            <div class="summary-grid">
                <article class="summary-card">
                    <span class="summary-label">NFC</span>
                    <strong class="summary-value summary-value-small">Tap</strong>
                    <p class="land-note">Graver l'URL du token sur la puce. Un tap ouvre le n0us.</p>
                </article>
                <article class="summary-card">
                    <span class="summary-label">QR</span>
                    <strong class="summary-value summary-value-small">Scan</strong>
                    <p class="land-note">Même URL. Imprimable, gravable, brodable.</p>
                </article>
                <article class="summary-card">
                    <span class="summary-label">SD</span>
                    <strong class="summary-value summary-value-small">Host</strong>
                    <p class="land-note">Le manifest sur la carte = l'objet héberge un fragment du 3ternet. Fonctionne hors connexion.</p>
                </article>
            </div>
        </aside>

    </section>

    <?php if ($myN0des): ?>
    <section class="panel reveal" aria-labelledby="n0de-list-title">
        <h2 id="n0de-list-title">Tes objets</h2>
        <p class="panel-copy"><?= count($myN0des) ?> objet<?= count($myN0des) > 1 ? 's' : '' ?> enregistré<?= count($myN0des) > 1 ? 's' : '' ?>.</p>
        <div class="n0de-list">
            <?php foreach ($myN0des as $n0de): ?>
                <?php $nfcUrl = n0de_nfc_url($n0de); ?>
                <article class="n0de-card">
                    <div class="n0de-token-display">
                        <span class="n0de-token"><?= h(n0de_format_token((string) $n0de['token'])) ?></span>
                        <span class="n0de-kind-badge"><?= h(n0de_kind_label((string) $n0de['kind'])) ?></span>
                    </div>
                    <p class="n0de-label"><?= h((string) $n0de['label']) ?></p>
                    <p class="n0de-physical-url"><?= h($nfcUrl) ?></p>
                    <canvas class="n0de-qr-canvas" data-qr="<?= h($nfcUrl) ?>" width="140" height="140"></canvas>
                    <div class="n0de-actions">
                        <button class="ghost-link" data-copy-link="<?= h($nfcUrl) ?>">Copier URL NFC/QR</button>
                        <?php if (in_array($n0de['kind'], ['sd', 'mixed'], true)): ?>
                            <a class="ghost-link"
                               href="/n0de.php?sync=<?= h((string) $n0de['token']) ?>"
                               download="n0de-<?= h(n0de_normalize_token((string) $n0de['token'])) ?>.json">
                                Manifest SD
                            </a>
                        <?php endif; ?>
                        <form method="post" class="n0de-delete-form">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="n0de_id" value="<?= h((string) $n0de['id']) ?>">
                            <button type="submit" class="ghost-link n0de-delete-btn">Retirer</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php endif; ?>

</main>
</body>
</html>
