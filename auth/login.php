<?php
require_once '../config/db.php';
require_once '../config/security.php';

    if (isLoggedIn()) {
    if (hasRole('patient')) redirect('patient/dashboard.php');
    elseif (hasRole('doctor')) redirect('doctor/dashboard.php');
    elseif (hasRole('receptionist')) redirect('receptionist/dashboard.php');
    elseif (hasRole('admin')) redirect('admin/dashboard.php');
    elseif (hasRole('driver')) redirect('drivers/dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        
        // Rate limiting
        if (!checkRateLimit($email, 5, 300)) {
            $error = 'Too many login attempts. Please try again after 5 minutes.';
        } else {
            // Get user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Clear failed attempts
                clearFailedLogins($email);

                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->execute([$newHash, $user['user_id']]);
                }
                
                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                session_regenerate_id(true);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'patient': redirect('patient/dashboard.php'); break;
                    case 'doctor': redirect('doctor/dashboard.php'); break;
                    case 'receptionist': redirect('receptionist/dashboard.php'); break;
                    case 'admin': redirect('admin/dashboard.php'); break;
                    case 'driver': redirect('drivers/dashboard.php'); break;
                    default: redirect('auth/login.php');
                }
            } else {
                trackFailedLogin($email);
                $error = 'Invalid email or password.';
                
                // Check if account is locked
                $failedCount = getFailedLoginCount($email);
                if ($failedCount >= 5) {
                    $error = 'Account temporarily locked due to multiple failed attempts. Please try again later.';
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card" >
    <div class="card-header">
        <h2 class="card-title">Welcome Back</h2>
        <i class="fas fa-hospital-user"></i>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required autofocus>
        </div>
        
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%; ">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="forgot_password.php" style="color: #667eea;">Forgot Password?</a>
        </div>
        
        <div style="text-align: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <p>Don't have an account? <a href="register.php" style="color: #667eea; font-weight: 600;">Register</a></p>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
