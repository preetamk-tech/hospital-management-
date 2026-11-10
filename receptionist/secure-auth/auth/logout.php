<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$user = current_user();
if ($user) {
    security_log((int) $user['id'], $user['email'], 'logout');
}

logout_user();
session_start();
flash('success', 'You have been logged out securely.');
redirect('auth/login.php');

