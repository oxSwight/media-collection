<?php
// src/reset_password.php
require_once 'includes/init.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

$user = null;
if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Link wygasł lub jest nieprawidłowy.";
    }
} else {
    $error = "Brak tokenu.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Hasło musi mieć co najmniej 8 znaków.";
    } elseif ($password !== $confirm) {
        $error = "Hasła nie są identyczne.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->execute([$hashed, $user['id']]);

        $success = "Hasło zostało zmienione! Możesz się zalogować.";
    }
}

require_once 'includes/header.php';
?>

<div class="auth-form">
    <h2>Ustaw nowe hasło</h2>

    <?php if ($success): ?>
        <div style="background: #55efc4; color: #00b894; padding: 15px; text-align: center; border-radius: 5px;">
            <?= $success ?>
            <br><br>
            <a href="login.php" class="btn-submit" style="display: inline-block; width: auto; text-decoration: none;">Zaloguj się</a>
        </div>
    <?php elseif ($error): ?>
        <div class="error-msg"><?= $error ?></div>
        <p style="text-align: center;"><a href="forgot_password.php">Wyślij link ponownie</a></p>
    <?php else: ?>
        <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="POST">
            <?= csrf_input(); ?>
            <div class="form-group">
                <label>Nowe hasło</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Powtórz hasło</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">Zmień hasło</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>