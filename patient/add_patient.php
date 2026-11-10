<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $user_id = $_SESSION['user_id'];
    $full_name = sanitize($_POST['full_name']);
    $gender = sanitize($_POST['gender']);
    $age = (int)$_POST['age'];
    $relation = sanitize($_POST['relation']);
    $phone = sanitize($_POST['phone']);
    $is_senior = ($age >= 60) ? 1 : 0;
    
    // Validation
    if (empty($full_name)) {
        $error = "Patient name is required!";
    } elseif ($age < 0 || $age > 120) {
        $error = "Please enter a valid age (0-120)!";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO patients (user_id, full_name, gender, age, relation, phone, is_senior) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$user_id, $full_name, $gender, $age, $relation, $phone, $is_senior])) {
            $success = "Family member added successfully!";
            if ($is_senior) {
                $success .= " <strong>Senior citizen benefit applied! (Priority queue)</strong>";
            }
        } else {
            $error = "Failed to add family member!";
        }
    }
}

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px; max-width: 600px; margin: 0 auto;">
    <div class="box-header">
        <h2 class="box-title">Add Family Member</h2>
        <i class="fas fa-user-plus"></i>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" required>
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
            <input type="number" name="age" class="form-control" min="0" max="120" required>
            <small>(Age 60+ gets priority queue with senior citizen benefits)</small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Relationship</label>
            <select name="relation" class="form-control">
                <option value="Self">Self</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Spouse">Spouse</option>
                <option value="Child">Child</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Add Family Member
            </button>
        </div>
    </form>
    
    <div class="info-box" style="margin-top: 1.5rem;">
        <h4><i class="fas fa-info-circle"></i> Important Information</h4>
        <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
            <li>Senior citizens (age 60+) get priority tokens (S-001, S-002, etc.)</li>
            <li>You can add multiple family members for easy appointment booking</li>
            <li>Update patient details anytime from the Family Members page</li>
            <li>Phone number helps for SMS notifications (optional)</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>