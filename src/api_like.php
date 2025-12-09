<?php
// src/api_like.php
require_once 'includes/init.php';
require_once __DIR__ . '/includes/rate_limit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Rate limiting: 30 запросов в минуту для API

$rateLimitError = enforceRateLimit(30, 60);
if ($rateLimitError) {
    echo json_encode($rateLimitError);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
require_valid_csrf_token($csrfHeader);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$mediaId = (int)($data['id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($mediaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid media id']);
    exit;
}

try {
    // Проверяем, существует ли медиа-элемент и принадлежит ли он пользователю или его друзьям
    $mediaCheck = $pdo->prepare("SELECT user_id FROM media_items WHERE id = ?");
    $mediaCheck->execute([$mediaId]);
    $mediaOwner = $mediaCheck->fetchColumn();
    
    if (!$mediaOwner) {
        jsonError('Media item not found', 404);
    }
    
    $check = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND media_id = ?");
    $check->execute([$userId, $mediaId]);
    $exists = $check->fetchColumn();

    if ($exists) {
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND media_id = ?");
        $stmt->execute([$userId, $mediaId]);
        
        $action = 'unliked';
    } else {
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, media_id) VALUES (?, ?)");
        $stmt->execute([$userId, $mediaId]);
        $action = 'liked';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE media_id = ?");
    $countStmt->execute([$mediaId]);
    $newCount = (int)$countStmt->fetchColumn();

    jsonSuccess(['action' => $action, 'count' => $newCount]);

} catch (PDOException $e) {
    handleDatabaseError($e, 'Database error');
    jsonError('Server error', 500);
} catch (Exception $e) {
    logError('Unexpected error in api_like.php: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    jsonError('Server error', 500);
}