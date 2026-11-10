<?php
require_once '../config/db.php';
require_once '../config/security.php';

if (!isLoggedIn() || !hasRole('patient')) {
    redirect('auth/login.php');
}

$user_id = $_SESSION['user_id'];

// Get completed appointments for feedback
$stmt = $pdo->prepare("
    SELECT a.appointment_id, d.full_name as doctor_name, a.appointment_date
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE p.user_id = ? AND a.status = 'completed'
    AND a.appointment_id NOT IN (SELECT appointment_id FROM feedback)
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

// Submit feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $appointment_id = (int)$_POST['appointment_id'];
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment']);
    
    $stmt = $pdo->prepare("INSERT INTO feedback (appointment_id, rating, comment) VALUES (?, ?, ?)");
    $stmt->execute([$appointment_id, $rating, $comment]);
    
    setMessage("Thank you for your feedback!", "success");
    redirect('patient/feedback.php');
}

// Get my feedback
$stmt = $pdo->prepare("
    SELECT f.*, d.full_name as doctor_name
    FROM feedback f
    JOIN appointments a ON f.appointment_id = a.appointment_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    WHERE p.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$my_feedback = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="grid">
    <!-- Give Feedback -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Rate Your Visit</h3>
            <i class="fas fa-star"></i>
        </div>
        
        <?php if (empty($appointments)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #48bb78;"></i>
                <p style="margin-top: 1rem;">No pending appointments to rate.</p>
                <p>Thank you for your visits!</p>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label class="form-label">Select Appointment</label>
                    <select name="appointment_id" class="form-control" required>
                        <?php foreach ($appointments as $row): ?>
                            <option value="<?php echo $row['appointment_id']; ?>">
                                Dr. <?php echo htmlspecialchars($row['doctor_name']); ?> - <?php echo date('d M Y', strtotime($row['appointment_date'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <select name="rating" class="form-control" required>
                        <option value="5">⭐⭐⭐⭐⭐ - Excellent</option>
                        <option value="4">⭐⭐⭐⭐ - Good</option>
                        <option value="3">⭐⭐⭐ - Average</option>
                        <option value="2">⭐⭐ - Poor</option>
                        <option value="1">⭐ - Very Poor</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Comments</label>
                    <textarea name="comment" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Feedback</button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- My Feedback -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">My Feedback</h3>
            <i class="fas fa-list"></i>
        </div>
        
        <?php if (empty($my_feedback)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-star" style="font-size: 3rem; color: #a0aec0;"></i>
                <p style="margin-top: 1rem;">No feedback submitted yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($my_feedback as $row): ?>
            <div style="border-bottom: 1px solid #e2e8f0; padding: 1rem 0;">
                <div style="display: flex; justify-content: space-between;">
                    <strong>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></strong>
                    <span class="badge badge-info"><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                </div>
                <div style="margin: 0.5rem 0;">
                    Rating: <?php echo str_repeat('⭐', $row['rating']); ?> (<?php echo $row['rating']; ?>/5)
                </div>
                <p style="color: #4a5568;"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>