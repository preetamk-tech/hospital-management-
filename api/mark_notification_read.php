<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit();
}

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notification_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>