<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    redirect('auth/login.php');
}

// Get doctor info by user_id
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    setMessage("Doctor profile not found!", "error");
    redirect('auth/logout.php');
}

$doctor_id = $doctor['doctor_id'];

// Get today's waiting patients
$stmt = $pdo->prepare("
    SELECT t.token_id, t.token_number, t.token_prefix, p.full_name as patient_name, 
           p.age, p.phone, t.position
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND t.queue_date = CURDATE() AND t.status = 'waiting'
    ORDER BY 
        CASE WHEN t.token_prefix = 'E' THEN 1
             WHEN t.token_prefix = 'S' THEN 2
             ELSE 3 END,
        t.position ASC
");
$stmt->execute([$doctor_id]);
$waiting_patients = $stmt->fetchAll();

// Get currently called token
$stmt = $pdo->prepare("
    SELECT t.token_number, p.full_name, p.age, p.phone
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND t.queue_date = CURDATE() AND t.status = 'called'
");
$stmt->execute([$doctor_id]);
$called = $stmt->fetch();

// Get completed today count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as c FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    WHERE a.doctor_id = ? AND t.queue_date = CURDATE() AND t.status = 'completed'
");
$stmt->execute([$doctor_id]);
$completed_today = $stmt->fetch()['c'];

// Get total waiting count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as c FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    WHERE a.doctor_id = ? AND t.queue_date = CURDATE() AND t.status = 'waiting'
");
$stmt->execute([$doctor_id]);
$total_waiting = $stmt->fetch()['c'];

// Get average service time for last 7 days
$stmt = $pdo->prepare("
    SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, called_at, completed_at)), 1), 0) as avg_time
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    WHERE a.doctor_id = ? AND t.completed_at IS NOT NULL AND t.queue_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$doctor_id]);
$avg_service_time = $stmt->fetch()['avg_time'];

include '../includes/header.php';
?>

<div class="fade-in" style="margin-top:70px;">
    <h1 style="color: white;">Doctor Dashboard</h1>
    <p style="color: white;">Welcome, Dr. <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
    
    <div class="grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3>Waiting Patients</h3>
                <div class="stat-number"><?php echo $total_waiting; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3>Completed Today</h3>
                <div class="stat-number"><?php echo $completed_today; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
            <div class="stat-info">
                <h3>Avg Service Time</h3>
                <div class="stat-number"><?php echo $avg_service_time; ?> min</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3>Current Time</h3>
                <div class="stat-number" style="font-size: 1.5rem;"><?php echo date('h:i A'); ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($called): ?>
        <div class="box" style="background: linear-gradient(135deg, #fed7d7, #fff5f5); border: 2px solid #f56565; margin-top:70px;">
            <div style="text-align: center;">
                <i class="fas fa-bell" style="font-size: 2rem; color: #f56565;"></i>
                <h2 style="color: #742a2a;">Currently Serving</h2>
                <div style="font-size: 5rem; font-weight: bold; color: #742a2a;">#<?php echo htmlspecialchars($called['token_number']); ?></div>
                <p style="font-size: 1.2rem;"><strong><?php echo htmlspecialchars($called['full_name']); ?></strong></p>
                <p>Age: <?php echo $called['age']; ?> | Phone: <?php echo $called['phone']; ?></p>
                <a href="call_next.php?complete=<?php echo $called['token_number']; ?>" class="btn btn-success" style="margin-top: 1rem;">
                    <i class="fas fa-check-circle"></i> Mark as Completed
                </a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="box" style="margin-top:70px;">
        <div class="box-header">
            <h3 class="box-title">Today's Queue</h3>
            <i class="fas fa-list-ol"></i>
        </div>
        
        <?php if (empty($waiting_patients)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #48bb78;"></i>
                <p style="margin-top: 1rem;">No patients waiting. Great job!</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>Token</th><th>Patient</th><th>Age</th><th>Phone</th><th>Priority</th><th>Action</th>
                    </thead>
                    <tbody>
                        <?php foreach ($waiting_patients as $row): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($row['token_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo $row['age']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td>
                                <?php if ($row['token_prefix'] == 'S'): ?>
                                    <span class="badge badge-success"><i class="fas fa-heart"></i> Senior</span>
                                <?php elseif ($row['token_prefix'] == 'E'): ?>
                                    <span class="badge badge-danger"><i class="fas fa-ambulance"></i> Emergency</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="call_next.php?call=<?php echo $row['token_id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">
                                    <i class="fas fa-bell"></i> Call Next
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>