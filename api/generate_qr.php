<?php
require_once '../config/db.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

$token_id = isset($_GET['token_id']) ? (int)$_GET['token_id'] : 0;

$stmt = $pdo->prepare("
    SELECT t.token_number, p.full_name as patient_name, d.full_name as doctor_name, t.queue_date, t.status
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE t.token_id = ?
");
$stmt->execute([$token_id]);
$token = $stmt->fetch();

if (!$token) {
    die("Token not found");
}

// Simple QR code generation (text-based)
$qr_data = "MediQueue Token\n";
$qr_data .= "Token: " . $token['token_number'] . "\n";
$qr_data .= "Patient: " . $token['patient_name'] . "\n";
$qr_data .= "Doctor: " . $token['doctor_name'] . "\n";
$qr_data .= "Date: " . $token['queue_date'] . "\n";
$qr_data .= "Status: " . $token['status'];

// Use Google Charts API for QR (free, no library needed)
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - Token <?php echo htmlspecialchars($token['token_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .qr-card {
            background: white;
            border-radius: 30px;
            padding: 2rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        
        .token-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .qr-code {
            margin: 1.5rem 0;
            padding: 1rem;
            background: white;
            border-radius: 20px;
        }
        
        .qr-code img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
        
        .info {
            text-align: left;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 15px;
        }
        
        .info p {
            margin: 0.5rem 0;
            color: #2d3748;
        }
        
        .info strong {
            color: #667eea;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: center;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .status-waiting {
            background: #feebc8;
            color: #7b341e;
        }
        
        .status-called {
            background: #fed7d7;
            color: #742a2a;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .btn-group {
                display: none;
            }
            .qr-card {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="qr-card">
        <i class="fas fa-hospital-user" style="font-size: 2rem; color: #667eea;"></i>
        <h2>MediQueue</h2>
        
        <div class="token-number">
            Token #<?php echo htmlspecialchars($token['token_number']); ?>
        </div>
        
        <div class="qr-code">
            <img src="<?php echo $qr_url; ?>" alt="QR Code">
        </div>
        
        <div class="info">
            <p><strong>Patient:</strong> <?php echo htmlspecialchars($token['patient_name']); ?></p>
            <p><strong>Doctor:</strong> Dr. <?php echo htmlspecialchars($token['doctor_name']); ?></p>
            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($token['queue_date'])); ?></p>
            <p><strong>Status:</strong> 
                <span class="status-badge status-<?php echo $token['status']; ?>">
                    <?php echo ucfirst($token['status']); ?>
                </span>
            </p>
        </div>
        
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="window.location.href='../patient/my_token.php'" class="btn btn-secondary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</body>
</html>