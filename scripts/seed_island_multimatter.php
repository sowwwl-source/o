#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$slugInput = trim((string) ($argv[1] ?? 'qa-multimatiere'));
$usernameInput = trim((string) ($argv[2] ?? 'QA multimatière'));
$timezoneInput = trim((string) ($argv[3] ?? DEFAULT_TIMEZONE));

try {
    $slug = normalize_username($slugInput);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Slug invalide : ' . $exception->getMessage() . "\n");
    exit(1);
}

$username = $slug;
if ($usernameInput !== '') {
    try {
        if (normalize_username($usernameInput) === $slug) {
            $username = $usernameInput;
        }
    } catch (Throwable) {
        // Garder le slug explicite si le nom libre diverge.
    }
}
$timezone = $timezoneInput !== '' ? $timezoneInput : DEFAULT_TIMEZONE;
$seedPrefix = '[seed:island-multimatter]';

$entries = [
    [
        'label' => 'Journal de rive',
        'original_name' => 'journal-de-rive.txt',
        'format' => 'txt',
        'format_family' => 'document',
        'notes' => $seedPrefix . ' Extrait textuel continu pour vérifier la prévisualisation inline de l’île.',
        'date_hint' => '2026-05-06',
        'body' => <<<TXT
Journal de rive
===============

Cette île sert de QA multimatière.

- texte : lecture continue
- data : aperçu brut
- design : aperçu source
- 3d : viewer natif

On vérifie ici que la station explique réellement son mode de lecture.
TXT,
    ],
    [
        'label' => 'Constellation des formats',
        'original_name' => 'constellation-formats.json',
        'format' => 'json',
        'format_family' => 'data',
        'notes' => $seedPrefix . ' Structure JSON lisible directement dans le lecteur data.',
        'date_hint' => '2026-05-06',
        'body' => json_encode([
            'slug' => $slug,
            'purpose' => 'public QA island',
            'readers' => [
                'text' => 'expected: prévisualisation textuelle',
                'data' => 'expected: aperçu brut',
                'design' => 'expected: aperçu textuel',
                'model' => 'expected: viewer 3d natif',
            ],
            'status' => 'seeded',
            'generated_at' => gmdate(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ],
    [
        'label' => 'Wireframe source',
        'original_name' => 'wireframe-source.fig',
        'format' => 'fig',
        'format_family' => 'design',
        'notes' => $seedPrefix . ' Source design textuelle pour déclencher le mode aperçu textuel.',
        'date_hint' => '2026-05-06',
        'body' => json_encode([
            'document' => [
                'name' => 'Island QA wireframe',
                'type' => 'FIGMA_MOCK',
                'frames' => [
                    ['id' => 'frame-1', 'name' => 'Reader diagnostics', 'width' => 1440, 'height' => 900],
                ],
                'notes' => 'Minimal text-based fig payload used as a readable fallback preview.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ],
    [
        'label' => 'Triangle témoin',
        'original_name' => 'triangle-temoin.gltf',
        'format' => 'gltf',
        'format_family' => '3d',
        'notes' => $seedPrefix . ' Objet glTF minimal pour vérifier le viewer 3D natif.',
        'date_hint' => '2026-05-06',
        'body' => json_encode([
            'asset' => ['version' => '2.0', 'generator' => 'seed_island_multimatter'],
            'scene' => 0,
            'scenes' => [['nodes' => [0]]],
            'nodes' => [['mesh' => 0]],
            'meshes' => [[
                'primitives' => [[
                    'attributes' => ['POSITION' => 0],
                    'indices' => 1,
                    'mode' => 4,
                ]],
            ]],
            'buffers' => [[
                'byteLength' => 42,
                'uri' => 'data:application/octet-stream;base64,AAAAAAAAAAAAAAAAAACAPwAAAAAAAAAAAAAAAAAAgD8AAAAAAAABAAIA',
            ]],
            'bufferViews' => [
                ['buffer' => 0, 'byteOffset' => 0, 'byteLength' => 36, 'target' => 34962],
                ['buffer' => 0, 'byteOffset' => 36, 'byteLength' => 6, 'target' => 34963],
            ],
            'accessors' => [
                [
                    'bufferView' => 0,
                    'byteOffset' => 0,
                    'componentType' => 5126,
                    'count' => 3,
                    'type' => 'VEC3',
                    'max' => [1, 1, 0],
                    'min' => [0, 0, 0],
                ],
                [
                    'bufferView' => 1,
                    'byteOffset' => 0,
                    'componentType' => 5123,
                    'count' => 3,
                    'type' => 'SCALAR',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ],
];

try {
    $land = find_land($slug);
    if ($land === null) {
        $land = create_land($username, $timezone);
        fwrite(STDOUT, 'Land created: ' . ($land['slug'] ?? $slug) . PHP_EOL);
    } else {
        fwrite(STDOUT, 'Land exists: ' . ($land['slug'] ?? $slug) . PHP_EOL);
    }

    $slug = (string) ($land['slug'] ?? $slug);

    aza_ingest_ensure_storage();
    $index = aza_ingest_read_files_index();
    $previousSeedRows = array_values(array_filter(
        $index,
        static fn (array $row): bool => (($row['owner_slug'] ?? '') === $slug && str_contains((string) ($row['notes'] ?? ''), '[seed:island-multimatter]'))
    ));
    foreach ($previousSeedRows as $previousSeedRow) {
        $previousPath = aza_absolute_storage_path((string) ($previousSeedRow['stored_file'] ?? ''));
        if (is_string($previousPath) && $previousPath !== '' && is_file($previousPath)) {
            @unlink($previousPath);
        }
    }
    $index = array_values(array_filter(
        $index,
        static fn (array $row): bool => !(($row['owner_slug'] ?? '') === $slug && str_contains((string) ($row['notes'] ?? ''), '[seed:island-multimatter]'))
    ));

    foreach ($entries as $entry) {
        $id = gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
        $storedName = $id . '_' . aza_ingest_safe_filename((string) $entry['original_name']);
        $absolutePath = aza_ingest_files_dir() . DIRECTORY_SEPARATOR . $storedName;
        $bytes = (string) $entry['body'];

        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new RuntimeException('Impossible d’écrire ' . $entry['original_name']);
        }

        array_unshift($index, [
            'id' => $id,
            'kind' => 'file',
            'owner_slug' => $slug,
            'label' => (string) $entry['label'],
            'notes' => (string) $entry['notes'],
            'date_hint' => (string) $entry['date_hint'],
            'original_name' => (string) $entry['original_name'],
            'format' => (string) $entry['format'],
            'format_family' => (string) $entry['format_family'],
            'size' => filesize($absolutePath) ?: strlen($bytes),
            'stored_file' => aza_public_storage_path('files/' . $storedName),
            'thumbnail' => null,
            'meta' => [],
            'created_at' => gmdate(DATE_ATOM),
        ]);

        fwrite(STDOUT, 'Seeded: ' . $entry['original_name'] . PHP_EOL);
    }

    $index = array_slice($index, 0, 2000);
    aza_ingest_write_files_index($index);

    fwrite(STDOUT, PHP_EOL . 'Done.' . PHP_EOL);
    fwrite(STDOUT, 'Slug: ' . $slug . PHP_EOL);
    fwrite(STDOUT, 'Island URL: ' . site_origin() . '/island?u=' . rawurlencode($slug) . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
