<?php
declare(strict_types=1);

function aza_memory_allowed_views(): array
{
    return [
        'chrono' => 'Chronologie',
        'family' => 'Familles',
        'format' => 'Formats',
        'size' => 'Tailles',
        'source' => 'Sources',
        'visual' => 'Visuel',
        'finder' => 'Finder',
    ];
}

function aza_memory_normalize_view(string $view): string
{
    $view = strtolower(trim($view));
    return array_key_exists($view, aza_memory_allowed_views()) ? $view : 'chrono';
}

function aza_memory_query_href(array $baseQuery, array $overrides = []): string
{
    $query = array_merge($baseQuery, $overrides);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }

    $encoded = http_build_query($query);
    return '/aza' . ($encoded !== '' ? '?' . $encoded : '');
}

function aza_memory_search_normalize(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function aza_memory_allowed_sorts(): array
{
    return [
        'newest',
        'oldest',
        'title',
        'size_desc',
        'size_asc',
    ];
}

function aza_memory_normalize_sort(string $sort): string
{
    $sort = strtolower(trim($sort));
    return in_array($sort, aza_memory_allowed_sorts(), true) ? $sort : 'newest';
}

function aza_memory_parse_date_hint(string $dateHint): ?array
{
    $dateHint = trim($dateHint);
    if ($dateHint === '') {
        return null;
    }

    if (preg_match('/\b(19\d{2}|20\d{2})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])\b/', $dateHint, $matches)) {
        return [
            'sort' => sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]),
            'label' => $matches[0],
            'origin' => 'Époque indiquée',
        ];
    }

    if (preg_match('/\b(19\d{2}|20\d{2})-(0[1-9]|1[0-2])\b/', $dateHint, $matches)) {
        return [
            'sort' => sprintf('%04d-%02d-01', (int) $matches[1], (int) $matches[2]),
            'label' => $matches[0],
            'origin' => 'Époque indiquée',
        ];
    }

    $seasonMonth = [
        'printemps' => 3,
        'ete' => 6,
        'été' => 6,
        'summer' => 6,
        'automne' => 9,
        'autumn' => 9,
        'hiver' => 12,
        'winter' => 12,
    ];
    $normalized = aza_memory_search_normalize($dateHint);
    if (preg_match('/\b(19\d{2}|20\d{2})\b/', $dateHint, $yearMatch)) {
        $year = (int) $yearMatch[1];
        foreach ($seasonMonth as $needle => $month) {
            if (str_contains($normalized, $needle)) {
                return [
                    'sort' => sprintf('%04d-%02d-01', $year, $month),
                    'label' => $dateHint,
                    'origin' => 'Époque indiquée',
                ];
            }
        }

        return [
            'sort' => sprintf('%04d-01-01', $year),
            'label' => $dateHint,
            'origin' => 'Époque indiquée',
        ];
    }

    $timestamp = strtotime($dateHint);
    if ($timestamp !== false) {
        return [
            'sort' => gmdate('Y-m-d', $timestamp),
            'label' => $dateHint,
            'origin' => 'Époque indiquée',
        ];
    }

    return null;
}

function aza_memory_archive_date_info(array $archive): array
{
    $chronologyLabel = trim((string) ($archive['chronology_label'] ?? ''));
    $chronologyOrigin = trim((string) ($archive['chronology_origin_label'] ?? 'Date de dépôt'));
    $startYear = (int) ($archive['chronology_start_year'] ?? 0);
    if ($startYear > 0) {
        return [
            'sort' => sprintf('%04d-01-01', $startYear),
            'label' => $chronologyLabel !== '' ? $chronologyLabel : (string) $startYear,
            'origin' => $chronologyOrigin,
        ];
    }

    $years = array_values(array_filter(array_map('intval', is_array($archive['years'] ?? null) ? $archive['years'] : [])));
    if ($years) {
        sort($years);
        return [
            'sort' => sprintf('%04d-01-01', (int) $years[0]),
            'label' => $chronologyLabel !== '' ? $chronologyLabel : implode(' · ', array_map('strval', $years)),
            'origin' => $chronologyOrigin,
        ];
    }

    $createdAt = trim((string) ($archive['created_at'] ?? ''));
    if ($createdAt !== '') {
        $timestamp = strtotime($createdAt);
        if ($timestamp !== false) {
            return [
                'sort' => gmdate('Y-m-d', $timestamp),
                'label' => human_created_label($createdAt) ?? 'maintenant',
                'origin' => 'Date de dépôt',
            ];
        }
    }

    return [
        'sort' => '0000-00-00',
        'label' => $chronologyLabel !== '' ? $chronologyLabel : 'Atemporel',
        'origin' => $chronologyOrigin,
    ];
}

