<?php
// src/update_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja bazy danych...</h2>";

    // Добавляем колонку для токена
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL");
    echo "Kolumna 'reset_token' sprawdzona/dodana.<br>";

    // Добавляем колонку для времени жизни токена
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_expires TIMESTAMP NULL");
    echo "Kolumna 'reset_expires' sprawdzona/dodana.<br>";

    echo "<h3 style='color: green;'>Sukces! Baza danych gotowa do resetowania haseł.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . $e->getMessage() . "</h3>";
}
?>