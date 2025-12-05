<?php
// src/watchlist.php - Список желаний (watchlist)

require_once 'includes/init.php';
require_once 'includes/pagination.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Добавление в watchlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_watchlist'])) {
    require_valid_csrf_token($_POST['_token'] ?? null);
    
    $upcomingId = (int)($_POST['upcoming_id'] ?? 0);
    $externalId = $_POST['external_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Если передан external_id (из прямого поиска TMDb), находим или создаем запись в upcoming_movies
    if (!empty($externalId) && is_numeric($externalId) && !empty($title)) {
        // Ищем фильм по external_id
        $stmt = $pdo->prepare("SELECT id FROM upcoming_movies WHERE external_id = ?");
        $stmt->execute([(string)$externalId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $upcomingId = (int)$existing['id'];
        } else {
            // Если фильма нет в БД, получаем данные из TMDb и сохраняем
            $apiKey = getenv('TMDB_API_KEY');
            if ($apiKey) {
                $langMap = [
                    'pl' => 'pl-PL',
                    'ru' => 'ru-RU',
                    'en' => 'en-US',
                ];
                $apiLang = $langMap[$_SESSION['lang'] ?? 'pl'] ?? 'en-US';
                
                $url = sprintf(
                    'https://api.themoviedb.org/3/movie/%d?api_key=%s&language=%s',
                    (int)$externalId,
                    urlencode($apiKey),
                    urlencode($apiLang)
                );
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($response && $httpCode === 200) {
                    $tmdbData = json_decode($response, true);
                    if ($tmdbData && is_array($tmdbData)) {
                        $genres = '';
                        if (!empty($tmdbData['genres']) && is_array($tmdbData['genres'])) {
                            $genreIds = array_column($tmdbData['genres'], 'id');
                            $genres = implode(',', array_map('intval', $genreIds));
                        }
                        
                        $posterUrl = null;
                        if (!empty($tmdbData['poster_path'])) {
                            $posterUrl = 'https://image.tmdb.org/t/p/w342' . $tmdbData['poster_path'];
                        }
                        
                        // Сохраняем в upcoming_movies
                        $stmt = $pdo->prepare("
                            INSERT INTO upcoming_movies (external_id, title, original_title, overview, poster_url, release_date, genres, popularity, vote_average, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ON CONFLICT (external_id) DO UPDATE SET
                                title = EXCLUDED.title,
                                original_title = EXCLUDED.original_title,
                                overview = EXCLUDED.overview,
                                poster_url = EXCLUDED.poster_url,
                                release_date = EXCLUDED.release_date,
                                genres = EXCLUDED.genres,
                                popularity = EXCLUDED.popularity,
                                vote_average = EXCLUDED.vote_average,
                                updated_at = NOW()
                            RETURNING id
                        ");
                        $stmt->execute([
                            (string)$tmdbData['id'],
                            $tmdbData['title'] ?? '',
                            $tmdbData['original_title'] ?? '',
                            $tmdbData['overview'] ?? '',
                            $posterUrl,
                            $tmdbData['release_date'] ?? null,
                            $genres,
                            $tmdbData['popularity'] ?? null,
                            !empty($tmdbData['vote_average']) ? (float)$tmdbData['vote_average'] : null,
                        ]);
                        $result = $stmt->fetch();
                        $upcomingId = (int)$result['id'];
                    }
                }
            }
        }
    }
    
    if ($upcomingId > 0 && !empty($title)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO watchlist (user_id, upcoming_movie_id, title, type, notes)
                VALUES (?, ?, ?, 'movie', ?)
                ON CONFLICT (user_id, upcoming_movie_id) DO UPDATE SET
                    notes = EXCLUDED.notes,
                    created_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId, $upcomingId, $title, $notes]);
            
            // Проверяем, является ли запрос AJAX-запросом
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                // Возвращаем JSON для AJAX-запроса
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => t('watchlist.added') ?? 'Добавлено в список желаний'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                // Обычный редирект для не-AJAX запросов
                header('Location: watchlist.php?msg=added');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при добавлении в список желаний';
            
            // Проверяем, является ли запрос AJAX-запросом
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
            }
        }
    }
}

// Удаление из watchlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_watchlist'])) {
    require_valid_csrf_token($_POST['_token'] ?? null);
    
    $watchlistId = (int)($_POST['watchlist_id'] ?? 0);
    if ($watchlistId > 0) {
        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$watchlistId, $userId]);
        header('Location: watchlist.php?msg=removed');
        exit;
    }
}

// Получаем список желаний
$pagination = get_pagination_params(20);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
$countStmt->execute([$userId]);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

$stmt = $pdo->prepare("
    SELECT w.*, um.poster_url, um.overview, um.release_date
    FROM watchlist w
    LEFT JOIN upcoming_movies um ON w.upcoming_movie_id = um.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $perPage, $offset]);
$watchlistItems = $stmt->fetchAll();

$paginationHtml = '';
if ($totalPages > 1) {
    $paginationHtml = render_pagination($page, $totalPages, 'watchlist.php');
}

require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="header-actions">
        <h2><?= htmlspecialchars(t('watchlist.title') ?? 'Список желаний') ?></h2>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
        <div class="toast-notification" style="background: #00b894; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
            ✅ <?= htmlspecialchars(t('watchlist.added') ?? 'Добавлено в список желаний') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($watchlistItems)): ?>
        <div class="empty-state">
            <p><?= htmlspecialchars(t('watchlist.empty') ?? 'Ваш список желаний пуст') ?></p>
            <a href="afisha.php" class="btn-submit" style="width: auto; text-decoration: none;">
                <?= htmlspecialchars(t('watchlist.browse_afisha') ?? 'Просмотреть афишу') ?>
            </a>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 15px; color: #636e72; font-size: 0.9rem;">
            <?= htmlspecialchars(t('watchlist.total') ?? 'Всего') ?>: <strong><?= $totalItems ?></strong>
        </div>

        <div class="media-grid">
            <?php foreach ($watchlistItems as $item): ?>
                <div class="media-card">
                    <div class="media-image">
                        <?php if (!empty($item['poster_url'])): ?>
                            <img src="<?= htmlspecialchars($item['poster_url']) ?>" alt="Poster">
                        <?php else: ?>
                            <div class="no-image">No poster</div>
                        <?php endif; ?>
                    </div>
                    <div class="media-content">
                        <span class="media-type type-movie">🎬</span>
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <?php if (!empty($item['release_date'])): ?>
                            <div class="media-meta"><?= htmlspecialchars(date('Y', strtotime($item['release_date']))) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['notes'])): ?>
                            <p class="media-review"><?= nl2br(htmlspecialchars($item['notes'])) ?></p>
                        <?php endif; ?>
                        <form method="POST" action="watchlist.php" style="margin-top: 10px;">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="watchlist_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" name="remove_from_watchlist" class="btn-submit" style="background: #e17055; width: 100%;">
                                <?= htmlspecialchars(t('watchlist.remove') ?? 'Удалить') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?= $paginationHtml ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>

