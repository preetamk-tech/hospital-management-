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

$patients = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_id = (int)$_POST['patient_id'];
    $appointment_id = (int)$_POST['appointment_id'];
    $diagnosis = sanitize($_POST['diagnosis']);
    $prescription = $_POST['prescription'];
    $notes = sanitize($_POST['notes']);
    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    
    $visit_date = date('Y-m-d');
    
    $stmt = $pdo->prepare("INSERT INTO medical_history (patient_id, doctor_id, diagnosis, prescription, notes, visit_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patient_id, $doctor_id, $diagnosis, $prescription, $notes, $visit_date]);
    $history_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    
    $stmt = $pdo->prepare("UPDATE tokens SET status = 'completed', completed_at = NOW(), service_end_time = NOW() WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    
    $message = 'Medical record saved! PDF available for download.';
}

$stmt = $pdo->prepare("
    SELECT p.*, a.appointment_id, t.token_number
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id AND t.queue_date = CURDATE()
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND (a.status = 'confirmed' OR t.status = 'called')
    ORDER BY t.position ASC
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll();

$templates = [];
$stmt = $pdo->prepare("SELECT template_id, template_name, condition_type FROM prescription_templates WHERE doctor_id = ? AND is_active = 1");
$stmt->execute([$doctor_id]);
$templates = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-file-medical-alt"></i> Add Medical Record & Generate Prescription</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label class="form-label">Select Patient *</label>
            <select name="patient_id" id="patient_select" class="form-control" required>
                <option value="">-- Select Patient --</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['patient_id']; ?>" data-appointment="<?php echo $p['appointment_id']; ?>">
                        <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['age']; ?> yrs) - <?php echo $p['token_number']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="appointment_id" id="appointment_id">
        </div>
        
        <div class="form-group">
            <label class="form-label">Load Prescription Template</label>
            <select id="template_select" class="form-control" onchange="loadTemplate(this.value)">
                <option value="">-- Select Template --</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?php echo $t['template_id']; ?>" data-diagnosis="" data-prescription="" data-notes="">
                        <?php echo htmlspecialchars($t['template_name']); ?> (<?php echo htmlspecialchars($t['condition_type']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Diagnosis *</label>
            <textarea name="diagnosis" id="diagnosis" class="form-control" rows="2" required></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Prescription *</label>
            <textarea name="prescription" id="prescription" class="form-control" rows="8" required placeholder="Tab. Medicine 10mg - 1 BD for 5 days&#10;Syp. Syrup - 10ml TDS"></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes/Advice</label>
            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Record</button>
        <button type="button" class="btn btn-secondary" onclick="window.open('../api/generate_prescription_pdf.php?history_id=<?php echo $history_id ?? '0'; ?>', '_blank')"><i class="fas fa-file-pdf"></i> Download PDF</button>
    </form>
</div>

<script>
document.getElementById('patient_select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('appointment_id').value = opt.dataset.appointment || '';
});

async function loadTemplate(id) {
    if (!id) return;
    const res = await fetch('../api/get_template.php?id=' + id);
    const data = await res.json();
    if (data.success) {
        document.getElementById('diagnosis').value = data.diagnosis;
        document.getElementById('prescription').value = data.prescription;
        document.getElementById('notes').value = data.notes;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
