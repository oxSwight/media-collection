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

            // Формируем ссылку для сброса пароля
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = $protocol . '://' . $host . '/reset_password.php?token=' . $token;
            
            // Подготавливаем письмо
            $subject = t('email.reset_password_subject');
            $htmlBody = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { display: inline-block; padding: 12px 24px; background: #6c5ce7; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { margin-top: 30px; font-size: 0.9em; color: #666; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>' . htmlspecialchars(t('email.reset_password_title')) . '</h2>
                    <p>' . htmlspecialchars(t('email.reset_password_text')) . '</p>
                    <p style="text-align: center;">
                        <a href="' . htmlspecialchars($resetLink) . '" class="button">' . htmlspecialchars(t('email.reset_password_button')) . '</a>
                    </p>
                    <p style="font-size: 0.9em; color: #666;">
                        ' . htmlspecialchars(t('email.reset_password_link_text')) . '<br>
                        <a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a>
                    </p>
                    <p style="font-size: 0.85em; color: #999; margin-top: 30px;">
                        ' . htmlspecialchars(t('email.reset_password_expires')) . '
                    </p>
                    <div class="footer">
                        <p>' . htmlspecialchars(t('email.reset_password_footer')) . '</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $textBody = t('email.reset_password_title') . "\n\n" .
                        t('email.reset_password_text') . "\n\n" .
                        t('email.reset_password_link_text') . ": " . $resetLink . "\n\n" .
                        t('email.reset_password_expires') . "\n\n" .
                        t('email.reset_password_footer');
            
            // Отправляем письмо
            $result = send_email($email, $subject, $htmlBody, $textBody);
            
            if ($result['success']) {
                $message = '<div style="background: #55efc4; color: #00b894; padding: 15px; border-radius: 5px; text-align: center;">
                    <strong>✅ ' . htmlspecialchars(t('email.reset_password_sent')) . '</strong><br>
                    ' . htmlspecialchars(t('email.reset_password_check')) . '
                </div>';
            } else {
                // Если отправка не удалась, показываем ссылку напрямую (для разработки)
                $error = t('email.reset_password_error') . ': ' . htmlspecialchars($result['error'] ?? 'Unknown error');
                
                // В режиме разработки можно показать ссылку
                if (getenv('APP_ENV') === 'development' || strpos($host, 'localhost') !== false) {
                    $message = '
                    <div style="background: #ffeaa7; padding: 15px; border-radius: 5px; border-left: 5px solid #fdcb6e;">
                        <strong>⚠️ Tryb deweloperski:</strong><br>
                        Email nie został wysłany, ale oto link do resetu:<br>
                        <a href="' . htmlspecialchars($resetLink) . '" style="word-break: break-all;">' . htmlspecialchars($resetLink) . '</a>
                    </div>';
                }
            }
        } else {
            // В целях безопасности всегда показываем одно и то же сообщение
            $message = '<div style="background: #55efc4; color: #00b894; padding: 15px; border-radius: 5px; text-align: center;">
                <strong>✅ ' . htmlspecialchars(t('email.reset_password_sent')) . '</strong><br>
                ' . htmlspecialchars(t('email.reset_password_check')) . '
            </div>';
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