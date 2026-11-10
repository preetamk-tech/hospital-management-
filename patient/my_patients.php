<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Handle update patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // Debug: Check if POST is received
    error_log("Update request received");
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_id = (int)$_POST['patient_id'];
    $full_name = sanitize($_POST['full_name']);
    $age = (int)$_POST['age'];
    $gender = sanitize($_POST['gender']);
    $phone = sanitize($_POST['phone']);
    $relation = sanitize($_POST['relation']);
    $is_senior = ($age >= 60) ? 1 : 0;
    
    // Validate input
    if (empty($full_name)) {
        setMessage("Patient name cannot be empty!", "error");
    } elseif ($age < 0 || $age > 120) {
        setMessage("Please enter a valid age (0-120)!", "error");
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE patients 
                SET full_name = ?, 
                    age = ?, 
                    gender = ?, 
                    phone = ?, 
                    relation = ?, 
                    is_senior = ?
                WHERE patient_id = ? AND user_id = ?
            ");
            
            $result = $stmt->execute([$full_name, $age, $gender, $phone, $relation, $is_senior, $patient_id, $user_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                setMessage("Patient updated successfully!", "success");
                if ($is_senior) {
                    setMessage("Senior citizen benefit applied! (Priority queue)", "info");
                }
            } else {
                setMessage("No changes made or patient not found!", "warning");
            }
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            setMessage("Database error: " . $e->getMessage(), "error");
        }
    }
    redirect('patient/my_patients.php');
}

// Get all family members
$stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$patients = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">My Family Members</h2>
        <a href="add_patient.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Member</a>
    </div>
    
    <?php if (empty($patients)): ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-users" style="font-size: 3rem; color: #a0aec0;"></i>
            <p style="margin-top: 1rem;">No family members added yet.</p>
            <a href="add_patient.php" class="btn btn-primary">Add Your First Family Member</a>
        </div>
    <?php else: ?>
        <?php foreach ($patients as $index => $row): ?>
        <div class="card" style="margin-bottom: 1rem; background: var(--light); border: 1px solid #e2e8f0;">
            <form method="POST" action="" id="form_<?php echo $row['patient_id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="patient_id" value="<?php echo $row['patient_id']; ?>">
                
                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($row['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Age *</label>
                        <input type="number" name="age" class="form-control" value="<?php echo $row['age']; ?>" min="0" max="120" required>
                        <?php if ($row['age'] >= 60): ?>
                            <small class="badge badge-success" style="margin-top: 5px; display: inline-block;">
                                <i class="fas fa-heart"></i> Senior Citizen Priority
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php echo $row['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $row['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $row['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($row['phone']); ?>" placeholder="Mobile number">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <select name="relation" class="form-control">
                            <option value="Self" <?php echo $row['relation'] == 'Self' ? 'selected' : ''; ?>>Self</option>
                            <option value="Father" <?php echo $row['relation'] == 'Father' ? 'selected' : ''; ?>>Father</option>
                            <option value="Mother" <?php echo $row['relation'] == 'Mother' ? 'selected' : ''; ?>>Mother</option>
                            <option value="Spouse" <?php echo $row['relation'] == 'Spouse' ? 'selected' : ''; ?>>Spouse</option>
                            <option value="Child" <?php echo $row['relation'] == 'Child' ? 'selected' : ''; ?>>Child</option>
                            <option value="Other" <?php echo $row['relation'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary" onclick="return confirmUpdate()">
                        <i class="fas fa-save"></i> Update
                    </button>
                    
                    <a href="book_appointment.php?patient_id=<?php echo $row['patient_id']; ?>" class="btn btn-success">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function confirmUpdate() {
    return confirm('Are you sure you want to update this patient information?');
}

// Add debug logging
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, forms ready');
    
    // Add form submit debugging
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Form submitted:', this.id);
        });
    });
});
</script>

<style>
body.dark-mode .card[style*="background: var(--light)"] {
    background: #4a5568 !important;
    border-color: #718096 !important;
}

.form-group {
    margin-bottom: 0;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
}
</style>

<?php include '../includes/footer.php'; ?>