<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

session_start();

if (!isset($_GET['discord_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$discordId = $_GET['discord_id'];

// Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM intra_users WHERE discord_id = :discord_id");
$stmt->execute(['discord_id' => $discordId]);
if ($stmt->fetch()) {
    header('Location: ' . BASE_PATH . 'auth/discord.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = 'Bitte geben Sie einen Registrierungscode ein.';
    } else {
        // Verify the code exists and is not used
        $codeStmt = $pdo->prepare("SELECT 1 FROM intra_registration_codes WHERE code = :code AND is_used = 0");
        $codeStmt->execute(['code' => $code]);
        
        if ($codeStmt->fetchColumn()) {
            $_SESSION['registration_code'] = $code;
            header('Location: ' . BASE_PATH . 'auth/discord.php');
            exit;
        } else {
            $error = 'Ungültiger oder bereits verwendeter Registrierungscode.';
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php
    $SITE_TITLE = 'Registrierungscode';
    include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="alogin" class="container-full position-relative">
    <div id="video-background">
        <iframe
            src="https://www.youtube.com/embed/9z1qetAaiBA?autoplay=1&mute=1&loop=1&playlist=9z1qetAaiBA&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1&fs=0"
            frameborder="0"
            allow="autoplay; encrypted-media"
            allowfullscreen>
        </iframe>
    </div>

    <div class="container d-flex justify-content-center align-items-center flex-column h-100">
        <div class="row" style="width:30%">
            <div class="col text-center">
                <img src="<?= SYSTEM_LOGO ?>" alt="intraRP Logo" class="mb-4" width="75%" height="auto">
                <div class="card px-4 py-3">
                    <h1 id="loginHeader">Registrierungscode</h1>
                    <p class="subtext mb-4">Bitte geben Sie Ihren Registrierungscode ein, um fortzufahren.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="code" placeholder="Registrierungscode" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Weiter</button>
                    </form>

                    <div class="mt-3">
                        <a href="<?= BASE_PATH ?>login.php" class="btn btn-secondary w-100">Zurück zum Login</a>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-3 small text-center">
            &copy; <?php echo date("Y") ?> <a href="https://intrarp.de">intraRP</a> | Alle Rechte vorbehalten
        </p>
    </div>
</body>

</html>
