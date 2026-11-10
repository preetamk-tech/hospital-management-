<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'];

    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO ambulances (vehicle_number, driver_name, driver_phone, ambulance_type, current_lat, current_lng) VALUES (?, ?, ?, ?, 19.0760, 72.8777)");
        $stmt->execute([sanitize($_POST['vehicle_number']), sanitize($_POST['driver_name']), sanitize($_POST['driver_phone']), sanitize($_POST['ambulance_type'])]);
        $message = 'Ambulance added!';
    } elseif ($action == 'edit') {
        $stmt = $pdo->prepare("UPDATE ambulances SET vehicle_number=?, driver_name=?, driver_phone=?, ambulance_type=? WHERE ambulance_id=?");
        $stmt->execute([sanitize($_POST['vehicle_number']), sanitize($_POST['driver_name']), sanitize($_POST['driver_phone']), sanitize($_POST['ambulance_type']), (int)$_POST['ambulance_id']]);
        $message = 'Ambulance updated!';
    } elseif ($action == 'toggle') {
        $stmt = $pdo->prepare("UPDATE ambulances SET is_active = NOT is_active WHERE ambulance_id=?");
        $stmt->execute([(int)$_POST['ambulance_id']]);
        $message = 'Status updated!';
    } elseif ($action == 'update_location') {
        $stmt = $pdo->prepare("UPDATE ambulances SET current_lat=?, current_lng=? WHERE ambulance_id=?");
        $stmt->execute([(float)$_POST['lat'], (float)$_POST['lng'], (int)$_POST['ambulance_id']]);
        $message = 'Location updated!';
    } elseif ($action == 'assign') {
        $booking_id = (int)$_POST['booking_id'];
        $ambulance_id = (int)$_POST['ambulance_id'];
        $stmt = $pdo->prepare("UPDATE ambulance_bookings SET ambulance_id=?, status='assigned', dispatched_at=NOW() WHERE booking_id=?");
        $stmt->execute([$ambulance_id, $booking_id]);
        $stmt = $pdo->prepare("UPDATE ambulances SET status='dispatched' WHERE ambulance_id=?");
        $stmt->execute([$ambulance_id]);
        $message = 'Ambulance assigned!';
    }
}

$ambulances = [];
$stmt = $pdo->prepare("SELECT * FROM ambulances ORDER BY status, vehicle_number");
$stmt->execute();
$ambulances = $stmt->fetchAll();

$pending_bookings = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, p.phone, u.full_name as booked_by_name
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN users u ON b.booked_by = u.user_id
    WHERE b.status = 'requested'
    ORDER BY b.created_at DESC
");
$stmt->execute();
$pending_bookings = $stmt->fetchAll();

$active_bookings = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, p.phone, a.vehicle_number, a.driver_name, a.driver_phone, a.ambulance_type
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.status NOT IN ('completed', 'cancelled', 'requested')
    ORDER BY b.created_at DESC
");
$stmt->execute();
$active_bookings = $stmt->fetchAll();

$recent_bookings = [];
$stmt = $pdo->prepare("
    SELECT b.*, p.full_name as patient_name, a.vehicle_number, a.driver_name, a.ambulance_type
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.status IN ('completed', 'cancelled')
    ORDER BY b.created_at DESC LIMIT 20
");
$stmt->execute();
$recent_bookings = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ambulances WHERE ambulance_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}

$stats = [];
$stmt = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status='available' THEN 1 END) as available,
    COUNT(CASE WHEN status IN ('dispatched','on_way','at_pickup','en_route','assigned') THEN 1 END) as active,
    COUNT(CASE WHEN status='maintenance' THEN 1 END) as maintenance
    FROM ambulances WHERE is_active = 1");
$stmt->execute();
$stats = $stmt->fetch();

$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM ambulance_bookings WHERE status = 'requested'")->fetchColumn();
?>

<?php include '../includes/header.php'; ?>

