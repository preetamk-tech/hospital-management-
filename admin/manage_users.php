<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Toggle user status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    $new_status = $user['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([$new_status, $id]);
    
    setMessage("User status updated!", "success");
    redirect('admin/manage_users.php');
}

// Delete user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if user has appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments a JOIN patients p ON a.patient_id = p.patient_id WHERE p.user_id = ?");
    $stmt->execute([$id]);
    $has_appointments = $stmt->fetch()['count'];
    
    if ($has_appointments > 0) {
        setMessage("Cannot delete user with existing appointments!", "error");
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$id]);
        setMessage("User deleted successfully!", "success");
    }
    redirect('admin/manage_users.php');
}

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title">Manage Users</h2>
        <i class="fas fa-users-cog"></i>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                <tr>
                    <td><?php echo $row['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><span class="badge badge-<?php echo $row['role'] == 'admin' ? 'danger' : ($row['role'] == 'doctor' ? 'info' : ($row['role'] == 'receptionist' ? 'warning' : 'success')); ?>"><?php echo $row['role']; ?></span></td>
                    <td><span class="badge badge-<?php echo $row['is_active'] ? 'success' : 'danger'; ?>"><?php echo $row['is_active'] ? 'Active' : 'Blocked'; ?></span></td>
                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?toggle=<?php echo $row['user_id']; ?>" class="btn btn-<?php echo $row['is_active'] ? 'warning' : 'success'; ?>" style="padding: 0.3rem 0.8rem;">
                                <?php echo $row['is_active'] ? 'Block' : 'Activate'; ?>
                            </a>
                            <?php if ($row['role'] != 'admin'): ?>
                            <a href="?delete=<?php echo $row['user_id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.8rem;" onclick="return confirm('Delete this user permanently?')">
                                Delete
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>