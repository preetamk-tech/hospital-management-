<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$userId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
$rememberMe = (bool) ($_SESSION['pending_2fa_remember'] ?? false);
$errors = [];

if ($userId <= 0) {
    flash('error', '2FA session expired. Please log in again.');
    redirect('auth/login.php');
}

$user = find_user_by_id($userId);
if (!$user) {
    flash('error', 'User not found.');
    redirect('auth/login.php');
}

if (is_post()) {
    verify_csrf();
    enforce_ip_rate_limit($user['email']);

    if (isset($_POST['resend'])) {
        if (!otp_resend_allowed($userId, 'login_2fa')) {
            $errors[] = 'Please wait before requesting another 2FA code.';
        } else {
            $otp = create_otp($userId, 'login_2fa');
            if (send_otp_email($user['email'], $user['name'], $otp, 'login_2fa')) {
                security_log($userId, $user['email'], 'two_factor_otp_resent');
                flash('success', 'A new 2FA code has been sent.');
                redirect('auth/two-factor.php');
            }
            $errors[] = 'Unable to send a new 2FA code.';
        }
    } else {
        $otp = input('otp');
        if (!verify_otp_code($userId, 'login_2fa', $otp)) {
            security_log($userId, $user['email'], 'otp_failure', ['purpose' => 'login_2fa']);
            $errors[] = 'Invalid or expired 2FA code.';
        } else {
            complete_login($user, $rememberMe);
            flash('success', 'Two-factor verification successful.');
            redirect('dashboard/index.php');
        }
    }
}

render('Two-Factor Verification', function () use ($errors, $user): void {
    ?>
    <section class="single-card-wrap">
        <div class="card">
            <h1>Two-factor verification</h1>
            <p class="muted">Enter the email OTP sent to <?= e($user['email']) ?> to finish signing in.</p>
            <?php render_alerts(); ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" data-validate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>2FA OTP</span>
                    <input type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                </label>
                <button class="btn btn-primary" type="submit">Verify & continue</button>
                <button class="btn btn-secondary" type="submit" name="resend" value="1">Resend code</button>
            </form>
        </div>
    </section>
    <?php
});

