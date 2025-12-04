<?php
// src/update_watchlist_db.php
// Создаем таблицу для списка желаний (watchlist)

require_once 'includes/db.php';

try {
    echo "<h2>Tworzenie tabeli watchlist...</h2>";

    $sql = "
        CREATE TABLE IF NOT EXISTS watchlist (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            upcoming_movie_id INT REFERENCES upcoming_movies(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(10) NOT NULL CHECK (type IN ('movie', 'book')),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, upcoming_movie_id)
        )
    ";

    $pdo->exec($sql);
    
    // Создаем индекс для оптимизации
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_watchlist_user ON watchlist(user_id, created_at DESC)");

    echo "✅ Tabela 'watchlist' została utworzona/sprawdzona.<br>";
    echo "<h3 style='color: green;'>Gotowe! Teraz możesz dodawać filmy do listy życzeń.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>

