<?php
declare(strict_types=1);

// ─── Storage ─────────────────────────────────────────────────────────────────

function t0k_storage_dir(): string
{
    return T0K_STORAGE_DIR;
}

function t0k_index_path(): string
{
    return t0k_storage_dir() . DIRECTORY_SEPARATOR . 'index.json';
}

function t0k_ensure_storage(): void
{
    $dir = t0k_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de préparer le stockage des t0ks.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Le stockage des t0ks n\'est pas accessible en écriture.');
    }
}

function t0k_read_index(): array
{
    $path = t0k_index_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function t0k_write_index(array $entries): void
{
    t0k_ensure_storage();
    $path  = t0k_index_path();
    $json  = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Impossible de sérialiser l\'index des t0ks.');
    }
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d\'écrire l\'index des t0ks.');
    }
}

// ─── Token generation ─────────────────────────────────────────────────────────

function t0k_generate_token(): string
{
    // No ambiguous chars: no 0,O,I,1,L,U,V → 28 chars
    $charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $token   = '';
    $bytes   = random_bytes(8);
    for ($i = 0; $i < 8; $i++) {
        $token .= $charset[ord($bytes[$i]) % 32];
    }
    return $token;
}

function t0k_format_token(string $token): string
{
    $clean = strtoupper(str_replace('-', '', $token));
    return strlen($clean) === 8 ? substr($clean, 0, 4) . '-' . substr($clean, 4) : $clean;
}

function t0k_normalize_token(string $token): string
{
    return strtoupper(str_replace(['-', ' '], '', $token));
}

function t0k_generate_id(): string
{
    return 't0k_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
}

// ─── Finders ─────────────────────────────────────────────────────────────────

function t0k_find_by_id(string $id): ?array
{
    foreach (t0k_read_index() as $entry) {
        if (($entry['id'] ?? '') === $id) {
            return $entry;
        }
    }
    return null;
}

function t0k_find_by_token(string $token): ?array
{
    $needle = t0k_normalize_token($token);
    foreach (t0k_read_index() as $entry) {
        if (t0k_normalize_token((string) ($entry['token'] ?? '')) === $needle) {
            return $entry;
        }
    }
    return null;
}

function t0k_list_for_land(string $slug): array
{
    $slug  = strtolower(trim($slug));
    $items = [];
    foreach (t0k_read_index() as $entry) {
        if (($entry['from_land'] ?? '') === $slug || ($entry['to_land'] ?? '') === $slug) {
            $items[] = $entry;
        }
    }
    return $items;
}

function t0k_pending_for_land(string $slug): array
{
    $slug  = strtolower(trim($slug));
    $items = [];
    foreach (t0k_read_index() as $entry) {
        if (($entry['to_land'] ?? '') === $slug && ($entry['status'] ?? '') === 'pending') {
            $items[] = $entry;
        }
    }
    return $items;
}

function t0k_active_for_land(string $slug): array
{
    $slug  = strtolower(trim($slug));
    $items = [];
    foreach (t0k_read_index() as $entry) {
        if (
            (($entry['from_land'] ?? '') === $slug || ($entry['to_land'] ?? '') === $slug)
            && ($entry['status'] ?? '') === 'active'
        ) {
            $items[] = $entry;
        }
    }
    return $items;
}

function t0k_recent_public(int $limit = 20): array
{
    $items = array_filter(
        t0k_read_index(),
        static fn ($e) => ($e['status'] ?? '') !== 'declined' && ($e['status'] ?? '') !== 'dissolved'
    );
    return array_slice(array_values($items), 0, $limit);
}

// ─── Mutations ───────────────────────────────────────────────────────────────

function t0k_send(string $fromSlug, string $toSlug, string $notes = ''): array
{
    $fromSlug = strtolower(trim($fromSlug));
    $toSlug   = strtolower(trim($toSlug));

    if ($fromSlug === '') {
        throw new InvalidArgumentException('La land émettrice est requise.');
    }
    if ($toSlug === '') {
        throw new InvalidArgumentException('La land destinataire est requise.');
    }
    if ($fromSlug === $toSlug) {
        throw new InvalidArgumentException('Un t0k ne peut pas partir et arriver sur la même land.');
    }

    $existing = t0k_read_index();
    foreach ($existing as $entry) {
        $lands = [(string) ($entry['from_land'] ?? ''), (string) ($entry['to_land'] ?? '')];
        if (
            in_array($fromSlug, $lands, true)
            && in_array($toSlug, $lands, true)
            && in_array($entry['status'] ?? '', ['pending', 'active'], true)
        ) {
            throw new InvalidArgumentException('Un t0k est déjà en cours entre ces deux lands.');
        }
    }

    $entry = [
        'id'        => t0k_generate_id(),
        'from_land' => $fromSlug,
        'to_land'   => $toSlug,
        'token'     => t0k_generate_token(),
        'status'    => 'pending',
        'notes'     => substr(trim($notes), 0, 400),
        'sent_at'   => gmdate(DATE_ATOM),
        'formed_at' => null,
    ];

    array_unshift($existing, $entry);
    t0k_write_index($existing);

    return $entry;
}

function t0k_update_status(string $id, string $newStatus, ?string $actorSlug = null): array
{
    $index  = t0k_read_index();
    $found  = false;

    foreach ($index as &$entry) {
        if (($entry['id'] ?? '') !== $id) {
            continue;
        }

        $from = (string) ($entry['from_land'] ?? '');
        $to   = (string) ($entry['to_land'] ?? '');

        if ($actorSlug !== null) {
            $actor = strtolower(trim($actorSlug));
            match ($newStatus) {
                'active'    => $actor !== $to
                    ? throw new InvalidArgumentException('Seule la land destinataire peut accepter.')
                    : null,
                'declined'  => $actor !== $to
                    ? throw new InvalidArgumentException('Seule la land destinataire peut décliner.')
                    : null,
                'dissolved' => ($actor !== $from && $actor !== $to)
                    ? throw new InvalidArgumentException('Seule une land du n0us peut le dissoudre.')
                    : null,
                default     => null,
            };
        }

        $entry['status'] = $newStatus;
        if ($newStatus === 'active') {
            $entry['formed_at'] = gmdate(DATE_ATOM);
        }
        $found = true;
        break;
    }
    unset($entry);

    if (!$found) {
        throw new InvalidArgumentException('T0k introuvable.');
    }

    t0k_write_index($index);
    return t0k_find_by_id($id) ?? [];
}

function t0k_accept(string $id, string $actorSlug): array
{
    return t0k_update_status($id, 'active', $actorSlug);
}

function t0k_decline(string $id, string $actorSlug): array
{
    return t0k_update_status($id, 'declined', $actorSlug);
}

function t0k_dissolve(string $id, string $actorSlug): array
{
    return t0k_update_status($id, 'dissolved', $actorSlug);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function t0k_status_label(string $status): string
{
    return match ($status) {
        'pending'   => 'en chemin',
        'active'    => 'n0us actif',
        'declined'  => 'décliné',
        'dissolved' => 'dissous',
        default     => $status,
    };
}

function t0k_partner_slug(array $t0k, string $mySlag): string
{
    return ($t0k['from_land'] ?? '') === $mySlag
        ? (string) ($t0k['to_land'] ?? '')
        : (string) ($t0k['from_land'] ?? '');
}

function t0k_is_actor(array $t0k, string $slug): bool
{
    return ($t0k['from_land'] ?? '') === $slug || ($t0k['to_land'] ?? '') === $slug;
}
