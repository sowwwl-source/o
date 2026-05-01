<?php
declare(strict_types=1);

if (!function_exists('get_pdo')) {
    require_once dirname(__DIR__) . '/config.php';
}

const SIGNAL_IDENTITY_PENDING = 'pending';
const SIGNAL_IDENTITY_UNVERIFIED = 'unverified';
const SIGNAL_IDENTITY_VERIFIED = 'verified';
const SIGNAL_IDENTITY_TTL_SECONDS = 86400;

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

function signal_conversation_summaries(array $land): array
{
    $pdo = get_pdo();
    $slug = normalize_username((string) ($land['slug'] ?? ''));

    $sql = <<<'SQL'
SELECT
    CASE
        WHEN sender_land_slug = :slug THEN receiver_land_slug
        ELSE sender_land_slug
    END AS counterpart_slug,
    MAX(created_at) AS last_message_at,
    SUM(CASE WHEN receiver_land_slug = :slug AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_count,
    SUBSTRING_INDEX(GROUP_CONCAT(subject ORDER BY created_at DESC SEPARATOR '\n'), '\n', 1) AS last_subject,
    SUBSTRING_INDEX(GROUP_CONCAT(body ORDER BY created_at DESC SEPARATOR '\n---\n'), '\n---\n', 1) AS last_body
FROM signal_messages
WHERE sender_land_slug = :slug OR receiver_land_slug = :slug
GROUP BY counterpart_slug
ORDER BY last_message_at DESC
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['slug' => $slug]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $landsBySlug = [];
    foreach (land_snapshot() as $candidate) {
        $candidateSlug = trim((string) ($candidate['slug'] ?? ''));
        if ($candidateSlug !== '') {
            $landsBySlug[$candidateSlug] = $candidate;
        }
    }

    return array_map(static function (array $row) use ($landsBySlug): array {
        $counterpartSlug = trim((string) ($row['counterpart_slug'] ?? ''));
        $counterpartLand = $landsBySlug[$counterpartSlug] ?? null;

        return [
            'counterpart_slug' => $counterpartSlug,
            'counterpart_username' => trim((string) ($counterpartLand['username'] ?? $counterpartSlug)),
            'unread_count' => (int) ($row['unread_count'] ?? 0),
            'last_subject' => trim((string) ($row['last_subject'] ?? '')),
            'last_body' => trim((string) ($row['last_body'] ?? '')),
            'last_message_at' => trim((string) ($row['last_message_at'] ?? '')),
        ];
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
        $contacts[] = [
            'counterpart_slug' => $slug,
            'counterpart_username' => trim((string) ($candidate['username'] ?? $slug)),
            'unread_count' => 0,
            'last_subject' => '',
            'last_body' => '',
            'last_message_at' => '',
        ];
    }

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

function signal_send_message(array $land, string $receiverIdentifier, string $subject, string $body): void
{
    $pdo = get_pdo();
    $senderSlug = normalize_username((string) ($land['slug'] ?? ''));
    $senderUsername = trim((string) ($land['username'] ?? $senderSlug));
    $receiverLand = find_land($receiverIdentifier);

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
    $slug = normalize_username((string) ($land['slug'] ?? ''));
    $token = bin2hex(random_bytes(24));
    $tokenHash = hash('sha256', $slug . '|' . $token);
    $verificationUrl = site_origin() . '/signal?land=' . rawurlencode($slug) . '&verify=' . rawurlencode($token);
    $subject = 'Validation de votre identité Signal';
    $body = "Bonjour,\n\nValidez l’identité de la terre {$land['username']} pour activer la messagerie Signal et recevoir les notifications :\n\n{$verificationUrl}\n\nCe lien reste valide 24 heures.\n";
    $error = null;

    if (!sowwwl_send_email($notificationEmail, $subject, $body, $error)) {
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
