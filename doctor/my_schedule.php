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

// Get upcoming appointments
$stmt = $pdo->prepare("
    SELECT a.*, p.full_name as patient_name, p.age, p.phone, p.gender,
           t.token_number, t.status as token_status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id
    WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, t.position ASC
");
$stmt->execute([$doctor_id]);
$appointments = $stmt->fetchAll();

// Get doctor schedule
$stmt = $pdo->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = ? ORDER BY day_of_week");
$stmt->execute([$doctor_id]);
$schedule = $stmt->fetchAll();

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

include '../includes/header.php';
?>

<div class="grid">
    <div class="box" style="margin-top: 70px;">
        <div class="box-header">
            <h2 class="box-title">My Schedule</h2>
            <i class="fas fa-calendar-alt"></i>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Slot Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $schedule_by_day = [];
                    foreach ($schedule as $sched) {
                        $schedule_by_day[$sched['day_of_week']] = $sched;
                    }
                    
                    for ($i = 1; $i <= 7; $i++): 
                        $sched = $schedule_by_day[$i] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $days[$i-1]; ?></td>
                        <td><?php echo $sched ? date('h:i A', strtotime($sched['start_time'])) : '--'; ?></td>
                        <td><?php echo $sched ? date('h:i A', strtotime($sched['end_time'])) : '--'; ?></td>
                        <td><?php echo $sched ? $sched['slot_duration'] . ' min' : '--'; ?></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="box" style="margin-top: 70px;">
        <div class="box-header">
            <h2 class="box-title">Upcoming Appointments</h2>
            <i class="fas fa-list"></i>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Age/Gender</th>
                        <th>Token</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $row): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                        <td><?php echo $row['age'] . '/' . $row['gender']; ?></td>
                        <td><strong>#<?php echo $row['token_number'] ?? '--'; ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $row['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>