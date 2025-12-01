<?php
// src/profile.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $bio = trim($_POST['bio']);
    $newUsername = trim($_POST['username']);

    if (empty($newUsername)) {
        $error = t('profile.username_empty');
    } else {
        $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $avatarPath = $stmt->fetchColumn();

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$newPath, $uploadError] = handle_image_upload($_FILES['avatar'], 'avatars', 1_500_000);
            if ($uploadError) {
                $error = $uploadError;
            } else {
                safe_delete_upload($avatarPath);
                $avatarPath = $newPath;
            }
        }

        if (!$error) {
            $update = $pdo->prepare("UPDATE users SET username = ?, bio = ?, avatar_path = ? WHERE id = ?");
            $update->execute([$newUsername, $bio, $avatarPath, $userId]);

            $_SESSION['username'] = $newUsername;
            $success = t('profile.updated');
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type='movie' THEN 1 ELSE 0 END) as movies,
        SUM(CASE WHEN type='book' THEN 1 ELSE 0 END) as books
    FROM media_items WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

require_once 'includes/header.php';
?>

<div class="profile-header">
    <?php if ($user['avatar_path']): ?>
        <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar" class="profile-avatar-large">
    <?php else: ?>
        <div class="profile-avatar-large" style="background: #dfe6e9; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
            <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-info">
        <h1><?= htmlspecialchars($user['username']) ?></h1>
        
        <div style="background: #e17055; color: white; display: inline-block; padding: 2px 8px; border-radius: 5px; font-weight: bold; font-size: 0.8rem; margin-bottom: 10px;">
            <?= htmlspecialchars(t('profile.friend_code')) ?> <?= htmlspecialchars($user['friend_code'] ?? '---') ?>
        </div>
        
        <p style="color: #636e72;"><?= htmlspecialchars($user['email']) ?></p>
        
        <div class="profile-stats">
            <span class="stat-item"><?= htmlspecialchars(t('profile.stats_movies')) ?> <?= $stats['movies'] ?? 0 ?></span>
            <span class="stat-item"><?= htmlspecialchars(t('profile.stats_books')) ?> <?= $stats['books'] ?? 0 ?></span>
            <span class="stat-item"><?= htmlspecialchars(t('profile.stats_all')) ?> <?= $stats['total'] ?? 0 ?></span>
        </div>
    </div>
</div>

<div class="auth-form" style="max-width: 100%; box-sizing: border-box;">
    <h3><?= htmlspecialchars(t('profile.edit')) ?></h3>
    <?php if ($success): ?><div style="color: green; margin-bottom: 10px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form action="profile.php" method="POST" enctype="multipart/form-data">
        <?= csrf_input(); ?>
        
        <!-- НОВОЕ ПОЛЕ: Смена имени -->
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.username')) ?></label>
            <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>">
        </div>

        
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.avatar')) ?></label>
            <input type="file" name="avatar" accept="image/*">
        </div>
        
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.bio')) ?></label>
            <textarea name="bio" rows="4" placeholder="<?= htmlspecialchars(t('profile.bio_placeholder')) ?>"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>
        
        <button type="submit" class="btn-submit" style="width: auto;"><?= htmlspecialchars(t('item.save_changes')) ?></button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>