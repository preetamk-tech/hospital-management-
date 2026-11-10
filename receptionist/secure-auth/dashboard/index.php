<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

require_auth();

$user = current_user();

$recentLogs = db()->prepare('SELECT event_type, ip_address, created_at FROM auth_logs WHERE user_id = :user_id ORDER BY id DESC LIMIT 8');
$recentLogs->execute(['user_id' => $user['id']]);
$logs = $recentLogs->fetchAll();

render('Dashboard', function () use ($user, $logs): void {
    ?>
    <section class="dashboard-grid">
        <div class="card large-card">
            <span class="eyebrow">Account Overview</span>
            <h1>Hello, <?= e($user['name']) ?></h1>
            <p class="muted">This area is protected by session hardening, inactivity timeout checks, and session version validation.</p>
            <div class="stats-grid">
                <div class="stat">
                    <span>Email</span>
                    <strong><?= e($user['email']) ?></strong>
                </div>
                <div class="stat">
                    <span>Verified</span>
                    <strong><?= (int) $user['verified'] === 1 ? 'Yes' : 'No' ?></strong>
                </div>
                <div class="stat">
                    <span>2FA</span>
                    <strong><?= (int) $user['two_factor_enabled'] === 1 ? 'Enabled' : 'Disabled' ?></strong>
                </div>
                <div class="stat">
                    <span>Last Login IP</span>
                    <strong><?= e($user['last_login_ip'] ?? 'N/A') ?></strong>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Security Controls</h2>
            <?php render_alerts(); ?>
            <form method="post" action="<?= e(current_path('auth/toggle-2fa.php')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="enable" value="<?= (int) $user['two_factor_enabled'] === 1 ? '0' : '1' ?>">
                <button class="btn btn-primary" type="submit">
                    <?= (int) $user['two_factor_enabled'] === 1 ? 'Disable Email 2FA' : 'Enable Email 2FA' ?>
                </button>
            </form>
            <a class="btn btn-secondary full-width" href="<?= e(current_path('auth/logout.php')) ?>">Logout securely</a>
        </div>

        <div class="card wide-card">
            <h2>Recent Security Activity</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Event</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e($log['event_type']) ?></td>
                            <td><?= e($log['ip_address']) ?></td>
                            <td><?= e($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php
});

