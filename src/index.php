<?php
// src/index.php
require_once 'includes/init.php';
require_once 'includes/pagination.php';

$items = [];
$search = $_GET['q'] ?? '';
$filterType = $_GET['type'] ?? '';
$totalItems = 0;
$totalPages = 0;
$paginationHtml = '';

// Логика получения данных из базы
if (isset($_SESSION['user_id'])) {
    try {
        // Получаем параметры пагинации
        $pagination = get_pagination_params(20);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];
        $offset = $pagination['offset'];
        
        // Сначала считаем общее количество
        $countSql = "SELECT COUNT(*) FROM media_items WHERE user_id = ?";
        $countParams = [$_SESSION['user_id']];
        
        if (!empty($search)) {
            $countSql .= " AND (title ILIKE ? OR author_director ILIKE ?)";
            $countParams[] = '%' . $search . '%';
            $countParams[] = '%' . $search . '%';
        }
        
        if (!empty($filterType) && in_array($filterType, ['movie', 'book'])) {
            $countSql .= " AND type = ?";
            $countParams[] = $filterType;
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $totalItems = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        
        // Теперь получаем данные с пагинацией
        $sql = "SELECT * FROM media_items WHERE user_id = ?";
        $params = [$_SESSION['user_id']];

        // Если есть поисковый запрос
        if (!empty($search)) {
            $sql .= " AND (title ILIKE ? OR author_director ILIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        // Если есть фильтр по типу
        if (!empty($filterType) && in_array($filterType, ['movie', 'book'])) {
            $sql .= " AND type = ?";
            $params[] = $filterType;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        // Генерируем HTML пагинации
        if ($totalPages > 1) {
            $paginationHtml = render_pagination($page, $totalPages, 'index.php');
        }
        
    } catch (PDOException $e) {
        echo "<div class='error-msg'>Błąd: " . $e->getMessage() . "</div>";
    }
}
require_once 'includes/header.php';
?>

<div class="dashboard">
    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Вид для гостя -->
        <div style="text-align: center; padding: 50px 0;">
            <h1><?= htmlspecialchars(t('common.welcome')) ?></h1>
            <p><?= htmlspecialchars(t('common.welcome_desc')) ?></p>
            <p><a href="login.php" style="color: var(--primary); font-weight: bold;"><?= htmlspecialchars(t('common.login_to_see')) ?></a></p>
        </div>
    <?php else: ?>
        <!-- Заголовок и кнопка Добавить -->
        <div class="header-actions">
            <h2><?= htmlspecialchars(t('collection.title')) ?></h2>
            <a href="add_item.php" class="btn-register" style="text-decoration: none;"><?= htmlspecialchars(t('collection.add_new')) ?></a>
        </div>

        <!-- Панель поиска -->
        <div class="search-bar-container">
            <form action="index.php" method="GET" class="search-form" id="searchForm">
                <input type="text" name="q" id="searchInput" autocomplete="off" placeholder="<?= htmlspecialchars(t('collection.search_placeholder')) ?>" value="<?= htmlspecialchars($search) ?>" class="search-input">
                
                <div class="filter-buttons">
                    <button type="submit" name="type" value="" class="filter-btn <?= $filterType === '' ? 'active' : '' ?>"><?= htmlspecialchars(t('collection.all')) ?></button>
                    <button type="submit" name="type" value="movie" class="filter-btn <?= $filterType === 'movie' ? 'active' : '' ?>"><?= htmlspecialchars(t('collection.movies')) ?></button>
                    <button type="submit" name="type" value="book" class="filter-btn <?= $filterType === 'book' ? 'active' : '' ?>"><?= htmlspecialchars(t('collection.books')) ?></button>
                </div>
            </form>
        </div>

        <!-- Обертка results-area -->
        <div id="results-area">
            <?php if (count($items) === 0): ?>
                <div class="empty-state">
                    <p><?= htmlspecialchars(t('collection.no_items')) ?></p>
                    
                    <?php 
                    // Показываем кнопку "Показать все" ТОЛЬКО если включен поиск или фильтр.
                    // Если просто пусто - кнопку "Добавить" больше не показываем (убрали по просьбе).
                    if (!empty($search) || !empty($filterType)): 
                    ?>
                        <a href="index.php" class="btn-submit" style="display: inline-block; width: auto; text-decoration: none;"><?= htmlspecialchars(t('collection.show_all')) ?></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($totalItems > 0): ?>
                    <div style="margin-bottom: 15px; color: #636e72; font-size: 0.9rem;">
                        <?= htmlspecialchars(t('collection.found')) ?> <strong><?= $totalItems ?></strong> 
                        <?php
                        $itemsKey = 'collection.items';
                        if ($totalItems >= 5) $itemsKey = 'collection.items_5plus';
                        elseif ($totalItems >= 2) $itemsKey = 'collection.items_2_4';
                        echo htmlspecialchars(t($itemsKey));
                        ?>
                        <?php if ($totalPages > 1): ?>
                            | <?= htmlspecialchars(t('collection.page')) ?> <strong><?= $page ?></strong> <?= htmlspecialchars(t('collection.of')) ?> <strong><?= $totalPages ?></strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="media-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="media-card">
                            <div class="media-image">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="Okładka">
                                <?php else: ?>
                                    <div class="no-image">Brak okładki</div>
                                <?php endif; ?>
                                <div class="media-rating"><?= htmlspecialchars((string)$item['rating']) ?>/10</div>
                                <div class="media-actions">
                                    <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn-icon edit">✎</a>
                                    <form action="delete_item.php" method="POST" style="display: inline;" onsubmit="return confirm('Usunąć?');">
                                        <?= csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn-icon delete" style="border: none; background: none; cursor: pointer;">✕</button>
                                    </form>
                                </div>
                            </div>
                            <div class="media-content">
                                <span class="media-type type-<?= htmlspecialchars($item['type']) ?>"><?= $item['type'] === 'movie' ? htmlspecialchars(t('collection.movies')) : htmlspecialchars(t('collection.books')) ?></span>
                                <h3><?= htmlspecialchars($item['title']) ?></h3>
                                <div class="media-meta"><?= htmlspecialchars($item['author_director']) ?> (<?= htmlspecialchars((string)$item['release_year']) ?>)</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?= $paginationHtml ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Уведомление (Toast) при добавлении -->
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div id="toast" class="toast-notification">✅ <?= htmlspecialchars(t('item.added_success')) ?></div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toast');
            if(toast) { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }
        }, 4000);
        window.history.replaceState({}, document.title, window.location.pathname);
    </script>
<?php endif; ?>

<!-- JAVASCRIPT ДЛЯ ЖИВОГО ПОИСКА -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    const resultsArea = document.getElementById('results-area');
    
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const formData = new FormData(searchForm);
            // При поиске сбрасываем на первую страницу
            formData.delete('page');
            const params = new URLSearchParams(formData);
            window.history.replaceState({}, '', `${window.location.pathname}?${params}`);

            fetch(`${window.location.pathname}?${params}`)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newResults = doc.getElementById('results-area');
                    if (newResults) {
                        resultsArea.innerHTML = newResults.innerHTML;
                        // Прокручиваем вверх после обновления результатов
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .catch(err => console.error('Ошибка поиска:', err));
        }, 300));
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>