<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn()) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}

$booking_id = (int)$_POST['booking_id'];
$ambulance_id = (int)$_POST['ambulance_id'];
$new_status = sanitize($_POST['new_status']);

$status_transitions = [
    'dispatched' => 'on_way',
    'on_way' => 'at_pickup',
    'at_pickup' => 'en_route',
    'en_route' => 'at_hospital',
    'at_hospital' => 'completed'
];

$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("UPDATE ambulance_bookings SET status = ? WHERE booking_id = ?");
$stmt->execute([$new_status, $booking_id]);

if ($new_status == 'on_way') {
    $pdo->prepare("UPDATE ambulances SET status = 'on_way' WHERE ambulance_id = ?")->execute([$ambulance_id]);
} elseif ($new_status == 'at_pickup') {
    $pdo->prepare("UPDATE ambulance_bookings SET arrived_at_pickup = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
} elseif ($new_status == 'en_route') {
    $pdo->prepare("UPDATE ambulances SET status = 'en_route' WHERE ambulance_id = ?")->execute([$ambulance_id]);
} elseif ($new_status == 'at_hospital') {
    $pdo->prepare("UPDATE ambulance_bookings SET arrived_at_hospital = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
} elseif ($new_status == 'completed') {
    $pdo->prepare("UPDATE ambulance_bookings SET completed_at = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
    $pdo->prepare("UPDATE ambulances SET status = 'available' WHERE ambulance_id = ?")->execute([$ambulance_id]);
}

$role = $_SESSION['role'];
if ($role == 'receptionist') {
    redirect('receptionist/ambulance.php');
} elseif ($role == 'admin') {
    redirect('admin/manage_ambulances.php');
} else {
    redirect('patient/ambulance.php');
}
?>
