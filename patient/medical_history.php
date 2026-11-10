<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT m.*, p.full_name as patient_name, d.full_name as doctor_name
    FROM medical_history m
    JOIN patients p ON m.patient_id = p.patient_id
    JOIN doctors d ON m.doctor_id = d.doctor_id
    WHERE p.user_id = ?
    ORDER BY m.visit_date DESC
");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Medical History</h2>
        <i class="fas fa-notes-medical"></i>
    </div>
    
    <?php if (empty($history)): ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-folder-open" style="font-size: 3rem; color: #a0aec0;"></i>
            <p style="margin-top: 1rem;">No medical records found.</p>
            <p>Medical history will appear here after your consultations.</p>
        </div>
    <?php else: ?>
        <?php foreach ($history as $row): ?>
        <div class="card" style="margin-bottom: 1rem; background: #f7fafc;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                <h3><?php echo htmlspecialchars($row['patient_name']); ?></h3>
                <span class="badge badge-info"><?php echo date('d M Y', strtotime($row['visit_date'])); ?></span>
            </div>
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></p>
            <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($row['diagnosis'])); ?></p>
            <p><strong>Prescription:</strong> <?php echo nl2br(htmlspecialchars($row['prescription'])); ?></p>
            <?php if ($row['notes']): ?>
            <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($row['notes'])); ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>