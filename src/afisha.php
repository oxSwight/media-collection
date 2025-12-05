<?php
// src/afisha.php - страница афиши (предстоящие фильмы)

require_once 'includes/init.php';
require_once 'includes/pagination.php';

$myId = $_SESSION['user_id'] ?? 0;

if (!$myId) {
    header('Location: login.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$mode   = $_GET['mode'] ?? 'recommended'; // recommended | all
$refresh = isset($_GET['refresh']); // Флаг обновления для рандомизации

// Параметры пагинации
$pagination = get_pagination_params(20);
$page    = $pagination['page'];
$perPage = $pagination['per_page'];
$offset  = $pagination['offset'];

// Seed для рандомизации (используем timestamp для уникальности)
$randomSeed = $refresh ? time() : ($_SESSION['afisha_random_seed'] ?? time());
$_SESSION['afisha_random_seed'] = $randomSeed;

// 1. Собираем список уже просмотренных фильмов (по названию)
$seenTitles = [];
$stmtSeen = $pdo->prepare("SELECT LOWER(title) FROM media_items WHERE user_id = ? AND type = 'movie'");
$stmtSeen->execute([$myId]);
foreach ($stmtSeen->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $seenTitles[$t] = true;
}

// 2. Строим базовый запрос по афише (ТОЛЬКО именованные параметры, без смешивания)
// Фильтруем только фильмы от 2000 года
$countSql = "SELECT COUNT(*) FROM upcoming_movies WHERE 1=1";
$dataSql  = "SELECT * FROM upcoming_movies WHERE 1=1";

// Фильтр по году - только от 2000 года
$countSql .= " AND (release_date IS NULL OR EXTRACT(YEAR FROM release_date) >= 2000)";
$dataSql  .= " AND (release_date IS NULL OR EXTRACT(YEAR FROM release_date) >= 2000)";

$countParams = [':uid' => $myId];
$dataParams  = [':uid' => $myId];

// Улучшенный поиск с токенизацией и приоритетами
$searchYear = null;
$searchWords = [];
$exactPhrase = null;

if ($search !== '') {
    // Извлекаем год из запроса (4 цифры)
    if (preg_match('/\b(19|20)\d{2}\b/', $search, $m)) {
        $searchYear = (int)$m[0];
        $search = preg_replace('/\b(19|20)\d{2}\b/', '', $search); // Удаляем год из поискового запроса
        $search = trim($search);
    }
    
    // Проверяем наличие точной фразы в кавычках
    if (preg_match('/"([^"]+)"/', $search, $m)) {
        $exactPhrase = trim($m[1]);
        $search = preg_replace('/"[^"]+"/', '', $search); // Удаляем фразу из запроса
        $search = trim($search);
    }
    
    // Токенизация: разбиваем на слова, убираем пустые
    $words = preg_split('/\s+/', $search);
    $searchWords = array_filter(array_map('trim', $words), function($w) {
        return mb_strlen($w) > 0;
    });
    $searchWords = array_values($searchWords);
    
    // Если нет слов после обработки, но есть год или фраза
    if (empty($searchWords) && $searchYear === null && $exactPhrase === null) {
        // Используем весь исходный запрос как одно слово
        $searchWords = [trim($search)];
    }
}

// Построение условий поиска
$searchConditions = [];
$searchOrderBy = [];
$paramIndex = 0;

if (!empty($searchWords) || $exactPhrase !== null || $searchYear !== null) {
    $searchParts = [];
    
    // Точная фраза (высший приоритет)
    if ($exactPhrase !== null) {
        $escapedPhrase = str_replace(['%', '_'], ['\\%', '\\_'], $exactPhrase);
        $phraseLike = '%' . $escapedPhrase . '%';
        
        $searchParts[] = "(
            title ILIKE :exact_title 
            OR original_title ILIKE :exact_orig 
            OR overview ILIKE :exact_overview
        )";
        
        $countParams[':exact_title'] = $phraseLike;
        $countParams[':exact_orig'] = $phraseLike;
        $countParams[':exact_overview'] = $phraseLike;
        
        $dataParams[':exact_title'] = $phraseLike;
        $dataParams[':exact_orig'] = $phraseLike;
        $dataParams[':exact_overview'] = $phraseLike;
    }
    
    // Поиск по словам: если одно слово - точный поиск, если несколько - OR логика (хотя бы одно слово)
    // Но также добавляем поиск по полной фразе для лучших результатов
    if (!empty($searchWords)) {
        // Сначала добавляем поиск по полной фразе (если несколько слов)
        if (count($searchWords) > 1) {
            $fullPhrase = implode(' ', $searchWords);
            $escapedPhrase = str_replace(['%', '_'], ['\\%', '\\_'], $fullPhrase);
            $phraseLike = '%' . $escapedPhrase . '%';
            
            $phraseKey = ':phrase_' . $paramIndex++;
            $searchParts[] = "(
                title ILIKE {$phraseKey}_title 
                OR original_title ILIKE {$phraseKey}_orig 
                OR overview ILIKE {$phraseKey}_overview
            )";
            
            $countParams[$phraseKey . '_title'] = $phraseLike;
            $countParams[$phraseKey . '_orig'] = $phraseLike;
            $countParams[$phraseKey . '_overview'] = $phraseLike;
            
            $dataParams[$phraseKey . '_title'] = $phraseLike;
            $dataParams[$phraseKey . '_orig'] = $phraseLike;
            $dataParams[$phraseKey . '_overview'] = $phraseLike;
        }
        
        // Затем добавляем поиск по отдельным словам (OR логика - хотя бы одно слово должно быть найдено)
        // Но для лучшей релевантности используем AND только если слов 2 или меньше
        $wordConditions = [];
        foreach ($searchWords as $word) {
            $escapedWord = str_replace(['%', '_'], ['\\%', '\\_'], $word);
            $wordLike = '%' . $escapedWord . '%';
            
            // Каждое слово должно быть найдено хотя бы в одном поле
            $wordKey = ':word_' . $paramIndex++;
            $wordConditions[] = "(
                title ILIKE {$wordKey}_title 
                OR original_title ILIKE {$wordKey}_orig 
                OR overview ILIKE {$wordKey}_overview
                OR genres::text ILIKE {$wordKey}_genres
            )";
            
            $countParams[$wordKey . '_title'] = $wordLike;
            $countParams[$wordKey . '_orig'] = $wordLike;
            $countParams[$wordKey . '_overview'] = $wordLike;
            $countParams[$wordKey . '_genres'] = $wordLike;
            
            $dataParams[$wordKey . '_title'] = $wordLike;
            $dataParams[$wordKey . '_orig'] = $wordLike;
            $dataParams[$wordKey . '_overview'] = $wordLike;
            $dataParams[$wordKey . '_genres'] = $wordLike;
        }
        
        // Если слов 2 или меньше, используем AND (оба слова должны быть найдены)
        // Если больше 2 слов, используем OR (хотя бы одно слово)
        if (!empty($wordConditions)) {
            if (count($searchWords) <= 2) {
                // Для коротких запросов (1-2 слова) используем AND для точности
                $searchParts[] = '(' . implode(' AND ', $wordConditions) . ')';
            } else {
                // Для длинных запросов используем OR для широты поиска
                $searchParts[] = '(' . implode(' OR ', $wordConditions) . ')';
            }
        }
    }
    
    // Фильтр по году
    if ($searchYear !== null) {
        $countSql .= " AND EXTRACT(YEAR FROM release_date) = :search_year";
        $dataSql  .= " AND EXTRACT(YEAR FROM release_date) = :search_year";
        $countParams[':search_year'] = $searchYear;
        $dataParams[':search_year'] = $searchYear;
    }
    
    // Объединяем все условия поиска (OR между фразой и словами, если есть и то и другое)
    if (!empty($searchParts)) {
        $searchCondition = '(' . implode(' OR ', $searchParts) . ')';
        $countSql .= " AND " . $searchCondition;
        $dataSql  .= " AND " . $searchCondition;
        
        // Подготовка сортировки по релевантности (будет применена позже)
        $hasSearch = true;
    } else {
        $hasSearch = false;
    }
} else {
    $hasSearch = false;
}

