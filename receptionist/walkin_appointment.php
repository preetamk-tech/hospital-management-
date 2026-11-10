<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

// Get doctors
$stmt = $pdo->prepare("
    SELECT d.*, dep.department_name 
    FROM doctors d 
    JOIN departments dep ON d.department_id = dep.department_id 
    WHERE d.is_available = 1
    ORDER BY dep.department_name, d.full_name
");
$stmt->execute();
$doctors = $stmt->fetchAll();

// Get patient from URL parameter (for existing patient)
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_name = sanitize($_POST['patient_name']);
    $age = (int)$_POST['age'];
    $gender = sanitize($_POST['gender']);
    $phone = sanitize($_POST['phone']);
    $doctor_id = (int)$_POST['doctor_id'];
    $appointment_date = sanitize($_POST['appointment_date']);
    
    // Create patient (no user_id for walk-in)
    $is_senior = ($age >= 60) ? 1 : 0;
    $prefix = $is_senior ? 'S' : 'N';
    
    $stmt = $pdo->prepare("
        INSERT INTO patients (user_id, full_name, gender, age, phone, is_senior) 
        VALUES (0, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$patient_name, $gender, $age, $phone, $is_senior]);
    $patient_id = $pdo->lastInsertId();
    
    // Get next token number
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(token_number, 3) AS UNSIGNED)) as last 
        FROM tokens 
        WHERE queue_date = ? AND token_prefix = ?
    ");
    $stmt->execute([$appointment_date, $prefix]);
    $last_token = $stmt->fetch();
    $next_number = ($last_token['last'] ?? 0) + 1;
    $token_number = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    // Create appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, consultation_type) 
        VALUES (?, ?, ?, 'confirmed', 'physical')
    ");
    $stmt->execute([$patient_id, $doctor_id, $appointment_date]);
    $appointment_id = $pdo->lastInsertId();
    
    // Create token
    $stmt = $pdo->prepare("
        INSERT INTO tokens (appointment_id, doctor_id, token_number, token_prefix, queue_date, position, estimated_wait_time) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $wait_time = ($next_number - 1) * 10;
    $stmt->execute([$appointment_id, $doctor_id, $token_number, $prefix, $appointment_date, $next_number, $wait_time]);
    
    setMessage("Walk-in appointment created! Token: $token_number", "success");
    redirect('receptionist/walkin_appointment.php');
}

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title">Walk-in Appointment</h2>
        <i class="fas fa-user-plus"></i>
    </div>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="form-group">
                <label class="form-label">Patient Name *</label>
                <input type="text" name="patient_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Age *</label>
                <input type="number" name="age" class="form-control" min="0" max="120" required>
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
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile number">
            </div>
            
            <div class="form-group">
                <label class="form-label">Select Doctor *</label>
                <select name="doctor_id" class="form-control" required>
                    <option value="">-- Select Doctor --</option>
                    <?php foreach ($doctors as $row): ?>
                        <option value="<?php echo $row['doctor_id']; ?>">
                            Dr. <?php echo htmlspecialchars($row['full_name']); ?> (<?php echo htmlspecialchars($row['department_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Appointment Date *</label>
                <input type="date" name="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Appointment</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
    
    <div class="info-box" style="margin-top: 1.5rem; padding: 1rem; background: #f7fafc; border-radius: 12px;">
        <h4><i class="fas fa-info-circle"></i> Information</h4>
        <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
            <li>Walk-in patients are not registered users</li>
            <li>Senior citizens (60+) automatically get priority tokens (S-xxx)</li>
            <li>Regular patients get normal tokens (N-xxx)</li>
            <li>Estimated wait time: 10 minutes per patient ahead</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>