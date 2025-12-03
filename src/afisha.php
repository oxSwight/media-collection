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

// Параметры пагинации
$pagination = get_pagination_params(20);
$page    = $pagination['page'];
$perPage = $pagination['per_page'];
$offset  = $pagination['offset'];

// 1. Собираем список уже просмотренных фильмов (по названию)
$seenTitles = [];
$stmtSeen = $pdo->prepare("SELECT LOWER(title) FROM media_items WHERE user_id = ? AND type = 'movie'");
$stmtSeen->execute([$myId]);
foreach ($stmtSeen->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $seenTitles[$t] = true;
}

// 2. Строим базовый запрос по афише
$countSql = "SELECT COUNT(*) FROM upcoming_movies WHERE 1=1";
$dataSql  = "SELECT * FROM upcoming_movies WHERE 1=1";
$params   = [];

// Поиск по названию
if ($search !== '') {
    $countSql .= " AND (title ILIKE ? OR original_title ILIKE ?)";
    $dataSql  .= " AND (title ILIKE ? OR original_title ILIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
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

// Добавляем параметры пользователя
$paramsWithUser = $params;
$paramsWithUser[':uid'] = $myId;

// Считаем общее количество
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($paramsWithUser);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

// Получаем сами фильмы
$dataSql .= " ORDER BY release_date ASC NULLS LAST, popularity DESC NULLS LAST LIMIT :limit OFFSET :offset";
$paramsWithUser[':limit']  = $perPage;
$paramsWithUser[':offset'] = $offset;

$stmt = $pdo->prepare($dataSql);
foreach ($paramsWithUser as $k => $v) {
    if (is_int($k)) {
        $stmt->bindValue($k + 1, $v); // позиционные параметры
    } else {
        $stmt->bindValue($k, $v);
    }
}
$stmt->execute();
$movies = $stmt->fetchAll();

// 3. Строим профиль интересов (по жанрам) на основе личной коллекции
$favoriteGenres = [];
$genresStmt = $pdo->prepare("SELECT genres FROM media_items WHERE user_id = ? AND type = 'movie' AND genres IS NOT NULL AND genres <> ''");
$genresStmt->execute([$myId]);
foreach ($genresStmt->fetchAll(PDO::FETCH_COLUMN) as $gLine) {
    $parts = preg_split('/[,\s]+/', $gLine);
    foreach ($parts as $g) {
        $g = trim($g);
        if ($g === '') continue;
        $favoriteGenres[$g] = ($favoriteGenres[$g] ?? 0) + 1;
    }
}

arsort($favoriteGenres);
$topGenres = array_slice(array_keys($favoriteGenres), 0, 5);

// Фильтрация рекомендованных фильмов по жанрам (если есть предпочтения)
$recommendedMovies = $movies;
if (!empty($topGenres)) {
    $recommendedMovies = array_filter($movies, function ($m) use ($topGenres) {
        if (empty($m['genres'])) {
            return false;
        }
        $movieGenres = preg_split('/[,\s]+/', $m['genres']);
        foreach ($movieGenres as $mg) {
            if (in_array($mg, $topGenres, true)) {
                return true;
            }
        }
        return false;
    });
}

// Выбор набора для отображения
$moviesToShow = ($mode === 'all' || empty($topGenres)) ? $movies : $recommendedMovies;

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
        <h2><?= htmlspecialchars(t('afisha.title')) ?></h2>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="admin_afisha_refresh.php" class="btn-register" style="text-decoration: none;">
                <?= htmlspecialchars(t('afisha.refresh_btn')) ?>
            </a>
        <?php endif; ?>
    </div>

    <p style="color:#636e72; margin-bottom:20px;">
        <?= htmlspecialchars(t('afisha.description')) ?>
    </p>

    <form action="afisha.php" method="GET" class="search-form" style="margin-bottom: 20px; gap: 10px;">
        <input
            type="text"
            name="q"
            placeholder="<?= htmlspecialchars(t('afisha.search_placeholder')) ?>"
            value="<?= htmlspecialchars($search) ?>"
            class="search-input"
            style="flex:1; min-width: 180px;"
        >
        <select name="mode" class="lang-select" style="width:auto; min-width: 160px;">
            <option value="recommended" <?= $mode === 'recommended' ? 'selected' : '' ?>>
                <?= htmlspecialchars(t('afisha.mode_recommended')) ?>
            </option>
            <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>
                <?= htmlspecialchars(t('afisha.mode_all')) ?>
            </option>
        </select>
        <button type="submit" class="btn-submit" style="width:auto;">
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
                <div class="media-card">
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
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?= $paginationHtml ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>


