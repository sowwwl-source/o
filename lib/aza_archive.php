<?php
declare(strict_types=1);

function aza_storage_dir(): string
{
    return AZA_STORAGE_DIR;
}

function aza_imports_dir(): string
{
    return aza_storage_dir() . DIRECTORY_SEPARATOR . 'imports';
}

function aza_archive_index_path(): string
{
    return aza_storage_dir() . DIRECTORY_SEPARATOR . 'archive_index.json';
}

function aza_ensure_storage(): void
{
    foreach ([aza_storage_dir(), aza_imports_dir()] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de préparer l’archive aZa.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('L’archive aZa n’est pas accessible en écriture pour le moment.');
        }
    }
}

function aza_supported_sources(): array
{
    return [
        'auto' => 'Détection douce',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'x' => 'X / Twitter',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
        'discord' => 'Discord',
        'mastodon' => 'Mastodon',
        'other' => 'Autre archive',
    ];
}

function aza_format_bytes(int $bytes): string
{
    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $value = max(0, $bytes);
    $index = 0;

    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }

    $precision = $value >= 10 || $index === 0 || abs($value - round($value)) < 0.05 ? 0 : 1;
    return number_format($value, $precision, ',', ' ') . ' ' . $units[$index];
}

function aza_direct_origin(): ?string
{
    $origin = trim((string) (defined('AZA_DIRECT_ORIGIN') ? AZA_DIRECT_ORIGIN : ''));
    return $origin !== '' ? rtrim($origin, '/') : null;
}

function aza_direct_host(): ?string
{
    $origin = aza_direct_origin();
    if ($origin === null) {
        return null;
    }

    $host = parse_url($origin, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return null;
    }

    return strtolower($host);
}

function aza_is_direct_request(?string $host = null): bool
{
    $directHost = aza_direct_host();
    if ($directHost === null) {
        return false;
    }

    $currentHost = strtolower(trim((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? ''))));
    $currentHost = preg_replace('/:\d+$/', '', $currentHost) ?? $currentHost;

    return $currentHost === $directHost;
}

function aza_direct_upload_url(?string $ownerSlug = null): ?string
{
    $origin = aza_direct_origin();
    if ($origin === null) {
        return null;
    }

    $query = [];
    $ownerSlug = trim((string) $ownerSlug);
    if ($ownerSlug !== '') {
        $query['u'] = $ownerSlug;
    }

    $path = '/aza.php';
    if ($query) {
        $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $origin . $path;
}

function aza_upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Archive trop lourde pour la limite serveur actuelle.',
        UPLOAD_ERR_PARTIAL => 'Le transfert de l’archive a été interrompu avant la fin.',
        UPLOAD_ERR_NO_FILE => 'Choisis une archive ZIP à déposer.',
        UPLOAD_ERR_NO_TMP_DIR => 'Le serveur n’a pas de dossier temporaire disponible pour cet upload.',
        UPLOAD_ERR_CANT_WRITE => 'Le serveur n’a pas réussi à écrire le ZIP temporaire.',
        UPLOAD_ERR_EXTENSION => 'Une extension serveur a interrompu l’upload du ZIP.',
        default => 'Le transfert de l’archive a échoué.',
    };
}

function aza_normalize_owner_slug(?string $ownerSlug): ?string
{
    $ownerSlug = trim((string) $ownerSlug);
    if ($ownerSlug === '') {
        return null;
    }

    $land = find_land($ownerSlug);
    if (!$land) {
        throw new InvalidArgumentException('Cette terre n’existe pas encore. Pose-la avant d’ouvrir une archive personnelle.');
    }

    return (string) $land['slug'];
}

function aza_normalize_source_hint(?string $source): string
{
    $source = strtolower(trim((string) $source));
    $sources = aza_supported_sources();
    return array_key_exists($source, $sources) ? $source : 'auto';
}

function aza_limit_text(string $value, int $maxLength): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function aza_safe_zip_basename(string $originalName): string
{
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $slug = normalize_username($base !== '' ? $base : 'archive');
    return $slug !== '' ? $slug : 'archive';
}

