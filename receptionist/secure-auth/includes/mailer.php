<?php

declare(strict_types=1);

if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function write_logged_otp(string $toEmail, string $otp, string $purpose): bool
{
    $path = APP_ROOT . '/logs/mail.log';
    $directory = dirname($path);

    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $entry = sprintf(
        "[%s] purpose=%s email=%s otp=%s%s",
        date('Y-m-d H:i:s'),
        $purpose,
        $toEmail,
        $otp,
        PHP_EOL
    );

    return @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) !== false;
}

function can_fallback_to_logged_mail(): bool
{
    return (string) env('APP_ENV', 'local') === 'local' || APP_DEBUG;
}

function send_otp_email(string $toEmail, string $toName, string $otp, string $purpose): bool
{
    $mailMode = (string) env('MAIL_MODE', 'smtp');

    if ($mailMode === 'log') {
        return write_logged_otp($toEmail, $otp, $purpose);
    }

    if (!class_exists(PHPMailer::class)) {
        log_message('PHPMailer not installed', ['email' => $toEmail, 'purpose' => $purpose]);
        return false;
    }

    $subjectMap = [
        'email_verification' => 'Verify your email address',
        'password_reset' => 'Your password reset code',
        'login_2fa' => 'Your login verification code',
    ];

    $contextMap = [
        'email_verification' => 'Use this OTP to activate your account.',
        'password_reset' => 'Use this OTP to reset your password.',
        'login_2fa' => 'Use this OTP to complete your login.',
    ];

    $rawPassword = (string) env('MAIL_PASSWORD');
    $normalizedPassword = preg_replace('/[\s-]+/', '', $rawPassword) ?? $rawPassword;
    $smtpHost = (string) env('MAIL_HOST', 'smtp.gmail.com');
    $smtpUsername = (string) env('MAIL_USERNAME');
    $smtpFromAddress = (string) env('MAIL_FROM_ADDRESS');
    $smtpFromName = (string) env('MAIL_FROM_NAME', APP_NAME);
    $profiles = [
        ['port' => (int) env('MAIL_PORT', 587), 'encryption' => (string) env('MAIL_ENCRYPTION', 'tls')],
        ['port' => 465, 'encryption' => PHPMailer::ENCRYPTION_SMTPS],
    ];
    $failures = [];

    foreach ($profiles as $profile) {
        $mail = new PHPMailer(true);
        $smtpDebugLog = [];

        try {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = (int) $profile['port'];
            $mail->SMTPAuth = true;
            $mail->AuthType = 'LOGIN';
            $mail->Username = $smtpUsername;
            $mail->Password = $normalizedPassword;
            $mail->SMTPSecure = (string) $profile['encryption'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            if (APP_DEBUG) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = static function (string $message, int $level) use (&$smtpDebugLog): void {
                    $smtpDebugLog[] = ['level' => $level, 'message' => trim($message)];
                };
            }
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];

            $mail->setFrom($smtpFromAddress, $smtpFromName);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subjectMap[$purpose] ?? 'Your OTP code';
            $mail->Body = sprintf(
                '<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>%s</h2><p>%s</p><p style="font-size:28px;font-weight:bold;letter-spacing:4px">%s</p><p>This code expires in %d minutes.</p></div>',
                e(APP_NAME),
                e($contextMap[$purpose] ?? 'Use this OTP to continue.'),
                e($otp),
                OTP_EXPIRY_MINUTES
            );
            $mail->AltBody = sprintf('%s OTP: %s. Expires in %d minutes.', APP_NAME, $otp, OTP_EXPIRY_MINUTES);
            $mail->send();

            return true;
        } catch (Exception $exception) {
            $failures[] = [
                'port' => (int) $profile['port'],
                'encryption' => (string) $profile['encryption'],
                'message' => $exception->getMessage(),
                'error_info' => $mail->ErrorInfo,
                'smtp_debug' => $smtpDebugLog,
            ];
        }
    }

    log_message('Mail send failure', [
        'email' => $toEmail,
        'mailer_username' => $smtpUsername,
        'from_address' => $smtpFromAddress,
        'attempts' => $failures,
    ]);

    if (can_fallback_to_logged_mail() && write_logged_otp($toEmail, $otp, $purpose)) {
        flash('info', 'SMTP failed on localhost, so the OTP was written to logs/mail.log for testing.');
        log_message('Mail send fallback to log', [
            'email' => $toEmail,
            'purpose' => $purpose,
        ]);
        return true;
    }

    return false;
}
