<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get current token
$stmt = $pdo->prepare("
    SELECT t.*, a.appointment_date, d.full_name as doctor_name, 
           p.full_name as patient_name, p.age
    FROM tokens t 
    JOIN appointments a ON t.appointment_id = a.appointment_id 
    JOIN patients p ON a.patient_id = p.patient_id 
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE p.user_id = ? AND t.status IN ('waiting', 'called') AND t.queue_date = CURDATE()
    ORDER BY t.token_id DESC LIMIT 1
");
$stmt->execute([$user_id]);
$token = $stmt->fetch();

if ($token) {
    // Get position ahead
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM tokens 
        WHERE queue_date = CURDATE() AND status = 'waiting' AND position < ?
    ");
    $stmt->execute([$token['position']]);
    $position_ahead = $stmt->fetch()['count'];
    
    // Get total waiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM tokens 
        WHERE queue_date = CURDATE() AND status = 'waiting'
    ");
    $stmt->execute();
    $total_waiting = $stmt->fetch()['count'];
    
    // Get current serving
    $stmt = $pdo->prepare("
        SELECT token_number FROM tokens 
        WHERE queue_date = CURDATE() AND status = 'called' 
        ORDER BY called_at DESC LIMIT 1
    ");
    $stmt->execute();
    $current_serving = $stmt->fetch();
}

include '../includes/header.php';
?>

<?php if (!$token): ?>
    <div class="card" style="text-align: center;">
        <i class="fas fa-ticket-alt" style="font-size: 4rem; color: #a0aec0;"></i>
        <h3>No Active Token</h3>
        <p>You don't have any active token for today.</p>
        <a href="book_appointment.php" class="btn btn-primary">Book Appointment</a>
    </div>
<?php else: ?>
    <div class="grid">
        <div class="card" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
            <div style="text-align: center;">
                <h3>Your Token Number</h3>
                <div class="token-number" style="font-size: 5rem;">#<?php echo htmlspecialchars($token['token_number']); ?></div>
                <p><?php echo htmlspecialchars($token['patient_name']); ?> 
                    <?php if ($token['age'] >= 60): ?> 
                        <span class="badge" style="background: rgba(255,255,255,0.3);">Senior Citizen</span>
                    <?php endif; ?>
                </p>
                <p><i class="fas fa-stethoscope"></i> Dr. <?php echo htmlspecialchars($token['doctor_name']); ?></p>
                <p><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($token['queue_date'])); ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Queue Status</h3>
                <i class="fas fa-chart-line"></i>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 3rem; font-weight: bold; color: #667eea;"><?php echo $position_ahead; ?></div>
                <p>People ahead of you</p>
                <div style="margin: 1rem 0;">
                    <div style="background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                        <div style="width: <?php echo ($total_waiting > 0) ? (($position_ahead / $total_waiting) * 100) : 0; ?>%; background: linear-gradient(135deg, #667eea, #764ba2); height: 10px;"></div>
                    </div>
                </div>
                <p>Total waiting: <?php echo $total_waiting; ?> patients</p>
                <p>Currently serving: <strong id="currentServing">#<?php echo $current_serving['token_number'] ?? '--'; ?></strong></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Estimated Wait Time</h3>
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div style="text-align: center;">
                <div id="timer" class="timer" style="font-size: 3rem; font-weight: bold; color: #667eea;">
                    <?php 
                    $wait_minutes = $position_ahead * 10;
                    echo $wait_minutes . ' min';
                    ?>
                </div>
                <p>Based on 10 minutes per patient</p>
                <button class="btn btn-primary" onclick="window.location.href='<?php echo SITE_URL; ?>api/generate_qr.php?token_id=<?php echo $token['token_id']; ?>'">
                    <i class="fas fa-qrcode"></i> Show QR Code
                </button>
            </div>
        </div>
    </div>
    
    <div class="card" id="liveStatus">
        <div class="card-header">
            <h3 class="card-title">Live Status</h3>
            <i class="fas fa-sync-alt" id="refreshIcon" style="cursor: pointer;"></i>
        </div>
        <div id="statusContent" style="text-align: center;">Checking status...</div>
    </div>
    
    <script src="<?php echo SITE_URL; ?>assets/js/websocket.js"></script>
    <script>
        let tokenId = <?php echo $token['token_id']; ?>;
        let waitSeconds = <?php echo $position_ahead * 10 * 60; ?>;
        let timerInterval;
        
        // Connect to WebSocket
        const ws = new HospitalWS();
        ws.connect(<?php echo $_SESSION['user_id']; ?>, 'patient');
        ws.on('token_update', function(data) {
            if (data.status === 'called') {
                $('#statusContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-bell"></i> Your token is called! 
                        Please proceed to the doctor's room immediately.
                    </div>
                `);
                clearInterval(timerInterval);
            }
            const aheadEl = document.getElementById('peopleAhead');
            const waitEl = document.getElementById('waitTime');
            const serveEl = document.getElementById('currentServing');
            if (aheadEl) aheadEl.textContent = data.people_ahead;
            if (waitEl) waitEl.textContent = data.estimated_wait + ' min';
            if (serveEl && data.position) serveEl.textContent = '#' + data.position;
        });
        
        function updateTimer() {
            if (waitSeconds > 0) {
                waitSeconds--;
                let mins = Math.floor(waitSeconds / 60);
                let secs = waitSeconds % 60;
                document.getElementById('timer').innerHTML = `${mins}:${secs.toString().padStart(2,'0')}`;
            } else {
                clearInterval(timerInterval);
            }
        }
        
        timerInterval = setInterval(updateTimer, 1000);
        
        function checkStatus() {
            $.ajax({
                url: '<?php echo SITE_URL; ?>api/check_token_status.php',
                data: { token_id: tokenId },
                success: function(r) {
                    if (r.status === 'called') {
                        $('#statusContent').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-bell"></i> Your token is called! 
                                Please proceed to the doctor's room immediately.
                            </div>
                        `);
                        showToast('Token #' + r.token_number + ' is called! Please go to doctor.', 'warning');
                        clearInterval(timerInterval);
                    } else if (r.status === 'completed') {
                        $('#statusContent').html(`
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Your consultation is completed. 
                                Thank you for visiting!
                            </div>
                        `);
                        clearInterval(timerInterval);
                    } else {
                        $('#statusContent').html(`
                            <p><strong>Currently serving:</strong> #${r.current_serving || '--'}</p>
                            <p><strong>Your position:</strong> #${r.position}</p>
                            <p id="peopleAhead"><strong>People ahead:</strong> ${r.position_ahead}</p>
                            <p id="waitTime"><strong>Estimated wait:</strong> ${r.estimated_time} minutes</p>
                        `);
                    }
                }
            });
        }
        
        setInterval(checkStatus, 10000);
        checkStatus();
        
        $('#refreshIcon').click(function() {
            checkStatus();
            $(this).addClass('fa-spin');
            setTimeout(() => $(this).removeClass('fa-spin'), 1000);
        });
        
        if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>