<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT vq.*, d.full_name as doctor_name, d.specialization, p.full_name as patient_name
    FROM virtual_queue vq
    JOIN patients p ON vq.patient_id = p.patient_id
    JOIN doctors d ON vq.doctor_id = d.doctor_id
    WHERE p.user_id = ? AND vq.queue_date = ?
    ORDER BY vq.vq_id DESC LIMIT 1
");
$stmt->execute([$user_id, $today]);
$token = $stmt->fetch();

if ($token && $token['status'] == 'waiting') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM virtual_queue 
        WHERE queue_date = ? AND doctor_id = ? AND position < ? AND status IN ('waiting', 'called')
    ");
    $stmt->execute([$today, $token['doctor_id'], $token['position']]);
    $position_ahead = $stmt->fetch()['count'];
    $total_waiting = $pdo->prepare("SELECT COUNT(*) as count FROM virtual_queue WHERE queue_date = ? AND doctor_id = ? AND status = 'waiting'");
    $total_waiting->execute([$today, $token['doctor_id']]);
    $total_waiting = $total_waiting->fetch()['count'];
}

$action = $_POST['action'] ?? '';
if ($action === 'check_in' && $token && $token['status'] === 'called') {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'checked_in', checked_in_at = NOW() WHERE vq_id = ?");
    $stmt->execute([$token['vq_id']]);
    setMessage('Checked in successfully! Please proceed to the waiting area.', 'success');
    redirect('patient/virtual_queue.php');
}

if ($action === 'cancel' && $token && $token['status'] === 'waiting') {
    $stmt = $pdo->prepare("UPDATE virtual_queue SET status = 'cancelled' WHERE vq_id = ?");
    $stmt->execute([$token['vq_id']]);
    setMessage('Virtual queue token cancelled.', 'warning');
    redirect('patient/virtual_queue_join.php');
}

include '../includes/header.php';
?>

<?php if (!$token || $token['status'] === 'cancelled'): ?>
    <div class="card" style="text-align: center;">
        <i class="fas fa-clock" style="font-size: 4rem; color: #a0aec0;"></i>
        <h3>No Active Virtual Queue Token</h3>
        <p>You don't have any active virtual queue token for today.</p>
        <a href="virtual_queue_join.php" class="btn btn-primary">Join Virtual Queue</a>
    </div>
<?php else: ?>
    <?php if ($token['status'] === 'called'): ?>
        <div class="alert alert-danger" style="text-align: center; font-size: 1.2rem;">
            <i class="fas fa-bell fa-2x"></i><br>
            <strong>Your token is called!</strong><br>
            Please check in now to confirm your arrival.
        </div>
        <form method="POST" style="text-align: center; margin-top: 1rem;">
            <input type="hidden" name="action" value="check_in">
            <button type="submit" class="btn btn-success" style="font-size: 1.2rem; padding: 1rem 2rem;">
                <i class="fas fa-check-circle"></i> Check In Now
            </button>
        </form>
    <?php endif; ?>

    <?php if ($token['status'] === 'checked_in'): ?>
        <div class="alert alert-success" style="text-align: center; font-size: 1.2rem;">
            <i class="fas fa-check-circle fa-2x"></i><br>
            <strong>Checked In!</strong><br>
            Please wait in the hospital waiting area. You will be called soon.
        </div>
    <?php endif; ?>

    <div class="grid">
        <div class="card" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
            <div style="text-align: center;">
                <h3>Virtual Token</h3>
                <div class="token-number" style="font-size: 5rem;">#V<?php echo htmlspecialchars($token['token_number']); ?></div>
                <p><?php echo htmlspecialchars($token['patient_name']); ?></p>
                <p><i class="fas fa-stethoscope"></i> Dr. <?php echo htmlspecialchars($token['doctor_name']); ?></p>
                <p><?php echo htmlspecialchars($token['specialization']); ?></p>
                <span class="badge" style="background: rgba(255,255,255,0.3); font-size: 1rem; padding: 6px 16px; margin-top: 8px;">
                    <?php echo ucfirst($token['status']); ?>
                </span>
            </div>
        </div>
        
        <?php if ($token['status'] === 'waiting'): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Queue Status</h3>
                <i class="fas fa-chart-line"></i>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 3rem; font-weight: bold; color: #667eea;"><?php echo $position_ahead ?? 0; ?></div>
                <p>People ahead of you</p>
                <div style="margin: 1rem 0;">
                    <div style="background: #e2e8f0; border-radius: 10px; overflow: hidden; height: 12px;">
                        <div style="width: <?php echo ($total_waiting > 0) ? ((($total_waiting - $position_ahead) / $total_waiting) * 100) : 0; ?>%; background: linear-gradient(135deg, #667eea, #764ba2); height: 100%; transition: width 1s;"></div>
                    </div>
                </div>
                <p>Total waiting: <?php echo $total_waiting; ?> patients</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Estimated Wait</h3>
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div style="text-align: center;">
                <div id="timer" style="font-size: 3rem; font-weight: bold; color: #667eea;">
                    <?php echo ($position_ahead ?? 0) * 10; ?> min
                </div>
                <p>Based on 10 minutes per patient</p>
                <p style="color: #718096; font-size: 0.85rem; margin-top: 0.5rem;">
                    <i class="fas fa-info-circle"></i>
                    You'll get a notification when it's time to head to the hospital.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($token['status'] === 'waiting'): ?>
    <div class="card" style="text-align: center; margin-top: 1rem;">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel your virtual queue token?')">
                <i class="fas fa-times-circle"></i> Cancel Token
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card" id="liveStatus">
        <div class="card-header">
            <h3 class="card-title">Live Status</h3>
            <i class="fas fa-sync-alt" id="refreshIcon" style="cursor: pointer;"></i>
        </div>
        <div id="statusContent" style="text-align: center;">Checking status...</div>
    </div>

    <script src="<?php echo SITE_URL; ?>assets/js/websocket.js"></script>
    <script>
        let vqId = <?php echo $token['vq_id']; ?>;
        let waitSeconds = <?php echo ($position_ahead ?? 0) * 10 * 60; ?>;
        
        const ws = new HospitalWS();
        ws.connect(<?php echo $user_id; ?>, 'patient');
        ws.on('token_update', function(data) {
            if (data.status === 'called') {
                location.reload();
            }
        });
        
        setInterval(function() {
            $.ajax({
                url: '<?php echo SITE_URL; ?>api/check_virtual_queue_status.php',
                data: { vq_id: vqId },
                success: function(r) {
                    if (r.status === 'called') {
                        location.reload();
                    } else if (r.status === 'waiting') {
                        $('#statusContent').html(`
                            <p><strong>Your position:</strong> #${r.position}</p>
                            <p><strong>People ahead:</strong> ${r.position_ahead}</p>
                            <p><strong>Estimated wait:</strong> ${r.estimated_time} minutes</p>
                        `);
                    }
                }
            });
        }, 10000);
        
        $('#refreshIcon').click(function() {
            location.reload();
        });
        
        if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
