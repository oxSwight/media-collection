<?php
// src/delete_item.php
require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_valid_csrf_token($_POST['_token'] ?? null);

$id = (int)($_POST['id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT image_path FROM media_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $item = $stmt->fetch();

    if ($item) {
        safe_delete_upload($item['image_path'] ?? null);

        $deleteStmt = $pdo->prepare("DELETE FROM media_items WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$id, $userId]);
    }
}

header("Location: index.php");
exit;