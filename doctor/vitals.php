<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    redirect('auth/login.php');
}

$stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();
$doctor_id = $doctor['doctor_id'];

$patients = [];
$vitals = [];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] == 'record_vitals') {
        $patient_id = (int)$_POST['patient_id'];
        $appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        $blood_pressure = sanitize($_POST['blood_pressure']);
        $heart_rate = isset($_POST['heart_rate']) && $_POST['heart_rate'] ? (int)$_POST['heart_rate'] : null;
        $temperature = isset($_POST['temperature']) && $_POST['temperature'] ? (float)$_POST['temperature'] : null;
        $oxygen_saturation = isset($_POST['oxygen_saturation']) && $_POST['oxygen_saturation'] ? (float)$_POST['oxygen_saturation'] : null;
        $weight = isset($_POST['weight']) && $_POST['weight'] ? (float)$_POST['weight'] : null;
        $height = isset($_POST['height']) && $_POST['height'] ? (float)$_POST['height'] : null;
        $respiratory_rate = isset($_POST['respiratory_rate']) && $_POST['respiratory_rate'] ? (int)$_POST['respiratory_rate'] : null;
        $blood_sugar = isset($_POST['blood_sugar']) && $_POST['blood_sugar'] ? (float)$_POST['blood_sugar'] : null;
        $notes = sanitize($_POST['vital_notes']);
        
        $stmt = $pdo->prepare("
            INSERT INTO patient_vitals (patient_id, doctor_id, appointment_id, blood_pressure, heart_rate, temperature, oxygen_saturation, weight, height, respiratory_rate, blood_sugar, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $doctor_id, $appointment_id, $blood_pressure, $heart_rate, $temperature, $oxygen_saturation, $weight, $height, $respiratory_rate, $blood_sugar, $notes]);
        $message = 'Vitals recorded successfully!';
    }
}

if (isset($_GET['patient_id'])) {
    $pid = (int)$_GET['patient_id'];
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$pid]);
    $selected_patient = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY recorded_at DESC");
    $stmt->execute([$pid]);
    $vitals = $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT p.*, a.appointment_id, a.appointment_date, t.token_number 
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    LEFT JOIN tokens t ON a.appointment_id = t.appointment_id AND t.queue_date = CURDATE()
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status != 'cancelled'
    ORDER BY t.position ASC
");
$stmt->execute([$doctor_id]);
$today_patients = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-heartbeat"></i> Patient Vitals</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="record_vitals">
        
        <div class="form-group">
            <label class="form-label">Select Patient *</label>
            <select name="patient_id" class="form-control" required onchange="if(this.value) window.location.href='?patient_id='+this.value;">
                <option value="">-- Select Patient --</option>
                <?php foreach ($today_patients as $p): ?>
                    <option value="<?php echo $p['patient_id']; ?>" <?php echo (isset($selected_patient) && $selected_patient['patient_id'] == $p['patient_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['age']; ?> yrs) - <?php echo $p['token_number']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if (isset($selected_patient)): ?>
        <input type="hidden" name="appointment_id" value="<?php echo $selected_patient['appointment_id']; ?>">
        
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="form-group">
                <label class="form-label">Blood Pressure (mmHg)</label>
                <input type="text" name="blood_pressure" class="form-control" placeholder="120/80">
            </div>
            <div class="form-group">
                <label class="form-label">Heart Rate (bpm)</label>
                <input type="number" name="heart_rate" class="form-control" placeholder="72">
            </div>
            <div class="form-group">
                <label class="form-label">Temperature (°F)</label>
                <input type="number" step="0.1" name="temperature" class="form-control" placeholder="98.6">
            </div>
            <div class="form-group">
                <label class="form-label">SpO2 (%)</label>
                <input type="number" step="0.1" name="oxygen_saturation" class="form-control" placeholder="98">
            </div>
            <div class="form-group">
                <label class="form-label">Weight (kg)</label>
                <input type="number" step="0.1" name="weight" class="form-control" placeholder="70">
            </div>
            <div class="form-group">
                <label class="form-label">Height (cm)</label>
                <input type="number" step="0.1" name="height" class="form-control" placeholder="170">
            </div>
            <div class="form-group">
                <label class="form-label">Respiratory Rate</label>
                <input type="number" name="respiratory_rate" class="form-control" placeholder="16">
            </div>
            <div class="form-group">
                <label class="form-label">Blood Sugar (mg/dL)</label>
                <input type="number" step="0.1" name="blood_sugar" class="form-control" placeholder="100">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="vital_notes" class="form-control" rows="2"></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Vitals</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<?php if (!empty($vitals)): ?>
<div class="box" style="margin-top: 1.5rem;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-history"></i> Vitals History - <?php echo htmlspecialchars($selected_patient['full_name']); ?></h2>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>BP</th>
                    <th>Heart Rate</th>
                    <th>Temp</th>
                    <th>SpO2</th>
                    <th>Weight</th>
                    <th>Blood Sugar</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vitals as $v): ?>
                <tr>
                    <td><?php echo date('d M Y, h:i A', strtotime($v['recorded_at'])); ?></td>
                    <td><?php echo $v['blood_pressure'] ?: '-'; ?></td>
                    <td><?php echo $v['heart_rate'] ?: '-'; ?> bpm</td>
                    <td><?php echo $v['temperature'] ?: '-'; ?> °F</td>
                    <td><?php echo $v['oxygen_saturation'] ?: '-'; ?>%</td>
                    <td><?php echo $v['weight'] ?: '-'; ?> kg</td>
                    <td><?php echo $v['blood_sugar'] ?: '-'; ?></td>
                    <td><?php echo $v['notes'] ?: '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
