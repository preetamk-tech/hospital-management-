<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$errors = [];

if (is_post()) {
    verify_csrf();
    enforce_ip_rate_limit();

    $email = strtolower(input('email'));
    with_old(['email' => $email]);

    if (!validate_email($email)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $user = find_user_by_email($email);
        if ($user) {
            $otp = create_otp((int) $user['id'], 'password_reset');
            if (send_otp_email($user['email'], $user['name'], $otp, 'password_reset')) {
                $_SESSION['reset_user_id'] = (int) $user['id'];
                security_log((int) $user['id'], $user['email'], 'password_reset_requested');
                clear_old();
                flash('success', 'If the email exists in our system, a reset OTP has been sent.');
                redirect('auth/verify-otp.php');
            } else {
                $errors[] = 'Unable to send reset OTP right now.';
            }
        } else {
            flash('success', 'If the email exists in our system, a reset OTP has been sent.');
            redirect('auth/login.php');
        }
    }
}

render('Forgot Password', function () use ($errors): void {
    ?>
    <section class="single-card-wrap">
        <div class="card">
            <h1>Forgot password</h1>
            <p class="muted">We will email you a one-time verification code so you can reset your password securely.</p>
            <?php render_alerts(); ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" data-validate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190">
                </label>
                <button class="btn btn-primary" type="submit">Send reset OTP</button>
                <p class="form-footer"><a href="<?= e(current_path('auth/login.php')) ?>">Back to login</a></p>
            </form>
        </div>
    </section>
    <?php
});
