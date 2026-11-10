<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$department = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

$sql = "
    SELECT t.token_number, t.token_prefix, p.full_name as patient_name, 
           t.status, d.full_name as doctor_name, t.estimated_wait_time, t.position,
           CASE 
               WHEN t.token_prefix = 'S' THEN 'Senior Citizen'
               WHEN t.token_prefix = 'E' THEN 'Emergency'
               ELSE 'Regular'
           END as priority
    FROM tokens t
    JOIN appointments a ON t.appointment_id = a.appointment_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE t.queue_date = CURDATE() AND t.status IN ('waiting', 'called')
";

$params = [];
if ($department > 0) {
    $sql .= " AND d.department_id = ?";
    $params[] = $department;
}

$sql .= " ORDER BY 
            CASE 
                WHEN t.token_prefix = 'E' THEN 1
                WHEN t.token_prefix = 'S' THEN 2
                ELSE 3
            END,
            t.position ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tokens = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'count' => count($tokens),
    'tokens' => $tokens
]);
?>