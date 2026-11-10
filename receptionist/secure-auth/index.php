<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

attempt_remember_me_login();

if (current_user()) {
    redirect('dashboard/index.php');
}

redirect('auth/login.php');

