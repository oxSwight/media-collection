<?php
// src/login.php
require_once 'includes/init.php';

// Если пользователь уже залогинен, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = t('auth.email_required');
    } else {
        // ИСПРАВЛЕНИЕ: Добавили is_admin в список выбираемых полей
        $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Проверка пароля
        if ($user && password_verify($password, $user['password'])) {
            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Теперь это сработает, так как мы запросили это поле из базы
            $_SESSION['is_admin'] = $user['is_admin']; 
            
            header("Location: index.php");
            exit;
        } else {
            $error = t('auth.invalid_credentials');
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-form">
        <h2><?= htmlspecialchars(t('auth.login')) ?></h2>

        <?php if (isset($_GET['registered'])): ?>
            <div style="background: #55efc4; color: #00b894; padding: 10px; margin-bottom: 10px; border-radius: 5px; text-align: center;">
                <?= htmlspecialchars(t('auth.registered_success')) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <?= csrf_input(); ?>
            <div class="form-group">
                <label><?= htmlspecialchars(t('auth.email')) ?></label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label><?= htmlspecialchars(t('auth.password')) ?></label>
                <input type="password" name="password" required>
                
                <div style="text-align: right; margin-top: 5px;">
                    <a href="forgot_password.php" style="font-size: 0.85rem; color: #6c5ce7;"><?= htmlspecialchars(t('auth.forgot_password')) ?></a>
                </div>
            </div>

            <button type="submit" class="btn-submit"><?= htmlspecialchars(t('auth.login_btn')) ?></button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            <?= htmlspecialchars(t('auth.no_account')) ?> <a href="register.php" style="color: #6c5ce7; font-weight: bold;"><?= htmlspecialchars(t('auth.register_btn')) ?></a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>