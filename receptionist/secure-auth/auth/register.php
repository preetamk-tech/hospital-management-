<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$errors = [];

if (is_post()) {
    verify_csrf();
    enforce_ip_rate_limit();

    $name = input('name');
    $email = strtolower(input('email'));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirmation'] ?? '');

    with_old(['name' => $name, 'email' => $email]);

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!validate_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }
    $errors = array_merge($errors, validate_password_strength($password));

    $existingUser = $email ? find_user_by_email($email) : null;
    if ($existingUser && (int) $existingUser['verified'] === 1) {
        $errors[] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($existingUser) {
                $stmt = $pdo->prepare('UPDATE users SET name = :name, password = :password, verified = 0, login_attempts = 0, lock_until = NULL WHERE id = :id');
                $stmt->execute([
                    'name' => $name,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $existingUser['id'],
                ]);
                $userId = (int) $existingUser['id'];
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, verified, login_attempts, session_version)
                     VALUES (:name, :email, :password, 0, 0, 1)'
                );
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
            }

            $otp = create_otp($userId, 'email_verification');
            $user = find_user_by_id($userId);
            $mailSent = $user ? send_otp_email($email, $user['name'], $otp, 'email_verification') : false;

            if (!$mailSent) {
                throw new RuntimeException('Unable to send verification email.');
            }

            $pdo->commit();
            clear_old();
            $_SESSION['verify_user_id'] = $userId;
            security_log($userId, $email, 'registration_success');
            flash('success', 'Registration successful. Enter the OTP sent to your email.');
            redirect('auth/verify-otp.php');
        } catch (Throwable $exception) {
            $pdo->rollBack();
            log_message('Registration failed', ['message' => $exception->getMessage(), 'email' => $email]);
            security_log(null, $email, 'registration_failure');
            $errors[] = 'We could not complete registration right now. Please verify mail settings and try again.';
        }
    }
}

render('Create Account', function () use ($errors): void {
    ?>
    <section class="auth-grid">
        <div class="hero-card">
            <span class="eyebrow">Production-Ready Auth</span>
            <h1>Secure registration with OTP verification.</h1>
            <p>Build trust from the very first step with strong passwords, verified email ownership, and abuse protection.</p>
            <ul class="feature-list">
                <li>Strong password policy and common-password blocking</li>
                <li>OTP stored hashed with expiry and attempt limits</li>
                <li>Prepared statements, CSRF protection, and logging</li>
            </ul>
        </div>
        <div class="card">
            <h2>Create your account</h2>
            <p class="muted">Use a real email address so we can verify ownership.</p>
            <?php render_alerts(); ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" novalidate data-validate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>Name</span>
                    <input type="text" name="name" value="<?= e(old('name')) ?>" required minlength="2" maxlength="120">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190">
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" required minlength="12" data-password-strength>
                    <small>Minimum 12 chars, uppercase, lowercase, number, symbol.</small>
                </label>
                <label>
                    <span>Confirm password</span>
                    <input type="password" name="password_confirmation" required minlength="12">
                </label>
                <button class="btn btn-primary" type="submit">Register & send OTP</button>
                <p class="form-footer">Already have an account? <a href="<?= e(current_path('auth/login.php')) ?>">Sign in</a></p>
            </form>
        </div>
    </section>
    <?php
});

