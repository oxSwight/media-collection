<?php
// src/update_admin_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja bazy pod Admina...</h2>";

    // Добавляем колонку is_admin (по умолчанию 0)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin INT DEFAULT 0");
    echo "Kolumna 'is_admin' dodana.<br>";

    // Назначаем ТЕБЯ админом (замени ID=1 на свой ID, если он другой)
    // Обычно первый зарегистрированный пользователь имеет ID 1
    $pdo->exec("UPDATE users SET is_admin = 1 WHERE id = 1");
    echo "Użytkownik z ID 1 jest teraz adminem.<br>";

    echo "<h3 style='color: green;'>Gotowe!</h3>";
    echo "<a href='index.php'>Wróć</a>";

} catch (PDOException $e) {
    echo "Błąd: " . $e->getMessage();
}
?>