<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Add department
if (isset($_POST['add'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $name = sanitize($_POST['dept_name']);
    
    $stmt = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
    if ($stmt->execute([$name])) {
        setMessage("Department added successfully!", "success");
    } else {
        setMessage("Failed to add department!", "error");
    }
    redirect('admin/manage_departments.php');
}

// Edit department
if (isset($_POST['edit'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_POST['dept_id'];
    $name = sanitize($_POST['dept_name']);
    
    $stmt = $pdo->prepare("UPDATE departments SET department_name = ? WHERE department_id = ?");
    $stmt->execute([$name, $id]);
    setMessage("Department updated!", "success");
    redirect('admin/manage_departments.php');
}

// Delete department
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if department has doctors
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM doctors WHERE department_id = ?");
    $stmt->execute([$id]);
    $has_doctors = $stmt->fetch()['count'];
    
    if ($has_doctors > 0) {
        setMessage("Cannot delete department with assigned doctors!", "error");
    } else {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
        $stmt->execute([$id]);
        setMessage("Department deleted!", "success");
    }
    redirect('admin/manage_departments.php');
}

$stmt = $pdo->prepare("SELECT * FROM departments ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title">Manage Departments</h2>
        <i class="fas fa-building"></i>
    </div>
    
    <form method="POST" style="margin-bottom: 2rem;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <input type="text" name="dept_name" class="form-control" style="flex: 1;" placeholder="Department Name" required>
            <button type="submit" name="add" class="btn btn-primary"><i class="fas fa-plus"></i> Add Department</button>
        </div>
    </form>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Department Name</th><th>Doctors Count</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $row): 
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM doctors WHERE department_id = ?");
                    $stmt->execute([$row['department_id']]);
                    $doctor_count = $stmt->fetch()['count'];
                ?>
                <tr>
                    <td><?php echo $row['department_id']; ?></td>
                    <td>
                        <form method="POST" style="display: inline-flex; gap: 0.5rem;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="dept_id" value="<?php echo $row['department_id']; ?>">
                            <input type="text" name="dept_name" value="<?php echo htmlspecialchars($row['department_name']); ?>" class="form-control" style="width: auto; display: inline-block;">
                            <button type="submit" name="edit" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">Update</button>
                        </form>
                    </td>
                    <td><span class="badge badge-info"><?php echo $doctor_count; ?> doctors</span></td>
                    <td>
                        <?php if ($doctor_count == 0): ?>
                        <a href="?delete=<?php echo $row['department_id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.8rem;" onclick="return confirm('Delete this department?')">Delete</a>
                        <?php else: ?>
                        <span class="badge badge-warning">Has Doctors</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>