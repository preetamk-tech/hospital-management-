<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('driver')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM ambulances WHERE user_id = ?");
$stmt->execute([$user_id]);
$ambulance = $stmt->fetch();

if (!$ambulance) {
    $ambulance = ['ambulance_id' => 0, 'vehicle_number' => 'N/A', 'ambulance_type' => '', 'status' => ''];
}
$ambulance_id = (int)$ambulance['ambulance_id'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status' && $ambulance_id > 0) {
        $booking_id = (int)$_POST['booking_id'];
        $new_status = sanitize($_POST['new_status']);
        $valid = ['assigned','dispatched','on_way','at_pickup','en_route','at_hospital','completed'];
        
        if (in_array($new_status, $valid)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE ambulance_bookings SET status = ? WHERE booking_id = ? AND ambulance_id = ?");
                $stmt->execute([$new_status, $booking_id, $ambulance_id]);

                $now = date('Y-m-d H:i:s');
                if ($new_status === 'dispatched') {
                    $pdo->prepare("UPDATE ambulance_bookings SET dispatched_at = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
                } elseif ($new_status === 'at_pickup') {
                    $pdo->prepare("UPDATE ambulance_bookings SET arrived_at_pickup = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
                } elseif ($new_status === 'at_hospital') {
                    $pdo->prepare("UPDATE ambulance_bookings SET arrived_at_hospital = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
                } elseif ($new_status === 'completed') {
                    $pdo->prepare("UPDATE ambulance_bookings SET completed_at = ? WHERE booking_id = ?")->execute([$now, $booking_id]);
                    $pdo->prepare("UPDATE ambulances SET status = 'available' WHERE ambulance_id = ?")->execute([$ambulance_id]);
                }

                $ambulanceStatus = ($new_status === 'completed') ? 'available' : $new_status;
                $pdo->prepare("UPDATE ambulances SET status = ? WHERE ambulance_id = ?")->execute([$ambulanceStatus, $ambulance_id]);

                $pdo->commit();
                setMessage('Status updated to ' . ucfirst(str_replace('_',' ',$new_status)), 'success');
            } catch (Exception $e) {
                $pdo->rollBack();
                setMessage('Failed to update status.', 'error');
            }
            redirect('drivers/dashboard.php');
        }
    }
}

$currentBooking = null;
if ($ambulance_id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.full_name as patient_name, p.age, p.gender, p.phone as patient_phone,
               u.full_name as booked_by
        FROM ambulance_bookings b
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN users u ON b.booked_by = u.user_id
        WHERE b.ambulance_id = ? AND b.status IN ('assigned','dispatched','on_way','at_pickup','en_route','at_hospital')
        ORDER BY b.created_at DESC LIMIT 1
    ");
    $stmt->execute([$ambulance_id]);
    $currentBooking = $stmt->fetch();
}

$history = [];
if ($ambulance_id > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.full_name as patient_name
        FROM ambulance_bookings b
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.ambulance_id = ? AND b.status IN ('completed','cancelled')
        ORDER BY b.created_at DESC LIMIT 10
    ");
    $stmt->execute([$ambulance_id]);
    $history = $stmt->fetchAll();
}
?>
<?php
$statusColors = [
    'assigned'   => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-user-check'],
    'dispatched' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-truck-fast'],
    'on_way'     => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-route'],
    'at_pickup'  => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-location-dot'],
    'en_route'   => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-road'],
    'at_hospital'=> ['bg' => '#f3f4f6', 'text' => '#374151', 'icon' => 'fa-hospital'],
    'completed'  => ['bg' => '#d1fae5', 'text' => '#065f46', 'icon' => 'fa-check-circle'],
];
$nextActions = [
    'assigned'   => ['status' => 'on_way',     'label' => 'Start Moving (On Way)',   'icon' => 'fa-play',         'color' => '#f59e0b'],
    'dispatched' => ['status' => 'on_way',     'label' => 'Start Moving (On Way)',   'icon' => 'fa-play',         'color' => '#f59e0b'],
    'on_way'     => ['status' => 'at_pickup',  'label' => 'Arrived at Pickup',       'icon' => 'fa-map-marker-alt','color' => '#3b82f6'],
    'at_pickup'  => ['status' => 'en_route',   'label' => 'Depart to Hospital',      'icon' => 'fa-hospital',     'color' => '#8b5cf6'],
    'en_route'   => ['status' => 'at_hospital','label' => 'Arrived at Hospital',     'icon' => 'fa-building',     'color' => '#10b981'],
    'at_hospital'=> ['status' => 'completed',  'label' => 'Complete Trip',           'icon' => 'fa-flag-checkered','color' => '#059669'],
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Driver Dashboard - <?php echo htmlspecialchars($ambulance['vehicle_number']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f3f4f6; min-height:100vh; padding-bottom:80px; }
        .header { background:linear-gradient(135deg,#1e3a5f,#2d5a87); color:#fff; padding:16px; position:sticky; top:0; z-index:100; }
        .header-top { display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:18px; font-weight:700; }
        .header-sub { font-size:12px; opacity:0.8; margin-top:4px; }
        .logout-btn { background:rgba(255,255,255,0.2); border:none; color:#fff; padding:8px 14px; border-radius:8px; font-size:13px; cursor:pointer; }
        .container { padding:16px; max-width:600px; margin:0 auto; }
        .card { background:#fff; border-radius:16px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,0.08); overflow:hidden; }
        .card-header { padding:14px 16px; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center; }
        .card-title { font-size:15px; font-weight:700; color:#111827; }
        .card-body { padding:16px; }
        .vehicle-badge { display:inline-flex; align-items:center; gap:6px; background:#dbeafe; color:#1e40af; padding:6px 12px; border-radius:8px; font-size:13px; font-weight:600; }
        .status-badge { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; }
        .patient-info { display:flex; align-items:center; gap:12px; padding:12px; background:#f9fafb; border-radius:12px; margin-bottom:12px; }
        .patient-avatar { width:48px; height:48px; background:linear-gradient(135deg,#667eea,#764ba2); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:18px; }
        .patient-name { font-weight:700; font-size:16px; color:#111827; }
        .patient-detail { font-size:13px; color:#6b7280; }
        .info-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f3f4f6; font-size:14px; color:#374151; }
        .info-row:last-child { border-bottom:none; }
        .info-row i { width:20px; text-align:center; color:#667eea; }
        .info-row a { color:#10b981; font-weight:600; text-decoration:none; }
        .action-btn { display:block; width:100%; padding:16px; border:none; border-radius:12px; font-size:16px; font-weight:700; color:#fff; cursor:pointer; transition:transform 0.2s; text-align:center; }
        .action-btn:hover { transform:translateY(-2px); }
        .action-btn:active { transform:translateY(0); }
        .map-container { height:200px; border-radius:12px; margin-top:12px; }
        .no-booking { text-align:center; padding:2rem; color:#6b7280; }
        .no-booking i { font-size:48px; color:#d1d5db; margin-bottom:1rem; }
        .history-item { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f3f4f6; }
        .history-item:last-child { border-bottom:none; }
        .tabs { display:flex; background:#fff; position:fixed; bottom:0; left:0; right:0; border-top:1px solid #e5e7eb; z-index:100; }
        .tab { flex:1; text-align:center; padding:12px; font-size:12px; color:#6b7280; cursor:pointer; transition:color 0.2s; }
        .tab.active { color:#667eea; font-weight:600; }
        .tab i { display:block; font-size:20px; margin-bottom:4px; }
        .msg-success { background:#d1fae5; color:#065f46; padding:12px; border-radius:10px; margin-bottom:12px; font-size:14px; }
        .msg-error { background:#fee2e2; color:#991b1b; padding:12px; border-radius:10px; margin-bottom:12px; font-size:14px; }
        .nav-btn { background:none; border:none; color:#fff; font-size:14px; cursor:pointer; display:flex; align-items:center; gap:4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div>
                <h1><i class="fas fa-ambulance"></i> <?php echo htmlspecialchars($ambulance['vehicle_number']); ?></h1>
                <div class="header-sub"><?php echo htmlspecialchars($_SESSION['full_name']); ?> — <?php echo ucfirst($ambulance['ambulance_type'] ?? ''); ?> Ambulance</div>
            </div>
            <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
        </div>
        <div style="display:flex; gap:8px; margin-top:12px;">
            <?php
            $sc = $statusColors[$ambulance['status']] ?? $statusColors['available'];
            ?>
            <span class="status-badge" style="background:<?php echo $sc['bg']; ?>; color:<?php echo $sc['text']; ?>;">
                <i class="fas <?php echo $sc['icon']; ?>"></i> <?php echo ucfirst(str_replace('_',' ',$ambulance['status'])); ?>
            </span>
            <button id="shareLocationBtn" style="margin-left:auto;background:rgba(255,255,255,0.2);border:none;color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;cursor:pointer;">
                <i class="fas fa-location-crosshairs"></i> Share Location
            </button>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="msg-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="msg-error"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <?php if ($currentBooking): ?>
            <?php
            $ba = $nextActions[$currentBooking['status']] ?? null;
            $bsc = $statusColors[$currentBooking['status']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'icon' => 'fa-circle'];
            ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-bell"></i> Current Trip</span>
                    <span class="status-badge" style="background:<?php echo $bsc['bg']; ?>; color:<?php echo $bsc['text']; ?>;">
                        <?php echo ucfirst(str_replace('_',' ',$currentBooking['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;">
                        <strong><i class="fas fa-hashtag" style="color:#16a34a;"></i> <?php echo htmlspecialchars($currentBooking['booking_number']); ?></strong>
                        <?php if ($currentBooking['emergency_type'] === 'emergency'): ?>
                            <span style="float:right;background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:4px;font-weight:600;font-size:11px;">EMERGENCY</span>
                        <?php endif; ?>
                    </div>

                    <div class="patient-info">
                        <div class="patient-avatar"><?php echo strtoupper(substr($currentBooking['patient_name'], 0, 1)); ?></div>
                        <div>
                            <div class="patient-name"><?php echo htmlspecialchars($currentBooking['patient_name']); ?></div>
                            <div class="patient-detail">Age: <?php echo (int)$currentBooking['age']; ?> / <?php echo ucfirst($currentBooking['gender']); ?></div>
                        </div>
                    </div>

                    <div class="info-row">
                        <i class="fas fa-phone"></i>
                        <span>Patient: <a href="tel:<?php echo $currentBooking['patient_phone']; ?>"><?php echo htmlspecialchars($currentBooking['patient_phone']); ?></a></span>
                    </div>
                    <div class="info-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Pickup: <?php echo htmlspecialchars($currentBooking['pickup_address']); ?></span>
                    </div>
                    <?php if ($currentBooking['notes']): ?>
                    <div class="info-row">
                        <i class="fas fa-sticky-note"></i>
                        <span style="color:#f59e0b;"><?php echo htmlspecialchars($currentBooking['notes']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="map-container" id="trip_map"></div>
                    <div style="margin-top:8px;">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $currentBooking['pickup_lat']; ?>,<?php echo $currentBooking['pickup_lng']; ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;color:#10b981;font-size:13px;font-weight:600;text-decoration:none;">
                            <i class="fas fa-directions"></i> Navigate to Pickup (Google Maps)
                        </a>
                    </div>

                    <?php if ($ba): ?>
                        <form method="POST" style="margin-top:16px;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="<?php echo $currentBooking['booking_id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $ba['status']; ?>">
                            <button type="submit" class="action-btn" style="background:<?php echo $ba['color']; ?>;">
                                <i class="fas <?php echo $ba['icon']; ?>"></i> <?php echo $ba['label']; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="no-booking">
                    <i class="fas fa-mug-hot"></i>
                    <h3 style="color:#374151;margin-bottom:8px;">No Active Trips</h3>
                    <p style="font-size:14px;">Waiting for dispatch from control room.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($history)): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-history"></i> Recent Trips</span>
            </div>
            <div class="card-body">
                <?php foreach ($history as $h): ?>
                <div class="history-item">
                    <div>
                        <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($h['patient_name']); ?></div>
                        <div style="font-size:12px;color:#9ca3af;"><?php echo htmlspecialchars($h['booking_number']); ?> — <?php echo date('d M', strtotime($h['created_at'])); ?></div>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:<?php echo $h['status'] === 'completed' ? '#059669' : '#dc2626'; ?>;">
                        <?php echo ucfirst($h['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    var shareLocationBtn = document.getElementById('shareLocationBtn');
    var watchId = null;

    shareLocationBtn.addEventListener('click', function() {
        if (!navigator.geolocation) { alert('GPS not supported'); return; }
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sharing...';
        this.disabled = true;

        navigator.geolocation.getCurrentPosition(function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;

            fetch('../api/driver_location.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({lat: lat, lng: lng, ambulance_id: <?php echo $ambulance_id; ?>})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                shareLocationBtn.innerHTML = '<i class="fas fa-check"></i> Shared!';
                shareLocationBtn.disabled = false;
                setTimeout(function() {
                    shareLocationBtn.innerHTML = '<i class="fas fa-location-crosshairs"></i> Share Location';
                }, 3000);
            })
            .catch(function() {
                shareLocationBtn.innerHTML = '<i class="fas fa-times"></i> Failed';
                shareLocationBtn.disabled = false;
            });
        }, function() {
            shareLocationBtn.innerHTML = '<i class="fas fa-times"></i> GPS Error';
            shareLocationBtn.disabled = false;
        }, {enableHighAccuracy: true});
    });

    <?php if ($currentBooking): ?>
    var tripMap = L.map('trip_map').setView([<?php echo $currentBooking['pickup_lat']; ?>, <?php echo $currentBooking['pickup_lng']; ?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OSM'}).addTo(tripMap);
    L.marker([<?php echo $currentBooking['pickup_lat']; ?>, <?php echo $currentBooking['pickup_lng']; ?>])
        .addTo(tripMap).bindPopup('Pickup Location').openPopup();
    <?php endif; ?>
    </script>
</body>
</html>
