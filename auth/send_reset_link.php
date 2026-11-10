<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$email = sanitize($_POST['email'] ?? '');

if (!validateEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit();
}

$stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = generateToken();
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
    $stmt->execute([$tokenHash, $expires, $user['user_id']]);

    $resetLink = SITE_URL . "auth/reset_password.php?token=" . $token;
    if (!sendPasswordResetEmail($email, $user['full_name'], $resetLink)) {
        error_log("Failed to send password reset email to user_id " . $user['user_id']);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'If this email exists, password reset instructions have been sent.'
]);
