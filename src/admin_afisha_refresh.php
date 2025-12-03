<?php
// src/admin_afisha_refresh.php
// Ручное обновление афиши: админ тянет список предстоящих фильмов из TMDb и сохраняет в БД.

require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Brak uprawnień.');
}

$apiKey = getenv('TMDB_API_KEY');

if (!$apiKey) {
    require_once 'includes/header.php';
    echo "<div class='container' style='padding:40px; text-align:center;'>";
    echo "<h2 style='margin-bottom:15px;'>Brak TMDB_API_KEY</h2>";
    echo "<p>Aby korzystać z afiszy, dodaj zmienną środowiskową <code>TMDB_API_KEY</code> w panelu Render (Environment).</p>";
    echo "<p>Klucz możesz uzyskać po rejestracji na <a href='https://www.themoviedb.org/' target='_blank' rel='noopener'>The Movie Database (TMDb)</a>.</p>";
    echo "<p>Po dodaniu klucza wróć na tę stronę, aby odświeżyć listę filmów.</p>";
    echo "<p><a href='afisha.php' class='btn-submit' style='width:auto; text-decoration:none; margin-top:20px;'>↩ Wróć do afiszy</a></p>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit;
}

// Выбираем язык для API в зависимости от текущего языка интерфейса
$langMap = [
    'pl' => 'pl-PL',
    'ru' => 'ru-RU',
    'en' => 'en-US',
];
$apiLang = $langMap[$currentLang] ?? 'en-US';

// Для региональных премьер можно указать region, но оставим по умолчанию (świat)
$page = 1;
$maxPages = 10; // до ~200 filmów (10 stron), wciąż bez przesady dla API
$imported = 0;

// Простая upsert-подготовка
$stmt = $pdo->prepare("
    INSERT INTO upcoming_movies (external_id, title, original_title, overview, poster_url, release_date, genres, popularity, updated_at)
    VALUES (:external_id, :title, :original_title, :overview, :poster_url, :release_date, :genres, :popularity, NOW())
    ON CONFLICT (external_id) DO UPDATE SET
        title = EXCLUDED.title,
        original_title = EXCLUDED.original_title,
        overview = EXCLUDED.overview,
        poster_url = EXCLUDED.poster_url,
        release_date = EXCLUDED.release_date,
        genres = EXCLUDED.genres,
        popularity = EXCLUDED.popularity,
        updated_at = NOW()
");

function fetch_tmdb_page(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

for ($page = 1; $page <= $maxPages; $page++) {
    $url = sprintf(
        'https://api.themoviedb.org/3/movie/upcoming?api_key=%s&language=%s&page=%d',
        urlencode($apiKey),
        urlencode($apiLang),
        $page
    );

    $data = fetch_tmdb_page($url);
    if (!$data || empty($data['results'])) {
        break;
    }

    foreach ($data['results'] as $movie) {
        $genres = '';
        if (!empty($movie['genre_ids']) && is_array($movie['genre_ids'])) {
            // Сохраняем как список ID жанров, разделенных запятыми (np. "28,12,878")
            $genres = implode(',', array_map('intval', $movie['genre_ids']));
        }

        $params = [
            ':external_id'    => (string)$movie['id'],
            ':title'          => $movie['title'] ?? '',
            ':original_title' => $movie['original_title'] ?? '',
            ':overview'       => $movie['overview'] ?? '',
            ':poster_url'     => !empty($movie['poster_path'])
                ? 'https://image.tmdb.org/t/p/w342' . $movie['poster_path']
                : null,
            ':release_date'   => !empty($movie['release_date']) ? $movie['release_date'] : null,
            ':genres'         => $genres,
            ':popularity'     => $movie['popularity'] ?? null,
        ];

        $stmt->execute($params);
        $imported++;
    }

    // Если это последняя страница по данным API — выходим
    if (!isset($data['total_pages']) || $page >= (int)$data['total_pages']) {
        break;
    }
}

// Po zakończeniu – ciche przekierowanie z powrotem na afiszę
header('Location: afisha.php');
exit;
