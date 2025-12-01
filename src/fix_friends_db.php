<?php
// src/fix_friends_db.php
require_once 'includes/db.php';

try {
    echo "<h2>🔧 Naprawianie bazy danych (Znajomi)...</h2>";

    // 1. Добавляем колонку friend_code, если её нет
    // Используем IF NOT EXISTS (Postgres 9.6+), либо игнорируем ошибку через catch
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN friend_code VARCHAR(10) NULL UNIQUE");
        echo "✅ Kolumna 'friend_code' dodana.<br>";
    } catch (PDOException $e) {
        // Если колонка уже есть, Postgres выдаст ошибку - игнорируем её
        echo "ℹ️ Kolumna 'friend_code' już istnieje (lub inny błąd: " . $e->getMessage() . ")<br>";
    }

    // 2. Создаем таблицу дружбы
    $sql = "CREATE TABLE IF NOT EXISTS friendships (
        id SERIAL PRIMARY KEY,
        requester_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(requester_id, receiver_id)
    )";
    $pdo->exec($sql);
    echo "✅ Tabela 'friendships' sprawdzona/utworzona.<br>";

    // 3. Генерируем коды для всех пользователей, у которых их нет (NULL)
    $users = $pdo->query("SELECT id FROM users WHERE friend_code IS NULL")->fetchAll();
    
    if (count($users) > 0) {
        $stmtUpdate = $pdo->prepare("UPDATE users SET friend_code = ? WHERE id = ?");
        $count = 0;
        foreach ($users as $u) {
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)); 
            $stmtUpdate->execute([$code, $u['id']]);
            $count++;
        }
        echo "✅ Wygenerowano kody dla $count użytkowników.<br>";
    } else {
        echo "ℹ️ Wszyscy użytkownicy mają już kody.<br>";
    }

    echo "<hr><h3 style='color: green;'>Gotowe! Baza jest naprawiona.</h3>";
    echo "<a href='friends.php' style='font-size: 1.2rem; font-weight: bold;'>Przejdź do Znajomych &rarr;</a>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Krytyczny błąd: " . $e->getMessage() . "</h3>";
}
?>