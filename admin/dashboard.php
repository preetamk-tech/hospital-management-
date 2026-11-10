<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Helper: safe count query
function safeQuery($pdo, $sql, $default = 0) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchColumn() : $default;
    } catch (Exception $e) { return $default; }
}

// Helper: safe prepared query
function safePrepared($pdo, $sql, $params, $default = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (Exception $e) { return $default; }
}

// Helper: safe fetch all
function safeFetchAll($pdo, $sql, $default = []) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll() : $default;
    } catch (Exception $e) { return $default; }
}

// Stats
$total_patients = safeQuery($pdo, "SELECT COUNT(*) FROM users WHERE role='patient'");
$total_doctors = safeQuery($pdo, "SELECT COUNT(*) FROM doctors WHERE is_active=1");
$today_appointments = safeQuery($pdo, "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
$today_tokens = safePrepared($pdo, "SELECT COUNT(*) FROM tokens WHERE queue_date = CURDATE()", []);
$waiting_tokens = safePrepared($pdo, "SELECT COUNT(*) FROM tokens WHERE queue_date = CURDATE() AND status = 'waiting'", []);
$completed_tokens = safePrepared($pdo, "SELECT COUNT(*) FROM tokens WHERE queue_date = CURDATE() AND status = 'completed'", []);
$occupied_beds = safeQuery($pdo, "SELECT COUNT(*) FROM beds WHERE is_occupied = 1");
$total_beds = safeQuery($pdo, "SELECT COUNT(*) FROM beds");
$available_ambulances = safeQuery($pdo, "SELECT COUNT(*) FROM ambulances WHERE status = 'available' AND is_active = 1");
$active_ambulances = safeQuery($pdo, "SELECT COUNT(*) FROM ambulances WHERE status NOT IN ('available','maintenance') AND is_active = 1");
$today_revenue = safeQuery($pdo, "SELECT COALESCE(SUM(final_amount), 0) FROM invoices WHERE DATE(created_at) = CURDATE() AND status IN ('paid','partially_paid')");
$monthly_revenue = safeQuery($pdo, "SELECT COALESCE(SUM(final_amount), 0) FROM invoices WHERE MONTH(created_at) = MONTH(CURDATE()) AND status = 'paid'");
$pending_ambulances = safeQuery($pdo, "SELECT COUNT(*) FROM ambulance_bookings WHERE status = 'requested'");
$today_consultations = safePrepared($pdo, "SELECT COUNT(*) FROM consultations WHERE DATE(scheduled_at) = CURDATE()", []);

$avg_rating_result = safeQuery($pdo, "SELECT AVG(rating) FROM feedback");
$avg_rating = $avg_rating_result ? round($avg_rating_result, 1) : 'N/A';
$total_feedback = safeQuery($pdo, "SELECT COUNT(*) FROM feedback");
$low_stock_medicines = safeQuery($pdo, "SELECT COUNT(*) FROM pharmacy_inventory WHERE stock_quantity <= reorder_level AND is_available = 1");

// Today's appointments
$todayAppts = safeFetchAll($pdo, "
    SELECT a.*, d.full_name as doctor_name, dep.department_name, p.full_name as patient_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE a.appointment_date = CURDATE()
    ORDER BY a.appointment_time LIMIT 10
");

// Active ambulances
$activeAmbs = safeFetchAll($pdo, "
    SELECT b.booking_number, b.status, b.pickup_address, p.full_name as patient_name,
           a.vehicle_number, a.driver_name, a.driver_phone
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN ambulances a ON b.ambulance_id = a.ambulance_id
    WHERE b.status NOT IN ('completed','cancelled','requested')
    ORDER BY b.created_at DESC LIMIT 5
");

// Pending ambulance requests
$pendingAmbs = safeFetchAll($pdo, "
    SELECT b.booking_number, b.emergency_type, b.pickup_address, p.full_name as patient_name, b.created_at
    FROM ambulance_bookings b
    JOIN patients p ON b.patient_id = p.patient_id
    WHERE b.status = 'requested'
    ORDER BY b.created_at DESC LIMIT 5
");

// Recent feedback
$recentFeedback = safeFetchAll($pdo, "
    SELECT f.rating, f.comment, f.created_at, p.full_name as patient_name, d.full_name as doctor_name
    FROM feedback f
    JOIN appointments a ON f.appointment_id = a.appointment_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    ORDER BY f.created_at DESC LIMIT 5
");

// Department token distribution today
$deptTokens = safeFetchAll($pdo, "
    SELECT dep.department_name, COUNT(t.token_id) as total,
           COUNT(CASE WHEN t.status='completed' THEN 1 END) as completed,
           COUNT(CASE WHEN t.status='waiting' THEN 1 END) as waiting
    FROM departments dep
    LEFT JOIN doctors d ON dep.department_id = d.department_id
    LEFT JOIN tokens t ON d.doctor_id = t.doctor_id AND t.queue_date = CURDATE()
    GROUP BY dep.department_id
    ORDER BY total DESC
");

// Weekly appointment trend
$weeklyData = safeFetchAll($pdo, "
    SELECT DATE_FORMAT(appointment_date, '%a') as day, COUNT(*) as count
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY appointment_date
    ORDER BY appointment_date
");

// Low stock medicines
$lowStock = safeFetchAll($pdo, "
    SELECT medicine_name, stock_quantity, reorder_level, unit
    FROM pharmacy_inventory
    WHERE stock_quantity <= reorder_level AND is_active = 1
    ORDER BY (stock_quantity - reorder_level) ASC LIMIT 5
");

// Doctor performance today
$doctorPerf = safeFetchAll($pdo, "
    SELECT d.full_name, dep.department_name,
           COUNT(t.token_id) as total,
           COUNT(CASE WHEN t.status='completed' THEN 1 END) as completed,
           COUNT(CASE WHEN t.status='waiting' THEN 1 END) as waiting
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    LEFT JOIN tokens t ON d.doctor_id = t.doctor_id AND t.queue_date = CURDATE()
    GROUP BY d.doctor_id
    ORDER BY completed DESC LIMIT 10
");

include '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="margin-top:70px;">

    <!-- Welcome Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="color:#fff;margin:0;font-size:24px;"><i class="fas fa-tachometer-alt" style="color:#667eea;"></i> Admin Dashboard</h1>
            <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:14px;"><?php echo date('l, d F Y'); ?> — Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="manage_ambulances.php" class="btn btn-danger" style="font-size:13px;padding:8px 16px;"><i class="fas fa-ambulance"></i> Ambulances<?php if($pending_ambulances): ?> <span style="background:#fff;color:#f56565;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?php echo $pending_ambulances; ?></span><?php endif; ?></a>
            <a href="pharmacy.php" class="btn btn-warning" style="font-size:13px;padding:8px 16px;"><i class="fas fa-pills"></i> Pharmacy<?php if($low_stock_medicines): ?> <span style="background:#fff;color:#ed8936;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?php echo $low_stock_medicines; ?></span><?php endif; ?></a>
        </div>
    </div>

    <!-- Primary Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #667eea;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Total Patients</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo number_format($total_patients); ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(102,126,234,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-users" style="font-size:20px;color:#667eea;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #48bb78;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Active Doctors</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo number_format($total_doctors); ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(72,187,120,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-stethoscope" style="font-size:20px;color:#48bb78;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #f59e0b;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Today's Appointments</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo $today_appointments; ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(245,158,11,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-calendar-check" style="font-size:20px;color:#f59e0b;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #8b5cf6;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Today's Tokens</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo $today_tokens; ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(139,92,246,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-ticket-alt" style="font-size:20px;color:#8b5cf6;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #10b981;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Beds Occupied</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo $occupied_beds; ?> <small style="font-size:14px;color:#6b7280;">/ <?php echo $total_beds; ?></small></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(16,185,129,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-bed" style="font-size:20px;color:#10b981;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #ef4444;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Ambulances Active</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo $active_ambulances; ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(239,68,68,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-truck-moving" style="font-size:20px;color:#ef4444;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #06b6d4;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Today's Revenue</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;">₹<?php echo number_format($today_revenue); ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(6,182,212,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-indian-rupee-sign" style="font-size:20px;color:#06b6d4;"></i></div>
            </div>
        </div>

        <div style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,0.08);border-left:4px solid #ec4899;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <p style="margin:0;font-size:13px;color:#6b7280;font-weight:500;">Avg Rating</p>
                    <h3 style="margin:4px 0 0;font-size:28px;font-weight:800;color:#111827;"><?php echo $avg_rating; ?></h3>
                </div>
                <div style="width:48px;height:48px;background:rgba(236,72,153,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-star" style="font-size:20px;color:#ec4899;"></i></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem;margin-bottom:1.5rem;">
        <a href="manage_departments.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-building" style="font-size:20px;color:#667eea;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Departments</span>
        </a>
        <a href="manage_doctors.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-user-md" style="font-size:20px;color:#48bb78;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Doctors</span>
        </a>
        <a href="manage_users.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-users-cog" style="font-size:20px;color:#f59e0b;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Users</span>
        </a>
        <a href="manage_beds.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-bed" style="font-size:20px;color:#10b981;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Beds</span>
        </a>
        <a href="billing.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-file-invoice-dollar" style="font-size:20px;color:#06b6d4;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Billing</span>
        </a>
        <a href="pharmacy.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-pills" style="font-size:20px;color:#f97316;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Pharmacy</span>
        </a>
        <a href="attendance.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-clock" style="font-size:20px;color:#8b5cf6;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Attendance</span>
        </a>
        <a href="telemedicine.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-video" style="font-size:20px;color:#ef4444;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Telemedicine</span>
        </a>
        <a href="analytics.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-chart-line" style="font-size:20px;color:#14b8a6;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Analytics</span>
        </a>
        <a href="view_reports.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-chart-bar" style="font-size:20px;color:#6366f1;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Reports</span>
        </a>
        <a href="view_feedback.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-comments" style="font-size:20px;color:#ec4899;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Feedback</span>
        </a>
        <a href="manage_ambulances.php" style="background:#fff;border-radius:12px;padding:14px;text-align:center;text-decoration:none;color:#111827;box-shadow:0 1px 4px rgba(0,0,0,0.06);transition:transform 0.15s,box-shadow 0.15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 1px 4px rgba(0,0,0,0.06)'">
            <i class="fas fa-ambulance" style="font-size:20px;color:#f43f5e;display:block;margin-bottom:6px;"></i>
            <span style="font-size:12px;font-weight:600;">Ambulances</span>
        </a>
    </div>

    <!-- Main Grid -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- Today's OPD Overview -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#111827;"><i class="fas fa-hospital" style="color:#667eea;margin-right:8px;"></i> Today's OPD Overview</h3>
                <div style="display:flex;gap:12px;font-size:13px;">
                    <span style="color:#10b981;font-weight:600;"><i class="fas fa-check-circle"></i> <?php echo $completed_tokens; ?></span>
                    <span style="color:#f59e0b;font-weight:600;"><i class="fas fa-clock"></i> <?php echo $waiting_tokens; ?></span>
                    <span style="color:#6b7280;">Total: <?php echo $today_tokens; ?></span>
                </div>
            </div>
            <div style="padding:20px;">
                <canvas id="opdChart" height="180"></canvas>
            </div>
        </div>

        <!-- Token Status -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#111827;"><i class="fas fa-chart-pie" style="color:#8b5cf6;margin-right:8px;"></i> Token Status</h3>
            </div>
            <div style="padding:20px;">
                <canvas id="tokenPie" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- Today's Appointments -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-calendar-day" style="color:#f59e0b;margin-right:8px;"></i> Today's Appointments</h3>
                <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><?php echo count($todayAppts); ?></span>
            </div>
            <div style="padding:16px;max-height:350px;overflow-y:auto;">
                <?php if (empty($todayAppts)): ?>
                    <div style="text-align:center;padding:2rem;color:#9ca3af;">
                        <i class="fas fa-calendar-xmark" style="font-size:32px;margin-bottom:8px;display:block;"></i>
                        <p style="font-size:13px;">No appointments today</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayAppts as $ta): 
                        $statusColors = ['pending'=>['bg'=>'#fef3c7','text'=>'#92400e'],'confirmed'=>['bg'=>'#dbeafe','text'=>'#1e40af'],'completed'=>['bg'=>'#d1fae5','text'=>'#065f46'],'cancelled'=>['bg'=>'#fee2e2','text'=>'#991b1b']];
                        $sc = $statusColors[$ta['status']] ?? ['bg'=>'#f3f4f6','text'=>'#374151'];
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
                        <div>
                            <div style="font-weight:600;font-size:13px;color:#111827;"><?php echo htmlspecialchars($ta['patient_name']); ?></div>
                            <div style="font-size:12px;color:#6b7280;margin-top:2px;">Dr. <?php echo htmlspecialchars($ta['doctor_name']); ?> — <?php echo htmlspecialchars($ta['department_name'] ?? 'N/A'); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:12px;color:#374151;font-weight:600;"><?php echo date('h:i A', strtotime($ta['appointment_time'])); ?></div>
                            <span style="display:inline-block;background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['text']; ?>;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;margin-top:4px;"><?php echo ucfirst($ta['status']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Ambulances -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-truck-moving" style="color:#ef4444;margin-right:8px;"></i> Active Ambulances</h3>
                <?php if ($pending_ambulances): ?>
                    <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> <?php echo $pending_ambulances; ?> pending</span>
                <?php endif; ?>
            </div>
            <div style="padding:16px;max-height:350px;overflow-y:auto;">
                <?php if (empty($pendingAmbs) && empty($activeAmbs)): ?>
                    <div style="text-align:center;padding:2rem;color:#9ca3af;">
                        <i class="fas fa-check-circle" style="font-size:32px;margin-bottom:8px;display:block;color:#48bb78;"></i>
                        <p style="font-size:13px;">All clear — no active requests</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingAmbs as $pa): ?>
                    <div style="background:#fef3c7;border-radius:10px;padding:12px;margin-bottom:8px;border-left:3px solid #f59e0b;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:13px;color:#92400e;"><?php echo htmlspecialchars($pa['booking_number']); ?></strong>
                            <span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;"><?php echo strtoupper($pa['emergency_type']); ?></span>
                        </div>
                        <div style="font-size:12px;color:#78350f;margin-top:4px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($pa['patient_name']); ?></div>
                        <div style="font-size:11px;color:#92400e;margin-top:2px;"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars(substr($pa['pickup_address'], 0, 50)); ?><?php echo strlen($pa['pickup_address']) > 50 ? '...' : ''; ?></div>
                        <div style="font-size:11px;color:#92400e;margin-top:2px;"><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($pa['created_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($activeAmbs as $aa): 
                        $ambStatusColors = ['assigned'=>['bg'=>'#dbeafe','text'=>'#1e40af'],'dispatched'=>['bg'=>'#e0e7ff','text'=>'#3730a3'],'on_way'=>['bg'=>'#ffedd5','text'=>'#9a3412'],'at_pickup'=>['bg'=>'#e0e7ff','text'=>'#3730a3'],'en_route'=>['bg'=>'#ffedd5','text'=>'#9a3412'],'at_hospital'=>['bg'=>'#f3f4f6','text'=>'#374151']];
                        $asc = $ambStatusColors[$aa['status']] ?? ['bg'=>'#f3f4f6','text'=>'#374151'];
                    ?>
                    <div style="background:#f0fdf4;border-radius:10px;padding:12px;margin-bottom:8px;border-left:3px solid #10b981;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:13px;color:#166534;"><?php echo htmlspecialchars($aa['vehicle_number']); ?></strong>
                            <span style="background:<?php echo $asc['bg']; ?>;color:<?php echo $asc['text']; ?>;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;"><?php echo ucfirst(str_replace('_',' ',$aa['status'])); ?></span>
                        </div>
                        <div style="font-size:12px;color:#15803d;margin-top:4px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($aa['patient_name']); ?></div>
                        <div style="font-size:11px;color:#166534;margin-top:2px;"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($aa['driver_name']); ?> — <a href="tel:<?php echo $aa['driver_phone']; ?>" style="color:#10b981;text-decoration:none;"><?php echo htmlspecialchars($aa['driver_phone']); ?></a></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Feedback -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-star" style="color:#ec4899;margin-right:8px;"></i> Recent Feedback</h3>
                <span style="font-size:12px;color:#6b7280;"><?php echo $total_feedback; ?> reviews</span>
            </div>
            <div style="padding:16px;max-height:350px;overflow-y:auto;">
                <?php if (empty($recentFeedback)): ?>
                    <div style="text-align:center;padding:2rem;color:#9ca3af;">
                        <i class="fas fa-comment-slash" style="font-size:32px;margin-bottom:8px;display:block;"></i>
                        <p style="font-size:13px;">No feedback yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentFeedback as $rf): ?>
                    <div style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:13px;color:#111827;"><?php echo htmlspecialchars($rf['patient_name']); ?></strong>
                            <span style="font-size:12px;"><?php echo str_repeat('⭐', (int)$rf['rating']); ?></span>
                        </div>
                        <div style="font-size:11px;color:#6b7280;margin-top:2px;">Dr. <?php echo htmlspecialchars($rf['doctor_name']); ?></div>
                        <?php if ($rf['comment']): ?>
                        <div style="font-size:12px;color:#4b5563;margin-top:4px;font-style:italic;">"<?php echo htmlspecialchars(substr($rf['comment'], 0, 80)); ?><?php echo strlen($rf['comment']) > 80 ? '...' : ''; ?>"</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Third Row: Doctor Performance + Alerts -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- Doctor Performance Today -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-user-md" style="color:#48bb78;margin-right:8px;"></i> Doctor Performance — Today</h3>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb;">
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Doctor</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Department</th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Total</th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Completed</th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Waiting</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctorPerf)): ?>
                        <tr><td colspan="6" style="padding:2rem;text-align:center;color:#9ca3af;">No doctor data available today</td></tr>
                        <?php else: ?>
                        <?php foreach ($doctorPerf as $dp): 
                            $eff = $dp['total'] > 0 ? round(($dp['completed']/$dp['total'])*100) : 0;
                            $barColor = $eff >= 70 ? '#48bb78' : ($eff >= 40 ? '#f59e0b' : '#f56565');
                        ?>
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#111827;"><?php echo htmlspecialchars($dp['full_name']); ?></td>
                            <td style="padding:10px 16px;font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($dp['department_name'] ?? '—'); ?></td>
                            <td style="padding:10px 16px;font-size:14px;font-weight:700;color:#111827;text-align:center;"><?php echo $dp['total']; ?></td>
                            <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#10b981;text-align:center;"><?php echo $dp['completed']; ?></td>
                            <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#f59e0b;text-align:center;"><?php echo $dp['waiting']; ?></td>
                            <td style="padding:10px 16px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="flex:1;background:#e5e7eb;border-radius:8px;height:8px;">
                                        <div style="background:<?php echo $barColor; ?>;border-radius:8px;height:8px;width:<?php echo $eff; ?>%;"></div>
                                    </div>
                                    <span style="font-size:12px;font-weight:600;color:#374151;min-width:32px;"><?php echo $eff; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alerts Panel -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            <!-- Bed Occupancy -->
            <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                    <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-bed" style="color:#10b981;margin-right:8px;"></i> Bed Occupancy</h3>
                </div>
                <div style="padding:20px;text-align:center;">
                    <?php $bedPct = $total_beds > 0 ? round(($occupied_beds/$total_beds)*100) : 0; $bedColor = $bedPct > 80 ? '#f56565' : ($bedPct > 50 ? '#f59e0b' : '#48bb78'); ?>
                    <div style="font-size:42px;font-weight:800;color:<?php echo $bedColor; ?>;"><?php echo $bedPct; ?>%</div>
                    <div style="font-size:13px;color:#6b7280;margin-top:4px;"><?php echo $occupied_beds; ?> of <?php echo $total_beds; ?> beds occupied</div>
                    <div style="background:#e5e7eb;border-radius:10px;height:12px;margin-top:12px;">
                        <div style="background:<?php echo $bedColor; ?>;border-radius:10px;height:12px;width:<?php echo $bedPct; ?>%;"></div>
                    </div>
                    <a href="manage_beds.php" style="display:inline-block;margin-top:12px;font-size:12px;color:#667eea;font-weight:600;text-decoration:none;">Manage Beds →</a>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;margin-right:8px;"></i> Low Stock Alerts</h3>
                    <?php if ($low_stock_medicines): ?>
                    <span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;"><?php echo $low_stock_medicines; ?></span>
                    <?php endif; ?>
                </div>
                <div style="padding:16px;max-height:200px;overflow-y:auto;">
                    <?php if (empty($lowStock)): ?>
                        <div style="text-align:center;padding:1rem;color:#9ca3af;font-size:13px;"><i class="fas fa-check-circle" style="color:#48bb78;"></i> All stocks adequate</div>
                    <?php else: ?>
                        <?php foreach ($lowStock as $ls): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f3f4f6;font-size:13px;">
                            <span style="color:#111827;font-weight:500;"><?php echo htmlspecialchars($ls['medicine_name']); ?></span>
                            <span style="color:#f56565;font-weight:700;font-size:12px;"><?php echo $ls['stock_quantity']; ?> / <?php echo $ls['reorder_level']; ?> <?php echo $ls['unit']; ?>s</span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Summary -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-indian-rupee-sign" style="color:#06b6d4;margin-right:8px;"></i> Revenue Summary</h3>
            </div>
            <div style="padding:20px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div style="background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;border-radius:12px;padding:16px;text-align:center;">
                        <p style="margin:0;font-size:12px;opacity:0.8;">Today</p>
                        <h2 style="margin:6px 0 0;font-size:24px;font-weight:800;">₹<?php echo number_format($today_revenue); ?></h2>
                    </div>
                    <div style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;border-radius:12px;padding:16px;text-align:center;">
                        <p style="margin:0;font-size:12px;opacity:0.8;">This Month</p>
                        <h2 style="margin:6px 0 0;font-size:24px;font-weight:800;">₹<?php echo number_format($monthly_revenue); ?></h2>
                    </div>
                </div>
                <a href="billing.php" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:#667eea;font-weight:600;text-decoration:none;">View All Invoices →</a>
            </div>
        </div>

        <!-- Department Distribution -->
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                <h3 style="margin:0;font-size:15px;font-weight:700;color:#111827;"><i class="fas fa-building" style="color:#6366f1;margin-right:8px;"></i> Department Tokens Today</h3>
            </div>
            <div style="padding:20px;">
                <canvas id="deptChart" height="180"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
// OPD Chart
new Chart(document.getElementById('opdChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => date('d M', strtotime($d['day'])), $weeklyData)); ?>,
        datasets: [{
            label: 'Appointments',
            data: <?php echo json_encode(array_column($weeklyData, 'count')); ?>,
            backgroundColor: 'rgba(102,126,234,0.7)',
            borderColor: '#667eea',
            borderWidth: 1,
            borderRadius: 6,
            barThickness: 24
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});

// Token Pie
new Chart(document.getElementById('tokenPie'), {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Waiting', 'Others'],
        datasets: [{
            data: [<?php echo (int)$completed_tokens; ?>, <?php echo (int)$waiting_tokens; ?>, <?php echo max(0, (int)$today_tokens - (int)$completed_tokens - (int)$waiting_tokens); ?>],
            backgroundColor: ['#48bb78', '#f59e0b', '#e5e7eb'],
            borderWidth: 0,
            cutout: '65%'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 12 } } }
        }
    }
});

// Department Chart
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(fn($d) => substr($d['department_name'], 0, 12), $deptTokens)); ?>,
        datasets: [
            {
                label: 'Completed',
                data: <?php echo json_encode(array_column($deptTokens, 'completed')); ?>,
                backgroundColor: '#48bb78',
                borderRadius: 4
            },
            {
                label: 'Waiting',
                data: <?php echo json_encode(array_column($deptTokens, 'waiting')); ?>,
                backgroundColor: '#f59e0b',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