function aza_memory_file_date_info(array $file): array
{
    $dateHint = trim((string) ($file['date_hint'] ?? ''));
    $hint = aza_memory_parse_date_hint($dateHint);
    if ($hint !== null) {
        return $hint;
    }

    $createdAt = trim((string) ($file['created_at'] ?? ''));
    if ($createdAt !== '') {
        $timestamp = strtotime($createdAt);
        if ($timestamp !== false) {
            return [
                'sort' => gmdate('Y-m-d', $timestamp),
                'label' => human_created_label($createdAt) ?? 'maintenant',
                'origin' => 'Date de dépôt',
            ];
        }
    }

    return [
        'sort' => '0000-00-00',
        'label' => 'Atemporel',
        'origin' => 'Date de dépôt',
    ];
}

function aza_memory_family_label(string $family): string
{
    $family = strtolower(trim($family));
    if ($family === '') {
        return 'Autre';
    }

    $ingestFamilies = aza_ingest_format_families();
    if (array_key_exists($family, $ingestFamilies)) {
        return aza_ingest_family_label($family);
    }

    return aza_label_content_family($family);
}

function aza_memory_visual_family_keys(): array
{
    return ['image', 'video', '3d', 'design', 'media'];
}

function aza_memory_is_visual_item(array $item): bool
{
    $families = array_map(static fn ($value): string => strtolower(trim((string) $value)), $item['families'] ?? []);
    foreach (aza_memory_visual_family_keys() as $family) {
        if (in_array($family, $families, true)) {
            return true;
        }
    }

    $memoryTypes = array_map(static fn ($value): string => strtolower(trim((string) $value)), $item['memory_types'] ?? []);
    return in_array('visual', $memoryTypes, true);
}

function aza_memory_build_items(array $archives, array $files, ?array $sources = null): array
{
    $sources = is_array($sources) ? $sources : aza_supported_sources();
    $items = [];

    foreach ($archives as $archive) {
        $dateInfo = aza_memory_archive_date_info($archive);
        $families = array_values(array_filter(array_map('strval', is_array($archive['content_families'] ?? null) ? $archive['content_families'] : [])));
        $memoryTypes = array_values(array_filter(array_map('strval', is_array($archive['memory_types'] ?? null) ? $archive['memory_types'] : [])));
        $summary = trim((string) (($archive['memory_note'] ?? '') ?: ($archive['human_summary'] ?? '') ?: ($archive['notes'] ?? '')));
        $searchBlob = implode(' ', array_filter([
            (string) ($archive['label'] ?? ''),
            $summary,
            (string) ($archive['owner_slug'] ?? ''),
            (string) ($archive['source'] ?? ''),
            implode(' ', $families),
            implode(' ', $memoryTypes),
            implode(' ', array_map('strval', is_array($archive['years'] ?? null) ? $archive['years'] : [])),
        ]));

        $items[] = [
            'kind' => 'archive',
            'kind_label' => 'Archive ZIP',
            'title' => (string) ($archive['label'] ?? 'Archive'),
            'summary' => $summary,
            'owner_slug' => (string) ($archive['owner_slug'] ?? ''),
            'families' => $families,
            'families_labels' => array_map('aza_memory_family_label', $families),
            'memory_types' => $memoryTypes,
            'memory_type_labels' => array_map('aza_label_memory_type', $memoryTypes),
            'format_label' => 'ZIP',
            'size' => null,
            'size_label' => ((int) ($archive['entries'] ?? 0)) > 0 ? ((string) ((int) $archive['entries']) . ' entrées') : '',
            'date_label' => $dateInfo['label'],
            'date_origin' => $dateInfo['origin'],
            'memory_date_sort' => $dateInfo['sort'],
            'created_at' => (string) ($archive['created_at'] ?? ''),
            'source_key' => (string) ($archive['source'] ?? 'other'),
            'source_label' => (string) ($sources[$archive['source']] ?? ($archive['source'] ?? 'Archive')),
            'meta_label' => (string) ($sources[$archive['source']] ?? ($archive['source'] ?? 'Archive')),
            'thumbnail_url' => '',
            'href' => '/' . ltrim((string) ($archive['stored_file'] ?? ''), '/'),
            'search' => aza_memory_search_normalize($searchBlob),
            'raw' => $archive,
        ];
    }

    foreach ($files as $file) {
        $dateInfo = aza_memory_file_date_info($file);
        $family = (string) ($file['format_family'] ?? 'other');
        $summary = trim((string) (($file['notes'] ?? '') ?: ($file['date_hint'] ?? '') ?: aza_ingest_family_label($family)));
        $thumbnail = trim((string) ($file['thumbnail'] ?? ''));
        if ($thumbnail === '' && $family === 'image') {
            $thumbnail = '/' . ltrim((string) ($file['stored_file'] ?? ''), '/');
        } elseif ($thumbnail !== '') {
            $thumbnail = '/' . ltrim($thumbnail, '/');
        }

        $searchBlob = implode(' ', array_filter([
            (string) ($file['label'] ?? ''),
            $summary,
            (string) ($file['owner_slug'] ?? ''),
            (string) ($file['format'] ?? ''),
            $family,
        ]));

        $items[] = [
            'kind' => 'file',
            'kind_label' => 'Fichier libre',
            'title' => (string) ($file['label'] ?? 'Fichier'),
            'summary' => $summary,
            'owner_slug' => (string) ($file['owner_slug'] ?? ''),
            'families' => [$family],
            'families_labels' => [aza_ingest_family_label($family)],
            'memory_types' => $family === 'image' || $family === 'video' || $family === '3d' ? ['visual'] : [],
            'memory_type_labels' => $family === 'image' || $family === 'video' || $family === '3d' ? [aza_label_memory_type('visual')] : [],
            'format_label' => strtoupper((string) ($file['format'] ?? '')),
            'size' => (int) ($file['size'] ?? 0),
            'size_label' => aza_format_bytes((int) ($file['size'] ?? 0)),
            'date_label' => $dateInfo['label'],
            'date_origin' => $dateInfo['origin'],
            'memory_date_sort' => $dateInfo['sort'],
            'created_at' => (string) ($file['created_at'] ?? ''),
            'source_key' => 'free',
            'source_label' => 'Dépôt libre',
            'meta_label' => aza_ingest_family_label($family),
            'thumbnail_url' => $thumbnail,
            'href' => '/' . ltrim((string) ($file['stored_file'] ?? ''), '/'),
            'search' => aza_memory_search_normalize($searchBlob),
            'raw' => $file,
        ];
    }

    return aza_memory_sort_items($items, 'newest');
}