// Не показываем фильмы, которые уже есть в личной коллекции (по названию)
$countSql .= " AND NOT EXISTS (
    SELECT 1 FROM media_items mi
    WHERE mi.user_id = :uid
      AND mi.type = 'movie'
      AND LOWER(mi.title) = LOWER(upcoming_movies.title)
)";
$dataSql .= " AND NOT EXISTS (
    SELECT 1 FROM media_items mi
    WHERE mi.user_id = :uid
      AND mi.type = 'movie'
      AND LOWER(mi.title) = LOWER(upcoming_movies.title)
)";

// Считаем общее количество
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

// Сортировка: при поиске - по релевантности, без поиска - случайный порядок
if ($hasSearch) {
    // При поиске сортируем по популярности и рейтингу (релевантность будет вычислена в PHP)
    $dataSql .= " ORDER BY popularity DESC NULLS LAST, vote_average DESC NULLS LAST, title ASC";
} else {
    // Без поиска - случайный порядок с seed для стабильности при пагинации
    $dataSql .= " ORDER BY MD5(id::text || :seed)";
    $dataParams[':seed'] = (string)$randomSeed;
}

$dataSql .= " LIMIT :limit OFFSET :offset";
$dataParams[':limit']  = $perPage;
$dataParams[':offset'] = $offset;

