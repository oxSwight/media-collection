<?php
// src/forgot_password.php
require_once 'includes/init.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Nieprawidłowy format email.";
    } else {
        // Проверяем, есть ли такой пользователь
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // Генерируем токен (32 символа)
            $token = bin2hex(random_bytes(16));
            // Время жизни: сейчас + 1 час
            // В PostgreSQL синтаксис: NOW() + INTERVAL '1 hour'
            
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = NOW() + INTERVAL '1 hour' WHERE email = ?");
            $update->execute([$token, $email]);

            // Эмуляция отправки письма (на localhost выводим ссылку)
            $resetLink = "http://localhost:8080/reset_password.php?token=" . $token;
            
            $message = "
            <div style='background: #f1f2f6; padding: 15px; border-radius: 5px; border-left: 5px solid #6c5ce7;'>
                <strong>Symulacja emaila:</strong><br>
                Cześć! Otrzymaliśmy prośbę o reset hasła.<br>
                Kliknij tutaj: <a href='$resetLink'>$resetLink</a>
            </div>";
        } else {
            // В целях безопасности можно писать то же самое, но мы скажем правду для тестов
            $error = "Nie znaleziono użytkownika z tym adresem email.";
        }
    }
}
require_once 'includes/header.php';
?>

<div class="auth-form">
    <h2>Zapomniane hasło</h2>
    <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">
        Podaj swój adres email, a wyślemy Ci link do resetowania hasła.
    </p>

    <?php if ($error): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <?= $message ?>
    <?php else: ?>
        <form action="forgot_password.php" method="POST">
            <?= csrf_input(); ?>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="np. jan@gmail.com">
            </div>
            <button type="submit" class="btn-submit">Wyślij link resetujący</button>
        </form>
    <?php endif; ?>

    <p style="text-align: center; margin-top: 15px;">
        <a href="login.php" style="color: #636e72;">Wróć do logowania</a>
    </p>
</div>

<?php require_once 'includes/footer.php'; ?>