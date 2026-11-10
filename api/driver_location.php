<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('driver')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$ambulance_id = (int)($input['ambulance_id'] ?? 0);
$lat = (float)($input['lat'] ?? 0);
$lng = (float)($input['lng'] ?? 0);

if ($ambulance_id <= 0 || $lat == 0 || $lng == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT ambulance_id FROM ambulances WHERE ambulance_id = ? AND user_id = ?");
$stmt->execute([$ambulance_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE ambulances SET current_lat = ?, current_lng = ? WHERE ambulance_id = ?");
    $stmt->execute([$lat, $lng, $ambulance_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>
