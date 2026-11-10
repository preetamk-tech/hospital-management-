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
$patient_ids = array_column($my_patients, 'patient_id');

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

if (empty($patient_ids)) {
    $consultations = [];
} else {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    
    if ($filter == 'upcoming') {
        $stmt = $pdo->prepare("
            SELECT c.*, d.full_name as doctor_name, dep.department_name
            FROM consultations c
            JOIN doctors d ON c.doctor_id = d.doctor_id
            LEFT JOIN departments dep ON d.department_id = dep.department_id
            WHERE c.patient_id IN ($placeholders) AND c.status = 'scheduled'
            ORDER BY c.scheduled_at ASC
        ");
        $stmt->execute($patient_ids);
        $consultations = $stmt->fetchAll();
    } elseif ($filter == 'active') {
        $stmt = $pdo->prepare("
            SELECT c.*, d.full_name as doctor_name, dep.department_name
            FROM consultations c
            JOIN doctors d ON c.doctor_id = d.doctor_id
            LEFT JOIN departments dep ON d.department_id = dep.department_id
            WHERE c.patient_id IN ($placeholders) AND c.status = 'active'
            ORDER BY c.started_at DESC
        ");
        $stmt->execute($patient_ids);
        $consultations = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, d.full_name as doctor_name, dep.department_name
            FROM consultations c
            JOIN doctors d ON c.doctor_id = d.doctor_id
            LEFT JOIN departments dep ON d.department_id = dep.department_id
            WHERE c.patient_id IN ($placeholders) AND c.status IN ('completed', 'cancelled', 'missed')
            ORDER BY c.scheduled_at DESC
            LIMIT 50
        ");
        $stmt->execute($patient_ids);
        $consultations = $stmt->fetchAll();
    }
}

$stats = [];
if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN status='scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN status='active' THEN 1 END) as active,
        COUNT(CASE WHEN status='completed' THEN 1 END) as completed
        FROM consultations WHERE patient_id IN ($placeholders)");
    $stmt->execute($patient_ids);
    $stats = $stmt->fetch();
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-video"></i> Video Consultations</h2>
    </div>
    
    <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
        <div style="background:#dbeafe;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#1e40af;margin:0;font-size:28px;"><?php echo $stats['scheduled'] ?? 0; ?></h3>
            <p style="margin:5px 0 0;color:#3b82f6;">Scheduled</p>
        </div>
        <div style="background:#d1fae5;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#065f46;margin:0;font-size:28px;"><?php echo $stats['active'] ?? 0; ?></h3>
            <p style="margin:5px 0 0;color:#10b981;">Active Now</p>
        </div>
        <div style="background:#ede9fe;padding:15px;border-radius:12px;text-align:center;">
            <h3 style="color:#5b21b6;margin:0;font-size:28px;"><?php echo $stats['completed'] ?? 0; ?></h3>
            <p style="margin:5px 0 0;color:#8b5cf6;">Completed</p>
        </div>
    </div>
    
    <div class="info-box" style="margin-bottom:1.5rem;background:#f0f4ff;border:1px solid #667eea;border-radius:12px;padding:15px;">
        <h4><i class="fas fa-info-circle" style="color:#667eea;"></i> How Video Consultations Work</h4>
        <ul style="margin-top:8px;padding-left:20px;color:#555;line-height:1.8;">
            <li>Book appointment and choose <strong>"Video Consultation"</strong> option</li>
            <li>When the scheduled time arrives, click <strong>"Join"</strong> to enter the video call</li>
            <li>Consultations are free and encrypted — no app download needed</li>
            <li>Works on mobile, tablet, or desktop browsers</li>
        </ul>
    </div>
    
    <h3 style="margin-bottom:0.5rem;">My Consultations</h3>
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;">
        <?php foreach (['upcoming','active','past'] as $f): ?>
        <a href="?filter=<?php echo $f; ?>" class="btn <?php echo $filter==$f?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;"><?php echo ucfirst($f); ?></a>
        <?php endforeach; ?>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Doctor</th><th>Department</th><th>Scheduled</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($consultations as $c): 
                    $colors = ['scheduled'=>'info','active'=>'success','completed'=>'secondary','cancelled'=>'danger','missed'=>'warning'];
                    $can_join = ($c['status'] == 'active') || ($c['status'] == 'scheduled' && strtotime($c['scheduled_at']) <= time() + 300);
                ?>
                <tr>
                    <td><strong>Dr. <?php echo htmlspecialchars($c['doctor_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($c['department_name']); ?></td>
                    <td><?php echo date('d M Y, h:i A', strtotime($c['scheduled_at'])); ?></td>
                    <td><?php echo $c['duration_minutes']; ?> min</td>
                    <td><span class="badge badge-<?php echo $colors[$c['status']] ?? 'secondary'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                    <td>
                        <?php if ($can_join): ?>
                        <a href="consultation_room.php?id=<?php echo $c['consultation_id']; ?>" class="btn btn-sm btn-success" target="_blank">
                            <i class="fas fa-video"></i> Join Now
                        </a>
                        <?php elseif ($c['status'] == 'scheduled'): ?>
                        <span style="color:#999;font-size:13px;">Starts in <?php echo round((strtotime($c['scheduled_at']) - time()) / 60); ?> min</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($consultations)): ?>
                <tr><td colspan="6" style="text-align:center;">No consultations found.</td></tr>
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
