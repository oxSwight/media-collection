<?php
// src/api_like.php
require_once 'includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
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
    $newCount = $countStmt->fetchColumn();

    echo json_encode(['success' => true, 'action' => $action, 'count' => $newCount]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}