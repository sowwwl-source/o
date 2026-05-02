<?php
declare(strict_types=1);

// ─── Storage ─────────────────────────────────────────────────────────────────

function n0de_storage_dir(): string
{
    return N0DE_STORAGE_DIR;
}

function n0de_index_path(): string
{
    return n0de_storage_dir() . DIRECTORY_SEPARATOR . 'index.json';
}

function n0de_ensure_storage(): void
{
    $dir = n0de_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de préparer le stockage des n0des.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Le stockage des n0des n\'est pas accessible en écriture.');
    }
}

function n0de_read_index(): array
{
    $path = n0de_index_path();
    if (!is_file($path)) {
        return [];
    }
    $raw     = file_get_contents($path);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function n0de_write_index(array $entries): void
{
    n0de_ensure_storage();
    $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents(n0de_index_path(), $json, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d\'écrire l\'index des n0des.');
    }
}

// ─── Token & ID ──────────────────────────────────────────────────────────────

function n0de_generate_token(): string
{
    // Distinct charset from t0k — adds clarity when mixing both
    $charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $raw     = random_bytes(8);
    $token   = '';
    for ($i = 0; $i < 8; $i++) {
        $token .= $charset[ord($raw[$i]) % 32];
    }
    return $token;
}

function n0de_format_token(string $token): string
{
    $clean = strtoupper(str_replace(['-', ' '], '', $token));
    return strlen($clean) === 8 ? substr($clean, 0, 4) . '-' . substr($clean, 4) : $clean;
}

function n0de_normalize_token(string $token): string
{
    return strtoupper(str_replace(['-', ' '], '', $token));
}

function n0de_generate_id(): string
{
    return 'n0de_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
}

// ─── Kind helpers ─────────────────────────────────────────────────────────────

function n0de_kinds(): array
{
    return [
        'nfc'   => 'Puce NFC',
        'qr'    => 'QR Code',
        'sd'    => 'Carte SD',
        'mixed' => 'NFC + QR + SD',
    ];
}

function n0de_kind_label(string $kind): string
{
    return n0de_kinds()[$kind] ?? $kind;
}

// ─── Finders ─────────────────────────────────────────────────────────────────

function n0de_find_by_id(string $id): ?array
{
    foreach (n0de_read_index() as $entry) {
        if (($entry['id'] ?? '') === $id) {
            return $entry;
        }
    }
    return null;
}

function n0de_find_by_token(string $token): ?array
{
    $needle = n0de_normalize_token($token);
    foreach (n0de_read_index() as $entry) {
        if (n0de_normalize_token((string) ($entry['token'] ?? '')) === $needle) {
            return $entry;
        }
    }
    return null;
}

function n0de_list_for_land(string $slug): array
{
    $slug = strtolower(trim($slug));
    return array_values(
        array_filter(n0de_read_index(), static fn ($e) => ($e['land_slug'] ?? '') === $slug)
    );
}

// ─── Mutations ───────────────────────────────────────────────────────────────

function n0de_register(string $landSlug, string $kind, string $label, string $t0kId = ''): array
{
    $landSlug = strtolower(trim($landSlug));
    if ($landSlug === '') {
        throw new InvalidArgumentException('La land est requise pour enregistrer un n0de.');
    }
    if (!array_key_exists($kind, n0de_kinds())) {
        throw new InvalidArgumentException('Type de n0de inconnu : ' . $kind);
    }

    $entry = [
        'id'            => n0de_generate_id(),
        'land_slug'     => $landSlug,
        'token'         => n0de_generate_token(),
        'kind'          => $kind,
        'label'         => substr(trim($label), 0, 80) ?: n0de_kind_label($kind),
        't0k_id'        => $t0kId,
        'created_at'    => gmdate(DATE_ATOM),
        'last_sync'     => null,
    ];

    $index = n0de_read_index();
    array_unshift($index, $entry);
    n0de_write_index($index);

    return $entry;
}

function n0de_delete(string $id, string $landSlug): void
{
    $index  = n0de_read_index();
    $before = count($index);
    $index  = array_values(
        array_filter($index, static fn ($e) => !(($e['id'] ?? '') === $id && ($e['land_slug'] ?? '') === $landSlug))
    );
    if (count($index) === $before) {
        throw new InvalidArgumentException('N0de introuvable ou non autorisé.');
    }
    n0de_write_index($index);
}

// ─── Manifest (for SD card) ───────────────────────────────────────────────────

function n0de_build_manifest(array $n0de, array $land): array
{
    return [
        'n0de'       => n0de_format_token((string) $n0de['token']),
        'token_raw'  => (string) $n0de['token'],
        'kind'       => (string) $n0de['kind'],
        'label'      => (string) $n0de['label'],
        'land'       => (string) $n0de['land_slug'],
        'username'   => (string) ($land['username'] ?? $n0de['land_slug']),
        'sync_url'   => site_origin() . '/n0de.php?sync=' . rawurlencode((string) $n0de['token']),
        'land_url'   => site_origin() . '/land.php?u=' . rawurlencode((string) $n0de['land_slug']),
        'shore_url'  => site_origin() . '/sh0re.php?u=' . rawurlencode((string) $n0de['land_slug']),
        'created_at' => (string) $n0de['created_at'],
        'exported_at' => gmdate(DATE_ATOM),
        'version'    => '1.0',
        'network'    => '3ternet',
    ];
}

// ─── NFC & QR helpers ────────────────────────────────────────────────────────

function n0de_nfc_url(array $n0de): string
{
    return site_origin() . '/n?t=' . rawurlencode((string) $n0de['token']);
}

function n0de_qr_data(array $n0de): string
{
    return n0de_nfc_url($n0de);
}
