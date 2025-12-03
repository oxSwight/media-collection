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
        SELECT a.*, u.username, u.avatar_path, m.title, m.type
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
    <div class="community-grid">
        <?php foreach ($activities as $act): ?>
            <div class="user-card">
                <div class="user-card-avatar">
                    <?php if (!empty($act['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($act['avatar_path']) ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="no-avatar"><?= htmlspecialchars(strtoupper(substr($act['username'], 0, 1))) ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-card-info">
                    <h3><?= htmlspecialchars($act['username']) ?></h3>
                    <p style="font-size:0.9rem; color:#636e72;">
                        <?php if ($act['type'] === 'add_item' && $act['title']): ?>
                            <?= htmlspecialchars(t('activity.add_item')) ?>: <strong><?= htmlspecialchars($act['title']) ?></strong>
                        <?php else: ?>
                            <?= htmlspecialchars($act['type']) ?>
                        <?php endif; ?>
                    </p>
                    <span style="font-size:0.8rem; color:#b2bec3;">
                        <?= htmlspecialchars($act['created_at']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>


