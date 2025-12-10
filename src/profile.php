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
    $visibility = $_POST['visibility'] ?? 'friends';
    $allowedVisibility = ['public', 'friends', 'private'];
    if (!in_array($visibility, $allowedVisibility, true)) {
        $visibility = 'friends';
    }

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
            $update = $pdo->prepare("UPDATE users SET username = ?, bio = ?, avatar_path = ?, visibility = ? WHERE id = ?");
            $update->execute([$newUsername, $bio, $avatarPath, $visibility, $userId]);

            $_SESSION['username'] = $newUsername;
            $success = t('profile.updated');
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Убедимся, что аватар не потерян (восстановим из backup при наличии)
if (!empty($user['avatar_path']) && !ensure_upload_exists($user['avatar_path'])) {
    $user['avatar_path'] = null;
}

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
    <div class="profile-avatar-wrapper" id="avatarWrapper" onclick="triggerAvatarSelect()">
        <?php if ($user['avatar_path']): ?>
            <img id="avatarPreview" src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar" class="profile-avatar-large">
        <?php else: ?>
            <div id="avatarPreview" class="profile-avatar-large" style="background: #dfe6e9; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
            </div>
        <?php endif; ?>
        <div class="avatar-overlay"><?= htmlspecialchars(t('profile.change_avatar') ?? 'Zmień zdjęcie') ?></div>
    </div>
    
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
        
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.username')) ?></label>
            <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>">
        </div>

        
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.avatar')) ?></label>
            <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;">
            <div class="avatar-editor">
                <div class="avatar-preview-frame">
                    <img id="avatarPreviewSmall" src="<?= htmlspecialchars($user['avatar_path'] ?: '') ?>" alt="" style="display: <?= $user['avatar_path'] ? 'block' : 'none' ?>;">
                    <?php if (!$user['avatar_path']): ?>
                        <div id="avatarPreviewPlaceholder" class="avatar-placeholder"><?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?></div>
                    <?php endif; ?>
                </div>
                <div class="avatar-controls">
                    <label style="display:block; font-size: 0.9rem;"><?= htmlspecialchars(t('profile.zoom') ?? 'Powiększenie') ?></label>
                    <input type="range" id="avatarZoom" min="1" max="2.5" step="0.01" value="1">
                    <label style="display:block; font-size: 0.9rem; margin-top:8px;"><?= htmlspecialchars(t('profile.rotate') ?? 'Obrót') ?></label>
                    <input type="range" id="avatarRotate" min="-15" max="15" step="0.5" value="0">
                </div>
            </div>
            <small style="color:#636e72; display:block; margin-top:6px;"><?= htmlspecialchars(t('profile.avatar_hint') ?? 'Kliknij na avatar lub ramkę, aby wybrać zdjęcie. Przeciągaj obraz w ramce, użyj suwaków powiększenia i obrotu do podglądu. Zapisywany jest oryginalny plik.') ?></small>
        </div>
        
        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.bio')) ?></label>
            <textarea name="bio" rows="4" placeholder="<?= htmlspecialchars(t('profile.bio_placeholder')) ?>"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label><?= htmlspecialchars(t('profile.visibility')) ?></label>
            <select name="visibility">
                <option value="public" <?= ($user['visibility'] ?? 'friends') === 'public' ? 'selected' : '' ?>>
                    <?= htmlspecialchars(t('profile.visibility_public')) ?>
                </option>
                <option value="friends" <?= ($user['visibility'] ?? 'friends') === 'friends' ? 'selected' : '' ?>>
                    <?= htmlspecialchars(t('profile.visibility_friends')) ?>
                </option>
                <option value="private" <?= ($user['visibility'] ?? 'friends') === 'private' ? 'selected' : '' ?>>
                    <?= htmlspecialchars(t('profile.visibility_private')) ?>
                </option>
            </select>
        </div>
        
        <button type="submit" class="btn-submit" style="width: auto;"><?= htmlspecialchars(t('item.save_changes')) ?></button>
    </form>
</div>

<script>
// Avatar select via click
function triggerAvatarSelect() {
    const input = document.getElementById('avatarInput');
    if (input) input.click();
}

(function setupAvatarEditor() {
    const input = document.getElementById('avatarInput');
    const previewMain = document.getElementById('avatarPreview');
    const previewSmall = document.getElementById('avatarPreviewSmall');
    const placeholder = document.getElementById('avatarPreviewPlaceholder');
    const zoom = document.getElementById('avatarZoom');
    const rotate = document.getElementById('avatarRotate');
    let offsetX = 0;
    let offsetY = 0;
    let startX = 0;
    let startY = 0;
    let dragging = false;

    function applyTransform() {
        const scale = parseFloat(zoom?.value || '1');
        const rot = parseFloat(rotate?.value || '0');
        const transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale}) rotate(${rot}deg)`;
        if (previewSmall) previewSmall.style.transform = transform;
        if (previewMain && previewMain.tagName === 'IMG') previewMain.style.transform = transform;
    }

    function setImage(src) {
        if (previewSmall) {
            previewSmall.src = src;
            previewSmall.style.display = 'block';
        }
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        if (previewMain && previewMain.tagName === 'IMG') {
            previewMain.src = src;
        }
        offsetX = 0; offsetY = 0;
        if (zoom) zoom.value = '1';
        if (rotate) rotate.value = '0';
        applyTransform();
    }

    if (input) {
        input.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                alert('Wybierz plik graficzny.');
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                setImage(reader.result);
            };
            reader.readAsDataURL(file);
        });
    }

    if (zoom) zoom.addEventListener('input', applyTransform);
    if (rotate) rotate.addEventListener('input', applyTransform);

    const frame = document.querySelector('.avatar-preview-frame');
    if (frame) {
        frame.addEventListener('mousedown', (e) => {
            dragging = true;
            startX = e.clientX - offsetX;
            startY = e.clientY - offsetY;
            e.preventDefault();
        });
        frame.addEventListener('touchstart', (e) => {
            dragging = true;
            const t = e.touches[0];
            startX = t.clientX - offsetX;
            startY = t.clientY - offsetY;
        }, {passive: true});
        window.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            offsetX = e.clientX - startX;
            offsetY = e.clientY - startY;
            applyTransform();
        });
        window.addEventListener('touchmove', (e) => {
            if (!dragging) return;
            const t = e.touches[0];
            offsetX = t.clientX - startX;
            offsetY = t.clientY - startY;
            applyTransform();
        }, {passive: true});
        window.addEventListener('mouseup', () => dragging = false);
        window.addEventListener('touchend', () => dragging = false);
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>