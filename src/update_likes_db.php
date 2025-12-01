<?php
// src/update_likes_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Tworzenie tabeli lajków...</h2>";

    // Таблица связывает пользователя и медиа-элемент
    $sql = "CREATE TABLE IF NOT EXISTS likes (
        user_id INT NOT NULL,
        media_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, media_id), -- Один юзер может лайкнуть один фильм только 1 раз
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (media_id) REFERENCES media_items(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "✅ Tabela 'likes' gotowa.<br>";
    echo "<a href='index.php'>Wróć</a>";

} catch (PDOException $e) {
    echo "Błąd: " . $e->getMessage();
}
?>