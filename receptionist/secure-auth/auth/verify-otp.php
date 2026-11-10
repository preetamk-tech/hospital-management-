<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$userId = (int) ($_SESSION['verify_user_id'] ?? $_SESSION['reset_user_id'] ?? 0);
$mode = isset($_SESSION['reset_user_id']) ? 'password_reset' : 'email_verification';
$errors = [];

if ($userId <= 0) {
    flash('error', 'Verification session expired. Please start again.');
    redirect('auth/register.php');
}

$user = find_user_by_id($userId);
if (!$user) {
    flash('error', 'User not found.');
    redirect('auth/register.php');
}

if (is_post()) {
    verify_csrf();
    enforce_ip_rate_limit($user['email']);

    if (isset($_POST['resend'])) {
        if (!otp_resend_allowed($userId, $mode)) {
            $errors[] = 'Please wait before requesting another OTP.';
        } else {
            $otp = create_otp($userId, $mode);
            if (send_otp_email($user['email'], $user['name'], $otp, $mode)) {
                security_log($userId, $user['email'], 'otp_resent', ['purpose' => $mode]);
                flash('success', 'A new OTP has been sent to your email.');
                redirect('auth/verify-otp.php');
            }
            $errors[] = 'Unable to send OTP right now.';
        }
    } else {
        $otp = input('otp');
        if (!preg_match('/^\d{6}$/', $otp)) {
            $errors[] = 'Enter the 6-digit OTP.';
        } elseif (!verify_otp_code($userId, $mode, $otp)) {
            security_log($userId, $user['email'], 'otp_failure', ['purpose' => $mode]);
            $errors[] = 'Invalid or expired OTP.';
        } else {
            if ($mode === 'email_verification') {
                $stmt = db()->prepare('UPDATE users SET verified = 1 WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                unset($_SESSION['verify_user_id']);
                security_log($userId, $user['email'], 'email_verified');
                flash('success', 'Email verified. You can log in now.');
                redirect('auth/login.php');
            }

            $_SESSION['password_reset_verified'] = true;
            security_log($userId, $user['email'], 'password_reset_otp_verified');
            flash('success', 'OTP verified. You can now choose a new password.');
            redirect('auth/reset-password.php');
        }
    }
}

render('Verify OTP', function () use ($errors, $user, $mode): void {
    ?>
    <section class="single-card-wrap">
        <div class="card">
            <h1><?= $mode === 'email_verification' ? 'Verify your email' : 'Verify reset code' ?></h1>
            <p class="muted">We sent a 6-digit code to <?= e($user['email']) ?>. It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</p>
            <?php render_alerts(); ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" data-validate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>OTP Code</span>
                    <input type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
                </label>
                <button class="btn btn-primary" type="submit">Verify OTP</button>
                <button class="btn btn-secondary" type="submit" name="resend" value="1">Resend OTP</button>
            </form>
            <p class="form-footer"><a href="<?= e(current_path('auth/login.php')) ?>">Back to login</a></p>
        </div>
    </section>
    <?php
});