function aza_detect_source(array $paths, string $sourceHint = 'auto'): string
{
    if ($sourceHint !== 'auto') {
        return $sourceHint;
    }

    $haystack = strtolower(implode("\n", $paths));

    $detectors = [
        'instagram' => ['instagram', 'messages/inbox', 'followers_and_following', 'content/posts_'],
        'facebook' => ['your_facebook_activity', 'facebook', 'friends/friends', 'profile_information'],
        'x' => ['tweet.js', 'tweets.js', 'direct-messages.js', 'like.js', 'account.js'],
        'tiktok' => ['chat history', 'video browsing history', 'tiktok', 'user_data_tiktok'],
        'linkedin' => ['connections.csv', 'profile.csv', 'linkedin'],
        'discord' => ['messages/index.json', 'discord'],
        'mastodon' => ['actor.json', 'outbox.json', 'mastodon'],
    ];

    foreach ($detectors as $source => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return $source;
            }
        }
    }

    return 'other';
}

function aza_source_markers(): array
{
    return [
        'instagram' => ['messages/inbox', 'followers_and_following', 'content/posts_', 'stories', 'reels'],
        'facebook' => ['your_facebook_activity', 'profile_information', 'friends/friends', 'marketplace', 'posts_and_comments'],
        'x' => ['tweet.js', 'tweets.js', 'direct-messages.js', 'like.js', 'account.js'],
        'tiktok' => ['chat history', 'video browsing history', 'liked videos', 'user_data_tiktok'],
        'linkedin' => ['connections.csv', 'invitations.csv', 'profile.csv', 'messages.csv'],
        'discord' => ['guilds', 'channels', 'messages/index.json', 'account/user.json'],
        'mastodon' => ['actor.json', 'outbox.json', 'following_accounts.csv', 'lists.csv'],
        'other' => ['media', 'messages', 'profile', 'posts', 'archive'],
    ];
}

function aza_detect_markers(array $paths, string $source): array
{
    $haystack = strtolower(implode("\n", $paths));
    $markers = aza_source_markers()[$source] ?? [];
    $found = [];

    foreach ($markers as $marker) {
        if (str_contains($haystack, strtolower($marker))) {
            $found[] = $marker;
        }
    }

    return array_values(array_slice(array_unique($found), 0, 6));
}

function aza_detect_content_families(array $paths): array
{
    $haystack = strtolower(implode("\n", $paths));
    $families = [
        'messages' => ['message', 'inbox', 'dm', 'chat', 'conversation'],
        'posts' => ['post', 'tweet', 'toot', 'status', 'publication'],
        'profile' => ['profile', 'account', 'about_you', 'personal_information'],
        'media' => ['media', '.jpg', '.jpeg', '.png', '.gif', '.webp', '.mp4', '.mov'],
        'contacts' => ['friend', 'followers', 'following', 'connections', 'contacts'],
        'reactions' => ['like', 'likes', 'favorites', 'reactions'],
        'ads' => ['ads', 'advertisers', 'interests', 'inferences'],
        'locations' => ['location', 'places', 'checkin'],
        'security' => ['login', 'security', 'devices', 'sessions'],
        'payments' => ['payment', 'purchase', 'order', 'transaction'],
    ];

    $found = [];
    foreach ($families as $label => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $found[] = $label;
                break;
            }
        }
    }

    return array_values(array_slice($found, 0, 6));
}

function aza_detect_years(array $paths): array
{
    $years = [];
    foreach ($paths as $path) {
        if (preg_match_all('/(?<!\d)(20\d{2}|19\d{2})(?!\d)/', $path, $matches)) {
            foreach ($matches[1] as $year) {
                $yearInt = (int) $year;
                if ($yearInt >= 1990 && $yearInt <= 2100) {
                    $years[$year] = true;
                }
            }
        }
    }

    $list = array_map('intval', array_keys($years));
    sort($list);
    return array_slice($list, 0, 8);
}

function aza_label_content_family(string $family): string
{
    return [
        'messages' => 'messages',
        'posts' => 'publications',
        'profile' => 'profil',
        'media' => 'médias',
        'contacts' => 'contacts',
        'reactions' => 'réactions',
        'ads' => 'signaux publicitaires',
        'locations' => 'lieux',
        'security' => 'sécurité',
        'payments' => 'transactions',
    ][$family] ?? $family;
}

function aza_label_source(string $source): string
{
    return aza_supported_sources()[$source] ?? ucfirst($source);
}

