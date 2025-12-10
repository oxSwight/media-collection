<?php
// src/includes/db.php

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

// Na Render i podobnych chmurach wymagane jest połączenie SSL z PostgreSQL,
// dlatego jawnie ustawiamy sslmode=require.
$dsn  = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // W produkcji lepiej logować błąd do pliku, a użytkownikowi pokazać ogólny komunikat
    $isProduction = getenv('APP_ENV') === 'production' || getenv('APP_ENV') === 'prod';
    
    if ($isProduction) {
        // Logujemy błąd (jeśli to możliwe)
        error_log("Database connection error: " . $e->getMessage());
        // Pokazujemy użytkownikowi ogólny komunikat
        die("Nie można połączyć się z bazą danych. Spróbuj ponownie później.");
    } else {
        // W środowisku deweloperskim pokazujemy szczegóły
        die("Nie można połączyć się z bazą danych: " . htmlspecialchars($e->getMessage()));
    }
}
?>