<?php
declare(strict_types=1);

function str3m_storage_dir(): string
{
	return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'str3m';
}

function str3m_media_index_path(): string
{
	return str3m_storage_dir() . DIRECTORY_SEPARATOR . 'media_index.json';
}

function str3m_load_media_index(): array
{
	$path = str3m_media_index_path();
	if (!is_file($path)) {
		return [
			'version' => 1,
			'updated_at' => null,
			'items' => [],
		];
	}

	$raw = file_get_contents($path);
	if ($raw === false) {
		return [
			'version' => 1,
			'updated_at' => null,
			'items' => [],
		];
	}

	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return [
			'version' => 1,
			'updated_at' => null,
			'items' => [],
		];
	}

	$items = [];
	foreach (($decoded['items'] ?? []) as $item) {
		if (!is_array($item)) {
			continue;
		}

		$normalized = str3m_normalize_media_item($item);
		if ($normalized !== null) {
			$items[] = $normalized;
		}
	}

	return [
		'version' => (int) ($decoded['version'] ?? 1),
		'updated_at' => is_string($decoded['updated_at'] ?? null) ? $decoded['updated_at'] : null,
		'items' => $items,
	];
}

function str3m_normalize_media_item(array $item): ?array
{
	$id = trim((string) ($item['id'] ?? ''));
	$type = strtolower(trim((string) ($item['type'] ?? '')));
	$title = trim((string) ($item['title'] ?? ''));
	$path = trim((string) ($item['path'] ?? ''));

	if ($id === '' || $title === '' || $path === '') {
		return null;
	}

	$allowedTypes = ['text', 'image', 'audio', 'video', 'model'];
	if (!in_array($type, $allowedTypes, true)) {
		return null;
	}

	$slug = trim((string) ($item['slug'] ?? ''));
	if ($slug === '') {
		try {
			$slug = normalize_username($title);
		} catch (Throwable) {
			$slug = $id;
		}
	}

	$weight = (float) ($item['weight'] ?? 1);
	if (!is_finite($weight) || $weight <= 0) {
		$weight = 1;
	}

	return [
		'id' => $id,
		'type' => $type,
		'title' => $title,
		'slug' => $slug,
		'path' => $path,
		'land_slug' => trim((string) ($item['land_slug'] ?? '')),
		'created_at' => trim((string) ($item['created_at'] ?? '')),
		'published_at' => trim((string) ($item['published_at'] ?? '')),
		'status' => strtolower(trim((string) ($item['status'] ?? 'draft'))),
		'weight' => $weight,
		'tags' => array_values(array_filter(array_map('strval', is_array($item['tags'] ?? null) ? $item['tags'] : []), static fn (string $value): bool => trim($value) !== '')),
		'moods' => array_values(array_filter(array_map(static fn ($value): string => strtolower(trim((string) $value)), is_array($item['moods'] ?? null) ? $item['moods'] : []), static fn (string $value): bool => $value !== '')),
		'visibility' => strtolower(trim((string) ($item['visibility'] ?? 'public'))),
		'layout_hints' => array_values(array_filter(array_map(static fn ($value): string => strtolower(trim((string) $value)), is_array($item['layout_hints'] ?? null) ? $item['layout_hints'] : []), static fn (string $value): bool => $value !== '')),
		'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
	];
}

function str3m_filter_publishable_items(array $items, ?string $landSlug = null): array
{
	$landSlug = trim((string) $landSlug);
	$now = time();

	return array_values(array_filter($items, static function (array $item) use ($landSlug, $now): bool {
		if (($item['status'] ?? 'draft') !== 'published') {
			return false;
		}

		if (($item['visibility'] ?? 'public') !== 'public') {
			return false;
		}

		$publishedAt = trim((string) ($item['published_at'] ?? ''));
		if ($publishedAt !== '') {
			$timestamp = strtotime($publishedAt);
			if ($timestamp === false || $timestamp > $now) {
				return false;
			}
		}

		$itemLandSlug = trim((string) ($item['land_slug'] ?? ''));
		if ($landSlug === '') {
			return true;
		}

		return $itemLandSlug === '' || $itemLandSlug === $landSlug;
	}));
}

function str3m_group_items_by_type(array $items): array
{
	$groups = [
		'text' => [],
		'image' => [],
		'audio' => [],
		'video' => [],
		'model' => [],
	];

	foreach ($items as $item) {
		$type = (string) ($item['type'] ?? '');
		if (!array_key_exists($type, $groups)) {
			continue;
		}

		$groups[$type][] = $item;
	}

	return $groups;
}

function str3m_resolve_media_path(array $item): string
{
	$path = trim((string) ($item['path'] ?? ''));
	if ($path === '') {
		return '';
	}

	if (preg_match('#^https?://#i', $path)) {
		return $path;
	}

	if ($path[0] === '/') {
		return $path;
	}

	return '/' . ltrim($path, '/');
}

function str3m_load_text_body(array $item): string
{
	if (($item['type'] ?? null) !== 'text') {
		return '';
	}

	$relativePath = trim((string) ($item['path'] ?? ''));
	if ($relativePath === '') {
		return '';
	}

	$normalized = ltrim($relativePath, '/');
	$absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
	if (!is_file($absolutePath)) {
		return '';
	}

	$raw = file_get_contents($absolutePath);
	return is_string($raw) ? trim($raw) : '';
}