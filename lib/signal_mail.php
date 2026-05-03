<?php
declare(strict_types=1);

if (!function_exists('get_pdo')) {
    require_once dirname(__DIR__) . '/config.php';
}

const SIGNAL_IDENTITY_PENDING = 'pending';
const SIGNAL_IDENTITY_UNVERIFIED = 'unverified';
const SIGNAL_IDENTITY_VERIFIED = 'verified';
const SIGNAL_IDENTITY_TTL_SECONDS = 86400;
const SIGNAL_IDENTITY_RESEND_SECONDS = 120;

function signal_mail_tables_ready(): bool
{
    try {
        $pdo = get_pdo();
        $pdo->query('SELECT 1 FROM signal_mailboxes LIMIT 1');
        $pdo->query('SELECT 1 FROM signal_messages LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}

function signal_virtual_address(array $land): string
{
    $email = trim((string) ($land['email_virtual'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    return normalize_username((string) ($land['slug'] ?? $land['username'] ?? 'terre')) . '@o.local';
}

function signal_find_land_by_slug(string $slug): ?array
{
    try {
        return find_land($slug);
    } catch (Throwable $exception) {
        return null;
    }
}

function signal_find_land_by_username(string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    foreach (land_snapshot() as $candidate) {
        if (hash_equals(
            normalize_username((string) ($candidate['username'] ?? '')),
            normalize_username($username)
        )) {
            return $candidate;
        }
    }

    return null;
}

function signal_find_land_by_identifier(string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }

    $bySlug = signal_find_land_by_slug($identifier);
    if ($bySlug) {
        return $bySlug;
    }

    return signal_find_land_by_username($identifier);
}

function ensure_signal_mailbox(array $land): array
{
    $pdo = get_pdo();
    $slug = normalize_username((string) ($land['slug'] ?? ''));
    $username = trim((string) ($land['username'] ?? $slug));
    $virtualEmail = signal_virtual_address($land);

    $select = $pdo->prepare('SELECT * FROM signal_mailboxes WHERE land_slug = ? LIMIT 1');
    $select->execute([$slug]);
    $existing = $select->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE signal_mailboxes SET land_username = ?, virtual_email = ?, last_seen_at = UTC_TIMESTAMP() WHERE land_slug = ?'
        );
        $update->execute([$username, $virtualEmail, $slug]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO signal_mailboxes (land_slug, land_username, virtual_email, identity_status, created_at, updated_at, last_seen_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );
        $insert->execute([$slug, $username, $virtualEmail, SIGNAL_IDENTITY_UNVERIFIED]);
    }

    $select->execute([$slug]);
    $mailbox = $select->fetch(PDO::FETCH_ASSOC);
    return is_array($mailbox) ? $mailbox : [];
}

function signal_mailbox_for_land(array $land): array
{
    if (!signal_mail_tables_ready()) {
        return [];
    }

    return ensure_signal_mailbox($land);
}

function signal_identity_status_label(string $status): string
{
    return match ($status) {
        SIGNAL_IDENTITY_VERIFIED => 'identité vérifiée',
        SIGNAL_IDENTITY_PENDING => 'validation en attente',
        default => 'identité non vérifiée',
    };
}

function signal_identity_delivery_mode(): string
{
    $mode = strtolower(trim((string) (getenv('SOWWWL_SIGNAL_IDENTITY_DELIVERY') ?: '')));
    if ($mode === '') {
        $mode = strtolower(trim((string) (getenv('SOWWWL_MAGIC_LINK_DELIVERY') ?: 'mail')));
    }

    return in_array($mode, ['mail', 'log', 'display'], true) ? $mode : 'mail';
}

function signal_identity_seconds_until_resend(array $mailbox): int
{
    if (($mailbox['identity_status'] ?? '') !== SIGNAL_IDENTITY_PENDING) {
        return 0;
    }

    $sentAt = strtotime((string) ($mailbox['verification_token_sent_at'] ?? '')) ?: 0;
    if ($sentAt <= 0) {
        return 0;
    }

    return max(0, SIGNAL_IDENTITY_RESEND_SECONDS - (time() - $sentAt));
}

function signal_identity_status_hint(array $mailbox): string
{
    $status = trim((string) ($mailbox['identity_status'] ?? SIGNAL_IDENTITY_UNVERIFIED));
    $email = trim((string) ($mailbox['notification_email'] ?? ''));

    if ($status === SIGNAL_IDENTITY_VERIFIED) {
        return $email !== ''
            ? 'Adresse confirmée : ' . $email . '. Les notifications peuvent circuler.'
            : 'Adresse confirmée. Les notifications peuvent circuler.';
    }

    if ($status === SIGNAL_IDENTITY_PENDING) {
        $sentAt = trim((string) ($mailbox['verification_token_sent_at'] ?? ''));
        $suffix = $sentAt !== '' ? ' Dernier envoi : ' . human_created_label($sentAt) . '.' : '';
        return $email !== ''
            ? 'Lien envoyé à ' . $email . '. En attente de confirmation.' . $suffix
            : 'Validation en attente.' . $suffix;
    }

    return 'Aucune adresse confirmée. Signal fonctionne en interne, mais la présence réelle n’est pas encore reliée.';
}

function signal_datetime_to_timestamp(string $value): int
{
    $timestamp = strtotime(trim($value));
    return $timestamp !== false ? $timestamp : 0;
}

function signal_contact_activity(array $summary): array
{
    $unreadCount = (int) ($summary['unread_count'] ?? 0);
    $lastMessageAt = trim((string) ($summary['last_message_at'] ?? ''));
    $hasMessages = $lastMessageAt !== '' || trim((string) ($summary['last_subject'] ?? '')) !== '' || trim((string) ($summary['last_body'] ?? '')) !== '';
    $timestamp = signal_datetime_to_timestamp($lastMessageAt);
    $now = time();
    $days = $timestamp > 0 ? max(0.0, ($now - $timestamp) / 86400) : 999.0;

    $score = 0.0;
    if ($hasMessages) {
        $score += 8.0;
    }
    $score += min(6.0, $unreadCount * 2.5);
    if ($timestamp > 0) {
        $score += max(0.0, 5.5 - min(5.5, $days * 0.42));
    }

    $heat = match (true) {
        $unreadCount >= 3 || $score >= 12.5 => 'hot',
        $unreadCount >= 1 || $score >= 8.5 => 'warm',
        $hasMessages => 'calm',
        default => 'dormant',
    };

    $label = match ($heat) {
        'hot' => 'plasma chaud',
        'warm' => 'en circulation',
        'calm' => 'stable',
        default => 'latente',
    };

    return [
        'activity_score' => round($score, 3),
        'activity_heat' => $heat,
        'activity_label' => $label,
        'last_message_ts' => $timestamp,
        'has_messages' => $hasMessages,
    ];
}

function signal_contact_resonance(array $land, ?array $counterpartLand): array
{
    $selfProfile = land_visual_profile($land);
    $counterpartProfile = $counterpartLand
        ? land_visual_profile($counterpartLand)
        : land_collective_profile('calm');

    $selfProgram = trim((string) ($selfProfile['program'] ?? 'collective')) ?: 'collective';
    $counterpartProgram = trim((string) ($counterpartProfile['program'] ?? 'collective')) ?: 'collective';
    $selfLambda = (int) ($selfProfile['lambda_nm'] ?? 548);
    $counterpartLambda = (int) ($counterpartProfile['lambda_nm'] ?? 548);
    $lambdaGap = abs($selfLambda - $counterpartLambda);
    $sameProgram = hash_equals($selfProgram, $counterpartProgram);

    $phase = match (true) {
        $sameProgram && $lambdaGap <= 18 => 'phase-locked',
        $sameProgram && $lambdaGap <= 52 => 'harmonic',
        !$sameProgram && $lambdaGap <= 28 => 'interference',
        $lambdaGap <= 92 => 'drift',
        default => 'inertia',
    };

    $label = match ($phase) {
        'phase-locked' => 'en phase',
        'harmonic' => 'harmonique',
        'interference' => 'interférence créative',
        'drift' => 'déphasage léger',
        default => 'inertie fertile',
    };

    $summary = match ($phase) {
        'phase-locked' => 'même programme, faible écart spectral : le fil part vite.',
        'harmonic' => 'même famille d’onde, un peu d’espace pour respirer.',
        'interference' => 'programmes distincts mais proches : contraste fécond.',
        'drift' => 'les ondes se croisent sans se perdre, avec un peu d’inertie.',
        default => 'grande distance spectrale : l’élan est plus lent mais profond.',
    };

    $score = max(0.4, 3.4 - min(3.0, $lambdaGap / 34));
    if ($sameProgram) {
        $score += 1.1;
    } elseif ($phase === 'interference') {
        $score += 0.7;
    }

    return [
        'self_lambda_nm' => $selfLambda,
        'self_program' => $selfProgram,
        'counterpart_program' => $counterpartProgram,
        'counterpart_program_label' => trim((string) ($counterpartProfile['label'] ?? $counterpartProgram)),
        'counterpart_tone' => trim((string) ($counterpartProfile['tone'] ?? '')),
        'counterpart_lambda_nm' => $counterpartLambda,
        'resonance_gap_nm' => $lambdaGap,
        'resonance_phase' => $phase,
        'resonance_label' => $label,
        'resonance_summary' => $summary,
        'resonance_family' => $sameProgram ? 'homophase' : 'hétérophase',
        'resonance_score' => round($score, 3),
    ];
}

function signal_contact_presence(array $contact): array
{
    $activityScore = (float) ($contact['activity_score'] ?? 0.0);
    $resonanceScore = (float) ($contact['resonance_score'] ?? 0.0);
    $unreadCount = (int) ($contact['unread_count'] ?? 0);
    $hasMessages = !empty($contact['has_messages']);
    $combinedScore = $activityScore + $resonanceScore;

    $heat = match (true) {
        $unreadCount >= 3 || $combinedScore >= 13.0 => 'hot',
        $unreadCount >= 1 || $combinedScore >= 9.2 => 'warm',
        $hasMessages || $resonanceScore >= 2.5 => 'calm',
        default => 'dormant',
    };

    $label = match ($heat) {
        'hot' => 'plasma chaud',
        'warm' => 'en circulation',
        'calm' => $resonanceScore >= 2.5 ? 'résonance claire' : 'stable',
        default => 'latente',
    };

    return [
        'activity_score' => round($combinedScore, 3),
        'activity_heat' => $heat,
        'activity_label' => $label,
        'contact_score' => round($combinedScore, 3),
    ];
}

function signal_conversation_summaries(array $land): array
{
    $pdo = get_pdo();
    $slug = normalize_username((string) ($land['slug'] ?? ''));

    $sql = <<<'SQL'
SELECT
    CASE
            WHEN sender_land_slug = :sender_slug_case THEN receiver_land_slug
        ELSE sender_land_slug
    END AS counterpart_slug,
    MAX(created_at) AS last_message_at,
        SUM(CASE WHEN receiver_land_slug = :receiver_slug_unread AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_count,
    SUBSTRING_INDEX(GROUP_CONCAT(subject ORDER BY created_at DESC SEPARATOR '\n'), '\n', 1) AS last_subject,
    SUBSTRING_INDEX(GROUP_CONCAT(body ORDER BY created_at DESC SEPARATOR '\n---\n'), '\n---\n', 1) AS last_body
FROM signal_messages
    WHERE sender_land_slug = :sender_slug_filter OR receiver_land_slug = :receiver_slug_filter
GROUP BY counterpart_slug
ORDER BY last_message_at DESC
SQL;

    $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'sender_slug_case' => $slug,
            'receiver_slug_unread' => $slug,
            'sender_slug_filter' => $slug,
            'receiver_slug_filter' => $slug,
        ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $landsBySlug = [];
    foreach (land_snapshot() as $candidate) {
        $candidateSlug = trim((string) ($candidate['slug'] ?? ''));
        if ($candidateSlug !== '') {
            $landsBySlug[$candidateSlug] = $candidate;
        }
    }

    return array_map(static function (array $row) use ($landsBySlug, $land): array {
        $counterpartSlug = trim((string) ($row['counterpart_slug'] ?? ''));
        $counterpartLand = $landsBySlug[$counterpartSlug] ?? null;
        $summary = [
            'counterpart_slug' => $counterpartSlug,
            'counterpart_username' => trim((string) ($counterpartLand['username'] ?? $counterpartSlug)),
            'unread_count' => (int) ($row['unread_count'] ?? 0),
            'last_subject' => trim((string) ($row['last_subject'] ?? '')),
            'last_body' => trim((string) ($row['last_body'] ?? '')),
            'last_message_at' => trim((string) ($row['last_message_at'] ?? '')),
        ];
        $activity = signal_contact_activity($summary);
        $resonance = signal_contact_resonance($land, $counterpartLand);

        return [...$summary, ...$activity, ...$resonance, ...signal_contact_presence([...$summary, ...$activity, ...$resonance])];
    }, $rows);
}

function signal_contact_candidates(array $land): array
{
    $me = normalize_username((string) ($land['slug'] ?? ''));
    $summaries = signal_conversation_summaries($land);
    $seen = [];
    $contacts = [];

    foreach ($summaries as $summary) {
        $slug = trim((string) ($summary['counterpart_slug'] ?? ''));
        if ($slug === '' || $slug === $me) {
            continue;
        }
        $seen[$slug] = true;
        $contacts[] = $summary;
    }

    foreach (land_snapshot() as $candidate) {
        $slug = trim((string) ($candidate['slug'] ?? ''));
        if ($slug === '' || $slug === $me || isset($seen[$slug])) {
            continue;
        }
        $summary = [
            'counterpart_slug' => $slug,
            'counterpart_username' => trim((string) ($candidate['username'] ?? $slug)),
            'unread_count' => 0,
            'last_subject' => '',
            'last_body' => '',
            'last_message_at' => '',
        ];
        $activity = signal_contact_activity($summary);
        $resonance = signal_contact_resonance($land, $candidate);
        $contacts[] = [...$summary, ...$activity, ...$resonance, ...signal_contact_presence([...$summary, ...$activity, ...$resonance])];
    }

    usort(
        $contacts,
        static function (array $left, array $right): int {
            $scoreComparison = ((float) ($right['contact_score'] ?? $right['activity_score'] ?? 0.0)) <=> ((float) ($left['contact_score'] ?? $left['activity_score'] ?? 0.0));
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            $timestampComparison = ((int) ($right['last_message_ts'] ?? 0)) <=> ((int) ($left['last_message_ts'] ?? 0));
            if ($timestampComparison !== 0) {
                return $timestampComparison;
            }

            return strcmp(
                trim((string) ($left['counterpart_username'] ?? $left['counterpart_slug'] ?? '')),
                trim((string) ($right['counterpart_username'] ?? $right['counterpart_slug'] ?? ''))
            );
        }
    );

    return $contacts;
}

function signal_load_conversation(array $land, string $otherSlug): array
{
    $pdo = get_pdo();
    $mySlug = normalize_username((string) ($land['slug'] ?? ''));
    $otherSlug = normalize_username($otherSlug);

    $update = $pdo->prepare('UPDATE signal_messages SET read_at = UTC_TIMESTAMP() WHERE sender_land_slug = ? AND receiver_land_slug = ? AND read_at IS NULL');
    $update->execute([$otherSlug, $mySlug]);

    $stmt = $pdo->prepare(
        'SELECT * FROM signal_messages WHERE (sender_land_slug = ? AND receiver_land_slug = ?) OR (sender_land_slug = ? AND receiver_land_slug = ?) ORDER BY created_at ASC'
    );
    $stmt->execute([$mySlug, $otherSlug, $otherSlug, $mySlug]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function signal_render_conversation_html(array $conversation, array $land, bool $showSubject = true, string $emptyMessage = 'Aucune trace encore entre vos deux boîtes.'): string
{
    ob_start();

    if (empty($conversation)) {
        ?>
        <p class="panel-copy"><?= h($emptyMessage) ?></p>
        <?php
        return (string) ob_get_clean();
    }

    foreach ($conversation as $entry) {
        $isMine = (string) ($entry['sender_land_slug'] ?? '') === (string) ($land['slug'] ?? '');
        ?>
        <div class="echo-msg <?= $isMine ? 'echo-msg--sent' : 'echo-msg--received' ?>">
            <span class="echo-msg-meta">
                <?= h((string) ($entry['sender_land_username'] ?? 'terre')) ?>
                · <?= h(human_created_label((string) ($entry['created_at'] ?? '')) ?? 'maintenant') ?>
            </span>
            <?php if ($showSubject && trim((string) ($entry['subject'] ?? '')) !== ''): ?>
                <strong><?= h((string) $entry['subject']) ?></strong><br>
            <?php endif; ?>
            <?= nl2br(h((string) ($entry['body'] ?? ''))) ?>
        </div>
        <?php
    }

    return (string) ob_get_clean();
}

function signal_render_echo_contacts_html(array $contacts, string $targetUsername = ''): string
{
    ob_start();

    ?>
    <div class="section-topline">
        <h2>Archipel connu</h2>
    </div>
    <?php foreach ($contacts as $contact): ?>
        <?php
        $username = trim((string) ($contact['username'] ?? ''));
        $unreadCount = (int) ($contact['unread_count'] ?? 0);
        ?>
        <a href="/echo?u=<?= rawurlencode($username) ?>" class="echo-contact <?= $username === $targetUsername ? 'is-active' : '' ?>">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <strong><?= h($username) ?></strong>
                <?php if ($unreadCount > 0): ?>
                    <span style="background: rgba(var(--land-secondary-rgb) / 0.8); color: var(--panel-rgb); font-size: 0.7rem; font-weight: 600; padding: 0.08rem 0.36rem; border-radius: 99px;"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    <?php

    return (string) ob_get_clean();
}

function signal_live_payload(array $land, ?string $targetIdentifier = null, string $view = 'signal'): array
{
    $resolvedView = strtolower(trim($view)) === 'echo' ? 'echo' : 'signal';
    $targetIdentifier = trim((string) $targetIdentifier);
    $targetLand = $targetIdentifier !== '' ? signal_find_land_by_identifier($targetIdentifier) : null;
    $conversation = [];

    if ($targetLand) {
        $conversation = signal_load_conversation($land, (string) ($targetLand['slug'] ?? ''));
    }

    $historyHtml = signal_render_conversation_html(
        $conversation,
        $land,
        $resolvedView !== 'echo',
        $resolvedView === 'echo'
            ? 'Le silence règne entre vos deux terres.'
            : 'Aucune trace encore entre vos deux boîtes.'
    );

    $latestEntry = !empty($conversation) ? $conversation[array_key_last($conversation)] : null;
    $payload = [
        'ok' => true,
        'view' => $resolvedView,
        'unread_total' => signal_unread_total($land),
        'message_count' => count($conversation),
        'history_html' => $historyHtml,
        'history_hash' => sha1($historyHtml),
        'latest_created_at' => trim((string) ($latestEntry['created_at'] ?? '')),
        'target' => $targetLand ? [
            'slug' => trim((string) ($targetLand['slug'] ?? '')),
            'username' => trim((string) ($targetLand['username'] ?? '')),
            'virtual_address' => signal_virtual_address($targetLand),
        ] : null,
    ];

    if ($resolvedView === 'echo') {
        $contacts = [];
        foreach (signal_contact_candidates($land) as $contact) {
            $contacts[] = [
                'username' => trim((string) ($contact['counterpart_username'] ?? '')),
                'slug' => trim((string) ($contact['counterpart_slug'] ?? '')),
                'unread_count' => (int) ($contact['unread_count'] ?? 0),
            ];
        }

        $payload['echo_contacts_html'] = signal_render_echo_contacts_html(
            $contacts,
            trim((string) ($targetLand['username'] ?? ''))
        );
    }

    return $payload;
}

function signal_send_message(array $land, string $receiverIdentifier, string $subject, string $body): void
{
    $pdo = get_pdo();
    $senderSlug = normalize_username((string) ($land['slug'] ?? ''));
    $senderUsername = trim((string) ($land['username'] ?? $senderSlug));
    $receiverLand = signal_find_land_by_identifier($receiverIdentifier);

    if (!$receiverLand) {
        throw new InvalidArgumentException('Cette terre n’existe pas.');
    }

    $receiverSlug = normalize_username((string) ($receiverLand['slug'] ?? ''));
    if ($receiverSlug === $senderSlug) {
        throw new InvalidArgumentException('Tu es déjà chez toi.');
    }

    $body = trim($body);
    $subject = trim($subject);

    if ($body === '') {
        throw new InvalidArgumentException('Le message ne peut pas rester vide.');
    }

    $subject = function_exists('mb_substr') ? mb_substr($subject, 0, 180) : substr($subject, 0, 180);
    $body = function_exists('mb_substr') ? mb_substr($body, 0, 12000) : substr($body, 0, 12000);

    $senderMailbox = ensure_signal_mailbox($land);
    $receiverMailbox = ensure_signal_mailbox($receiverLand);

    $insert = $pdo->prepare(
        'INSERT INTO signal_messages (sender_land_slug, sender_land_username, sender_virtual_email, receiver_land_slug, receiver_land_username, receiver_virtual_email, subject, body, delivered_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );
    $insert->execute([
        $senderSlug,
        $senderUsername,
        trim((string) ($senderMailbox['virtual_email'] ?? signal_virtual_address($land))),
        $receiverSlug,
        trim((string) ($receiverLand['username'] ?? $receiverSlug)),
        trim((string) ($receiverMailbox['virtual_email'] ?? signal_virtual_address($receiverLand))),
        $subject,
        $body,
    ]);
}

function signal_request_identity_verification(array $land, string $notificationEmail): array
{
    $notificationEmail = trim($notificationEmail);
    if ($notificationEmail === '' || !filter_var($notificationEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Adresse email invalide.');
    }

    $mailbox = ensure_signal_mailbox($land);
    $resendWait = signal_identity_seconds_until_resend($mailbox);
    if ($resendWait > 0) {
        throw new RuntimeException('Un lien vient déjà d’être généré. Réessaie dans ' . $resendWait . ' secondes.');
    }

    if (
        ($mailbox['identity_status'] ?? '') === SIGNAL_IDENTITY_VERIFIED
        && hash_equals(strtolower(trim((string) ($mailbox['notification_email'] ?? ''))), strtolower($notificationEmail))
    ) {
        return signal_mailbox_for_land($land);
    }

    $slug = normalize_username((string) ($land['slug'] ?? ''));
    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $slug . '|' . $token);
    $verificationUrl = site_origin() . '/signal?land=' . rawurlencode($slug) . '&verify=' . rawurlencode($token);
    $subject = 'Validation de votre identité Signal';
    $body = "Bonjour,\n\nValidez l’identité de la terre {$land['username']} pour activer la messagerie Signal et recevoir les notifications :\n\n{$verificationUrl}\n\nCe lien reste valide 24 heures.\n";
    $error = null;

    $deliveryMode = signal_identity_delivery_mode();
    if ($deliveryMode === 'log' || $deliveryMode === 'display') {
        error_log('[sowwwl][signal.identity] ' . $notificationEmail . ' ' . $verificationUrl);
    } elseif (!sowwwl_send_email($notificationEmail, $subject, $body, $error)) {
        throw new RuntimeException($error ?: 'Impossible d’envoyer l’email de validation.');
    }

    $pdo = get_pdo();
    $update = $pdo->prepare(
        'UPDATE signal_mailboxes SET notification_email = ?, identity_status = ?, verification_token_hash = ?, verification_token_sent_at = UTC_TIMESTAMP(), verified_at = NULL, updated_at = UTC_TIMESTAMP() WHERE land_slug = ?'
    );
    $update->execute([$notificationEmail, SIGNAL_IDENTITY_PENDING, $tokenHash, $slug]);

    return signal_mailbox_for_land($land);
}

function signal_verify_identity_token(string $landSlug, string $token): bool
{
    $pdo = get_pdo();
    $landSlug = normalize_username($landSlug);
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT land_slug, verification_token_hash, verification_token_sent_at FROM signal_mailboxes WHERE land_slug = ? LIMIT 1');
    $stmt->execute([$landSlug]);
    $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mailbox) {
        return false;
    }

    $sentAt = strtotime((string) ($mailbox['verification_token_sent_at'] ?? '')) ?: 0;
    if ($sentAt <= 0 || (time() - $sentAt) > SIGNAL_IDENTITY_TTL_SECONDS) {
        return false;
    }

    $expectedHash = (string) ($mailbox['verification_token_hash'] ?? '');
    $actualHash = hash('sha256', $landSlug . '|' . $token);
    if ($expectedHash === '' || !hash_equals($expectedHash, $actualHash)) {
        return false;
    }

    $update = $pdo->prepare('UPDATE signal_mailboxes SET identity_status = ?, verified_at = UTC_TIMESTAMP(), verification_token_hash = NULL, updated_at = UTC_TIMESTAMP() WHERE land_slug = ?');
    $update->execute([SIGNAL_IDENTITY_VERIFIED, $landSlug]);
    return true;
}

function signal_unread_total(array $land): int
{
    $pdo = get_pdo();
    $slug = normalize_username((string) ($land['slug'] ?? ''));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM signal_messages WHERE receiver_land_slug = ? AND read_at IS NULL');
    $stmt->execute([$slug]);
    return (int) $stmt->fetchColumn();
}
