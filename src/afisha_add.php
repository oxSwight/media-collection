<?php
// src/afisha_add.php - добавить фильм из афиши в личную коллекцию

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: afisha.php');
    exit;
}

require_valid_csrf_token($_POST['_token'] ?? null);

$myId = (int)$_SESSION['user_id'];
$upcomingId = (int)($_POST['upcoming_id'] ?? 0);

if ($upcomingId <= 0) {
    header('Location: afisha.php');
    exit;
}

// Берем фильм из upcoming_movies
$stmt = $pdo->prepare("SELECT * FROM upcoming_movies WHERE id = ?");
$stmt->execute([$upcomingId]);
$movie = $stmt->fetch();

if (!$movie) {
    header('Location: afisha.php');
    exit;
}

// Проверяем, нет ли уже такого названия в коллекции пользователя
$check = $pdo->prepare("SELECT id FROM media_items WHERE user_id = ? AND type = 'movie' AND LOWER(title) = LOWER(?)");
$check->execute([$myId, $movie['title']]);
if ($check->fetchColumn()) {
    header('Location: index.php');
    exit;
}

// Вставляем в media_items
$insert = $pdo->prepare("
    INSERT INTO media_items (user_id, title, type, author_director, release_year, rating, image_path, review)
    VALUES (?, ?, 'movie', ?, ?, ?, ?, ?)
");

$year = null;
if (!empty($movie['release_date'])) {
    $year = (int)date('Y', strtotime($movie['release_date']));
}

// Рейтинг по умолчанию 5 (средняя оценка), пользователь потом может изменить через редактирование
// БД требует rating BETWEEN 1 AND 10, поэтому нельзя использовать 0
$rating = 5;

$insert->execute([
    $myId,
    $movie['title'],
    $movie['original_title'] ?: '',
    $year,
    $rating,
    $movie['poster_url'],
    $movie['overview'] ?? '',
]);

$mediaId = (int)$pdo->lastInsertId();

// Логируем активность
$act = $pdo->prepare("INSERT INTO activities (user_id, media_id, type) VALUES (?, ?, 'add_item')");
$act->execute([$myId, $mediaId]);

header('Location: index.php?msg=added');
exit;


