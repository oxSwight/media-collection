<?php
// src/activity.php - лента активности (я + друзья)

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$myId = (int)$_SESSION['user_id'];

// Идентификаторы друзей
$friendsStmt = $pdo->prepare("
    SELECT CASE 
               WHEN requester_id = :me THEN receiver_id 
               ELSE requester_id 
           END as friend_id
    FROM friendships
    WHERE status = 'accepted' AND (requester_id = :me OR receiver_id = :me)
");
$friendsStmt->execute([':me' => $myId]);
$friendIds = $friendsStmt->fetchAll(PDO::FETCH_COLUMN);

$idsForFeed = $friendIds;
$idsForFeed[] = $myId;

if (empty($idsForFeed)) {
    $activities = [];
} else {
    // Лента последних 50 действий
    $inPlaceholders = implode(',', array_fill(0, count($idsForFeed), '?'));
    $sql = "
        SELECT a.*, u.username, u.avatar_path, u.id as user_id,
               m.title, m.type, m.rating, m.release_year, 
               m.author_director, m.image_path, m.id as media_id
        FROM activities a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN media_items m ON a.media_id = m.id
        WHERE a.user_id IN ($inPlaceholders)
        ORDER BY a.created_at DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($idsForFeed);
    $activities = $stmt->fetchAll();
    
    // Функция для форматирования относительного времени
    function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) return t('activity.just_now') ?? 'только что';
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' ' . ($mins === 1 ? (t('activity.minute_ago') ?? 'минуту назад') : (t('activity.minutes_ago') ?? 'минут назад'));
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ' . ($hours === 1 ? (t('activity.hour_ago') ?? 'час назад') : (t('activity.hours_ago') ?? 'часов назад'));
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' ' . ($days === 1 ? (t('activity.day_ago') ?? 'день назад') : (t('activity.days_ago') ?? 'дней назад'));
        }
        return date('d.m.Y', $timestamp);
    }
}

require_once 'includes/header.php';
?>

<div class="header-actions">
    <h2><?= htmlspecialchars(t('activity.title')) ?></h2>
    <a href="index.php" style="text-decoration: none; color: #636e72; font-weight: bold;">&larr; <?= htmlspecialchars(t('item.back')) ?></a>
</div>

<?php if (empty($activities)): ?>
    <div class="empty-state">
        <p><?= htmlspecialchars(t('activity.empty')) ?></p>
    </div>
<?php else: ?>
    <div class="activity-feed">
        <?php foreach ($activities as $act): ?>
            <div class="activity-card">
                <a href="user_collection.php?id=<?= (int)$act['user_id'] ?>" class="activity-user-link">
                    <div class="activity-avatar">
                        <?php if (!empty($act['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars($act['avatar_path']) ?>" alt="<?= htmlspecialchars($act['username']) ?>">
                        <?php else: ?>
                            <div class="no-avatar"><?= htmlspecialchars(strtoupper(substr($act['username'], 0, 1))) ?></div>
                        <?php endif; ?>
                    </div>
                </a>
                
                <div class="activity-content">
                    <div class="activity-header">
                        <a href="user_collection.php?id=<?= (int)$act['user_id'] ?>" class="activity-username">
                            <strong><?= htmlspecialchars($act['username']) ?></strong>
                        </a>
                        <?php if ($act['type'] === 'add_item' && $act['title']): ?>
                            <span class="activity-action-badge activity-action-add">
                                <span class="activity-action-icon">➕</span>
                                <span class="activity-action-text"><?= htmlspecialchars(t('activity.add_item')) ?></span>
                            </span>
                        <?php elseif ($act['type'] === 'like' && $act['title']): ?>
                            <span class="activity-action-badge activity-action-like">
                                <span class="activity-action-icon">❤</span>
                                <span class="activity-action-text"><?= htmlspecialchars(t('activity.like') ?? 'Polubienie') ?></span>
                            </span>
                        <?php else: ?>
                            <span class="activity-action-badge activity-action-default">
                                <span class="activity-action-text"><?= htmlspecialchars($act['type'] ?? 'activity') ?></span>
                            </span>
                        <?php endif; ?>
                        <span class="activity-time"><?= htmlspecialchars(timeAgo($act['created_at'])) ?></span>
                    </div>
                    
                    <?php if (in_array($act['type'], ['add_item','like'], true) && $act['title']): ?>
                        <div class="activity-media">
                            <div class="activity-media-action-indicator">
                                <div class="activity-action-pulse"></div>
                                <span class="activity-action-icon-large"><?= $act['type'] === 'like' ? '❤' : '➕' ?></span>
                            </div>
                            
                            <?php if (!empty($act['image_path'])): ?>
                                <?php 
                                $imageUrl = $act['image_path'];
                                if (strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, 'https') !== 0) {
                                    $imageUrl = '/' . ltrim($imageUrl, '/');
                                }
                                ?>
                                <a href="user_collection.php?id=<?= (int)$act['user_id'] ?>" class="activity-media-image">
                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($act['title']) ?>" onerror="this.style.display='none'">
                                </a>
                            <?php endif; ?>
                            
                            <div class="activity-media-info">
                                <div class="activity-media-header">
                                    <span class="activity-media-type">
                                        <?= $act['type'] === 'movie' ? '🎬' : '📚' ?>
                                        <?= $act['type'] === 'movie' ? htmlspecialchars(t('collection.movies')) : htmlspecialchars(t('collection.books')) ?>
                                    </span>
                                    <?php if (!empty($act['rating'])): ?>
                                        <span class="activity-rating">⭐ <?= htmlspecialchars((string)$act['rating']) ?>/10</span>
                                    <?php endif; ?>
                                </div>
                                
                                <h4 class="activity-media-title"><?= htmlspecialchars($act['title']) ?></h4>
                                
                                <?php if (!empty($act['author_director']) || !empty($act['release_year'])): ?>
                                    <div class="activity-media-meta">
                                        <?php if (!empty($act['author_director'])): ?>
                                            <span><?= htmlspecialchars($act['author_director']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($act['release_year'])): ?>
                                            <span class="activity-year">(<?= htmlspecialchars((string)$act['release_year']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>