function aza_memory_group_files_by_format(array $files): array
{
    $groups = [];
    foreach ($files as $file) {
        $format = strtolower(trim((string) ($file['format'] ?? '')));
        if ($format === '') {
            $format = 'inconnu';
        }

        if (!isset($groups[$format])) {
            $groups[$format] = [
                'format' => $format,
                'label' => strtoupper($format),
                'items' => [],
            ];
        }

        $groups[$format]['items'][] = $file;
    }

    ksort($groups);
    foreach ($groups as &$group) {
        usort($group['items'], static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    }
    unset($group);

    return array_values($groups);
}

function aza_memory_group_files_by_size(array $files): array
{
    $buckets = [
        'micro' => ['label' => '1 Mo et moins', 'items' => []],
        'light' => ['label' => '1 à 10 Mo', 'items' => []],
        'dense' => ['label' => '10 à 100 Mo', 'items' => []],
        'heavy' => ['label' => '100 Mo et plus', 'items' => []],
    ];

    foreach ($files as $file) {
        $size = (int) ($file['size'] ?? 0);
        $bucket = match (true) {
            $size <= 1024 * 1024 => 'micro',
            $size <= 10 * 1024 * 1024 => 'light',
            $size <= 100 * 1024 * 1024 => 'dense',
            default => 'heavy',
        };
        $buckets[$bucket]['items'][] = $file;
    }

    foreach ($buckets as $key => &$bucket) {
        if (!$bucket['items']) {
            continue;
        }
        usort($bucket['items'], static fn (array $a, array $b): int => ((int) ($b['size'] ?? 0)) <=> ((int) ($a['size'] ?? 0)));
        $bucket['key'] = $key;
    }
    unset($bucket);

    return array_values(array_filter($buckets, static fn (array $bucket): bool => !empty($bucket['items'])));
}

function aza_memory_group_items_by_source(array $items): array
{
    $groups = [];
    foreach ($items as $item) {
        $key = (string) ($item['source_key'] ?? 'other');
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'label' => (string) ($item['source_label'] ?? $key),
                'items' => [],
            ];
        }

        $groups[$key]['items'][] = $item;
    }

    uasort($groups, static function (array $a, array $b): int {
        $countCompare = count($b['items']) <=> count($a['items']);
        return $countCompare !== 0 ? $countCompare : strcmp((string) $a['label'], (string) $b['label']);
    });

    return array_values($groups);
}

function aza_memory_filter_visual_items(array $items): array
{
    return array_values(array_filter($items, 'aza_memory_is_visual_item'));
}

function aza_memory_build_family_options(array $items): array
{
    $options = [];
    foreach ($items as $item) {
        foreach (($item['families'] ?? []) as $family) {
            $family = strtolower(trim((string) $family));
            if ($family === '') {
                continue;
            }
            $options[$family] = aza_memory_family_label($family);
        }
    }

    asort($options);
    return $options;
}

