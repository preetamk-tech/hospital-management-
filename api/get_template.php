<?php
require_once '../config/db.php';
require_once '../config/security.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('doctor')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$doctor_id = $pdo->prepare("SELECT doctor_id FROM doctors WHERE user_id = ?")->execute([$_SESSION['user_id']]);
$stmt = $pdo->query("SELECT doctor_id FROM doctors WHERE user_id = " . (int)$_SESSION['user_id']);
$doctor = $stmt->fetch();
$doctor_id = $doctor['doctor_id'];

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM prescription_templates WHERE template_id = ? AND doctor_id = ?");
    $stmt->execute([(int)$_GET['id'], $doctor_id]);
    $template = $stmt->fetch();
    if ($template) {
        echo json_encode(['success' => true, 'diagnosis' => $template['diagnosis'], 'prescription' => $template['prescription'], 'notes' => $template['notes']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
    }
} else {
    $stmt = $pdo->prepare("SELECT template_id, template_name, condition_type FROM prescription_templates WHERE doctor_id = ? AND is_active = 1");
    $stmt->execute([$doctor_id]);
    echo json_encode(['success' => true, 'templates' => $stmt->fetchAll()]);
}
?>
