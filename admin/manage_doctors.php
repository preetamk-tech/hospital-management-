<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Add doctor
if (isset($_POST['add'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $name = sanitize($_POST['full_name']);
    $dept = (int)$_POST['department_id'];
    $specialization = sanitize($_POST['specialization']);
    $consultation_time = (int)$_POST['consultation_time'];
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        setMessage("Email already registered!", "error");
    } else {
        // Create user account for doctor
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'doctor')");
        $stmt->execute([$name, $email, $hashed]);
        $user_id = $pdo->lastInsertId();
        
        // Insert doctor record
        $stmt = $pdo->prepare("INSERT INTO doctors (user_id, full_name, department_id, specialization, consultation_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $dept, $specialization, $consultation_time]);
        
        setMessage("Doctor added successfully!", "success");
    }
    redirect('admin/manage_doctors.php');
}

// Delete doctor
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if doctor has appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$id]);
    $has_appointments = $stmt->fetch()['count'];
    
    if ($has_appointments > 0) {
        setMessage("Cannot delete doctor with existing appointments!", "error");
    } else {
        // Get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$id]);
        $doctor = $stmt->fetch();
        
        if ($doctor && $doctor['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$doctor['user_id']]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM doctors WHERE doctor_id = ?");
        $stmt->execute([$id]);
        setMessage("Doctor deleted!", "success");
    }
    redirect('admin/manage_doctors.php');
}

// Toggle availability
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    
    $stmt = $pdo->prepare("SELECT is_available FROM doctors WHERE doctor_id = ?");
    $stmt->execute([$id]);
    $doctor = $stmt->fetch();
    
    $new_status = $doctor['is_available'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE doctors SET is_available = ? WHERE doctor_id = ?");
    $stmt->execute([$new_status, $id]);
    
    setMessage("Doctor availability updated!", "success");
    redirect('admin/manage_doctors.php');
}

$stmt = $pdo->prepare("
    SELECT d.*, dep.department_name, u.email 
    FROM doctors d 
    JOIN departments dep ON d.department_id = dep.department_id
    LEFT JOIN users u ON d.user_id = u.user_id
    ORDER BY d.full_name
");
$stmt->execute();
$doctors = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM departments ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px;;">
    <div class="box-header">
        <h2 class="box-title">Manage Doctors</h2>
        <i class="fas fa-stethoscope"></i>
    </div>
    
    <form method="POST" style="margin-bottom: 2rem;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <input type="text" name="full_name" class="form-control" placeholder="Doctor Name" required>
            <select name="department_id" class="form-control" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="specialization" class="form-control" placeholder="Specialization">
            <input type="number" name="consultation_time" class="form-control" placeholder="Consultation Time (min)" value="10">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
            <input type="password" name="password" class="form-control" placeholder="Password" required>
            <button type="submit" name="add" class="btn btn-primary"><i class="fas fa-plus"></i> Add Doctor</button>
        </div>
    </form>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Department</th><th>Specialization</th><th>Email</th><th>Consult Time</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                    <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                    <td><?php echo $row['consultation_time']; ?> min</td>
                    <td>
                        <span class="badge badge-<?php echo $row['is_available'] ? 'success' : 'danger'; ?>">
                            <?php echo $row['is_available'] ? 'Available' : 'Unavailable'; ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="?toggle=<?php echo $row['doctor_id']; ?>" class="btn btn-<?php echo $row['is_available'] ? 'warning' : 'success'; ?>" style="padding: 0.3rem 0.8rem;">
                                <?php echo $row['is_available'] ? 'Set Off' : 'Set On'; ?>
                            </a>
                            <a href="?delete=<?php echo $row['doctor_id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.8rem;" onclick="return confirm('Delete this doctor?')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>