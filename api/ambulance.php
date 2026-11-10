<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);

if (!$patientId) {
    mobileError('Patient profile not found', 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT b.*, a.vehicle_number, a.driver_name, a.driver_phone, a.current_lat, a.current_lng
        FROM ambulance_bookings b
        LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$patientId]);
    mobileResponse(['success' => true, 'bookings' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobileError('Method not allowed', 405);
}

$data = mobileJsonInput();
$pickupAddress = trim($data['pickup_address'] ?? '');
$pickupLat = isset($data['pickup_lat']) ? (float)$data['pickup_lat'] : null;
$pickupLng = isset($data['pickup_lng']) ? (float)$data['pickup_lng'] : null;
$emergencyType = $data['emergency_type'] ?? 'routine';
$notes = trim($data['notes'] ?? '');
$allowedTypes = ['normal', 'routine', 'emergency', 'transfer', 'discharge'];

if (!$pickupAddress) {
    mobileError('Pickup address is required');
}

if (!in_array($emergencyType, $allowedTypes, true)) {
    mobileError('Invalid emergency type');
}

$next = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 10) AS UNSIGNED)), 0) + 1 FROM ambulance_bookings")->fetchColumn();
$bookingNumber = 'AMB-' . date('Y') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("
    INSERT INTO ambulance_bookings (booking_number, patient_id, pickup_address, pickup_lat, pickup_lng, emergency_type, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$bookingNumber, $patientId, $pickupAddress, $pickupLat, $pickupLng, $emergencyType, $notes]);

mobileResponse([
    'success' => true,
    'message' => 'Ambulance requested successfully',
    'booking_id' => (int)$pdo->lastInsertId(),
    'booking_number' => $bookingNumber
], 201);
?>
