<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_ids = array_column($stmt->fetchAll(), 'patient_id');

if (empty($my_ids)) {
    redirect('patient/ambulance.php');
}

$placeholders = implode(',', array_fill(0, count($my_ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM ambulance_bookings WHERE booking_id = ? AND patient_id IN ($placeholders) AND status IN ('requested', 'assigned')");
$stmt->execute(array_merge([$booking_id], $my_ids));
$booking = $stmt->fetch();

if ($booking) {
    $pdo->prepare("UPDATE ambulance_bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$booking_id]);
    
    if ($booking['ambulance_id']) {
        $pdo->prepare("UPDATE ambulances SET status = 'available' WHERE ambulance_id = ? AND status = 'dispatched'")->execute([$booking['ambulance_id']]);
    }
    
    setMessage('Ambulance booking cancelled.', 'success');
}

redirect('patient/ambulance.php');
?>
