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
    
    if ($action == 'add_provider') {
        $stmt = $pdo->prepare("INSERT INTO insurance_providers (provider_name, contact_phone, contact_email) VALUES (?, ?, ?)");
        $stmt->execute([sanitize($_POST['provider_name']), sanitize($_POST['contact_phone']), sanitize($_POST['contact_email'])]);
        $message = 'Provider added!';
    } elseif ($action == 'toggle_provider') {
        $stmt = $pdo->prepare("UPDATE insurance_providers SET is_active = NOT is_active WHERE provider_id = ?");
        $stmt->execute([(int)$_POST['provider_id']]);
        $message = 'Status updated!';
    }
}

$providers = [];
$stmt = $pdo->prepare("SELECT * FROM insurance_providers ORDER BY provider_name");
$stmt->execute();
$providers = $stmt->fetchAll();

$policies = [];
$stmt = $pdo->prepare("
    SELECT pi.*, pr.provider_name, p.full_name as patient_name, p.phone
    FROM patient_insurance pi
    JOIN insurance_providers pr ON pi.provider_id = pr.provider_id
    JOIN patients p ON pi.patient_id = p.patient_id
    ORDER BY pi.created_at DESC
    LIMIT 100
");
$stmt->execute();
$policies = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 600px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-shield-alt"></i> Add Insurance Provider</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="add_provider">
        
        <div class="form-group">
            <label class="form-label">Provider Name *</label>
            <input type="text" name="provider_name" class="form-control" required>
        </div>
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Contact Phone</label>
                <input type="text" name="contact_phone" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input type="email" name="contact_email" class="form-control">
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Provider</button>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-building"></i> Insurance Providers</h2>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Provider</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($providers as $pr): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($pr['provider_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($pr['contact_phone']); ?></td>
                    <td><?php echo htmlspecialchars($pr['contact_email']); ?></td>
                    <td><span class="badge badge-<?php echo $pr['is_active'] ? 'success' : 'danger'; ?>"><?php echo $pr['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="toggle_provider">
                            <input type="hidden" name="provider_id" value="<?php echo $pr['provider_id']; ?>">
                            <button class="btn btn-sm btn-<?php echo $pr['is_active'] ? 'warning' : 'success'; ?>"><i class="fas fa-<?php echo $pr['is_active'] ? 'ban' : 'check'; ?>"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-id-card"></i> All Insurance Policies</h2>
    </div>
    <div class="table-container">
        <table>
            <thead><tr><th>Patient</th><th>Phone</th><th>Provider</th><th>Policy #</th><th>Holder</th><th>Valid Until</th><th>Coverage</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($policies as $p): 
                    $colors = ['active'=>'success','expired'=>'danger','suspended'=>'warning'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['phone']); ?></td>
                    <td><?php echo htmlspecialchars($p['provider_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($p['policy_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['policy_holder_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($p['valid_until'])); ?></td>
                    <td>₹<?php echo number_format($p['coverage_amount']); ?></td>
                    <td><span class="badge badge-<?php echo $colors[$p['status']] ?? 'secondary'; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($policies)): ?>
                <tr><td colspan="8" style="text-align:center;">No policies found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;}.badge-success{background:#d1fae5;color:#065f46;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-secondary{background:#e5e7eb;color:#374151;}
</style>

<?php include '../includes/footer.php'; ?>
