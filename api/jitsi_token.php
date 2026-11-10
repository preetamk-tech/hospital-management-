<?php
require_once __DIR__ . '/mobile_common.php';

mobileCurrentUser();
$data = mobileJsonInput();
$roomName = sanitize($data['room_name'] ?? '');

if (!$roomName) {
    mobileError('Room name is required');
}

mobileResponse(['success' => true, 'room_name' => $roomName, 'token' => null]);
?>
