<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

$daten = array();

if (isset($_GET['enr'])) {
    $queryget = "SELECT * FROM intra_edivi WHERE enr = :enr";
    $stmt = $pdo->prepare($queryget);
    $stmt->execute(['enr' => $_GET['enr']]);

    $daten = $stmt->fetch(PDO::FETCH_ASSOC);

    if (count($daten) == 0) {
        header("Location: " . BASE_PATH . "enotf/");
        exit();
    }
} else {
    header("Location: " . BASE_PATH . "enotf/");
    exit();
}

if ($daten['freigegeben'] == 1) {
    $ist_freigegeben = true;
} else {
    $ist_freigegeben = false;
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>[#<?= $daten['enr'] ?>] &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/divi.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="<?= BASE_PATH ?>assets/favicon/favicon.svg" />
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?= BASE_PATH ?>assets/favicon/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?php echo SYSTEM_NAME ?>" />
    <link rel="manifest" href="<?= BASE_PATH ?>assets/favicon/site.webmanifest" />
    <!-- Metas -->
    <meta name="theme-color" content="#ffaf2f" />
    <meta property="og:site_name" content="<?php echo SERVER_NAME ?>" />
    <meta property="og:url" content="<?= $prot_url ?>" />
    <meta property="og:title" content="[#<?= $daten['enr'] ?>] &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?>" />
    <meta property="og:image" content="https://<?php echo SYSTEM_URL ?>/assets/img/aelrd.png" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
</head>

<body data-page="erstbefund">
    <?php
    include __DIR__ . '/../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content">
                    <div class="row">
                        <div class="col">
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/atemwege/diagnostik/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Diagnostik Atemwege</h5>
                                <div class="col">
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <?php
                                                    $awfrei_labels = [
                                                        1 => 'frei',
                                                        2 => 'gefährdet',
                                                        3 => 'verlegt'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Atemwegszustand</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $awfrei_labels[$daten['awfrei_1']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <?php
                                                    $zyanose_labels = [
                                                        1 => 'Nein',
                                                        2 => 'Ja'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Zyanose</label><br>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $zyanose_labels[$daten['zyanose_1']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/atemwege/massnahmen/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                        <h5 class="text-light px-2 py-1">Maßnahmen Atemwege</h5>
                        <div class="col">
                            <div class="row mt-2 mb-4">
                                <div class="col">
                                    <?php
                                    $awsicherung_labels = [
                                        0 => 'Keine',
                                        1 => 'Endotrachealtubus',
                                        2 => 'Larynxtubus/-maske',
                                        3 => 'Guedel-/Wendltubus',
                                        99 => 'Sonstige'
                                    ];
                                    ?>
                                    <label class="edivi__description">Atemwegssicherung</label><br>
                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $awsicherung_labels[$daten['awsicherung_neu']] ?? '' ?>">
                                </div>
                                <div class="col">
                                    <label class="edivi__description">Sauerstoffgabe</label>
                                    <div class="row">
                                        <div class="col"><input class="w-100 vitalparam form-control" type="text" value="<?= $daten['o2gabe'] ?>" style="display:inline" readonly> <small>L/min</small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../assets/functions/enotf/clock.php';
    ?>
    <?php if ($ist_freigegeben) : ?>
        <script>
            var formElements = document.querySelectorAll('input, textarea');
            var selectElements2 = document.querySelectorAll('select');
            var inputElements2 = document.querySelectorAll('.btn-check');
            var inputElements3 = document.querySelectorAll('.form-check-input');

            formElements.forEach(function(element) {
                element.setAttribute('readonly', 'readonly');
            });

            selectElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements2.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });

            inputElements3.forEach(function(element) {
                element.setAttribute('disabled', 'disabled');
            });
        </script>
    <?php endif; ?>
</body>

</html>