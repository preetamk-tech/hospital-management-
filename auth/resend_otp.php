<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

if (!isset($_SESSION['verify_email'])) {
    redirect('auth/register.php');
}

$email = $_SESSION['verify_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
}

$stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_verified = 0");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['verify_email']);
    redirect('auth/register.php');
}

$otp = sprintf("%06d", mt_rand(100000, 999999));
$otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires = ? WHERE user_id = ?");
$stmt->execute([$otp, $otp_expires, $user['user_id']]);

$otpSent = sendOTPByEmail($email, $user['full_name'], $otp);

if ($otpSent) {
    $_SESSION['message'] = 'New OTP sent successfully!';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Failed to send OTP. Please try again.';
    $_SESSION['message_type'] = 'error';
}

redirect('auth/verify_otp.php');