<div style="margin-top: 70px;">
    <?php if ($message): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="margin-bottom:1rem;"><?php echo $error; ?></div><?php endif; ?>

    <div class="section-header-main" style="margin-bottom: 1.5rem;">
        <h2 style="margin: 0; font-size: 22px;"><i class="fas fa-ambulance"></i> Ambulance Management</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card-green">
            <i class="fas fa-check-circle"></i>
            <h3><?php echo $stats['available']; ?></h3>
            <p>Available</p>
        </div>
        <div class="stat-card-red">
            <i class="fas fa-truck-moving"></i>
            <h3><?php echo $stats['active']; ?></h3>
            <p>On Dispatch</p>
        </div>
        <div class="stat-card-yellow">
            <i class="fas fa-wrench"></i>
            <h3><?php echo $stats['maintenance']; ?></h3>
            <p>Maintenance</p>
        </div>
        <div class="stat-card-purple">
            <i class="fas fa-clock"></i>
            <h3><?php echo $pending_count; ?></h3>
            <p>Pending</p>
        </div>
    </div>

    <?php if (!empty($pending_bookings)): ?>
    <div class="section-card">
        <div class="section-header" style="background: #fef3c7; border-bottom-color: #f59e0b;">
            <h3 class="section-title" style="color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Pending Assignments</h3>
            <span class="section-count"><?php echo count($pending_bookings); ?></span>
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
                    <?php foreach ($pending_bookings as $pb): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($pb['booking_number']); ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($pb['patient_name']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($pb['phone']); ?></div>
                            <?php if ($pb['booked_by_name']): ?>
                                <div style="font-size: 11px; color: #9ca3af;">Booked by: <?php echo htmlspecialchars($pb['booked_by_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 280px;">
                            <div style="font-size: 13px; color: #374151; font-weight: 500; margin-bottom:4px;"><?php echo htmlspecialchars($pb['pickup_address']); ?></div>
                            <div style="font-size: 11px; color: #9ca3af; margin-bottom:6px;">
                                <i class="fas fa-map-pin"></i> <?php echo number_format($pb['pickup_lat'], 6); ?>, <?php echo number_format($pb['pickup_lng'], 6); ?>
                            </div>
                            <a href="https://www.google.com/maps?q=<?php echo $pb['pickup_lat']; ?>,<?php echo $pb['pickup_lng']; ?>" target="_blank" class="btn-view-map">
                                <i class="fas fa-external-link-alt"></i> View on Google Maps
                            </a>
                        </td>
                        <td>
                            <?php if ($pb['emergency_type'] === 'emergency'): ?>
                                <span class="badge badge-danger"><i class="fas fa-bolt"></i> Emergency</span>
                            <?php else: ?>
                                <span class="badge badge-info"><?php echo ucfirst($pb['emergency_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #6b7280; white-space: nowrap;"><?php echo date('d M, h:i A', strtotime($pb['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="booking_id" value="<?php echo $pb['booking_id']; ?>">
                                <select name="ambulance_id" class="form-control" style="width: 160px; padding: 6px 10px; font-size: 13px;" required>
                                    <option value="">-- Ambulance --</option>
                                    <?php foreach ($ambulances as $amb): ?>
                                        <?php if ($amb['status'] == 'available' && $amb['is_active']): ?>
                                            <option value="<?php echo $amb['ambulance_id']; ?>">
                                                <?php echo htmlspecialchars($amb['vehicle_number']); ?> (<?php echo strtoupper($amb['ambulance_type']); ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-action btn-green" title="Assign"><i class="fas fa-check"></i> Assign</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($active_bookings)): ?>
    <div class="section-card">
        <div class="section-header" style="background: #d1fae5; border-bottom-color: #10b981;">
            <h3 class="section-title" style="color: #065f46;"><i class="fas fa-truck-moving"></i> Active Bookings</h3>
            <span class="section-count"><?php echo count($active_bookings); ?></span>
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
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_bookings as $ab):
                        $statusConfig = [
                            'assigned'   => ['bg' => '#dbeafe', 'text' => '#1e40af', 'icon' => 'fa-user-check', 'label' => 'Assigned'],
                            'dispatched' => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-truck-fast', 'label' => 'Dispatched'],
                            'on_way'     => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-route', 'label' => 'On Way'],
                            'at_pickup'  => ['bg' => '#e0e7ff', 'text' => '#3730a3', 'icon' => 'fa-location-dot', 'label' => 'At Pickup'],
                            'en_route'   => ['bg' => '#ffedd5', 'text' => '#9a3412', 'icon' => 'fa-road', 'label' => 'En Route'],
                            'at_hospital'=> ['bg' => '#f3f4f6', 'text' => '#374151', 'icon' => 'fa-hospital', 'label' => 'At Hospital'],
                        ];
                        $sc = $statusConfig[$ab['status']] ?? ['bg' => '#e5e7eb', 'text' => '#374151', 'icon' => 'fa-circle', 'label' => ucfirst(str_replace('_', ' ', $ab['status']))];
                    ?>
                    <tr data-booking-id="<?php echo $ab['booking_id']; ?>">
                        <td><strong><?php echo htmlspecialchars($ab['booking_number']); ?></strong></td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($ab['patient_name']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($ab['phone']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($ab['vehicle_number']); ?></div>
                            <?php if ($ab['ambulance_type']): ?>
                                <div style="font-size: 12px; color: #6b7280;"><?php echo ucfirst($ab['ambulance_type']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ab['driver_name']): ?>
                                <div><?php echo htmlspecialchars($ab['driver_name']); ?></div>
                                <a href="tel:<?php echo $ab['driver_phone']; ?>" style="font-size: 12px; color: #10b981; text-decoration: none;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($ab['driver_phone']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #9ca3af;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge" style="background: <?php echo $sc['bg']; ?>; color: <?php echo $sc['text']; ?>;">
                                <i class="fas <?php echo $sc['icon']; ?>"></i> <?php echo $sc['label']; ?>
                            </span>
                            <?php if ($ab['pickup_lat'] && $ab['pickup_lng']): ?>
                                <br><a href="https://www.google.com/maps?q=<?php echo $ab['pickup_lat']; ?>,<?php echo $ab['pickup_lng']; ?>" target="_blank" style="font-size:11px;color:#667eea;text-decoration:none;margin-top:4px;display:inline-block;">
                                    <i class="fas fa-external-link-alt"></i> Maps
                                </a>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #6b7280; white-space: nowrap;"><?php echo date('d M, h:i A', strtotime($ab['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="section-card">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-<?php echo $edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $edit ? 'Edit' : 'Add'; ?> Ambulance</h3>
        </div>
        <form method="POST" style="padding: 1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $edit ? 'edit' : 'add'; ?>">
            <?php if ($edit): ?><input type="hidden" name="ambulance_id" value="<?php echo $edit['ambulance_id']; ?>"><?php endif; ?>

            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Vehicle Number *</label>
                    <input type="text" name="vehicle_number" class="form-control" value="<?php echo $edit ? htmlspecialchars($edit['vehicle_number']) : ''; ?>" required placeholder="e.g. MH-01-AB-1001">
                </div>
                <div class="form-group">
                    <label class="form-label">Driver Name *</label>
                    <input type="text" name="driver_name" class="form-control" value="<?php echo $edit ? htmlspecialchars($edit['driver_name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Driver Phone *</label>
                    <input type="text" name="driver_phone" class="form-control" value="<?php echo $edit ? htmlspecialchars($edit['driver_phone']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="ambulance_type" class="form-control">
                        <option value="basic" <?php echo ($edit && $edit['ambulance_type']=='basic')?'selected':''; ?>>Basic</option>
                        <option value="bls" <?php echo ($edit && $edit['ambulance_type']=='bls')?'selected':''; ?>>BLS</option>
                        <option value="als" <?php echo ($edit && $edit['ambulance_type']=='als')?'selected':''; ?>>ALS</option>
                        <option value="mortuary" <?php echo ($edit && $edit['ambulance_type']=='mortuary')?'selected':''; ?>>Mortuary</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;"><i class="fas fa-save"></i> <?php echo $edit ? 'Update' : 'Add'; ?> Ambulance</button>
            <?php if ($edit): ?>
                <a href="manage_ambulances.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="section-card">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-truck-medical"></i> Fleet (<?php echo count($ambulances); ?>)</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Driver</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ambulances as $a):
                        $status_colors = ['available'=>'success','dispatched'=>'warning','on_way'=>'info','at_pickup'=>'info','en_route'=>'info','at_hospital'=>'secondary','maintenance'=>'danger','assigned'=>'warning'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($a['vehicle_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($a['driver_name']); ?></td>
                        <td><a href="tel:<?php echo $a['driver_phone']; ?>" style="color: #10b981; text-decoration: none;"><?php echo htmlspecialchars($a['driver_phone']); ?></a></td>
                        <td><span style="font-weight: 600; font-size: 13px;"><?php echo strtoupper($a['ambulance_type']); ?></span></td>
                        <td>
                            <span class="badge badge-<?php echo $status_colors[$a['status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst(str_replace('_',' ',$a['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['is_active']): ?>
                                <span class="badge badge-success"><i class="fas fa-check"></i> Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fas fa-ban"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="display: flex; gap: 6px;">
                            <a href="?edit=<?php echo $a['ambulance_id']; ?>" class="btn-action btn-blue" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="ambulance_id" value="<?php echo $a['ambulance_id']; ?>">
                                <button class="btn-action <?php echo $a['is_active'] ? 'btn-orange' : 'btn-green'; ?>" title="<?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas fa-<?php echo $a['is_active'] ? 'ban' : 'check'; ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($recent_bookings)): ?>
    <div class="section-card">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-history"></i> Recent Bookings</h3>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking #</th>
                        <th>Patient</th>
                        <th>Ambulance</th>
                        <th>Pickup</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Booked At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $rb): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($rb['booking_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($rb['patient_name']); ?></td>
                        <td><?php echo $rb['vehicle_number'] ? htmlspecialchars($rb['vehicle_number']) : '<span style="color:#9ca3af;">Not assigned</span>'; ?></td>
                        <td style="max-width: 220px;">
                            <div style="font-size: 13px; color: #6b7280;"><?php echo htmlspecialchars($rb['pickup_address']); ?></div>
                            <?php if ($rb['pickup_lat'] && $rb['pickup_lng']): ?>
                                <a href="https://www.google.com/maps?q=<?php echo $rb['pickup_lat']; ?>,<?php echo $rb['pickup_lng']; ?>" target="_blank" style="font-size:11px;color:#667eea;text-decoration:none;">
                                    <i class="fas fa-map-pin"></i> <?php echo number_format($rb['pickup_lat'], 4); ?>, <?php echo number_format($rb['pickup_lng'], 4); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rb['emergency_type'] === 'emergency'): ?>
                                <span class="badge badge-danger"><i class="fas fa-bolt"></i> Emergency</span>
                            <?php else: ?>
                                <span class="badge badge-info"><?php echo ucfirst($rb['emergency_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($rb['status'] === 'completed'): ?>
                                <span class="status-badge" style="background: #d1fae5; color: #065f46;"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #fee2e2; color: #991b1b;"><i class="fas fa-times-circle"></i> Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: #6b7280;"><?php echo date('d M, h:i A', strtotime($rb['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.section-header-main {
    padding: 0.5rem 0;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 10px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.stat-card-green, .stat-card-red, .stat-card-yellow, .stat-card-purple {
    padding: 18px;
    border-radius: 12px;
    text-align: center;
    position: relative;
}
.stat-card-green { background: #d1fae5; }
.stat-card-green i { color: #10b981; }
.stat-card-green h3 { color: #065f46; }
.stat-card-green p { color: #059669; }

.stat-card-red { background: #fee2e2; }
.stat-card-red i { color: #ef4444; }
.stat-card-red h3 { color: #991b1b; }
.stat-card-red p { color: #dc2626; }

.stat-card-yellow { background: #fef3c7; }
.stat-card-yellow i { color: #f59e0b; }
.stat-card-yellow h3 { color: #92400e; }
.stat-card-yellow p { color: #d97706; }

.stat-card-purple { background: #ede9fe; }
.stat-card-purple i { color: #8b5cf6; }
.stat-card-purple h3 { color: #5b21b6; }
.stat-card-purple p { color: #7c3aed; }

.stats-grid [class^="stat-card"] i {
    position: absolute;
    top: 12px;
    right: 14px;
    font-size: 18px;
    opacity: 0.4;
}
.stats-grid [class^="stat-card"] h3 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
}
.stats-grid [class^="stat-card"] p {
    margin: 4px 0 0;
    font-size: 13px;
    font-weight: 600;
}
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-count {
    background: #e5e7eb;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 20px;
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
.badge-secondary { background: #e5e7eb; color: #374151; }
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
    justify-content: center;
    gap: 5px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    white-space: nowrap;
    text-decoration: none;
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
    var activeBookings = <?php echo json_encode(array_column($active_bookings, 'booking_id')); ?>;
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
