<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);

if (!$patientId) {
    mobileError('Patient profile not found', 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = mobileJsonInput();
    $doctorId = (int)($data['doctor_id'] ?? 0);
    if (!$doctorId) {
        mobileError('Doctor is required');
    }

    $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_id = ? AND is_available = 1");
    $stmt->execute([$doctorId]);
    if (!$stmt->fetch()) {
        mobileError('Doctor not available', 404);
    }

    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM virtual_queue
        WHERE patient_id = ? AND queue_date = ? AND status IN ('waiting', 'called', 'checked_in')
    ");
    $stmt->execute([$patientId, $today]);
    if ((int)$stmt->fetch()['count'] > 0) {
        mobileError('You already have an active virtual queue token for today', 409);
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(token_number), 0) + 1 as next_token
        FROM virtual_queue
        WHERE queue_date = ? AND doctor_id = ?
    ");
    $stmt->execute([$today, $doctorId]);
    $nextToken = (int)$stmt->fetch()['next_token'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM virtual_queue
        WHERE queue_date = ? AND doctor_id = ? AND status IN ('waiting', 'called')
    ");
    $stmt->execute([$today, $doctorId]);
    $position = (int)$stmt->fetch()['count'] + 1;

    $stmt = $pdo->prepare("
        INSERT INTO virtual_queue
            (patient_id, doctor_id, token_number, token_prefix, queue_date, position, joined_at, estimated_wait_minutes)
        VALUES (?, ?, ?, 'V', ?, ?, ?, ?)
    ");
    $stmt->execute([$patientId, $doctorId, $nextToken, $today, $position, date('Y-m-d H:i:s'), $position * 10]);
}

$stmt = $pdo->prepare("
    SELECT vq.*, d.full_name as doctor_name
    FROM virtual_queue vq
    JOIN doctors d ON vq.doctor_id = d.doctor_id
    WHERE vq.patient_id = ? AND vq.queue_date = CURDATE() AND vq.status IN ('waiting', 'called', 'checked_in')
    ORDER BY vq.vq_id DESC LIMIT 1
");
$stmt->execute([$patientId]);
$queue = $stmt->fetch();

if (!$queue) {
    mobileResponse(['active' => null]);
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM virtual_queue
    WHERE doctor_id = ? AND queue_date = CURDATE() AND status = 'waiting' AND position < ?
");
$stmt->execute([$queue['doctor_id'], $queue['position']]);
$positionAhead = (int)$stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM virtual_queue
    WHERE doctor_id = ? AND queue_date = CURDATE() AND status IN ('waiting', 'called')
");
$stmt->execute([$queue['doctor_id']]);
$totalWaiting = (int)$stmt->fetch()['count'];

mobileResponse([
    'active' => [
        'vq_id' => (int)$queue['vq_id'],
        'token_number' => (int)$queue['token_number'],
        'status' => $queue['status'],
        'doctor_name' => $queue['doctor_name'],
        'position' => (int)$queue['position'],
        'position_ahead' => $positionAhead,
        'total_waiting' => $totalWaiting,
        'estimated_wait' => (int)$queue['estimated_wait_minutes'],
        'check_in_window' => (int)$queue['check_in_window_minutes']
    ]
]);
?>
