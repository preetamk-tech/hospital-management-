<?php
require_once 'config/db.php';

// Get all waiting and called tokens for today
$stmt = $pdo->prepare("
    SELECT t.token_number, t.token_prefix, p.full_name as patient_name, 
           t.status, d.full_name as doctor_name, d.doctor_id
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE t.queue_date = CURDATE() AND t.status IN ('waiting', 'called')
    ORDER BY 
        CASE WHEN t.token_prefix = 'E' THEN 1
             WHEN t.token_prefix = 'S' THEN 2
             ELSE 3 END,
        t.position ASC
");
$stmt->execute();
$tokens = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tokens WHERE queue_date = CURDATE()");
$stmt->execute();
$total_today = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as waiting FROM tokens WHERE queue_date = CURDATE() AND status = 'waiting'");
$stmt->execute();
$waiting = $stmt->fetch()['waiting'];

$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM tokens WHERE queue_date = CURDATE() AND status = 'completed'");
$stmt->execute();
$completed = $stmt->fetch()['completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, refresh=10">
    <title>Live Dashboard - MediQueue</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-box h3 {
            font-size: 0.875rem;
            color: #718096;
            margin-bottom: 0.5rem;
        }
        
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .queue-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s;
        }
        
        .queue-card:hover {
            transform: translateX(5px);
        }
        
        .token-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .patient-info {
            flex: 1;
            margin-left: 1rem;
        }
        
        .patient-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .doctor-name {
            font-size: 0.875rem;
            color: #718096;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-senior {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-emergency {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-waiting {
            background: #feebc8;
            color: #7b341e;
        }
        
        .badge-called {
            background: #bee3f8;
            color: #2c5282;
        }
        
        .last-updated {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-top: 2rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            color: white;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .token-number { font-size: 1.5rem; }
            .queue-card { flex-direction: column; text-align: center; gap: 0.5rem; }
            .patient-info { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-tv"></i> Live Queue Dashboard</h1>
        
        <div class="stats">
            <div class="stat-box">
                <h3>Total Today</h3>
                <div class="number"><?php echo $total_today; ?></div>
            </div>
            <div class="stat-box">
                <h3>Waiting</h3>
                <div class="number" style="color: #ed8936;"><?php echo $waiting; ?></div>
            </div>
            <div class="stat-box">
                <h3>Completed</h3>
                <div class="number" style="color: #48bb78;"><?php echo $completed; ?></div>
            </div>
        </div>
        
        <?php if (empty($tokens)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No Patients in Queue</h3>
                <p>All done for today! Check back later.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tokens as $row): ?>
                <div class="queue-card">
                    <div class="token-number">#<?php echo htmlspecialchars($row['token_number']); ?></div>
                    <div class="patient-info">
                        <div class="patient-name"><?php echo htmlspecialchars($row['patient_name']); ?></div>
                        <div class="doctor-name">Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></div>
                    </div>
                    <div>
                        <?php if ($row['token_prefix'] == 'S'): ?>
                            <span class="badge badge-senior"><i class="fas fa-heart"></i> Senior Citizen</span>
                        <?php elseif ($row['token_prefix'] == 'E'): ?>
                            <span class="badge badge-emergency"><i class="fas fa-ambulance"></i> Emergency</span>
                        <?php endif; ?>
                        <span class="badge badge-<?php echo $row['status']; ?>">
                            <i class="fas <?php echo $row['status'] == 'waiting' ? 'fa-hourglass-half' : 'fa-bell'; ?>"></i>
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="last-updated">
            <i class="fas fa-clock"></i> Last updated: <?php echo date('h:i:s A'); ?> 
            <span style="display: inline-block; width: 8px; height: 8px; background: #48bb78; border-radius: 50%; margin-left: 8px;"></span>
            (Auto-refreshes every 10 seconds)
        </div>
    </div>
    
    <script>
        // Auto-refresh page every 10 seconds
        setTimeout(function() {
            location.reload();
        }, 10000);
    </script>
</body>
</html>