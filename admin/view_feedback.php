<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('admin')) {
    redirect('auth/login.php');
}

// Get all feedback
$stmt = $pdo->prepare("
    SELECT f.*, d.full_name as doctor_name, p.full_name as patient_name
    FROM feedback f
    JOIN appointments a ON f.appointment_id = a.appointment_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    ORDER BY f.created_at DESC
");
$stmt->execute();
$feedback = $stmt->fetchAll();

// Get average rating
$stmt = $pdo->query("SELECT AVG(rating) as avg FROM feedback");
$avg_rating = $stmt->fetch()['avg'];

// Get rating distribution
$stmt = $pdo->query("
    SELECT rating, COUNT(*) as count 
    FROM feedback 
    GROUP BY rating 
    ORDER BY rating DESC
");
$distribution = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="box" style="margin-top:70px;">
    <div class="box-header">
        <h2 class="box-title">Patient Feedback</h2>
        <div>
            <span class="badge badge-warning" style="font-size: 1rem;">
                Average Rating: <?php echo str_repeat('⭐', round($avg_rating)); ?> (<?php echo round($avg_rating, 1); ?>/5)
            </span>
        </div>
    </div>
    
    <div class="grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-info">
                <h3>Average Rating</h3>
                <div class="stat-number"><?php echo round($avg_rating, 1); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-comments"></i></div>
            <div class="stat-info">
                <h3>Total Reviews</h3>
                <div class="stat-number"><?php echo count($feedback); ?></div>
            </div>
        </div>
    </div>
    
    <h3>Rating Distribution</h3>
    <div style="margin-bottom: 2rem;">
        <?php for ($i = 5; $i >= 1; $i--): 
            $count = 0;
            foreach ($distribution as $dist) {
                if ($dist['rating'] == $i) $count = $dist['count'];
            }
            $percentage = count($feedback) > 0 ? ($count / count($feedback)) * 100 : 0;
        ?>
        <div style="display: flex; align-items: center; gap: 1rem; margin: 0.5rem 0;">
            <span style="width: 50px;"><?php echo $i; ?> ★</span>
            <div style="flex: 1; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                <div style="width: <?php echo $percentage; ?>%; background: linear-gradient(135deg, #667eea, #764ba2); height: 10px;"></div>
            </div>
            <span style="width: 50px;"><?php echo $count; ?></span>
        </div>
        <?php endfor; ?>
    </div>
    
    <div class="table-container">
        <table class="table">
            <thead>
                <tr><th>Patient</th><th>Doctor</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($feedback as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                    <td>
                        <?php echo str_repeat('⭐', $row['rating']); ?>
                        <span class="badge badge-info"><?php echo $row['rating']; ?>/5</span>
                    </td>
                    <td><?php echo htmlspecialchars($row['comment'] ?? 'No comment'); ?></td>
                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>