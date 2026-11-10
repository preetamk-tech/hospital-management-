<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    redirect('auth/login.php');
}

$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();
$doctor_id = $doctor['doctor_id'];

$follow_ups = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'schedule') {
        $appointment_id = (int)$_POST['appointment_id'];
        $patient_id = (int)$_POST['patient_id'];
        $follow_up_date = sanitize($_POST['follow_up_date']);
        $follow_up_time = sanitize($_POST['follow_up_time']);
        $reason = sanitize($_POST['reason']);
        
        $stmt = $pdo->prepare("INSERT INTO follow_ups (appointment_id, patient_id, doctor_id, follow_up_date, follow_up_time, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$appointment_id, $patient_id, $doctor_id, $follow_up_date, $follow_up_time, $reason]);
        $message = 'Follow-up scheduled!';
    } elseif ($action == 'complete') {
        $stmt = $pdo->prepare("UPDATE follow_ups SET status='completed' WHERE follow_up_id=?");
        $stmt->execute([(int)$_POST['follow_up_id']]);
        $message = 'Follow-up marked complete!';
    } elseif ($action == 'cancel_fu') {
        $stmt = $pdo->prepare("UPDATE follow_ups SET status='cancelled' WHERE follow_up_id=?");
        $stmt->execute([(int)$_POST['follow_up_id']]);
        $message = 'Follow-up cancelled!';
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
if ($filter == 'upcoming') {
    $stmt = $pdo->prepare("
        SELECT f.*, p.full_name as patient_name, t.token_number
        FROM follow_ups f
        JOIN patients p ON f.patient_id = p.patient_id
        LEFT JOIN tokens t ON f.appointment_id = t.appointment_id AND t.queue_date = f.follow_up_date
        WHERE f.doctor_id = ? AND f.status = 'scheduled' AND f.follow_up_date >= CURDATE()
        ORDER BY f.follow_up_date, f.follow_up_time
    ");
    $stmt->execute([$doctor_id]);
    $follow_ups = $stmt->fetchAll();
} elseif ($filter == 'past') {
    $stmt = $pdo->prepare("
        SELECT f.*, p.full_name as patient_name, t.token_number
        FROM follow_ups f
        JOIN patients p ON f.patient_id = p.patient_id
        LEFT JOIN tokens t ON f.appointment_id = t.appointment_id AND t.queue_date = f.follow_up_date
        WHERE f.doctor_id = ? AND f.follow_up_date < CURDATE()
        ORDER BY f.follow_up_date DESC
    ");
    $stmt->execute([$doctor_id]);
    $follow_ups = $stmt->fetchAll();
}

$appointments = [];
$stmt = $pdo->prepare("
    SELECT a.appointment_id, p.full_name, t.token_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id AND t.queue_date = CURDATE()
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status = 'completed'
");
$stmt->execute([$doctor_id]);
$completed_today = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-calendar-check"></i> Follow-up Schedule</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;">
        <a href="?filter=upcoming" class="btn <?php echo $filter=='upcoming'?'btn-primary':'btn-secondary'; ?>"><i class="fas fa-clock"></i> Upcoming</a>
        <a href="?filter=past" class="btn <?php echo $filter=='past'?'btn-primary':'btn-secondary'; ?>"><i class="fas fa-history"></i> Past</a>
    </div>
    
    <h3 style="margin-bottom:0.5rem;">Schedule New Follow-up</h3>
    <form method="POST" style="background:#f8f9fa;padding:1rem;border-radius:8px;margin-bottom:1.5rem;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="schedule">
        
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label class="form-label">Patient (Completed Today)</label>
                <select name="patient_id" class="form-control" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($completed_today as $a): ?>
                        <option value="<?php echo $a['appointment_id']; ?>" data-appointment="<?php echo $a['appointment_id']; ?>">
                            <?php echo htmlspecialchars($a['full_name']); ?> - <?php echo $a['token_number']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Follow-up Date *</label>
                <input type="date" name="follow_up_date" class="form-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Time</label>
                <input type="time" name="follow_up_time" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Reason</label>
                <input type="text" name="reason" class="form-control" placeholder="Review, Test results, etc.">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Schedule Follow-up</button>
    </form>
    
    <h3 style="margin-bottom:0.5rem;"><?php echo $filter == 'upcoming' ? 'Upcoming' : 'Past'; ?> Follow-ups</h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Token</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($follow_ups as $f): ?>
                <tr>
                    <td><?php echo htmlspecialchars($f['patient_name']); ?></td>
                    <td><?php echo $f['token_number'] ?: '-'; ?></td>
                    <td><?php echo date('d M Y', strtotime($f['follow_up_date'])); ?></td>
                    <td><?php echo $f['follow_up_time'] ? date('h:i A', strtotime($f['follow_up_time'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($f['reason']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $f['status']=='scheduled'?'warning':($f['status']=='completed'?'success':'danger'); ?>">
                            <?php echo ucfirst($f['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($f['status'] == 'scheduled'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="follow_up_id" value="<?php echo $f['follow_up_id']; ?>">
                            <button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="cancel_fu">
                            <input type="hidden" name="follow_up_id" value="<?php echo $f['follow_up_id']; ?>">
                            <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($follow_ups)): ?>
                <tr><td colspan="7" style="text-align:center;">No follow-ups found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
</style>
