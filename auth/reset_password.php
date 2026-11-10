<?php
require_once '../config/db.php';
require_once '../config/security.php';

$token = $_GET['token'] ?? '';
$tokenHash = hash('sha256', $token);
$error = '';
$success = '';

if (empty($token)) {
    $error = "Invalid reset link.";
} else {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired reset link.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            die('Invalid CSRF token');
        }
        
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        
        if (!isStrongPassword($password)) {
            $error = 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers.';
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
            $stmt->execute([$hashed, $user['user_id']]);
            session_regenerate_id(true);
            $success = "Password reset successful! Please login.";
        }
    }
}

include '../includes/header.php';
?>

<div class="card" >
    <div class="card-header">
        <h2 class="card-title">Reset Password</h2>
        <i class="fas fa-lock" style="font-size: 2rem; color: #667eea;"></i>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?> <a href="login.php">Login now</a>
        </div>
    <?php endif; ?>
    
    <?php if (!$error && !$success && isset($user)): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
                <small>Minimum 8 characters with uppercase, lowercase, and numbers</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
