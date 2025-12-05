<?php
// src/includes/helpers.php - Вспомогательные функции

/**
 * Построение SQL запроса с фильтрами для media_items
 * Убирает дублирование кода между index.php и другими файлами
 * 
 * @param PDO $pdo
 * @param int $userId
 * @param string $search Поисковый запрос
 * @param string $filterType Тип фильтра ('movie', 'book' или '')
 * @param int $perPage Количество элементов на странице
 * @param int $offset Смещение для пагинации
 * @return array ['items' => [], 'total' => int, 'totalPages' => int]
 */
function getMediaItemsWithFilters(PDO $pdo, int $userId, string $search = '', string $filterType = '', int $perPage = 20, int $offset = 0): array
{
    // Валидация входных данных
    $userId = max(1, (int)$userId);
    $search = trim($search);
    $filterType = in_array($filterType, ['movie', 'book'], true) ? $filterType : '';
    $perPage = max(1, min(100, (int)$perPage)); // Ограничение от 1 до 100
    $offset = max(0, (int)$offset);
    
    // Строим условия для WHERE
    $whereConditions = ['user_id = ?'];
    $params = [$userId];
    
    // Поиск по названию или автору
    if (!empty($search)) {
        $whereConditions[] = '(title ILIKE ? OR author_director ILIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Фильтр по типу
    if (!empty($filterType) && in_array($filterType, ['movie', 'book'], true)) {
        $whereConditions[] = 'type = ?';
        $params[] = $filterType;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Подсчет общего количества
    $countSql = "SELECT COUNT(*) FROM media_items WHERE $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    
    // Получение данных с пагинацией
    $sql = "SELECT * FROM media_items WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $dataParams = array_merge($params, [$perPage, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dataParams);
    $items = $stmt->fetchAll();
    
    return [
        'items' => $items,
        'total' => $totalItems,
        'totalPages' => $totalPages
    ];
}

/**
 * Безопасная валидация и очистка строки
 * 
 * @param string $value
 * @param int $maxLength Максимальная длина
 * @return string
 */
function sanitizeString(string $value, int $maxLength = 255): string
{
    $value = trim($value);
    $value = strip_tags($value);
    $value = mb_substr($value, 0, $maxLength);
    return $value;
}

/**
 * Валидация года выпуска
 * 
 * @param mixed $year
 * @return int|null Валидный год или null
 */
function validateYear($year): ?int
{
    if (empty($year)) {
        return null;
    }
    
    $year = (int)$year;
    $currentYear = (int)date('Y') + 1;
    
    if ($year >= 1900 && $year <= $currentYear) {
        return $year;
    }
    
    return null;
}

/**
 * Валидация рейтинга
 * 
 * @param mixed $rating
 * @return int|null Валидный рейтинг (1-10) или null
 */
function validateRating($rating): ?int
{
    if (empty($rating)) {
        return null;
    }
    
    $rating = (int)$rating;
    
    if ($rating >= 1 && $rating <= 10) {
        return $rating;
    }
    
    return null;
}

/**
 * Безопасное перенаправление с проверкой URL
 * 
 * @param string $url
 * @param int $statusCode HTTP код ответа
 */
function safeRedirect(string $url, int $statusCode = 302): void
{
    // Проверяем, что URL относительный или того же домена
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($url);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        
        if (isset($parsed['host']) && $parsed['host'] !== $currentHost) {
            // Внешний URL - не разрешаем
            $url = 'index.php';
        }
    }
    
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Форматирование размера файла
 * 
 * @param int $bytes
 * @return string
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

