<?php
// src/update_comments_db.php
// Создаем таблицу для комментариев к фильмам друзей

require_once 'includes/db.php';

try {
    echo "<h2>Tworzenie tabeli komentarzy...</h2>";

    $sql = "
        CREATE TABLE IF NOT EXISTS comments (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            media_id INT NOT NULL REFERENCES media_items(id) ON DELETE CASCADE,
            comment_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";

    $pdo->exec($sql);
    
    // Создаем индексы для оптимизации
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_media ON comments(media_id, created_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_user ON comments(user_id)");

    echo "✅ Tabela 'comments' została utworzona/sprawdzona.<br>";
    echo "<h3 style='color: green;'>Gotowe! Teraz możesz komentować filmy i książки друзей.</h3>";
    echo "<a href='index.php'>Wróć na stronę główną</a>";
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</h3>";
}
?>

