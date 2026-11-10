<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

// Get all tokens for today
$stmt = $pdo->prepare("
    SELECT t.token_id, t.token_number, t.token_prefix, p.full_name as patient_name, 
           p.age, p.phone, d.full_name as doctor_name, t.status, t.estimated_wait_time,
           t.position, t.created_at
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE t.queue_date = CURDATE()
    ORDER BY 
        CASE WHEN t.token_prefix = 'E' THEN 1
             WHEN t.token_prefix = 'S' THEN 2
             ELSE 3 END,
        t.position ASC
");
$stmt->execute();
$tokens = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND status = 'waiting'");
$stmt->execute();
$waiting = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND status = 'called'");
$stmt->execute();
$called = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tokens WHERE queue_date = CURDATE() AND status = 'completed'");
$stmt->execute();
$completed = $stmt->fetch()['count'];

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title">Today's Queue</h2>
        <div>
            <span class="badge badge-warning">Waiting: <?php echo $waiting; ?></span>
            <span class="badge badge-info">Called: <?php echo $called; ?></span>
            <span class="badge badge-success">Completed: <?php echo $completed; ?></span>
        </div>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Doctor</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Est. Wait</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $row): ?>
                <tr>
                    <td><strong>#<?php echo htmlspecialchars($row['token_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo $row['age']; ?></td>
                    <td><?php echo $row['phone']; ?></td>
                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
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
                        <span class="badge badge-<?php echo $row['status'] == 'waiting' ? 'warning' : ($row['status'] == 'called' ? 'info' : 'success'); ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $row['estimated_wait_time']; ?> min</td>
                    <td>
                        <?php if ($row['status'] == 'waiting'): ?>
                            <button class="btn btn-primary" style="padding: 0.3rem 0.8rem;" onclick="callToken(<?php echo $row['token_id']; ?>)">
                                <i class="fas fa-bell"></i> Call
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?php echo SITE_URL; ?>assets/js/websocket.js"></script>
<script>
function callToken(tokenId) {
    if (confirm('Call this token?')) {
        $.ajax({
            url: '../doctor/call_next.php',
            data: { call: tokenId },
            method: 'POST',
            success: function() {
                showToast('Token called successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        });
    }
}

// Connect to WebSocket for real-time queue updates
const ws = new HospitalWS();
ws.connect(<?php echo $_SESSION['user_id']; ?>, 'receptionist');
ws.on('queue_update', function(data) {
    if (data.tokens) {
        showToast('Queue updated!', 'info');
        setTimeout(() => location.reload(), 1500);
    }
});
ws.on('token_update', function(data) {
    showToast('Token #' + data.token_number + ' status changed: ' + data.status, 'info');
    setTimeout(() => location.reload(), 2000);
});

if ('Notification' in window && Notification.permission !== 'denied') {
    Notification.requestPermission();
}
</script>

<?php include '../includes/footer.php'; ?>