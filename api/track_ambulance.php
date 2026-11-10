<?php
require_once '../config/db.php';
require_once '../config/security.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$accessSql = "";
$params = [$booking_id];

if (hasRole('patient')) {
    $userId = (int)$_SESSION['user_id'];
    $accessSql = " AND b.patient_id IN (SELECT patient_id FROM patients WHERE user_id = ?)";
    $params[] = $userId;
} elseif (!hasRole('admin') && !hasRole('receptionist')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT b.status, a.current_lat as lat, a.current_lng as lng, a.vehicle_number, a.driver_name, a.driver_phone,
           b.pickup_address, b.arrived_at_pickup, b.arrived_at_hospital
    FROM ambulance_bookings b
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.booking_id = ? $accessSql
");
$stmt->execute($params);
$data = $stmt->fetch();

if ($data && $data['lat']) {
    echo json_encode([
        'success' => true,
        'lat' => (float)$data['lat'],
        'lng' => (float)$data['lng'],
        'status' => $data['status'],
        'vehicle_number' => $data['vehicle_number'],
        'driver_name' => $data['driver_name'],
        'driver_phone' => $data['driver_phone']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Tracking data not available']);
}
?>
