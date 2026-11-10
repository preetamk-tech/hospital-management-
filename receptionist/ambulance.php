<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('receptionist')) {
    redirect('auth/login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? 'request';

    if ($action === 'assign') {
        $booking_id = (int)$_POST['booking_id'];
        $ambulance_id = (int)$_POST['ambulance_id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE ambulance_bookings SET ambulance_id = ?, status = 'assigned' WHERE booking_id = ? AND status = 'requested'");
            $stmt->execute([$ambulance_id, $booking_id]);

            $stmt = $pdo->prepare("UPDATE ambulances SET status = 'assigned' WHERE ambulance_id = ? AND status = 'available'");
            $stmt->execute([$ambulance_id]);

            $pdo->commit();
            setMessage('Ambulance assigned successfully.', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            setMessage('Failed to assign ambulance.', 'error');
        }
        redirect('receptionist/ambulance.php');
    } else {
        $patient_id = (int)$_POST['patient_id'];
        $pickup_address = sanitize($_POST['pickup_address']);
        $pickup_lat = isset($_POST['pickup_lat']) ? (float)$_POST['pickup_lat'] : 19.0760;
        $pickup_lng = isset($_POST['pickup_lng']) ? (float)$_POST['pickup_lng'] : 72.8777;
        $emergency_type = sanitize($_POST['emergency_type']);
        $notes = sanitize($_POST['notes']);

        $booking_num = 'AMB-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO ambulance_bookings (booking_number, patient_id, booked_by, pickup_lat, pickup_lng, pickup_address, emergency_type, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$booking_num, $patient_id, $_SESSION['user_id'], $pickup_lat, $pickup_lng, $pickup_address, $emergency_type, $notes]);

        setMessage("Ambulance requested! Booking: $booking_num", "success");
        redirect('receptionist/ambulance.php');
    }
}

$patients = [];
$stmt = $pdo->prepare("SELECT * FROM patients ORDER BY full_name");
$stmt->execute();
$patients = $stmt->fetchAll();

$available_ambulances = [];
$stmt = $pdo->prepare("SELECT * FROM ambulances WHERE status = 'available' AND is_active = 1 ORDER BY vehicle_number");
$stmt->execute();
$available_ambulances = $stmt->fetchAll();

$pending = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, p.phone
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    WHERE b.status = 'requested'
    ORDER BY b.created_at DESC
");
$stmt->execute();
$pending = $stmt->fetchAll();

$active = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, p.phone, a.vehicle_number, a.driver_name, a.driver_phone, a.ambulance_type
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.status NOT IN ('completed', 'cancelled', 'requested')
    ORDER BY b.created_at DESC
");
$stmt->execute();
$active = $stmt->fetchAll();

$recent = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, p.phone, a.vehicle_number, a.driver_name
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.status IN ('completed', 'cancelled')
    ORDER BY b.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recent = $stmt->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<div style="margin-top: 70px;">
    <?php if ($message): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="margin-bottom:1rem;"><?php echo $error; ?></div><?php endif; ?>

    <div class="section-card">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-plus-circle"></i> Request Ambulance</h3>
        </div>
        <form method="POST" style="padding: 1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="pickup_lat" id="pickup_lat" value="19.0760">
            <input type="hidden" name="pickup_lng" id="pickup_lng" value="72.8777">

            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Patient *</label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['patient_id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['age']; ?> yrs)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Urgency *</label>
                    <select name="emergency_type" class="form-control" required>
                        <option value="routine">Routine</option>
                        <option value="emergency">Emergency</option>
                        <option value="transfer">Patient Transfer</option>
                        <option value="discharge">Discharge</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Pickup Address *</label>
                    <input type="text" name="pickup_address" class="form-control" required placeholder="Full address with landmark">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Patient condition, special requirements..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-danger" style="margin-top: 0.5rem;">
                <i class="fas fa-ambulance"></i> Request Ambulance
            </button>
        </form>
    </div>

    <?php if (!empty($pending)): ?>
    <div class="section-card">
        <div class="section-header" style="background: #fef3c7; border-bottom-color: #f59e0b;">
            <h3 class="section-title" style="color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Pending Assignments (<?php echo count($pending); ?>)</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Patient</th>
                        <th>Pickup</th>
                        <th>Type</th>
                        <th>Requested</th>
                        <th>Assign</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['booking_number']); ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($p['patient_name']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($p['phone']); ?></div>
                        </td>
                        <td style="max-width: 260px;">
                            <div style="font-size: 13px; color: #374151; font-weight: 500; margin-bottom:4px;"><?php echo htmlspecialchars($p['pickup_address']); ?></div>
                            <div style="font-size: 11px; color: #9ca3af; margin-bottom:6px;">
                                <i class="fas fa-map-pin"></i> <?php echo number_format($p['pickup_lat'], 6); ?>, <?php echo number_format($p['pickup_lng'], 6); ?>
                            </div>
                            <a href="https://www.google.com/maps?q=<?php echo $p['pickup_lat']; ?>,<?php echo $p['pickup_lng']; ?>" target="_blank" class="btn-view-map">
                                <i class="fas fa-external-link-alt"></i> View on Google Maps
                            </a>
                        </td>
                        <td>
                            <?php if ($p['emergency_type'] === 'emergency'): ?>
                                <span class="badge badge-danger"><i class="fas fa-bolt"></i> Emergency</span>
                            <?php else: ?>
                                <span class="badge badge-info"><?php echo ucfirst($p['emergency_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #6b7280; white-space: nowrap;"><?php echo date('d M, h:i A', strtotime($p['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="booking_id" value="<?php echo $p['booking_id']; ?>">
                                <select name="ambulance_id" class="form-control" style="width: 140px; padding: 6px 10px; font-size: 13px;" required>
                                    <option value="">-- Ambulance --</option>
                                    <?php foreach ($available_ambulances as $a): ?>
                                        <option value="<?php echo $a['ambulance_id']; ?>">
                                            <?php echo htmlspecialchars($a['vehicle_number']); ?> (<?php echo ucfirst($a['ambulance_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-success" style="padding: 6px 14px; font-size: 13px; white-space: nowrap;">
                                    <i class="fas fa-check"></i> Assign
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($active)): ?>
    <div class="section-card">
        <div class="section-header" style="background: #d1fae5; border-bottom-color: #10b981;">
            <h3 class="section-title" style="color: #065f46;"><i class="fas fa-truck-moving"></i> Active Bookings (<?php echo count($active); ?>)</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Patient</th>
                        <th>Ambulance</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active as $a):
                        $statusConfig = [
                            'assigned'   => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-user-check', 'label' => 'Assigned'],
                            'dispatched' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-truck-fast', 'label' => 'Dispatched'],
                            'on_way'     => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-route', 'label' => 'On Way'],
                            'at_pickup'  => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-location-dot', 'label' => 'At Pickup'],
                            'en_route'   => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-road', 'label' => 'En Route'],
                            'at_hospital'=> ['bg' => '#f3f4f6', 'text' => '#374151', 'icon' => 'fa-hospital', 'label' => 'At Hospital'],
                        ];
                        $sc = $statusConfig[$a['status']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'icon' => 'fa-circle', 'label' => ucfirst($a['status'])];
                    ?>
                    <tr data-booking-id="<?php echo $a['booking_id']; ?>">
                        <td><strong><?php echo htmlspecialchars($a['booking_number']); ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($a['patient_name']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($a['phone']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($a['vehicle_number']); ?></div>
                            <?php if ($a['ambulance_type']): ?>
                                <div style="font-size: 12px; color: #6b7280;"><?php echo ucfirst($a['ambulance_type']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['driver_name']): ?>
                                <div><?php echo htmlspecialchars($a['driver_name']); ?></div>
                                <a href="tel:<?php echo $a['driver_phone']; ?>" style="font-size: 12px; color: #10b981; text-decoration: none;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($a['driver_phone']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #9ca3af;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['text']; ?>;">
                                <i class="fas <?php echo $sc['icon']; ?>"></i> <?php echo $sc['label']; ?>
                            </span>
                            <?php if ($a['pickup_lat'] && $a['pickup_lng']): ?>
                                <br><a href="https://www.google.com/maps?q=<?php echo $a['pickup_lat']; ?>,<?php echo $a['pickup_lng']; ?>" target="_blank" style="font-size:11px;color:#667eea;text-decoration:none;margin-top:4px;display:inline-block;">
                                    <i class="fas fa-external-link-alt"></i> Maps
                                </a>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <?php if ($a['status'] === 'assigned'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="dispatched">
                                    <button class="btn-action btn-purple" title="Dispatch"><i class="fas fa-paper-plane"></i> Dispatch</button>
                                </form>
                            <?php elseif ($a['status'] === 'dispatched'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="on_way">
                                    <button class="btn-action btn-orange" title="Mark On Way"><i class="fas fa-road"></i> On Way</button>
                                </form>
                            <?php elseif ($a['status'] === 'on_way'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="at_pickup">
                                    <button class="btn-action btn-blue" title="At Pickup"><i class="fas fa-map-pin"></i> At Pickup</button>
                                </form>
                            <?php elseif ($a['status'] === 'at_pickup'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="en_route">
                                    <button class="btn-action btn-blue" title="En Route"><i class="fas fa-hospital"></i> En Route</button>
                                </form>
                            <?php elseif ($a['status'] === 'en_route'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="at_hospital">
                                    <button class="btn-action btn-green" title="Arrived"><i class="fas fa-check"></i> Arrived</button>
                                </form>
                            <?php elseif ($a['status'] === 'at_hospital'): ?>
                                <form method="POST" action="../api/update_ambulance_status.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo $a['booking_id']; ?>">
                                    <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                    <input type="hidden" name="new_status" value="completed">
                                    <button class="btn-action btn-green" title="Complete"><i class="fas fa-flag-checkered"></i> Complete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($recent)): ?>
    <div class="section-card">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-history"></i> Recent Bookings (<?php echo count($recent); ?>)</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Patient</th>
                        <th>Ambulance</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Booked At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['booking_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['patient_name']); ?></td>
                        <td><?php echo $r['vehicle_number'] ? htmlspecialchars($r['vehicle_number']) : '-'; ?></td>
                        <td>
                            <?php if ($r['emergency_type'] === 'emergency'): ?>
                                <span class="badge badge-danger">Emergency</span>
                            <?php else: ?>
                                <span class="badge badge-info"><?php echo ucfirst($r['emergency_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'completed'): ?>
                                <span class="status-badge" style="background: #d1fae5; color: #065f46;"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #fee2e2; color: #991b1b;"><i class="fas fa-times-circle"></i> Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #6b7280;"><?php echo date('d M, h:i A', strtotime($r['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.section-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}
.section-header {
    padding: 14px 20px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}
.section-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
}
.table-container { padding: 0; }
table { width: 100%; border-collapse: collapse; }
table thead th {
    background: #f9fafb;
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6b7280;
    border-bottom: 2px solid #e5e7eb;
}
table tbody td {
    padding: 14px 16px;
    font-size: 14px;
    color: #374151;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
table tbody tr:hover { background: #f9fafb; }
table tbody tr:last-child td { border-bottom: none; }
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    white-space: nowrap;
}
.btn-action:hover { transform: translateY(-1px); }
.btn-action:active { transform: translateY(0); }
.btn-purple { background: #ede9fe; color: #6d28d9; }
.btn-purple:hover { background: #ddd6fe; }
.btn-orange { background: #ffedd5; color: #9a3412; }
.btn-orange:hover { background: #fed7aa; }
.btn-blue { background: #dbeafe; color: #1e40af; }
.btn-blue:hover { background: #bfdbfe; }
.btn-green { background: #d1fae5; color: #065f46; }
.btn-green:hover { background: #a7f3d0; }
.btn-view-map {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #667eea;
    background: #f0f4ff;
    border: 1px solid #c7d2fe;
    border-radius: 6px;
    padding: 4px 8px;
    text-decoration: none;
    transition: background 0.2s;
}
.btn-view-map:hover { background: #e0e7ff; }
</style>

<?php include '../includes/footer.php'; ?>

<script>
(function() {
    var activeBookings = <?php echo json_encode(array_column($active, 'booking_id')); ?>;
    var pollInterval = 10000;

    function pollLocations() {
        activeBookings.forEach(function(bookingId) {
            fetch('../api/track_ambulance.php?booking_id=' + bookingId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.lat) {
                        var row = document.querySelector('[data-booking-id="' + bookingId + '"]');
                        if (!row) return;

                        var indicator = row.querySelector('.gps-indicator');
                        if (!indicator) {
                            var statusCell = row.querySelector('.status-cell');
                            if (statusCell) {
                                var dot = document.createElement('span');
                                dot.className = 'gps-indicator';
                                dot.innerHTML = '<span style="display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-size:11px;color:#10b981;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;animation:gpsPulse 1.5s infinite;"></span>Live</span>';
                                statusCell.appendChild(dot);
                            }
                        }
                    }
                })
                .catch(function() {});
        });
    }

    if (activeBookings.length > 0) {
        pollLocations();
        setInterval(pollLocations, pollInterval);
    }
})();
</script>

<style>
@keyframes gpsPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}
</style>
