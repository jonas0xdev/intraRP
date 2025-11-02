<?php

use App\Utils\AuditLogger;
use App\Support\SupportPasswordManager;

session_start();
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';
require __DIR__ . '/../vendor/autoload.php';

$error = null;
$auditLogger = new AuditLogger($pdo);
$supportManager = new SupportPasswordManager($pdo, $auditLogger);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($token) || empty($password)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } else {
        $auth_result = $supportManager->authenticateSupport($token, $password);

        if ($auth_result) {
            $_SESSION['support_mode'] = true;
            $_SESSION['support_session_id'] = $auth_result['session_id'];
            $_SESSION['support_password_id'] = $auth_result['support_password_id'];
            $_SESSION['support_created_by'] = $auth_result['created_by'];
            $_SESSION['support_expires_at'] = $auth_result['expires_at'];

            $_SESSION['userid'] = 999999;
            $_SESSION['cirs_user'] = 'Support-Zugang';
            $_SESSION['cirs_username'] = 'support';
            $_SESSION['username'] = 'Support';
            $_SESSION['aktenid'] = null;
            $_SESSION['role'] = 99;
            $_SESSION['role_name'] = 'Support (Temporär)';
            $_SESSION['role_color'] = 'warning';
            $_SESSION['role_priority'] = 0;
            $_SESSION['role_id'] = 99;
            $_SESSION['discordtag'] = null;
            $_SESSION['permissions'] = ['full_admin'];

            $supportManager->logSupportAction(
                $auth_result['session_id'],
                'login',
                'Support-Zugang wurde verwendet'
            );

            header('Location: ' . BASE_PATH . 'index.php');
            exit;
        } else {
            $error = 'Ungültige Zugangsdaten oder Token bereits verwendet/abgelaufen.';
        }
    }
}

$expired_message = '';
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $reason = $_GET['reason'] ?? 'Unbekannter Grund';
    $expired_message = 'Ihre Support-Session ist abgelaufen: ' . htmlspecialchars($reason);
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support-Zugang - intraRP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .logo .subtitle {
            color: #666;
            font-size: 14px;
        }

        .support-badge {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 16px;
            margin-top: 24px;
            border-radius: 4px;
        }

        .info-box h3 {
            color: #1976D2;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e0e0e0;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <h1>🚨 intraRP</h1>
            <div style="margin-top: 16px;">
                <span class="support-badge">🔧 Support-Zugang</span>
            </div>
        </div>

        <?php if ($expired_message): ?>
            <div class="alert alert-warning">
                <?= htmlspecialchars($expired_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="token">Support-Token</label>
                <input
                    type="text"
                    id="token"
                    name="token"
                    required
                    autocomplete="off"
                    autofocus>
            </div>

            <div class="form-group">
                <label for="password">Ticket-ID</label>
                <input
                    type="text"
                    id="password"
                    name="password"
                    required
                    autocomplete="off">
            </div>

            <button type="submit" class="btn">
                Support-Zugang aktivieren
            </button>
        </form>

        <div class="footer">
            <a href="<?= BASE_PATH ?>login.php">← Zurück zum normalen Login</a>
        </div>
    </div>
</body>

</html>