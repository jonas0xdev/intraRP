<?php
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', SYSTEM_URL);
ini_set('session.cookie_lifetime', 604800);
ini_set('session.cookie_secure', true);

session_start();

if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
    header('Location: ' . BASE_PATH . 'index.php');
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php
    $SITE_TITLE = 'Login';
    include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
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
                <img src="https://dev.intrarp.de/assets/img/defaultLogo.webp" alt="intraRP Logo" class="mb-4" width="75%" height="auto">
                <div class="card px-4 py-3 text-center">
                    <h1 id="loginHeader"><?php echo SYSTEM_NAME ?></h1>
                    <p class="subtext">Das Intranet der Stadt <?php echo SERVER_CITY ?>!</p>

                    <?php
                    $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
                    
                    if ($registrationMode === 'closed') {
                        echo '<div class="alert alert-warning mb-3" role="alert">';
                        echo '<i class="las la-exclamation-triangle"></i> Registrierung für neue Benutzer ist derzeit geschlossen.';
                        echo '</div>';
                    } elseif ($registrationMode === 'code') {
                        echo '<div class="alert alert-info mb-3" role="alert">';
                        echo '<i class="las la-info-circle"></i> Neue Benutzer benötigen einen Registrierungscode.';
                        echo '</div>';
                    }
                    ?>

                    <div class="text-center mb-3">
                        <a href="<?= BASE_PATH ?>auth/discord.php" class="btn btn-primary btn-lg w-100"><i class="lab la-discord"></i> Login</a>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-3 small text-center">Hintergrundvideo: <a href="https://www.youtube.com/watch?v=9z1qetAaiBA" target="_blank" rel="nofollow">Rosenbauer Group: "Alles für diesen Moment. - Rosenbauer" (YouTube)</a><br>
            &copy; <?php echo date("Y") ?> <a href="https://intrarp.de">intraRP</a> | Alle Rechte vorbehalten</p>
    </div>
</body>

</html>