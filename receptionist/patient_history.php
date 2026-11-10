<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

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
$patient_details = null;

if ($selected_patient) {
    // Get patient details
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$selected_patient]);
    $patient_details = $stmt->fetch();
    
    // Get appointment history
    $stmt = $pdo->prepare("
        SELECT a.*, d.full_name as doctor_name, t.token_number,
               f.rating, f.comment as feedback_comment
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN tokens t ON a.appointment_id = t.appointment_id
        LEFT JOIN feedback f ON a.appointment_id = f.appointment_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([$selected_patient]);
    $history = $stmt->fetchAll();
    
    // Get medical history
    $stmt = $pdo->prepare("
        SELECT m.*, d.full_name as doctor_name
        FROM medical_history m
        JOIN doctors d ON m.doctor_id = d.doctor_id
        WHERE m.patient_id = ?
        ORDER BY m.visit_date DESC
    ");
    $stmt->execute([$selected_patient]);
    $medical_history = $stmt->fetchAll();
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
                            <td><?php echo $row['is_senior'] ? '<span class="badge badge-success">Yes</span>' : 'No'; ?></td>
                            <td>
                                <a href="?search=<?php echo urlencode($search); ?>&patient_id=<?php echo $row['patient_id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem;">
                                    View History
                                </a>
                                <a href="walkin_appointment.php?patient_id=<?php echo $row['patient_id']; ?>" class="btn btn-success" style="padding: 0.3rem 0.8rem;">
                                    Book
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
    
    <?php if ($selected_patient && $patient_details): ?>
    <div class="box" style="margin-top: 70px;">
        <div class="box-header">
            <h2 class="box-title">Patient History: <?php echo htmlspecialchars($patient_details['full_name']); ?></h2>
            <i class="fas fa-notes-medical"></i>
        </div>
        
        <div class="patient-info" style="background: #f7fafc; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <div class="grid" style="grid-template-columns: repeat(3, 1fr);">
                <div><strong>Age:</strong> <?php echo $patient_details['age']; ?></div>
                <div><strong>Gender:</strong> <?php echo $patient_details['gender']; ?></div>
                <div><strong>Phone:</strong> <?php echo $patient_details['phone']; ?></div>
                <div><strong>Senior Citizen:</strong> <?php echo $patient_details['is_senior'] ? 'Yes' : 'No'; ?></div>
                <div><strong>Registered:</strong> <?php echo date('d M Y', strtotime($patient_details['created_at'])); ?></div>
            </div>
        </div>
        
        <h3>Appointment History</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Token</th>
                        <th>Status</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                        <td><strong>#<?php echo $row['token_number'] ?? '--'; ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $row['status'] == 'completed' ? 'success' : ($row['status'] == 'cancelled' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['rating']): ?>
                                <?php echo str_repeat('⭐', $row['rating']); ?>
                            <?php else: ?>
                                --
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <h3 style="margin-top: 2rem;">Medical History</h3>
        <?php if (empty($medical_history)): ?>
            <p style="text-align: center; padding: 2rem;">No medical records found.</p>
        <?php else: ?>
            <?php foreach ($medical_history as $row): ?>
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
        
        <div style="margin-top: 1rem;">
            <a href="walkin_appointment.php?patient_id=<?php echo $selected_patient; ?>" class="btn btn-primary">
                <i class="fas fa-calendar-plus"></i> Book New Appointment
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>