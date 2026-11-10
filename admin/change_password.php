<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect!';
        } 
        // Check password strength
        elseif (!isStrongPassword($new_password)) {
            $error = 'Password must be at least 8 characters and contain uppercase, lowercase, and numbers.';
        } 
        // Check if passwords match
        elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match!';
        }
        // Check if new password is same as old password
        elseif (password_verify($new_password, $user['password'])) {
            $error = 'New password cannot be the same as current password!';
        }
        else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $success = 'Password changed successfully! You will be redirected to login page.';
                
                // Clear session to force re-login with new password
                session_destroy();
                
                // Redirect after 2 seconds
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "../auth/login.php";
                    }, 2000);
                </script>';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="box" style="max-width: 500px; margin: 70px auto 0;">
    <div class="box-header">
        <h2 class="box-title">Change Password</h2>
        <i class="fas fa-key" style="font-size: 2rem; color: #667eea;"></i>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <p style="margin-top: 10px;">Redirecting to login page...</p>
        </div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <form method="POST" id="passwordForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Current Password *</label>
            <input type="password" name="current_password" class="form-control" id="current_password" required autofocus>
        </div>
        
        <div class="form-group">
            <label class="form-label">New Password *</label>
            <input type="password" name="new_password" class="form-control" id="new_password" required>
            <small>Minimum 8 characters with uppercase, lowercase, and numbers</small>
            <div id="passwordStrength" style="margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
            <div id="passwordMatch" style="margin-top: 5px;"></div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;" id="submitBtn">
                <i class="fas fa-save"></i> Change Password
            </button>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="dashboard.php" style="color: #667eea;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
// Password strength checker
document.getElementById('new_password').addEventListener('keyup', function() {
    var password = this.value;
    var strengthDiv = document.getElementById('passwordStrength');
    
    if (password.length == 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    var strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    var strengthText = '';
    var strengthColor = '';
    
    switch(strength) {
        case 1:
            strengthText = 'Weak';
            strengthColor = '#f56565';
            break;
        case 2:
            strengthText = 'Fair';
            strengthColor = '#ed8936';
            break;
        case 3:
            strengthText = 'Good';
            strengthColor = '#48bb78';
            break;
        case 4:
            strengthText = 'Strong';
            strengthColor = '#38a169';
            break;
        case 5:
            strengthText = 'Very Strong';
            strengthColor = '#2c7a4d';
            break;
        default:
            strengthText = 'Very Weak';
            strengthColor = '#f56565';
    }
    
    if (strength >= 3) {
        strengthDiv.innerHTML = '<span style="color: ' + strengthColor + ';"><i class="fas fa-check-circle"></i> ' + strengthText + ' password</span>';
    } else {
        strengthDiv.innerHTML = '<span style="color: ' + strengthColor + ';"><i class="fas fa-exclamation-triangle"></i> ' + strengthText + ' password (add uppercase, numbers, or special characters)</span>';
    }
});

// Password match checker
document.getElementById('confirm_password').addEventListener('keyup', function() {
    var password = document.getElementById('new_password').value;
    var confirm = this.value;
    var matchDiv = document.getElementById('passwordMatch');
    
    if (confirm.length == 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (password === confirm) {
        matchDiv.innerHTML = '<span style="color: #48bb78;"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        matchDiv.innerHTML = '<span style="color: #f56565;"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
    }
});

// Form validation before submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    var newPassword = document.getElementById('new_password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    var submitBtn = document.getElementById('submitBtn');
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        showToast('New passwords do not match!', 'error');
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        showToast('Password must be at least 8 characters long!', 'error');
        return false;
    }
    
    // Disable button and show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
    submitBtn.disabled = true;
});
</script>

<style>
#passwordStrength, #passwordMatch {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.alert {
    margin-bottom: 1.5rem;
}
</style>

<?php include '../includes/footer.php'; ?>