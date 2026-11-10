<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$period = isset($_GET['period']) ? $_GET['period'] : '30';
$start = date('Y-m-d', strtotime("-$period days"));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE scheduled_at >= ?");
$stmt->execute([$start]);
$total = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE scheduled_at >= ? AND status = 'completed'");
$stmt->execute([$start]);
$completed = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE scheduled_at >= ? AND status = 'cancelled'");
$stmt->execute([$start]);
$cancelled = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)), 1), 0) FROM consultations WHERE scheduled_at >= ? AND ended_at IS NOT NULL");
$stmt->execute([$start]);
$avg_duration = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT d.full_name as doctor_name, dep.department_name,
        COUNT(c.consultation_id) as total,
        COUNT(CASE WHEN c.status='completed' THEN 1 END) as completed,
        COUNT(CASE WHEN c.status='cancelled' THEN 1 END) as cancelled,
        COALESCE(ROUND(AVG(c.duration_minutes), 0), 0) as avg_duration
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    LEFT JOIN consultations c ON d.doctor_id = c.doctor_id AND c.scheduled_at >= ?
    GROUP BY d.doctor_id
    ORDER BY total DESC
");
$stmt->execute([$start]);
$doctor_stats = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT DATE(c.scheduled_at) as date, COUNT(*) as total,
        COUNT(CASE WHEN c.status='completed' THEN 1 END) as completed
    FROM consultations c WHERE c.scheduled_at >= ?
    GROUP BY DATE(c.scheduled_at) ORDER BY date
");
$stmt->execute([$start]);
$daily = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.*, p.full_name as patient_name, d.full_name as doctor_name, dep.department_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.patient_id
    JOIN doctors d ON c.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    WHERE c.scheduled_at >= ?
    ORDER BY c.scheduled_at DESC LIMIT 50
");
$stmt->execute([$start]);
$recent = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-video"></i> Telemedicine Reports</h2>
        <div style="display:flex;gap:0.5rem;">
            <?php foreach (['7'=>'7 Days','30'=>'30 Days','90'=>'90 Days'] as $d=>$l): ?>
            <a href="?period=<?php echo $d; ?>" class="btn <?php echo $period==$d?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;"><?php echo $l; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;text-align:center;">
            <h2 style="margin:0;font-size:32px;"><?php echo $total; ?></h2>
            <p style="margin:5px 0 0;opacity:0.9;">Total Consultations</p>
        </div>
        <div style="background:linear-gradient(135deg,#48bb78,#38a169);color:#fff;padding:20px;border-radius:12px;text-align:center;">
            <h2 style="margin:0;font-size:32px;"><?php echo $completed; ?></h2>
            <p style="margin:5px 0 0;opacity:0.9;">Completed</p>
        </div>
        <div style="background:linear-gradient(135deg,#f56565,#e53e3e);color:#fff;padding:20px;border-radius:12px;text-align:center;">
            <h2 style="margin:0;font-size:32px;"><?php echo $cancelled; ?></h2>
            <p style="margin:5px 0 0;opacity:0.9;">Cancelled</p>
        </div>
        <div style="background:linear-gradient(135deg,#ed8936,#dd6b20);color:#fff;padding:20px;border-radius:12px;text-align:center;">
            <h2 style="margin:0;font-size:32px;"><?php echo $avg_duration; ?> min</h2>
            <p style="margin:5px 0 0;opacity:0.9;">Avg Duration</p>
        </div>
    </div>
    
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
            <h4>Daily Consultations</h4>
            <canvas id="dailyChart" height="200"></canvas>
        </div>
        <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
            <h4>Completion Rate</h4>
            <canvas id="completionChart" height="200"></canvas>
        </div>
    </div>
    
    <h4 style="margin-bottom:0.5rem;">Doctor-wise Statistics</h4>
    <div class="table-container">
        <table>
            <thead><tr><th>Doctor</th><th>Department</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Avg Duration</th></tr></thead>
            <tbody>
                <?php foreach ($doctor_stats as $ds): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ds['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($ds['department_name']); ?></td>
                    <td><?php echo $ds['total']; ?></td>
                    <td><?php echo $ds['completed']; ?></td>
                    <td><?php echo $ds['cancelled']; ?></td>
                    <td><?php echo $ds['avg_duration']; ?> min</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <h4 style="margin:1.5rem 0 0.5rem;">Recent Consultations</h4>
    <div class="table-container">
        <table>
            <thead><tr><th>Patient</th><th>Doctor</th><th>Department</th><th>Scheduled</th><th>Duration</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r): 
                    $colors = ['scheduled'=>'info','active'=>'success','completed'=>'secondary','cancelled'=>'danger','missed'=>'warning'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($r['doctor_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['department_name']); ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($r['scheduled_at'])); ?></td>
                    <td><?php echo $r['duration_minutes']; ?> min</td>
                    <td><span class="badge badge-<?php echo $colors[$r['status']] ?? 'secondary'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const dates = <?php echo json_encode(array_map(fn($d)=>date('d M',strtotime($d['date'])), $daily)); ?>;
const totals = <?php echo json_encode(array_column($daily, 'total')); ?>;
const completed = <?php echo json_encode(array_column($daily, 'completed')); ?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dates,
        datasets: [
            { label: 'Total', data: totals, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', fill: true, tension: 0.4 },
            { label: 'Completed', data: completed, borderColor: '#48bb78', fill: false, tension: 0.4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('completionChart'), {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Cancelled', 'Scheduled'],
        datasets: [{ data: [<?php echo $completed; ?>, <?php echo $cancelled; ?>, <?php echo $total - $completed - $cancelled; ?>], backgroundColor: ['#48bb78','#f56565','#ed8936'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<style>
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block;}
.badge-info{background:#dbeafe;color:#1e40af;}.badge-success{background:#d1fae5;color:#065f46;}.badge-secondary{background:#e5e7eb;color:#374151;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-warning{background:#fef3c7;color:#92400e;}
</style>

<?php include '../includes/footer.php'; ?>
