<?php
// src/friends.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$myId = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'add_friend') {
        $code = strtoupper(trim($_POST['add_friend_code'] ?? ''));

        $stmt = $pdo->prepare("SELECT id FROM users WHERE friend_code = ?");
        $stmt->execute([$code]);
        $friend = $stmt->fetch();

        if (!$friend) {
            $error = t('friends.user_not_found') . " " . htmlspecialchars($code);
        } elseif ((int)$friend['id'] === $myId) {
            $error = t('friends.cannot_add_self');
        } else {
            $check = $pdo->prepare("SELECT status FROM friendships WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
            $check->execute([$myId, $friend['id'], $friend['id'], $myId]);
            $exists = $check->fetch();

            if ($exists) {
                $error = ($exists['status'] === 'accepted') ? t('friends.already_friends') : t('friends.invite_exists');
            } else {
                $ins = $pdo->prepare("INSERT INTO friendships (requester_id, receiver_id) VALUES (?, ?)");
                $ins->execute([$myId, $friend['id']]);
                $message = t('friends.invite_sent');
            }
        }
    } elseif ($action === 'accept_friend' || $action === 'reject_friend') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId > 0) {
            if ($action === 'accept_friend') {
                $upd = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
                $upd->execute([$requestId, $myId]);
                if ($upd->rowCount()) {
                    $message = t('friends.invite_accepted');
                }
            } else {
                $del = $pdo->prepare("DELETE FROM friendships WHERE id = ? AND receiver_id = ?");
                $del->execute([$requestId, $myId]);
                if ($del->rowCount()) {
                    $message = t('friends.invite_rejected');
                }
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT friend_code FROM users WHERE id = ?");
$stmt->execute([$myId]);
$myCode = $stmt->fetchColumn();

$pendingStmt = $pdo->prepare("
    SELECT f.id as friendship_id, u.username, u.avatar_path 
    FROM friendships f
    JOIN users u ON f.requester_id = u.id
    WHERE f.receiver_id = ? AND f.status = 'pending'
");
$pendingStmt->execute([$myId]);
$pendingRequests = $pendingStmt->fetchAll();

$friendsStmt = $pdo->prepare("
    SELECT u.id, u.username, u.avatar_path, u.bio 
    FROM friendships f
    JOIN users u ON (f.requester_id = u.id OR f.receiver_id = u.id)
    WHERE (f.requester_id = ? OR f.receiver_id = ?) 
    AND f.status = 'accepted' 
    AND u.id != ?
");
$friendsStmt->execute([$myId, $myId, $myId]);
$friends = $friendsStmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="container" style="max-width: 800px; margin-top: 30px;">
    
    <!-- Блок: Мой код -->
    <div class="auth-form" style="max-width: 100%; text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); color: white;">
        <h3 style="margin: 0; color: white;"><?= htmlspecialchars(t('friends.your_code')) ?></h3>
        <div style="font-size: 3rem; font-weight: 800; letter-spacing: 5px; margin: 10px 0; text-shadow: 0 2px 10px rgba(0,0,0,0.2);">
            <?= htmlspecialchars($myCode) ?>
        </div>
        <p style="opacity: 0.9; margin: 0;"><?= htmlspecialchars(t('friends.code_description')) ?></p>
    </div>

    <!-- Уведомления -->
    <?php if ($message): ?>
        <div style="background: #00b894; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Блок: Добавить друга -->
    <div class="search-bar-container" style="margin-bottom: 40px;">
        <h3 style="margin-top: 0;"><?= htmlspecialchars(t('friends.add_friend')) ?></h3>
        <form method="POST" class="search-form" style="gap: 10px;">
            <?= csrf_input(); ?>
            <input type="hidden" name="action" value="add_friend">
            <input type="text" name="add_friend_code" placeholder="<?= htmlspecialchars(t('friends.code_placeholder')) ?>" class="search-input" style="text-transform: uppercase;" required>
            <button type="submit" class="btn-register"><?= htmlspecialchars(t('friends.send_invite')) ?></button>
        </form>
    </div>

    <!-- Блок: Заявки -->
    <?php if (count($pendingRequests) > 0): ?>
        <h3 style="margin-bottom: 15px;"><?= htmlspecialchars(t('friends.pending')) ?></h3>
        <div class="community-grid" style="margin-bottom: 40px;">
            <?php foreach ($pendingRequests as $req): ?>
                <div class="user-card" style="border: 2px solid #fdcb6e;">
                    <div class="user-card-avatar">
                        <?php if ($req['avatar_path']): ?>
                            <img src="<?= htmlspecialchars($req['avatar_path']) ?>">
                        <?php else: ?>
                            <div class="no-avatar"><?= htmlspecialchars(strtoupper(substr($req['username'], 0, 1))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="user-card-info">
                        <h3><?= htmlspecialchars($req['username']) ?></h3>
                        <p style="font-size: 0.8rem; color: #636e72;">Chce być Twoim znajomym</p>
                        <form method="POST" style="display: flex; gap: 10px; margin-top: 5px;">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="request_id" value="<?= (int)$req['friendship_id'] ?>">
                            <button type="submit" name="action" value="accept_friend" class="btn-register" style="padding: 5px 10px; font-size: 0.8rem; background: #00b894; border: none; cursor: pointer;">
                                <?= htmlspecialchars(t('friends.accept')) ?>
                            </button>
                            <button type="submit" name="action" value="reject_friend" class="btn-register" style="padding: 5px 10px; font-size: 0.8rem; background: #fab1a0; color: #d63031 !important; border: none; cursor: pointer;">
                                <?= htmlspecialchars(t('friends.reject')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Блок: Мои Друзья -->
    <h3 style="margin-bottom: 15px;"><?= htmlspecialchars(t('friends.your_friends')) ?> (<?= count($friends) ?>)</h3>
    
    <?php if (count($friends) === 0): ?>
        <div class="empty-state">
            <p><?= htmlspecialchars(t('friends.no_friends')) ?></p>
        </div>
    <?php else: ?>
        <div class="community-grid">
            <?php foreach ($friends as $friend): ?>
                <a href="user_collection.php?id=<?= (int)$friend['id'] ?>" class="user-card">
                    <div class="user-card-avatar">
                        <?php if ($friend['avatar_path']): ?>
                            <img src="<?= htmlspecialchars($friend['avatar_path']) ?>">
                        <?php else: ?>
                            <div class="no-avatar"><?= htmlspecialchars(strtoupper(substr($friend['username'], 0, 1))) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="user-card-info">
                        <h3><?= htmlspecialchars($friend['username']) ?></h3>
                        <p style="color: #636e72; font-size: 0.8rem;">
                            <?= $friend['bio'] ? htmlspecialchars(mb_strimwidth($friend['bio'], 0, 30, "...")) : htmlspecialchars(t('community.friend')) ?>
                        </p>
                        <span class="btn-visit"><?= htmlspecialchars(t('community.view_collection')) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
// Обновление badge после принятия/отклонения запросов
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(form => {
        const acceptBtn = form.querySelector('button[value="accept_friend"]');
        const rejectBtn = form.querySelector('button[value="reject_friend"]');
        
        if (acceptBtn || rejectBtn) {
            form.addEventListener('submit', function() {
                // После отправки формы обновляем badge через небольшую задержку
                setTimeout(function() {
                    updateFriendsBadge();
                }, 500);
            });
        }
    });
});

// Функция для обновления badge через AJAX
function updateFriendsBadge() {
    fetch('api_friends_badge.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('friends-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    // Создаем badge, если его нет
                    const friendsLink = document.querySelector('a[href="friends.php"]');
                    if (friendsLink) {
                        const newBadge = document.createElement('span');
                        newBadge.id = 'friends-badge';
                        newBadge.className = 'nav-badge';
                        newBadge.textContent = data.count;
                        friendsLink.appendChild(newBadge);
                    }
                }
            } else {
                // Удаляем badge, если запросов нет
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => {
            console.error('Error updating friends badge:', error);
        });
}
</script>

<?php require_once 'includes/footer.php'; ?>