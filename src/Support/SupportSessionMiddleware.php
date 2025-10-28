<?php

namespace App\Support;

use PDO;
use App\Utils\AuditLogger;

class SupportSessionMiddleware
{

    private SupportPasswordManager $supportManager;
    private PDO $db;
    private AuditLogger $auditLogger;

    public function __construct(PDO $db, AuditLogger $auditLogger)
    {
        $this->db = $db;
        $this->auditLogger = $auditLogger;
        $this->supportManager = new SupportPasswordManager($db, $auditLogger);
    }

    public function validateSession(): bool
    {

        if (!isset($_SESSION['support_mode']) || !$_SESSION['support_mode']) {
            return true;
        }

        $session_id = $_SESSION['support_session_id'] ?? null;

        if (!$session_id) {
            $this->terminateSession('Ungültige Support-Session');
            return false;
        }

        $session = $this->supportManager->validateSupportSession($session_id);

        if (!$session) {
            $this->terminateSession('Support-Zugang ist abgelaufen');
            return false;
        }

        if (strtotime($session['expires_at']) < time()) {
            $this->terminateSession('Support-Zugang ist abgelaufen');
            return false;
        }

        $time_left = strtotime($session['expires_at']) - time();
        if ($time_left < 120 && $time_left > 0) {
            $minutes = ceil($time_left / 60);
            $_SESSION['support_warning'] = "⚠️ Support-Zugang läuft in {$minutes} Minute(n) ab!";
        }

        return true;
    }

    private function terminateSession(string $reason): void
    {

        if (isset($_SESSION['support_session_id'])) {
            $this->supportManager->endSupportSession($_SESSION['support_session_id']);

            $this->supportManager->logSupportAction(
                $_SESSION['support_session_id'],
                'auto_logout',
                'Support-Session automatisch beendet: ' . $reason
            );
        }

        session_destroy();

        header('Location: support_login.php?expired=1&reason=' . urlencode($reason));
        exit;
    }

    public function renderSupportBanner(): string
    {

        if (!isset($_SESSION['support_mode']) || !$_SESSION['support_mode']) {
            return '';
        }

        $expires_at = $_SESSION['support_expires_at'] ?? '';
        $time_left = strtotime($expires_at) - time();
        $minutes_left = ceil($time_left / 60);

        $warning_class = $minutes_left <= 2 ? 'warning-urgent' : 'warning';

        $html = '
        <style>
            .support-banner {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
                padding: 12px 20px;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            .support-banner.warning-urgent {
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
                animation: pulse 1.5s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.85; }
            }
            
            .support-banner-content {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .support-banner-icon {
                font-size: 24px;
            }
            
            .support-banner-text {
                font-size: 14px;
                font-weight: 500;
            }
            
            .support-banner-time {
                background: rgba(255,255,255,0.2);
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
            }
            
            .support-banner-logout {
                background: rgba(255,255,255,0.9);
                color: #dc3545;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                font-size: 13px;
            }
            
            .support-banner-logout:hover {
                background: white;
            }
            
            body {
                padding-top: 50px !important;
            }
        </style>
        
        <div class="support-banner ' . $warning_class . '" id="supportBanner">
            <div class="support-banner-content">
                <span class="support-banner-icon">🔧</span>
                <span class="support-banner-text">
                    <strong>Support-Modus aktiv</strong> - Alle Aktionen werden protokolliert
                </span>
                <span class="support-banner-time" id="timeLeft">
                    ⏱️ Noch ' . $minutes_left . ' Min.
                </span>
            </div>
            <button class="support-banner-logout" onclick="endSupportSession()">
                Support-Zugang beenden
            </button>
        </div>
        
        <script>
            function updateTimeLeft() {
                const expiresAt = new Date("' . $expires_at . '").getTime();
                const now = new Date().getTime();
                const timeLeft = expiresAt - now;
                
                if (timeLeft <= 0) {
                    window.location.href = "support_login.php?expired=1";
                    return;
                }
                
                const minutes = Math.ceil(timeLeft / 60000);
                document.getElementById("timeLeft").innerHTML = "⏱️ Noch " + minutes + " Min.";
                
                if (minutes <= 2) {
                    document.getElementById("supportBanner").classList.add("warning-urgent");
                }
            }
            
            setInterval(updateTimeLeft, 10000);
            
            function endSupportSession() {
                if (confirm("Möchten Sie den Support-Zugang jetzt beenden?")) {
                    window.location.href = "support_logout.php";
                }
            }
        </script>
        ';

        return $html;
    }

    public function logAction(
        string $action_type,
        string $description,
        ?string $table = null,
        ?int $record_id = null,
        $old_value = null,
        $new_value = null
    ): bool {

        if (!isset($_SESSION['support_mode']) || !$_SESSION['support_mode']) {
            return false;
        }

        return $this->supportManager->logSupportAction(
            $_SESSION['support_session_id'],
            $action_type,
            $description,
            $table,
            $record_id,
            $old_value,
            $new_value
        );
    }

    public function isSupportMode(): bool
    {
        return isset($_SESSION['support_mode']) && $_SESSION['support_mode'] === true;
    }

    public function getSupportSessionInfo(): ?array
    {

        if (!$this->isSupportMode()) {
            return null;
        }

        return [
            'session_id' => $_SESSION['support_session_id'] ?? null,
            'created_by' => $_SESSION['support_created_by'] ?? null,
            'expires_at' => $_SESSION['support_expires_at'] ?? null,
            'time_left' => strtotime($_SESSION['support_expires_at']) - time()
        ];
    }
}
