<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/security.php';

$current_page = basename($_SERVER['PHP_SELF']);
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_name = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : '';

$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <title>MediQueue - Hospital Queue Management System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/notifications.css">
    <?php if(isLoggedIn()): ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/chatbot.css">
    <?php endif; ?>
    
    <?php if($dark_mode): ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/dark-mode.css">
    <?php endif; ?>
</head>
<body class="<?php echo $dark_mode ? 'dark-mode' : ''; ?>">
    <?php if(isLoggedIn()): ?>
    <script>
        window.MediBotConfig = {
            apiUrl: "<?php echo SITE_URL; ?>api/chatbot.php",
            role: "<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>",
            userName: "<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>",
            page: "<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>"
        };
    </script>
    <?php endif; ?>
    <nav class="navbar">
        <a href="<?php echo SITE_URL; ?>" class="logo">
            <i class="fas fa-hospital-user"></i>
            <h2>MediQueue</h2>
        </a>
        
        <div class="nav-right">
            <?php if(isLoggedIn()): ?>
                <button id="darkModeToggle" title="Toggle Dark Mode">
                    <i class="fas <?php echo $dark_mode ? 'fa-sun' : 'fa-moon'; ?>"></i>
                </button>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="dropdown-menu">
                <?php if(isLoggedIn()): ?>
                    <div class="user-info">
                        <div class="user-name"><?php echo $user_name; ?></div>
                        <div class="user-role"><?php echo ucfirst($role); ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="nav-section">
                        <?php if(hasRole('patient')): ?>
                            <a href="<?php echo SITE_URL; ?>patient/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/book_appointment.php">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/my_appointments.php">
                                <i class="fas fa-calendar-check"></i> My Appointments
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/my_token.php">
                                <i class="fas fa-ticket-alt"></i> My Token
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/lab_tests.php">
                                <i class="fas fa-vials"></i> Lab Tests
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/insurance.php">
                                <i class="fas fa-shield-alt"></i> Insurance
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/consultations.php">
                                <i class="fas fa-video"></i> Consultations
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/ambulance.php">
                                <i class="fas fa-ambulance"></i> Ambulance
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/virtual_queue.php">
                                <i class="fas fa-clock"></i> Virtual Queue
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/virtual_queue_join.php">
                                <i class="fas fa-sign-in-alt"></i> Join Virtual Queue
                            </a>
                            <a href="<?php echo SITE_URL; ?>patient/medical_history.php">
                                <i class="fas fa-notes-medical"></i> Medical History
                            </a>
                        <?php elseif(hasRole('doctor')): ?>
                            <a href="<?php echo SITE_URL; ?>doctor/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-stethoscope"></i> Dashboard
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/today_patients.php">
                                <i class="fas fa-users"></i> Today's Patients
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/vitals.php">
                                <i class="fas fa-heartbeat"></i> Vitals
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/add_medical_history.php">
                                <i class="fas fa-notes-medical"></i> Medical Records
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/follow_ups.php">
                                <i class="fas fa-calendar-check"></i> Follow-ups
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/prescription_templates.php">
                                <i class="fas fa-file-medical"></i> Prescription Templates
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/my_leaves.php">
                                <i class="fas fa-calendar-minus"></i> Leave Management
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/telemedicine.php">
                                <i class="fas fa-video"></i> Telemedicine
                            </a>
                            <a href="<?php echo SITE_URL; ?>doctor/virtual_queue.php">
                                <i class="fas fa-clock"></i> Virtual Queue
                            </a>
                        <?php elseif(hasRole('receptionist')): ?>
                            <a href="<?php echo SITE_URL; ?>receptionist/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-desktop"></i> Dashboard
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/view_queue.php">
                                <i class="fas fa-list-ol"></i> View Queue
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/walkin_appointment.php">
                                <i class="fas fa-user-plus"></i> Walk-in Appointment
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/emergency_appointment.php">
                                <i class="fas fa-ambulance"></i> Emergency
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/ambulance.php">
                                <i class="fas fa-truck-medical"></i> Ambulance
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/virtual_queue.php">
                                <i class="fas fa-clock"></i> Virtual Queue
                            </a>
                            <a href="<?php echo SITE_URL; ?>receptionist/patient_history.php">
                                <i class="fas fa-history"></i> Patient History
                            </a>
                        <?php elseif(hasRole('admin')): ?>
                            <a href="<?php echo SITE_URL; ?>admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-crown"></i> Dashboard
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/analytics.php">
                                <i class="fas fa-chart-line"></i> Analytics
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/manage_beds.php">
                                <i class="fas fa-bed"></i> Bed Management
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/pharmacy.php">
                                <i class="fas fa-pills"></i> Pharmacy
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/billing.php">
                                <i class="fas fa-file-invoice-dollar"></i> Billing & Invoices
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/manage_lab_tests.php">
                                <i class="fas fa-vials"></i> Lab Tests
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/manage_insurance.php">
                                <i class="fas fa-shield-alt"></i> Insurance
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/attendance.php">
                                <i class="fas fa-clock"></i> Attendance
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/telemedicine.php">
                                <i class="fas fa-video"></i> Telemedicine
                            </a>
                            <a href="<?php echo SITE_URL; ?>admin/manage_ambulances.php">
                                <i class="fas fa-ambulance"></i> Ambulances
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo SITE_URL; ?>auth/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>auth/login.php" class="dropdown-item">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="<?php echo SITE_URL; ?>auth/register.php" class="dropdown-item">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .nav-section {
                max-height: 400px;
                overflow-y: auto;
                padding: 4px 8px;
            }
            .nav-section a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                color: #4a5568;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.15s;
                border-radius: 8px;
            }
            .nav-section a:hover { background: #f0f4ff; color: #667eea; }
            .nav-section a.active { background: #667eea; color: #fff; }
            .nav-section a i { width: 20px; text-align: center; font-size: 15px; }
            body.dark-mode .nav-section a { color: #e2e8f0; }
            body.dark-mode .nav-section a:hover { background: #4a5568; color: #fff; }
            body.dark-mode .nav-section a.active { background: #667eea; color: #fff; }
            .user-menu .dropdown-menu { min-width: 280px; }
            .nav-section::-webkit-scrollbar { width: 4px; }
            .nav-section::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }
            body.dark-mode .nav-section::-webkit-scrollbar-thumb { background: #4a5568; }
        </style>
    </nav>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <main class="main-container">
    <?php echo getMessage(); ?>
