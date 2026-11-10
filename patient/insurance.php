<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ? ORDER BY full_name");
$stmt->execute([$user_id]);
$my_patients = $stmt->fetchAll();

$providers = [];
$stmt = $pdo->prepare("SELECT * FROM insurance_providers WHERE is_active = 1");
$stmt->execute();
$providers = $stmt->fetchAll();

$insurances = [];
foreach ($my_patients as $p) {
    $stmt = $pdo->prepare("
        SELECT pi.*, pr.provider_name
        FROM patient_insurance pi
        JOIN insurance_providers pr ON pi.provider_id = pr.provider_id
        WHERE pi.patient_id = ?
    ");
    $stmt->execute([$p['patient_id']]);
    $insurances[$p['patient_id']] = $stmt->fetchAll();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $patient_id = (int)$_POST['patient_id'];
    $provider_id = (int)$_POST['provider_id'];
    $policy_number = sanitize($_POST['policy_number']);
    $policy_holder_name = sanitize($_POST['policy_holder_name']);
    $valid_from = sanitize($_POST['valid_from']);
    $valid_until = sanitize($_POST['valid_until']);
    $coverage_amount = (float)$_POST['coverage_amount'];
    
    $status = strtotime($valid_until) >= strtotime(date('Y-m-d')) ? 'active' : 'expired';
    
    $stmt = $pdo->prepare("INSERT INTO patient_insurance (patient_id, provider_id, policy_number, policy_holder_name, valid_from, valid_until, coverage_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patient_id, $provider_id, $policy_number, $policy_holder_name, $valid_from, $valid_until, $coverage_amount, $status]);
    $message = 'Insurance added successfully!';
    
    $stmt = $pdo->prepare("
        SELECT pi.*, pr.provider_name
        FROM patient_insurance pi
        JOIN insurance_providers pr ON pi.provider_id = pr.provider_id
        WHERE pi.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $insurances[$patient_id] = $stmt->fetchAll();
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 700px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-shield-alt"></i> Add Insurance</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
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
            <label class="form-label">Insurance Provider *</label>
            <select name="provider_id" class="form-control" required>
                <option value="">-- Select Provider --</option>
                <?php foreach ($providers as $pr): ?>
                    <option value="<?php echo $pr['provider_id']; ?>"><?php echo htmlspecialchars($pr['provider_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Policy Number *</label>
                <input type="text" name="policy_number" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Policy Holder Name *</label>
                <input type="text" name="policy_holder_name" class="form-control" required>
            </div>
        </div>
        
        <div class="grid" style="grid-template-columns: 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">Valid From *</label>
                <input type="date" name="valid_from" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Valid Until *</label>
                <input type="date" name="valid_until" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Coverage Amount (₹) *</label>
            <input type="number" name="coverage_amount" class="form-control" step="0.01" required>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Insurance</button>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> My Insurance Policies</h2>
    </div>
    <?php foreach ($my_patients as $p): 
        if (empty($insurances[$p['patient_id']])) continue;
    ?>
    <h3 style="padding:10px 15px;background:#f8f9fa;border-radius:8px;margin-bottom:10px;">
        <?php echo htmlspecialchars($p['full_name']); ?>
    </h3>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Policy Number</th>
                    <th>Holder</th>
                    <th>Valid From</th>
                    <th>Valid Until</th>
                    <th>Coverage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($insurances[$p['patient_id']] as $ins): 
                    $status_colors = ['active' => 'success', 'expired' => 'danger', 'suspended' => 'warning'];
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($ins['provider_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($ins['policy_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($ins['policy_holder_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($ins['valid_from'])); ?></td>
                    <td><?php echo date('d M Y', strtotime($ins['valid_until'])); ?></td>
                    <td>₹<?php echo number_format($ins['coverage_amount']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $status_colors[$ins['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst($ins['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-warning { background: #fef3c7; color: #92400e; }
</style>

<?php include '../includes/footer.php'; ?>
