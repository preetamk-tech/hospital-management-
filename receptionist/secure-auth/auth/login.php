<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$errors = [];

if (is_post()) {
    verify_csrf();
    enforce_ip_rate_limit();

    $email = strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);

    with_old(['email' => $email]);

    if (!validate_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }

    $user = $email ? find_user_by_email($email) : null;

    if ($user && account_is_locked($user)) {
        security_log((int) $user['id'], $user['email'], 'login_blocked_locked');
        $errors[] = 'Your account is temporarily locked. Please try again later.';
    } elseif (!$user || !password_verify($password, $user['password'])) {
        if ($user) {
            increment_login_failure($user, 'Invalid credentials');
        } else {
            security_log(null, $email, 'login_failure', ['reason' => 'Unknown email']);
        }
        $errors[] = 'Invalid email or password.';
    } elseif (!(int) $user['verified']) {
        $_SESSION['verify_user_id'] = (int) $user['id'];
        security_log((int) $user['id'], $user['email'], 'login_blocked_unverified');
        flash('info', 'Verify your email before logging in.');
        redirect('auth/verify-otp.php');
    } else {
        if ((int) $user['two_factor_enabled'] === 1) {
            if (start_two_factor_flow($user, $rememberMe)) {
                clear_old();
                flash('success', 'Enter the 2FA code sent to your email.');
                redirect('auth/two-factor.php');
            }
            $errors[] = 'Unable to send 2FA OTP right now.';
        } else {
            complete_login($user, $rememberMe);
            clear_old();
            redirect('dashboard/index.php');
        }
    }
}

render('Login', function () use ($errors): void {
    ?>
    <section class="auth-grid">
        <div class="hero-card alt">
            <span class="eyebrow">Welcome Back</span>
            <h1>Login protected by real-world security controls.</h1>
            <p>Secure sessions, remember-me tokens, temporary lockouts, IP-aware logging, and optional email 2FA.</p>
            <div class="pill-row">
                <span class="pill">Session hardening</span>
                <span class="pill">Lockout protection</span>
                <span class="pill">Remember me</span>
            </div>
        </div>
        <div class="card">
            <h2>Sign in</h2>
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
                <label>
                    <span>Password</span>
                    <input type="password" name="password" required minlength="12">
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="remember_me" value="1">
                    <span>Remember me on this device</span>
                </label>
                <button class="btn btn-primary" type="submit">Login</button>
                <div class="split-links">
                    <a href="<?= e(current_path('auth/forgot-password.php')) ?>">Forgot password?</a>
                    <a href="<?= e(current_path('auth/register.php')) ?>">Create account</a>
                </div>
            </form>
        </div>
    </section>
    <?php
});
