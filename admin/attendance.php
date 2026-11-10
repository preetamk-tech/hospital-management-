<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'];
    
    if ($action == 'clock_in') {
        $user_id = (int)$_POST['user_id'];
        $date = sanitize($_POST['date']);
        $time = date('H:i:s');
        $status = $time > '10:00:00' ? 'late' : 'present';
        
        $stmt = $pdo->prepare("INSERT INTO staff_attendance (user_id, date, clock_in, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE clock_in=VALUES(clock_in), status=VALUES(status)");
        $stmt->execute([$user_id, $date, $time, $status]);
        $message = 'Clock-in recorded!';
    } elseif ($action == 'clock_out') {
        $stmt = $pdo->prepare("UPDATE staff_attendance SET clock_out=?, status=CASE WHEN status='present' THEN 'present' ELSE status END WHERE attendance_id=?");
        $stmt->execute([date('H:i:s'), (int)$_POST['attendance_id']]);
        $message = 'Clock-out recorded!';
    } elseif ($action == 'mark_status') {
        $stmt = $pdo->prepare("UPDATE staff_attendance SET status=? WHERE attendance_id=?");
        $stmt->execute([sanitize($_POST['status']), (int)$_POST['attendance_id']]);
        $message = 'Status updated!';
    }
}

$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';

$sql = "SELECT sa.*, u.full_name, u.email, u.role FROM staff_attendance sa JOIN users u ON sa.user_id = u.user_id WHERE sa.date = ?";
$params = [$date];

if ($filter) { $sql .= " AND u.role = ?"; $params[] = $filter; }

$sql .= " ORDER BY u.role, u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance = $stmt->fetchAll();

$staff = [];
$stmt = $pdo->prepare("SELECT user_id, full_name, role FROM users WHERE role != 'patient' ORDER BY role, full_name");
$stmt->execute();
$staff = $stmt->fetchAll();

$today_stats = $pdo->prepare("SELECT COUNT(*) FROM staff_attendance WHERE date = ?");
$today_stats->execute([$date]);
$marked_count = $today_stats->fetchColumn();
$total_staff = count($staff);
$absent = $total_staff - $marked_count;
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-clock"></i> Staff Attendance</h2>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            <input type="date" value="<?php echo $date; ?>" onchange="window.location.href='?date='+this.value" class="form-control" style="width:auto;">
            <select onchange="if(this.value) window.location.href='?date=<?php echo $date; ?>&role='+this.value" class="form-control" style="width:auto;">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $filter=='admin'?'selected':''; ?>>Admin</option>
                <option value="doctor" <?php echo $filter=='doctor'?'selected':''; ?>>Doctor</option>
                <option value="receptionist" <?php echo $filter=='receptionist'?'selected':''; ?>>Receptionist</option>
            </select>
        </div>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="grid" style="grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background:#f8f9fa;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#667eea;margin:0;font-size:28px;"><?php echo $total_staff; ?></h3>
            <p style="margin:5px 0 0;color:#666;">Total Staff</p>
        </div>
        <div style="background:#f8f9fa;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#48bb78;margin:0;font-size:28px;"><?php echo $marked_count; ?></h3>
            <p style="margin:5px 0 0;color:#666;">Marked</p>
        </div>
        <div style="background:#f8f9fa;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#ed8936;margin:0;font-size:28px;"><?php echo $absent; ?></h3>
            <p style="margin:5px 0 0;color:#666;">Not Marked</p>
        </div>
        <div style="background:#f8f9fa;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#f56565;margin:0;font-size:28px;"><?php echo round(($marked_count/$total_staff)*100); ?>%</h3>
            <p style="margin:5px 0 0;color:#666;">Attendance Rate</p>
        </div>
    </div>
    
    <h3 style="margin-bottom:0.5rem;">Quick Clock-In</h3>
    <form method="POST" style="background:#f8f9fa;padding:1rem;border-radius:8px;margin-bottom:1.5rem;display:flex;gap:0.5rem;align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="clock_in">
        <input type="hidden" name="date" value="<?php echo $date; ?>">
        <div class="form-group" style="flex:1;">
            <label class="form-label">Staff Member</label>
            <select name="user_id" class="form-control" required>
                <option value="">-- Select --</option>
                <?php foreach ($staff as $s): ?>
                    <option value="<?php echo $s['user_id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?> (<?php echo ucfirst($s['role']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-sign-in-alt"></i> Clock In</button>
    </form>
    
    <h3 style="margin-bottom:0.5rem;">Attendance for <?php echo date('d M Y', strtotime($date)); ?></h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Role</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance as $a): 
                    $status_colors = ['present'=>'success','absent'=>'danger','late'=>'warning','half_day'=>'info','on_leave'=>'secondary'];
                    $hours = ($a['clock_in'] && $a['clock_out']) ? round((strtotime($a['clock_out']) - strtotime($a['clock_in'])) / 3600, 1) : '-';
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($a['full_name']); ?></strong></td>
                    <td><?php echo ucfirst($a['role']); ?></td>
                    <td><?php echo $a['clock_in'] ? date('h:i A', strtotime($a['clock_in'])) : '-'; ?></td>
                    <td><?php echo $a['clock_out'] ? date('h:i A', strtotime($a['clock_out'])) : '-'; ?></td>
                    <td><?php echo $hours; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $status_colors[$a['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst(str_replace('_',' ',$a['status'])); ?>
                        </span>
                    </td>
                    <td style="display:flex;gap:0.3rem;">
                        <?php if (!$a['clock_out']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="clock_out">
                            <input type="hidden" name="attendance_id" value="<?php echo $a['attendance_id']; ?>">
                            <button class="btn btn-sm btn-warning"><i class="fas fa-sign-out-alt"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="mark_status">
                            <input type="hidden" name="attendance_id" value="<?php echo $a['attendance_id']; ?>">
                            <select name="status" class="form-control" style="padding:4px 8px;font-size:12px;" onchange="this.form.submit()">
                                <?php foreach (['present','absent','late','half_day','on_leave'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $a['status']==$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attendance)): ?>
                <tr><td colspan="7" style="text-align:center;">No attendance records for this date.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;}
.badge-success{background:#d1fae5;color:#065f46;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-info{background:#dbeafe;color:#1e40af;}.badge-secondary{background:#e5e7eb;color:#374151;}
</style>

<?php include '../includes/footer.php'; ?>