$stmt = $pdo->prepare($dataSql);
$stmt->execute($dataParams);
$movies = $stmt->fetchAll();

// Вычисляем релевантность и сортируем результаты при поиске
if ($hasSearch && !empty($movies)) {
    foreach ($movies as &$movie) {
        $relevance = 0;
        $titleLower = mb_strtolower($movie['title'] ?? '');
        $origTitleLower = mb_strtolower($movie['original_title'] ?? '');
        $overviewLower = mb_strtolower($movie['overview'] ?? '');
        
        // Точная фраза
        if ($exactPhrase !== null) {
            $phraseLower = mb_strtolower($exactPhrase);
            if ($titleLower === $phraseLower) {
                $relevance += 1000; // Точное совпадение в названии
            } elseif (mb_strpos($titleLower, $phraseLower) === 0) {
                $relevance += 500; // Начинается с фразы
            } elseif (mb_strpos($titleLower, $phraseLower) !== false) {
                $relevance += 200; // Содержит фразу в названии
            } elseif (mb_strpos($origTitleLower, $phraseLower) !== false) {
                $relevance += 150; // В оригинальном названии
            } elseif (mb_strpos($overviewLower, $phraseLower) !== false) {
                $relevance += 50; // В описании
            }
        }
        
        // Поиск по словам
        foreach ($searchWords as $word) {
            $wordLower = mb_strtolower($word);
            if ($titleLower === $wordLower) {
                $relevance += 500; // Точное совпадение слова в названии
            } elseif (mb_strpos($titleLower, $wordLower) === 0) {
                $relevance += 300; // Начинается со слова
            } elseif (mb_strpos($titleLower, $wordLower) !== false) {
                $relevance += 100; // Содержит слово в названии
            } elseif (mb_strpos($origTitleLower, $wordLower) !== false) {
                $relevance += 75; // В оригинальном названии
            } elseif (mb_strpos($overviewLower, $wordLower) !== false) {
                $relevance += 25; // В описании
            }
        }
        
        // Бонус за популярность и рейтинг
        if (!empty($movie['popularity']) && is_numeric($movie['popularity'])) {
            $relevance += min(50, (float)$movie['popularity'] / 10); // До 50 баллов за популярность
        }
        if (!empty($movie['vote_average']) && is_numeric($movie['vote_average'])) {
            $relevance += (float)$movie['vote_average'] * 5; // До 50 баллов за рейтинг
        }
        
        $movie['_relevance'] = $relevance;
    }
    unset($movie);
    
    // Сортируем по релевантности
    usort($movies, function($a, $b) {
        $relA = $a['_relevance'] ?? 0;
        $relB = $b['_relevance'] ?? 0;
        if ($relB !== $relA) {
            return $relB <=> $relA;
        }
        // Если релевантность одинаковая, сортируем по популярности
        $popA = (float)($a['popularity'] ?? 0);
        $popB = (float)($b['popularity'] ?? 0);
        if ($popB !== $popA) {
            return $popB <=> $popA;
        }
        return 0;
    });
}

// 3. Улучшенный алгоритм рекомендаций: анализируем жанры, описания, тематику, годы, популярность и рейтинг
$favoriteGenres = [];
$favoriteKeywords = [];
$favoriteThemes = [];
$favoriteYears = [];
$avgPopularity = 0;
$avgVoteAverage = 0;
$popularityCount = 0;
$voteCount = 0;

