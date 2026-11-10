<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);
$data = mobileJsonInput();
$vqId = (int)($data['vq_id'] ?? 0);

if (!$vqId || !$patientId) {
    mobileError('Invalid queue token');
}

$stmt = $pdo->prepare("SELECT * FROM virtual_queue WHERE vq_id = ? AND patient_id = ?");
$stmt->execute([$vqId, $patientId]);
$queue = $stmt->fetch();

if (!$queue) {
    mobileError('Queue token not found', 404);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'cancelled' WHERE vq_id = ? AND patient_id = ?");
    $stmt->execute([$vqId, $patientId]);

    $stmt = $pdo->prepare("
        UPDATE virtual_queue
        SET position = position - 1
        WHERE doctor_id = ? AND queue_date = ? AND position > ? AND status = 'waiting'
    ");
    $stmt->execute([$queue['doctor_id'], $queue['queue_date'], $queue['position']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    mobileError('Unable to cancel queue token', 500);
}

mobileResponse(['success' => true, 'message' => 'Queue token cancelled']);
?>
