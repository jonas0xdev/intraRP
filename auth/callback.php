<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';

use League\OAuth2\Client\Provider\GenericProvider;
use App\Helpers\ProtocolDetection;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['oauth2state']) || !isset($_SESSION['oauth2state_time'])) {
    exit('Session expired. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

if (time() - $_SESSION['oauth2state_time'] > 300) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['oauth2state_time']);
    exit('Authorization expired. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

$provider = new GenericProvider([
    'clientId'                => $_ENV['DISCORD_CLIENT_ID'],
    'clientSecret'            => $_ENV['DISCORD_CLIENT_SECRET'],
    'redirectUri'             => ProtocolDetection::buildRedirectUri('auth/callback.php'),
    'urlAuthorize'            => 'https://discord.com/api/oauth2/authorize',
    'urlAccessToken'          => 'https://discord.com/api/oauth2/token',
    'urlResourceOwnerDetails' => 'https://discord.com/api/users/@me',
]);

if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['oauth2state_time']);
    exit('Invalid state parameter. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

unset($_SESSION['oauth2state']);
unset($_SESSION['oauth2state_time']);

if (!isset($_GET['code'])) {
    exit('Authorization code not provided.');
}

try {
    $accessToken = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    $resourceOwner = $provider->getResourceOwner($accessToken);
    $discordUser = $resourceOwner->toArray();

    $discordId = $discordUser['id'];
    $username = $discordUser['username'];
    $avatar = $discordUser['avatar'];

    $adminRoleStmt = $pdo->prepare("SELECT id FROM intra_users_roles WHERE admin = 1 LIMIT 1");
    $adminRoleStmt->execute();
    $adminRole = $adminRoleStmt->fetch();

    if (!$adminRole) {
        exit('Admin role not configured in intra_users_roles table.');
    }

    $defaultRoleStmt = $pdo->prepare("SELECT id FROM intra_users_roles WHERE `default` = 1 LIMIT 1");
    $defaultRoleStmt->execute();
    $defaultRole = $defaultRoleStmt->fetch();

    if (!$defaultRole) {
        exit('Default role not configured in intra_users_roles table.');
    }

    $checkStmt = $pdo->query("SELECT COUNT(*) FROM intra_users");
    $userCount = $checkStmt->fetchColumn();

    if ($userCount == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
            VALUES (:discord_id, :username, NULL, :role, :full_admin)
        ");
        $stmt->execute([
            'discord_id' => $discordId,
            'username'   => $username,
            'role'       => $adminRole['id'],
            'full_admin' => 1
        ]);
    }

    $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
    $stmt->execute(['discord_id' => $discordId]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['userid'] = $user['id'];
        $_SESSION['cirs_user'] = $user['fullname'];
        $_SESSION['cirs_username'] = $user['username'];
        $_SESSION['aktenid'] = $user['aktenid'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['discordtag'] = $user['discord_id'];

        if ($user['full_admin'] == 1) {
            $_SESSION['permissions'] = ['full_admin'];
        } else {
            $roleStmt = $pdo->prepare("SELECT permissions FROM intra_users_roles WHERE id = :role_id");
            $roleStmt->execute(['role_id' => $user['role']]);
            $role = $roleStmt->fetch();

            if ($role && isset($role['permissions'])) {
                $_SESSION['permissions'] = json_decode($role['permissions'], true) ?? [];
            } else {
                $_SESSION['permissions'] = [];
            }
        }
    } else {
        // Check registration mode
        $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';

        if ($registrationMode === 'closed') {
            // No registration allowed
            exit('Registrierung ist derzeit geschlossen. Bitte wenden Sie sich an einen Administrator. <a href="' . BASE_PATH . 'login.php">Zurück zum Login</a>');
        } elseif ($registrationMode === 'code') {
            // Check for valid registration code
            $code = $_SESSION['registration_code'] ?? null;
            
            if (!$code) {
                // Redirect to code entry page
                header('Location: ' . BASE_PATH . 'auth/register-code.php?discord_id=' . urlencode($discordId));
                exit;
            }

            $codeStmt = $pdo->prepare("SELECT * FROM intra_registration_codes WHERE code = :code AND is_used = 0");
            $codeStmt->execute(['code' => $code]);
            $codeRecord = $codeStmt->fetch();

            if (!$codeRecord) {
                unset($_SESSION['registration_code']);
                exit('Ungültiger oder bereits verwendeter Registrierungscode. <a href="' . BASE_PATH . 'login.php">Zurück zum Login</a>');
            }

            // Create user with the code
            $insertStmt = $pdo->prepare("
                INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
                VALUES (:discord_id, :username, NULL, :role, :full_admin)
            ");
            $insertStmt->execute([
                'discord_id' => $discordId,
                'username'   => $username,
                'role'       => $defaultRole['id'],
                'full_admin' => 0
            ]);

            // Mark code as used
            $userId = $pdo->lastInsertId();
            $updateCodeStmt = $pdo->prepare("UPDATE intra_registration_codes SET is_used = 1, used_by = :user_id, used_at = NOW() WHERE id = :code_id");
            $updateCodeStmt->execute(['user_id' => $userId, 'code_id' => $codeRecord['id']]);

            unset($_SESSION['registration_code']);

            $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
            $stmt->execute(['discord_id' => $discordId]);
            $user = $stmt->fetch();
        } else {
            // Open registration
            $insertStmt = $pdo->prepare("
                INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
                VALUES (:discord_id, :username, NULL, :role, :full_admin)
            ");
            $insertStmt->execute([
                'discord_id' => $discordId,
                'username'   => $username,
                'role'       => $defaultRole['id'],
                'full_admin' => 0
            ]);

            $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
            $stmt->execute(['discord_id' => $discordId]);
            $user = $stmt->fetch();
        }

        $_SESSION['userid'] = $user['id'];
        $_SESSION['cirs_user'] = $user['fullname'];
        $_SESSION['cirs_username'] = $user['username'];
        $_SESSION['aktenid'] = $user['aktenid'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['discordtag'] = $user['discord_id'];
        $_SESSION['permissions'] = [];
    }

    $redirectUrl = $_SESSION['redirect_url'] ?? BASE_PATH . 'index.php';
    unset($_SESSION['redirect_url']);
    header("Location: $redirectUrl");
    exit;
} catch (Exception $e) {
    echo 'Failed to get access token: ' . $e->getMessage();
    exit;
}
