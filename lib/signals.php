<?php
declare(strict_types=1);

const SIGNAL_KINDS = ['note', 'pulse', 'image', 'link', 'fragment'];
const SIGNAL_VISIBILITIES = ['public', 'private', 'unlisted'];
const SIGNAL_STATUSES = ['draft', 'published'];

function signals_dir(): string
{
    return __DIR__ . '/../storage/signals';
}

function signals_items_dir(): string
{
    return signals_dir() . '/items';
}

function signals_index_path(): string
{
    return signals_dir() . '/index.json';
}

function signal_file_path(string $id): string
{
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?? '';
    return signals_items_dir() . '/' . $safeId . '.json';
}

function ensure_signals_storage(): void
{
    foreach ([signals_dir(), signals_items_dir()] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de préparer le stockage des signaux.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Le stockage des signaux n’est pas accessible en écriture pour le moment.');
        }
    }

    $indexPath = signals_index_path();
    if (!is_file($indexPath)) {
        $written = file_put_contents(
            $indexPath,
            json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException('Impossible d’initialiser l’index des signaux.');
        }
    }
}

function generate_signal_id(): string
{
    return bin2hex(random_bytes(8));
}

function normalize_signal_kind(string $kind): string
{
    $kind = strtolower(trim($kind));
    return in_array($kind, SIGNAL_KINDS, true) ? $kind : 'note';
}

function normalize_signal_visibility(string $visibility): string
{
    $visibility = strtolower(trim($visibility));
    return in_array($visibility, SIGNAL_VISIBILITIES, true) ? $visibility : 'private';
}

function normalize_signal_status(string $status): string
{
    $status = strtolower(trim($status));
    return in_array($status, SIGNAL_STATUSES, true) ? $status : 'draft';
}

function normalize_signal_media_ids(array $mediaIds): array
{
    $normalized = [];

    foreach ($mediaIds as $mediaId) {
        $mediaId = trim((string) $mediaId);
        if ($mediaId === '') {
            continue;
        }

        $normalized[] = preg_replace('/[^a-zA-Z0-9_-]/', '', $mediaId) ?? '';
    }

    $normalized = array_values(array_filter($normalized, static fn (string $value): bool => $value !== ''));
    return array_values(array_unique($normalized));
}

function signal_limit_text(string $value, int $maxLength): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function signal_slug_from_title(string $title): string
{
    try {
        return normalize_username($title);
    } catch (InvalidArgumentException $exception) {
        return 'signal';
    }
}

function signal_normalize_tags(string|array|null $rawTags): array
{
    if ($rawTags === null) {
        return [];
    }

    $candidates = is_array($rawTags) ? $rawTags : explode(',', $rawTags);
    $normalized = [];

    foreach ($candidates as $tag) {
        $tag = trim((string) $tag);
        if ($tag === '') {
            continue;
        }

        $tag = strtolower($tag);
        $tag = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $tag) ?? '';
        $tag = trim($tag, '-_');

        if ($tag !== '') {
            $normalized[] = signal_limit_text($tag, 32);
        }
    }

    return array_values(array_unique($normalized));
}

function signal_excerpt(string $body, int $maxLength = 180): string
{
    $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
    if ($body === '') {
        return '';
    }

    $excerpt = signal_limit_text($body, $maxLength);
    return $excerpt === $body ? $excerpt : rtrim($excerpt) . '…';
}

function validate_signal_payload(array $input, array $land): array
{
    $landSlug = trim((string) ($land['slug'] ?? ''));
    if ($landSlug === '') {
        throw new InvalidArgumentException('Aucune terre authentifiée ne peut porter ce signal.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));

    if ($body === '') {
        throw new InvalidArgumentException('Le corps du signal ne peut pas rester vide.');
    }

    if ($title === '') {
        $title = 'Signal ' . date('Y-m-d H:i');
    }

    $title = signal_limit_text($title, 120);
    $body = signal_limit_text($body, 12000);

    $kind = normalize_signal_kind((string) ($input['kind'] ?? 'note'));
    $visibility = normalize_signal_visibility((string) ($input['visibility'] ?? 'private'));
    $status = normalize_signal_status((string) ($input['status'] ?? 'draft'));
    $tags = signal_normalize_tags($input['tags'] ?? null);
    $mediaIds = normalize_signal_media_ids(is_array($input['media_ids'] ?? null) ? $input['media_ids'] : []);

    $now = gmdate(DATE_ATOM);
    $slugBase = signal_slug_from_title($title);

    return [
        'id' => generate_signal_id(),
        'slug' => $slugBase . '-' . substr(generate_signal_id(), 0, 6),
        'land_slug' => $landSlug,
        'land_username' => trim((string) ($land['username'] ?? $landSlug)),
        'title' => $title,
        'body' => $body,
        'kind' => $kind,
        'visibility' => $visibility,
        'status' => $status,
        'tags' => $tags,
        'media_ids' => $mediaIds,
        'excerpt' => signal_excerpt($body),
        'created_at' => $now,
        'updated_at' => $now,
        'published_at' => $status === 'published' ? $now : null,
    ];
}

function write_signal(array $signal): void
{
    ensure_signals_storage();

    $signal['id'] = trim((string) ($signal['id'] ?? ''));
    if ($signal['id'] === '') {
        throw new RuntimeException('Le signal ne possède pas d’identifiant exploitable.');
    }

    $encoded = json_encode($signal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) {
        throw new RuntimeException('Impossible d’encoder ce signal.');
    }

    $written = file_put_contents(signal_file_path($signal['id']), $encoded . PHP_EOL, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Impossible d’écrire ce signal pour le moment.');
    }
}

function read_signal(string $id): ?array
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }

    $path = signal_file_path($id);
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function load_signals_index(): array
{
    ensure_signals_storage();

    $raw = file_get_contents(signals_index_path());
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $index = [];
    foreach ($decoded as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        if (!isset($entry['id']) && is_string($key) && $key !== '') {
            $entry['id'] = $key;
        }

        $entryId = trim((string) ($entry['id'] ?? ''));
        if ($entryId === '') {
            continue;
        }

        $index[$entryId] = $entry;
    }

    return $index;
}

function write_signals_index(array $index): void
{
    ensure_signals_storage();

    $encoded = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) {
        throw new RuntimeException('Impossible d’encoder l’index des signaux.');
    }

    $written = file_put_contents(signals_index_path(), $encoded . PHP_EOL, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Impossible de mettre à jour l’index des signaux.');
    }
}

