<?php
// CSRF Protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
        error_log("CSRF token mismatch.");
        return false;
    }
    
    return true;
}

// Rate Limiting using database
function checkRateLimit($key, $limit = 5, $timeout = 300) {
    global $pdo;
    $time = date('Y-m-d H:i:s', strtotime("-{$timeout} seconds"));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempt_time > ?");
    $stmt->execute([$key, $ip, $time]);
    $result = $stmt->fetch();
    
    return $result['count'] < $limit;
}

// Failed Login Tracking
function trackFailedLogin($email) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

function getFailedLoginCount($email, $minutes = 30) {
    global $pdo;
    $time = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempt_time > ?");
    $stmt->execute([$email, $ip, $time]);
    $result = $stmt->fetch();
    return $result['count'];
}

function clearFailedLogins($email) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
}

// Password Strength Check
function isStrongPassword($password) {
    $commonPasswords = ['password', 'password123', 'admin123', 'qwerty123', '12345678', 'letmein123'];
    return strlen($password) >= 8 &&
           !in_array(strtolower($password), $commonPasswords, true) &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password);
}

// Input Validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// XSS Prevention
function xss_clean($data) {
    if (is_array($data)) {
        return array_map('xss_clean', $data);
    }
    return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
}
?>
