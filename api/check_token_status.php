<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$token_id = isset($_GET['token_id']) ? (int)$_GET['token_id'] : 0;

if (!$token_id) {
    echo json_encode(['error' => 'Invalid token']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT t.*, a.doctor_id 
    FROM tokens t 
    JOIN appointments a ON t.appointment_id = a.appointment_id 
    WHERE t.token_id = ?
");
$stmt->execute([$token_id]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['error' => 'Token not found']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT token_number FROM tokens 
    WHERE doctor_id = ? AND queue_date = CURDATE() AND status = 'called' 
    ORDER BY called_at DESC LIMIT 1
");
$stmt->execute([$token['doctor_id']]);
$current = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM tokens 
    WHERE queue_date = CURDATE() AND status = 'waiting' AND position < ?
");
$stmt->execute([$token['position']]);
$position_ahead = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM tokens 
    WHERE queue_date = CURDATE() AND status = 'waiting'
");
$stmt->execute();
$total_waiting = $stmt->fetch()['count'];

echo json_encode([
    'status' => $token['status'],
    'token_number' => $token['token_number'],
    'position' => $token['position'],
    'position_ahead' => $position_ahead,
    'total_waiting' => $total_waiting,
    'current_serving' => $current['token_number'] ?? 'None',
    'estimated_time' => $position_ahead * 10
]);
?>