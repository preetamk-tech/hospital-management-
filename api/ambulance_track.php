<?php
require_once __DIR__ . '/mobile_common.php';

$user = mobileCurrentUser('patient');
$patientId = mobilePatientIdForUser($user['user_id']);
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$patientId) {
    mobileError('Patient profile not found', 404);
}

if (!$bookingId) {
    mobileError('Booking id is required');
}

$stmt = $pdo->prepare("
    SELECT b.booking_id, b.booking_number, b.status, b.pickup_address, b.pickup_lat, b.pickup_lng,
           a.vehicle_number, a.driver_name, a.driver_phone, a.current_lat as lat, a.current_lng as lng
    FROM ambulance_bookings b
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.booking_id = ? AND b.patient_id = ?
");
$stmt->execute([$bookingId, $patientId]);
$booking = $stmt->fetch();

if (!$booking) {
    mobileError('Ambulance booking not found', 404);
}

mobileResponse([
    'success' => true,
    'booking' => [
        'booking_id' => (int)$booking['booking_id'],
        'booking_number' => $booking['booking_number'],
        'status' => $booking['status'],
        'pickup_address' => $booking['pickup_address'],
        'pickup_lat' => $booking['pickup_lat'] !== null ? (float)$booking['pickup_lat'] : null,
        'pickup_lng' => $booking['pickup_lng'] !== null ? (float)$booking['pickup_lng'] : null,
        'lat' => $booking['lat'] !== null ? (float)$booking['lat'] : null,
        'lng' => $booking['lng'] !== null ? (float)$booking['lng'] : null,
        'vehicle_number' => $booking['vehicle_number'],
        'driver_name' => $booking['driver_name'],
        'driver_phone' => $booking['driver_phone']
    ]
]);
?>
