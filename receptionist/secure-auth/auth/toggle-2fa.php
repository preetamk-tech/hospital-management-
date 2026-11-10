<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_auth();

if (!is_post()) {
    redirect('dashboard/index.php');
}

verify_csrf();

$user = current_user();
$enable = !empty($_POST['enable']);

$stmt = db()->prepare('UPDATE users SET two_factor_enabled = :enabled WHERE id = :id');
$stmt->execute([
    'enabled' => $enable ? 1 : 0,
    'id' => $user['id'],
]);

security_log((int) $user['id'], $user['email'], $enable ? 'two_factor_enabled' : 'two_factor_disabled');
flash('success', $enable ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.');
redirect('dashboard/index.php');

