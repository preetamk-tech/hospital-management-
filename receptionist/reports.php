<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

// Get tokens for selected date
$stmt = $pdo->prepare("
    SELECT t.token_number, t.token_prefix, p.full_name, p.age, p.phone,
           d.full_name as doctor_name, t.status, t.created_at, t.completed_at
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE t.queue_date = ?
    ORDER BY t.position ASC
");
$stmt->execute([$date]);
$tokens = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = ?");
$stmt->execute([$date]);
$total = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = ? AND token_prefix = 'S'");
$stmt->execute([$date]);
$senior = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = ? AND token_prefix = 'E'");
$stmt->execute([$date]);
$emergency = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = ? AND status = 'completed'");
$stmt->execute([$date]);
$completed = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = ? AND status = 'cancelled'");
$stmt->execute([$date]);
$cancelled = $stmt->fetch()['count'];

// Average wait time
$stmt = $pdo->prepare("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_wait 
    FROM tokens 
    WHERE queue_date = ? AND status = 'completed' AND completed_at IS NOT NULL
");
$stmt->execute([$date]);
$avg_wait = round($stmt->fetch()['avg_wait'] ?? 0);

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title">Daily Report</h2>
        <i class="fas fa-file-alt"></i>
    </div>
    
    <form method="GET" style="margin-bottom: 2rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <input type="date" name="date" class="form-control" value="<?php echo $date; ?>" style="flex: 1;">
            <button type="submit" class="btn btn-primary">View Report</button>
            <button type="button" class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </form>
    
    <div class="grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info">
                <h3>Total Tokens</h3>
                <div class="stat-number"><?php echo $total; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-info">
                <h3>Senior Citizens</h3>
                <div class="stat-number"><?php echo $senior; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ambulance"></i></div>
            <div class="stat-info">
                <h3>Emergency</h3>
                <div class="stat-number"><?php echo $emergency; ?></div>
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
    
    <div class="grid" style="margin-top: 1rem;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <h3>Cancelled</h3>
                <div class="stat-number"><?php echo $cancelled; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h3>Avg. Wait Time</h3>
                <div class="stat-number"><?php echo $avg_wait; ?> min</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <h3>Completion Rate</h3>
                <div class="stat-number"><?php echo $total > 0 ? round(($completed / $total) * 100) : 0; ?>%</div>
            </div>
        </div>
    </div>
    
    <h3 style="margin-top: 2rem;">Token Details</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Doctor</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $row): ?>
                <tr>
                    <td><strong>#<?php echo htmlspecialchars($row['token_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo $row['age']; ?></td>
                    <td><?php echo $row['phone']; ?></td>
                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                    <td>
                        <?php if ($row['token_prefix'] == 'S'): ?>
                            <span class="badge badge-success">Senior</span>
                        <?php elseif ($row['token_prefix'] == 'E'): ?>
                            <span class="badge badge-danger">Emergency</span>
                        <?php else: ?>
                            <span class="badge badge-info">Normal</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $row['status'] == 'completed' ? 'success' : ($row['status'] == 'cancelled' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 2rem; padding: 1rem; background: #f7fafc; border-radius: 12px;">
        <h4>Report Summary</h4>
        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($date)); ?></p>
        <p><strong>Total Patients:</strong> <?php echo $total; ?></p>
        <p><strong>Senior Citizens Served:</strong> <?php echo $senior; ?></p>
        <p><strong>Emergency Cases:</strong> <?php echo $emergency; ?></p>
        <p><strong>Average Wait Time:</strong> <?php echo $avg_wait; ?> minutes</p>
        <p><strong>Report Generated:</strong> <?php echo date('d M Y h:i A'); ?></p>
    </div>
</div>

<style media="print">
    .btn, .stat-card, .card-header, .nav-links, .user-menu, footer {
        display: none !important;
    }
    .card {
        box-shadow: none;
        padding: 0;
    }
    body {
        background: white;
    }
</style>

<?php include '../includes/footer.php'; ?>