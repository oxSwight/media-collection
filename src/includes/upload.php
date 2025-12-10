<?php
// src/includes/upload.php

function uploads_root(): string
{
    return dirname(__DIR__) . '/uploads';
}

function handle_image_upload(array $file, string $subDir = '', int $maxBytes = 2_000_000): array
{
    // Проверка ошибок загрузки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Plik przekracza maksymalny rozmiar ustawiony w php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Plik przekracza maksymalny rozmiar formularza.',
            UPLOAD_ERR_PARTIAL => 'Plik został przesłany tylko częściowo.',
            UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku.',
            UPLOAD_ERR_NO_TMP_DIR => 'Brak katalogu tymczasowego.',
            UPLOAD_ERR_CANT_WRITE => 'Nie można zapisać pliku na dysku.',
            UPLOAD_ERR_EXTENSION => 'Przesyłanie zostało zatrzymane przez rozszerzenie PHP.',
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Błąd przesyłania pliku.';
        return [null, $errorMsg];
    }

    // Проверка размера файла
    if ($file['size'] > $maxBytes) {
        $maxSizeMB = round($maxBytes / 1024 / 1024, 1);
        return [null, "Plik jest zbyt duży (max {$maxSizeMB}MB)."];
    }

    // Проверка минимального размера (защита от пустых файлов)
    if ($file['size'] < 100) {
        return [null, 'Plik jest zbyt mały lub uszkodzony.'];
    }

    // Проверка имени файла на безопасность
    $filename = basename($file['name']);
    if (preg_match('/[^a-zA-Z0-9._-]/', $filename)) {
        return [null, 'Nieprawidłowa nazwa pliku.'];
    }

    // Разрешенные MIME типы
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif', // Добавляем поддержку GIF
    ];

    // Проверка MIME типа через finfo (более надежно)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']) ?: '';
    
    // Дополнительная проверка через getimagesize для безопасности
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [null, 'Plik nie jest prawidłowym obrazem.'];
    }
    
    // Проверяем, что MIME тип соответствует реальному типу изображения
    $detectedMime = $imageInfo['mime'] ?? '';
    if (!isset($allowed[$mime]) || ($detectedMime && $mime !== $detectedMime)) {
        return [null, 'Dozwolone są tylko obrazy JPG, PNG, WEBP lub GIF.'];
    }
    
    // Проверка расширения файла
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $expectedExt = $allowed[$mime] ?? '';
    if ($ext !== $expectedExt && !in_array($ext, ['jpg', 'jpeg'], true)) {
        return [null, 'Rozszerzenie pliku nie odpowiada jego typowi.'];
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

    backup_upload($fullPath, $subDir, $filename);

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

    backup_upload($fullPath, '', $filename);

    // Проверяем, что это действительно изображение
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fullPath);
    if (strpos($mime, 'image/') !== 0) {
        @unlink($fullPath);
        return null;
    }

    return 'uploads/' . $filename;
}

/**
 * Создает резервную копию загруженного файла в uploads/_backup
 */
function backup_upload(string $fullPath, string $subDir, string $filename): void
{
    $backupRoot = uploads_root() . '/_backup';
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0775, true) && !is_dir($backupRoot)) {
        return;
    }
    $subDir = trim($subDir, '/');
    $targetDir = $subDir ? $backupRoot . '/' . $subDir : $backupRoot;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return;
    }
    @copy($fullPath, $targetDir . '/' . $filename);
}

/**
 * Проверяет существование файла uploads и при отсутствии восстанавливает из _backup
 */
function ensure_upload_exists(?string $relativePath): bool
{
    if (!$relativePath) return false;
    if (str_starts_with($relativePath, 'http')) return true;
    $relative = ltrim($relativePath, '/');
    if (str_starts_with($relative, 'uploads/')) {
        $relative = substr($relative, strlen('uploads/'));
    }
    $root = uploads_root();
    $full = $root . '/' . $relative;
    if (file_exists($full)) return true;

    $backup = $root . '/_backup/' . $relative;
    if (file_exists($backup)) {
        @mkdir(dirname($full), 0775, true);
        if (@copy($backup, $full)) {
            return true;
        }
    }
    return false;
}

