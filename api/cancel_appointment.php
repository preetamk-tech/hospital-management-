<?php
require_once __DIR__ . '/mobile_common.php';
require_once __DIR__ . '/../includes/email_functions.php';

if (isLoggedIn()) {
    $userId = (int)$_SESSION['user_id'];
} else {
    $user = mobileCurrentUser('patient');
    $userId = (int)$user['user_id'];
}

$data = mobileJsonInput();
$appointment_id = isset($data['appointment_id']) ? (int)$data['appointment_id'] : 0;

if (!$appointment_id) {
    mobileError('Invalid appointment');
}

// Get token info
$stmt = $pdo->prepare("
    SELECT t.token_id, t.token_number, t.position, t.queue_date, p.user_id, p.full_name, u.email, a.appointment_date
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON p.user_id = u.user_id
    WHERE t.appointment_id = ? AND p.user_id = ?
");
$stmt->execute([$appointment_id, $userId]);
$token = $stmt->fetch();

if ($token) {
    // Cancel appointment and token
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    
    $stmt = $pdo->prepare("UPDATE tokens SET status = 'cancelled' WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    
    // Update positions for remaining tokens
    $stmt = $pdo->prepare("
        UPDATE tokens 
        SET position = position - 1
        WHERE queue_date = ? AND position > ? AND status = 'waiting'
    ");
    $stmt->execute([$token['queue_date'], $token['position']]);
    
    // Send email notification
    if ($token['email']) {
        sendAppointmentCancelledEmail(
            $token['email'],
            $token['full_name'],
            $token['token_number'],
            $token['appointment_date']
        );
    }
    
    mobileResponse(['success' => true, 'message' => 'Appointment cancelled, queue updated']);
} else {
    mobileError('Token not found', 404);
}
?>
