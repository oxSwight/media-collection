<?php
// src/update_profile_visibility_db.php
// Одноразовый скрипт: добавляем поле приватности профиля.

require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja prywatności profili...</h2>";

    // visibility: 'public' | 'friends' | 'private'
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS visibility VARCHAR(20) NOT NULL DEFAULT 'friends'
    ");

    echo "✅ Kolumna 'visibility' została dodana (domyślnie: 'friends').<br>";
    echo "<h3 style='color: green;'>Gotowe!</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>


