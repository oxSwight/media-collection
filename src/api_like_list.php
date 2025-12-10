<?php
// src/api_like_list.php - lista osób, które polubiły элемент
require_once 'includes/init.php';
require_once __DIR__ . '/includes/rate_limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$rateLimitError = enforceRateLimit(60, 60);
if ($rateLimitError) {
    echo json_encode($rateLimitError);
    exit;
}

$mediaId = (int)($_GET['id'] ?? 0);
if ($mediaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid media id']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.avatar_path
        FROM likes l
        JOIN users u ON u.id = l.user_id
        WHERE l.media_id = ?
        ORDER BY l.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$mediaId]);
    $likers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'likers' => $likers], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    handleDatabaseError($e, 'Database error');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}

