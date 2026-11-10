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
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO lab_tests (test_name, department, price, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([sanitize($_POST['test_name']), sanitize($_POST['department']), (float)$_POST['price'], sanitize($_POST['description'])]);
        $message = 'Lab test added!';
    } elseif ($action == 'edit') {
        $stmt = $pdo->prepare("UPDATE lab_tests SET test_name=?, department=?, price=?, description=? WHERE test_id=?");
        $stmt->execute([sanitize($_POST['test_name']), sanitize($_POST['department']), (float)$_POST['price'], sanitize($_POST['description']), (int)$_POST['test_id']]);
        $message = 'Lab test updated!';
    } elseif ($action == 'delete') {
        $stmt = $pdo->prepare("UPDATE lab_tests SET is_active=0 WHERE test_id=?");
        $stmt->execute([(int)$_POST['test_id']]);
        $message = 'Lab test deactivated!';
    } elseif ($action == 'restore') {
        $stmt = $pdo->prepare("UPDATE lab_tests SET is_active=1 WHERE test_id=?");
        $stmt->execute([(int)$_POST['test_id']]);
        $message = 'Lab test restored!';
    }
}

$show_inactive = isset($_GET['show_inactive']);
$stmt = $pdo->prepare("SELECT * FROM lab_tests WHERE " . ($show_inactive ? "1=1" : "is_active=1") . " ORDER BY department, test_name");
$stmt->execute();
$tests = $stmt->fetchAll();

$edit_test = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM lab_tests WHERE test_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_test = $stmt->fetch();
}
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="max-width: 800px; margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-vials"></i> <?php echo $edit_test ? 'Edit' : 'Add'; ?> Lab Test</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="<?php echo $edit_test ? 'edit' : 'add'; ?>">
        <?php if ($edit_test): ?><input type="hidden" name="test_id" value="<?php echo $edit_test['test_id']; ?>"><?php endif; ?>
        
        <div class="grid" style="grid-template-columns: 2fr 1fr;">
            <div class="form-group">
                <label class="form-label">Test Name *</label>
                <input type="text" name="test_name" class="form-control" value="<?php echo $edit_test ? htmlspecialchars($edit_test['test_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Department</label>
                <input type="text" name="department" class="form-control" value="<?php echo $edit_test ? htmlspecialchars($edit_test['department']) : ''; ?>">
            </div>
        </div>
        
        <div class="grid" style="grid-template-columns: 1fr 2fr;">
            <div class="form-group">
                <label class="form-label">Price (₹) *</label>
                <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $edit_test ? $edit_test['price'] : ''; ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="<?php echo $edit_test ? htmlspecialchars($edit_test['description']) : ''; ?>">
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $edit_test ? 'Update' : 'Add'; ?> Test</button>
        <?php if ($edit_test): ?><a href="manage_lab_tests.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-list"></i> All Lab Tests (<?php echo count($tests); ?>)</h2>
        <a href="?<?php echo $show_inactive ? '' : 'show_inactive=1'; ?>" class="btn btn-sm btn-secondary">
            <?php echo $show_inactive ? 'Show Active Only' : 'Show Inactive'; ?>
        </a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Department</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($t['test_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($t['department']); ?></td>
                    <td>₹<?php echo number_format($t['price']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $t['is_active'] ? 'success' : 'danger'; ?>">
                            <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="?edit=<?php echo $t['test_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="<?php echo $t['is_active'] ? 'delete' : 'restore'; ?>">
                            <input type="hidden" name="test_id" value="<?php echo $t['test_id']; ?>">
                            <button class="btn btn-sm btn-<?php echo $t['is_active'] ? 'danger' : 'success'; ?>">
                                <i class="fas fa-<?php echo $t['is_active'] ? 'ban' : 'check'; ?>"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
</style>

<?php include '../includes/footer.php'; ?>
