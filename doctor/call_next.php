<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';
require_once '../includes/audit.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    redirect('auth/login.php');
}

// Get doctor info
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    setMessage("Doctor profile not found!", "error");
    redirect('auth/logout.php');
}

$doctor_id = $doctor['doctor_id'];

// Call next token
if (isset($_GET['call'])) {
    $token_id = (int)$_GET['call'];
    
    // Update token status
    $stmt = $pdo->prepare("UPDATE tokens SET status = 'called', called_at = NOW(), service_start_time = NOW() WHERE token_id = ?");
    $stmt->execute([$token_id]);
    
    // Get token details for notification
    $stmt = $pdo->prepare("
        SELECT t.token_number, p.user_id, p.full_name, p.phone, u.email, d.full_name as doctor_name, dep.department_name
        FROM tokens t
        JOIN appointments a ON t.appointment_id = a.appointment_id
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN users u ON p.user_id = u.user_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        WHERE t.token_id = ?
    ");
    $stmt->execute([$token_id]);
    $token = $stmt->fetch();
    
    // Create notification for patient
    if ($token && $token['user_id']) {
        $stmt = $pdo->prepare("
               INSERT INTO notifications (user_id, title, message, type) 
               VALUES (?, 'Token Called', CONCAT('Your token #', ?, ' has been called. Please proceed to doctor.'), 'warning')
            ");
        $stmt->execute([$token['user_id'], $token['token_number']]);
        
        // Send email notification
        if ($token['email']) {
            sendTokenCalledEmail(
                $token['email'],
                $token['full_name'],
                $token['token_number'],
                $token['doctor_name'],
                $token['department_name']
            );
        }
    }
    
    setMessage("Token #" . $token['token_number'] . " called successfully!", "success");
    redirect('doctor/dashboard.php');
}

// Complete current token
if (isset($_GET['complete'])) {
    $token_number = sanitize($_GET['complete']);
    
    // Update token status
    $stmt = $pdo->prepare("UPDATE tokens SET status = 'completed', completed_at = NOW(), service_end_time = NOW() WHERE token_number = ? AND queue_date = CURDATE()");
    $stmt->execute([$token_number]);
    
    logAudit('complete_patient', 'tokens', null, ['status' => 'called'], ['status' => 'completed']);
    
    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE appointments a 
        JOIN tokens t ON a.appointment_id = t.appointment_id 
        SET a.status = 'completed' 
        WHERE t.token_number = ? AND t.queue_date = CURDATE()
    ");
    $stmt->execute([$token_number]);
    
    setMessage("Patient completed successfully!", "success");
    redirect('doctor/dashboard.php');
}

redirect('doctor/dashboard.php');
?>