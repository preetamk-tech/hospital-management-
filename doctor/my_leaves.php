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

$leaves = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'request') {
        $leave_type = sanitize($_POST['leave_type']);
        $start_date = sanitize($_POST['start_date']);
        $end_date = sanitize($_POST['end_date']);
        $reason = sanitize($_POST['reason']);
        
        if (strtotime($end_date) < strtotime($start_date)) {
            $error = 'End date must be after start date!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO doctor_leaves (doctor_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$doctor_id, $leave_type, $start_date, $end_date, $reason]);
            $message = 'Leave request submitted!';
        }
    } elseif ($action == 'cancel_leave') {
        $stmt = $pdo->prepare("UPDATE doctor_leaves SET status='cancelled' WHERE leave_id=? AND doctor_id=? AND status='pending'");
        $stmt->execute([(int)$_POST['leave_id'], $doctor_id]);
        $message = 'Leave request cancelled!';
    }
}

$stmt = $pdo->prepare("SELECT * FROM doctor_leaves WHERE doctor_id = ? ORDER BY start_date DESC");
$stmt->execute([$doctor_id]);
$leaves = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 700px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-calendar-minus"></i> Request Leave</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="request">
        
        <div class="form-group">
            <label class="form-label">Leave Type *</label>
            <select name="leave_type" class="form-control" required>
                <option value="sick">Sick Leave</option>
                <option value="vacation">Vacation</option>
                <option value="personal">Personal</option>
                <option value="emergency">Emergency</option>
                <option value="other">Other</option>
            </select>
        </div>
        
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Start Date *</label>
                <input type="date" name="start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date *</label>
                <input type="date" name="end_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Reason</label>
            <textarea name="reason" class="form-control" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> My Leave History</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaves as $l): 
                    $days = (strtotime($l['end_date']) - strtotime($l['start_date'])) / 86400 + 1;
                    $status_colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
                ?>
                <tr>
                    <td><?php echo ucfirst($l['leave_type']); ?></td>
                    <td><?php echo date('d M Y', strtotime($l['start_date'])); ?></td>
                    <td><?php echo date('d M Y', strtotime($l['end_date'])); ?></td>
                    <td><?php echo $days; ?></td>
                    <td><?php echo htmlspecialchars($l['reason']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $status_colors[$l['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst($l['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($l['status'] == 'pending'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this leave request?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="cancel_leave">
                            <input type="hidden" name="leave_id" value="<?php echo $l['leave_id']; ?>">
                            <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($leaves)): ?>
                <tr><td colspan="7" style="text-align:center;">No leave requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #e5e7eb; color: #374151; }
</style>

<?php include '../includes/footer.php'; ?>