function aza_memory_type_labels(): array
{
    return [
        'relational' => 'mémoire relationnelle',
        'public' => 'mémoire publique',
        'administrative' => 'mémoire administrative',
        'visual' => 'mémoire visuelle',
        'surveillance' => 'surveillance douce',
        'transactional' => 'mémoire transactionnelle',
    ];
}

function aza_label_memory_type(string $type): string
{
    return aza_memory_type_labels()[$type] ?? $type;
}

function aza_detect_memory_types(array $summary): array
{
    $families = $summary['content_families'] ?? [];
    $scores = [
        'relational' => 0,
        'public' => 0,
        'administrative' => 0,
        'visual' => 0,
        'surveillance' => 0,
        'transactional' => 0,
    ];

    foreach ($families as $family) {
        switch ($family) {
            case 'messages':
            case 'contacts':
                $scores['relational'] += 3;
                break;
            case 'posts':
            case 'reactions':
                $scores['public'] += 3;
                break;
            case 'profile':
            case 'security':
                $scores['administrative'] += 3;
                break;
            case 'media':
                $scores['visual'] += 3;
                break;
            case 'ads':
            case 'locations':
                $scores['surveillance'] += 3;
                break;
            case 'payments':
                $scores['transactional'] += 3;
                break;
        }
    }

    $markers = $summary['markers'] ?? [];
    foreach ($markers as $marker) {
        $marker = strtolower((string) $marker);
        if (str_contains($marker, 'message') || str_contains($marker, 'inbox')) {
            $scores['relational'] += 1;
        }
        if (str_contains($marker, 'post') || str_contains($marker, 'tweet')) {
            $scores['public'] += 1;
        }
        if (str_contains($marker, 'account') || str_contains($marker, 'profile')) {
            $scores['administrative'] += 1;
        }
    }

    arsort($scores);
    $types = [];
    foreach ($scores as $type => $score) {
        if ($score > 0) {
            $types[] = $type;
        }
    }

    return array_slice($types, 0, 3);
}

function aza_build_memory_note(array $summary): string
{
    $types = $summary['memory_types'] ?? [];
    if (!$types) {
        return 'Archive légère sans profil de mémoire dominant clairement lisible.';
    }

    $primary = aza_label_memory_type((string) $types[0]);
    $secondary = array_map('aza_label_memory_type', array_slice($types, 1, 2));
    $families = array_map('aza_label_content_family', $summary['content_families'] ?? []);

    $sentence = 'Cette archive ressemble surtout à une ' . $primary;
    if ($secondary) {
        $sentence .= ', avec une pente vers ' . implode(' et ', $secondary);
    }
    if ($families) {
        $sentence .= '. Elle se tient autour de ' . implode(', ', array_slice($families, 0, 3));
    }

    return $sentence . '.';
}

function aza_build_human_summary(array $summary): string
{
    $source = aza_label_source((string) ($summary['source'] ?? 'other'));
    $families = array_map('aza_label_content_family', $summary['content_families'] ?? []);
    $entries = (int) ($summary['entries'] ?? 0);
    $years = $summary['years'] ?? [];

    $parts = [];
    $parts[] = 'Archive ' . strtolower($source);

    if ($families) {
        $parts[] = 'centrée sur ' . implode(', ', array_slice($families, 0, 3));
    }

    if ($entries > 0) {
        $parts[] = 'avec ' . $entries . ' entrées repérées';
    }

    if (is_array($years) && count($years) >= 2) {
        $parts[] = 'et des traces entre ' . min($years) . ' et ' . max($years);
    } elseif (is_array($years) && count($years) === 1) {
        $parts[] = 'et un repère temporel autour de ' . $years[0];
    }

    return ucfirst(implode(' ', $parts)) . '.';
}

function aza_zip_entries(string $zipPath): array
{
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new RuntimeException('Impossible d’ouvrir cette archive ZIP.');
        }

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name !== '') {
                $entries[] = $name;
            }
        }
        $zip->close();
        return $entries;
    }

    $binary = trim((string) @shell_exec('command -v unzip 2>/dev/null'));
    if ($binary !== '' && function_exists('shell_exec')) {
        $command = $binary . ' -Z1 ' . escapeshellarg($zipPath) . ' 2>/dev/null';
        $output = shell_exec($command);
        if (is_string($output) && trim($output) !== '') {
            return preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        }
    }

    throw new RuntimeException('Impossible de lire cette archive ZIP sur ce serveur.');
}

