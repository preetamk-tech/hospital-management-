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
    
    if ($action == 'add_ward') {
        $stmt = $pdo->prepare("INSERT INTO wards (ward_name, ward_type, total_beds, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([sanitize($_POST['ward_name']), sanitize($_POST['ward_type']), (int)$_POST['total_beds'], sanitize($_POST['ward_description'])]);
        $message = 'Ward added!';
    } elseif ($action == 'add_bed') {
        $ward_id = (int)$_POST['ward_id'];
        $bed_number = sanitize($_POST['bed_number']);
        $floor = (int)$_POST['floor'];
        $stmt = $pdo->prepare("INSERT INTO beds (ward_id, bed_number, floor) VALUES (?, ?, ?)");
        $stmt->execute([$ward_id, $bed_number, $floor]);
        $stmt = $pdo->prepare("UPDATE wards SET total_beds = total_beds + 1 WHERE ward_id = ?");
        $stmt->execute([$ward_id]);
        $message = 'Bed added!';
    } elseif ($action == 'discharge') {
        $bed_id = (int)$_POST['bed_id'];
        $stmt = $pdo->prepare("UPDATE beds SET is_occupied = 0, patient_id = NULL, admitted_at = NULL, expected_discharge = NULL WHERE bed_id = ?");
        $stmt->execute([$bed_id]);
        $message = 'Patient discharged!';
    }
}

$filter_ward = isset($_GET['ward']) ? (int)$_GET['ward'] : 0;

$wards = [];
$stmt = $pdo->prepare("SELECT w.*, 
    (SELECT COUNT(*) FROM beds b WHERE b.ward_id = w.ward_id AND b.is_occupied = 1) as occupied_beds 
    FROM wards w ORDER BY w.ward_name");
$stmt->execute();
$wards = $stmt->fetchAll();

$beds = [];
if ($filter_ward) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.full_name as patient_name
        FROM beds b
        LEFT JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.ward_id = ?
        ORDER BY b.bed_number
    ");
    $stmt->execute([$filter_ward]);
    $beds = $stmt->fetchAll();
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 800px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-bed"></i> Bed Management</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
            <h3 style="margin-bottom:0.5rem;">Add Ward</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_ward">
                <div class="form-group">
                    <label class="form-label">Ward Name</label>
                    <input type="text" name="ward_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ward Type</label>
                    <select name="ward_type" class="form-control">
                        <option value="general">General</option>
                        <option value="semi_private">Semi Private</option>
                        <option value="private">Private</option>
                        <option value="icu">ICU</option>
                        <option value="emergency">Emergency</option>
                        <option value="pediatric">Pediatric</option>
                        <option value="maternity">Maternity</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Total Beds</label>
                    <input type="number" name="total_beds" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="ward_description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Ward</button>
            </form>
        </div>
        
        <div>
            <h3 style="margin-bottom:0.5rem;">Add Bed to Ward</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_bed">
                <div class="form-group">
                    <label class="form-label">Ward</label>
                    <select name="ward_id" class="form-control" required>
                        <option value="">-- Select Ward --</option>
                        <?php foreach ($wards as $w): ?>
                            <option value="<?php echo $w['ward_id']; ?>"><?php echo htmlspecialchars($w['ward_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Bed Number</label>
                    <input type="text" name="bed_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Floor</label>
                    <input type="number" name="floor" class="form-control" value="1" min="1">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Bed</button>
            </form>
        </div>
    </div>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-th"></i> Ward Overview</h2>
    </div>
    <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
        <?php foreach ($wards as $w): 
            $free = $w['total_beds'] - $w['occupied_beds'];
            $pct = $w['total_beds'] > 0 ? ($w['occupied_beds'] / $w['total_beds']) * 100 : 0;
            $color = $pct > 80 ? '#f56565' : ($pct > 50 ? '#ed8936' : '#48bb78');
        ?>
        <a href="?ward=<?php echo $w['ward_id']; ?>" style="text-decoration:none;color:inherit;">
        <div class="ward-card" style="background:#f8f9fa;border-radius:12px;padding:15px;border-left:4px solid <?php echo $color; ?>;">
            <h4 style="margin:0 0 10px;"><?php echo htmlspecialchars($w['ward_name']); ?></h4>
            <p style="margin:0;color:#666;font-size:14px;">Type: <?php echo ucfirst(str_replace('_', ' ', $w['ward_type'])); ?></p>
            <div style="display:flex;justify-content:space-between;margin-top:10px;">
                <span><strong><?php echo $w['occupied_beds']; ?></strong> occupied</span>
                <span><strong><?php echo $free; ?></strong> free</span>
            </div>
            <div style="background:#e2e8f0;border-radius:10px;height:8px;margin-top:8px;">
                <div style="background:<?php echo $color; ?>;border-radius:10px;height:8px;width:<?php echo $pct; ?>%;"></div>
            </div>
            <p style="text-align:right;font-size:12px;color:#666;margin-top:5px;"><?php echo number_format($pct, 0); ?>% full</p>
        </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($beds)): $selected_ward = $wards[array_search($filter_ward, array_column($wards, 'ward_id'))]; ?>
<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-bed"></i> Beds - <?php echo htmlspecialchars($selected_ward['ward_name']); ?></h2>
        <a href="manage_beds.php" class="btn btn-sm btn-secondary">Back</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Bed Number</th>
                    <th>Floor</th>
                    <th>Status</th>
                    <th>Patient</th>
                    <th>Admitted</th>
                    <th>Expected Discharge</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($beds as $b): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($b['bed_number']); ?></strong></td>
                    <td>Floor <?php echo $b['floor']; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $b['is_occupied'] ? 'danger' : 'success'; ?>">
                            <?php echo $b['is_occupied'] ? 'Occupied' : 'Available'; ?>
                        </span>
                    </td>
                    <td><?php echo $b['patient_name'] ?: '-'; ?></td>
                    <td><?php echo $b['admitted_at'] ? date('d M Y', strtotime($b['admitted_at'])) : '-'; ?></td>
                    <td><?php echo $b['expected_discharge'] ? date('d M Y', strtotime($b['expected_discharge'])) : '-'; ?></td>
                    <td>
                        <?php if ($b['is_occupied']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Discharge this patient?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="discharge">
                            <input type="hidden" name="bed_id" value="<?php echo $b['bed_id']; ?>">
                            <button class="btn btn-sm btn-success"><i class="fas fa-sign-out-alt"></i> Discharge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
</style>

<?php include '../includes/footer.php'; ?>
