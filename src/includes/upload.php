<?php
// src/includes/upload.php

function uploads_root(): string
{
    return dirname(__DIR__) . '/uploads';
}

function handle_image_upload(array $file, string $subDir = '', int $maxBytes = 2_000_000): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Błąd przesyłania pliku.'];
    }

    if ($file['size'] > $maxBytes) {
        return [null, 'Plik jest zbyt duży (max 2MB).'];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        return [null, 'Dozwolone są tylko obrazy JPG, PNG lub WEBP.'];
    }

    $root = uploads_root();
    $subDir = trim($subDir, '/');
    $targetDir = $subDir ? $root . '/' . $subDir : $root;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return [null, 'Nie udało się utworzyć katalogu na pliki.'];
    }

    $filename = uniqid('img_', true) . '.' . $allowed[$mime];
    $fullPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [null, 'Nie udało się zapisać pliku.'];
    }

    $relative = 'uploads';
    if ($subDir) {
        $relative .= '/' . $subDir;
    }

    return [$relative . '/' . $filename, null];
}

function safe_delete_upload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }

    $relativePath = ltrim($relativePath, '/');
    if (str_contains($relativePath, '..')) {
        return;
    }

    $baseDir = uploads_root();
    if (str_starts_with($relativePath, 'uploads/')) {
        $relativePath = substr($relativePath, strlen('uploads/'));
    }
    $fullPath = $baseDir . '/' . $relativePath;

    $realBase = realpath($baseDir);
    $realTarget = $fullPath && file_exists($fullPath) ? realpath($fullPath) : null;

    if ($realBase && $realTarget && str_starts_with($realTarget, $realBase) && is_file($realTarget)) {
        unlink($realTarget);
    }
}

