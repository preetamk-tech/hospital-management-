<?php

declare(strict_types=1);

function log_message(string $message, array $context = []): void
{
    $path = APP_ROOT . '/logs/security.log';
    $directory = dirname($path);

    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $entry = sprintf(
        "[%s] %s %s%s",
        date('Y-m-d H:i:s'),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : '',
        PHP_EOL
    );

    $written = @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);

    if ($written === false) {
        error_log('SecureAuth log write failed: ' . $message);
    }
}

function abort_with_generic_error(): never
{
    http_response_code(500);
    exit('Something went wrong. Please try again later.');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function current_path(string $path): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function old(string $key, string $default = ''): string
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function with_old(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid request token.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
}

function render(string $title, callable $content, array $options = []): void
{
    $user = current_user();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(current_path('assets/css/styles.css')) ?>">
    </head>
    <body class="<?= e($options['body_class'] ?? '') ?>">
        <div class="page-shell">
            <header class="topbar">
                <a class="brand" href="<?= e(current_path('index.php')) ?>"><?= e(APP_NAME) ?></a>
                <nav class="topnav">
                    <?php if ($user): ?>
                        <a href="<?= e(current_path('dashboard/index.php')) ?>">Dashboard</a>
                        <a href="<?= e(current_path('auth/logout.php')) ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?= e(current_path('auth/login.php')) ?>">Login</a>
                        <a href="<?= e(current_path('auth/register.php')) ?>">Register</a>
                    <?php endif; ?>
                </nav>
            </header>
            <main class="page-content">
                <?php $content(); ?>
            </main>
        </div>
        <script src="<?= e(current_path('assets/js/app.js')) ?>" defer></script>
    </body>
    </html>
    <?php
}

function render_alerts(): void
{
    foreach (['success', 'error', 'info'] as $type) {
        $message = flash($type);
        if ($message) {
            printf('<div class="alert alert-%s">%s</div>', e($type), e($message));
        }
    }
}

function common_passwords(): array
{
    return [
        'password', 'password123', 'qwerty', 'admin123', 'welcome123',
        '12345678', 'letmein', 'iloveyou', 'abc123', 'secureauth',
    ];
}

function validate_password_strength(string $password): array
{
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must include at least one symbol.';
    }
    if (in_array(strtolower($password), common_passwords(), true)) {
        $errors[] = 'Please choose a less common password.';
    }

    return $errors;
}

function db_time(string $modifier = 'now'): string
{
    return date('Y-m-d H:i:s', strtotime($modifier));
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function security_log(?int $userId, ?string $email, string $eventType, array $details = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO auth_logs (user_id, email, event_type, ip_address, user_agent, details)
         VALUES (:user_id, :email, :event_type, :ip_address, :user_agent, :details)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'email' => $email,
        'event_type' => $eventType,
        'ip_address' => client_ip(),
        'user_agent' => user_agent(),
        'details' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
    ]);

    log_message($eventType, ['user_id' => $userId, 'email' => $email, 'details' => $details]);
}

function is_ip_blocked(string $ip): bool
{
    $stmt = db()->prepare('SELECT blocked_until FROM ip_blocks WHERE ip_address = :ip AND blocked_until > NOW() LIMIT 1');
    $stmt->execute(['ip' => $ip]);

    return (bool) $stmt->fetchColumn();
}

function block_ip(string $ip, string $reason): void
{
    $stmt = db()->prepare(
        'INSERT INTO ip_blocks (ip_address, blocked_until, reason)
         VALUES (:ip, :blocked_until, :reason)
         ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until), reason = VALUES(reason)'
    );
    $stmt->execute([
        'ip' => $ip,
        'blocked_until' => db_time('+' . IP_BLOCK_MINUTES . ' minutes'),
        'reason' => $reason,
    ]);
}

