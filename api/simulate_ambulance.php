<?php
require_once '../config/db.php';

$ambulances = $pdo->query("SELECT * FROM ambulances WHERE status IN ('dispatched','on_way','at_pickup','en_route')")->fetchAll();

foreach ($ambulances as $amb) {
    $lat = (float)$amb['current_lat'];
    $lng = (float)$amb['current_lng'];
    
    $moveLat = (rand(-5, 5) / 10000);
    $moveLng = (rand(-5, 5) / 10000);
    
    $newLat = $lat + $moveLat;
    $newLng = $lng + $moveLng;
    
    $pdo->prepare("UPDATE ambulances SET current_lat = ?, current_lng = ? WHERE ambulance_id = ?")
        ->execute([$newLat, $newLng, $amb['ambulance_id']]);
    
    $bookings = $pdo->prepare("SELECT booking_id FROM ambulance_bookings WHERE ambulance_id = ? AND status NOT IN ('completed','cancelled')");
    $bookings->execute([$amb['ambulance_id']]);
    foreach ($bookings->fetchAll() as $b) {
        $pdo->prepare("INSERT INTO ambulance_tracking (booking_id, ambulance_id, lat, lng, speed) VALUES (?, ?, ?, ?, ?)")
            ->execute([$b['booking_id'], $amb['ambulance_id'], $newLat, $newLng, rand(20, 60)]);
    }
}

echo "OK";
?>
