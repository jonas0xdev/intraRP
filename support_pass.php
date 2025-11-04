<?php

use App\Utils\AuditLogger;
use App\Support\SupportPasswordManager;

session_start();
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions']) || !in_array('full_admin', $_SESSION['permissions'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$auditLogger = new AuditLogger($pdo);
$supportManager = new SupportPasswordManager($pdo, $auditLogger);

$success = null;
$error = null;
$generated_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'generate') {
        $ticket_id = $_POST['ticket_id'] ?? '';
        $duration = (int)($_POST['duration'] ?? 30);
        $notes = $_POST['notes'] ?? null;

        if (empty($ticket_id)) {
            $error = 'Bitte geben Sie eine Ticket-ID ein.';
        } else {
            try {
                $generated_data = $supportManager->generateSupportPassword(
                    $_SESSION['userid'],
                    $ticket_id,
                    $duration,
                    $notes
                );
                $success = 'Support-Passwort erfolgreich erstellt!';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['password_id'])) {
        $password_id = (int)$_POST['password_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM intra_support_passwords WHERE id = ? AND created_by = ?");
            $stmt->execute([$password_id, $_SESSION['userid']]);

            $auditLogger->log(
                $_SESSION['userid'],
                'Support-Passwort gelöscht',
                "Passwort-ID: {$password_id}",
                'Support-System',
                1
            );

            $success = 'Support-Passwort wurde gelöscht.';
        } catch (Exception $e) {
            $error = 'Fehler beim Löschen: ' . $e->getMessage();
        }
    }
}

$passwords = $supportManager->getAdminSupportPasswords($_SESSION['userid']);

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support-Passwort-Manager - intraRP</title>
    <link rel="stylesheet" href="/vendor/fortawesome/font-awesome/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f6fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .card h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .generated-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .generated-item {
            margin-bottom: 16px;
        }

        .generated-item label {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .generated-item .value {
            background: white;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .copy-btn:hover {
            background: #5568d3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .help-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }

        .login-url {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 16px;
            margin-top: 20px;
            border-radius: 4px;
        }

        .login-url strong {
            color: #1976D2;
            display: block;
            margin-bottom: 8px;
        }

        .login-url a {
            color: #2196F3;
            text-decoration: none;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-key"></i> Support-Passwort-Manager</h1>
            <p>Erstellen Sie temporäre Zugangsdaten für Support-Mitarbeiter</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle" style="font-size: 20px;"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-triangle" style="font-size: 20px;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($generated_data): ?>
            <div class="card">
                <h2><i class="fa-solid fa-check-circle"></i> Support-Zugang erstellt</h2>

                <div class="generated-box">
                    <div class="generated-item">
                        <label>Support-Token</label>
                        <div class="value">
                            <span id="token"><?= htmlspecialchars($generated_data['token']) ?></span>
                            <button class="copy-btn" onclick="copyToClipboard('token')">
                                <i class="fa-solid fa-copy"></i> Kopieren
                            </button>
                        </div>
                    </div>

                    <div class="generated-item">
                        <label>Ticket-ID (Passwort)</label>
                        <div class="value">
                            <span id="ticket"><?= htmlspecialchars($generated_data['ticket_id']) ?></span>
                            <button class="copy-btn" onclick="copyToClipboard('ticket')">
                                <i class="fa-solid fa-copy"></i> Kopieren
                            </button>
                        </div>
                    </div>

                    <div class="generated-item">
                        <label>Gültig bis</label>
                        <div class="value">
                            <span><?= date('d.m.Y H:i', strtotime($generated_data['expires_at'])) ?> Uhr (<?= $generated_data['expires_in_minutes'] ?> Min)</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fa-solid fa-plus-circle"></i> Neues Support-Passwort erstellen</h2>

            <form method="POST" action="">
                <input type="hidden" name="action" value="generate">

                <div class="form-group">
                    <label for="ticket_id">Ticket-ID *</label>
                    <input
                        type="text"
                        id="ticket_id"
                        name="ticket_id"
                        placeholder="z.B. intrarp-123"
                        required>
                    <p class="help-text">Diese ID dient als Passwort für den Support-Login</p>
                </div>

                <div class="form-group">
                    <label for="duration">Gültigkeitsdauer (Minuten) *</label>
                    <select id="duration" name="duration">
                        <option value="15">15 Minuten</option>
                        <option value="30" selected>30 Minuten (Standard)</option>
                        <option value="45">45 Minuten</option>
                        <option value="60">60 Minuten</option>
                    </select>
                    <p class="help-text">Maximale Dauer: 60 Minuten</p>
                </div>

                <div class="form-group">
                    <label for="notes">Notizen (Optional)</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        placeholder="z.B. Grund für Support-Zugang, betroffener User, etc."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i>
                    Support-Passwort generieren
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-history"></i> Ihre Support-Passwörter</h2>

            <?php if (empty($passwords)): ?>
                <p style="color: #6c757d; text-align: center; padding: 40px 0;">
                    <i class="fa-solid fa-info-circle" style="font-size: 48px; display: block; margin-bottom: 16px;"></i>
                    Noch keine Support-Passwörter erstellt
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ticket-ID</th>
                            <th>Erstellt</th>
                            <th>Gültig bis</th>
                            <th>Status</th>
                            <th>Sessions</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passwords as $pw): ?>
                            <?php
                            $is_expired = strtotime($pw['expires_at']) < time();
                            $is_used = $pw['used'];
                            $status_class = $is_used ? 'success' : ($is_expired ? 'danger' : 'warning');
                            $status_text = $is_used ? 'Verwendet' : ($is_expired ? 'Abgelaufen' : 'Aktiv');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($pw['ticket_id']) ?></strong>
                                    <?php if ($pw['notes']): ?>
                                        <br><small style="color: #6c757d;"><?= htmlspecialchars($pw['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($pw['created_at'])) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($pw['expires_at'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                                <td><?= $pw['session_count'] ?></td>
                                <td>
                                    <?php if (!$is_used && !$is_expired): ?>
                                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); showConfirm('Wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Support-Passwort löschen'}).then(result => { if(result) this.submit(); });">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="password_id" value="<?= $pw['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-small">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;

            navigator.clipboard.writeText(text).then(() => {
                const btn = element.parentElement.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Kopiert!';
                btn.style.background = '#27ae60';

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = '#667eea';
                }, 2000);
            });
        }
    </script>
</body>

</html>