<?php
// src/fix_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Naprawianie bazy danych...</h2>";

    // 1. Добавляем колонку is_admin, если её нет
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin INT DEFAULT 0");
    echo "✅ Kolumna 'is_admin' została dodana.<br>";

    // 2. Добавляем колонки для профиля (на всякий случай, если их тоже нет)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL");
    echo "✅ Kolumna 'avatar_path' sprawdzona.<br>";
    
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL");
    echo "✅ Kolumna 'bio' sprawdzona.<br>";

    echo "<hr>";
    echo "<h3>Sukces! Baza jest naprawiona.</h3>";
    echo "<p>Teraz możesz odświeżyć stronę 'Społeczność'.</p>";
    echo "<a href='community.php'>Przejdź do społeczności</a>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Błąd: " . $e->getMessage() . "</h3>";
}
?>