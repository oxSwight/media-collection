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
        try {
            // Проверка, не занят ли email (оптимистичная проверка для UX)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = t('auth.email_taken');
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Генерируем уникальный Friend Code (6 символов)
                // Используем цикл для гарантии уникальности
                $maxAttempts = 10;
                $friendCode = null;
                for ($i = 0; $i < $maxAttempts; $i++) {
                    $candidate = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE friend_code = ?");
                    $checkStmt->execute([$candidate]);
                    if ($checkStmt->rowCount() === 0) {
                        $friendCode = $candidate;
                        break;
                    }
                }
                
                if ($friendCode === null) {
                    $error = t('common.error');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, friend_code) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $friendCode]);
                    
                    // Успешная регистрация
                    header("Location: login.php?registered=1");
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Логируем ошибку БД
            if (function_exists('logError')) {
                logError('Registration database error: ' . $e->getMessage(), [
                    'email' => $email,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            } else {
                error_log("Registration error: " . $e->getMessage());
            }
            
            // Обработка race condition: если email уже существует (код 23505 - unique violation в PostgreSQL)
            $errorCode = $e->getCode();
            $errorMessage = strtolower($e->getMessage());
            
            if ($errorCode == '23505' || $errorCode == 23505 || 
                strpos($errorMessage, 'unique') !== false || 
                strpos($errorMessage, 'duplicate') !== false ||
                strpos($errorMessage, 'violates unique constraint') !== false) {
                $error = t('auth.email_taken');
            } else {
                // Другая ошибка БД (например, таблица не существует)
                $error = 'Wystąpił błąd podczas rejestracji. Spróbuj ponownie później.';
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
            <button type="submit" class="btn-submit" id="registerBtn"><?= htmlspecialchars(t('auth.register_btn')) ?></button>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            <?= htmlspecialchars(t('auth.has_account')) ?> <a href="login.php" style="color: #6c5ce7; font-weight: bold;"><?= htmlspecialchars(t('auth.login_btn')) ?></a>
        </p>
    </div>
</div>

<script>
// Защита от двойного клика при регистрации
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="register.php"]');
    const submitBtn = document.getElementById('registerBtn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            // Отключаем кнопку и меняем текст
            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '<?= htmlspecialchars(addslashes(t("common.processing") ?? "Обработка...")) ?>';
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
            
            // Если форма не прошла валидацию, возвращаем кнопку в исходное состояние
            setTimeout(function() {
                if (!form.checkValidity()) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }, 100);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>