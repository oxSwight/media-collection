<?php
// src/update_media_genres_db.php
// Скрипт одноразового обновления БД: добавляем поле жанров к медиа-элементам.

require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja bazy dla gatunków filmów...</h2>";

    $pdo->exec("ALTER TABLE media_items ADD COLUMN IF NOT EXISTS genres VARCHAR(255) NULL");
    echo "✅ Kolumna 'genres' została sprawdzona/dodana w tabeli 'media_items'.<br>";

    echo "<h3 style='color: green;'>Gotowe! Teraz możesz zapisywać gatunki filmów.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>


