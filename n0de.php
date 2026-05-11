<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host              = request_host();
$csrfToken         = csrf_token();
$brandDomain       = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);
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
$myN0deCount = count($myN0des);
$myT0kCount = count($myT0ks);
$myManifestCount = count(array_filter(
    $myN0des,
    static fn (array $n0de): bool => in_array((string) ($n0de['kind'] ?? ''), ['sd', 'mixed'], true)
));
$myLinkedN0deCount = count(array_filter(
    $myN0des,
    static fn (array $n0de): bool => trim((string) ($n0de['t0k_id'] ?? '')) !== ''
));
$latestN0de = $registered ?: ($myN0des[0] ?? null);
$latestN0deCreatedLabel = $latestN0de
    ? (human_created_label((string) ($latestN0de['created_at'] ?? '')) ?? 'maintenant')
    : '';
$registeredHasManifest = $registered
    ? in_array((string) ($registered['kind'] ?? ''), ['sd', 'mixed'], true)
    : false;
$registeredLinkedT0k = $registered && trim((string) ($registered['t0k_id'] ?? '')) !== ''
    ? t0k_find_by_id((string) $registered['t0k_id'])
    : null;
$n0deViewLabel = $authenticatedLand ? 'présence liée' : 'lecture de principe';
$n0deRecommendedKind = $myT0kCount > 0 ? 'mixed' : ($myN0deCount > 0 ? 'qr' : 'mixed');
$n0deRecommendedLabel = n0de_kind_label($n0deRecommendedKind);
$n0deAnchorTitle = 'Une terre doit tenir l’objet';
$n0deAnchorCopy = 'Le registre n0de reste lié à une terre ouverte. C’est elle qui porte le token, le manifest et la relation.';
$n0deAnchorMeta = 'aucune présence liée';
$n0deAnchorHref = '/rejoindre';
$n0deAnchorLinkLabel = 'Poser une terre';
$n0deGestureTitle = 'Commencer par un objet simple';
$n0deGestureCopy = 'QR pour pointer vite, NFC pour le geste, SD pour embarquer un fragment hors connexion.';
$n0deGestureMeta = 'type conseillé · NFC + QR + SD';
$n0deGestureHref = '/str3m';
$n0deGestureLinkLabel = 'Voir le courant public';
$n0deBridgeTitle = 'La relation vient ensuite';
$n0deBridgeCopy = 'Sh0re tient les n0us, Str3m montre le courant, et n0de donne un support matériel à cette circulation.';
$n0deBridgeMeta = 'Sh0re · Str3m · n0de';
$n0deRegisterHint = 'Un n0de mixte reste le point d’entrée le plus souple: NFC, QR et manifest dans un seul geste.';