function signal_index_entry(array $signal): array
{
    return [
        'id' => (string) ($signal['id'] ?? ''),
        'slug' => (string) ($signal['slug'] ?? ''),
        'land_slug' => (string) ($signal['land_slug'] ?? ''),
        'land_username' => (string) ($signal['land_username'] ?? ''),
        'title' => (string) ($signal['title'] ?? ''),
        'kind' => normalize_signal_kind((string) ($signal['kind'] ?? 'note')),
        'visibility' => normalize_signal_visibility((string) ($signal['visibility'] ?? 'private')),
        'status' => normalize_signal_status((string) ($signal['status'] ?? 'draft')),
        'excerpt' => signal_excerpt((string) (($signal['excerpt'] ?? '') !== '' ? $signal['excerpt'] : ($signal['body'] ?? ''))),
        'created_at' => (string) ($signal['created_at'] ?? ''),
        'published_at' => $signal['published_at'] ?? null,
    ];
}

function upsert_signal_index_entry(array $signal): void
{
    $entry = signal_index_entry($signal);
    $id = trim((string) ($entry['id'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Impossible d’indexer un signal sans identifiant.');
    }

    $index = load_signals_index();
    $index[$id] = $entry;
    write_signals_index($index);
}

function signal_timestamp(array $signal): int
{
    $candidate = (string) (($signal['published_at'] ?? '') ?: ($signal['created_at'] ?? ''));
    $timestamp = strtotime($candidate);
    return $timestamp !== false ? $timestamp : 0;
}

function sort_signals_reverse_chronological(array $signals): array
{
    usort(
        $signals,
        static fn (array $left, array $right): int => signal_timestamp($right) <=> signal_timestamp($left)
    );

    return $signals;
}

function list_signals(?string $landSlug = null, ?string $status = null): array
{
    $landSlug = $landSlug !== null ? trim($landSlug) : null;
    $status = $status !== null ? normalize_signal_status($status) : null;
    $signals = [];

    foreach (load_signals_index() as $signal) {
        if ($landSlug !== null && (string) ($signal['land_slug'] ?? '') !== $landSlug) {
            continue;
        }

        if ($status !== null && (string) ($signal['status'] ?? '') !== $status) {
            continue;
        }

        $signals[] = $signal;
    }

    return sort_signals_reverse_chronological($signals);
}

function list_public_signals(): array
{
    $signals = [];

    foreach (load_signals_index() as $signal) {
        if (
            (string) ($signal['status'] ?? '') === 'published'
            && (string) ($signal['visibility'] ?? '') === 'public'
        ) {
            $signals[] = $signal;
        }
    }

    return sort_signals_reverse_chronological($signals);
}

function signal_is_owner(array $signal, ?array $currentLand = null): bool
{
    if (!$currentLand) {
        return false;
    }

    return hash_equals(
        trim((string) ($signal['land_slug'] ?? '')),
        trim((string) ($currentLand['slug'] ?? ''))
    );
}

function signal_is_publicly_accessible(array $signal): bool
{
    return (string) ($signal['status'] ?? '') === 'published'
        && in_array((string) ($signal['visibility'] ?? ''), ['public', 'unlisted'], true);
}

function signal_can_view(array $signal, ?array $currentLand = null): bool
{
    return signal_is_owner($signal, $currentLand) || signal_is_publicly_accessible($signal);
}

function create_signal(array $input, array $land): array
{
    $signal = validate_signal_payload($input, $land);
    write_signal($signal);
    upsert_signal_index_entry($signal);
    return $signal;
}