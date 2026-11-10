<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

$today = date('Y-m-d');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';
    $vq_id = (int)($_POST['vq_id'] ?? 0);

    if ($action === 'call' && $vq_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE virtual_queue SET status = 'called', called_at = NOW() 
            WHERE vq_id = ? AND queue_date = ? AND status = 'waiting'
        ");
        $stmt->execute([$vq_id, $today]);
        setMessage('Virtual queue token called!', 'success');
        redirect('receptionist/virtual_queue.php');
    } elseif ($action === 'cancel' && $vq_id > 0) {
        $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'cancelled' WHERE vq_id = ? AND queue_date = ?");
        $stmt->execute([$vq_id, $today]);
        setMessage('Virtual queue token cancelled.', 'warning');
        redirect('receptionist/virtual_queue.php');
    } elseif ($action === 'no_show' && $vq_id > 0) {
        $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'no_show' WHERE vq_id = ? AND queue_date = ?");
        $stmt->execute([$vq_id, $today]);
        setMessage('Token marked as no-show.', 'warning');
        redirect('receptionist/virtual_queue.php');
    } elseif ($action === 'complete' && $vq_id > 0) {
        $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'completed' WHERE vq_id = ? AND queue_date = ?");
        $stmt->execute([$vq_id, $today]);
        setMessage('Token marked as completed.', 'success');
        redirect('receptionist/virtual_queue.php');
    }
}

// Get all virtual queue tokens for today
$stmt = $pdo->prepare("
    SELECT vq.*, p.full_name as patient_name, p.age, p.phone, p.gender,
           d.full_name as doctor_name, d.specialization
    FROM virtual_queue vq
    JOIN patients p ON vq.patient_id = p.patient_id
    JOIN doctors d ON vq.doctor_id = d.doctor_id
    WHERE vq.queue_date = ?
    ORDER BY 
        CASE WHEN vq.status = 'called' THEN 1
             WHEN vq.status = 'waiting' THEN 2
             WHEN vq.status = 'checked_in' THEN 3
             ELSE 4 END,
        vq.position ASC
");
$stmt->execute([$today]);
$vq_tokens = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND status = 'waiting'");
$stmt->execute([$today]);
$waiting = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND status = 'called'");
$stmt->execute([$today]);
$called = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND status = 'completed'");
$stmt->execute([$today]);
$completed = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND status = 'checked_in'");
$stmt->execute([$today]);
$checked_in = $stmt->fetch()['count'];

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-clock"></i> Virtual Queue Management</h2>
        <div>
            <span class="badge badge-warning">Waiting: <?php echo $waiting; ?></span>
            <span class="badge badge-info">Called: <?php echo $called; ?></span>
            <span class="badge badge-success">Completed: <?php echo $completed; ?></span>
            <span class="badge badge-primary">Checked In: <?php echo $checked_in; ?></span>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Doctor</th>
                    <th>Position</th>
                    <th>Joined At</th>
                    <th>Est. Wait</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vq_tokens as $t): 
                    $colors = ['waiting'=>'warning','called'=>'info','checked_in'=>'primary','completed'=>'success','cancelled'=>'secondary','no_show'=>'danger'];
                ?>
                <tr>
                    <td><strong>#V<?php echo htmlspecialchars($t['token_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['patient_name']); ?></td>
                    <td><?php echo $t['age']; ?></td>
                    <td><?php echo htmlspecialchars($t['phone']); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($t['doctor_name']); ?></td>
                    <td><?php echo $t['position']; ?></td>
                    <td><?php echo date('h:i A', strtotime($t['joined_at'])); ?></td>
                    <td><?php echo $t['estimated_wait_minutes']; ?> min</td>
                    <td>
                        <span class="badge badge-<?php echo $colors[$t['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($t['status'] == 'waiting'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="call">
                                <input type="hidden" name="vq_id" value="<?php echo $t['vq_id']; ?>">
                                <button class="btn btn-sm btn-success"><i class="fas fa-bell"></i> Call</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="vq_id" value="<?php echo $t['vq_id']; ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                            </form>
                        <?php elseif ($t['status'] == 'called'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="no_show">
                                <input type="hidden" name="vq_id" value="<?php echo $t['vq_id']; ?>">
                                <button class="btn btn-sm btn-warning"><i class="fas fa-user-times"></i> No Show</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="vq_id" value="<?php echo $t['vq_id']; ?>">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Complete</button>
                            </form>
                        <?php elseif ($t['status'] == 'checked_in'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="vq_id" value="<?php echo $t['vq_id']; ?>">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Complete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vq_tokens)): ?>
                <tr><td colspan="10" style="text-align:center;">No virtual queue tokens for today.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block;}
.badge-info{background:#dbeafe;color:#1e40af;}.badge-success{background:#d1fae5;color:#065f46;}.badge-secondary{background:#e5e7eb;color:#374151;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-primary{background:#dbeafe;color:#1e40af;}
</style>

<script src="<?php echo SITE_URL; ?>assets/js/websocket.js"></script>
<script>
const ws = new HospitalWS();
ws.connect(<?php echo $_SESSION['user_id']; ?>, 'receptionist');
ws.on('queue_update', function(data) {
    showToast('Queue updated!', 'info');
    setTimeout(() => location.reload(), 1500);
});

if ('Notification' in window && Notification.permission !== 'denied') {
    Notification.requestPermission();
}
</script>

<?php include '../includes/footer.php'; ?>
