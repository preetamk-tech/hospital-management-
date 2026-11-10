<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);
$data = mobileJsonInput();

$doctorId = (int)($data['doctor_id'] ?? 0);
$date = $data['appointment_date'] ?? date('Y-m-d');
$time = $data['appointment_time'] ?? null;
$consultationType = ($data['consultation_type'] ?? 'physical') === 'video' ? 'video' : 'physical';

if (!$patientId || !$doctorId || !validateDate($date)) {
    mobileError('Patient, doctor and valid appointment date are required');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, consultation_type)
        VALUES (?, ?, ?, ?, 'confirmed', ?)
    ");
    $stmt->execute([$patientId, $doctorId, $date, $time, $consultationType]);
    $appointmentId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tokens
        WHERE doctor_id = ? AND queue_date = ? AND status IN ('waiting', 'called')
    ");
    $stmt->execute([$doctorId, $date]);
    $position = (int)$stmt->fetch()['count'] + 1;
    $tokenNumber = 'N' . str_pad((string)$position, 3, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO tokens (appointment_id, doctor_id, token_number, token_prefix, queue_date, position, estimated_wait_time)
        VALUES (?, ?, ?, 'N', ?, ?, ?)
    ");
    $stmt->execute([$appointmentId, $doctorId, $tokenNumber, $date, $position, $position * 10]);

    if ($consultationType === 'video') {
        $roomName = 'medi-' . $doctorId . '-' . uniqid() . '-' . bin2hex(random_bytes(4));
        $scheduledAt = $date . ' 09:00:00';
        $stmt = $pdo->prepare("
            INSERT INTO consultations (appointment_id, patient_id, doctor_id, room_name, scheduled_at, duration_minutes, status)
            VALUES (?, ?, ?, ?, ?, 30, 'scheduled')
        ");
        $stmt->execute([$appointmentId, $patientId, $doctorId, $roomName, $scheduledAt]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    mobileError('Unable to book appointment', 500);
}

$response = ['success' => true, 'appointment_id' => $appointmentId, 'token_number' => $tokenNumber];
if ($consultationType === 'video') {
    $response['consultation_type'] = 'video';
}
mobileResponse($response);
?>
