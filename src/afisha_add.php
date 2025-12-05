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
$externalId = $_POST['external_id'] ?? null;
$tmdbData = null;

// Если передан external_id (из прямого поиска TMDb), получаем данные из TMDb API
if (!empty($externalId) && is_numeric($externalId)) {
    $apiKey = getenv('TMDB_API_KEY');
    if ($apiKey) {
        // Выбираем язык для API
        $langMap = [
            'pl' => 'pl-PL',
            'ru' => 'ru-RU',
            'en' => 'en-US',
        ];
        $apiLang = $langMap[$_SESSION['lang'] ?? 'pl'] ?? 'en-US';
        
        // Запрос к TMDb API для получения детальной информации о фильме
        $url = sprintf(
            'https://api.themoviedb.org/3/movie/%d?api_key=%s&language=%s',
            (int)$externalId,
            urlencode($apiKey),
            urlencode($apiLang)
        );
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $tmdbData = json_decode($response, true);
        }
    }
}

// Если есть данные из TMDb, используем их
if ($tmdbData && is_array($tmdbData)) {
    // Формируем данные фильма из ответа TMDb
    $posterUrl = null;
    if (!empty($tmdbData['poster_path'])) {
        $posterUrl = 'https://image.tmdb.org/t/p/w342' . $tmdbData['poster_path'];
    }
    
    $movie = [
        'title' => $tmdbData['title'] ?? '',
        'original_title' => $tmdbData['original_title'] ?? '',
        'overview' => $tmdbData['overview'] ?? '',
        'poster_url' => $posterUrl,
        'release_date' => $tmdbData['release_date'] ?? null,
    ];
} elseif ($upcomingId > 0) {
    // Иначе берем фильм из локальной БД upcoming_movies
    $stmt = $pdo->prepare("SELECT * FROM upcoming_movies WHERE id = ?");
    $stmt->execute([$upcomingId]);
    $movie = $stmt->fetch();
    
    if (!$movie) {
        header('Location: afisha.php');
        exit;
    }
} else {
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


