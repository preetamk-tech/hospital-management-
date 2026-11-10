<?php

declare(strict_types=1);

function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array
{
    static $cachedUser = null;

    if (!is_authenticated()) {
        return null;
    }

    if ($cachedUser !== null && (int) $cachedUser['id'] === (int) $_SESSION['user_id']) {
        return $cachedUser;
    }

    $user = find_user_by_id((int) $_SESSION['user_id']);

    if (!$user || (int) $user['session_version'] !== (int) ($_SESSION['session_version'] ?? 0)) {
        logout_user();
        return null;
    }

    if (!empty($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        logout_user();
        flash('error', 'Your session expired due to inactivity.');
        redirect('auth/login.php');
    }

    $_SESSION['last_activity'] = time();
    $cachedUser = $user;

    return $cachedUser;
}

function require_auth(): void
{
    attempt_remember_me_login();
    if (!current_user()) {
        flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

function complete_login(array $user, bool $rememberMe = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['session_version'] = (int) $user['session_version'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['pending_2fa_user_id']);

    if ($rememberMe) {
        issue_remember_me_token((int) $user['id']);
    }

    $stmt = db()->prepare('UPDATE users SET login_attempts = 0, lock_until = NULL, last_login_at = NOW(), last_login_ip = :ip WHERE id = :id');
    $stmt->execute(['ip' => client_ip(), 'id' => $user['id']]);

    security_log((int) $user['id'], $user['email'], 'login_success', ['remember_me' => $rememberMe]);
}

function logout_user(): void
{
    forget_remember_me_token();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function increment_login_failure(array $user, string $reason): void
{
    $attempts = (int) $user['login_attempts'] + 1;
    $lockUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? db_time('+' . ACCOUNT_LOCK_MINUTES . ' minutes') : null;

    $stmt = db()->prepare('UPDATE users SET login_attempts = :attempts, lock_until = :lock_until WHERE id = :id');
    $stmt->execute([
        'attempts' => $attempts,
        'lock_until' => $lockUntil,
        'id' => $user['id'],
    ]);

    security_log((int) $user['id'], $user['email'], 'login_failure', ['reason' => $reason, 'attempts' => $attempts]);
}

function account_is_locked(array $user): bool
{
    return !empty($user['lock_until']) && strtotime((string) $user['lock_until']) > time();
}

function start_two_factor_flow(array $user, bool $rememberMe): bool
{
    $otp = create_otp((int) $user['id'], 'login_2fa');
    $sent = send_otp_email($user['email'], $user['name'], $otp, 'login_2fa');

    if (!$sent) {
        security_log((int) $user['id'], $user['email'], 'mail_failure', ['purpose' => 'login_2fa']);
        return false;
    }

    $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
    $_SESSION['pending_2fa_remember'] = $rememberMe;
    security_log((int) $user['id'], $user['email'], 'two_factor_otp_sent');

    return true;
}

