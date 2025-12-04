<?php
// src/export.php - Экспорт коллекции в CSV/JSON

require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$format = $_GET['format'] ?? 'json'; // json | csv

// Получаем данные коллекции
$stmt = $pdo->prepare("
    SELECT 
        title,
        type,
        author_director,
        release_year,
        rating,
        review,
        genres,
        created_at
    FROM media_items
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

if ($format === 'csv') {
    // Экспорт в CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="collection_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM для правильного отображения кириллицы в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Заголовки
    fputcsv($output, [
        'Название',
        'Тип',
        'Автор/Режиссер',
        'Год',
        'Оценка',
        'Рецензия',
        'Жанры',
        'Дата добавления'
    ], ';');
    
    // Данные
    foreach ($items as $item) {
        fputcsv($output, [
            $item['title'],
            $item['type'] === 'movie' ? 'Фильм' : 'Книга',
            $item['author_director'],
            $item['release_year'] ?: '',
            $item['rating'] ?: '',
            $item['review'] ?: '',
            $item['genres'] ?: '',
            $item['created_at']
        ], ';');
    }
    
    fclose($output);
    exit;
} else {
    // Экспорт в JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="collection_' . date('Y-m-d') . '.json"');
    
    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'total_items' => count($items),
        'items' => array_map(function($item) {
            return [
                'title' => $item['title'],
                'type' => $item['type'],
                'author_director' => $item['author_director'],
                'release_year' => $item['release_year'] ? (int)$item['release_year'] : null,
                'rating' => $item['rating'] ? (int)$item['rating'] : null,
                'review' => $item['review'],
                'genres' => $item['genres'],
                'created_at' => $item['created_at']
            ];
        }, $items)
    ];
    
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

