<?php
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT t.token_id, t.token_number, t.position, t.status
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE p.user_id = ? AND t.queue_date = CURDATE() AND t.status = 'waiting'
    ORDER BY t.token_id DESC LIMIT 1
");
$stmt->execute([$user_id]);
$token = $stmt->fetch();

if (!$token) {
    echo json_encode(['has_token' => false]);
    exit();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) as c FROM tokens 
    WHERE queue_date = CURDATE() AND status = 'waiting' AND position < ?
");
$stmt->execute([$token['position']]);
$position_ahead = $stmt->fetch()['c'];

$stmt = $pdo->prepare("
    SELECT token_number FROM tokens 
    WHERE queue_date = CURDATE() AND status = 'called' 
    ORDER BY token_id DESC LIMIT 1
");
$stmt->execute();
$current_serving = $stmt->fetch();

echo json_encode([
    'has_token' => true,
    'token_number' => $token['token_number'],
    'position' => $token['position'],
    'position_ahead' => $position_ahead,
    'current_serving' => $current_serving['token_number'] ?? 'None',
    'estimated_time' => $position_ahead * 10
]);
?>