<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Ensure patient record exists
$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient_record = $stmt->fetch();
if (!$patient_record) {
    setMessage('Patient profile not found. Please update your profile.', 'error');
    redirect('patient/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setMessage('Invalid security token. Please try again.', 'error');
        redirect('patient/virtual_queue_join.php');
    }

    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $today = date('Y-m-d');

    if ($doctor_id <= 0) {
        setMessage('Please select a valid doctor.', 'error');
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE patient_id = ? AND queue_date = ? AND status IN ('waiting', 'called', 'checked_in')");
        $stmt->execute([$patient_record['patient_id'], $today]);
        $existing = $stmt->fetch()['count'];

        if ($existing > 0) {
            setMessage('You already have an active virtual queue token for today.', 'error');
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(token_number), 0) + 1 as next_token
                FROM virtual_queue
                WHERE queue_date = ? AND doctor_id = ?
            ");
            $stmt->execute([$today, $doctor_id]);
            $next_token = $stmt->fetch()['next_token'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND doctor_id = ? AND status IN ('waiting', 'called')");
            $stmt->execute([$today, $doctor_id]);
            $position = $stmt->fetch()['count'] + 1;

            $estimated_wait = $position * 10;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO virtual_queue (patient_id, doctor_id, token_number, token_prefix, queue_date, position, joined_at, estimated_wait_minutes)
                    VALUES (?, ?, ?, 'V', ?, ?, ?, ?)
                ");
                $stmt->execute([$patient_record['patient_id'], $doctor_id, $next_token, $today, date('Y-m-d H:i:s'), $estimated_wait]);

                setMessage('Successfully joined virtual queue! Token #V' . $next_token, 'success');
                redirect('patient/virtual_queue.php');
            } catch (Exception $e) {
                error_log("Virtual queue join failed: " . $e->getMessage());
                setMessage('Failed to join queue. Please try again.', 'error');
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT d.doctor_id, d.full_name, d.specialization, 
           (SELECT COUNT(*) FROM virtual_queue vq WHERE vq.doctor_id = d.doctor_id AND vq.queue_date = CURDATE() AND vq.status IN ('waiting','called')) as queue_length
    FROM doctors d WHERE d.is_available = 1 ORDER BY d.full_name
");
$stmt->execute();
$doctors = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Join Virtual Queue</h3>
        <i class="fas fa-clock"></i>
    </div>
    <p style="color: #718096; margin-bottom: 1.5rem;">
        Join the queue from home. You'll get a token and can track your position in real-time.
        You'll be notified when it's time to check in at the hospital.
    </p>
    
    <form method="POST" class="form" id="joinQueueForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="form-group">
            <label>Select Doctor</label>
            <select name="doctor_id" required class="form-control" id="doctorSelect">
                <option value="">-- Choose a Doctor --</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?php echo $doc['doctor_id']; ?>">
                        Dr. <?php echo htmlspecialchars($doc['full_name']); ?> 
                        - <?php echo htmlspecialchars($doc['specialization']); ?>
                        (<?php echo $doc['queue_length']; ?> in queue)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="queueInfo" style="display:none; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
            <h4 style="color: #2d3748; margin-bottom: 0.5rem;"><i class="fas fa-info-circle"></i> Queue Details</h4>
            <p><strong>Estimated Wait:</strong> <span id="estWait">--</span> minutes</p>
            <p><strong>Your Position:</strong> <span id="estPosition">--</span></p>
            <p style="color: #718096; font-size: 0.85rem; margin-top: 0.5rem;">
                You must check in within 15 minutes of your token being called.
            </p>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-sign-in-alt"></i> Join Queue
        </button>
    </form>
</div>

<script>
document.getElementById('doctorSelect').addEventListener('change', function() {
    const info = document.getElementById('queueInfo');
    if (this.value) {
        const selected = this.options[this.selectedIndex];
        const queueLength = parseInt(selected.text.match(/\((\d+) in queue\)/)?.[1] || 0);
        document.getElementById('estPosition').textContent = '#' + (queueLength + 1);
        document.getElementById('estWait').textContent = (queueLength + 1) * 10;
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
