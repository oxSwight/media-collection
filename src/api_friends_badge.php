<?php
// src/api_friends_badge.php - API endpoint для получения количества входящих запросов в друзья

require_once 'includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

$myId = (int)$_SESSION['user_id'];

// Подсчитываем входящие запросы в друзья
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM friendships 
    WHERE receiver_id = ? AND status = 'pending'
");
$stmt->execute([$myId]);
$count = (int)$stmt->fetchColumn();

echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE);
?>