// Получаем все фильмы пользователя для анализа (с учетом оценок)
$userMoviesStmt = $pdo->prepare("
    SELECT genres, review, title, author_director, release_year, rating
    FROM media_items 
    WHERE user_id = ? AND type = 'movie'
");
$userMoviesStmt->execute([$myId]);
$userMovies = $userMoviesStmt->fetchAll();

// Вычисляем среднюю оценку пользователя и предпочтения по высоким оценкам
$userAvgRating = 0;
$highRatedMovies = []; // Фильмы с оценкой >= 7
$veryHighRatedMovies = []; // Фильмы с оценкой >= 9
$ratings = array_filter(array_column($userMovies, 'rating'));
if (!empty($ratings)) {
    $userAvgRating = array_sum($ratings) / count($ratings);
    foreach ($userMovies as $um) {
        if (!empty($um['rating'])) {
            $rating = (int)$um['rating'];
            if ($rating >= 9) {
                $veryHighRatedMovies[] = $um;
            } elseif ($rating >= 7) {
                $highRatedMovies[] = $um;
            }
        }
    }
}

// Анализ жанров и годов (с учетом оценок - высоко оцененные фильмы имеют больший вес)
foreach ($userMovies as $um) {
    $weight = 1; // Базовый вес
    if (!empty($um['rating'])) {
        $rating = (int)$um['rating'];
        // Высоко оцененные фильмы имеют больший вес в анализе
        if ($rating >= 9) {
            $weight = 3; // Очень высоко оцененные
        } elseif ($rating >= 7) {
            $weight = 2; // Высоко оцененные
        } elseif ($rating <= 4) {
            $weight = 0.5; // Низко оцененные - меньше влияют
        }
    }
    
    // Жанры
    if (!empty($um['genres'])) {
        $parts = preg_split('/[,\s]+/', $um['genres']);
        foreach ($parts as $g) {
            $g = trim($g);
            if ($g === '') continue;
            $favoriteGenres[$g] = ($favoriteGenres[$g] ?? 0) + $weight;
        }
    }
    
    // Годы выпуска (анализируем предпочтения по годам)
    if (!empty($um['release_year']) && $um['release_year'] >= 1900 && $um['release_year'] <= date('Y')) {
        $year = (int)$um['release_year'];
        // Группируем по десятилетиям для более гибкого анализа
        $decade = floor($year / 10) * 10;
        $favoriteYears[$decade] = ($favoriteYears[$decade] ?? 0) + $weight;
    }
}

// Анализ высоко оцененных фильмов отдельно (для более точных рекомендаций)
foreach ($veryHighRatedMovies as $um) {
    if (!empty($um['genres'])) {
        $parts = preg_split('/[,\s]+/', $um['genres']);
        foreach ($parts as $g) {
            $g = trim($g);
            if ($g !== '') {
                $favoriteGenres[$g] = ($favoriteGenres[$g] ?? 0) + 2; // Дополнительный бонус
            }
        }
    }
}

// Анализ ключевых слов из описаний и рецензий
$commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'];
$stopWords = array_merge($commonWords, ['film', 'movie', 'фильм', 'кино', 'film', 'movie']);

foreach ($userMovies as $um) {
    $text = strtolower(($um['review'] ?? '') . ' ' . ($um['title'] ?? '') . ' ' . ($um['author_director'] ?? ''));
    $words = preg_split('/[\s\p{P}]+/u', $text);
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 3 && !in_array($word, $stopWords, true)) {
            $favoriteKeywords[$word] = ($favoriteKeywords[$word] ?? 0) + 1;
        }
    }
}

// Анализ тематики (ключевые слова из описаний фильмов в афише)
foreach ($userMovies as $um) {
    if (!empty($um['review'])) {
        $review = strtolower($um['review']);
        // Ищем тематические слова (длина > 4 символов)
        $themes = preg_split('/[\s\p{P}]+/u', $review);
        foreach ($themes as $theme) {
            $theme = trim($theme);
            if (strlen($theme) > 4 && !in_array($theme, $stopWords, true)) {
                $favoriteThemes[$theme] = ($favoriteThemes[$theme] ?? 0) + 1;
            }
        }
    }
}

// Сортируем и берем топ-5 жанров, топ-10 ключевых слов/тем и топ-3 десятилетия
arsort($favoriteGenres);
arsort($favoriteKeywords);
arsort($favoriteThemes);
arsort($favoriteYears);

