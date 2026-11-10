<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get appointments
$stmt = $pdo->prepare("
    SELECT a.*, d.full_name as doctor_name, p.full_name as patient_name, 
           t.token_number, t.status as token_status, t.token_id
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.doctor_id 
    JOIN patients p ON a.patient_id = p.patient_id 
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id
    WHERE p.user_id = ? 
    ORDER BY a.appointment_date DESC, a.created_at DESC
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

// Cancel appointment
if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf_token']) || !verifyCSRFToken($_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $appointment_id = (int)$_GET['cancel'];
    
    // Check if appointment is in the future
    $stmt = $pdo->prepare("SELECT appointment_date FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$appointment_id]);
    $apt = $stmt->fetch();
    
    if ($apt && strtotime($apt['appointment_date']) >= strtotime(date('Y-m-d'))) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            
            $stmt = $pdo->prepare("UPDATE tokens SET status = 'cancelled' WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            
            $stmt = $pdo->prepare("UPDATE consultations SET status = 'cancelled' WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Cancel appointment failed: " . $e->getMessage());
        }
        
        setMessage("Appointment cancelled successfully!", "success");
    } else {
        setMessage("Cannot cancel past appointments!", "error");
    }
    redirect('patient/my_appointments.php');
}

include '../includes/header.php';
?>

<div class="box" style="margin-top: 70px; margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title">My Appointments</h2>
        <a href="book_appointment.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Appointment</a>
    </div>
    
    <?php if (empty($appointments)): ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-calendar-times" style="font-size: 3rem; color: #a0aec0;"></i>
            <p style="margin-top: 1rem;">No appointments found.</p>
            <a href="book_appointment.php" class="btn btn-primary">Book Your First Appointment</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Token</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $row):
                        $con_type = ($row['consultation_type'] ?? 'physical') === 'video' ? 'video' : 'physical';
                    ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                        <td>
                            <?php if ($con_type === 'video'): ?>
                                <span class="badge badge-success"><i class="fas fa-video"></i> Video</span>
                            <?php else: ?>
                                <span class="badge badge-info"><i class="fas fa-hospital"></i> Physical</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                        <td>
                            <?php if ($row['token_number']): ?>
                                <strong>#<?php echo $row['token_number']; ?></strong>
                                <?php if ($row['token_status'] == 'waiting'): ?>
                                    <a href="my_token.php" class="badge badge-info">Track</a>
                                <?php endif; ?>
                            <?php else: ?>
                                --
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $row['status'] == 'confirmed' ? 'success' : ($row['status'] == 'cancelled' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['status'] == 'confirmed' && strtotime($row['appointment_date']) >= strtotime(date('Y-m-d'))): ?>
                                <a href="?cancel=<?php echo $row['appointment_id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>" class="btn btn-danger" style="padding: 0.3rem 0.8rem;" onclick="return confirm('Cancel this appointment?')">
                                    Cancel
                                </a>
                            <?php endif; ?>
                            <?php if ($con_type === 'video' && $row['status'] == 'confirmed'): ?>
                                <a href="consultations.php" class="btn btn-success" style="padding: 0.3rem 0.8rem;">
                                    <i class="fas fa-video"></i> Consultations
                                </a>
                            <?php endif; ?>
                            <?php if ($row['status'] == 'completed' && !isset($row['feedback_id'])): ?>
                                <a href="feedback.php?appointment=<?php echo $row['appointment_id']; ?>" class="btn btn-warning" style="padding: 0.3rem 0.8rem;">Rate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>