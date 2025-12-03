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

// 2. Строим базовый запрос по афише (ТОЛЬКО именованные параметры, без смешивания)
$countSql = "SELECT COUNT(*) FROM upcoming_movies WHERE 1=1";
$dataSql  = "SELECT * FROM upcoming_movies WHERE 1=1";

$countParams = [':uid' => $myId];
$dataParams  = [':uid' => $myId];

// Поиск по названию и описанию
if ($search !== '') {
    $countSql .= " AND (title ILIKE :q_title OR original_title ILIKE :q_orig OR overview ILIKE :q_overview)";
    $dataSql  .= " AND (title ILIKE :q_title OR original_title ILIKE :q_orig OR overview ILIKE :q_overview)";
    $like = '%' . $search . '%';

    $countParams[':q_title']    = $like;
    $countParams[':q_orig']     = $like;
    $countParams[':q_overview'] = $like;

    $dataParams[':q_title']    = $like;
    $dataParams[':q_orig']     = $like;
    $dataParams[':q_overview'] = $like;
}

// Если пользователь ввёл год (4 цифры), добавляем фильтр по году выхода
if (preg_match('/\b(19|20)\d{2}\b/', $search, $m)) {
    $year = (int)$m[0];
    $countSql .= " AND EXTRACT(YEAR FROM release_date) = :year";
    $dataSql  .= " AND EXTRACT(YEAR FROM release_date) = :year";
    $countParams[':year'] = $year;
    $dataParams[':year']  = $year;
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

// Получаем сами фильмы — сперва более новые релизы
$dataSql .= " ORDER BY release_date DESC NULLS LAST, popularity DESC NULLS LAST LIMIT :limit OFFSET :offset";
$dataParams[':limit']  = $perPage;
$dataParams[':offset'] = $offset;

$stmt = $pdo->prepare($dataSql);
$stmt->execute($dataParams);
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
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <a href="admin_afisha_refresh.php" class="btn-register" style="text-decoration: none;">
                <?= htmlspecialchars(t('afisha.refresh_btn')) ?>
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

                        <form method="POST" action="afisha_add.php" onsubmit="event.stopPropagation();" style="margin-top: 10px;">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="upcoming_id" value="<?= (int)$movie['id'] ?>">
                            <button type="submit" class="btn-submit" style="width:100%; margin-top:5px;">
                                <?= htmlspecialchars(t('afisha.add_to_collection')) ?>
                            </button>
                        </form>
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


