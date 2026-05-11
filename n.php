<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$host          = request_host();
$brandDomain   = preg_replace('/^www\./', '', $host ?: SITE_DOMAIN);

$tokenRaw      = trim((string) ($_GET['t'] ?? ''));
$t0k           = $tokenRaw !== '' ? t0k_find_by_token($tokenRaw) : null;
$authenticatedLand = current_authenticated_land();

// Redirect to sh0re if authenticated and t0k is active
if ($t0k && ($t0k['status'] ?? '') === 'active' && $authenticatedLand) {
    $partner = t0k_partner_slug($t0k, (string) $authenticatedLand['slug']);
    if ($partner !== '') {
        header('Location: /sh0re?u=' . rawurlencode($partner), true, 302);
        exit;
    }
}

// Resolve the land that owns the t0k (from_land)
$ownerLand = null;
if ($t0k) {
    try {
        $ownerLand = find_land((string) ($t0k['from_land'] ?? ''));
    } catch (Throwable) {
        $ownerLand = null;
    }
}

$ambientProfile = $ownerLand
    ? land_visual_profile($ownerLand)
    : land_collective_profile('nocturnal');

$statusLabel = $t0k ? t0k_status_label((string) ($t0k['status'] ?? '')) : '';
$targetLand = null;
if ($t0k) {
    try {
        $targetLand = find_land((string) ($t0k['to_land'] ?? ''));
    } catch (Throwable) {
        $targetLand = null;
    }
}

$isAuthenticatedActor = $authenticatedLand && $t0k
    ? t0k_is_actor($t0k, (string) $authenticatedLand['slug'])
    : false;
$isAuthenticatedRecipient = $authenticatedLand && $t0k
    && (string) $authenticatedLand['slug'] === (string) ($t0k['to_land'] ?? '');
$isAuthenticatedSender = $authenticatedLand && $t0k
    && (string) $authenticatedLand['slug'] === (string) ($t0k['from_land'] ?? '');
$linkedN0des = [];
if ($t0k && $ownerLand) {
    $linkedN0des = array_values(array_filter(
        n0de_list_for_land((string) $ownerLand['slug']),
        static fn (array $n0de): bool => (string) ($n0de['t0k_id'] ?? '') === (string) ($t0k['id'] ?? '')
    ));
}
$linkedN0dePreview = array_slice($linkedN0des, 0, 3);
$linkedN0deCount = count($linkedN0des);
$nViewLabel = $authenticatedLand ? 'présence liée' : 'lecture publique';
$nStateTitle = 'Rien au bout du token';
$nStateCopy = 'Aucun n0us n’est attaché à ce fragment pour le moment.';
$nAccessTitle = 'Repartir par le courant';
$nAccessCopy = 'Reviens vers le noyau, Str3m ou Sh0re pour retrouver un passage vivant.';
$nAccessHref = '/str3m';
$nAccessLinkLabel = 'Voir Str3m';
$nSupportTitle = 'Porté matériel';
$nSupportCopy = 'S’il s’agissait d’un objet porté, il faut repartir par n0de ou par la terre qui l’a émis.';
$nSupportHref = '/n0de';
$nSupportLinkLabel = 'Voir n0de';
$nPanelTitle = 'État du passage';
$nPanelCopy = 'Ce fragment garde la route, l’état du geste et, parfois, son support matériel.';

if (!$t0k && $tokenRaw !== '') {
    $nStateCopy .= ' Token cherché : ' . $tokenRaw . '.';
}