function aza_memory_filter_items(array $items, string $query, string $kind = 'all', string $family = 'all', string $source = 'all'): array
{
    $normalizedQuery = aza_memory_search_normalize($query);

    return array_values(array_filter($items, static function (array $item) use ($normalizedQuery, $kind, $family, $source): bool {
        if ($kind !== 'all' && ($item['kind'] ?? '') !== $kind) {
            return false;
        }
        if ($family !== 'all' && !in_array($family, $item['families'] ?? [], true)) {
            return false;
        }
        if ($source !== 'all' && ($item['source_key'] ?? '') !== $source) {
            return false;
        }
        if ($normalizedQuery === '') {
            return true;
        }

        return str_contains((string) ($item['search'] ?? ''), $normalizedQuery);
    }));
}

function aza_memory_sort_items(array $items, string $sort): array
{
    $sort = aza_memory_normalize_sort($sort);
    usort($items, static function (array $a, array $b) use ($sort): int {
        return match ($sort) {
            'oldest' => strcmp((string) ($a['memory_date_sort'] ?? ''), (string) ($b['memory_date_sort'] ?? '')),
            'title' => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')),
            'size_desc' => ((int) ($b['size'] ?? -1)) <=> ((int) ($a['size'] ?? -1)),
            'size_asc' => ((int) ($a['size'] ?? PHP_INT_MAX)) <=> ((int) ($b['size'] ?? PHP_INT_MAX)),
            default => strcmp((string) ($b['memory_date_sort'] ?? ''), (string) ($a['memory_date_sort'] ?? '')),
        };
    });

    return $items;
}

function aza_memory_summarize_items(array $items): array
{
    if (!$items) {
        return [
            'count' => 0,
            'first_trace' => null,
            'last_trace' => null,
            'visual_count' => 0,
            'source_count' => 0,
        ];
    }

    $sorted = aza_memory_sort_items($items, 'oldest');
    $first = $sorted[0];
    $last = $sorted[count($sorted) - 1];

    return [
        'count' => count($items),
        'first_trace' => $first['date_label'] ?? null,
        'last_trace' => $last['date_label'] ?? null,
        'visual_count' => count(aza_memory_filter_visual_items($items)),
        'source_count' => count(aza_memory_group_items_by_source($items)),
    ];
}

function aza_memory_build_island_projection(array $items, ?string $ownerSlug = null): array
{
    $summary = aza_memory_summarize_items($items);
    $ownerSlug = trim((string) $ownerSlug);
    $recent = array_slice($items, 0, 3);
    $sourceGroups = aza_memory_group_items_by_source($items);
    $topSources = array_slice(array_map(static fn (array $group): string => (string) $group['label'], $sourceGroups), 0, 3);
    $visualCount = (int) ($summary['visual_count'] ?? 0);
    $total = (int) ($summary['count'] ?? 0);
    $readiness = match (true) {
        $total >= 24 && $visualCount >= 8 => 'dense',
        $total >= 10 && $visualCount >= 3 => 'emergent',
        $total > 0 => 'seed',
        default => 'void',
    };

    $statusLabel = match ($readiness) {
        'dense' => 'Île presque lisible',
        'emergent' => 'Île en formation',
        'seed' => 'Germe d’île',
        default => 'Aucune île encore',
    };

    $copy = match ($readiness) {
        'dense' => 'La mémoire est assez dense pour devenir une île lisible : plusieurs provenances, des traces visuelles, une chronologie qui tient.',
        'emergent' => 'La matière commence à former une île : quelques sources, des objets visibles, déjà assez pour une lecture située.',
        'seed' => 'Quelques traces existent déjà. Pas une île encore, mais un rivage commence à apparaître.',
        default => 'Aucune matière n’est encore assez déposée pour faire île. Il faut d’abord quelques traces, puis un relief.',
    };

    $traits = [];
    if ($total > 0) {
        $traits[] = $total . ' trace' . ($total > 1 ? 's' : '');
    }
    if ($visualCount > 0) {
        $traits[] = $visualCount . ' visuelle' . ($visualCount > 1 ? 's' : '');
    }
    if ($topSources) {
        $traits[] = implode(' · ', $topSources);
    }

    return [
        'status' => $readiness,
        'status_label' => $statusLabel,
        'title' => $ownerSlug !== '' ? 'Préfiguration de l’île · ' . $ownerSlug : 'Préfiguration de l’île',
        'copy' => $copy,
        'traits' => $traits,
        'recent' => $recent,
        'summary' => $summary,
    ];
}