$topGenres = array_slice(array_keys($favoriteGenres), 0, 5);
$topKeywords = array_slice(array_keys($favoriteKeywords), 0, 10);
$topThemes = array_slice(array_keys($favoriteThemes), 0, 10);
$topDecades = array_slice(array_keys($favoriteYears), 0, 3);

// Вычисляем средние значения для популярности и рейтинга (если есть данные в коллекции)
// Это поможет рекомендовать фильмы с похожей популярностью/рейтингом

// Улучшенная фильтрация рекомендованных фильмов с учетом всех факторов
$recommendedMovies = [];
$userMovieCount = count($userMovies);

// Если у пользователя мало фильмов, показываем больше рекомендаций
if ($userMovieCount < 3) {
    // Для новых пользователей показываем топ-фильмы по популярности и рейтингу
    foreach ($movies as $m) {
        $score = 0;
        
        // Базовый бонус за высокий рейтинг
        if (!empty($m['vote_average']) && (float)$m['vote_average'] >= 7.0) {
            $score += 3;
        } elseif (!empty($m['vote_average']) && (float)$m['vote_average'] >= 6.0) {
            $score += 1;
        }
        
        // Бонус за популярность
        if (!empty($m['popularity']) && (float)$m['popularity'] > 50) {
            $score += 2;
        } elseif (!empty($m['popularity']) && (float)$m['popularity'] > 10) {
            $score += 1;
        }
        
        // Бонус за недавний релиз
        if (!empty($m['release_date'])) {
            $releaseYear = (int)date('Y', strtotime($m['release_date']));
            $currentYear = (int)date('Y');
            if ($releaseYear >= $currentYear - 1) {
                $score += 1;
            }
        }
        
        if ($score > 0) {
            $m['recommendation_score'] = $score;
            $recommendedMovies[] = $m;
        }
    }
} elseif (!empty($topGenres) || !empty($topKeywords) || !empty($topThemes) || !empty($topDecades)) {
    foreach ($movies as $m) {
        $score = 0;
        
        // 1. Проверка жанров (вес: 4 - самый важный фактор)
        if (!empty($m['genres'])) {
            $movieGenres = preg_split('/[,\s]+/', $m['genres']);
            foreach ($movieGenres as $mg) {
                $mg = trim($mg);
                if (in_array($mg, $topGenres, true)) {
                    $score += 4;
                }
            }
        }
        
        // 2. Проверка года выпуска (вес: 2)
        if (!empty($m['release_date'])) {
            $movieYear = (int)date('Y', strtotime($m['release_date']));
            $movieDecade = floor($movieYear / 10) * 10;
            if (in_array($movieDecade, $topDecades, true)) {
                $score += 2;
            }
        }
        
        // 3. Проверка ключевых слов в названии и описании (вес: 2)
        $movieText = strtolower(($m['title'] ?? '') . ' ' . ($m['original_title'] ?? '') . ' ' . ($m['overview'] ?? ''));
        foreach ($topKeywords as $keyword) {
            if (stripos($movieText, $keyword) !== false) {
                $score += 2;
            }
        }
        
        // 4. Проверка тематики в описании (вес: 1)
        if (!empty($m['overview'])) {
            $overview = strtolower($m['overview']);
            foreach ($topThemes as $theme) {
                if (stripos($overview, $theme) !== false) {
                    $score += 1;
                }
            }
        }
        
        // 5. Учет популярности (вес: 1) - бонус за высокую популярность
        if (!empty($m['popularity']) && is_numeric($m['popularity'])) {
            $popularity = (float)$m['popularity'];
            // Если популярность выше среднего, добавляем бонус
            if ($popularity > 10) { // Порог популярности
                $score += 1;
            }
            if ($popularity > 50) { // Очень популярные фильмы
                $score += 1;
            }
        }
        
        // 6. Учет рейтинга TMDb (вес: 2) - бонус за высокий рейтинг
        if (!empty($m['vote_average']) && is_numeric($m['vote_average'])) {
            $voteAvg = (float)$m['vote_average'];
            // Рейтинг от 0 до 10, добавляем бонус за рейтинг выше 7
            if ($voteAvg >= 7.0) {
                $score += 2;
            } elseif ($voteAvg >= 6.0) {
                $score += 1;
            }
            
            // Дополнительный бонус, если рейтинг TMDb близок к средней оценке пользователя
            if ($userAvgRating > 0 && abs($voteAvg - $userAvgRating) <= 1.5) {
                $score += 1;
            }
        }
        
        // Добавляем фильм в рекомендации, если:
        // 1. Набрал хотя бы 1 балл ИЛИ
        // 2. Имеет высокий рейтинг TMDb (>= 7.0) ИЛИ
        // 3. Имеет высокую популярность (> 50) ИЛИ
        // 4. Если у пользователя мало фильмов в коллекции - показываем больше рекомендаций
        $userMovieCount = count($userMovies);
        $minScore = $userMovieCount < 5 ? 0 : 1; // Если меньше 5 фильмов, показываем все с score >= 0
        
        if ($score >= $minScore || 
            (!empty($m['vote_average']) && (float)$m['vote_average'] >= 7.0) ||
            (!empty($m['popularity']) && (float)$m['popularity'] > 50)) {
            $m['recommendation_score'] = $score;
            $recommendedMovies[] = $m;
        }
    }
    
    // Сортируем по score (лучшие рекомендации первыми), затем по популярности и рейтингу
    usort($recommendedMovies, function($a, $b) {
        $scoreA = $a['recommendation_score'] ?? 0;
        $scoreB = $b['recommendation_score'] ?? 0;
        
        // Сначала по score
        if ($scoreB !== $scoreA) {
            return $scoreB - $scoreA;
        }
        
        // Если score одинаковый, сортируем по популярности
        $popA = (float)($a['popularity'] ?? 0);
        $popB = (float)($b['popularity'] ?? 0);
        if ($popB !== $popA) {
            return $popB <=> $popA;
        }
        
        // Если популярность тоже одинаковая, сортируем по рейтингу TMDb
        $voteA = (float)($a['vote_average'] ?? 0);
        $voteB = (float)($b['vote_average'] ?? 0);
        return $voteB <=> $voteA;
    });
}

