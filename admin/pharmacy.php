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
    
    if ($action == 'add_category') {
        $stmt = $pdo->prepare("INSERT INTO pharmacy_categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([sanitize($_POST['category_name']), sanitize($_POST['category_description'])]);
        $message = 'Category added!';
    } elseif ($action == 'add_medicine') {
        $stmt = $pdo->prepare("INSERT INTO pharmacy_inventory (category_id, medicine_name, generic_name, manufacturer, batch_number, expiry_date, stock_quantity, reorder_level, price, unit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([(int)$_POST['category_id'], sanitize($_POST['medicine_name']), sanitize($_POST['generic_name']), sanitize($_POST['manufacturer']), sanitize($_POST['batch_number']), sanitize($_POST['expiry_date']), (int)$_POST['stock_quantity'], (int)$_POST['reorder_level'], (float)$_POST['price'], sanitize($_POST['unit'])]);
        $message = 'Medicine added!';
    } elseif ($action == 'update_stock') {
        $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET stock_quantity=?, price=? WHERE medicine_id=?");
        $stmt->execute([(int)$_POST['stock_quantity'], (float)$_POST['price'], (int)$_POST['medicine_id']]);
        $message = 'Stock updated!';
    } elseif ($action == 'toggle') {
        $stmt = $pdo->prepare("UPDATE pharmacy_inventory SET is_available = NOT is_available WHERE medicine_id=?");
        $stmt->execute([(int)$_POST['medicine_id']]);
        $message = 'Status updated!';
    }
}

$categories = [];
$stmt = $pdo->prepare("SELECT * FROM pharmacy_categories ORDER BY category_name");
$stmt->execute();
$categories = $stmt->fetchAll();

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$show_low = isset($_GET['low_stock']);
$show_expired = isset($_GET['expired']);

$sql = "SELECT p.*, c.category_name FROM pharmacy_inventory p LEFT JOIN pharmacy_categories c ON p.category_id = c.category_id WHERE 1=1";
$params = [];

if ($filter != 'all') { $sql .= " AND p.category_id = ?"; $params[] = (int)$filter; }
if ($show_low) { $sql .= " AND p.stock_quantity <= p.reorder_level"; }
if ($show_expired) { $sql .= " AND p.expiry_date < CURDATE()"; }

$sql .= " ORDER BY p.medicine_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$low_stock_count = $pdo->query("SELECT COUNT(*) FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND is_available = 1")->fetchColumn();
$expired_count = $pdo->query("SELECT COUNT(*) FROM pharmacy_inventory WHERE expiry_date < CURDATE() AND is_available = 1")->fetchColumn();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 800px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-pills"></i> Add Medicine / Category</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div>
            <h3 style="margin-bottom:0.5rem;">Add Category</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_category">
                <div class="form-group">
                    <label class="form-label">Category Name</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="category_description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Category</button>
            </form>
        </div>
        
        <div>
            <h3 style="margin-bottom:0.5rem;">Add Medicine</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_medicine">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- None --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Medicine Name *</label>
                    <input type="text" name="medicine_name" class="form-control" required>
                </div>
                <div class="grid" style="grid-template-columns:1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control">
                    </div>
                </div>
                <div class="grid" style="grid-template-columns:1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Batch No.</label>
                        <input type="text" name="batch_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                </div>
                <div class="grid" style="grid-template-columns:1fr 1fr 1fr;">
                    <div class="form-group">
                        <label class="form-label">Stock *</label>
                        <input type="number" name="stock_quantity" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Unit</label>
                    <select name="unit" class="form-control">
                        <option value="tablet">Tablet</option>
                        <option value="capsule">Capsule</option>
                        <option value="ml">ml (Syrup)</option>
                        <option value="dose">Dose (Injection)</option>
                        <option value="tube">Tube (Ointment)</option>
                        <option value="piece">Piece</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Medicine</button>
            </form>
        </div>
    </div>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> Pharmacy Inventory (<?php echo count($medicines); ?>)</h2>
        <div style="display:flex;gap:0.5rem;">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['low_stock'=>1])); ?>" class="btn btn-sm <?php echo $show_low?'btn-danger':'btn-warning'; ?>">
                <i class="fas fa-exclamation-triangle"></i> Low Stock (<?php echo $low_stock_count; ?>)
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['expired'=>1])); ?>" class="btn btn-sm <?php echo $show_expired?'btn-danger':'btn-secondary'; ?>">
                <i class="fas fa-calendar-times"></i> Expired (<?php echo $expired_count; ?>)
            </a>
        </div>
    </div>
    
    <div style="margin-bottom:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="pharmacy.php" class="btn <?php echo $filter=='all'?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;">All</a>
        <?php foreach ($categories as $c): ?>
        <a href="?filter=<?php echo $c['category_id']; ?>" class="btn <?php echo $filter==$c['category_id']?'btn-primary':'btn-secondary'; ?>" style="font-size:13px;padding:5px 12px;">
            <?php echo htmlspecialchars($c['category_name']); ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Generic</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th>Expiry</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicines as $m): 
                    $is_low = $m['stock_quantity'] <= $m['reorder_level'];
                    $is_expired = $m['expiry_date'] && strtotime($m['expiry_date']) < strtotime(date('Y-m-d'));
                ?>
                <tr style="<?php echo $is_expired ? 'background:#fee2e2;' : ($is_low ? 'background:#fef3c7;' : ''); ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($m['medicine_name']); ?></strong>
                        <?php if ($m['batch_number']): ?><br><small style="color:#999;">Batch: <?php echo htmlspecialchars($m['batch_number']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($m['generic_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['category_name']); ?></td>
                    <td>
                        <strong><?php echo $m['stock_quantity']; ?></strong> <?php echo $m['unit']; ?>
                        <?php if ($is_low): ?><br><small style="color:#f56565;">⚠ Low Stock</small><?php endif; ?>
                    </td>
                    <td>₹<?php echo number_format($m['price']); ?></td>
                    <td>
                        <?php echo $m['expiry_date'] ? date('d M Y', strtotime($m['expiry_date'])) : '-'; ?>
                        <?php if ($is_expired): ?><br><small style="color:#f56565;">EXPIRED</small><?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="medicine_id" value="<?php echo $m['medicine_id']; ?>">
                            <button class="btn btn-sm btn-<?php echo $m['is_available'] ? 'warning' : 'success'; ?>">
                                <i class="fas fa-<?php echo $m['is_available'] ? 'ban' : 'check'; ?>"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($medicines)): ?>
                <tr><td colspan="7" style="text-align:center;">No medicines found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
