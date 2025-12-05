<?php
// src/includes/cache.php - Простая система кэширования

/**
 * Получить значение из кэша
 * 
 * @param string $key Ключ кэша
 * @param int $ttl Время жизни в секундах
 * @return mixed|null Значение или null если истекло
 */
function cache_get(string $key, int $ttl = 3600)
{
    $cacheDir = sys_get_temp_dir() . '/medialib_cache';
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $file = $cacheDir . '/' . md5($key) . '.cache';
    
    if (!file_exists($file)) {
        return null;
    }
    
    $data = @unserialize(file_get_contents($file));
    
    if (!$data || !isset($data['expires']) || !isset($data['value'])) {
        return null;
    }
    
    if (time() > $data['expires']) {
        @unlink($file);
        return null;
    }
    
    return $data['value'];
}

/**
 * Сохранить значение в кэш
 * 
 * @param string $key Ключ кэша
 * @param mixed $value Значение для кэширования
 * @param int $ttl Время жизни в секундах
 * @return bool
 */
function cache_set(string $key, $value, int $ttl = 3600): bool
{
    $cacheDir = sys_get_temp_dir() . '/medialib_cache';
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $file = $cacheDir . '/' . md5($key) . '.cache';
    
    $data = [
        'expires' => time() + $ttl,
        'value' => $value
    ];
    
    return @file_put_contents($file, serialize($data), LOCK_EX) !== false;
}

/**
 * Удалить значение из кэша
 * 
 * @param string $key
 * @return bool
 */
function cache_delete(string $key): bool
{
    $cacheDir = sys_get_temp_dir() . '/medialib_cache';
    $file = $cacheDir . '/' . md5($key) . '.cache';
    
    if (file_exists($file)) {
        return @unlink($file);
    }
    
    return true;
}

/**
 * Очистить весь кэш
 * 
 * @return int Количество удаленных файлов
 */
function cache_clear(): int
{
    $cacheDir = sys_get_temp_dir() . '/medialib_cache';
    
    if (!is_dir($cacheDir)) {
        return 0;
    }
    
    $files = glob($cacheDir . '/*.cache');
    $count = 0;
    
    foreach ($files as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }
    
    return $count;
}

