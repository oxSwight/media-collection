<?php
// src/community.php
require_once 'includes/init.php';

$myId = $_SESSION['user_id'] ?? null;
$isAdmin = !empty($_SESSION['is_admin']);

if (!$myId) {
    header("Location: login.php");
    exit;
}
$search = $_GET['q'] ?? '';
$users = [];
$showResults = false; // Флаг: показывать ли результаты

// ЛОГИКА:
if ($isAdmin) {
    // 1. АДМИН видит всех (кроме себя), даже если не ищет
    // Если введен поиск - фильтруем, если нет - показываем всех
    $sql = "SELECT id, username, avatar_path, bio, is_admin FROM users WHERE id != ?";
    $params = [$myId];

    if (!empty($search)) {
        $sql .= " AND username ILIKE ?";
        $params[] = '%' . $search . '%';
    }
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    $showResults = true; // Админ всегда видит список

} else {
    // 2. ОБЫЧНЫЙ ЮЗЕР видит только тех, кого нашел
    if (!empty($search)) {
        // Поиск по нику (исключая себя и админов, если хотим скрыть админов)
        $stmt = $pdo->prepare("
            SELECT id, username, avatar_path, bio 
            FROM users 
            WHERE id != ? AND is_admin = 0 AND username ILIKE ?
            ORDER BY username ASC
        ");
        $stmt->execute([$myId, '%' . $search . '%']);
        $users = $stmt->fetchAll();
        $showResults = true;
    }
}
require_once 'includes/header.php';
?>

<div class="header-actions">
    <h2>
        <?php if ($isAdmin): ?>
            <?= htmlspecialchars(t('community.title_admin')) ?>
        <?php else: ?>
            <?= htmlspecialchars(t('community.title_user')) ?>
        <?php endif; ?>
    </h2>
    <a href="index.php" style="text-decoration: none; color: #636e72; font-weight: bold;">&larr; <?= htmlspecialchars(t('item.back')) ?></a>
</div>

<!-- ПАНЕЛЬ ПОИСКА (Видна всем) -->
<div class="search-bar-container">
    <form action="community.php" method="GET" class="search-form">
        <input type="text" name="q" 
               placeholder="<?= htmlspecialchars(t('community.search_placeholder')) ?>" 
               value="<?= htmlspecialchars($search) ?>" 
               class="search-input"
               style="border-radius: 30px;">
        
        <button type="submit" class="btn-register" style="border-radius: 30px; margin-left: 10px;">
            <?= htmlspecialchars(t('community.search_btn')) ?>
        </button>
        
        <?php if (!empty($search)): ?>
            <a href="community.php" style="margin-left: 10px; color: #e17055; text-decoration: none; font-weight: bold;"><?= htmlspecialchars(t('community.clear')) ?></a>
        <?php endif; ?>
    </form>
</div>

<!-- СПИСОК ПОЛЬЗОВАТЕЛЕЙ -->
<div class="community-grid">
    <?php if (!$showResults): ?>
        <!-- Состояние "До поиска" (для обычных юзеров) -->
        <div class="empty-state" style="grid-column: 1/-1;">
            <p style="font-size: 1.2rem; margin-bottom: 10px;"><?= htmlspecialchars(t('community.no_search')) ?></p>
            <p style="color: #636e72;"><?= htmlspecialchars(t('community.no_search_desc')) ?></p>
        </div>

    <?php elseif (count($users) === 0): ?>
        <!-- Состояние "Ничего не найдено" -->
        <div class="empty-state" style="grid-column: 1/-1;">
            <p><?= htmlspecialchars(t('community.not_found')) ?> "<strong><?= htmlspecialchars($search) ?></strong>".</p>
        </div>

    <?php else: ?>
        <!-- Результаты -->
        <?php foreach ($users as $u): ?>
            <a href="user_collection.php?id=<?= (int)$u['id'] ?>" class="user-card">
                <div class="user-card-avatar">
                    <?php if (!empty($u['avatar_path'])): ?>
                        <img src="<?= htmlspecialchars($u['avatar_path']) ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="no-avatar"><?= htmlspecialchars(strtoupper(substr($u['username'], 0, 1))) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="user-card-info">
                    <h3 style="display: flex; align-items: center; gap: 5px;">
                        <?= htmlspecialchars($u['username']) ?>
                        <?php if (isset($u['is_admin']) && $u['is_admin']): ?>
                            <span style="font-size: 0.7rem; background: #6c5ce7; color: white; padding: 2px 6px; border-radius: 4px;">ADMIN</span>
                        <?php endif; ?>
                    </h3>
                    
                    <?php if (!empty($u['bio'])): ?>
                        <p><?= htmlspecialchars(mb_strimwidth($u['bio'], 0, 40, "...")) ?></p>
                    <?php else: ?>
                        <p style="color: #b2bec3; font-style: italic;"><?= htmlspecialchars(t('community.no_bio')) ?></p>
                    <?php endif; ?>
                    
                    <span class="btn-visit"><?= htmlspecialchars(t('community.visit_profile')) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>