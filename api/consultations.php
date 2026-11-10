<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);

try {
    $stmt = $pdo->prepare("
        SELECT c.*, d.full_name as doctor_name
        FROM consultations c
        JOIN doctors d ON c.doctor_id = d.doctor_id
        WHERE c.patient_id = ?
        ORDER BY c.scheduled_at DESC
    ");
    $stmt->execute([$patientId]);
    mobileResponse($stmt->fetchAll());
} catch (Exception $e) {
    mobileResponse([]);
}
?>
