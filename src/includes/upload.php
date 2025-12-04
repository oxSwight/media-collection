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

/**
 * Скачивает изображение по URL и сохраняет локально
 * @param string $imageUrl URL изображения
 * @return string|null Относительный путь к сохраненному файлу или null при ошибке
 */
function handle_image_from_url(string $imageUrl): ?string
{
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return null;
    }

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'MediaLib/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($imageData === false || $httpCode !== 200 || strlen($imageData) < 100) {
        return null;
    }

    // Определяем расширение
    $ext = 'jpg';
    if ($contentType) {
        if (strpos($contentType, 'jpeg') !== false || strpos($contentType, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($contentType, 'png') !== false) {
            $ext = 'png';
        } elseif (strpos($contentType, 'webp') !== false) {
            $ext = 'webp';
        } elseif (strpos($contentType, 'gif') !== false) {
            $ext = 'gif';
        }
    } else {
        $urlPath = parse_url($imageUrl, PHP_URL_PATH);
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $urlPath, $matches)) {
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
        }
    }

    $root = uploads_root();
    if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
        return null;
    }

    $filename = 'img_' . uniqid('', true) . '.' . $ext;
    $fullPath = $root . '/' . $filename;

    if (file_put_contents($fullPath, $imageData) === false) {
        return null;
    }

    // Проверяем, что это действительно изображение
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fullPath);
    if (strpos($mime, 'image/') !== 0) {
        @unlink($fullPath);
        return null;
    }

    return 'uploads/' . $filename;
}

