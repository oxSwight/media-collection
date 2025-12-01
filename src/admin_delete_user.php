<?php
// src/admin_delete_user.php
require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Brak uprawnień.');
}

require_valid_csrf_token($_POST['_token'] ?? null);

$targetId = (int)($_POST['id'] ?? 0);

if ($targetId && $targetId !== (int)$_SESSION['user_id']) {
    $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $avatar = $stmt->fetchColumn();
    safe_delete_upload($avatar);

    $stmt = $pdo->prepare("SELECT image_path FROM media_items WHERE user_id = ?");
    $stmt->execute([$targetId]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($images as $img) {
        safe_delete_upload($img);
    }

    $pdo->prepare("DELETE FROM media_items WHERE user_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
}

header("Location: admin.php?msg=deleted");
exit;