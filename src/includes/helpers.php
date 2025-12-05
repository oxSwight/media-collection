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
    
    // Улучшенный поиск с токенизацией
    $searchWords = [];
    $exactPhrase = null;
    $searchYear = null;
    
    if (!empty($search)) {
        // Извлекаем год из запроса (4 цифры)
        if (preg_match('/\b(19|20)\d{2}\b/', $search, $m)) {
            $searchYear = (int)$m[0];
            $search = preg_replace('/\b(19|20)\d{2}\b/', '', $search);
            $search = trim($search);
        }
        
        // Проверяем наличие точной фразы в кавычках
        if (preg_match('/"([^"]+)"/', $search, $m)) {
            $exactPhrase = trim($m[1]);
            $search = preg_replace('/"[^"]+"/', '', $search);
            $search = trim($search);
        }
        
        // Токенизация: разбиваем на слова
        $words = preg_split('/\s+/', $search);
        $searchWords = array_filter(array_map('trim', $words), function($w) {
            return mb_strlen($w) > 0;
        });
        $searchWords = array_values($searchWords);
        
        // Если есть точная фраза, добавляем её
        if ($exactPhrase !== null) {
            array_unshift($searchWords, $exactPhrase);
        }
        
        // Если нет слов после обработки, используем весь запрос
        if (empty($searchWords) && $searchYear === null && $exactPhrase === null) {
            $searchWords = [trim($search)];
        }
        
        // Построение условий поиска
        $searchParts = [];
        
        // Точная фраза
        if ($exactPhrase !== null) {
            $escapedPhrase = str_replace(['%', '_'], ['\\%', '\\_'], $exactPhrase);
            $phraseLike = '%' . $escapedPhrase . '%';
            $searchParts[] = '(title ILIKE ? OR author_director ILIKE ? OR review ILIKE ?)';
            $params[] = $phraseLike;
            $params[] = $phraseLike;
            $params[] = $phraseLike;
        }
        
        // Поиск по каждому слову (AND логика)
        if (!empty($searchWords)) {
            $wordConditions = [];
            foreach ($searchWords as $word) {
                $escapedWord = str_replace(['%', '_'], ['\\%', '\\_'], $word);
                $wordLike = '%' . $escapedWord . '%';
                // Каждое слово должно быть найдено хотя бы в одном поле
                $wordConditions[] = '(title ILIKE ? OR author_director ILIKE ? OR review ILIKE ?)';
                $params[] = $wordLike;
                $params[] = $wordLike;
                $params[] = $wordLike;
            }
            // Все слова должны быть найдены
            if (!empty($wordConditions)) {
                $searchParts[] = '(' . implode(' AND ', $wordConditions) . ')';
            }
        }
        
        // Объединяем условия (OR между фразой и словами)
        if (!empty($searchParts)) {
            $whereConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }
        
        // Фильтр по году
        if ($searchYear !== null) {
            $whereConditions[] = 'release_year = ?';
            $params[] = $searchYear;
        }
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
    // При поиске сортируем по релевантности, без поиска - по дате создания
    if (!empty($searchWords) || $exactPhrase !== null) {
        $sql = "SELECT * FROM media_items WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    } else {
        $sql = "SELECT * FROM media_items WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    }
    
    $dataParams = array_merge($params, [$perPage, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dataParams);
    $items = $stmt->fetchAll();
    
    // Вычисляем релевантность при поиске
    if ((!empty($searchWords) || $exactPhrase !== null) && !empty($items)) {
        foreach ($items as &$item) {
            $relevance = 0;
            $titleLower = mb_strtolower($item['title'] ?? '');
            $authorLower = mb_strtolower($item['author_director'] ?? '');
            $reviewLower = mb_strtolower($item['review'] ?? '');
            
            // Точная фраза
            if ($exactPhrase !== null) {
                $phraseLower = mb_strtolower($exactPhrase);
                if ($titleLower === $phraseLower) {
                    $relevance += 1000;
                } elseif (mb_strpos($titleLower, $phraseLower) === 0) {
                    $relevance += 500;
                } elseif (mb_strpos($titleLower, $phraseLower) !== false) {
                    $relevance += 200;
                } elseif (mb_strpos($authorLower, $phraseLower) !== false) {
                    $relevance += 150;
                } elseif (mb_strpos($reviewLower, $phraseLower) !== false) {
                    $relevance += 50;
                }
            }
            
            // Поиск по словам
            foreach ($searchWords as $word) {
                $wordLower = mb_strtolower($word);
                if ($titleLower === $wordLower) {
                    $relevance += 500;
                } elseif (mb_strpos($titleLower, $wordLower) === 0) {
                    $relevance += 300;
                } elseif (mb_strpos($titleLower, $wordLower) !== false) {
                    $relevance += 100;
                } elseif (mb_strpos($authorLower, $wordLower) !== false) {
                    $relevance += 75;
                } elseif (mb_strpos($reviewLower, $wordLower) !== false) {
                    $relevance += 25;
                }
            }
            
            $item['_relevance'] = $relevance;
        }
        unset($item);
        
        // Сортируем по релевантности
        usort($items, function($a, $b) {
            $relA = $a['_relevance'] ?? 0;
            $relB = $b['_relevance'] ?? 0;
            if ($relB !== $relA) {
                return $relB <=> $relA;
            }
            // Если релевантность одинаковая, сортируем по дате создания
            return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
        });
    }
    
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

