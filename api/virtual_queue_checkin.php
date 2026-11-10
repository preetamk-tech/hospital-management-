<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);
$data = mobileJsonInput();
$vqId = (int)($data['vq_id'] ?? 0);

if (!$vqId || !$patientId) {
    mobileError('Invalid queue token');
}

$stmt = $pdo->prepare("
    UPDATE virtual_queue
    SET status = 'checked_in', checked_in_at = NOW()
    WHERE vq_id = ? AND patient_id = ? AND status = 'called'
");
$stmt->execute([$vqId, $patientId]);

if ($stmt->rowCount() === 0) {
    mobileError('Queue token cannot be checked in', 409);
}

mobileResponse(['success' => true, 'message' => 'Checked in successfully']);
?>
