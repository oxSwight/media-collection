<?php
// src/update_afisha_db.php
// Создаем таблицу для хранения предстоящих фильмов (афиша).

require_once 'includes/db.php';

try {
    echo "<h2>Tworzenie tabeli dla nadchodzących filmów (afisza)...</h2>";

    $sql = "
        CREATE TABLE IF NOT EXISTS upcoming_movies (
            id SERIAL PRIMARY KEY,
            external_id VARCHAR(50) UNIQUE NOT NULL, -- ID z zewnętrznego API (np. TMDb)
            title VARCHAR(255) NOT NULL,
            original_title VARCHAR(255),
            overview TEXT,
            poster_url VARCHAR(255),
            release_date DATE,
            genres VARCHAR(255),
            popularity NUMERIC,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";

    $pdo->exec($sql);

    echo "✅ Tabela 'upcoming_movies' została utworzona/sprawdzona.<br>";
    echo "<h3 style='color: green;'>Gotowe! Teraz możesz zasilać afiszę danymi.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>


