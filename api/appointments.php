<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');

$stmt = $pdo->prepare("
    SELECT a.*, d.full_name as doctor_name, p.full_name as patient_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE p.user_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$user['user_id']]);

mobileResponse($stmt->fetchAll());
?>
