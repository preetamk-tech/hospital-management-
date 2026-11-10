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
        $scheduled_at = sanitize($_POST['scheduled_at']);
        $duration = (int)($_POST['duration'] ?? 30);
        
        $room_name = 'medi-' . $doctor_id . '-' . uniqid();
        
        $stmt = $pdo->prepare("INSERT INTO consultations (appointment_id, patient_id, doctor_id, room_name, scheduled_at, duration_minutes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$appointment_id, $patient_id, $doctor_id, $room_name, $scheduled_at, $duration]);
        $message = 'Video consultation scheduled! Link sent to patient.';
    } elseif ($action == 'start') {
        $stmt = $pdo->prepare("UPDATE consultations SET status = 'active', started_at = NOW() WHERE consultation_id = ? AND doctor_id = ?");
        $stmt->execute([(int)$_POST['consultation_id'], $doctor_id]);
        header("Location: consultation_room.php?id=" . (int)$_POST['consultation_id']);
        exit;
    } elseif ($action == 'end') {
        $stmt = $pdo->prepare("UPDATE consultations SET status = 'completed', ended_at = NOW() WHERE consultation_id = ? AND doctor_id = ?");
        $stmt->execute([(int)$_POST['consultation_id'], $doctor_id]);
        $message = 'Consultation ended.';
    } elseif ($action == 'cancel') {
        $stmt = $pdo->prepare("UPDATE consultations SET status = 'cancelled' WHERE consultation_id = ? AND doctor_id = ?");
        $stmt->execute([(int)$_POST['consultation_id'], $doctor_id]);
        $message = 'Consultation cancelled.';
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

if ($filter == 'upcoming') {
    $stmt = $pdo->prepare("
        SELECT c.*, p.full_name as patient_name, p.age, p.phone, p.gender
        FROM consultations c
        JOIN patients p ON c.patient_id = p.patient_id
        WHERE c.doctor_id = ? AND c.status = 'scheduled'
        ORDER BY c.scheduled_at ASC
    ");
    $stmt->execute([$doctor_id]);
    $consultations = $stmt->fetchAll();
} elseif ($filter == 'active') {
    $stmt = $pdo->prepare("
        SELECT c.*, p.full_name as patient_name
        FROM consultations c
        JOIN patients p ON c.patient_id = p.patient_id
        WHERE c.doctor_id = ? AND c.status = 'active'
        ORDER BY c.started_at DESC
    ");
    $stmt->execute([$doctor_id]);
    $consultations = $stmt->fetchAll();
} elseif ($filter == 'past') {
    $stmt = $pdo->prepare("
        SELECT c.*, p.full_name as patient_name
        FROM consultations c
        JOIN patients p ON c.patient_id = p.patient_id
        WHERE c.doctor_id = ? AND c.status IN ('completed', 'cancelled', 'missed')
        ORDER BY c.scheduled_at DESC
        LIMIT 50
    ");
    $stmt->execute([$doctor_id]);
    $consultations = $stmt->fetchAll();
}

$appointments = [];
$stmt = $pdo->prepare("
    SELECT a.appointment_id, a.consultation_type, p.patient_id, p.full_name, p.age, p.phone, t.token_number
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id AND t.queue_date = CURDATE()
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status = 'confirmed' AND a.consultation_type = 'video'
");
$stmt->execute([$doctor_id]);
$appointments = $stmt->fetchAll();

$stats = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status='scheduled' THEN 1 END) as scheduled,
    COUNT(CASE WHEN status='active' THEN 1 END) as active,
    COUNT(CASE WHEN status='completed' THEN 1 END) as completed
    FROM consultations WHERE doctor_id = ?");
$stats->execute([$doctor_id]);
$consult_stats = $stats->fetch();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-video"></i> Telemedicine</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background:#dbeafe;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#1e40af;margin:0;font-size:28px;"><?php echo $consult_stats['scheduled']; ?></h3>
            <p style="margin:5px 0 0;color:#3b82f6;">Scheduled</p>
        </div>
        <div style="background:#d1fae5;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#065f46;margin:0;font-size:28px;"><?php echo $consult_stats['active']; ?></h3>
            <p style="margin:5px 0 0;color:#10b981;">Active</p>
        </div>
        <div style="background:#ede9fe;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#5b21b6;margin:0;font-size:28px;"><?php echo $consult_stats['completed']; ?></h3>
            <p style="margin:5px 0 0;color:#8b5cf6;">Completed</p>
        </div>
    </div>
    
    <h3 style="margin-bottom:0.5rem;"><i class="fas fa-video"></i> Today's Video Patients</h3>
    
    <?php if (empty($appointments)): ?>
        <div style="background:#f8f9fa;padding:1.5rem;border-radius:8px;margin-bottom:1.5rem;text-align:center;color:#888;">
            <i class="fas fa-info-circle"></i> No video consultation patients booked for today.
        </div>
    <?php else: ?>
        <div style="background:#f0f4ff;border:1px solid #667eea;border-radius:8px;padding:1rem;margin-bottom:1.5rem;">
            <p style="margin:0 0 0.5rem;color:#555;"><strong>Patients who chose Video Consultation today:</strong></p>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem;">
                <?php foreach ($appointments as $a): ?>
                    <div style="background:#fff;padding:10px 15px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong><?php echo htmlspecialchars($a['full_name']); ?></strong>
                            <div style="font-size:12px;color:#888;">Token: <?php echo $a['token_number'] ?? '--'; ?></div>
                        </div>
                        <span class="badge badge-success">Video</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <h3 style="margin-bottom:0.5rem;">Consultations</h3>
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;">
        <?php foreach (['upcoming','active','past'] as $f): ?>
        <a href="?filter=<?php echo $f; ?>" class="btn <?php echo $filter==$f?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;"><?php echo ucfirst($f); ?></a>
        <?php endforeach; ?>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Patient</th><th>Type</th><th>Scheduled</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($consultations as $c): 
                    $colors = ['scheduled'=>'info','active'=>'success','completed'=>'secondary','cancelled'=>'danger','missed'=>'warning'];
                    $ctype = $c['appointment_id'] ? 'video' : 'manual';
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($c['patient_name']); ?></strong></td>
                    <td>
                        <?php if ($ctype === 'video'): ?>
                            <span class="badge badge-success"><i class="fas fa-video"></i> Video Booking</span>
                        <?php else: ?>
                            <span class="badge badge-info"><i class="fas fa-calendar"></i> Scheduled</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d M Y, h:i A', strtotime($c['scheduled_at'])); ?></td>
                    <td><?php echo $c['duration_minutes']; ?> min</td>
                    <td><span class="badge badge-<?php echo $colors[$c['status']] ?? 'secondary'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                    <td style="display:flex;gap:0.3rem;">
                        <?php if ($c['status'] == 'scheduled'): ?>
                            <?php if (strtotime($c['scheduled_at']) <= time() + 300): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="start">
                                <input type="hidden" name="consultation_id" value="<?php echo $c['consultation_id']; ?>">
                                <button class="btn btn-sm btn-success"><i class="fas fa-video"></i> Start</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Cancel this consultation?');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="consultation_id" value="<?php echo $c['consultation_id']; ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                            </form>
                        <?php elseif ($c['status'] == 'active'): ?>
                            <a href="consultation_room.php?id=<?php echo $c['consultation_id']; ?>" class="btn btn-sm btn-success"><i class="fas fa-video"></i> Join</a>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="end">
                                <input type="hidden" name="consultation_id" value="<?php echo $c['consultation_id']; ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-stop"></i> End</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($consultations)): ?>
                <tr><td colspan="5" style="text-align:center;">No consultations found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block;}
.badge-info{background:#dbeafe;color:#1e40af;}.badge-success{background:#d1fae5;color:#065f46;}.badge-secondary{background:#e5e7eb;color:#374151;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-warning{background:#fef3c7;color:#92400e;}
</style>

<?php include '../includes/footer.php'; ?>
