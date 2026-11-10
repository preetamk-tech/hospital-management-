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

$templates = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $template_name = sanitize($_POST['template_name']);
    $condition_type = sanitize($_POST['condition_type']);
    $diagnosis = sanitize($_POST['diagnosis']);
    $prescription = $_POST['prescription'];
    $notes = sanitize($_POST['notes']);
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO prescription_templates (doctor_id, template_name, condition_type, diagnosis, prescription, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$doctor_id, $template_name, $condition_type, $diagnosis, $prescription, $notes]);
        $message = 'Template created successfully!';
    } elseif ($action == 'edit') {
        $template_id = (int)$_POST['template_id'];
        $stmt = $pdo->prepare("UPDATE prescription_templates SET template_name=?, condition_type=?, diagnosis=?, prescription=?, notes=? WHERE template_id=? AND doctor_id=?");
        $stmt->execute([$template_name, $condition_type, $diagnosis, $prescription, $notes, $template_id, $doctor_id]);
        $message = 'Template updated successfully!';
    } elseif ($action == 'delete') {
        $template_id = (int)$_POST['template_id'];
        $stmt = $pdo->prepare("DELETE FROM prescription_templates WHERE template_id=? AND doctor_id=?");
        $stmt->execute([$template_id, $doctor_id]);
        $message = 'Template deleted!';
    }
}

$stmt = $pdo->prepare("SELECT * FROM prescription_templates WHERE doctor_id = ? ORDER BY template_name");
$stmt->execute([$doctor_id]);
$templates = $stmt->fetchAll();

$edit_template = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM prescription_templates WHERE template_id = ? AND doctor_id = ?");
    $stmt->execute([(int)$_GET['edit'], $doctor_id]);
    $edit_template = $stmt->fetch();
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 700px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-file-medical"></i> <?php echo $edit_template ? 'Edit Template' : 'Prescription Templates'; ?></h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="<?php echo $edit_template ? 'edit' : 'add'; ?>">
        <?php if ($edit_template): ?><input type="hidden" name="template_id" value="<?php echo $edit_template['template_id']; ?>"><?php endif; ?>
        
        <div class="form-group">
            <label class="form-label">Template Name *</label>
            <input type="text" name="template_name" class="form-control" value="<?php echo $edit_template ? htmlspecialchars($edit_template['template_name']) : ''; ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Condition Type</label>
            <input type="text" name="condition_type" class="form-control" value="<?php echo $edit_template ? htmlspecialchars($edit_template['condition_type']) : ''; ?>" placeholder="e.g., Respiratory, Cardiology">
        </div>
        
        <div class="form-group">
            <label class="form-label">Diagnosis</label>
            <textarea name="diagnosis" class="form-control" rows="2"><?php echo $edit_template ? htmlspecialchars($edit_template['diagnosis']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Prescription *</label>
            <textarea name="prescription" class="form-control" rows="6" required placeholder="Tab. Medicine 10mg - 1 BD for 5 days&#10;Syp. Syrup - 10ml TDS"><?php echo $edit_template ? htmlspecialchars($edit_template['prescription']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes/Advice</label>
            <textarea name="notes" class="form-control" rows="2"><?php echo $edit_template ? htmlspecialchars($edit_template['notes']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $edit_template ? 'Update' : 'Create'; ?> Template</button>
            <?php if ($edit_template): ?><a href="prescription_templates.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> My Templates (<?php echo count($templates); ?>)</h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Diagnosis</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $t): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($t['template_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['condition_type']); ?></td>
                    <td><?php echo htmlspecialchars(substr($t['diagnosis'], 0, 50)); ?></td>
                    <td>
                        <a href="?edit=<?php echo $t['template_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="template_id" value="<?php echo $t['template_id']; ?>">
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?>
                <tr><td colspan="4" style="text-align:center;">No templates yet. Create your first one above.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
