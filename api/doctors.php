<?php
require_once __DIR__ . '/mobile_common.php';

$stmt = $pdo->prepare("
    SELECT d.doctor_id, d.full_name, d.specialization, dep.department_name,
           (SELECT COUNT(*)
            FROM virtual_queue vq
            WHERE vq.doctor_id = d.doctor_id
              AND vq.queue_date = CURDATE()
              AND vq.status IN ('waiting', 'called')) as queue_length
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    WHERE d.is_available = 1
    ORDER BY d.full_name
");
$stmt->execute();

mobileResponse($stmt->fetchAll());
?>
