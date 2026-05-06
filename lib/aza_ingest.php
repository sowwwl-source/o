<?php
declare(strict_types=1);

function aza_ingest_format_families(): array
{
    return [
        'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'tiff', 'tif', 'bmp', 'heic', 'avif', 'svg'],
        'video'    => ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mts', 'm4v', 'wmv', 'ogv'],
        'audio'    => ['mp3', 'wav', 'flac', 'ogg', 'aac', 'm4a', 'aiff', 'opus'],
        'document' => ['pdf', 'txt', 'md', 'rtf', 'docx', 'doc', 'odt', 'epub'],
        'design'   => ['psd', 'psb', 'ai', 'eps', 'indd', 'idml', 'xd', 'fig', 'sketch'],
        '3d'       => ['obj', 'skp', 'stl', 'fbx', 'gltf', 'glb', '3ds', 'blend', 'dae', 'ply', 'step', 'stp'],
        'data'     => ['json', 'csv', 'xml', 'yaml', 'yml', 'tsv'],
    ];
}

function aza_ingest_family_label(string $family): string
{
    return match ($family) {
        'image'    => 'Image',
        'video'    => 'Vidéo',
        'audio'    => 'Audio',
        'document' => 'Document',
        'design'   => 'Design',
        '3d'       => '3D',
        'data'     => 'Données',
        default    => 'Autre',
    };
}

function aza_ingest_detect_family(string $ext): string
{
    $ext = strtolower(trim($ext, '.'));
    foreach (aza_ingest_format_families() as $family => $exts) {
        if (in_array($ext, $exts, true)) {
            return $family;
        }
    }
    return 'other';
}

function aza_ingest_allowed_extensions(): array
{
    $exts = [];
    foreach (aza_ingest_format_families() as $family_exts) {
        foreach ($family_exts as $ext) {
            $exts[] = $ext;
        }
    }
    return $exts;
}

function aza_ingest_files_dir(): string
{
    return aza_storage_dir() . DIRECTORY_SEPARATOR . 'files';
}

function aza_ingest_thumbs_dir(): string
{
    return aza_ingest_files_dir() . DIRECTORY_SEPARATOR . 'thumbs';
}

function aza_ingest_files_index_path(): string
{
    return aza_storage_dir() . DIRECTORY_SEPARATOR . 'files_index.json';
}

function aza_ingest_read_files_index(): array
{
    $path = aza_ingest_files_index_path();
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

function aza_ingest_write_files_index(array $entries): void
{
    $path = aza_ingest_files_index_path();
    $json = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Impossible de sérialiser l\'index des fichiers.');
    }
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d\'écrire l\'index des fichiers.');
    }
}

function aza_ingest_list_files(?string $ownerSlug = null): array
{
    $entries = aza_ingest_read_files_index();
    if ($ownerSlug === null || $ownerSlug === '') {
        return $entries;
    }
    return array_values(
        array_filter($entries, static fn ($e) => ($e['owner_slug'] ?? '') === $ownerSlug)
    );
}

function aza_ingest_ensure_storage(): void
{
    foreach ([aza_ingest_files_dir(), aza_ingest_thumbs_dir()] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de préparer le stockage des fichiers libres.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Le stockage des fichiers n\'est pas accessible en écriture.');
        }
    }
}

function aza_ingest_max_upload_bytes(): int
{
    $env = trim((string) (getenv('SOWWWL_AZA_INGEST_MAX_BYTES') ?: ''));
    return ctype_digit($env) ? max(1024 * 1024, (int) $env) : (200 * 1024 * 1024);
}

function aza_ingest_safe_filename(string $original): string
{
    $ext  = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    $name = (string) pathinfo($original, PATHINFO_FILENAME);
    $name = (string) preg_replace('/[^a-z0-9_\-]+/i', '-', $name);
    $name = trim($name, '-');
    $name = $name !== '' ? substr($name, 0, 60) : 'fichier';
    return $ext !== '' ? $name . '.' . $ext : $name;
}

