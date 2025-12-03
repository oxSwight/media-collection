<?php
// src/update_afisha_vote_average_db.php
// Добавляем поле vote_average (рейтинг TMDb) в таблицу upcoming_movies

require_once 'includes/db.php';

try {
    echo "<h2>Dodawanie kolumny vote_average do tabeli upcoming_movies...</h2>";

    // Добавляем колонку vote_average (рейтинг от TMDb, от 0 до 10)
    $pdo->exec("ALTER TABLE upcoming_movies ADD COLUMN IF NOT EXISTS vote_average NUMERIC(3,1) NULL");
    echo "✅ Kolumna 'vote_average' została dodana/sprawdzona.<br>";

    echo "<h3 style='color: green;'>Gotowe! Teraz można używać reйтинга TMDb w rekomendacjach.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>

