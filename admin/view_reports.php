<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Get date filter
$start_date = isset($_GET['start']) ? sanitize($_GET['start']) : date('Y-m-01');
$end_date = isset($_GET['end']) ? sanitize($_GET['end']) : date('Y-m-d');

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$total_appointments = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'");
$stmt->execute([$start_date, $end_date]);
$completed = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'cancelled'");
$stmt->execute([$start_date, $end_date]);
$cancelled = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date BETWEEN ? AND ? AND token_prefix = 'S'");
$stmt->execute([$start_date, $end_date]);
$senior_tokens = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date BETWEEN ? AND ? AND token_prefix = 'E'");
$stmt->execute([$start_date, $end_date]);
$emergency_tokens = $stmt->fetch()['count'];

// Doctor performance
$stmt = $pdo->prepare("
    SELECT d.full_name, d.doctor_id,
           COUNT(a.appointment_id) as total,
           SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
           AVG(f.rating) as avg_rating
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id AND a.appointment_date BETWEEN ? AND ?
    LEFT JOIN feedback f ON a.appointment_id = f.appointment_id
    GROUP BY d.doctor_id
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$doctors = $stmt->fetchAll();

// Daily trend
$stmt = $pdo->prepare("
    SELECT appointment_date, COUNT(*) as count
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date
");
$stmt->execute([$start_date, $end_date]);
$daily_trend = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title">Reports</h2>
        <i class="fas fa-chart-bar"></i>
    </div>
    
    <form method="GET" style="margin-bottom: 2rem;">
        <div class="grid" style="grid-template-columns: 1fr 1fr auto auto;">
            <input type="date" name="start" class="form-control" value="<?php echo $start_date; ?>">
            <input type="date" name="end" class="form-control" value="<?php echo $end_date; ?>">
            <button type="submit" class="btn btn-primary">Filter</button>
            <button type="button" class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        </div>
    </form>
    
    <div class="grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h3>Total Appointments</h3>
                <div class="stat-number"><?php echo number_format($total_appointments); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3>Completed</h3>
                <div class="stat-number"><?php echo number_format($completed); ?></div>
                <small><?php echo $total_appointments > 0 ? round(($completed/$total_appointments)*100) : 0; ?>%</small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <h3>Cancelled</h3>
                <div class="stat-number"><?php echo number_format($cancelled); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-info">
                <h3>Senior Tokens</h3>
                <div class="stat-number"><?php echo number_format($senior_tokens); ?></div>
            </div>
        </div>
    </div>
    
    <h3 style="margin-top: 2rem;">Daily Trend</h3>
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr><th>Date</th><th>Appointments</th><th>Progress</th></tr>
            </thead>
            <tbody>
                <?php 
                $max_count = 0;
                foreach ($daily_trend as $trend) {
                    if ($trend['count'] > $max_count) $max_count = $trend['count'];
                }
                foreach ($daily_trend as $trend): 
                    $width = $max_count > 0 ? ($trend['count'] / $max_count) * 100 : 0;
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($trend['appointment_date'])); ?></td>
                    <td><?php echo $trend['count']; ?></td>
                    <td style="width: 200px;">
                        <div style="background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo $width; ?>%; background: linear-gradient(135deg, #667eea, #764ba2); height: 20px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <h3 style="margin-top: 2rem;">Doctor Performance</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Doctor</th><th>Total Appointments</th><th>Completed</th><th>Completion Rate</th><th>Avg Rating</th></tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $row): ?>
                <tr>
                    <td>Dr. <?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['completed']; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $row['total'] > 0 && ($row['completed']/$row['total'])*100 >= 80 ? 'success' : 'warning'; ?>">
                            <?php echo $row['total'] > 0 ? round(($row['completed']/$row['total'])*100) : 0; ?>%
                        </span>
                    </td>
                    <td>
                        <?php if ($row['avg_rating']): ?>
                        <span class="badge badge-info"><?php echo round($row['avg_rating'], 1); ?> ★</span>
                        <?php else: ?>
                        <span class="badge badge-warning">No ratings</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 2rem; padding: 1rem; background: #f7fafc; border-radius: 12px;">
        <h4>Summary</h4>
        <p>Period: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
        <p>Total Appointments: <?php echo $total_appointments; ?></p>
        <p>Average Daily: <?php echo count($daily_trend) > 0 ? round($total_appointments / count($daily_trend), 1) : 0; ?></p>
        <p>Emergency Cases: <?php echo $emergency_tokens; ?></p>
    </div>
</div>

<script>
    // Print functionality
    window.print = function() {
        window.print();
    };
</script>

<?php include '../includes/footer.php'; ?>