if ($authenticatedLand) {
    $n0deAnchorTitle = $myN0deCount > 0
        ? $myN0deCount . ' objet' . ($myN0deCount > 1 ? 's' : '') . ' déjà ancré' . ($myN0deCount > 1 ? 's' : '')
        : 'Aucun objet encore ancré';
    $n0deAnchorCopy = 'La terre ' . (string) $authenticatedLand['username'] . ' tient le registre privé des tokens, manifests et liens portés.';
    $n0deAnchorMeta = $myManifestCount . ' manifest' . ($myManifestCount > 1 ? 's' : '') . ' · '
        . $myLinkedN0deCount . ' n0de' . ($myLinkedN0deCount > 1 ? 's' : '') . ' lié' . ($myLinkedN0deCount > 1 ? 's' : '')
        . ' · '
        . $myT0kCount . ' n0us actif' . ($myT0kCount > 1 ? 's' : '');
    $n0deAnchorHref = '/land?u=' . rawurlencode((string) $authenticatedLand['slug']);
    $n0deAnchorLinkLabel = 'Voir ma terre';

    if ($myN0deCount <= 0 && $myT0kCount > 0) {
        $n0deGestureTitle = 'Commencer par un n0de mixte';
        $n0deGestureCopy = 'Tu as déjà un n0us actif: un objet mixte peut porter à la fois le lien rapide et un manifest hors connexion.';
    } elseif ($myN0deCount <= 0) {
        $n0deGestureTitle = 'Commencer par un premier porté';
        $n0deGestureCopy = 'Commence par un objet souple à graver ou imprimer, puis ajoute la SD si tu veux embarquer la matière.';
    } else {
        $n0deGestureTitle = 'Étendre la portée';
        $n0deGestureCopy = 'Le prochain objet peut spécialiser ta circulation: simple QR, geste NFC, ou manifest SD pour une autonomie plus forte.';
    }

    $n0deGestureMeta = 'type conseillé · ' . $n0deRecommendedLabel;
    if ($latestN0deCreatedLabel !== '') {
        $n0deGestureMeta .= ' · dernier posé ' . $latestN0deCreatedLabel;
    }
    $n0deGestureHref = '#n0de-register-title';
    $n0deGestureLinkLabel = 'Préparer un n0de';
    $n0deBridgeTitle = 'Du token au passage';
    $n0deBridgeCopy = 'Le registre reste ici, la relation se vit sur Sh0re, puis le courant public peut remonter dans Str3m sans perdre l’ancrage matériel.';
    $n0deBridgeMeta = 'Sh0re · Str3m · shell porté';
    $n0deRegisterHint = $myT0kCount > 0
        ? 'Tu peux lier un n0de à un n0us déjà actif pour que l’objet porte aussi la relation.'
        : 'Le type mixte reste le plus complet si tu veux un seul objet pour pointer, toucher et héberger.';
}
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
<?= render_o_page_head_assets('main') ?>
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
                <a class="meta-pill meta-pill-link" href="/sh0re">Sh0re</a>
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
        <div class="section-topline">
            <div>
                <h2>N0de prêt</h2>
                <p class="panel-copy">
                    <?= $registeredHasManifest
                        ? 'Le token est posé. Prochain geste: copier l’accès rapide, puis prendre le manifest SD si l’objet doit porter la matière hors connexion.'
                        : 'Le token est posé. Prochain geste: copier l’accès rapide, puis l’écrire sur la puce ou le support choisi.' ?>
                </p>
            </div>
            <span class="badge">objet porté</span>
        </div>
        <div class="n0de-card n0de-card-new">
            <div class="n0de-token-display">
                <span class="n0de-token"><?= h(n0de_format_token((string) $registered['token'])) ?></span>
                <span class="n0de-kind-badge"><?= h(n0de_kind_label((string) $registered['kind'])) ?></span>
            </div>
            <p class="n0de-label"><?= h((string) $registered['label']) ?></p>
            <?php if ($registeredLinkedT0k): ?>
                <p class="n0de-panel-note">
                    Ce n0de porte aussi le n0us vers <?= h(t0k_partner_slug($registeredLinkedT0k, (string) $authenticatedLand['slug'])) ?>.
                </p>
            <?php endif; ?>

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

    <section class="panel reveal n0de-mode-panel" aria-labelledby="n0de-mode-title">
        <div class="section-topline">
            <div>
                <h2 id="n0de-mode-title">Repères du porté</h2>
                <p class="panel-copy">N0de sépare l’ancrage privé du token, le premier geste matériel, puis les passages où cet objet rejoint une relation ou un courant.</p>
            </div>
            <span class="badge"><?= h($n0deViewLabel) ?></span>
        </div>

        <div class="land-focus-grid n0de-focus-grid">
            <article class="land-focus-card n0de-focus-card">
                <p class="land-card-kicker">ancrage</p>
                <h3><?= h($n0deAnchorTitle) ?></h3>
                <p class="land-card-copy"><?= h($n0deAnchorCopy) ?></p>
                <p class="n0de-focus-meta"><?= h($n0deAnchorMeta) ?></p>
                <div class="n0de-focus-actions">
                    <a class="ghost-link" href="<?= h($n0deAnchorHref) ?>"><?= h($n0deAnchorLinkLabel) ?></a>
                </div>
            </article>

            <article class="land-focus-card n0de-focus-card">
                <p class="land-card-kicker">premier geste</p>
                <h3><?= h($n0deGestureTitle) ?></h3>
                <p class="land-card-copy"><?= h($n0deGestureCopy) ?></p>
                <p class="n0de-focus-meta"><?= h($n0deGestureMeta) ?></p>
                <div class="n0de-focus-actions">
                    <a class="ghost-link" href="<?= h($n0deGestureHref) ?>"><?= h($n0deGestureLinkLabel) ?></a>
                </div>
            </article>

            <article class="land-focus-card n0de-focus-card">
                <p class="land-card-kicker">passages</p>
                <h3><?= h($n0deBridgeTitle) ?></h3>
                <p class="land-card-copy"><?= h($n0deBridgeCopy) ?></p>
                <p class="n0de-focus-meta"><?= h($n0deBridgeMeta) ?></p>
                <div class="n0de-focus-actions">
                    <a class="ghost-link" href="/sh0re">Sh0re</a>
                    <a class="ghost-link" href="/str3m">Str3m</a>
                </div>
            </article>
        </div>
    </section>

    <?php if (!$authenticatedLand): ?>
    <section class="panel reveal">
        <h2>Une land est requise</h2>
        <p class="panel-copy">Les objets physiques sont ancrés à une land. Commence par en ouvrir une.</p>
        <div class="action-row">
            <a class="pill-link" href="/">Ouvrir une land</a>
            <a class="ghost-link" href="/rejoindre">Poser une terre</a>
        </div>
    </section>

    <?php else: ?>

    <section class="panel-shell n0de-shell">

        <section class="panel reveal n0de-register-panel" aria-labelledby="n0de-register-title">
            <div class="section-topline">
                <div>
                    <h2 id="n0de-register-title">Enregistrer un objet</h2>
                    <p class="panel-copy">Un objet = un token d’hébergement. NFC et QR pointent vers ce token. La SD l’héberge.</p>
                </div>
                <span class="badge"><?= h($n0deRecommendedLabel) ?></span>
            </div>
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
            <p class="n0de-panel-note"><?= h($n0deRegisterHint) ?></p>
        </section>

        <aside class="panel reveal" aria-labelledby="n0de-explain-title">
            <div class="section-topline">
                <div>
                    <h2 id="n0de-explain-title">Premières prises</h2>
                    <p class="panel-copy">Choisir le bon support selon ce que l’objet doit faire tout de suite.</p>
                </div>
                <a class="ghost-link" href="/sh0re">Voir Sh0re</a>
            </div>
            <div class="summary-grid">
                <article class="summary-card">
                    <span class="summary-label">NFC</span>
                    <strong class="summary-value summary-value-small">Tap</strong>
                    <p class="land-note">Graver l’URL du token sur la puce. Un tap ouvre le n0us ou la porte portée.</p>
                </article>
                <article class="summary-card">
                    <span class="summary-label">QR</span>
                    <strong class="summary-value summary-value-small">Scan</strong>
                    <p class="land-note">Même URL. Imprimable, gravable, brodable, donc facile à faire circuler.</p>
                </article>
                <article class="summary-card">
                    <span class="summary-label">SD</span>
                    <strong class="summary-value summary-value-small">Host</strong>
                    <p class="land-note">Le manifest sur la carte = l’objet héberge un fragment du 3ternet. Fonctionne hors connexion.</p>
                </article>
            </div>
            <div class="n0de-side-notes">
                <p class="n0de-panel-note">Le registre reste privé ici; la relation se lit ensuite sur Sh0re, et la surface publique peut remonter dans Str3m.</p>
                <div class="action-row n0de-side-actions">
                    <a class="ghost-link" href="/str3m">Aller vers Str3m</a>
                    <a class="ghost-link" href="/land?u=<?= rawurlencode((string) $authenticatedLand['slug']) ?>">Retour à ma terre</a>
                </div>
            </div>
        </aside>

    </section>

    <?php if ($myN0des): ?>
    <section class="panel reveal" aria-labelledby="n0de-list-title">
        <div class="section-topline">
            <div>
                <h2 id="n0de-list-title">Tes objets</h2>
                <p class="panel-copy"><?= $myN0deCount ?> objet<?= $myN0deCount > 1 ? 's' : '' ?> enregistré<?= $myN0deCount > 1 ? 's' : '' ?><?= $myLinkedN0deCount > 0 ? ' · ' . $myLinkedN0deCount . ' lié' . ($myLinkedN0deCount > 1 ? 's' : '') . ' à un n0us' : '' ?>.</p>
            </div>
            <a class="ghost-link" href="#n0de-register-title">Ajouter un autre n0de</a>
        </div>
        <div class="n0de-list">
            <?php foreach ($myN0des as $n0de): ?>
                <?php $nfcUrl = n0de_nfc_url($n0de); ?>
                <?php $linkedT0k = trim((string) ($n0de['t0k_id'] ?? '')) !== '' ? t0k_find_by_id((string) $n0de['t0k_id']) : null; ?>
                <article class="n0de-card">
                    <div class="n0de-token-display">
                        <span class="n0de-token"><?= h(n0de_format_token((string) $n0de['token'])) ?></span>
                        <span class="n0de-kind-badge"><?= h(n0de_kind_label((string) $n0de['kind'])) ?></span>
                    </div>
                    <p class="n0de-label"><?= h((string) $n0de['label']) ?></p>
                    <p class="n0de-card-meta">
                        <span>posé <?= h(human_created_label((string) ($n0de['created_at'] ?? '')) ?? 'récemment') ?></span>
                        <?php if ($linkedT0k): ?>
                            <span>lié à <?= h(t0k_partner_slug($linkedT0k, (string) $authenticatedLand['slug'])) ?></span>
                        <?php endif; ?>
                        <?php if (in_array((string) ($n0de['kind'] ?? ''), ['sd', 'mixed'], true)): ?>
                            <span>manifest prêt</span>
                        <?php endif; ?>
                    </p>
                    <p class="n0de-physical-url"><?= h($nfcUrl) ?></p>
                    <canvas class="n0de-qr-canvas" data-qr="<?= h($nfcUrl) ?>" width="140" height="140"></canvas>
                    <?php if ($linkedT0k): ?>
                        <p class="n0de-panel-note">Cet objet porte aussi le passage vers <?= h(t0k_partner_slug($linkedT0k, (string) $authenticatedLand['slug'])) ?>.</p>
                    <?php endif; ?>
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
