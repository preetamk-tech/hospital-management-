<?php
require_once __DIR__ . '/mobile_common.php';

$doctorId = (int)($_GET['doctor_id'] ?? 0);
if (!$doctorId) {
    mobileError('Doctor is required');
}

$stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = ? AND is_active = 1 ORDER BY day_of_week, start_time");
$stmt->execute([$doctorId]);

mobileResponse($stmt->fetchAll());
?>
