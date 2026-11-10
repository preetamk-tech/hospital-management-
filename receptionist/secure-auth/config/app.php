<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

loadEnv(dirname(__DIR__) . '/.env');

date_default_timezone_set('Asia/Kolkata');

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', (string) env('APP_NAME', 'SecureAuth'));
define('APP_URL', rtrim((string) env('APP_URL', 'http://localhost/secure-auth'), '/'));
define('APP_DEBUG', (bool) env('APP_DEBUG', false));
define('SESSION_TIMEOUT', (int) env('SESSION_LIFETIME', 1800));
define('REMEMBER_ME_DAYS', (int) env('REMEMBER_ME_DAYS', 30));
define('OTP_EXPIRY_MINUTES', (int) env('OTP_EXPIRY_MINUTES', 5));
define('OTP_MAX_ATTEMPTS', (int) env('OTP_MAX_ATTEMPTS', 5));
define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', 5));
define('ACCOUNT_LOCK_MINUTES', (int) env('ACCOUNT_LOCK_MINUTES', 15));
define('OTP_RESEND_COOLDOWN', (int) env('OTP_RESEND_COOLDOWN', 60));
define('IP_MAX_ATTEMPTS', (int) env('IP_MAX_ATTEMPTS', 20));
define('IP_BLOCK_MINUTES', (int) env('IP_BLOCK_MINUTES', 15));

error_reporting(APP_DEBUG ? E_ALL : 0);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/php-error.log');

$secureCookie = (bool) env('SESSION_SECURE_COOKIE', false);
$sessionName = (string) env('SESSION_NAME', 'secure_auth_session');

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/mailer.php';
require_once APP_ROOT . '/includes/auth.php';

