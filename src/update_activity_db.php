<?php
// src/update_activity_db.php
// Создаем таблицу активностей пользователей.

require_once 'includes/db.php';

try {
    echo "<h2>Tworzenie tabeli aktywności użytkowników...</h2>";

    $sql = "
        CREATE TABLE IF NOT EXISTS activities (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            media_id INT REFERENCES media_items(id) ON DELETE CASCADE,
            type VARCHAR(50) NOT NULL, -- np. 'add_item'
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";

    $pdo->exec($sql);

    echo "✅ Tabela 'activities' została utworzona/sprawdzona.<br>";
    echo "<h3 style='color: green;'>Gotowe! Teraz możesz śledzić aktywność użytkowników.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>


