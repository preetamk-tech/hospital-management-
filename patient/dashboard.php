<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get patient record
$stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch();

// Get stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patients_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE p.user_id = ? AND a.status != 'cancelled'
");
$stmt->execute([$user_id]);
$appointments_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM appointments a 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE p.user_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'confirmed'
");
$stmt->execute([$user_id]);
$upcoming_count = $stmt->fetch()['count'];

// Get current token
$stmt = $pdo->prepare("
    SELECT t.token_number, t.status, t.estimated_wait_time, t.position
    FROM tokens t 
    JOIN appointments a ON t.appointment_id = a.appointment_id 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE p.user_id = ? AND t.status = 'waiting' AND t.queue_date = CURDATE()
    ORDER BY t.token_id DESC LIMIT 1
");
$stmt->execute([$user_id]);
$current_token = $stmt->fetch();

// Get recent appointments
$stmt = $pdo->prepare("
    SELECT a.*, d.full_name as doctor_name, p.full_name as patient_name 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.doctor_id 
    JOIN patients p ON a.patient_id = p.patient_id 
    WHERE p.user_id = ? 
    ORDER BY a.created_at DESC LIMIT 5
");
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="fade-in" style="margin-top: 70px;">
    <h1 style="color: white; margin-bottom: 0.5rem;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
    <?php if ($patient && $patient['age'] > 0): ?>
        <p style="color: white; margin-bottom: 2rem;">
            Age: <?php echo $patient['age']; ?> years | 
            Gender: <?php echo $patient['gender']; ?> |
            <?php if ($patient['age'] >= 60): ?>
                <span class="badge badge-success"><i class="fas fa-heart"></i> Senior Citizen Benefit Active</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    
    <div class="grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Family Members</h3>
                <div class="stat-number"><?php echo $patients_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Total Appointments</h3>
                <div class="stat-number"><?php echo $appointments_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3>Upcoming</h3>
                <div class="stat-number"><?php echo $upcoming_count; ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info">
                <h3>Current Token</h3>
                <div class="stat-number"><?php echo $current_token ? $current_token['token_number'] : 'None'; ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($current_token && $current_token['status'] == 'waiting'): ?>
        <div class="box" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;margin:20px auto;">
            <div class="box-header" style="border-bottom-color: rgba(255,255,255,0.3);">
                <h3 class="box-title" style="color: white;">Your Current Token</h3>
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div style="text-align: center; padding: 1rem;">
                <div class="token-number" >#<?php echo $current_token['token_number']; ?></div>
                <p>Position: <?php echo $current_token['position']; ?> in queue</p>
                <p>Estimated Wait: <strong id="timer"><?php echo $current_token['estimated_wait_time']; ?> min</strong></p>
                <a href="my_token.php" class="btn btn-light" style="margin-top: 1rem; background: white; color: #667eea;">Track Status</a>
            </div>
        </div>
        
        <script>
            let waitTime = <?php echo $current_token['estimated_wait_time']; ?> * 60;
            setInterval(() => {
                if(waitTime > 0) {
                    waitTime--;
                    let mins = Math.floor(waitTime / 60);
                    let secs = waitTime % 60;
                    document.getElementById('timer').innerHTML = `${mins}:${secs.toString().padStart(2,'0')} min`;
                }
            }, 1000);
        </script>
    <?php endif; ?>
    
    <div class="grid" style="margin-top:2rem; ">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">Quick Actions</h3>
                <i class="fas fa-bolt"></i>
            </div>
            <div style=" display: grid;grid-template-columns: repeat(2, 1fr);gap: 30px; padding:60px 0;">
                <a href="add_patient.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Add Family Member</a>
                <a href="book_appointment.php" class="btn btn-success"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
                <a href="my_token.php" class="btn btn-info"><i class="fas fa-ticket-alt"></i> My Token</a>
                <a href="medical_history.php" class="btn btn-info"><i class="fas fa-notes-medical"></i> Medical History</a>
                <a href="my_appointments.php" class="btn btn-primary"><i class="fas fa-list"></i> View Appointments</a>
                <a href="my_patients.php" class="btn btn-primary"><i class="fas fa-users"></i> Manage Family</a>
                <a href="ambulance.php" class="btn btn-danger"><i class="fas fa-ambulance"></i> Book Ambulance</a>
                <a href="virtual_queue_join.php" class="btn btn-warning"><i class="fas fa-clock"></i> Join Virtual Queue</a>
            </div>
        </div>
        
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">Recent Appointments</h3>
                <i class="fas fa-history"></i>
            </div>
            <?php if (!empty($recent)): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Status</th>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                                <td><span class="badge badge-<?php echo $row['status'] == 'confirmed' ? 'success' : 'warning'; ?>"><?php echo $row['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #a0aec0;">No appointments yet.</p>
            <?php endif; ?>
            <a href="book_appointment.php" class="btn btn-primary " style="margin-top: 1rem;"><i class="fas fa-calendar-plus"></i>Book New Appointment</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>