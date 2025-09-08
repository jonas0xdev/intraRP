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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awfrei_1,awsicherung_neu,zyanose_1">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_symptome,b_auskult">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/kreislauf/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_kreislauf,c_ekg">
                                <span>Kreislauf</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/index.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein,d_ex_1,d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2,d_gcs_1,d_gcs_2,d_gcs_3" class="active">
                                <span>Neurologie</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/index.php?enr=<?= $daten['enr'] ?>" data-requires="v_muster_k,v_muster_t,v_muster_a,v_muster_al,v_muster_bl,v_muster_w">
                                <span>Erweitern</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/1.php?enr=<?= $daten['enr'] ?>" data-requires="d_bewusstsein">
                                <span>Bewusstseinslage</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/2.php?enr=<?= $daten['enr'] ?>" data-requires="d_ex_1">
                                <span>Extremitätenbewegung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/3.php?enr=<?= $daten['enr'] ?>" data-requires="d_pupillenw_1,d_pupillenw_2,d_lichtreakt_1,d_lichtreakt_2">
                                <span>Pupillen</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/4.php?enr=<?= $daten['enr'] ?>" data-requires="d_gcs_1,d_gcs_2,d_gcs_3" class="active">
                                <span>GCS</span>
                            </a>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">Augen öffnen</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-0" name="d_gcs_1" value="0" <?php echo ($daten['d_gcs_1'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-0">spontan</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-1" name="d_gcs_1" value="1" <?php echo ($daten['d_gcs_1'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-1">auf Aufforderung</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-2" name="d_gcs_1" value="2" <?php echo ($daten['d_gcs_1'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-2">auf Schmerzreiz</label>

                            <input type="radio" class="btn-check" id="d_gcs_1-3" name="d_gcs_1" value="3" <?php echo ($daten['d_gcs_1'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_1-3">kein Öffnen</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">Beste verbale Reaktion</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-0" name="d_gcs_2" value="0" <?php echo ($daten['d_gcs_2'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-0">orientiert</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-1" name="d_gcs_2" value="1" <?php echo ($daten['d_gcs_2'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-1">desorientiert</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-2" name="d_gcs_2" value="2" <?php echo ($daten['d_gcs_2'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-2">inadäquate Äußerungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-3" name="d_gcs_2" value="3" <?php echo ($daten['d_gcs_2'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-3">unverständliche Laute</label>

                            <input type="radio" class="btn-check" id="d_gcs_2-4" name="d_gcs_2" value="4" <?php echo ($daten['d_gcs_2'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_2-4">keine Reaktion</label>
                        </div>
                        <div class="col-2 d-flex flex-column edivi__interactbutton">
                            <label class="edivi__interactbutton-text">Beste motorische Reaktion</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-0" name="d_gcs_3" value="0" <?php echo ($daten['d_gcs_3'] === 0 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-0">folgt Aufforderung</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-1" name="d_gcs_3" value="1" <?php echo ($daten['d_gcs_3'] == 1 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-1">gezielte Abwehrbewegungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-2" name="d_gcs_3" value="2" <?php echo ($daten['d_gcs_3'] == 2 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-2">ungezielte Abwehrbewegungen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-3" name="d_gcs_3" value="3" <?php echo ($daten['d_gcs_3'] == 3 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-3">Beugesynergismen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-4" name="d_gcs_3" value="4" <?php echo ($daten['d_gcs_3'] == 4 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-4">Strecksynergismen</label>

                            <input type="radio" class="btn-check" id="d_gcs_3-5" name="d_gcs_3" value="5" <?php echo ($daten['d_gcs_3'] == 5 ? 'checked' : '') ?> autocomplete="off">
                            <label for="d_gcs_3-5">keine Reaktion</label>
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