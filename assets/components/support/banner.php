<?php

if (isset($_SESSION['support_mode']) && $_SESSION['support_mode'] === true) {
    $expiresAt = strtotime($_SESSION['support_expires_at'] ?? 'now');
    $remainingSeconds = max(0, $expiresAt - time());
    $remainingMinutes = ceil($remainingSeconds / 60);

    $loginTime = $_SESSION['support_login_time'] ?? date('H:i');
    $sessionId = $_SESSION['support_session_id'] ?? null;

    $expiresAtFormatted = date('H:i', $expiresAt);

    $actionsCount = 0;
    if ($sessionId && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM intra_support_actions_log WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $actionsCount = $result['count'];
            }
        } catch (Exception $e) {
        }
    }

    $loginTimestamp = strtotime('today ' . $loginTime);
    $elapsedMinutes = round((time() - $loginTimestamp) / 60);
    if ($elapsedMinutes < 0) $elapsedMinutes = 0;

    $bannerClass = '';
    if ($remainingMinutes <= 5) {
        $bannerClass = 'danger';
    } elseif ($remainingMinutes <= 10) {
        $bannerClass = 'warning';
    }

?>
    <style>
        .support-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 3px solid rgba(255, 255, 255, 0.2);
        }

        .support-banner.warning {
            background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);
            animation: pulse-border-warning 2s infinite;
        }

        .support-banner.danger {
            background: linear-gradient(135deg, #ef5350 0%, #e53935 100%);
            animation: pulse-border-danger 1s infinite;
        }

        @keyframes pulse-border-warning {

            0%,
            100% {
                box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4);
            }

            50% {
                box-shadow: 0 2px 12px rgba(255, 152, 0, 0.6);
            }
        }

        @keyframes pulse-border-danger {

            0%,
            100% {
                box-shadow: 0 2px 8px rgba(239, 83, 80, 0.5);
            }

            50% {
                box-shadow: 0 2px 16px rgba(239, 83, 80, 0.8);
            }
        }

        .support-banner-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .support-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
            animation: blink 1.5s infinite;
        }

        @keyframes blink {

            0%,
            50%,
            100% {
                opacity: 1;
            }

            25%,
            75% {
                opacity: 0.7;
            }
        }

        .support-info {
            display: flex;
            gap: 20px;
            font-size: 13px;
        }

        .support-info-item {
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0.95;
        }

        .support-info-item i {
            font-size: 16px;
        }

        .support-banner-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .support-timer {
            background: rgba(0, 0, 0, 0.2);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 18px;
            font-family: 'Courier New', monospace;
            min-width: 80px;
            text-align: center;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .support-timer.warning {
            background: rgba(255, 193, 7, 0.3);
            animation: pulse-warning 1s infinite;
        }

        .support-timer.danger {
            background: rgba(220, 53, 69, 0.4);
            animation: pulse-danger 0.5s infinite;
        }

        @keyframes pulse-warning {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        @keyframes pulse-danger {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .support-actions {
            display: flex;
            gap: 8px;
        }

        .support-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .support-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .support-banner {
                flex-direction: column;
                gap: 10px;
            }

            .support-info {
                flex-wrap: wrap;
            }
        }
    </style>

    <div class="support-banner <?= $bannerClass ?>">
        <div class="support-banner-left">
            <div class="support-badge">
                <i class="las la-headset"></i>
                Support-Zugriff
            </div>
            <div class="support-info">
                <div class="support-info-item" title="Wie lange ist dieser Support-Zugang bereits aktiv?">
                    <i class="las la-history"></i>
                    <span>Aktiv seit: <?= $elapsedMinutes ?> Min</span>
                </div>
                <div class="support-info-item" title="Automatischer Logout um diese Uhrzeit">
                    <i class="las la-clock"></i>
                    <span>Auto-Logout: <?= $expiresAtFormatted ?> Uhr</span>
                </div>
                <div class="support-info-item" title="Anzahl der durchgeführten Aktionen in dieser Session">
                    <i class="las la-tasks"></i>
                    <span>Aktionen: <?= $actionsCount ?></span>
                </div>
            </div>
        </div>

        <div class="support-banner-right">
            <div class="support-timer <?= $remainingMinutes <= 5 ? 'danger' : ($remainingMinutes <= 10 ? 'warning' : '') ?>"
                id="supportTimer"
                data-expires="<?= $expiresAt ?>"
                data-elapsed-start="<?= $elapsedMinutes ?>"
                title="Verbleibende Zeit bis automatischer Abmeldung">
                <i class="las la-hourglass-half"></i>
                <span id="timerDisplay"><?= sprintf('%02d:%02d', floor($remainingMinutes), $remainingMinutes % 60) ?></span>
            </div>

            <div class="support-actions">
                <a href="<?= BASE_PATH ?>admin/logout.php" class="support-btn" title="Support-Session sofort beenden und abmelden">
                    <i class="las la-sign-out-alt"></i>
                    Abmelden
                </a>
            </div>
        </div>
    </div>

    <script>
        let elapsedMinutesStart = 0;
        let startTime = Date.now();

        function updateSupportTimer() {
            const timerElement = document.getElementById('supportTimer');
            const displayElement = document.getElementById('timerDisplay');
            const bannerElement = document.querySelector('.support-banner');

            if (!timerElement || !displayElement) return;

            const expiresAt = parseInt(timerElement.dataset.expires);
            const now = Math.floor(Date.now() / 1000);
            const remaining = Math.max(0, expiresAt - now);

            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;

            displayElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

            timerElement.classList.remove('warning', 'danger');
            if (bannerElement) {
                bannerElement.classList.remove('warning', 'danger');
            }

            if (remaining <= 300) {
                timerElement.classList.add('danger');
                if (bannerElement) bannerElement.classList.add('danger');
            } else if (remaining <= 600) {
                timerElement.classList.add('warning');
                if (bannerElement) bannerElement.classList.add('warning');
            }

            const elapsedElement = document.querySelector('.support-info-item:first-child span');
            if (elapsedElement && elapsedMinutesStart !== undefined) {
                const elapsedNow = elapsedMinutesStart + Math.floor((Date.now() - startTime) / 60000);
                elapsedElement.textContent = `Aktiv seit: ${elapsedNow} Min`;
            }

            if (remaining <= 0) {
                window.location.href = '<?= BASE_PATH ?>support/login.php?expired=1&reason=Zeit+abgelaufen';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const timerElement = document.getElementById('supportTimer');
            if (timerElement && timerElement.dataset.elapsedStart) {
                elapsedMinutesStart = parseInt(timerElement.dataset.elapsedStart);
            }
        });

        setInterval(updateSupportTimer, 1000);
        updateSupportTimer();
    </script>
<?php
}
?>