function aza_summarize_zip(string $zipPath, string $sourceHint = 'auto'): array
{
    $paths = aza_zip_entries($zipPath);
    $fileCount = 0;
    $mediaCount = 0;
    $samplePaths = [];
    $topFolders = [];
    $mediaExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm', 'mp3', 'm4a', 'wav', 'json', 'html', 'csv'];

    foreach ($paths as $name) {
        if ($name === '' || str_ends_with($name, '/')) {
            continue;
        }

        $fileCount++;

        if (count($samplePaths) < 12) {
            $samplePaths[] = $name;
        }

        $segments = explode('/', $name);
        $folder = $segments[0] !== '' ? $segments[0] : 'racine';
        $topFolders[$folder] = ($topFolders[$folder] ?? 0) + 1;

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, $mediaExtensions, true)) {
            $mediaCount++;
        }
    }

    arsort($topFolders);
    $source = aza_detect_source($paths, $sourceHint);
    $contentFamilies = aza_detect_content_families($paths);
    $years = aza_detect_years($paths);
    $markers = aza_detect_markers($paths, $source);

    $summary = [
        'source' => $source,
        'entries' => $fileCount,
        'media_entries' => $mediaCount,
        'sample_paths' => $samplePaths,
        'top_folders' => array_slice($topFolders, 0, 6, true),
        'content_families' => $contentFamilies,
        'years' => $years,
        'markers' => $markers,
    ];

    $summary['memory_types'] = aza_detect_memory_types($summary);

    $summary['human_summary'] = aza_build_human_summary($summary);
    $summary['memory_note'] = aza_build_memory_note($summary);

    return $summary;
}

function aza_hydrate_archive_entry(array $entry): array
{
    if (!empty($entry['human_summary']) && !empty($entry['content_families'])) {
        return $entry;
    }

    $stored = (string) ($entry['stored_file'] ?? '');
    if ($stored !== '') {
        $absolutePath = aza_absolute_storage_path($stored);
        if (is_file($absolutePath)) {
            try {
                $summary = aza_summarize_zip($absolutePath, (string) ($entry['source'] ?? 'auto'));
                return array_merge($entry, [
                    'source' => $summary['source'],
                    'entries' => $summary['entries'],
                    'media_entries' => $summary['media_entries'],
                    'sample_paths' => $summary['sample_paths'],
                    'top_folders' => $summary['top_folders'],
                    'content_families' => $summary['content_families'],
                    'years' => $summary['years'],
                    'markers' => $summary['markers'],
                    'memory_types' => $summary['memory_types'],
                    'human_summary' => $summary['human_summary'],
                    'memory_note' => $summary['memory_note'],
                ]);
            } catch (Throwable $exception) {
                return $entry;
            }
        }
    }

    $paths = array_values(array_filter(array_map('strval', $entry['sample_paths'] ?? [])));
    if (!$paths) {
        return $entry;
    }

    $source = (string) ($entry['source'] ?? aza_detect_source($paths));
    $contentFamilies = aza_detect_content_families($paths);
    $years = aza_detect_years($paths);
    $markers = aza_detect_markers($paths, $source);
    $summary = [
        'source' => $source,
        'entries' => (int) ($entry['entries'] ?? count($paths)),
        'content_families' => $contentFamilies,
        'years' => $years,
        'markers' => $markers,
    ];

    $summary['memory_types'] = aza_detect_memory_types($summary);

    $entry['content_families'] = $entry['content_families'] ?? $contentFamilies;
    $entry['years'] = $entry['years'] ?? $years;
    $entry['markers'] = $entry['markers'] ?? $markers;
    $entry['memory_types'] = $entry['memory_types'] ?? $summary['memory_types'];
    $entry['human_summary'] = $entry['human_summary'] ?? aza_build_human_summary($summary);
    $entry['memory_note'] = $entry['memory_note'] ?? aza_build_memory_note($summary);

    return $entry;
}

