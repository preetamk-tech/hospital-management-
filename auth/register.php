<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

if (isLoggedIn()) {
    redirect('patient/dashboard.php');
}

$error = '';
$success = '';
$full_name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $age = isset($_POST['age']) ? (int)$_POST['age'] : 0;
    $gender = isset($_POST['gender']) ? sanitize($_POST['gender']) : 'Male';
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!isStrongPassword($password)) {
        $error = 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already registered. Please login.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Generate OTP
                $otp = sprintf("%06d", mt_rand(100000, 999999));
                $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, otp_code, otp_expires, is_verified) VALUES (?, ?, ?, 'patient', ?, ?, 0)");
                $stmt->execute([$full_name, $email, $hashed, $otp, $otp_expires]);
                $user_id = $pdo->lastInsertId();
                
                // Create patient record with actual age and gender
                $is_senior = ($age >= 60) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO patients (user_id, full_name, gender, age, phone, relation, is_senior) VALUES (?, ?, ?, ?, ?, 'Self', ?)");
                $stmt->execute([$user_id, $full_name, $gender, $age, $phone, $is_senior]);
                
                // Send OTP via email pehle
                $otpSent = sendOTPByEmail($email, $full_name, $otp);
                
                if ($otpSent) {
                    $pdo->commit();
                    $_SESSION['verify_email'] = $email;
                    redirect('auth/verify_otp.php');
                } else {
                    $pdo->rollBack();
                    $error = "Failed to send OTP. Please check your email and try again.";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Registration failed: " . $e->getMessage());
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="card" >
    <div class="card-header">
        <h2 class="card-title">Create Account</h2>
        <i class="fas fa-user-plus"></i>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?> <a href="login.php">Login here</a>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Gender *</label>
            <select name="gender" class="form-control" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Age *</label>
            <input type="number" name="age" class="form-control" min="0" max="120" value="0" required>
            <small>(Age 60+ gets priority queue)</small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number">
        </div>
        
        <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required>
            <small>Minimum 8 characters with uppercase, lowercase, and numbers</small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Already have an account? <a href="login.php" style="color: #667eea;">Login</a></p>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
