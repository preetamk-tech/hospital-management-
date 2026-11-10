<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('doctor')) {
    die('Unauthorized');
}

$history_id = isset($_GET['history_id']) ? (int)$_GET['history_id'] : 0;

if ($history_id) {
    $stmt = $pdo->prepare("
        SELECT mh.*, p.full_name as patient_name, p.age, p.gender, p.phone, d.full_name as doctor_name, dep.department_name
        FROM medical_history mh
        JOIN patients p ON mh.patient_id = p.patient_id
        JOIN doctors d ON mh.doctor_id = d.doctor_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        WHERE mh.history_id = ?
    ");
    $stmt->execute([$history_id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        die('Record not found');
    }
} else {
    die('No record specified');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription - <?php echo htmlspecialchars($record['patient_name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 40px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #667eea; font-size: 28px; }
        .header p { color: #666; margin-top: 5px; }
        .patient-info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
        .patient-info .row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .patient-info .row:last-child { margin-bottom: 0; }
        .section { margin-bottom: 25px; }
        .section h3 { color: #667eea; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; font-size: 16px; }
        .section p, .section pre { font-size: 14px; line-height: 1.6; }
        .section pre { white-space: pre-wrap; background: #f8f9fa; padding: 15px; border-radius: 5px; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .footer .signature { text-align: center; border-top: 1px solid #333; padding-top: 10px; width: 200px; }
        @media print { body { padding: 20px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" style="background:#667eea;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
    </div>
    
    <div class="header">
        <h1>MediQueue Hospital</h1>
        <p>Medical Prescription</p>
    </div>
    
    <div class="patient-info">
        <div class="row">
            <span><strong>Patient:</strong> <?php echo htmlspecialchars($record['patient_name']); ?></span>
            <span><strong>Age:</strong> <?php echo $record['age']; ?> yrs</span>
            <span><strong>Gender:</strong> <?php echo $record['gender']; ?></span>
        </div>
        <div class="row">
            <span><strong>Phone:</strong> <?php echo htmlspecialchars($record['phone']); ?></span>
            <span><strong>Date:</strong> <?php echo date('d M Y', strtotime($record['visit_date'])); ?></span>
        </div>
    </div>
    
    <div class="doctor-info" style="margin-bottom:25px;padding:15px;background:#f0f4ff;border-radius:8px;">
        <strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($record['doctor_name']); ?> | 
        <strong>Department:</strong> <?php echo htmlspecialchars($record['department_name']); ?>
    </div>
    
    <div class="section">
        <h3>Diagnosis</h3>
        <p><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
    </div>
    
    <div class="section">
        <h3>Prescription</h3>
        <pre><?php echo htmlspecialchars($record['prescription']); ?></pre>
    </div>
    
    <?php if ($record['notes']): ?>
    <div class="section">
        <h3>Notes & Advice</h3>
        <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <div>
            <p><strong>Generated:</strong> <?php echo date('d M Y, h:i A'); ?></p>
        </div>
        <div class="signature">
            <strong>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></strong>
        </div>
    </div>
</body>
</html>
