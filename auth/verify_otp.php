<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

if (!isset($_SESSION['verify_email'])) {
    redirect('auth/register.php');
}

$email = $_SESSION['verify_email'];
$error = '';
$success = '';

// Get user details
$stmt = $pdo->prepare("SELECT user_id, full_name, otp_code, otp_expires FROM users WHERE email = ? AND is_verified = 0");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['verify_email']);
    redirect('auth/register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $otp = sanitize($_POST['otp']);
    
    if (empty($otp)) {
        $error = 'Please enter the OTP.';
    } elseif ($user['otp_code'] !== $otp) {
        $error = 'Invalid OTP. Please try again.';
    } elseif (strtotime($user['otp_expires']) < time()) {
        $error = 'OTP has expired. Please request a new one.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires = NULL WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        
        unset($_SESSION['verify_email']);
        $_SESSION['message'] = 'Email verified successfully! You can now login.';
        $_SESSION['message_type'] = 'success';
        redirect('auth/login.php');
    }
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Verify Your Email</h2>
        <i class="fas fa-envelope-open-text"></i>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <p style="color:#555;margin-bottom:20px;">We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($email); ?></strong>. Please enter it below:</p>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Enter OTP *</label>
            <input type="text" name="otp" class="form-control" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required style="font-size:24px;text-align:center;letter-spacing:8px;">
            <small>Enter the 6-digit code sent to your email</small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-check-circle"></i> Verify OTP
            </button>
        </div>
    </form>
    
    <div style="text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
        <p>Didn't receive the OTP?</p>
        <form method="POST" action="resend_otp.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <button type="submit" class="btn btn-secondary" style="width: 100%;">
                <i class="fas fa-redo"></i> Resend OTP
            </button>
        </form>
    </div>
    
    <div style="text-align: center; margin-top: 1rem;">
        <p>Wrong email? <a href="register.php" style="color: #667eea;">Register again</a></p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
