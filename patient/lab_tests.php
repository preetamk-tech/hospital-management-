<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT patient_id, full_name FROM patients WHERE user_id = ? ORDER BY full_name");
$stmt->execute([$user_id]);
$my_patients = $stmt->fetchAll();

$tests = [];
$stmt = $pdo->prepare("SELECT * FROM lab_tests WHERE is_active = 1 ORDER BY department, test_name");
$stmt->execute();
$tests = $stmt->fetchAll();

$bookings = [];
$stmt = $pdo->prepare("
    SELECT b.*, lt.test_name, lt.price, p.full_name as patient_name
    FROM lab_bookings b
    JOIN lab_tests lt ON b.test_id = lt.test_id
    JOIN patients p ON b.patient_id = p.patient_id
    WHERE p.user_id = ?
    ORDER BY b.booking_date DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_id = (int)$_POST['patient_id'];
    $test_id = (int)$_POST['test_id'];
    $scheduled_date = sanitize($_POST['scheduled_date']);
    $notes = sanitize($_POST['notes']);
    
    $stmt = $pdo->prepare("INSERT INTO lab_bookings (patient_id, test_id, booking_date, scheduled_date, notes) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([$patient_id, $test_id, $scheduled_date, $notes]);
    $message = 'Lab test booked successfully!';
    
    $stmt = $pdo->prepare("
        SELECT b.*, lt.test_name, lt.price, p.full_name as patient_name
        FROM lab_bookings b
        JOIN lab_tests lt ON b.test_id = lt.test_id
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE p.user_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filtered_bookings = $bookings;
if ($filter != 'all') {
    $filtered_bookings = array_filter($bookings, function($b) use ($filter) { return $b['status'] == $filter; });
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-vials"></i> Book Lab Test</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label class="form-label">Patient *</label>
                <select name="patient_id" class="form-control" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($my_patients as $p): ?>
                        <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Test *</label>
                <select name="test_id" class="form-control" required>
                    <option value="">-- Select Test --</option>
                    <?php foreach ($tests as $t): ?>
                        <option value="<?php echo $t['test_id']; ?>">
                            <?php echo htmlspecialchars($t['test_name']); ?> - ₹<?php echo number_format($t['price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Preferred Date *</label>
                <input type="date" name="scheduled_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes</label>
            <input type="text" name="notes" class="form-control" placeholder="Any specific instructions">
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Book Test</button>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-clipboard-list"></i> My Lab Bookings</h2>
    </div>
    
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;">
        <?php foreach (['all', 'pending', 'confirmed', 'completed', 'cancelled'] as $f): ?>
        <a href="?filter=<?php echo $f; ?>" class="btn <?php echo $filter==$f?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;">
            <?php echo ucfirst($f); ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Test</th>
                    <th>Price</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th>Results</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_bookings as $b): 
                    $status_colors = ['pending' => 'warning', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($b['test_name']); ?></td>
                    <td>₹<?php echo number_format($b['price']); ?></td>
                    <td><?php echo date('d M Y', strtotime($b['scheduled_date'])); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $status_colors[$b['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($b['status'] == 'completed'): ?>
                        <a href="?result=<?php echo $b['booking_id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-eye"></i> View</a>
                        <?php else: ?>
                        <span style="color:#999;">Awaiting</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($filtered_bookings)): ?>
                <tr><td colspan="6" style="text-align:center;">No lab bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($_GET['result'])): 
    $stmt = $pdo->prepare("
        SELECT lr.*, lt.test_name, p.full_name
        FROM lab_results lr
        JOIN lab_tests lt ON lr.test_id = lt.test_id
        JOIN patients p ON lr.patient_id = p.patient_id
        WHERE lr.booking_id = ?
    ");
    $stmt->execute([(int)$_GET['result']]);
    $result = $stmt->fetch();
    if ($result):
?>
<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-file-medical"></i> Test Results</h2>
    </div>
    <div style="padding:15px;background:#f8f9fa;border-radius:8px;">
        <p><strong>Patient:</strong> <?php echo htmlspecialchars($result['full_name']); ?></p>
        <p><strong>Test:</strong> <?php echo htmlspecialchars($result['test_name']); ?></p>
        <p><strong>Reported:</strong> <?php echo date('d M Y, h:i A', strtotime($result['reported_at'])); ?></p>
        <div style="margin-top:15px;">
            <h4>Results:</h4>
            <pre style="background:#fff;padding:15px;border-radius:5px;white-space:pre-wrap;"><?php echo htmlspecialchars($result['result_data']); ?></pre>
        </div>
        <?php if ($result['remarks']): ?>
        <div style="margin-top:10px;">
            <h4>Remarks:</h4>
            <p><?php echo nl2br(htmlspecialchars($result['remarks'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; endif; ?>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-secondary { background: #e5e7eb; color: #374151; }
</style>

<?php include '../includes/footer.php'; ?>
