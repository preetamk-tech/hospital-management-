<?php
// Start session with safer browser cookie defaults.
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Database configuration (Pre-configured for TiDB Cloud & Render)
define('DB_HOST', getenv('DB_HOST') ?: 'gateway01.ap-northeast-1.prod.aws.tidbcloud.com');
define('DB_PORT', getenv('DB_PORT') ?: '4000');
define('DB_NAME', getenv('DB_NAME') ?: 'hospital_queue');
define('DB_USER', getenv('DB_USER') ?: '3esLxTPndRcafMJ.root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'Jy9tN8ZkmIr38cv2');
define('APP_SECRET', getenv('APP_SECRET') ?: 'd6d5362021b0033237d03c2dec51b1a79e9e10f55aab6e7af30f30622bdf216d');

// Site URL - Supports Render root & Localhost subfolders
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$folder = dirname(dirname($script_name));
$folder = str_replace('\\', '/', $folder);
$folder = ($folder == '/' || $folder == '/\\') ? '' : $folder;
define('SITE_URL', $protocol . $host . $folder . '/');

// PDO Database Connection with Port and Cloud SSL support
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // Enable SSL for TiDB Cloud using the ISRG Root X1 certificate
    $caFile = __DIR__ . '/isrgrootx1.pem';
    if (file_exists($caFile) && (DB_PORT == '4000' || DB_HOST !== 'localhost')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

// Time zone
date_default_timezone_set('Asia/Kolkata');

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function setMessage($msg, $type = 'success') {
    $_SESSION['message'] = $msg;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $msg = '<div class="alert alert-' . $type . '"><i class="fas fa-' . ($type == 'success' ? 'check-circle' : ($type == 'error' ? 'exclamation-circle' : 'info-circle')) . '"></i> ' . $_SESSION['message'] . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        return $msg;
    }
    return '';
}

function getUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function generateToken() {
    return bin2hex(random_bytes(32));
}
?>
