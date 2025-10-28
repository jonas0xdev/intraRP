<?php

namespace App\Support;

class SupportGuard
{
    public static function checkAndEnable(): bool
    {
        if (!isset($_SESSION['support_mode']) || $_SESSION['support_mode'] !== true) {
            return false;
        }

        if (!isset($_SESSION['support_expires_at'])) {
            self::terminateSupport('Session-Daten fehlen');
            return false;
        }

        $expiresAt = strtotime($_SESSION['support_expires_at']);
        if ($expiresAt < time()) {
            self::terminateSupport('Session abgelaufen');
            return false;
        }

        $_SESSION['permissions'] = ['full_admin'];
        $_SESSION['userid'] = $_SESSION['userid'] ?? 0;

        if (!isset($_SESSION['role_name']) || strpos($_SESSION['role_name'], 'Support') === false) {
            $_SESSION['role_name'] = 'Support (Temporär)';
            $_SESSION['role_color'] = 'warning';
        }

        return true;
    }

    private static function terminateSupport(string $reason): void
    {
        unset($_SESSION['support_mode']);
        unset($_SESSION['support_session_id']);
        unset($_SESSION['support_password_id']);
        unset($_SESSION['support_created_by']);
        unset($_SESSION['support_expires_at']);
        unset($_SESSION['permissions']);
        unset($_SESSION['userid']);

        $redirect = '/support/login.php?expired=1&reason=' . urlencode($reason);
        header('Location: ' . $redirect);
        exit;
    }

    public static function isSupport(): bool
    {
        return isset($_SESSION['support_mode']) && $_SESSION['support_mode'] === true;
    }

    public static function getRemainingTime(): int
    {
        if (!self::isSupport() || !isset($_SESSION['support_expires_at'])) {
            return 0;
        }

        $expiresAt = strtotime($_SESSION['support_expires_at']);
        $remaining = $expiresAt - time();

        return max(0, (int)ceil($remaining / 60));
    }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    SupportGuard::checkAndEnable();
}