// Если рекомендаций слишком мало, добавляем фильмы с высоким рейтингом/популярностью
if (count($recommendedMovies) < 10 && $mode === 'recommended') {
    $existingIds = array_column($recommendedMovies, 'id');
    foreach ($movies as $m) {
        if (in_array($m['id'], $existingIds)) continue;
        
        $addScore = 0;
        if (!empty($m['vote_average']) && (float)$m['vote_average'] >= 7.5) {
            $addScore += 2;
        }
        if (!empty($m['popularity']) && (float)$m['popularity'] > 30) {
            $addScore += 1;
        }
        
        if ($addScore > 0) {
            $m['recommendation_score'] = $addScore;
            $recommendedMovies[] = $m;
        }
    }
    
    // Пересортируем с учетом новых фильмов
    usort($recommendedMovies, function($a, $b) {
        $scoreA = $a['recommendation_score'] ?? 0;
        $scoreB = $b['recommendation_score'] ?? 0;
        if ($scoreB !== $scoreA) {
            return $scoreB - $scoreA;
        }
        $popA = (float)($a['popularity'] ?? 0);
        $popB = (float)($b['popularity'] ?? 0);
        if ($popB !== $popA) {
            return $popB <=> $popA;
        }
        $voteA = (float)($a['vote_average'] ?? 0);
        $voteB = (float)($b['vote_average'] ?? 0);
        return $voteB <=> $voteA;
    });
}

// Выбор набора для отображения
// Если есть поисковый запрос, всегда показываем результаты поиска, независимо от режима
// Алгоритм рекомендаций применяется только когда нет поискового запроса
if ($search !== '') {
    // При поиске показываем все найденные результаты
    $moviesToShow = $movies;
} else {
    // Без поиска используем алгоритм рекомендаций или все фильмы
    $moviesToShow = ($mode === 'all' || empty($recommendedMovies)) ? $movies : $recommendedMovies;
}

// Перегенерируем количество для отображаемого набора (только визуально)
$visibleCount = count($moviesToShow);

