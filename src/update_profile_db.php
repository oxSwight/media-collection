<?php
// src/update_profile_db.php
require_once 'includes/db.php';

try {
    echo "<h2>Aktualizacja bazy pod Profil...</h2>";

    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL");
    echo "Kolumna 'avatar_path' dodana.<br>";

    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL");
    echo "Kolumna 'bio' dodana.<br>";

    echo "<h3 style='color: green;'>Gotowe! Można robić profile.</h3>";
    echo "<a href='index.php'>Wróć</a>";

} catch (PDOException $e) {
    echo "Błąd: " . $e->getMessage();
}
?>