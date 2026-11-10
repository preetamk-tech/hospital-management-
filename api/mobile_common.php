<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

// CORS headers for mobile and external API consumers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function mobileJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    return $_POST;
}

function mobileResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function mobileError($msg, $code = 400) {
    mobileResponse([
        'success' => false,
        'error' => $msg,
        'message' => $msg
    ], $code);
}

function mobileCreateToken($user) {
    $payload = [
        'user_id' => (int)$user['user_id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + (86400 * 30) // 30 days
    ];
    $b64 = base64_encode(json_encode($payload));
    $sig = hash_hmac('sha256', $b64, APP_SECRET);
    return $b64 . '.' . $sig;
}

function mobileCurrentUser($requiredRole = null) {
    global $pdo;
    
    // Check Authorization Header
    $token = null;
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        $token = $m[1];
    } elseif (!empty($_GET['token'])) {
        $token = $_GET['token'];
    } elseif (!empty($_SESSION['user_id'])) {
        $user = getUser($_SESSION['user_id']);
        if ($user) {
            if ($requiredRole && $user['role'] !== $requiredRole) {
                mobileError('Forbidden', 403);
            }
            return $user;
        }
    }
    
    if (!$token) {
        mobileError('Unauthorized. Bearer token required.', 401);
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        mobileError('Invalid token format', 401);
    }
    
    list($b64, $sig) = $parts;
    $expectedSig = hash_hmac('sha256', $b64, APP_SECRET);
    if (!hash_equals($expectedSig, $sig)) {
        mobileError('Invalid token signature', 401);
    }
    
    $payload = json_decode(base64_decode($b64), true);
    if (!$payload || !isset($payload['user_id'])) {
        mobileError('Malformed token payload', 401);
    }
    
    if (($payload['exp'] ?? 0) < time()) {
        mobileError('Token has expired. Please login again.', 401);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([(int)$payload['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        mobileError('User account not found or deactivated', 401);
    }
    
    if ($requiredRole && $user['role'] !== $requiredRole) {
        mobileError('Forbidden: Access denied for role ' . htmlspecialchars($user['role']), 403);
    }
    
    return $user;
}

function mobilePatientIdForUser($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ? ORDER BY patient_id ASC LIMIT 1");
    $stmt->execute([(int)$userId]);
    $patientId = $stmt->fetchColumn();
    return $patientId ? (int)$patientId : null;
}

function mobileTokenPayload($token) {
    if (!$token) {
        return [
            'success' => true,
            'has_token' => false,
            'token' => null
        ];
    }
    return [
        'success' => true,
        'has_token' => true,
        'token' => [
            'token_id' => (int)($token['token_id'] ?? 0),
            'token_number' => $token['token_number'] ?? '',
            'token_prefix' => $token['token_prefix'] ?? '',
            'status' => $token['status'] ?? '',
            'position' => (int)($token['position'] ?? 0),
            'estimated_wait_time' => (int)($token['estimated_wait_time'] ?? 0),
            'doctor_name' => $token['doctor_name'] ?? '',
            'queue_date' => $token['queue_date'] ?? date('Y-m-d')
        ]
    ];
}
?>
