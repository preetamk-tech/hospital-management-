<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$vq_id = (int)($_GET['vq_id'] ?? 0);
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT vq.* FROM virtual_queue vq
    JOIN patients p ON vq.patient_id = p.patient_id
    WHERE p.user_id = ? AND vq.vq_id = ?
");
$stmt->execute([$user_id, $vq_id]);
$vq = $stmt->fetch();

if (!$vq) {
    echo json_encode(['error' => 'Token not found']);
    exit;
}

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM virtual_queue 
    WHERE queue_date = ? AND doctor_id = ? AND position < ? AND status IN ('waiting', 'called')
");
$stmt->execute([$today, $vq['doctor_id'], $vq['position']]);
$position_ahead = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT token_number FROM virtual_queue 
    WHERE queue_date = ? AND doctor_id = ? AND status = 'called' ORDER BY called_at DESC LIMIT 1
");
$stmt->execute([$today, $vq['doctor_id']]);
$current = $stmt->fetch();

echo json_encode([
    'vq_id' => $vq['vq_id'],
    'token_number' => 'V' . $vq['token_number'],
    'status' => $vq['status'],
    'position' => $vq['position'],
    'position_ahead' => $position_ahead,
    'estimated_time' => $position_ahead * 10,
    'current_serving' => $current ? 'V' . $current['token_number'] : null
]);
?>