$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = render_pagination($page, $totalPages, 'afisha.php');
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="header-actions">
        <a href="admin_afisha_refresh.php" class="btn-register" style="text-decoration: none;" title="<?= htmlspecialchars(t('afisha.refresh_btn_title')) ?>">
            <?= htmlspecialchars(t('afisha.refresh_btn')) ?>
        </a>
        <?php if ($mode === 'all'): ?>
            <a href="afisha.php?mode=all&refresh=1<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn-register" style="text-decoration: none; margin-left: 10px;" title="<?= htmlspecialchars(t('afisha.randomize_btn_title')) ?>">
                <?= htmlspecialchars(t('afisha.randomize_btn')) ?>
            </a>
        <?php endif; ?>
    </div>

    <p style="color:#636e72; margin-bottom:20px;">
        <?= htmlspecialchars(t('afisha.description')) ?>
    </p>

    <form action="afisha.php" method="GET" class="search-form afisha-search-form" style="margin-bottom: 20px; gap: 10px;">
        <input
            type="text"
            name="q"
            placeholder="<?= htmlspecialchars(t('afisha.search_placeholder')) ?>"
            value="<?= htmlspecialchars($search) ?>"
            class="search-input"
            style="flex:1; min-width: 180px;"
        >
        <div class="afisha-mode-toggle">
            <button type="submit" name="mode" value="recommended" class="mode-btn <?= $mode === 'recommended' ? 'active' : '' ?>">
                <?= htmlspecialchars(t('afisha.mode_recommended')) ?>
            </button>
            <button type="submit" name="mode" value="all" class="mode-btn <?= $mode === 'all' ? 'active' : '' ?>">
                <?= htmlspecialchars(t('afisha.mode_all')) ?>
            </button>
        </div>
        <button type="submit" class="btn-submit afisha-submit-btn" style="width:auto;">
            <?= htmlspecialchars(t('afisha.filter_btn')) ?>
        </button>
    </form>

    <?php if ($visibleCount === 0): ?>
        <div class="empty-state">
            <?php if (!empty($topGenres) && $mode === 'recommended'): ?>
                <p><?= htmlspecialchars(t('afisha.no_recommended')) ?></p>
            <?php else: ?>
                <p><?= htmlspecialchars(t('afisha.no_movies')) ?></p>
            <?php endif; ?>
            <?php if ($mode === 'recommended'): ?>
                <a href="afisha.php?mode=all" class="btn-submit" style="width:auto; text-decoration:none; margin-top:10px;">
                    <?= htmlspecialchars(t('afisha.show_all')) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 15px; color: #636e72; font-size: 0.9rem;">
            <?= htmlspecialchars(t('afisha.found')) ?> <strong><?= (int)$visibleCount ?></strong>
        </div>

        <div class="media-grid">
            <?php foreach ($moviesToShow as $movie): ?>
                <div class="media-card"
                     onclick="openAfishaModal(this)"
                     data-title="<?= htmlspecialchars($movie['title']) ?>"
                     data-original-title="<?= htmlspecialchars($movie['original_title'] ?? '') ?>"
                     data-overview="<?= htmlspecialchars($movie['overview'] ?? '') ?>"
                     data-poster="<?= htmlspecialchars($movie['poster_url'] ?? '') ?>"
                     data-release-date="<?= htmlspecialchars($movie['release_date'] ?? '') ?>">
                    <div class="media-image">
                        <?php if (!empty($movie['poster_url'])): ?>
                            <img src="<?= htmlspecialchars($movie['poster_url']) ?>" alt="Poster">
                        <?php else: ?>
                            <div class="no-image">No poster</div>
                        <?php endif; ?>
                        <?php if (!empty($movie['release_date'])): ?>
                            <div class="media-rating">
                                <?= htmlspecialchars(date('Y-m-d', strtotime($movie['release_date']))) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="media-content">
                        <span class="media-type type-movie">🎬</span>
                        <h3><?= htmlspecialchars($movie['title']) ?></h3>
                        <?php if (!empty($movie['original_title']) && $movie['original_title'] !== $movie['title']): ?>
                            <div class="media-meta" style="font-size:0.85rem; color:#b2bec3;">
                                <?= htmlspecialchars($movie['original_title']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($movie['overview'])): ?>
                            <p class="media-review">
                                <?= nl2br(htmlspecialchars(mb_strimwidth($movie['overview'], 0, 140, "..."))) ?>
                            </p>
                        <?php endif; ?>

                        <div class="afisha-buttons-wrapper">
                            <form method="POST" action="afisha_add.php" onsubmit="event.stopPropagation();">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="upcoming_id" value="<?= (int)$movie['id'] ?>">
                                <button type="submit" class="afisha-add-btn">
                                    <span class="plus-icon">+</span>
                                    <?= htmlspecialchars(t('afisha.add_to_collection')) ?>
                                </button>
                            </form>
                            <form method="POST" action="watchlist.php" onsubmit="event.stopPropagation();">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="add_to_watchlist" value="1">
                                <input type="hidden" name="upcoming_id" value="<?= (int)$movie['id'] ?>">
                                <input type="hidden" name="title" value="<?= htmlspecialchars($movie['title']) ?>">
                                <button type="submit" class="afisha-watchlist-btn">
                                    ⭐ <?= htmlspecialchars(t('watchlist.add')) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?= $paginationHtml ?>
    <?php endif; ?>
