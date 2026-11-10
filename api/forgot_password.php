<?php
require_once __DIR__ . '/mobile_common.php';
require_once __DIR__ . '/../includes/email_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobileError('Method not allowed', 405);
}

$data = mobileJsonInput();
$email = sanitize($data['email'] ?? '');

if ($email && validateEmail($email)) {
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
}

mobileResponse([
    'success' => true,
    'message' => 'If this email exists, password reset instructions have been sent.'
]);
?>
