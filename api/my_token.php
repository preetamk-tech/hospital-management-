<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');

$stmt = $pdo->prepare("
    SELECT t.*, d.full_name as doctor_name
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE p.user_id = ? AND t.status IN ('waiting', 'called') AND t.queue_date = CURDATE()
    ORDER BY t.token_id DESC LIMIT 1
");
$stmt->execute([$user['user_id']]);
$token = $stmt->fetch();

mobileResponse(mobileTokenPayload($token));
?>