</div>

<!-- Модальное окно для полного описания фильма (афиша) -->
<div id="afishaModal" class="modal-overlay" onclick="closeAfishaModal(event)">
    <div class="modal-content">
        <div class="modal-close" onclick="closeAfishaModalDirect()">&times;</div>

        <div class="modal-image-wrapper" id="afishaImgWrapper" style="display: none;">
            <img id="afishaPoster" class="modal-image-large" alt="Poster">
        </div>

        <div class="modal-body">
            <div class="modal-header-row">
                <div>
                    <span class="media-type type-movie" style="margin-bottom: 5px;">🎬</span>
                    <h2 id="afishaTitle" class="modal-title"></h2>
                    <p id="afishaOriginal" style="color: #636e72; margin: 5px 0 0 0; font-weight: 500;"></p>
                </div>
                <div id="afishaDate" style="font-weight: 800; color: #fdcb6e; background: #2d3436; padding: 5px 10px; border-radius: 10px; font-size: 0.9rem; white-space: nowrap;"></div>
            </div>

            <hr style="border: 0; border-top: 1px solid #f1f2f6; margin: 15px 0;">

            <h4 style="margin: 0 0 10px 0; color: #b2bec3; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">
                <?= htmlspecialchars(t('item.review')) ?>
            </h4>
            <div id="afishaOverview" style="line-height: 1.6; color: #2d3436; font-size: 1rem;"></div>
        </div>
    </div>
</div>

<script>
// Сохраняем выбранный режим и запрос афиши
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.afisha-search-form');
    if (!form) return;
    const qInput = form.querySelector('input[name="q"]');

    // Восстанавливаем
    try {
        const saved = JSON.parse(localStorage.getItem('afishaFilters') || '{}');
        if (saved.q && qInput && !qInput.value) {
            qInput.value = saved.q;
        }
    } catch (e) {}

    form.addEventListener('submit', function() {
        const formData = new FormData(form);
        const data = {
            q: formData.get('q') || '',
            mode: formData.get('mode') || 'recommended'
        };
        try {
            localStorage.setItem('afishaFilters', JSON.stringify(data));
        } catch (e) {}
    });
});
function openAfishaModal(card) {
    const title   = card.getAttribute('data-title') || '';
    const original = card.getAttribute('data-original-title') || '';
    const overview = card.getAttribute('data-overview') || '';
    const poster   = card.getAttribute('data-poster') || '';
    const date     = card.getAttribute('data-release-date') || '';

    document.getElementById('afishaTitle').textContent = title;
    const origElem = document.getElementById('afishaOriginal');
    if (original && original !== title) {
        origElem.textContent = original;
        origElem.style.display = 'block';
    } else {
        origElem.style.display = 'none';
    }

    const dateElem = document.getElementById('afishaDate');
    if (date) {
        dateElem.textContent = date;
        dateElem.style.display = 'block';
    } else {
        dateElem.style.display = 'none';
    }

    const overviewElem = document.getElementById('afishaOverview');
    overviewElem.textContent = '';
    if (overview) {
        const lines = overview.split('\n');
        lines.forEach((line, i) => {
            if (i > 0) overviewElem.appendChild(document.createElement('br'));
            overviewElem.appendChild(document.createTextNode(line));
        });
    }

    const imgWrapper = document.getElementById('afishaImgWrapper');
    const img = document.getElementById('afishaPoster');
    if (poster) {
        img.src = poster;
        imgWrapper.style.display = 'flex';
    } else {
        imgWrapper.style.display = 'none';
    }

    document.getElementById('afishaModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeAfishaModal(event) {
    if (event.target.id === 'afishaModal') {
        closeAfishaModalDirect();
    }
}

function closeAfishaModalDirect() {
    document.getElementById('afishaModal').classList.remove('open');
    document.body.style.overflow = 'auto';
}
</script>

<?php require_once 'includes/footer.php'; ?>


