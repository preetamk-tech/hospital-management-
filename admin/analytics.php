<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$period = isset($_GET['period']) ? $_GET['period'] : '7';
$start_date = date('Y-m-d', strtotime("-$period days"));
$end_date = date('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM doctors");
$total_doctors = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$period_appointments = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE queue_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$period_tokens = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE queue_date BETWEEN ? AND ? AND status = 'completed'");
$stmt->execute([$start_date, $end_date]);
$completed = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE is_occupied = 1");
$occupied_beds = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE is_occupied = 0");
$free_beds = $stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM invoices WHERE status = 'paid' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$revenue = $stmt->fetchColumn();

$daily_tokens = [];
$stmt = $pdo->prepare("SELECT queue_date as date, COUNT(*) as total FROM tokens WHERE queue_date BETWEEN ? AND ? GROUP BY queue_date ORDER BY queue_date");
$stmt->execute([$start_date, $end_date]);
$daily_tokens = $stmt->fetchAll();

$daily_completed = [];
$stmt = $pdo->prepare("SELECT queue_date as date, COUNT(*) as total FROM tokens WHERE queue_date BETWEEN ? AND ? AND status = 'completed' GROUP BY queue_date ORDER BY queue_date");
$stmt->execute([$start_date, $end_date]);
$daily_completed = $stmt->fetchAll();

$doctor_performance = [];
$stmt = $pdo->prepare("
    SELECT d.full_name, dep.department_name,
        COUNT(t.token_id) as total_patients,
        COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN t.status = 'waiting' THEN 1 END) as waiting,
        COALESCE(AVG(CASE WHEN t.completed_at IS NOT NULL AND t.called_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, t.called_at, t.completed_at) END), 0) as avg_time_min
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    LEFT JOIN tokens t ON d.doctor_id = t.doctor_id AND t.queue_date BETWEEN ? AND ?
    GROUP BY d.doctor_id
    ORDER BY completed DESC
");
$stmt->execute([$start_date, $end_date]);
$doctor_performance = $stmt->fetchAll();

$department_stats = [];
$stmt = $pdo->prepare("
    SELECT dep.department_name, COUNT(t.token_id) as total,
        COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed
    FROM departments dep
    LEFT JOIN doctors d ON dep.department_id = d.department_id
    LEFT JOIN tokens t ON d.doctor_id = t.doctor_id AND t.queue_date BETWEEN ? AND ?
    GROUP BY dep.department_id
    ORDER BY total DESC
");
$stmt->execute([$start_date, $end_date]);
$department_stats = $stmt->fetchAll();

$completion_rate = $period_tokens > 0 ? round(($completed / $period_tokens) * 100, 1) : 0;
$avg_wait = $pdo->prepare("SELECT COALESCE(AVG(estimated_wait_time), 0) FROM tokens WHERE queue_date BETWEEN ? AND ? AND status = 'waiting'");
$avg_wait->execute([$start_date, $end_date]);
$avg_wait_time = round($avg_wait->fetchColumn());
?>

<?php include '../includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-chart-line"></i> Analytics Dashboard</h2>
        <div style="display:flex;gap:0.5rem;">
            <?php foreach (['7'=>'Last 7 Days','30'=>'Last 30 Days','90'=>'Last 90 Days'] as $d=>$l): ?>
            <a href="?period=<?php echo $d; ?>" class="btn <?php echo $period==$d?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;"><?php echo $l; ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="stat-card" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Total Patients</p>
            <h2 style="margin:5px 0 0;font-size:32px;"><?php echo $total_patients; ?></h2>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#48bb78,#38a169);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Doctors</p>
            <h2 style="margin:5px 0 0;font-size:32px;"><?php echo $total_doctors; ?></h2>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#ed8936,#dd6b20);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Appointments (Period)</p>
            <h2 style="margin:5px 0 0;font-size:32px;"><?php echo $period_appointments; ?></h2>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#f56565,#e53e3e);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Completion Rate</p>
            <h2 style="margin:5px 0 0;font-size:32px;"><?php echo $completion_rate; ?>%</h2>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#4299e1,#3182ce);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Occupied Beds</p>
            <h2 style="margin:5px 0 0;font-size:32px;"><?php echo $occupied_beds; ?> <small style="font-size:14px;">/ <?php echo $occupied_beds + $free_beds; ?></small></h2>
        </div>
        <div class="stat-card" style="background:linear-gradient(135deg,#9f7aea,#805ad5);color:#fff;padding:20px;border-radius:12px;">
            <p style="margin:0;font-size:14px;opacity:0.9;">Revenue</p>
            <h2 style="margin:5px 0 0;font-size:32px;">₹<?php echo number_format($revenue); ?></h2>
        </div>
    </div>
    
    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
            <h4>Daily Tokens</h4>
            <canvas id="dailyChart" height="200"></canvas>
        </div>
        <div style="background:#f8f9fa;padding:20px;border-radius:12px;">
            <h4>Department Stats</h4>
            <canvas id="deptChart" height="200"></canvas>
        </div>
    </div>
    
    <div style="margin-top:1.5rem;">
        <h4 style="margin-bottom:0.5rem;">Doctor Performance</h4>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Doctor</th><th>Department</th><th>Total</th><th>Completed</th><th>Waiting</th><th>Avg Time (min)</th><th>Efficiency</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($doctor_performance as $dp): 
                        $eff = $dp['total_patients'] > 0 ? round(($dp['completed']/$dp['total_patients'])*100) : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dp['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($dp['department_name']); ?></td>
                        <td><?php echo $dp['total_patients']; ?></td>
                        <td><?php echo $dp['completed']; ?></td>
                        <td><?php echo $dp['waiting']; ?></td>
                        <td><?php echo round($dp['avg_time_min']); ?></td>
                        <td>
                            <div style="background:#e2e8f0;border-radius:10px;height:8px;width:100px;">
                                <div style="background:<?php echo $eff>70?'#48bb78':($eff>40?'#ed8936':'#f56565'); ?>;border-radius:10px;height:8px;width:<?php echo $eff; ?>%;"></div>
                            </div>
                            <small><?php echo $eff; ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const dailyTokens = <?php echo json_encode(array_column($daily_tokens, 'total')); ?>;
const dailyDates = <?php echo json_encode(array_map(fn($d)=>date('d M', strtotime($d['date'])), $daily_tokens)); ?>;
const dailyComp = <?php echo json_encode(array_column($daily_completed, 'total')); ?>;

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: dailyDates,
        datasets: [
            { label: 'Total Tokens', data: dailyTokens, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', fill: true, tension: 0.4 },
            { label: 'Completed', data: dailyComp, borderColor: '#48bb78', backgroundColor: 'rgba(72,187,120,0.1)', fill: true, tension: 0.4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

const deptNames = <?php echo json_encode(array_column($department_stats, 'department_name')); ?>;
const deptTotals = <?php echo json_encode(array_column($department_stats, 'total')); ?>;

new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: deptNames,
        datasets: [{ data: deptTotals, backgroundColor: ['#667eea','#48bb78','#ed8936','#f56565','#4299e1','#9f7aea','#ecc94b','#fc8181'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
});
</script>

<?php include '../includes/footer.php'; ?>
