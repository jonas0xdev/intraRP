<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Remove ALL existing CSP headers
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');

    // Set permissive headers for FiveM
    header('Content-Security-Policy: frame-ancestors *', true);
    header('Access-Control-Allow-Origin: *');
}


session_start();
require_once __DIR__ . '/../assets/config/config.php';

if (isset($_SESSION['fahrername']) && isset($_SESSION['protfzg'])) {
    header("Location: " . BASE_PATH . "enotf/overview.php");
    exit();
} else {
    header("Location: " . BASE_PATH . "enotf/loggedout.php");
    exit();
}

?>
<!DOCTYPE html>
<html>

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" id="dashboard" class="container-full position-relative">
    <h1>Wird weitergeleitet...</h1>
    <?php include __DIR__ . "/../assets/components/footer.php"; ?>

</body>

</html>