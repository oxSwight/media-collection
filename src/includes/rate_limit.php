<?php
// src/includes/rate_limit.php - Rate limiting для защиты от злоупотреблений

/**
 * Простой rate limiter на основе файлов (для продакшена лучше использовать Redis)
 * 
 * @param string $key Уникальный ключ (например, user_id или IP)
 * @param int $maxRequests Максимальное количество запросов
 * @param int $windowSeconds Временное окно в секундах
 * @return bool true если запрос разрешен, false если превышен лимит
 */
function checkRateLimit(string $key, int $maxRequests = 60, int $windowSeconds = 60): bool
{
    $cacheDir = sys_get_temp_dir() . '/medialib_rate_limit';
    
    // Создаем директорию если её нет
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $file = $cacheDir . '/' . md5($key) . '.json';
    $now = time();
    
    // Читаем существующие данные
    $data = ['count' => 0, 'reset' => $now + $windowSeconds];
    
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            $decoded = @json_decode($content, true);
            if ($decoded && isset($decoded['reset'])) {
                // Если окно истекло, сбрасываем счетчик
                if ($now < $decoded['reset']) {
                    $data = $decoded;
                }
            }
        }
    }
    
    // Увеличиваем счетчик
    $data['count']++;
    $data['reset'] = $now + $windowSeconds;
    
    // Проверяем лимит
    if ($data['count'] > $maxRequests) {
        return false;
    }
    
    // Сохраняем обновленные данные
    @file_put_contents($file, json_encode($data), LOCK_EX);
    
    // Очищаем старые файлы (старше 1 часа)
    if (rand(1, 100) === 1) { // Вероятность 1% при каждом запросе
        $files = glob($cacheDir . '/*.json');
        foreach ($files as $oldFile) {
            if (filemtime($oldFile) < $now - 3600) {
                @unlink($oldFile);
            }
        }
    }
    
    return true;
}

/**
 * Получить ключ для rate limiting на основе IP и user_id
 * 
 * @return string
 */
function getRateLimitKey(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user_id'] ?? 'guest';
    return $ip . '_' . $userId;
}

/**
 * Проверить rate limit и вернуть ошибку если превышен
 * 
 * @param int $maxRequests
 * @param int $windowSeconds
 * @return array|null null если OK, массив с ошибкой если превышен лимит
 */
function enforceRateLimit(int $maxRequests = 60, int $windowSeconds = 60): ?array
{
    $key = getRateLimitKey();
    
    if (!checkRateLimit($key, $maxRequests, $windowSeconds)) {
        http_response_code(429);
        return [
            'success' => false,
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $windowSeconds
        ];
    }
    
    return null;
}

