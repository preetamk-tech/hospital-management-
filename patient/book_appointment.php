<?php
require_once '../config/db.php';
require_once '../config/security.php';
require_once '../includes/email_functions.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get family members
$stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ? ORDER BY full_name");
$stmt->execute([$user_id]);
$patients = $stmt->fetchAll();

// Get available doctors
$stmt = $pdo->prepare("
    SELECT d.*, dep.department_name 
    FROM doctors d 
    JOIN departments dep ON d.department_id = dep.department_id 
    WHERE d.is_available = 1
    ORDER BY dep.department_name, d.full_name
");
$stmt->execute();
$doctors = $stmt->fetchAll();

$message = '';
$message_type = 'danger';
$token_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $appointment_date = sanitize($_POST['appointment_date']);
    $consultation_type = ($_POST['consultation_type'] ?? 'physical') === 'video' ? 'video' : 'physical';
    
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $message = "Please select a future date!";
    } else {
        // Check if doctor is available on this date
        $day_of_week = date('N', strtotime($appointment_date));
        $stmt = $pdo->prepare("
            SELECT * FROM doctor_schedule 
            WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
        ");
        $stmt->execute([$doctor_id, $day_of_week]);
        
        if (!$stmt->fetch()) {
            $message = "Doctor is not available on this day!";
        } else {
            // Get patient info
            $stmt = $pdo->prepare("SELECT age FROM patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $patient_info = $stmt->fetch();
            
            $is_senior = ($patient_info['age'] >= 60);
            $prefix = $is_senior ? 'S' : 'N';
            
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
            $wait_time = ($next_number - 1) * 10;
            
            $pdo->beginTransaction();
            try {
                // Create appointment
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, consultation_type) 
                    VALUES (?, ?, ?, 'confirmed', ?)
                ");
                $stmt->execute([$patient_id, $doctor_id, $appointment_date, $consultation_type]);
                $appointment_id = $pdo->lastInsertId();
                
                // Create token
                $stmt = $pdo->prepare("
                    INSERT INTO tokens (appointment_id, doctor_id, token_number, token_prefix, queue_date, position, estimated_wait_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$appointment_id, $doctor_id, $token_number, $prefix, $appointment_date, $next_number, $wait_time]);
                
                // If video consultation, create consultation record
                if ($consultation_type === 'video') {
                    $room_name = 'medi-' . $doctor_id . '-' . uniqid() . '-' . bin2hex(random_bytes(4));
                    $scheduled_at = $appointment_date . ' 09:00:00';
                    $stmt = $pdo->prepare("
                        INSERT INTO consultations (appointment_id, patient_id, doctor_id, room_name, scheduled_at, duration_minutes, status)
                        VALUES (?, ?, ?, ?, ?, 30, 'scheduled')
                    ");
                    $stmt->execute([$appointment_id, $patient_id, $doctor_id, $room_name, $scheduled_at]);
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Appointment booking failed: " . $e->getMessage());
                setMessage("Booking failed. Please try again.", "error");
                redirect('patient/book_appointment.php');
            }
            
            // Get user email and doctor details for notification
            $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user_info = $stmt->fetch();
            
            $stmt = $pdo->prepare("
                SELECT d.full_name as doctor_name, dep.department_name 
                FROM doctors d 
                JOIN departments dep ON d.department_id = dep.department_id 
                WHERE d.doctor_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $doctor_info = $stmt->fetch();
            
            // Send email notification
            if ($user_info && $user_info['email']) {
                sendAppointmentBookedEmail(
                    $user_info['email'],
                    $user_info['full_name'],
                    $token_number,
                    $doctor_info['doctor_name'],
                    $doctor_info['department_name'],
                    $appointment_date,
                    $wait_time
                );
            }
            
            $msg = "Appointment booked successfully! Token: $token_number";
            if ($consultation_type === 'video') {
                $msg .= " (Video Consultation — Join from My Consultations)";
            }
            setMessage($msg, "success");
            redirect('patient/my_appointments.php');
        }
    }
}

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px; max-width: 600px; margin: 0 auto;">
    <div class="box-header">
        <h2 class="box-title">Book Appointment</h2>
        <i class="fas fa-calendar-plus"></i>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Select Patient *</label>
            <select name="patient_id" class="form-control" required>
                <option value="">-- Select Patient --</option>
                <?php foreach ($patients as $row): ?>
                    <option value="<?php echo $row['patient_id']; ?>">
                        <?php echo htmlspecialchars($row['full_name']); ?> (<?php echo $row['age']; ?> yrs)
                        <?php if ($row['age'] >= 60): ?> - Senior Citizen! <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small><a href="add_patient.php" style="color:red;">+ Add new family member</a></small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Select Doctor *</label>
            <select name="doctor_id" class="form-control" required>
                <option value="">-- Select Doctor --</option>
                <?php foreach ($doctors as $row): ?>
                    <option value="<?php echo $row['doctor_id']; ?>">
                        Dr. <?php echo htmlspecialchars($row['full_name']); ?> - <?php echo htmlspecialchars($row['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Appointment Date *</label>
            <input type="date" name="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Consultation Type *</label>
            <div style="display:flex;gap:1rem;margin-top:0.5rem;">
                <label style="display:flex;align-items:center;gap:8px;padding:12px 20px;border:2px solid #667eea;border-radius:10px;cursor:pointer;flex:1;transition:all 0.2s;background:#f8f9ff;">
                    <input type="radio" name="consultation_type" value="physical" checked style="width:18px;height:18px;accent-color:#667eea;">
                    <div>
                        <div style="font-weight:600;color:#333;"><i class="fas fa-hospital" style="color:#667eea;"></i> Physical Visit</div>
                        <div style="font-size:12px;color:#888;">Visit hospital in person</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:8px;padding:12px 20px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;flex:1;transition:all 0.2s;" id="videoOption">
                    <input type="radio" name="consultation_type" value="video" style="width:18px;height:18px;accent-color:#667eea;">
                    <div>
                        <div style="font-weight:600;color:#333;"><i class="fas fa-video" style="color:#48bb78;"></i> Video Consultation</div>
                        <div style="font-size:12px;color:#888;">Online call from home</div>
                    </div>
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-check-circle"></i> Book Appointment
            </button>
        </div>
    </form>
    
    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i> Information</h4>
        <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
            <li>Senior citizens (60+) get priority tokens (S-001)</li>
            <li>Estimated wait: 10 minutes per patient ahead</li>
            <li>You'll receive notification when token is called</li>
            <li>You can cancel appointment up to 24 hours before</li>
        </ul>
    </div>
</div>

<script>
document.querySelectorAll('input[name="consultation_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const physical = document.querySelector('label[for=""]');
        document.querySelectorAll('label').forEach(function(label) {
            if (label.querySelector('input[name="consultation_type"]')) {
                label.style.borderColor = '#e2e8f0';
                label.style.background = '#fff';
            }
        });
        this.closest('label').style.borderColor = '#667eea';
        this.closest('label').style.background = '#f8f9ff';
    });
});
</script>

<?php include '../includes/footer.php'; ?>