<?php
// src/update_friends_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja bazy pod Znajomych...</h2>";

    // 1. Добавляем friend_code в таблицу users
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS friend_code VARCHAR(10) NULL UNIQUE");
    echo "✅ Kolumna 'friend_code' dodana.<br>";

    // 2. Создаем таблицу дружбы
    $sql = "CREATE TABLE IF NOT EXISTS friendships (
        id SERIAL PRIMARY KEY,
        requester_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending', -- 'pending' (ожидает), 'accepted' (друзья)
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(requester_id, receiver_id) -- Защита от дублей
    )";
    $pdo->exec($sql);
    echo "✅ Tabela 'friendships' utworzona.<br>";

    // 3. Генерируем коды для тех, у кого их нет (старых пользователей)
    $users = $pdo->query("SELECT id FROM users WHERE friend_code IS NULL")->fetchAll();
    
    $stmtUpdate = $pdo->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
    
    foreach ($users as $u) {
        // Генерируем случайный код (6 символов, цифры и буквы)
        $code = strtoupper(substr(md5(uniqid()), 0, 6)); 
        $stmtUpdate->execute([$code, $u['id']]);
        echo "🔹 Wygenerowano kod $code dla ID {$u['id']}<br>";
    }

    echo "<hr><h3 style='color: green;'>Gotowe!</h3>";
    echo "<a href='index.php'>Wróć</a>";

} catch (PDOException $e) {
    echo "Błąd: " . $e->getMessage();
}
?>