<?php
// src/register.php
require_once 'includes/init.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = t('auth.all_fields_required');
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('auth.invalid_email');
    }
    else {
        // Проверка, не занят ли email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = t('auth.email_taken');
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Генерируем уникальный Friend Code (6 символов)
            $friendCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, friend_code) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashedPassword, $friendCode])) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = t('common.error');
            }
        }
    }
}

require_once 'includes/header.php';
?>

<!-- Используем auth-wrapper для центрирования на экране -->
<div class="auth-wrapper">
    <div class="auth-form">
        <h2><?= htmlspecialchars(t('auth.register')) ?></h2>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form action="register.php" method="POST">
            <?= csrf_input(); ?>
            <div class="form-group">
                <label><?= htmlspecialchars(t('auth.username')) ?></label>
                <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(t('auth.email')) ?></label>
                <input type="email" name="email" required placeholder="np. kowalski@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars(t('auth.password')) ?></label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-submit"><?= htmlspecialchars(t('auth.register_btn')) ?></button>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            <?= htmlspecialchars(t('auth.has_account')) ?> <a href="login.php" style="color: #6c5ce7; font-weight: bold;"><?= htmlspecialchars(t('auth.login_btn')) ?></a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>