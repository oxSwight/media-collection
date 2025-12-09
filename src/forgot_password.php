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
        // Sprawdzamy, czy istnieje taki użytkownik
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // Generujemy token (32 znaki)
            $token = bin2hex(random_bytes(16));
            // Czas życia: teraz + 1 godzina
            // W PostgreSQL składnia: NOW() + INTERVAL '1 hour'
            
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = NOW() + INTERVAL '1 hour' WHERE email = ?");
            $update->execute([$token, $email]);

            // Tworzymy link do resetu hasła
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetLink = $protocol . '://' . $host . '/reset_password.php?token=' . $token;
            
            // Przygotowujemy wiadomość
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
            
            // Wysyłamy wiadomość
            $result = send_email($email, $subject, $htmlBody, $textBody);
            
            // Logujemy wynik do debugowania
            error_log("Password reset email send attempt to: $email, success: " . ($result['success'] ? 'yes' : 'no') . ", error: " . ($result['error'] ?? 'none'));
            
            if ($result['success']) {
                $message = '<div style="background: #55efc4; color: #00b894; padding: 15px; border-radius: 5px; text-align: center;">
                    <strong>✅ ' . htmlspecialchars(t('email.reset_password_sent')) . '</strong><br>
                    ' . htmlspecialchars(t('email.reset_password_check')) . '
                </div>';
            } else {
                // Jeśli wysyłka nie powiodła się
                $errorMsg = $result['error'] ?? 'Unknown error';
                
                // Sprawdzamy, czy to błąd weryfikacji SendGrid?
                $isSendGridVerificationError = (stripos($errorMsg, 'verified Sender Identity') !== false || 
                                                stripos($errorMsg, 'HTTP 403') !== false ||
                                                stripos($errorMsg, 'sender authentication') !== false ||
                                                stripos($errorMsg, 'unverified') !== false);
                
                // Sprawdzamy, czy jest klucz API
                $hasSendGridKey = !empty(getenv('SENDGRID_API_KEY'));
                $hasSMTPConfig = !empty(getenv('SMTP_HOST')) && !empty(getenv('SMTP_USER')) && !empty(getenv('SMTP_PASS'));
                
                // W trybie deweloperskim pokazujemy link bezpośrednio
                if (getenv('APP_ENV') === 'development' || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                    $message = '
                    <div style="background: #ffeaa7; padding: 15px; border-radius: 5px; border-left: 5px solid #fdcb6e;">
                        <strong>⚠️ Tryb deweloperski (localhost):</strong><br>
                        Email nie został wysłany (wymaga konfiguracji SendGrid/SMTP), ale oto link do resetu hasła:<br><br>
                        <a href="' . htmlspecialchars($resetLink) . '" style="word-break: break-all; color: #6c5ce7; font-weight: bold;">' . htmlspecialchars($resetLink) . '</a><br><br>
                        <small style="color: #636e72;">Na produkcji (Render) po skonfigurowaniu SendGrid API email będzie wysyłany automatycznie.</small>
                    </div>';
                } elseif ($isSendGridVerificationError) {
                    // Specjalna wiadomość dla błędu weryfikacji SendGrid
                    $error = '<div style="background: #fab1a0; color: #d63031; padding: 15px; border-radius: 5px; border-left: 5px solid #e17055;">
                        <strong>⚠️ SendGrid wymaga weryfikacji nadawcy:</strong><br>
                        Adres email nadawcy (' . htmlspecialchars(getenv('SENDGRID_FROM_EMAIL') ?: 'noreply@medialib.app') . ') nie jest zweryfikowany w SendGrid.<br><br>
                        <strong>Jak naprawić:</strong><br>
                        1. Zaloguj się do SendGrid<br>
                        2. Przejdź do Settings → Sender Authentication<br>
                        3. Kliknij "Verify a Single Sender" lub użyj domeny<br>
                        4. Podaj email, który chcesz używać jako nadawca<br>
                        5. Potwierdź email (otrzymasz wiadomość weryfikacyjną)<br><br>
                        <small>Po weryfikacji email będzie działał automatycznie.</small>
                    </div>';
                } elseif (!$hasSendGridKey && !$hasSMTPConfig) {
                    // Brak konfiguracji email
                    $error = '<div style="background: #fab1a0; color: #d63031; padding: 15px; border-radius: 5px; border-left: 5px solid #e17055;">
                        <strong>⚠️ Email nie jest skonfigurowany:</strong><br>
                        Brak konfiguracji SendGrid API Key lub SMTP. Skontaktuj się z administratorem.<br><br>
                        <small>W trybie deweloperskim link resetu hasła jest wyświetlany bezpośrednio.</small>
                    </div>';
                } else {
                    // Inne błędy - pokazujemy szczegółową wiadomość dla admina, ogólną dla użytkownika
                    $error = '<div style="background: #fab1a0; color: #d63031; padding: 15px; border-radius: 5px; border-left: 5px solid #e17055;">
                        <strong>⚠️ ' . htmlspecialchars(t('email.reset_password_error')) . '</strong><br>
                        ' . htmlspecialchars(substr($errorMsg, 0, 200)) . '<br><br>
                        <small>Skontaktuj się z administratorem lub spróbuj ponownie później.</small>
                    </div>';
                }
            }
        } else {
            // Ze względów bezpieczeństwa zawsze pokazujemy tę samą wiadomość
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