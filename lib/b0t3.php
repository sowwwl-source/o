<?php
declare(strict_types=1);

function b0t3_storage_dir(): string
{
    return B0T3_STORAGE_DIR;
}

function b0t3_index_path(): string
{
    return b0t3_storage_dir() . DIRECTORY_SEPARATOR . 'index.json';
}

function b0t3_ensure_storage(): void
{
    $dir = b0t3_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de préparer le stockage des b0t3s.');
    }
}

function b0t3_read_index(): array
{
    $path = b0t3_index_path();
    if (!is_file($path)) {
        return [];
    }
    $raw     = file_get_contents($path);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function b0t3_write_index(array $entries): void
{
    b0t3_ensure_storage();
    $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents(b0t3_index_path(), $json, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d\'écrire l\'index des b0t3s.');
    }
}

function b0t3_kinds(): array
{
    return ['ia' => 'IA', 'human' => 'Humain', 'animal' => 'Animal', 'other' => 'Autre'];
}

function b0t3_list_for_shore(string $targetLand): array
{
    $slug = strtolower(trim($targetLand));
    return array_values(array_filter(
        b0t3_read_index(),
        static fn ($e) => ($e['target_land'] ?? '') === $slug && ($e['status'] ?? '') === 'active'
    ));
}

function b0t3_recent_public(int $limit = 16): array
{
    return array_slice(
        array_filter(b0t3_read_index(), static fn ($e) => ($e['status'] ?? '') === 'active'),
        0,
        $limit
    );
}

function b0t3_find_by_id(string $id): ?array
{
    foreach (b0t3_read_index() as $e) {
        if (($e['id'] ?? '') === $id) {
            return $e;
        }
    }
    return null;
}

function b0t3_deposit(string $targetLand, string $text, string $kind = 'human', float $instability = 0.25, string $originLand = ''): array
{
    $targetLand = strtolower(trim($targetLand));
    $text       = trim($text);

    if ($targetLand === '') {
        throw new InvalidArgumentException('La land cible est requise.');
    }
    if ($text === '' || mb_strlen($text) > 280) {
        throw new InvalidArgumentException('Le texte doit faire entre 1 et 280 caractères.');
    }
    if (!array_key_exists($kind, b0t3_kinds())) {
        $kind = 'other';
    }

    $entry = [
        'id'          => 'b0t3_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4)),
        'kind'        => $kind,
        'origin_land' => strtolower(trim($originLand)),
        'target_land' => $targetLand,
        'text'        => $text,
        'instability' => max(0.0, min(1.0, $instability)),
        'status'      => 'active',
        'created_at'  => gmdate(DATE_ATOM),
    ];

    $index = b0t3_read_index();
    array_unshift($index, $entry);
    $index = array_slice($index, 0, 2000);
    b0t3_write_index($index);

    return $entry;
}

function b0t3_dissolve(string $id, string $actorSlug): void
{
    $index = b0t3_read_index();
    $found = false;
    foreach ($index as &$e) {
        if (($e['id'] ?? '') !== $id) {
            continue;
        }
        if ($actorSlug !== '' && ($e['origin_land'] ?? '') !== $actorSlug && ($e['target_land'] ?? '') !== $actorSlug) {
            throw new InvalidArgumentException('Non autorisé.');
        }
        $e['status'] = 'dissolved';
        $found = true;
        break;
    }
    unset($e);
    if (!$found) {
        throw new InvalidArgumentException('B0t3 introuvable.');
    }
    b0t3_write_index($index);
}