function enforce_ip_rate_limit(string $email = ''): void
{
    $ip = client_ip();
    if (is_ip_blocked($ip)) {
        flash('error', 'Too many requests from your network. Please try again later.');
        redirect('auth/login.php');
    }

    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM auth_logs
         WHERE ip_address = :ip
         AND event_type IN ('login_failure', 'otp_failure', 'password_reset_failure')
         AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
    );
    $stmt->execute(['ip' => $ip]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= IP_MAX_ATTEMPTS) {
        block_ip($ip, 'Too many failed attempts');
        security_log(null, $email ?: null, 'ip_blocked', ['reason' => 'Rate limit exceeded']);
        flash('error', 'Too many requests from your network. Please try again later.');
        redirect('auth/login.php');
    }
}

function create_otp(int $userId, string $purpose): string
{
    $otp = (string) random_int(100000, 999999);

    $delete = db()->prepare('DELETE FROM otp_codes WHERE user_id = :user_id AND purpose = :purpose');
    $delete->execute(['user_id' => $userId, 'purpose' => $purpose]);

    $insert = db()->prepare(
        'INSERT INTO otp_codes (user_id, purpose, otp_hash, expires_at, attempts, max_attempts, resend_available_at)
         VALUES (:user_id, :purpose, :otp_hash, :expires_at, 0, :max_attempts, :resend_available_at)'
    );
    $insert->execute([
        'user_id' => $userId,
        'purpose' => $purpose,
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => db_time('+' . OTP_EXPIRY_MINUTES . ' minutes'),
        'max_attempts' => OTP_MAX_ATTEMPTS,
        'resend_available_at' => db_time('+' . OTP_RESEND_COOLDOWN . ' seconds'),
    ]);

    return $otp;
}

function get_otp_record(int $userId, string $purpose): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM otp_codes WHERE user_id = :user_id AND purpose = :purpose ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId, 'purpose' => $purpose]);

    $otp = $stmt->fetch();
    return $otp ?: null;
}

function verify_otp_code(int $userId, string $purpose, string $otp): bool
{
    $record = get_otp_record($userId, $purpose);

    if (!$record || $record['consumed_at'] !== null) {
        return false;
    }

    if (strtotime($record['expires_at']) < time()) {
        return false;
    }

    if ((int) $record['attempts'] >= (int) $record['max_attempts']) {
        return false;
    }

    if (!password_verify($otp, $record['otp_hash'])) {
        $stmt = db()->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id');
        $stmt->execute(['id' => $record['id']]);
        return false;
    }

    $stmt = db()->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $record['id']]);

    return true;
}

function otp_resend_allowed(int $userId, string $purpose): bool
{
    $record = get_otp_record($userId, $purpose);
    if (!$record) {
        return true;
    }

    if (empty($record['resend_available_at'])) {
        return true;
    }

    return strtotime($record['resend_available_at']) <= time();
}

function issue_remember_me_token(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = db()->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (:user_id, :selector, :token_hash, :expires_at)');
    $stmt->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => db_time('+' . REMEMBER_ME_DAYS . ' days'),
    ]);

    setcookie('remember_me', $selector . ':' . $token, [
        'expires' => time() + (86400 * REMEMBER_ME_DAYS),
        'path' => '/',
        'secure' => (bool) env('SESSION_SECURE_COOKIE', false),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function forget_remember_me_token(): void
{
    if (!empty($_COOKIE['remember_me'])) {
        [$selector] = explode(':', $_COOKIE['remember_me'], 2);
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
    }

    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (bool) env('SESSION_SECURE_COOKIE', false),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function attempt_remember_me_login(): void
{
    if (is_authenticated() || empty($_COOKIE['remember_me'])) {
        return;
    }

    [$selector, $token] = array_pad(explode(':', $_COOKIE['remember_me'], 2), 2, null);
    if (!$selector || !$token) {
        return;
    }

    $stmt = db()->prepare(
        'SELECT rt.*, u.* FROM remember_tokens rt
         INNER JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = :selector AND rt.expires_at > NOW() LIMIT 1'
    );
    $stmt->execute(['selector' => $selector]);
    $record = $stmt->fetch();

    if (!$record || !hash_equals($record['token_hash'], hash('sha256', $token))) {
        forget_remember_me_token();
        return;
    }

    complete_login($record, true);
}

function require_guest(): void
{
    attempt_remember_me_login();
    if (is_authenticated()) {
        redirect('dashboard/index.php');
    }
}

function validate_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
