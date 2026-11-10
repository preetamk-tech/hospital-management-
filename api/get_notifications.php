<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get unread notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC LIMIT 20
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Mark as read
if (!empty($notifications)) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

echo json_encode($notifications);
?>