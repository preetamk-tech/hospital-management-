<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_patient_ids = array_column($stmt->fetchAll(), 'patient_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $patient_id = (int)$_POST['patient_id'];
    $pickup_address = sanitize($_POST['pickup_address']);
    $pickup_lat = (float)($_POST['pickup_lat'] ?? 19.0760);
    $pickup_lng = (float)($_POST['pickup_lng'] ?? 72.8777);
    $emergency_type = in_array($_POST['emergency_type'], ['routine', 'emergency', 'transfer', 'discharge']) ? $_POST['emergency_type'] : 'routine';
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($pickup_address)) {
        $error = 'Please enter pickup address.';
    } else {
        $booking_number = 'AMB-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO ambulance_bookings (booking_number, patient_id, booked_by, pickup_address, pickup_lat, pickup_lng, emergency_type, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested')
        ");
        $stmt->execute([$booking_number, $patient_id, $user_id, $pickup_address, $pickup_lat, $pickup_lng, $emergency_type, $notes]);

        setMessage('Ambulance requested successfully! Booking #' . $booking_number, 'success');
        redirect('patient/ambulance.php');
    }
}

$stmt = $pdo->prepare("SELECT patient_id, full_name, phone FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_patients = $stmt->fetchAll();
$my_patient_ids = array_column($my_patients, 'patient_id');

$bookings = [];
if (!empty($my_patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($my_patient_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT b.*, a.vehicle_number, a.driver_name, a.driver_phone, a.ambulance_type
        FROM ambulance_bookings b
        LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
        WHERE b.patient_id IN ($placeholders)
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($my_patient_ids);
    $bookings = $stmt->fetchAll();
}
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="box" style="margin-top: 70px;">
    <div class="box-header">
        <h2 class="box-title"><i class="fas fa-ambulance"></i> Ambulance Service</h2>
    </div>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="box" style="background:#f8f9fa;border:2px solid #667eea;border-radius:12px;margin-bottom:2rem;">
        <div class="box-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
            <h2 class="box-title" style="color:#fff;"><i class="fas fa-ambulance"></i> Book Ambulance</h2>
        </div>
        <form method="POST" style="padding:1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="pickup_lat" id="pickupLat" value="19.0760">
            <input type="hidden" name="pickup_lng" id="pickupLng" value="72.8777">
            
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Patient *</label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($my_patients as $p): ?>
                            <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Urgency *</label>
                    <select name="emergency_type" class="form-control" required>
                        <option value="routine">Routine (Non-emergency)</option>
                        <option value="emergency">Emergency (Urgent)</option>
                        <option value="transfer">Patient Transfer</option>
                        <option value="discharge">Hospital Discharge</option>
                    </select>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Pickup Address *</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="pickup_address" id="pickupAddress" class="form-control" placeholder="Enter address or tap on the map" required style="flex:1;">
                        <button type="button" onclick="getMyLocation()" class="btn btn-secondary" style="white-space:nowrap;" title="Use my current GPS location">
                            <i class="fas fa-location-crosshairs"></i> GPS
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Pickup Location — tap or drag pin on map</label>
                    <div id="pickup_map" style="height:280px;border-radius:12px;border:2px solid #e5e7eb;"></div>
                    <small style="color:#6b7280;margin-top:4px;display:block;">
                        <i class="fas fa-info-circle"></i> Click anywhere on the map to set your exact pickup point
                    </small>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any special requirements (e.g., ventilator, wheelchair)"></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-danger" style="font-size:1.1rem;padding:12px 30px;margin-top:0.5rem;">
                <i class="fas fa-paper-plane"></i> Request Ambulance Now
            </button>
        </form>
    </div>
    
    <h3 style="margin-bottom:1rem;"><i class="fas fa-history"></i> My Bookings</h3>
    
    <?php if (!empty($bookings)): ?>
        <div class="bookings-grid">
            <?php foreach ($bookings as $b):
                $statusColors = [
                    'requested' => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#f59e0b', 'icon' => 'fa-clock'],
                    'assigned'  => ['bg' => '#dbeafe', 'text' => '#1e40af', 'border' => '#3b82f6', 'icon' => 'fa-user-check'],
                    'dispatched'=> ['bg' => '#e0e7ff', 'text' => '#3730a3', 'border' => '#6366f1', 'icon' => 'fa-truck-fast'],
                    'on_way'    => ['bg' => '#ffedd5', 'text' => '#9a3412', 'border' => '#f97316', 'icon' => 'fa-route'],
                    'at_pickup' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'border' => '#6366f1', 'icon' => 'fa-location-dot'],
                    'en_route'  => ['bg' => '#ffedd5', 'text' => '#9a3412', 'border' => '#f97316', 'icon' => 'fa-road'],
                    'at_hospital'=> ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#9ca3af', 'icon' => 'fa-hospital'],
                    'completed' => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#10b981', 'icon' => 'fa-check-circle'],
                    'cancelled' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#ef4444', 'icon' => 'fa-times-circle'],
                ];
                $sc = $statusColors[$b['status']] ?? $statusColors['requested'];
            ?>
            <div class="booking-card" style="border-left:4px solid <?php echo $sc['border']; ?>;">
                <div class="booking-header">
                    <span class="booking-number">#<?php echo htmlspecialchars($b['booking_number']); ?></span>
                    <span class="booking-status" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>;">
                        <i class="fas <?php echo $sc['icon']; ?>"></i>
                        <?php echo ucfirst(str_replace('_',' ',$b['status'])); ?>
                    </span>
                </div>
                
                <div class="booking-body">
                    <div class="booking-row">
                        <i class="fas fa-tag" style="color:#667eea;"></i>
                        <span><?php echo ucfirst($b['emergency_type']); ?></span>
                    </div>
                    
                    <?php if ($b['ambulance_type']): ?>
                    <div class="booking-row">
                        <i class="fas fa-ambulance" style="color:#667eea;"></i>
                        <span><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$b['ambulance_type']))); ?> — <?php echo htmlspecialchars($b['vehicle_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($b['driver_name']): ?>
                    <div class="booking-row">
                        <i class="fas fa-user" style="color:#667eea;"></i>
                        <span><?php echo htmlspecialchars($b['driver_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($b['driver_phone']): ?>
                    <div class="booking-row">
                        <i class="fas fa-phone" style="color:#10b981;"></i>
                        <a href="tel:<?php echo $b['driver_phone']; ?>" style="color:#10b981;text-decoration:none;font-weight:600;">
                            <?php echo htmlspecialchars($b['driver_phone']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (in_array($b['status'], ['requested', 'assigned'])): ?>
                <div class="booking-footer">
                    <a href="cancel_ambulance.php?id=<?php echo $b['booking_id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this ambulance booking?');">
                        <i class="fas fa-times"></i> Cancel Booking
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center;padding:3rem;">
            <i class="fas fa-ambulance" style="font-size:4rem;color:#e2e8f0;"></i>
            <p style="margin-top:1rem;color:#666;">No ambulance bookings found.</p>
        </div>
    <?php endif; ?>
</div>

<script>
var pickupMap, pickupMarker;
var mapLoaded = false;

function initPickupMap(lat, lng) {
    if (mapLoaded) return;
    mapLoaded = true;

    pickupMap = L.map('pickup_map').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(pickupMap);

    pickupMarker = L.marker([lat, lng], {draggable: true}).addTo(pickupMap)
        .bindPopup('Your pickup location').openPopup();

    pickupMap.on('click', function(e) {
        pickupMarker.setLatLng(e.latlng);
        updateAddress(e.latlng.lat, e.latlng.lng);
    });

    pickupMarker.on('dragend', function(e) {
        var pos = pickupMarker.getLatLng();
        updateAddress(pos.lat, pos.lng);
    });
}

function updateAddress(lat, lng) {
    document.getElementById('pickupLat').value = lat.toFixed(7);
    document.getElementById('pickupLng').value = lng.toFixed(7);

    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.display_name) {
                document.getElementById('pickupAddress').value = data.display_name;
            } else {
                document.getElementById('pickupAddress').value = lat.toFixed(7) + ', ' + lng.toFixed(7);
            }
        })
        .catch(function() {
            document.getElementById('pickupAddress').value = lat.toFixed(7) + ', ' + lng.toFixed(7);
        });
}

function getMyLocation() {
    var btn = event.target.closest('button');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding...';
    btn.disabled = true;

    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> GPS';
        btn.disabled = false;
        return;
    }

    navigator.geolocation.getCurrentPosition(function(pos) {
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;

        if (!mapLoaded) {
            initPickupMap(lat, lng);
        } else {
            pickupMap.setView([lat, lng], 16);
            pickupMarker.setLatLng([lat, lng]).openPopup();
        }

        updateAddress(lat, lng);

        btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> GPS';
        btn.disabled = false;
    }, function() {
        alert('Unable to get your location. Please tap on the map to set pickup point.');
        btn.innerHTML = '<i class="fas fa-location-crosshairs"></i> GPS';
        btn.disabled = false;
    }, {enableHighAccuracy: true, timeout: 10000});
}

document.addEventListener('DOMContentLoaded', function() {
    initPickupMap(19.0760, 72.8777);
});
</script>

<style>
.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1rem;
}
.booking-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: box-shadow 0.2s, transform 0.2s;
}
.booking-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}
.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}
.booking-number {
    font-weight: 700;
    font-size: 14px;
    color: #111827;
}
.booking-status {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.booking-body {
    padding: 14px 16px;
}
.booking-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
    font-size: 14px;
    color: #374151;
}
.booking-row i {
    width: 16px;
    text-align: center;
    font-size: 13px;
}
.booking-row a:hover {
    text-decoration: underline !important;
}
.booking-footer {
    padding: 10px 16px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
}
.btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #ef4444;
    background: #fee2e2;
    border: 1px solid #fca5a5;
    border-radius: 8px;
    padding: 6px 14px;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-cancel:hover {
    background: #fecaca;
}
</style>

<?php include '../includes/footer.php'; ?>
