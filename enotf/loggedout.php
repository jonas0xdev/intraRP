<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

unset(
    $_SESSION['fahrername'],
    $_SESSION['fahrerquali'],
    $_SESSION['beifahrername'],
    $_SESSION['beifahrerquali'],
    $_SESSION['weiterername'],
    $_SESSION['weitererquali'],
    $_SESSION['protfzg']
);

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "eNOTF";
    include __DIR__ . '/../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="edivi__login-buttons">
                        <div class="row">
                            <div class="col">
                                Sie sind nicht angemeldet!
                            </div>
                            <div class="col-3">
                                <a href="login.php">anmelden</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>