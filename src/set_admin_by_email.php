<?php
// src/set_admin_by_email.php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Brak uprawnień.');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token($_POST['_token'] ?? null);

    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div style='color: red; margin-bottom: 20px;'>Nieprawidłowy adres email.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $update = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
            $update->execute([$user['id']]);
            
            $safeName = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            $message = "<div style='color: green; font-weight: bold; margin-bottom: 20px;'>
                ✅ Sukces! Użytkownik '{$safeName}' ({$safeEmail}) jest teraz ADMINEM.<br>
                Wyloguj się i zaloguj ponownie, aby zobaczyć zmiany.
            </div>";
        } else {
            $message = "<div style='color: red; margin-bottom: 20px;'>
                ❌ Nie znaleziono użytkownika o adresie: " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "
            </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Set Admin</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; padding-top: 50px; background: #f1f2f6; }
        .box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { background: #6c5ce7; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-size: 1rem; }
        button:hover { background: #a29bfe; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Kto ma być Adminem?</h2>
        <p>Wpisz email użytkownika, któremu chcesz dać pełną władzę.</p>
        
        <?= $message ?>

        <form method="POST">
            <?= csrf_input(); ?>
            <input type="email" name="email" placeholder="np. twoj@email.com" required>
            <button type="submit">Mianuj Adminem 👑</button>
        </form>
        
        <p style="margin-top: 20px;">
            <a href="index.php" style="color: #636e72;">Wróć na stronę główną</a>
        </p>
    </div>
</body>
</html>