function aza_ingest_try_image_thumbnail(string $srcPath, string $ext, string $destPath): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    try {
        $image = match (strtolower($ext)) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($srcPath),
            'png'         => @imagecreatefrompng($srcPath),
            'gif'         => @imagecreatefromgif($srcPath),
            'webp'        => @imagecreatefromwebp($srcPath),
            default       => false,
        };

        if (!$image) {
            return false;
        }

        $srcW  = imagesx($image);
        $srcH  = imagesy($image);
        $maxDim = 400;

        if ($srcW <= $maxDim && $srcH <= $maxDim) {
            $dstW = $srcW;
            $dstH = $srcH;
        } elseif ($srcW > $srcH) {
            $dstW = $maxDim;
            $dstH = max(1, (int) round($srcH * $maxDim / $srcW));
        } else {
            $dstH = $maxDim;
            $dstW = max(1, (int) round($srcW * $maxDim / $srcH));
        }

        $thumb = imagecreatetruecolor($dstW, $dstH);
        if (!$thumb) {
            imagedestroy($image);
            return false;
        }

        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        $ok = imagejpeg($thumb, $destPath, 85);
        imagedestroy($image);
        imagedestroy($thumb);

        return (bool) $ok;
    } catch (Throwable) {
        return false;
    }
}

function aza_ingest_extract_meta(string $path, string $family, string $ext): array
{
    $meta = [];

    if ($family === 'image') {
        $size = @getimagesize($path);
        if ($size) {
            $meta['width']  = $size[0];
            $meta['height'] = $size[1];
        }
    }

    return $meta;
}

function aza_ingest_import_file(array $file, array $form = []): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(aza_upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }

    $originalName = (string) ($file['name'] ?? 'fichier');
    $ext          = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed      = aza_ingest_allowed_extensions();

    if ($ext === '' || !in_array($ext, $allowed, true)) {
        $sample = implode(', ', array_slice($allowed, 0, 12));
        throw new InvalidArgumentException(
            'Format non reconnu : .' . ($ext ?: '?') . '. Exemples acceptés : ' . $sample . '…'
        );
    }

    $size    = (int) ($file['size'] ?? 0);
    $maxSize = aza_ingest_max_upload_bytes();

    if ($size <= 0) {
        throw new InvalidArgumentException('Ce fichier semble vide.');
    }
    if ($size > $maxSize) {
        throw new InvalidArgumentException(
            'Fichier trop lourd. Limite : ' . aza_format_bytes($maxSize) . '.'
        );
    }

    aza_ingest_ensure_storage();

    $family    = aza_ingest_detect_family($ext);
    $ownerSlug = aza_normalize_owner_slug($form['owner_slug'] ?? null);
    $label     = trim((string) ($form['label'] ?? ''));
    $label     = $label !== '' ? aza_limit_text($label, 120) : (string) pathinfo($originalName, PATHINFO_FILENAME);
    $notes     = aza_limit_text(trim((string) ($form['notes'] ?? '')), 800);
    $dateHint  = trim((string) ($form['date_hint'] ?? ''));

    $id         = gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
    $safeBase   = aza_ingest_safe_filename($originalName);
    $storedName = $id . '_' . $safeBase;
    $storedPath = aza_ingest_files_dir() . DIRECTORY_SEPARATOR . $storedName;
    $storedKey  = aza_public_storage_path('files/' . $storedName);

    if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
        throw new RuntimeException('Impossible d\'enregistrer le fichier.');
    }

    $thumbnailKey = null;
    if ($family === 'image') {
        $thumbName = $id . '.jpg';
        $thumbPath = aza_ingest_thumbs_dir() . DIRECTORY_SEPARATOR . $thumbName;
        if (aza_ingest_try_image_thumbnail($storedPath, $ext, $thumbPath)) {
            $thumbnailKey = aza_public_storage_path('files/thumbs/' . $thumbName);
        }
    }

    $meta = aza_ingest_extract_meta($storedPath, $family, $ext);

    $entry = [
        'id'            => $id,
        'kind'          => 'file',
        'owner_slug'    => $ownerSlug,
        'label'         => $label,
        'notes'         => $notes,
        'date_hint'     => $dateHint,
        'original_name' => $originalName,
        'format'        => $ext,
        'format_family' => $family,
        'size'          => $size,
        'stored_file'   => $storedKey,
        'thumbnail'     => $thumbnailKey,
        'meta'          => $meta,
        'created_at'    => gmdate(DATE_ATOM),
    ];

    $index = aza_ingest_read_files_index();
    array_unshift($index, $entry);
    $index = array_slice($index, 0, 2000);
    aza_ingest_write_files_index($index);

    return $entry;
}

function aza_ingest_group_by_family(array $files): array
{
    $groups = [];
    foreach ($files as $file) {
        $family = (string) ($file['format_family'] ?? 'other');
        if (!isset($groups[$family])) {
            $groups[$family] = [
                'family' => $family,
                'label'  => aza_ingest_family_label($family),
                'items'  => [],
            ];
        }
        $groups[$family]['items'][] = $file;
    }
    ksort($groups);
    return array_values($groups);
}
