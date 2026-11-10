# SecureAuth

Production-style authentication system using core PHP, MySQL, HTML, CSS, JavaScript, and PHPMailer.

## Features

- Registration with strong password rules and email OTP verification
- Login with account lockout, IP logging, secure session handling, and remember-me tokens
- Optional email-based 2FA
- Forgot password with OTP verification and session invalidation
- Dashboard protected by inactivity timeout and session version checks
- CSRF protection, XSS-safe output, prepared statements, security headers, and audit logging

## Project Structure

```text
/config
/includes
/auth
/dashboard
/assets
/logs
/database
```

## Requirements

- PHP 8.1+
- MySQL / MariaDB
- XAMPP or MAMP
- Composer

## Setup on XAMPP / MAMP

1. Copy `.env.example` to `.env`.
2. Update database, app URL, and Gmail SMTP credentials in `.env`.
3. Create a MySQL database named `secure_auth`.
4. Import [`database/schema.sql`](/Applications/XAMPP/xamppfiles/htdocs/secure-auth/database/schema.sql).
5. From the project root run:

```bash
composer install
```

6. Make sure Apache serves `http://localhost/secure-auth`.
7. Open the app and register a user.

## Local Development Without SMTP

- Set `MAIL_MODE=log` in `.env` for localhost testing.
- OTP codes will be written to `logs/mail.log` instead of being sent by email.
- Switch back to `MAIL_MODE=smtp` after Composer and Gmail SMTP are configured.

## Gmail App Password Setup

1. Enable 2-Step Verification on your Google account.
2. Open Google Account > Security > App passwords.
3. Create a new app password for Mail.
4. Put the generated 16-character password into `MAIL_PASSWORD` in `.env`.
5. Use your Gmail address in `MAIL_USERNAME` and `MAIL_FROM_ADDRESS`.

## Security Notes

- `SESSION_SECURE_COOKIE=true` should be used when running over HTTPS.
- Change `APP_KEY` to a long random secret before production.
- `logs/security.log` stores application-side security events.
- Password reset increments `session_version`, which invalidates older sessions.

## Default URLs

- Register: `/auth/register.php`
- Verify email OTP: `/auth/verify-otp.php`
- Login: `/auth/login.php`
- Forgot password: `/auth/forgot-password.php`
- Dashboard: `/dashboard/index.php`
