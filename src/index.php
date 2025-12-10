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
        
        // Используем helper функцию вместо дублирования кода
        $result = getMediaItemsWithFilters(
            $pdo,
            $_SESSION['user_id'],
            $search,
            $filterType,
            $perPage,
            $offset
        );
        
        $items = $result['items'];
        $totalItems = $result['total'];
        $totalPages = $result['totalPages'];
        
        // Генерируем HTML пагинации
        if ($totalPages > 1) {
            $paginationHtml = render_pagination($page, $totalPages, 'index.php');
        }
        
    } catch (PDOException $e) {
        error_log("Database error in index.php: " . $e->getMessage());
        echo "<div class='error-msg'>" . htmlspecialchars(t('common.error') ?? 'Произошла ошибка. Пожалуйста, попробуйте позже.') . "</div>";
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
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="add_item.php" class="btn-register" style="text-decoration: none;"><?= htmlspecialchars(t('collection.add_new')) ?></a>
            </div>
        </div>

        <!-- Панель поиска -->
        <div class="search-bar-container">
            <form action="index.php" method="GET" class="search-form" id="searchForm" role="search">
                <input type="text" name="q" id="searchInput" autocomplete="off" placeholder="<?= htmlspecialchars(t('collection.search_placeholder')) ?>" value="<?= htmlspecialchars($search) ?>" class="search-input" aria-label="<?= htmlspecialchars(t('collection.search_placeholder')) ?>">
                
                <div class="filter-buttons" role="group" aria-label="<?= htmlspecialchars(t('collection.filter_by_type') ?? 'Фильтр по типу') ?>">
                    <button type="submit" name="type" value="" class="filter-btn <?= $filterType === '' ? 'active' : '' ?>" aria-label="<?= htmlspecialchars(t('collection.all')) ?>" aria-pressed="<?= $filterType === '' ? 'true' : 'false' ?>"><?= htmlspecialchars(t('collection.all')) ?></button>
                    <button type="submit" name="type" value="movie" class="filter-btn <?= $filterType === 'movie' ? 'active' : '' ?>" aria-label="<?= htmlspecialchars(t('collection.movies')) ?>" aria-pressed="<?= $filterType === 'movie' ? 'true' : 'false' ?>"><?= htmlspecialchars(t('collection.movies')) ?></button>
                    <button type="submit" name="type" value="book" class="filter-btn <?= $filterType === 'book' ? 'active' : '' ?>" aria-label="<?= htmlspecialchars(t('collection.books')) ?>" aria-pressed="<?= $filterType === 'book' ? 'true' : 'false' ?>"><?= htmlspecialchars(t('collection.books')) ?></button>
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
                                <?php 
                                $imagePath = $item['image_path'] ?? '';
                                // Проверяем, существует ли файл, если это локальный путь
                                $imageExists = false;
                                if (!empty($imagePath)) {
                                    if (strpos($imagePath, 'http') === 0) {
                                        // Это внешний URL - считаем, что он существует
                                        $imageExists = true;
                                    } else {
                                        // Это локальный путь - проверяем существование файла
                                        $fullPath = __DIR__ . '/' . ltrim($imagePath, '/');
                                        $imageExists = file_exists($fullPath);
                                    }
                                }
                                ?>
                                <?php if ($imageExists): ?>
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="Okładka" onerror="this.parentElement.innerHTML='<div class=\'no-image\'>Brak okładki</div>'">
                                <?php else: ?>
                                    <div class="no-image">Brak okładki</div>
                                <?php endif; ?>
                                <div class="media-rating" aria-label="<?= htmlspecialchars(t('item.rating') ?? 'Рейтинг') ?>: <?= htmlspecialchars((string)$item['rating']) ?>/10"><?= htmlspecialchars((string)$item['rating']) ?>/10</div>
                                <div class="media-actions" role="group" aria-label="<?= htmlspecialchars(t('item.actions') ?? 'Действия') ?>">
                                    <a href="edit_item.php?id=<?= $item['id'] ?>" class="btn-icon edit" aria-label="<?= htmlspecialchars(t('item.edit') ?? 'Редактировать') ?>" title="<?= htmlspecialchars(t('item.edit') ?? 'Редактировать') ?>">✎</a>
                                    <form action="delete_item.php" method="POST" style="display: inline;" onsubmit="return confirmDelete(event, '<?= htmlspecialchars(t('item.delete_confirm') ?? 'Usunąć?') ?>');">
                                        <?= csrf_input(); ?>
                                        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn-icon delete" style="border: none; background: none; cursor: pointer;" aria-label="<?= htmlspecialchars(t('item.delete') ?? 'Удалить') ?>" title="<?= htmlspecialchars(t('item.delete') ?? 'Удалить') ?>">✕</button>
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
// Сохранение и восстановление фильтров коллекции (поиск + тип)
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');

    // Восстанавливаем состояние
    try {
        const saved = JSON.parse(localStorage.getItem('collectionFilters') || '{}');
        if (saved.q && searchInput && !searchInput.value) {
            searchInput.value = saved.q;
        }
        if (saved.type && searchForm) {
            const btn = searchForm.querySelector(`button[name="type"][value="${saved.type}"]`);
            if (btn) btn.classList.add('active');
        }
    } catch (e) {}

    // При отправке формы сохраняем фильтры
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const formData = new FormData(searchForm);
            const data = {
                q: formData.get('q') || '',
                type: formData.get('type') || ''
            };
            try {
                localStorage.setItem('collectionFilters', JSON.stringify(data));
            } catch (e) {}
        });
    }
});
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
                .catch(err => {
                    console.error('Ошибка поиска:', err);
                    resultsArea.innerHTML = '<div class="error-msg" role="alert">' + (document.documentElement.lang === 'ru' ? 'Произошла ошибка при поиске. Пожалуйста, попробуйте еще раз.' : document.documentElement.lang === 'pl' ? 'Wystąpił błąd podczas wyszukiwania. Spróbuj ponownie.' : 'An error occurred during search. Please try again.') + '</div>';
                });
        }, 300));
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>