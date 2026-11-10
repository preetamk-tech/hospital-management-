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

// Get all today's patients
$stmt = $pdo->prepare("
    SELECT t.token_id, t.token_number, t.token_prefix, p.full_name, p.age, p.gender, p.phone, 
           t.status, t.position, t.estimated_wait_time, a.consultation_type
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.doctor_id = ? AND t.queue_date = CURDATE()
    ORDER BY 
        CASE WHEN t.token_prefix = 'E' THEN 1
             WHEN t.token_prefix = 'S' THEN 2
             ELSE 3 END,
        t.position ASC
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title">Today's Patients</h2>
        <i class="fas fa-users"></i>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Token</th><th>Type</th><th>Patient Name</th><th>Age</th><th>Gender</th><th>Phone</th><th>Priority</th><th>Status</th><th>Action</th>
            </thead>
            <tbody>
                <?php foreach ($patients as $row): ?>
                <tr>
                    <td><strong>#<?php echo htmlspecialchars($row['token_number']); ?></strong></td>
                    <td>
                        <?php if (($row['consultation_type'] ?? 'physical') === 'video'): ?>
                            <span class="badge badge-success"><i class="fas fa-video"></i> Video</span>
                        <?php else: ?>
                            <span class="badge badge-info"><i class="fas fa-hospital"></i> Physical</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo $row['age']; ?></td>
                    <td><?php echo $row['gender']; ?></td>
                    <td><?php echo $row['phone']; ?></td>
                    <td>
                        <?php if ($row['token_prefix'] == 'S'): ?>
                            <span class="badge badge-success">Senior</span>
                        <?php elseif ($row['token_prefix'] == 'E'): ?>
                            <span class="badge badge-danger">Emergency</span>
                        <?php else: ?>
                            <span class="badge badge-info">Normal</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $row['status'] == 'waiting' ? 'warning' : ($row['status'] == 'called' ? 'info' : 'success'); ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['status'] == 'waiting'): ?>
                            <a href="call_next.php?call=<?php echo $row['token_id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">Call</a>
                        <?php elseif ($row['status'] == 'called'): ?>
                            <a href="call_next.php?complete=<?php echo $row['token_number']; ?>" class="btn btn-success" style="padding: 0.3rem 0.8rem;">Complete</a>
                        <?php else: ?>
                            <a href="add_medical_history.php?patient_id=<?php echo $row['token_id']; ?>" class="btn btn-info" style="padding: 0.3rem 0.8rem;">Add History</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>