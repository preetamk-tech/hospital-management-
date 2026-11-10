<?php
require_once __DIR__ . '/mobile_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobileError('Method not allowed', 405);
}

$data = mobileJsonInput();
$email = sanitize($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
    mobileError('Email and password are required');
}

if (!checkRateLimit($email, 5, 300)) {
    mobileError('Too many login attempts. Please try again after 5 minutes.', 429);
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    trackFailedLogin($email);
    mobileError('Invalid email or password', 401);
}

clearFailedLogins($email);
if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$newHash, $user['user_id']]);
}
$token = mobileCreateToken($user);

mobileResponse([
    'success' => true,
    'token' => $token,
    'user' => [
        'id' => (int)$user['user_id'],
        'user_id' => (int)$user['user_id'],
        'name' => $user['full_name'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role']
    ]
]);
?>
