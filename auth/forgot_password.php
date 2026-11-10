<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

$message = '';
$error = '';
$safeMessage = 'If this email exists, password reset instructions have been sent.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $email = sanitize($_POST['email']);
    
    if (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
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
            
            // Send password reset email
            if (!sendPasswordResetEmail($email, $user['full_name'], $resetLink)) {
                error_log("Failed to send password reset email to user_id " . $user['user_id']);
            }
        }

        $message = $safeMessage;
    }
}

include '../includes/header.php';
?>

<div class="card" >
    <div class="card-header">
        <h2 class="card-title">Forgot Password</h2>
        <i class="fas fa-key" style="font-size: 2rem; color: #667eea;"></i>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </div>
        
        <div style="text-align: center;">
            <a href="login.php">Back to Login</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