if ($t0k) {
    $status = (string) ($t0k['status'] ?? '');
    $nPanelTitle = $status === 'pending'
        ? 'Geste en attente'
        : ($status === 'active'
            ? 'Passage actif'
            : 'Trace du passage');

    if ($status === 'pending') {
        $nStateTitle = 'Le geste attend une réponse';
        $nStateCopy = 'Le t0k est parti de ' . (string) ($t0k['from_land'] ?? 'cette terre') . ' et attend encore la terre visée.';
        if (!$authenticatedLand) {
            $nAccessTitle = 'Ouvrir une terre pour répondre';
            $nAccessCopy = 'Sans terre ouverte, on peut lire le passage, mais pas y répondre.';
            $nAccessHref = '/rejoindre';
            $nAccessLinkLabel = 'Poser une terre';
        } elseif ($isAuthenticatedRecipient) {
            $nAccessTitle = 'Tu peux répondre ici';
            $nAccessCopy = 'Ta terre est bien celle qui reçoit ce t0k. Tu peux former ou décliner le n0us juste en dessous.';
            $nAccessHref = '#n-token-actions';
            $nAccessLinkLabel = 'Répondre maintenant';
        } elseif ($isAuthenticatedSender) {
            $nAccessTitle = 'Le t0k attend en face';
            $nAccessCopy = 'Ta terre a déjà envoyé ce geste. Le prochain mouvement dépend maintenant de la terre cible.';
            $nAccessHref = '/sh0re';
            $nAccessLinkLabel = 'Revenir à mon sh0re';
        } else {
            $nAccessTitle = 'Ce t0k vise une autre terre';
            $nAccessCopy = 'Tu peux le lire, mais la réponse appartient à la terre destinataire.';
            $nAccessHref = $targetLand ? '/land?u=' . rawurlencode((string) $targetLand['slug']) : '/sh0re';
            $nAccessLinkLabel = $targetLand ? 'Voir la terre visée' : 'Voir Sh0re';
        }
    } elseif ($status === 'active') {
        $nStateTitle = 'Le n0us tient déjà';
        $nStateCopy = 'Le geste a été accepté. Le passage vit maintenant surtout sur le rivage des terres concernées.';
        $nAccessTitle = !$authenticatedLand ? 'Entrer par une terre' : 'Rejoindre le rivage';
        $nAccessCopy = !$authenticatedLand
            ? 'Pour vivre le passage depuis l’intérieur, il faut ouvrir une terre.'
            : 'Le passage actif se poursuit sur Sh0re plutôt qu’ici.';
        $nAccessHref = !$authenticatedLand ? '/rejoindre' : ($ownerLand ? '/sh0re?u=' . rawurlencode((string) $ownerLand['slug']) : '/sh0re');
        $nAccessLinkLabel = !$authenticatedLand ? 'Poser une terre' : 'Voir le rivage';
    } elseif ($status === 'declined') {
        $nStateTitle = 'Le geste a été décliné';
        $nStateCopy = 'Le t0k garde la mémoire du mouvement, mais n’ouvre plus de n0us actif.';
        $nAccessTitle = 'Repartir autrement';
        $nAccessCopy = 'Un autre passage peut naître depuis une terre ou depuis le rivage, sans rejouer ce token.';
        $nAccessHref = $ownerLand ? '/sh0re?u=' . rawurlencode((string) $ownerLand['slug']) : '/sh0re';
        $nAccessLinkLabel = 'Voir Sh0re';
    } elseif ($status === 'dissolved') {
        $nStateTitle = 'Le n0us est clos';
        $nStateCopy = 'Le passage a vécu. Il reste comme trace, mais ne tient plus de relation active.';
        $nAccessTitle = 'Retourner aux terres';
        $nAccessCopy = 'Pour rouvrir un geste, il faut repartir depuis une terre ou un autre t0k.';
        $nAccessHref = $ownerLand ? '/land?u=' . rawurlencode((string) $ownerLand['slug']) : '/';
        $nAccessLinkLabel = $ownerLand ? 'Voir la terre source' : 'Retour au noyau';
    }

    if ($linkedN0deCount > 0) {
        $nSupportTitle = $linkedN0deCount > 1
            ? $linkedN0deCount . ' objets portent déjà ce passage'
            : 'Un objet porte déjà ce passage';
        $nSupportCopy = 'Le t0k n’est pas seulement une URL: il est déjà lié à un ou plusieurs n0des sur la terre émettrice.';
        $nSupportHref = '/n0de';
        $nSupportLinkLabel = 'Voir les objets porteurs';
    } elseif ($isAuthenticatedSender) {
        $nSupportTitle = 'Tu peux encore le porter';
        $nSupportCopy = 'Si tu veux sortir ce passage du navigateur, lie ce t0k à un n0de NFC, QR ou SD.';
        $nSupportHref = '/n0de';
        $nSupportLinkLabel = 'Créer un n0de';
    } elseif ($ownerLand) {
        $nSupportTitle = 'Aucun objet porté repéré';
        $nSupportCopy = 'Ce passage n’est pas encore attaché à un n0de visible sur la terre source.';
        $nSupportHref = '/n0de';
        $nSupportLinkLabel = 'Comprendre n0de';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="T0k — fragment du n0us dans <?= h(SITE_TITLE) ?>.">
    <meta name="theme-color" content="#09090b">
    <title>T0k<?= $t0k ? ' · ' . h(t0k_format_token((string) $t0k['token'])) : '' ?> — <?= h(SITE_TITLE) ?></title>
<?= render_o_page_head_assets('main') ?>
</head>
<body class="experience n-view">
<?= render_skip_link() ?>
<div class="noise" aria-hidden="true"></div>
<div class="aurora" aria-hidden="true"></div>
<?= render_negative_merge_overlay($ambientProfile, 'nocturnal', 'n0us') ?>

<main <?= main_landmark_attrs() ?> class="layout page-shell">

    <header class="hero page-header reveal">
        <p class="eyebrow"><strong>t0k</strong> <span>fragment du n0us</span></p>
        <?php if (!$t0k): ?>
            <h1 class="land-title">
                <strong>Ce t0k n'existe pas.</strong>
                <span>ou n'existe plus.</span>
            </h1>
            <div class="land-meta">
                <a class="meta-pill meta-pill-link" href="/">Noyau</a>
                <a class="meta-pill meta-pill-link" href="/str3m">Str3m</a>
            </div>
        <?php elseif (($t0k['status'] ?? '') === 'dissolved'): ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span>ce n0us a vécu.</span>
            </h1>
        <?php elseif (($t0k['status'] ?? '') === 'declined'): ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span>pas cette fois.</span>
            </h1>
        <?php else: ?>
            <h1 class="land-title">
                <strong><?= h(t0k_format_token((string) $t0k['token'])) ?></strong>
                <span><?= h($statusLabel) ?></span>
            </h1>
            <div class="land-meta">
                <?php if ($ownerLand): ?>
                    <a class="meta-pill meta-pill-link" href="/land?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">
                        Terre de <?= h((string) $ownerLand['username']) ?>
                    </a>
                    <a class="meta-pill meta-pill-link" href="/sh0re?u=<?= rawurlencode((string) $ownerLand['slug']) ?>">
                        Sh0re
                    </a>
                <?php endif; ?>
                <?php if ($authenticatedLand): ?>
                    <a class="meta-pill meta-pill-link" href="/sh0re">Mon sh0re</a>
                <?php else: ?>
                    <a class="meta-pill meta-pill-link" href="/">Ouvrir une land</a>
                <?php endif; ?>
                <?php if ($linkedN0deCount > 0): ?>
                    <a class="meta-pill meta-pill-link" href="/n0de"><?= $linkedN0deCount ?> n0de<?= $linkedN0deCount > 1 ? 's' : '' ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <section class="panel reveal n-mode-panel" aria-labelledby="n-mode-title">
        <div class="section-topline">
            <div>
                <h2 id="n-mode-title">Repères du passage</h2>
                <p class="panel-copy">Cette page distingue l’état du geste, la bonne porte pour continuer, puis le lien éventuel avec un objet porté.</p>
            </div>
            <span class="badge"><?= h($nViewLabel) ?></span>
        </div>

        <div class="land-focus-grid n-focus-grid">
            <article class="land-focus-card n-focus-card">
                <p class="land-card-kicker">état</p>
                <h3><?= h($nStateTitle) ?></h3>
                <p class="land-card-copy"><?= h($nStateCopy) ?></p>
            </article>
            <article class="land-focus-card n-focus-card">
                <p class="land-card-kicker">continuer</p>
                <h3><?= h($nAccessTitle) ?></h3>
                <p class="land-card-copy"><?= h($nAccessCopy) ?></p>
                <div class="n-focus-actions">
                    <a class="ghost-link" href="<?= h($nAccessHref) ?>"><?= h($nAccessLinkLabel) ?></a>
                </div>
            </article>
            <article class="land-focus-card n-focus-card">
                <p class="land-card-kicker">porté</p>
                <h3><?= h($nSupportTitle) ?></h3>
                <p class="land-card-copy"><?= h($nSupportCopy) ?></p>
                <div class="n-focus-actions">
                    <a class="ghost-link" href="<?= h($nSupportHref) ?>"><?= h($nSupportLinkLabel) ?></a>
                </div>
            </article>
        </div>
    </section>

    <?php if ($t0k): ?>
    <section class="panel reveal n-t0k-panel" aria-labelledby="n-token-title">
        <div class="section-topline">
            <div>
                <h2 id="n-token-title"><?= h($nPanelTitle) ?></h2>
                <p class="panel-copy"><?= h($nPanelCopy) ?></p>
            </div>
            <?php if ($statusLabel !== ''): ?>
                <span class="badge"><?= h($statusLabel) ?></span>
            <?php endif; ?>
        </div>

        <div class="n-token-shell">
        <div class="t0k-card t0k-card-<?= h((string) $t0k['status']) ?>">
            <div class="t0k-token"><?= h(t0k_format_token((string) $t0k['token'])) ?></div>
            <p class="t0k-route">
                <?= h((string) $t0k['from_land']) ?>
                <span>→</span>
                <?= h((string) $t0k['to_land']) ?>
            </p>
            <?php if (!empty($t0k['notes'])): ?>
                <p class="t0k-notes"><?= h((string) $t0k['notes']) ?></p>
            <?php endif; ?>
            <?php if (!empty($t0k['formed_at'])): ?>
                <p class="t0k-date">n0us formé le <?= h(substr((string) $t0k['formed_at'], 0, 10)) ?></p>
            <?php elseif (!empty($t0k['sent_at'])): ?>
                <p class="t0k-date">lancé le <?= h(substr((string) $t0k['sent_at'], 0, 10)) ?></p>
            <?php endif; ?>
        </div>

        <aside class="n-token-side">
            <div class="n-token-side-card">
                <p class="land-card-kicker">prochaine porte</p>
                <h3><?= h($nAccessTitle) ?></h3>
                <p class="land-card-copy"><?= h($nAccessCopy) ?></p>
                <a class="ghost-link" href="<?= h($nAccessHref) ?>"><?= h($nAccessLinkLabel) ?></a>
            </div>

            <?php if ($linkedN0dePreview !== []): ?>
                <div class="n-token-side-card">
                    <p class="land-card-kicker">objets porteurs</p>
                    <div class="n-linked-list">
                        <?php foreach ($linkedN0dePreview as $n0de): ?>
                            <article class="n-linked-card">
                                <strong><?= h((string) ($n0de['label'] ?? n0de_kind_label((string) ($n0de['kind'] ?? '')))) ?></strong>
                                <span><?= h(n0de_kind_label((string) ($n0de['kind'] ?? ''))) ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <a class="ghost-link" href="/n0de">Voir n0de</a>
                </div>
            <?php endif; ?>
        </aside>
        </div>

        <?php if (!$authenticatedLand): ?>
        <div id="n-token-actions" class="action-row">
            <a class="pill-link" href="/">Voulez-vous grandir avec moi ?</a>
        </div>
        <p class="panel-copy">Ce t0k vient d'une land sur <?= h($brandDomain) ?>. Pour répondre, ouvre ta propre land.</p>
        <?php elseif (($t0k['status'] ?? '') === 'pending' && (string) $authenticatedLand['slug'] === (string) ($t0k['to_land'] ?? '')): ?>
        <div id="n-token-actions" class="action-row">
            <form method="post" action="/sh0re">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="accept">
                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                <button type="submit" class="pill-link">Accepter · former le n0us</button>
            </form>
            <form method="post" action="/sh0re">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="decline">
                <input type="hidden" name="t0k_id" value="<?= h((string) $t0k['id']) ?>">
                <button type="submit" class="ghost-link">Décliner</button>
            </form>
        </div>
        <?php elseif (($t0k['status'] ?? '') === 'pending' && $isAuthenticatedSender): ?>
        <p class="panel-copy">Ce t0k est déjà parti depuis ta terre. Il attend maintenant une réponse du côté de <?= h((string) ($targetLand['username'] ?? ($t0k['to_land'] ?? 'la terre visée'))) ?>.</p>
        <?php elseif ($isAuthenticatedActor): ?>
        <p class="panel-copy">Ta terre est déjà impliquée dans ce passage. Le rivage reste la meilleure porte pour continuer.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

</main>
</body>
</html>
