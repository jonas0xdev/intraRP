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

<body data-page="atemwege">
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
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atemwege/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Atemwege</h5>
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
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/kreislauf/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Kreislauf</h5>
                                <div class="col">
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <?php
                                                    $c_kreislauf_labels = [
                                                        1 => 'stabil',
                                                        2 => 'instabil',
                                                        3 => 'Nicht beurteilbar'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Patientenzustand</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $c_kreislauf_labels[$daten['c_kreislauf']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <?php
                                                    $c_ekg_labels = [
                                                        1 => 'Sinusrhythmus',
                                                        2 => 'STEMI',
                                                        3 => 'Absolute Arrhythmie',
                                                        4 => 'Kammerflimmern',
                                                        5 => 'AV-Block II°/III°',
                                                        6 => 'Astystolie',
                                                        98 => 'Nicht beurteilbar',
                                                        99 => 'Kein EKG'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">EKG</label><br>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $c_ekg_labels[$daten['c_ekg']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/erweitern/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Erweitern</h5>
                                <div class="col">
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <h6>Verletzungen</h6>
                                                <div class="col">
                                                    <?php
                                                    $v_muster_k_labels = [
                                                        1 => 'keine',
                                                        2 => 'leicht',
                                                        3 => 'mittel',
                                                        4 => 'schwer',
                                                        99 => 'Nicht untersucht'
                                                    ];
                                                    $v_muster_k1_labels = [
                                                        1 => 'offen',
                                                        2 => 'geschlossen'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Schädel-Hirn</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_k']] ?? '') . (!empty($daten['v_muster_k1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_k1']] ?? '') : '') ?>">
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">Wirbelsäule</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_w']] ?? '') . (!empty($daten['v_muster_w1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_w1']] ?? '') : '') ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col">
                                                    <label class="edivi__description">Thorax</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_t']] ?? '') . (!empty($daten['v_muster_t1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_t1']] ?? '') : '') ?>">
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">Abdomen</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_a']] ?? '') . (!empty($daten['v_muster_a1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_a1']] ?? '') : '') ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col">
                                                    <label class="edivi__description">Obere Extremitäten</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_al']] ?? '') . (!empty($daten['v_muster_al1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_al1']] ?? '') : '') ?>">
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">Untere Extremitäten</label>
                                                    <input type="text" class="form-control edivi__input-check" readonly
                                                        value="<?= ($v_muster_k_labels[$daten['v_muster_bl']] ?? '') . (!empty($daten['v_muster_bl1']) ? ' / ' . ($v_muster_k1_labels[$daten['v_muster_bl1']] ?? '') : '') ?>">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <h6>Schmerzen</h6>
                                                <div class="col">
                                                    <?php
                                                    $sz_nrs_labels = [
                                                        0 => 'keine',
                                                        1 => '1',
                                                        2 => '2',
                                                        3 => '3',
                                                        4 => '4',
                                                        5 => '5',
                                                        6 => '6',
                                                        7 => '7',
                                                        8 => '8',
                                                        9 => '9',
                                                        10 => '10',
                                                        98 => 'Nicht erhoben',
                                                        99 => 'Nicht beurteilbar'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Intensität</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $sz_nrs_labels[$daten['sz_nrs']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <?php
                                                    $sz_toleranz_1_labels = [
                                                        1 => 'Tolerabel',
                                                        2 => 'Nicht tolerabel'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Toleranz</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $sz_toleranz_1_labels[$daten['sz_toleranz_1']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/atmung/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Atmung</h5>
                                <div class="col">
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <?php
                                                    $b_symptome_labels = [
                                                        0 => 'unauffällig',
                                                        1 => 'Dyspnoe',
                                                        2 => 'Apnoe',
                                                        3 => 'Schnappatmung',
                                                        4 => 'Andere pathologien',
                                                        99 => 'Nicht untersucht'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Beurteilung Atemwege</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $b_symptome_labels[$daten['b_symptome']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <?php
                                                    $b_auskult_labels = [
                                                        0 => 'unauffällig',
                                                        1 => 'Spastik',
                                                        2 => 'Stridor',
                                                        3 => 'Rasselgeräusche',
                                                        4 => 'Andere pathologien',
                                                        99 => 'Nicht untersucht'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Auskultation</label><br>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $b_auskult_labels[$daten['b_auskult']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/neurologie/index.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                <h5 class="text-light px-2 py-1 edivi__group-check">Neurologie</h5>
                                <div class="col">
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col">
                                                    <?php
                                                    $d_bewusstsein_labels = [
                                                        1 => 'wach',
                                                        2 => 'somnolent',
                                                        3 => 'soporös',
                                                        4 => 'komatös'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Bewusstseinslage</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_bewusstsein_labels[$daten['d_bewusstsein']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <?php
                                                    $d_ex_1_labels = [
                                                        1 => 'uneingeschränkt',
                                                        2 => 'leicht eingeschränkt',
                                                        3 => 'stark eingeschränkt',
                                                        99 => 'Nicht beurteilbar'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Extremitätenbewegung</label><br>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_ex_1_labels[$daten['d_ex_1']] ?? '' ?>">
                                                </div>
                                                <div class="col-2">
                                                    <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_1" id="d_gcs_1" required data-mapping="4,3,2,1" autocomplete="off" style="display:none">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="0" <?php echo ($daten['d_gcs_1'] == 0 ? 'selected' : '') ?>>(4)</option>
                                                        <option value="1" <?php echo ($daten['d_gcs_1'] == 1 ? 'selected' : '') ?>>(3)</option>
                                                        <option value="2" <?php echo ($daten['d_gcs_1'] == 2 ? 'selected' : '') ?>>(2)</option>
                                                        <option value="3" <?php echo ($daten['d_gcs_1'] == 3 ? 'selected' : '') ?>>(1)</option>
                                                    </select>

                                                    <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_2" id="d_gcs_2" required data-mapping="5,4,3,2,1" autocomplete="off" style="display:none">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="0" <?php echo ($daten['d_gcs_2'] == 0 ? 'selected' : '') ?>>(5)</option>
                                                        <option value="1" <?php echo ($daten['d_gcs_2'] == 1 ? 'selected' : '') ?>>(4)</option>
                                                        <option value="2" <?php echo ($daten['d_gcs_2'] == 2 ? 'selected' : '') ?>>(3)</option>
                                                        <option value="3" <?php echo ($daten['d_gcs_2'] == 3 ? 'selected' : '') ?>>(2)</option>
                                                        <option value="4" <?php echo ($daten['d_gcs_2'] == 4 ? 'selected' : '') ?>>(1)</option>
                                                    </select>

                                                    <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_3" id="d_gcs_3" required data-mapping="6,5,4,3,2,1" autocomplete="off" style="display:none">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="0" <?php echo ($daten['d_gcs_3'] == 0 ? 'selected' : '') ?>>(6)</option>
                                                        <option value="1" <?php echo ($daten['d_gcs_3'] == 1 ? 'selected' : '') ?>>(5)</option>
                                                        <option value="2" <?php echo ($daten['d_gcs_3'] == 2 ? 'selected' : '') ?>>(4)</option>
                                                        <option value="3" <?php echo ($daten['d_gcs_3'] == 3 ? 'selected' : '') ?>>(3)</option>
                                                        <option value="4" <?php echo ($daten['d_gcs_3'] == 4 ? 'selected' : '') ?>>(2)</option>
                                                        <option value="5" <?php echo ($daten['d_gcs_3'] == 5 ? 'selected' : '') ?>>(1)</option>
                                                    </select>

                                                    <label for="_GCS_" class="edivi__description">GCS</label>
                                                    <input type="text" class="form-control" id="_GCS_" name="_GCS_" placeholder="3" value="" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col-5">
                                                    <?php
                                                    $d_pupillenw_1_labels = [
                                                        1 => 'weit',
                                                        2 => 'mittel',
                                                        3 => 'eng',
                                                        4 => 'entrundet',
                                                        99 => 'Nicht untersucht'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Pupillenweite links</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_pupillenw_1_labels[$daten['d_pupillenw_1']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">rechts</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_pupillenw_1_labels[$daten['d_pupillenw_2']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2 mb-4">
                                        <div class="col">
                                            <div class="row">
                                                <div class="col-5">
                                                    <?php
                                                    $d_lichtreakt_1_labels = [
                                                        1 => 'prompt',
                                                        2 => 'träge',
                                                        3 => 'keine',
                                                        99 => 'Nicht untersucht'
                                                    ];
                                                    ?>
                                                    <label class="edivi__description">Lichtreaktion links</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_1']] ?? '' ?>">
                                                </div>
                                                <div class="col">
                                                    <label class="edivi__description">rechts</label>
                                                    <input type="text" class="w-100 form-control edivi__input-check" readonly value="<?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_2']] ?? '' ?>">
                                                </div>
                                            </div>
                                        </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('.gcs-select');
            const gcsInput = document.getElementById('_GCS_');

            function updateGCS() {
                let total = 0;
                selects.forEach(sel => {
                    const mapping = sel.dataset.mapping.split(',').map(Number);
                    const selectedIndex = parseInt(sel.value);
                    if (!isNaN(selectedIndex) && selectedIndex < mapping.length) {
                        total += mapping[selectedIndex];
                    }
                });
                gcsInput.value = total;
            }

            selects.forEach(sel => {
                sel.addEventListener('change', updateGCS);
            });


            updateGCS();
        });
    </script>
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