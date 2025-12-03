<?php
// src/includes/db.php

$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

// Для Render и похожих облаков требуется SSL-подключение к PostgreSQL,
// поэтому явно указываем sslmode=require.
$dsn  = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // В продакшене лучше логировать ошибку в файл, а пользователю показывать "Упс"
    die("Nie można połączyć się z bazą danych: " . $e->getMessage());
}
?>