<?php
// src/admin.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? ORDER BY id DESC");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header-actions">
    <h2><?= htmlspecialchars(t('admin.title')) ?></h2>
    <a href="index.php" style="text-decoration: none; color: #636e72;">&larr; <?= htmlspecialchars(t('admin.back')) ?></a>
</div>

<div class="auth-form" style="max-width: 100%; padding: 20px;">
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div style="background: #ff7675; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?= htmlspecialchars(t('admin.user_deleted')) ?>
        </div>
    <?php endif; ?>

    <div style="overflow-x: auto;"> <!-- Прокрутка таблицы на мобильных -->
        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid #dfe6e9;">
                    <th style="padding: 10px;">ID</th>
                    <th style="padding: 10px;">Avatar</th>
                    <th style="padding: 10px;">Użytkownik</th>
                    <th style="padding: 10px;">Kod</th> <!-- НОВАЯ КОЛОНКА -->
                    <th style="padding: 10px;">Email</th>
                    <th style="padding: 10px;">Rola</th>
                    <th style="padding: 10px;">Akcja</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #f1f2f6;">
                        <td style="padding: 10px;">#<?= (int)$u['id'] ?></td>
                        <td style="padding: 10px;">
                            <?php if (!empty($u['avatar_path'])): ?>
                                <img src="<?= htmlspecialchars($u['avatar_path']) ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 30px; height: 30px; background: #dfe6e9; border-radius: 50%;"></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; font-weight: bold;"><?= htmlspecialchars($u['username']) ?></td>
                        
                        <!-- ВЫВОД КОДА -->
                        <td style="padding: 10px;">
                            <span style="background: #dfe6e9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold;">
                                <?= htmlspecialchars($u['friend_code'] ?? '-') ?>
                            </span>
                        </td>
                        
                        <td style="padding: 10px; color: #636e72;"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="padding: 10px;">
                            <?= $u['is_admin'] ? '<span style="color: purple; font-weight: bold;">ADMIN</span>' : 'Użytkownik' ?>
                        </td>
                        <td style="padding: 10px;">
                            <form method="POST" action="admin_delete_user.php" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('admin.delete_confirm'))) ?>');">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" style="background: #d63031; color: white; padding: 5px 10px; border: none; border-radius: 5px; font-size: 0.8rem; cursor: pointer;">
                                    <?= htmlspecialchars(t('admin.delete')) ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>