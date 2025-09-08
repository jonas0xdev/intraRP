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

function getCurrentZugaenge($zugangJson)
{
    if (empty($zugangJson) || $zugangJson === '0') {
        return [];
    }

    $decoded = json_decode($zugangJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    if (isset($decoded['art'])) {
        return [$decoded];
    } else if (is_array($decoded)) {
        return $decoded;
    }

    return [];
}

function hasZugangAtLocation($zugaenge, $art, $ort, $seite)
{
    foreach ($zugaenge as $zugang) {
        if (
            $zugang['art'] === $art &&
            $zugang['ort'] === $ort &&
            $zugang['seite'] === $seite
        ) {
            return true;
        }
    }
    return false;
}

function addZugang($zugaenge, $newZugang)
{
    foreach ($zugaenge as $index => $zugang) {
        if (
            $zugang['art'] === $newZugang['art'] &&
            $zugang['ort'] === $newZugang['ort'] &&
            $zugang['seite'] === $newZugang['seite']
        ) {
            $zugaenge[$index] = $newZugang;
            return $zugaenge;
        }
    }

    $zugaenge[] = $newZugang;
    return $zugaenge;
}

function removeZugang($zugaenge, $art, $ort, $seite)
{
    return array_values(array_filter($zugaenge, function ($zugang) use ($art, $ort, $seite) {
        return !($zugang['art'] === $art &&
            $zugang['ort'] === $ort &&
            $zugang['seite'] === $seite);
    }));
}

$currentZugaenge = getCurrentZugaenge($daten['c_zugang'] ?? '');

function displayAllZugaenge($zugangJson)
{
    if (!isset($zugangJson) || $zugangJson === null) {
        return '<em>Nicht gesetzt</em>';
    }

    if ($zugangJson === '0') {
        return 'Kein Zugang';
    }

    $zugaenge = getCurrentZugaenge($zugangJson);

    if (empty($zugaenge)) {
        return '<em>Keine gültigen Zugänge</em>';
    }

    $displays = [];
    foreach ($zugaenge as $zugang) {
        $artNames = ['pvk' => 'PVK', 'zvk' => 'ZVK', 'io' => 'i.o.'];
        $artName = $artNames[$zugang['art']] ?? $zugang['art'];
        $displays[] = sprintf(
            '%s %s %s %s',
            $artName,
            $zugang['groesse'],
            $zugang['ort'],
            $zugang['seite']
        );
    }

    return implode('<br>', $displays);
}

function hasAnyZugang($zugangJson)
{
    $zugaenge = getCurrentZugaenge($zugangJson);
    return !empty($zugaenge);
}

function getCurrentMedikamente($medikamenteJson)
{
    if (empty($medikamenteJson) || $medikamenteJson === '0') {
        return [];
    }

    $decoded = json_decode($medikamenteJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    if (is_array($decoded)) {
        return $decoded;
    }

    return [];
}

function displayAllMedikamente($medikamenteJson)
{
    if (!isset($medikamenteJson) || $medikamenteJson === null) {
        return '<em>Nicht gesetzt</em>';
    }

    if ($medikamenteJson === '0') {
        return 'Keine Medikamente';
    }

    $medikamente = getCurrentMedikamente($medikamenteJson);

    if (empty($medikamente)) {
        return '<em>Keine gültigen Medikamente</em>';
    }

    usort($medikamente, function ($a, $b) {
        return strcmp($a['zeit'], $b['zeit']);
    });

    $displays = [];
    foreach ($medikamente as $med) {
        // Einheit für Anzeige konvertieren
        $displayEinheit = $med['einheit'];
        if ($displayEinheit === 'mcg') {
            $displayEinheit = '&micro;g';
        } else if ($displayEinheit === 'IE') {
            $displayEinheit = 'I.E.';
        }

        $displays[] = sprintf(
            '%s: %s %s %s %s',
            $med['zeit'],
            $med['wirkstoff'],
            $med['dosierung'],
            $displayEinheit,
            $med['applikation']
        );
    }

    return implode('<br>', $displays);
}

function hasAnyMedikamente($medikamenteJson)
{
    $medikamente = getCurrentMedikamente($medikamenteJson);
    return !empty($medikamente);
}
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

<body data-page="massnahmen">
    <?php
    include __DIR__ . '/../../../assets/components/enotf/topbar.php';
    ?>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <?php include __DIR__ . '/../../../assets/components/enotf/nav.php'; ?>
                <div class="col" id="edivi__content" style="padding-left: 0">
                    <div class="row" style="margin-left: 0">
                        <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" data-requires="awsicherung_neu">
                                <span>Atemwege</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" data-requires="b_beatmung">
                                <span>Atmung</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" data-requires="c_zugang">
                                <span>Zugang</span>
                            </a>
                            <a href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" data-requires="medis">
                                <span>Medikamente</span>
                            </a>
                        </div>
                        <?php
                        $awsicherung_neu_labels = [
                            1 => 'keine',
                            2 => 'Endotrachealtubus',
                            3 => 'Larynxtubus/-maske',
                            4 => 'Guedel-/Wendltubus',
                            99 => 'Sonstige'
                        ];
                        $b_beatmung_labels = [
                            1 => 'Spontanatmung',
                            2 => 'Assistierte Beatmung',
                            3 => 'Kontrollierte Beatmung',
                            4 => 'Maschinelle Beatmung',
                            5 => 'keine'
                        ];
                        $o2gabe_labels = [
                            0 => 'keine',
                            1 => '1 L/min',
                            2 => '2 L/min',
                            3 => '3 L/min',
                            4 => '4 L/min',
                            5 => '5 L/min',
                            6 => '6 L/min',
                            7 => '7 L/min',
                            8 => '8 L/min',
                            9 => '9 L/min',
                            10 => '10 L/min',
                            11 => '11 L/min',
                            12 => '12 L/min',
                            13 => '13 L/min',
                            14 => '14 L/min',
                            15 => '15 L/min'
                        ];
                        ?>
                        <div class="col edivi__overview-container">
                            <div class="row edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atemwege/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h6 class="fw-bold pt-1 pb-0">Atemwege</h6>
                                <div class="col border border-light py-1"><span class="fw-bold">Atemwegssicherung</span> <?= $awsicherung_neu_labels[$daten['awsicherung_neu']] ?? '' ?></div>
                            </div>
                            <div class="row edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/atmung/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h6 class="fw-bold pt-1 pb-0">Atmung</h6>
                                <div class="col border border-end-0 border-light py-1"><span class="fw-bold">Beatmung</span> <?= $b_beatmung_labels[$daten['b_beatmung']] ?? '' ?></div>
                                <div class="col border border-light py-1"><span class="fw-bold">O2-Gabe</span> <?= $o2gabe_labels[$daten['o2gabe']] ?? '' ?></div>
                            </div>
                            <div class="row edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/zugang/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h6 class="fw-bold pt-1 pb-0">Zugänge</h6>
                                <div class="col border border-light py-1" style="min-height: 15vh;">
                                    <?= displayAllZugaenge($daten['c_zugang'] ?? '') ?>
                                </div>
                            </div>
                            <div class="row edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/massnahmen/medikamente/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h6 class="fw-bold pt-1 pb-0">Medikamente</h6>
                                <div class="col border border-light py-1" style="min-height: 40vh;">
                                    <?= displayAllMedikamente($daten['medis'] ?? '') ?>
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