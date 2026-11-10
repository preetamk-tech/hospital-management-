<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    redirect('auth/login.php');
}

// Get doctor info
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    setMessage("Doctor profile not found!", "error");
    redirect('auth/logout.php');
}

$doctor_id = $doctor['doctor_id'];
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$patients = [];

if ($search) {
    $stmt = $pdo->prepare("
        SELECT * FROM patients 
        WHERE full_name LIKE ? OR phone LIKE ?
        ORDER BY created_at DESC
    ");
    $stmt->execute(["%$search%", "%$search%"]);
    $patients = $stmt->fetchAll();
}

// Get selected patient history
$selected_patient = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$history = [];
if ($selected_patient) {
    $stmt = $pdo->prepare("
        SELECT m.*, d.full_name as doctor_name
        FROM medical_history m
        JOIN doctors d ON m.doctor_id = d.doctor_id
        WHERE m.patient_id = ?
        ORDER BY m.visit_date DESC
    ");
    $stmt->execute([$selected_patient]);
    $history = $stmt->fetchAll();
    
    // Get patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$selected_patient]);
    $patient = $stmt->fetch();
}

include '../includes/header.php';
?>

<div class="grid">
    <div class="box" style="margin-top: 70px;">
        <div class="box-header">
            <h2 class="box-title">Search Patient</h2>
            <i class="fas fa-search"></i>
        </div>
        
        <form method="GET">
            <div style="display: flex; gap: 1rem;">
                <input type="text" name="search" class="form-control" placeholder="Search by name or phone" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
        
        <?php if ($search && !empty($patients)): ?>
            <div class="table-container" style="margin-top: 2rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Senior</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo $row['age']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['is_senior'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="?search=<?php echo urlencode($search); ?>&patient_id=<?php echo $row['patient_id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">
                                    View History
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($search): ?>
            <p style="margin-top: 2rem; text-align: center;">No patients found.</p>
        <?php endif; ?>
    </div>
    
    <?php if ($selected_patient && isset($patient)): ?>
    <div class="box" style="margin-top: 70px;">
        <div class="box-header">
            <h2 class="box-title">Medical History: <?php echo htmlspecialchars($patient['full_name']); ?></h2>
            <i class="fas fa-notes-medical"></i>
        </div>
        
        <?php if (empty($history)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #a0aec0;"></i>
                <p>No medical records found for this patient.</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $row): ?>
            <div class="box" style="margin-bottom: 1rem; background: #f7fafc;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                    <strong>Visit: <?php echo date('d M Y', strtotime($row['visit_date'])); ?></strong>
                    <span class="badge badge-info">Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></span>
                </div>
                <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($row['diagnosis'])); ?></p>
                <p><strong>Prescription:</strong> <?php echo nl2br(htmlspecialchars($row['prescription'])); ?></p>
                <?php if ($row['notes']): ?>
                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($row['notes'])); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <a href="add_medical_history.php?patient_id=<?php echo $selected_patient; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Record
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>