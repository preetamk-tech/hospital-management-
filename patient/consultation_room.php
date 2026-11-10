<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient_ids = array_column($stmt->fetchAll(), 'patient_id');

$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT c.*, p.full_name as patient_name, d.full_name as doctor_name, dep.department_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.patient_id
    JOIN doctors d ON c.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    WHERE c.consultation_id = ? AND c.patient_id IN (" . implode(',', array_fill(0, count($patient_ids), '?')) . ")
");
$params = array_merge([$consultation_id], $patient_ids);
$stmt->execute($params);
$consultation = $stmt->fetch();

if (!$consultation) {
    die('Consultation not found');
}

if ($consultation['status'] == 'scheduled' && strtotime($consultation['scheduled_at']) > time() + 300) {
    setMessage("Consultation hasn't started yet.", "warning");
    redirect('patient/consultations.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - Dr. <?php echo htmlspecialchars($consultation['doctor_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; background: #1a1a2e; height: 100vh; display: flex; flex-direction: column; }
        .header { background: #16213e; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        .header h3 { font-size: 16px; }
        .header .info { font-size: 13px; color: #a0aec0; }
        .header .info span { background: #48bb78; padding: 3px 10px; border-radius: 12px; margin-left: 10px; }
        #jitsi-container { flex: 1; }
        .footer { background: #16213e; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
        .btn-leave { background: #f56565; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .timer { font-size: 14px; color: #a0aec0; }
        @media (max-width: 768px) { .header { flex-direction: column; gap: 5px; } }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h3><i class="fas fa-video"></i> Video Consultation</h3>
            <div class="info">
                Dr. <strong><?php echo htmlspecialchars($consultation['doctor_name']); ?></strong> — <?php echo htmlspecialchars($consultation['department_name']); ?>
                <span>LIVE</span>
            </div>
        </div>
        <div class="timer" id="timer">00:00:00</div>
    </div>
    
    <div id="jitsi-container"></div>
    
    <div class="footer">
        <span style="font-size:12px;color:#a0aec0;">Powered by Jitsi Meet (Free & Encrypted)</span>
        <a href="consultations.php" style="color:#667eea;text-decoration:none;font-size:14px;"><i class="fas fa-phone-slash"></i> Leave Call</a>
    </div>

    <script src='https://meet.jit.si/external_api.js'></script>
    <script>
        const api = new JitsiMeetExternalAPI('meet.jit.si', {
            roomName: '<?php echo $consultation['room_name']; ?>',
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: { displayName: '<?php echo htmlspecialchars($consultation['patient_name']); ?>' },
            configOverwrite: {
                startWithAudioMuted: false, startWithVideoMuted: false,
                disableModeratorIndicator: true, prejoinPageEnabled: false,
                toolbarButtons: ['camera','chat','desktop','hangup','microphone','participants','raisehand','settings','tileview','toggle-camera','videoquality']
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false, SHOW_WATERMARK_FOR_GUESTS: false,
                TOOLBAR_ALWAYS_VISIBLE: false
            }
        });
        api.addEventListener('videoConferenceLeft', function() { window.location.href = 'consultations.php'; });

        let seconds = 0;
        setInterval(function() {
            seconds++;
            const h = String(Math.floor(seconds/3600)).padStart(2,'0');
            const m = String(Math.floor((seconds%3600)/60)).padStart(2,'0');
            const s = String(seconds%60).padStart(2,'0');
            document.getElementById('timer').textContent = h+':'+m+':'+s;
        }, 1000);
    </script>
</body>
</html>
