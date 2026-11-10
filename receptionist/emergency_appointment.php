<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

// Get available doctors
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE is_available = 1 ORDER BY full_name");
$stmt->execute();
$doctors = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_name = sanitize($_POST['patient_name']);
    $age = (int)$_POST['age'];
    $gender = sanitize($_POST['gender']);
    $phone = sanitize($_POST['phone']);
    $doctor_id = (int)$_POST['doctor_id'];
    $emergency_description = sanitize($_POST['emergency_description']);
    
    // Create patient
    $stmt = $pdo->prepare("
        INSERT INTO patients (user_id, full_name, gender, age, phone, is_senior) 
        VALUES (0, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$patient_name, $gender, $age, $phone]);
    $patient_id = $pdo->lastInsertId();
    
    $date = date('Y-m-d');
    
    // Emergency token - always 'E' prefix
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(token_number, 3) AS UNSIGNED)) as last 
        FROM tokens 
        WHERE queue_date = ? AND token_prefix = 'E'
    ");
    $stmt->execute([$date]);
    $last_token = $stmt->fetch();
    $next_number = ($last_token['last'] ?? 0) + 1;
    $token_number = 'E-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    // Create appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, is_emergency) 
        VALUES (?, ?, ?, 'confirmed', 1)
    ");
    $stmt->execute([$patient_id, $doctor_id, $date]);
    $appointment_id = $pdo->lastInsertId();
    
    // Emergency gets position 1 (front of queue)
    $stmt = $pdo->prepare("
        INSERT INTO tokens (appointment_id, doctor_id, token_number, token_prefix, queue_date, position, status) 
        VALUES (?, ?, ?, 'E', ?, 1, 'waiting')
    ");
    $stmt->execute([$appointment_id, $doctor_id, $token_number, $date]);
    
    // Shift all existing tokens position by 1
    $stmt = $pdo->prepare("
        UPDATE tokens 
        SET position = position + 1 
        WHERE queue_date = ? AND token_prefix != 'E' AND status = 'waiting'
    ");
    $stmt->execute([$date]);
    
    setMessage("Emergency appointment created! Token: $token_number (Priority)", "success");
    redirect('receptionist/emergency_appointment.php');
}

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title">Emergency Appointment</h2>
        <i class="fas fa-ambulance" style="color: #f56565;"></i>
    </div>
    
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Emergency patients will get priority and go to the front of the queue!</strong>
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
                            Dr. <?php echo htmlspecialchars($row['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Emergency Description</label>
                <textarea name="emergency_description" class="form-control" rows="2" placeholder="Brief description of emergency..."></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-danger"><i class="fas fa-ambulance"></i> Create Emergency Appointment</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>