function aza_read_archive_index(): array
{
    $path = aza_archive_index_path();
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function aza_write_archive_index(array $entries): void
{
    aza_ensure_storage();
    $path = aza_archive_index_path();
    $handle = fopen($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Impossible de mettre à jour l’index aZa.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller l’index aZa.');
        }

        ftruncate($handle, 0);
        rewind($handle);
        $encoded = json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || fwrite($handle, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('Impossible d’écrire l’index aZa.');
        }
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function aza_list_archives(?string $ownerSlug = null): array
{
    $entries = array_map('aza_hydrate_archive_entry', aza_read_archive_index());
    if ($ownerSlug === null || $ownerSlug === '') {
        return $entries;
    }

    return array_values(array_filter(
        $entries,
        static fn (array $entry): bool => (($entry['owner_slug'] ?? null) === $ownerSlug)
    ));
}

function get_all_aza_archives(): array
{
    return aza_list_archives(null);
}

function get_archives_for_land(string $slug): array
{
    return aza_list_archives($slug);
}

function aza_parse_archive_datetime(?string $value): ?DateTimeImmutable
{
    if (!$value) {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }
}

function aza_archive_with_chronology(array $entry): array
{
    $years = [];
    foreach (($entry['years'] ?? []) as $year) {
        $yearInt = (int) $year;
        if ($yearInt >= 1900 && $yearInt <= 2100) {
            $years[$yearInt] = true;
        }
    }

    $years = array_keys($years);
    sort($years);

    $createdAt = aza_parse_archive_datetime((string) ($entry['created_at'] ?? ''));
    $localYear = $createdAt
        ? (int) $createdAt->setTimezone(new DateTimeZone(DEFAULT_TIMEZONE))->format('Y')
        : null;

    $startYear = $years ? (int) min($years) : $localYear;
    $endYear = $years ? (int) max($years) : $localYear;
    $isArchiveDerived = $years !== [];
    $sortYear = $startYear ?? 9999;
    $sortTimestamp = $createdAt ? $createdAt->getTimestamp() : PHP_INT_MAX;

    if ($startYear !== null && $endYear !== null) {
        $chronologyLabel = $startYear === $endYear
            ? (string) $startYear
            : $startYear . ' → ' . $endYear;
    } else {
        $chronologyLabel = 'Atemporel';
    }

    $entry['chronology_start_year'] = $startYear;
    $entry['chronology_end_year'] = $endYear;
    $entry['chronology_label'] = $chronologyLabel;
    $entry['chronology_bucket'] = $startYear !== null ? (string) $startYear : 'Inconnu';
    $entry['chronology_origin'] = $isArchiveDerived ? 'years' : 'created_at';
    $entry['chronology_origin_label'] = $isArchiveDerived ? 'Traces internes' : 'Date de dépôt';
    $entry['chronology_sort_year'] = $sortYear;
    $entry['chronology_sort_timestamp'] = $sortTimestamp;

    return $entry;
}

function aza_enrich_chronology(array $archive): array
{
    return aza_archive_with_chronology($archive);
}

function aza_sort_archives_chronologically(array $entries): array
{
    $entries = array_map('aza_archive_with_chronology', $entries);

    usort(
        $entries,
        static function (array $left, array $right): int {
            $cmp = ((int) ($left['chronology_sort_year'] ?? 9999)) <=> ((int) ($right['chronology_sort_year'] ?? 9999));
            if ($cmp !== 0) {
                return $cmp;
            }

            $cmp = ((int) ($left['chronology_end_year'] ?? 9999)) <=> ((int) ($right['chronology_end_year'] ?? 9999));
            if ($cmp !== 0) {
                return $cmp;
            }

            return ((int) ($left['chronology_sort_timestamp'] ?? PHP_INT_MAX)) <=> ((int) ($right['chronology_sort_timestamp'] ?? PHP_INT_MAX));
        }
    );

    return $entries;
}

function aza_sort_chronologically(array $archives): array
{
    return aza_sort_archives_chronologically($archives);
}

function aza_group_archives_by_chronology(array $entries): array
{
    $sorted = aza_sort_archives_chronologically($entries);
    $groups = [];

    foreach ($sorted as $entry) {
        $bucket = (string) ($entry['chronology_bucket'] ?? 'Inconnu');
        if (!isset($groups[$bucket])) {
            $groups[$bucket] = [
                'bucket' => $bucket,
                'label' => $bucket,
                'items' => [],
            ];
        }

        $groups[$bucket]['items'][] = $entry;
    }

    return array_values($groups);
}

function aza_group_by_bucket(array $archives): array
{
    $groups = [];
    foreach (aza_sort_archives_chronologically($archives) as $archive) {
        $bucket = (string) ($archive['chronology_bucket'] ?? 'Inconnu');
        $groups[$bucket][] = $archive;
    }

    return $groups;
}

function aza_chronology_overview(array $entries): array
{
    $sorted = aza_sort_archives_chronologically($entries);
    if (!$sorted) {
        return [
            'count' => 0,
            'first_label' => '—',
            'last_label' => '—',
            'first_trace' => null,
            'last_trace' => null,
        ];
    }

    $first = $sorted[0];
    $last = $sorted[count($sorted) - 1];

    return [
        'count' => count($sorted),
        'first_label' => (string) ($first['chronology_label'] ?? '—'),
        'last_label' => (string) ($last['chronology_label'] ?? '—'),
        'first_trace' => $first['chronology_start_year'] ?? null,
        'last_trace' => $last['chronology_end_year'] ?? null,
    ];
}

function aza_summarize_chronology(array $archives): array
{
    return aza_chronology_overview($archives);
}

function aza_prepare_chronology(array $archives): array
{
    $enriched = array_map('aza_enrich_chronology', $archives);
    $sorted = aza_sort_chronologically($enriched);

    return [
        'raw' => $archives,
        'enriched' => $enriched,
        'sorted' => $sorted,
        'grouped' => aza_group_by_bucket($sorted),
        'summary' => aza_summarize_chronology($sorted),
    ];
}

function aza_import_zip_archive(array $file, array $meta = []): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(aza_upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }

    $originalName = (string) ($file['name'] ?? 'archive.zip');
    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'zip') {
        throw new InvalidArgumentException('Seules les archives ZIP sont acceptées pour le moment.');
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = AZA_MAX_UPLOAD_BYTES;
    if ($size <= 0) {
        throw new InvalidArgumentException('Cette archive semble vide.');
    }
    if ($size > $maxBytes) {
        throw new InvalidArgumentException('Archive trop lourde. Garde-la sous ' . aza_format_bytes($maxBytes) . ' pour cet espace aZa.');
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
    }
    $allowed = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'];
    if ($mime !== null && !in_array($mime, $allowed, true)) {
        throw new InvalidArgumentException('Ce fichier ne ressemble pas à une archive ZIP valide.');
    }

    aza_ensure_storage();

    $ownerSlug = aza_normalize_owner_slug($meta['owner_slug'] ?? null);
    $sourceHint = aza_normalize_source_hint($meta['source_hint'] ?? 'auto');
    $label = trim((string) ($meta['label'] ?? ''));
    $label = $label !== '' ? aza_limit_text($label, 120) : '';
    $notes = trim((string) ($meta['notes'] ?? ''));
    $notes = $notes !== '' ? aza_limit_text($notes, 0 + 800) : '';

    $summary = aza_summarize_zip((string) $file['tmp_name'], $sourceHint);
    $baseName = aza_safe_zip_basename($originalName);
    $id = gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
    $storedFilename = $id . '_' . $baseName . '.zip';
    $targetPath = aza_imports_dir() . DIRECTORY_SEPARATOR . $storedFilename;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Impossible d’enregistrer cette archive pour le moment.');
    }

    $entry = [
        'id' => $id,
        'owner_slug' => $ownerSlug,
        'label' => $label !== '' ? $label : $baseName,
        'notes' => $notes,
        'source' => $summary['source'],
        'original_name' => $originalName,
        'stored_file' => aza_public_storage_path('imports/' . $storedFilename),
        'size' => $size,
        'entries' => $summary['entries'],
        'media_entries' => $summary['media_entries'],
        'sample_paths' => $summary['sample_paths'],
        'top_folders' => $summary['top_folders'],
        'content_families' => $summary['content_families'],
        'years' => $summary['years'],
        'markers' => $summary['markers'],
        'memory_types' => $summary['memory_types'],
        'human_summary' => $summary['human_summary'],
        'memory_note' => $summary['memory_note'],
        'created_at' => gmdate(DATE_ATOM),
    ];

    $entries = aza_read_archive_index();
    array_unshift($entries, $entry);
    $entries = array_slice($entries, 0, 200);
    aza_write_archive_index($entries);

    return $entry;
}
