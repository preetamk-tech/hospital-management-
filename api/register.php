<?php
require_once __DIR__ . '/mobile_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobileError('Method not allowed', 405);
}

$data = mobileJsonInput();
$fullName = sanitize($data['full_name'] ?? $data['name'] ?? '');
$email = sanitize($data['email'] ?? '');
$password = $data['password'] ?? '';
$confirm = $data['confirm_password'] ?? $data['password_confirmation'] ?? $password;
$age = isset($data['age']) ? (int)$data['age'] : 0;
$gender = sanitize($data['gender'] ?? 'Male');
$phone = sanitize($data['phone'] ?? '');

if (!$fullName || !$email || !$password) {
    mobileError('Name, email and password are required');
}

if (!validateEmail($email)) {
    mobileError('Please enter a valid email address');
}

if (!isStrongPassword($password)) {
    mobileError('Password must be at least 8 characters and contain uppercase, lowercase, and numbers');
}

if ($password !== $confirm) {
    mobileError('Passwords do not match');
}

if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
    $gender = 'Male';
}

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    mobileError('Email already registered. Please login.', 409);
}

$pdo->beginTransaction();
try {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'patient')");
    $stmt->execute([$fullName, $email, $hashed]);
    $userId = (int)$pdo->lastInsertId();

    $isSenior = $age >= 60 ? 1 : 0;
    $stmt = $pdo->prepare("
        INSERT INTO patients (user_id, full_name, gender, age, phone, relation, is_senior)
        VALUES (?, ?, ?, ?, ?, 'Self', ?)
    ");
    $stmt->execute([$userId, $fullName, $gender, $age, $phone, $isSenior]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    mobileError('Registration failed. Please try again.', 500);
}

mobileResponse(['success' => true, 'message' => 'Registration successful']);
?>
