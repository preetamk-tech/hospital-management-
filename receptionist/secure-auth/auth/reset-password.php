<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_guest();

$userId = (int) ($_SESSION['reset_user_id'] ?? 0);
$verified = (bool) ($_SESSION['password_reset_verified'] ?? false);
$errors = [];

if ($userId <= 0 || !$verified) {
    flash('error', 'Please verify your password reset OTP first.');
    redirect('auth/forgot-password.php');
}

$user = find_user_by_id($userId);
if (!$user) {
    flash('error', 'User not found.');
    redirect('auth/forgot-password.php');
}

if (is_post()) {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirmation'] ?? '');

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }
    $errors = array_merge($errors, validate_password_strength($password));

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE users
             SET password = :password, session_version = session_version + 1, login_attempts = 0, lock_until = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        $deleteRemember = db()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
        $deleteRemember->execute(['user_id' => $userId]);

        unset($_SESSION['reset_user_id'], $_SESSION['password_reset_verified']);
        security_log($userId, $user['email'], 'password_reset_completed');
        flash('success', 'Password updated successfully. Please log in again.');
        redirect('auth/login.php');
    }
}

render('Reset Password', function () use ($errors): void {
    ?>
    <section class="single-card-wrap">
        <div class="card">
            <h1>Reset password</h1>
            <p class="muted">Choose a strong new password. Existing sessions will be invalidated automatically.</p>
            <?php render_alerts(); ?>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <form method="post" data-validate>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <label>
                    <span>New password</span>
                    <input type="password" name="password" minlength="12" required data-password-strength>
                </label>
                <label>
                    <span>Confirm new password</span>
                    <input type="password" name="password_confirmation" minlength="12" required>
                </label>
                <button class="btn btn-primary" type="submit">Update password</button>
            </form>
        </div>
    </section>
    <?php
});

