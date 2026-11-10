<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT c.*, p.full_name as patient_name, p.age, p.gender, d.full_name as doctor_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.patient_id
    JOIN doctors d ON c.doctor_id = d.doctor_id
    WHERE c.consultation_id = ?
");
$stmt->execute([$consultation_id]);
$consultation = $stmt->fetch();

if (!$consultation) {
    die('Consultation not found');
}

$is_doctor = hasRole('doctor') && $consultation['doctor_id'] == $pdo->query("SELECT doctor_id FROM doctors WHERE user_id = " . (int)$_SESSION['user_id'])->fetchColumn();
$is_patient = hasRole('patient');

if (!$is_doctor && !$is_patient) {
    die('Unauthorized');
}

if ($consultation['status'] == 'scheduled' && strtotime($consultation['scheduled_at']) > time() + 300) {
    setMessage("Consultation hasn't started yet. Please wait.", "warning");
    redirect($is_doctor ? 'doctor/telemedicine.php' : 'patient/consultations.php');
}

if ($consultation['status'] == 'scheduled' && $is_doctor) {
    $pdo->prepare("UPDATE consultations SET status = 'active', started_at = NOW() WHERE consultation_id = ?")->execute([$consultation_id]);
    $consultation['status'] = 'active';
}

$room_name = $consultation['room_name'];
$jitsi_domain = 'meet.jit.si';
$display_name = $is_doctor ? 'Dr. ' . $consultation['doctor_name'] : $consultation['patient_name'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - <?php echo htmlspecialchars($consultation['patient_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; background: #1a1a2e; height: 100vh; display: flex; flex-direction: column; }
        .header { background: #16213e; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        .header h3 { font-size: 16px; }
        .header .info { font-size: 13px; color: #a0aec0; }
        .header .info span { background: #667eea; padding: 3px 10px; border-radius: 12px; margin-left: 10px; }
        #jitsi-container { flex: 1; }
        .footer { background: #16213e; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        .btn-end { background: #f56565; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-end:hover { background: #e53e3e; }
        .timer { font-size: 14px; color: #a0aec0; }
        @media (max-width: 768px) { .header { flex-direction: column; gap: 5px; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h3><i class="fas fa-video"></i> Video Consultation</h3>
            <div class="info">
                Patient: <strong><?php echo htmlspecialchars($consultation['patient_name']); ?></strong> (<?php echo $consultation['age']; ?> yrs)
                <span><?php echo strtoupper($consultation['status']); ?></span>
            </div>
        </div>
        <div class="timer" id="timer">00:00:00</div>
    </div>
    
    <div id="jitsi-container"></div>
    
    <div class="footer">
        <span style="font-size:12px;color:#a0aec0;">Powered by Jitsi Meet (Free & Encrypted)</span>
        <?php if ($is_doctor): ?>
        <form method="POST" action="telemedicine.php">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
            <input type="hidden" name="action" value="end">
            <input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
            <button type="submit" class="btn-end"><i class="fas fa-phone-slash"></i> End Consultation</button>
        </form>
        <?php else: ?>
        <a href="consultations.php" style="color:#667eea;text-decoration:none;font-size:14px;"><i class="fas fa-arrow-left"></i> Back to Consultations</a>
        <?php endif; ?>
    </div>

    <script src='https://meet.jit.si/external_api.js'></script>
    <script>
        const domain = '<?php echo $jitsi_domain; ?>';
        const roomName = '<?php echo $room_name; ?>';
        
        const api = new JitsiMeetExternalAPI(domain, {
            roomName: roomName,
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: {
                displayName: '<?php echo $display_name; ?>'
            },
            configOverwrite: {
                startWithAudioMuted: false,
                startWithVideoMuted: false,
                disableModeratorIndicator: true,
                enableEmailInStats: false,
                prejoinPageEnabled: false,
                toolbarButtons: [
                    'camera', 'chat', 'desktop', 'fodeviceselection', 'hangup',
                    'microphone', 'participants', 'raisehand', 'settings',
                    'shortcuts', 'tileview', 'toggle-camera', 'videoquality'
                ]
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                TOOLBAR_ALWAYS_VISIBLE: false,
                DEFAULT_REMOTE_DISPLAY_NAME: 'Participant'
            }
        });

        api.addEventListener('videoConferenceLeft', function() {
            window.location.href = 'telemedicine.php';
        });

        let seconds = 0;
        const timerEl = document.getElementById('timer');
        setInterval(function() {
            seconds++;
            const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            timerEl.textContent = h + ':' + m + ':' + s;
        }, 1000);
    </script>
</body>
</html>
