<?php
// src/afisha_add.php - перенаправление на форму добавления с предзаполненными данными из афиши

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
$myId = (int)$_SESSION['user_id'];
$check = $pdo->prepare("SELECT id FROM media_items WHERE user_id = ? AND type = 'movie' AND LOWER(title) = LOWER(?)");
$check->execute([$myId, $movie['title']]);
if ($check->fetchColumn()) {
    header('Location: index.php?msg=already_exists');
    exit;
}

// Формируем год из release_date
$year = '';
if (!empty($movie['release_date'])) {
    $year = (int)date('Y', strtotime($movie['release_date']));
}

// Перенаправляем на форму добавления с предзаполненными данными через GET параметры
$params = [
    'from_afisha' => '1',
    'title' => urlencode($movie['title']),
    'author' => urlencode($movie['original_title'] ?: ''),
    'year' => $year,
    'image_url' => urlencode($movie['poster_url'] ?? ''),
    'review' => urlencode($movie['overview'] ?? ''),
];

$queryString = http_build_query($params);
header('Location: add_item.php?' . $queryString);
exit;


