<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$today = date('Y-m-d');
$vq_id = (int)($_POST['vq_id'] ?? $_GET['vq_id'] ?? 0);
$action = $_POST['action'] ?? 'call';

if ($vq_id <= 0) {
    echo json_encode(['error' => 'Invalid token ID']);
    exit;
}

// Get doctor_id if user is a doctor
$doctor_id = null;
if (hasRole('doctor')) {
    $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor = $stmt->fetch();
    $doctor_id = $doctor['doctor_id'];
}

// Fetch the virtual queue token
$stmt = $pdo->prepare("
    SELECT vq.*, p.user_id, p.full_name as patient_name, p.phone, u.email,
           d.full_name as doctor_name
    FROM virtual_queue vq
    JOIN patients p ON vq.patient_id = p.patient_id
    LEFT JOIN users u ON p.user_id = u.user_id
    JOIN doctors d ON vq.doctor_id = d.doctor_id
    WHERE vq.vq_id = ? AND vq.queue_date = ?
");
$stmt->execute([$vq_id, $today]);
$vq = $stmt->fetch();

if (!$vq) {
    echo json_encode(['error' => 'Token not found']);
    exit;
}

// Doctors can only manage their own tokens
if (hasRole('doctor') && $vq['doctor_id'] != $doctor_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($action === 'call' && $vq['status'] === 'waiting') {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'called', called_at = NOW() WHERE vq_id = ?");
    $stmt->execute([$vq_id]);

    // Create notification
    if ($vq['user_id']) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, 'Virtual Token Called', CONCAT('Your virtual token #V', ?, ' has been called. Please check in.'), 'warning')
        ");
        $stmt->execute([$vq['user_id'], $vq['token_number']]);
    }

    echo json_encode(['success' => true, 'message' => 'Token #V' . $vq['token_number'] . ' called']);
    exit;
}

if ($action === 'complete' && in_array($vq['status'], ['called', 'checked_in'])) {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'completed' WHERE vq_id = ?");
    $stmt->execute([$vq_id]);
    echo json_encode(['success' => true, 'message' => 'Token completed']);
    exit;
}

if ($action === 'cancel' && $vq['status'] === 'waiting') {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'cancelled' WHERE vq_id = ?");
    $stmt->execute([$vq_id]);
    echo json_encode(['success' => true, 'message' => 'Token cancelled']);
    exit;
}

if ($action === 'no_show' && $vq['status'] === 'called') {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'no_show' WHERE vq_id = ?");
    $stmt->execute([$vq_id]);
    echo json_encode(['success' => true, 'message' => 'Token marked as no-show']);
    exit;
}

echo json_encode(['error' => 'Invalid action or token status']);
?>
