<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

// Get today's stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE()");
$stmt->execute();
$today_tokens = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND status = 'waiting'");
$stmt->execute();
$waiting = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND status = 'completed'");
$stmt->execute();
$completed = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND token_prefix = 'S'");
$stmt->execute();
$senior = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND token_prefix = 'E'");
$stmt->execute();
$emergency = $stmt->fetch()['count'];

// Get recent walk-ins
$stmt = $pdo->prepare("
    SELECT p.full_name, p.phone, a.appointment_date, t.token_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id
    WHERE a.appointment_date = CURDATE() AND p.user_id = 0
    ORDER BY a.created_at DESC LIMIT 5
");
$stmt->execute();
$recent_walkins = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="fade-in">
    <h1 style="color: white;">Receptionist Dashboard</h1>
    <p style="color: white; margin-bottom: 2rem;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
    
    <div class="grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info">
                <h3>Total Tokens Today</h3>
                <div class="stat-number"><?php echo $today_tokens; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h3>Waiting</h3>
                <div class="stat-number"><?php echo $waiting; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3>Completed</h3>
                <div class="stat-number"><?php echo $completed; ?></div>
            </div>
        </div>
    </div>
    
    <div class="grid" style="margin-top: 2rem;">
        <div class="box" style="margin-top: 70px;">
            <div class="box-header">
                <h3 class="box-title">Quick Actions</h3>
                <i class="fas fa-bolt"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <a href="walkin_appointment.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Walk-in Appointment</a>
                <a href="emergency_appointment.php" class="btn btn-danger"><i class="fas fa-ambulance"></i> Emergency Booking</a>
                <a href="view_queue.php" class="btn btn-primary"><i class="fas fa-list-ol"></i> View Queue</a>
                <a href="patient_history.php" class="btn btn-primary"><i class="fas fa-history"></i> Patient History</a>
                <a href="reports.php" class="btn btn-primary"><i class="fas fa-file-alt"></i> Daily Report</a>
            </div>
        </div>
        
        <div class="box" style="margin-top: 70px;">
            <div class="box-header">
                <h3 class="box-title">Current Queue Summary</h3>
                <i class="fas fa-chart-pie"></i>
            </div>
            <div style="margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span><i class="fas fa-heart" style="color: #48bb78;"></i> Senior Citizens</span>
                    <span class="badge badge-success"><?php echo $senior; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span><i class="fas fa-ambulance" style="color: #f56565;"></i> Emergency</span>
                    <span class="badge badge-danger"><?php echo $emergency; ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span><i class="fas fa-user"></i> Normal</span>
                    <span class="badge badge-info"><?php echo $today_tokens - $senior - $emergency; ?></span>
                </div>
                <hr style="margin: 1rem 0;">
                <div style="display: flex; justify-content: space-between;">
                    <span><strong>Waiting Patients:</strong></span>
                    <span class="badge badge-warning"><?php echo $waiting; ?></span>
                </div>
            </div>
        </div>
        
        <div class="box" style="margin-top: 70px;">
            <div class="box-header">
                <h3 class="box-title">Recent Walk-ins</h3>
                <i class="fas fa-user-clock"></i>
            </div>
            <?php if (empty($recent_walkins)): ?>
                <p style="text-align: center; color: #a0aec0;">No walk-ins today.</p>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr><th>Patient</th><th>Token</th><th>Phone</th>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_walkins as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['full_name']); ?>
                                <td><strong>#<?php echo htmlspecialchars($row['token_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>