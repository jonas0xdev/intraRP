<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';

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

<body data-page="atemwege">
    <?php
    include __DIR__ . '/../../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/atemwege/massnahmen/1.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu">
                                <span>Atemwegssicherung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/atemwege/massnahmen/2.php?enr=<?= $daten['enr'] ?>" class="active">
                                <span>Sauerstoffgabe</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="o2gabe_1" name="o2gabe" value="0" <?php echo ($daten['o2gabe'] === '0' || $daten['o2gabe'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_1">Keine</label>

                            <input type="radio" class="btn-check" id="o2gabe_2" name="o2gabe" value="1" <?php echo ($daten['o2gabe'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_2">1 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_3" name="o2gabe" value="2" <?php echo ($daten['o2gabe'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_3">2 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_4" name="o2gabe" value="3" <?php echo ($daten['o2gabe'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_4">3 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_5" name="o2gabe" value="4" <?php echo ($daten['o2gabe'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_5">4 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_6" name="o2gabe" value="5" <?php echo ($daten['o2gabe'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_6">5 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_7" name="o2gabe" value="6" <?php echo ($daten['o2gabe'] == 6 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_7">6 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_8" name="o2gabe" value="7" <?php echo ($daten['o2gabe'] == 7 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_8">7 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_9" name="o2gabe" value="8" <?php echo ($daten['o2gabe'] == 8 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_9">8 L</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <input type="radio" class="btn-check" id="o2gabe_10" name="o2gabe" value="9" <?php echo ($daten['o2gabe'] == 9 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_10">9 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_11" name="o2gabe" value="10" <?php echo ($daten['o2gabe'] == 10 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_11">10 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_12" name="o2gabe" value="11" <?php echo ($daten['o2gabe'] == 11 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_12">11 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_13" name="o2gabe" value="12" <?php echo ($daten['o2gabe'] == 12 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_13">12 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_14" name="o2gabe" value="13" <?php echo ($daten['o2gabe'] == 13 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_14">13 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_15" name="o2gabe" value="14" <?php echo ($daten['o2gabe'] == 14 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_15">14 L</label>

                            <input type="radio" class="btn-check" id="o2gabe_16" name="o2gabe" value="15" <?php echo ($daten['o2gabe'] == 15 ? 'checked' : '') ?> autocomplete="off">
                            <label for="o2gabe_16">15 L</label>
                        </div>
                    </div>
                </div>
            </div>
    </form>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    include __DIR__ . '/../../../../assets/functions/enotf/clock.php';
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