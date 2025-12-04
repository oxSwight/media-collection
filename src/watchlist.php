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
    $title = trim($_POST['title'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
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
            header('Location: watchlist.php?msg=added');
            exit;
        } catch (PDOException $e) {
            $error = 'Ошибка при добавлении в список желаний';
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

