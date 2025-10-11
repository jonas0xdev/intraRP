<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

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

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

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
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/print.min.css" />
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

<body>
    <div class="print__paper">
        <div class="row">
            <div class="col-5">
                <div class="row border border-dark">
                    <div class="col">
                        <h6 class="print__heading">Patientendaten</h6>
                        <div class="print__field-wrapper" data-field-name="Name">
                            <input type="text" class="w-100 print__field" value="<?= $daten['patname'] ?>" readonly>
                        </div>
                        <div class="print__field-wrapper" data-field-name="Geburtsdatum">
                            <input type="text" class="w-100 print__field" id="patgebdat" data-date="<?= $daten['patgebdat'] ?>" value="<?= !empty($daten['patgebdat']) ? date('d.m.Y', strtotime($daten['patgebdat'])) : '' ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Geschlecht</td>
                                <td>
                                    <?php if ($daten['patsex'] === 0): ?>
                                        <input type="radio" name="männlich" checked disabled />
                                        <label for="männlich">männlich</label>
                                    <?php else : ?>
                                        <input type="radio" name="männlich" disabled />
                                        <label for="männlich">männlich</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['patsex'] == 1): ?>
                                        <input type="radio" name="weiblich" checked disabled />
                                        <label for="weiblich">weiblich</label>
                                    <?php else : ?>
                                        <input type="radio" name="weiblich" disabled />
                                        <label for="weiblich">weiblich</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['patsex'] == 2): ?>
                                        <input type="radio" name="divers" checked disabled />
                                        <label for="divers">divers</label>
                                    <?php else : ?>
                                        <input type="radio" name="divers" disabled />
                                        <label for="divers">divers</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-5">
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <div class="print__field-wrapper" data-field-name="Alter">
                                        <input type="text" class="w-100 print__field" id="_AGE_" readonly>
                                    </div>
                                </td>
                                <td>Jahre</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php if ($daten['prot_by'] === 0) : ?>
                            <h1 class="print__heading-main">Einsatzprotokoll</h1>
                        <?php else : ?>
                            <h1 class="print__heading-main">Notarztprotokoll</h1>
                        <?php endif; ?>
                        <p class="print__text-small">Automatisch erstellt mit intraRP Version <?= SYSTEM_VERSION ?></p>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col-5">
                        <div class="print__field-wrapper" data-field-name="Einsatz-Nr">
                            <input type="text" class="w-100 print__field" value="<?= $daten['enr'] ?>" readonly>
                        </div>
                    </div>
                    <div class="col">
                        <?php
                        $fahrzeugname = '';
                        if ($daten['prot_by'] === 0) {
                            if (!empty($daten['fzg_transp'])) {
                                $stmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :id LIMIT 1");
                                $stmt->execute(['id' => $daten['fzg_transp']]);
                                $fahrzeugname = $stmt->fetchColumn();
                            }
                        } else {
                            if (!empty($daten['fzg_na'])) {
                                $stmt = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :id LIMIT 1");
                                $stmt->execute(['id' => $daten['fzg_na']]);
                                $fahrzeugname = $stmt->fetchColumn();
                            }
                        }
                        ?>
                        <div class="print__field-wrapper" data-field-name="Rufname">
                            <input type="text" class="w-100 print__field" value="<?= $fahrzeugname ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row border border-dark border-start-0 print__full-height">
                    <div class="col">
                        <h6 class="print__heading">Einsatztechnische Daten</h6>
                        <div class="row">
                            <div class="col">
                                <div class="print__field-wrapper" data-field-name="Einsatz-Datum/Zeit">
                                    <input type="text" class="w-100 print__field" value="<?= !empty($daten['edatum']) ? date('d.m.Y', strtotime($daten['edatum'])) : '' . " " . $daten['ezeit'] ?>" readonly>
                                </div>
                                <div class="print__field-wrapper" data-field-name="Einsatz-Ort">
                                    <input type="text" class="w-100 print__field" value="<?= $daten['eort'] ?>" readonly>
                                </div>
                                <?php
                                $zielname = '';
                                if (!empty($daten['transportziel'])) {
                                    $stmt = $pdo->prepare("SELECT name FROM intra_edivi_ziele WHERE identifier = :id LIMIT 1");
                                    $stmt->execute(['id' => $daten['transportziel']]);
                                    $zielname = $stmt->fetchColumn();
                                }
                                ?>
                                <div class="print__field-wrapper" data-field-name="Versorgung/Transp.-Ziel">
                                    <input type="text" class="w-100 print__field" value="<?= $zielname ?>" readonly>
                                </div>
                                <?php
                                $einsatzarten = [
                                    1  => 'Notfallrettung - Primäreinsatz mit NA',
                                    11 => 'Notfallrettung - Primäreinsatz ohne NA',
                                    2  => 'Notfallrettung - Verlegung mit NA',
                                    21 => 'Notfallrettung - Verlegung ohne NA',
                                    3  => 'Intensivtransport',
                                    4  => 'Krankentransport',
                                ];

                                $eart_text = $einsatzarten[$daten['eart']] ?? '';
                                ?>
                                <div class="print__field-wrapper" data-field-name="Einsatz-Art">
                                    <input type="text" class="w-100 print__field" value="<?= $eart_text ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row border border-dark border-top-0">
            <div class="col">
                <h6 class="print__heading">Notfallgeschehen, Anamnese, Erstebefund, Vormedikation, Vorbehandlung</h6>
                <div class="row">
                    <div class="col">
                        <div class="print__field-wrapper">
                            <textarea rows="25" style="resize:none" class="w-100 print__textbox" readonly><?= $daten['anmerkungen'] ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Erstbefund</h6>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Atemwege</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 1): ?>
                                        <input type="radio" name="awfrei" checked disabled />
                                        <label for="awfrei">frei</label>
                                    <?php else : ?>
                                        <input type="radio" name="awfrei" disabled />
                                        <label for="awfrei">frei</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 2): ?>
                                        <input type="radio" name="awgefährdet" checked disabled />
                                        <label for="awgefährdet">gefährdet</label>
                                    <?php else : ?>
                                        <input type="radio" name="awgefährdet" disabled />
                                        <label for="awgefährdet">gefährdet</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if ($daten['awfrei_1'] == 3): ?>
                                        <input type="radio" name="awverlegt" checked disabled />
                                        <label for="awverlegt">verlegt</label>
                                    <?php else : ?>
                                        <input type="radio" name="awverlegt" disabled />
                                        <label for="awverlegt">verlegt</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col border-start border-dark">
                        <h6 class="print__heading">Zyanose</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['zyanose_1'] == 1): ?>
                                        <input type="radio" name="zyanosenein" checked disabled />
                                        <label for="zyanosenein">Nein</label>
                                    <?php else : ?>
                                        <input type="radio" name="zyanosenein" disabled />
                                        <label for="zyanosenein">Nein</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['zyanose_1'] == 2): ?>
                                        <input type="radio" name="zyanoseja" checked disabled />
                                        <label for="zyanoseja">Ja</label>
                                    <?php else : ?>
                                        <input type="radio" name="zyanoseja" disabled />
                                        <label for="zyanoseja">Ja</label>
                                    <?php endif; ?>
                                </td>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">Atmung</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['b_symptome'] == 99): ?>
                                    <input type="radio" name="nicht_untersucht_atmung" checked disabled />
                                    <label for="nicht_untersucht_atmung">nicht untersucht</label>
                                <?php else : ?>
                                    <input type="radio" name="nicht_untersucht_atmung" disabled />
                                    <label for="nicht_untersucht_atmung">nicht untersucht</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['b_symptome'] === 0): ?>
                                        <input type="radio" name="unauffällig_atmung" checked disabled />
                                        <label for="unauffällig_atmung">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="unauffällig_atmung" disabled />
                                        <label for="unauffällig_atmung">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 1): ?>
                                        <input type="radio" name="dyspnoe" checked disabled />
                                        <label for="dyspnoe">Dyspnoe</label>
                                    <?php else : ?>
                                        <input type="radio" name="dyspnoe" disabled />
                                        <label for="dyspnoe">Dyspnoe</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 2): ?>
                                        <input type="radio" name="apnoe" checked disabled />
                                        <label for="apnoe">Apnoe</label>
                                    <?php else : ?>
                                        <input type="radio" name="apnoe" disabled />
                                        <label for="apnoe">Apnoe</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 5): ?>
                                        <input type="radio" name="beatmung_atmung" checked disabled />
                                        <label for="beatmung_atmung">Beatmung</label>
                                    <?php else : ?>
                                        <input type="radio" name="beatmung_atmung" disabled />
                                        <label for="beatmung_atmung">Beatmung</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if ($daten['b_symptome'] == 4): ?>
                                        <input type="radio" name="sonstigepatho_atmung" checked disabled />
                                        <label for="sonstigepatho_atmung">sonstige path. Atemmuster</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstigepatho_atmung" disabled />
                                        <label for="sonstigepatho_atmung">sonstige path. Atemmuster</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 3): ?>
                                        <input type="radio" name="schnappatmung" checked disabled />
                                        <label for="schnappatmung">Schnappatmung</label>
                                    <?php else : ?>
                                        <input type="radio" name="schnappatmung" disabled />
                                        <label for="schnappatmung">Schnappatmung</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_symptome'] == 6): ?>
                                        <input type="radio" name="hyperventilation" checked disabled />
                                        <label for="hyperventilation">Hyperventilation</label>
                                    <?php else : ?>
                                        <input type="radio" name="hyperventilation" disabled />
                                        <label for="hyperventilation">Hyperventilation</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h5 class="print__heading">Auskultation</h5>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['b_auskult'] == 99): ?>
                                    <input type="radio" name="nicht_untersucht_auskult" checked disabled />
                                    <label for="nicht_untersucht_auskult">nicht untersucht</label>
                                <?php else : ?>
                                    <input type="radio" name="nicht_untersucht_auskult" disabled />
                                    <label for="nicht_untersucht_auskult">nicht untersucht</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['b_auskult'] === 0): ?>
                                        <input type="radio" name="unauffällig_auskult" checked disabled />
                                        <label for="unauffällig_auskult">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_untersucht_auskult" disabled />
                                        <label for="unauffällig_auskult">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 1): ?>
                                        <input type="radio" name="spastik" checked disabled />
                                        <label for="spastik">Spastik</label>
                                    <?php else : ?>
                                        <input type="radio" name="spastik" disabled />
                                        <label for="spastik">Spastik</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 2): ?>
                                        <input type="radio" name="stridor" checked disabled />
                                        <label for="stridor">Stridor</label>
                                    <?php else : ?>
                                        <input type="radio" name="stridor" disabled />
                                        <label for="stridor">Stridor</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 4): ?>
                                        <input type="radio" name="andere_pathologien_auskult" checked disabled />
                                        <label for="andere_pathologien_auskult">Andere Pathologien</label>
                                    <?php else : ?>
                                        <input type="radio" name="andere_pathologien_auskult" disabled />
                                        <label for="andere_pathologien_auskult">Andere Pathologien</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if ($daten['b_auskult'] == 98): ?>
                                        <input type="radio" name="nicht_beurtielbar_auskult" checked disabled />
                                        <label for="nicht_beurtielbar_auskult">nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurtielbar_auskult" disabled />
                                        <label for="nicht_beurtielbar_auskult">nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['b_auskult'] == 3): ?>
                                        <input type="radio" name="rasselgeräusche" checked disabled />
                                        <label for="rasselgeräusche">Rasselgeräusche</label>
                                    <?php else : ?>
                                        <input type="radio" name="rasselgeräusche" disabled />
                                        <label for="rasselgeräusche">Rasselgeräusche</label>
                                    <?php endif; ?>
                                </td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Kreislauf</h6>
                        <?php
                        $c_kreislauf_labels = [
                            1 => 'stabil',
                            2 => 'instabil',
                            3 => 'Nicht beurteilbar'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>Zustand<br><span class="fw-bold"><?= $c_kreislauf_labels[$daten['c_kreislauf']] ?? '' ?></span></td>
                                <td>
                                    Puls
                                    <br>
                                    <?php if ($daten['c_puls_reg'] == 1): ?>
                                        <input type="radio" name="puls_regelmässig" checked disabled />
                                        <label for="puls_regelmässig">regelmäßig</label>
                                    <?php else : ?>
                                        <input type="radio" name="puls_regelmässig" disabled />
                                        <label for="puls_regelmässig">regelmäßig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Radialispuls
                                    <br>
                                    <?php if ($daten['c_puls_rad'] == 1): ?>
                                        <input type="radio" name="radialispuls_tastbar" checked disabled />
                                        <label for="radialispuls_tastbar">tastbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="radialispuls_tastbar" disabled />
                                        <label for="radialispuls_tastbar">tastbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if ($daten['c_puls_reg'] == 2): ?>
                                        <input type="radio" name="puls_arrhythmisch" checked disabled />
                                        <label for="puls_arrhythmisch">arrhythmisch</label>
                                    <?php else : ?>
                                        <input type="radio" name="puls_arrhythmisch" disabled />
                                        <label for="puls_arrhythmisch">arrhythmisch</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_puls_rad'] == 2): ?>
                                        <input type="radio" name="radialispuls_nicht_tastbar" checked disabled />
                                        <label for="radialispuls_nicht_tastbar">nicht tastbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="radialispuls_nicht_tastbar" disabled />
                                        <label for="radialispuls_nicht_tastbar">nicht tastbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <div class="row">
                            <div class="col">
                                <h6 class="print__heading">EKG</h6>
                            </div>
                            <div class="col print__text-small text-end">
                                <?php if ($daten['c_ekg'] == 99): ?>
                                    <input type="radio" name="kein_ekg" checked disabled />
                                    <label for="kein_ekg">Kein EKG</label>
                                <?php else : ?>
                                    <input type="radio" name="kein_ekg" disabled />
                                    <label for="kein_ekg">Kein EKG</label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 1): ?>
                                        <input type="radio" name="sinusrhythmus" checked disabled />
                                        <label for="sinusrhythmus">Sinusrhythmus</label>
                                    <?php else : ?>
                                        <input type="radio" name="sinusrhythmus" disabled />
                                        <label for="sinusrhythmus">Sinusrhythmus</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 4): ?>
                                        <input type="radio" name="kammerflimmern" checked disabled />
                                        <label for="kammerflimmern">VF</label>
                                    <?php else : ?>
                                        <input type="radio" name="kammerflimmern" disabled />
                                        <label for="kammerflimmern">VF</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 2): ?>
                                        <input type="radio" name="infarkt_ekg" checked disabled />
                                        <label for="infarkt_ekg">STEMI</label>
                                    <?php else : ?>
                                        <input type="radio" name="infarkt_ekg" disabled />
                                        <label for="infarkt_ekg">STEMI</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 5): ?>
                                        <input type="radio" name="avblockii" checked disabled />
                                        <label for="avblockii">AV-Block II</label>
                                    <?php else : ?>
                                        <input type="radio" name="avblockii" disabled />
                                        <label for="avblockii">AV-Block II</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 97): ?>
                                        <input type="radio" name="sonstige_ekg" checked disabled />
                                        <label for="sonstige_ekg">Sonstige</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstige_ekg" disabled />
                                        <label for="sonstige_ekg">Sonstige</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 3): ?>
                                        <input type="radio" name="absolute_arrhythmie" checked disabled />
                                        <label for="absolute_arrhythmie">Abs. Arrhythmie</label>
                                    <?php else : ?>
                                        <input type="radio" name="absolute_arrhythmie" disabled />
                                        <label for="absolute_arrhythmie">Abs. Arrhythmie</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 41): ?>
                                        <input type="radio" name="pulslose_elektrische_aktivität" checked disabled />
                                        <label for="pulslose_elektrische_aktivität">PEA</label>
                                    <?php else : ?>
                                        <input type="radio" name="pulslose_elektrische_aktivität" disabled />
                                        <label for="pulslose_elektrische_aktivität">PEA</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 7): ?>
                                        <input type="radio" name="linksschenkelblock" checked disabled />
                                        <label for="linksschenkelblock">LSB</label>
                                    <?php else : ?>
                                        <input type="radio" name="linksschenkelblock" disabled />
                                        <label for="linksschenkelblock">LSB</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 51): ?>
                                        <input type="radio" name="avblockiii" checked disabled />
                                        <label for="avblockiii">AV-Block III</label>
                                    <?php else : ?>
                                        <input type="radio" name="avblockiii" disabled />
                                        <label for="avblockiii">AV-Block III</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 98): ?>
                                        <input type="radio" name="nicht_beurteilbar_ekg" checked disabled />
                                        <label for="nicht_beurteilbar_ekg">nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurteilbar_ekg" disabled />
                                        <label for="nicht_beurteilbar_ekg">nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['c_ekg'] == 21): ?>
                                        <input type="radio" name="schrittmacherrhytmus" checked disabled />
                                        <label for="schrittmacherrhytmus">Schrittmacherrhytmus</label>
                                    <?php else : ?>
                                        <input type="radio" name="schrittmacherrhytmus" disabled />
                                        <label for="schrittmacherrhytmus">Schrittmacherrhytmus</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 6): ?>
                                        <input type="radio" name="asystolie" checked disabled />
                                        <label for="asystolie">Asystolie</label>
                                    <?php else : ?>
                                        <input type="radio" name="asystolie" disabled />
                                        <label for="asystolie">Asystolie</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($daten['c_ekg'] == 71): ?>
                                        <input type="radio" name="rechtsschenkelblock" checked disabled />
                                        <label for="rechtsschenkelblock">RSB</label>
                                    <?php else : ?>
                                        <input type="radio" name="rechtsschenkelblock" disabled />
                                        <label for="rechtsschenkelblock">RSB</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <h6 class="print__heading">Neurologie</h6>
                    </div>
                    <div class="col-8 border-start border-dark">
                        <?php
                        $d_bewusstsein_labels = [
                            1 => 'wach',
                            2 => 'analgosediert / Narkose',
                            3 => 'reagiert auf Ansprache',
                            4 => 'reagiert auf Schmerzreiz',
                            5 => 'bewusstlos',
                            97 => 'Sonstige',
                            98 => 'nicht beurteilbar',
                            99 => 'nicht untersucht'
                        ];
                        ?>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td colspan="4">
                                    Bewusstsein <span class="fw-bold"><?= $d_bewusstsein_labels[$daten['d_bewusstsein']] ?? '' ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    Augen<br><span class="fw-bold"><?= 4 - $daten['d_gcs_1'] ?></span>
                                </td>
                                <td>
                                    Verbal<br><span class="fw-bold"><?= 5 - $daten['d_gcs_2'] ?></span>
                                </td>
                                <td>
                                    Motorik<br><span class="fw-bold"><?= 6 - $daten['d_gcs_2'] ?></span>
                                </td>
                                <td>
                                    Summe<br><span class="fw-bold" style="font-size: 8pt"><span id="_GCS_"></span> GCS</span>
                                </td>
                            </tr>
                        </table>
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
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php
                        $d_pupillenw_1_labels = [
                            1 => 'weit',
                            2 => 'mittel',
                            3 => 'eng',
                            4 => 'entrundet',
                            99 => 'nicht untersucht'
                        ];
                        $d_lichtreakt_1_labels = [
                            1 => 'prompt',
                            2 => 'träge',
                            3 => 'keine',
                            99 => 'nicht untersucht'
                        ];
                        ?>
                        <h5 class="print__heading">Pupillenstatus</h5>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td>rechts</td>
                                <td>Weite<br><span class="fw-bold"><?= $d_pupillenw_1_labels[$daten['d_pupillenw_2']] ?? '' ?></span></td>
                                <td>Lichtreaktion<br><span class="fw-bold"><?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_2']] ?? '' ?></span></td>
                                <td>links</td>
                                <td>Weite<br><span class="fw-bold"><?= $d_pupillenw_1_labels[$daten['d_pupillenw_1']] ?? '' ?></span></td>
                                <td>Lichtreaktion<br><span class="fw-bold"><?= $d_lichtreakt_1_labels[$daten['d_lichtreakt_1']] ?? '' ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col print__text-small">
                        <?php
                        $d_ex_1_labels = [
                            1 => 'uneingeschränkt',
                            2 => 'leicht eingeschränkt',
                            3 => 'stark eingeschränkt',
                            99 => 'Nicht beurteilbar'
                        ];
                        ?>
                        Extremitätenbewegung<br><span class="fw-bold"><?= $d_ex_1_labels[$daten['d_ex_1']] ?? '' ?></span>
                    </div>
                    <div class="col border-start border-dark">
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
                        <table class="w-100 print__text-small">
                            <tr>
                                <td rowspan="2">Schmerzen<br><span class="fw-bold"><?= $sz_nrs_labels[$daten['sz_nrs']] ?? '' ?></span></td>
                                <td>
                                    <?php if ($daten['sz_toleranz_1'] == 1): ?>
                                        <input type="radio" name="tolerabel" checked disabled />
                                        <label for="tolerabel">Tolerabel</label>
                                    <?php else : ?>
                                        <input type="radio" name="tolerabel" disabled />
                                        <label for="tolerabel">Tolerabel</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if ($daten['sz_toleranz_1'] == 2): ?>
                                        <input type="radio" name="ntolerabel" checked disabled />
                                        <label for="ntolerabel">Nicht tolerabel</label>
                                    <?php else : ?>
                                        <input type="radio" name="ntolerabel" disabled />
                                        <label for="ntolerabel">Nicht tolerabel</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row border border-dark border-top-0">
                    <div class="col">
                        <?php
                        $psych = [];
                        if (!empty($daten['psych'])) {
                            $decoded = json_decode($daten['psych'], true);
                            if (is_array($decoded)) {
                                $psych = array_map('intval', $decoded);
                            }
                        }
                        ?>
                        <h6 class="print__heading">Psyche</h6>
                        <table class="w-100 print__text-small">
                            <tr>
                                <td></td>
                                <td>
                                    <?php if (in_array(2, $psych)): ?>
                                        <input type="radio" name="aggressiv" checked disabled />
                                        <label for="aggressiv">aggressiv</label>
                                    <?php else : ?>
                                        <input type="radio" name="aggressiv" disabled />
                                        <label for="aggressiv">aggressiv</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(5, $psych)): ?>
                                        <input type="radio" name="verwirrt" checked disabled />
                                        <label for="verwirrt">verwirrt</label>
                                    <?php else : ?>
                                        <input type="radio" name="verwirrt" disabled />
                                        <label for="verwirrt">verwirrt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(8, $psych)): ?>
                                        <input type="radio" name="erregt" checked disabled />
                                        <label for="erregt">erregt</label>
                                    <?php else : ?>
                                        <input type="radio" name="erregt" disabled />
                                        <label for="erregt">erregt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(10, $psych)): ?>
                                        <input type="radio" name="suizidal" checked disabled />
                                        <label for="suizidal">suizidal</label>
                                    <?php else : ?>
                                        <input type="radio" name="suizidal" disabled />
                                        <label for="suizidal">suizidal</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(98, $psych)): ?>
                                        <input type="radio" name="nicht_beurteilbar_psych" checked disabled />
                                        <label for="nicht_beurteilbar_psych">Nicht beurteilbar</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_beurteilbar_psych" disabled />
                                        <label for="nicht_beurteilbar_psych">Nicht beurteilbar</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <?php if (in_array(1, $psych)): ?>
                                        <input type="radio" name="unauffällig_psych" checked disabled />
                                        <label for="unauffällig_psych">unauffällig</label>
                                    <?php else : ?>
                                        <input type="radio" name="unauffällig_psych" disabled />
                                        <label for="unauffällig_psych">unauffällig</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(3, $psych)): ?>
                                        <input type="radio" name="depressiv" checked disabled />
                                        <label for="depressiv">depressiv</label>
                                    <?php else : ?>
                                        <input type="radio" name="depressiv" disabled />
                                        <label for="depressiv">depressiv</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(6, $psych)): ?>
                                        <input type="radio" name="verlangsamt" checked disabled />
                                        <label for="verlangsamt">verlangsamt</label>
                                    <?php else : ?>
                                        <input type="radio" name="verlangsamt" disabled />
                                        <label for="verlangsamt">verlangsamt</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(9, $psych)): ?>
                                        <input type="radio" name="ängstlich" checked disabled />
                                        <label for="ängstlich">ängstlich</label>
                                    <?php else : ?>
                                        <input type="radio" name="ängstlich" disabled />
                                        <label for="ängstlich">ängstlich</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(12, $psych)): ?>
                                        <input type="radio" name="sonstige_psych" checked disabled />
                                        <label for="sonstige_psych">Sonstige</label>
                                    <?php else : ?>
                                        <input type="radio" name="sonstige_psych" disabled />
                                        <label for="sonstige_psych">Sonstige</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(99, $psych)): ?>
                                        <input type="radio" name="nicht_untersucht_psych" checked disabled />
                                        <label for="nicht_untersucht_psych">nicht untersucht</label>
                                    <?php else : ?>
                                        <input type="radio" name="nicht_untersucht_psych" disabled />
                                        <label for="nicht_untersucht_psych">nicht untersucht</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <?php if (in_array(4, $psych)): ?>
                                        <input type="radio" name="wahnhaft" checked disabled />
                                        <label for="wahnhaft">wahnhaft</label>
                                    <?php else : ?>
                                        <input type="radio" name="wahnhaft" disabled />
                                        <label for="wahnhaft">wahnhaft</label>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array(7, $psych)): ?>
                                        <input type="radio" name="euphorisch" checked disabled />
                                        <label for="euphorisch">euphorisch</label>
                                    <?php else : ?>
                                        <input type="radio" name="euphorisch" disabled />
                                        <label for="euphorisch">euphorisch</label>
                                    <?php endif; ?>
                                </td>
                                <td colspan="2">
                                    <?php if (in_array(11, $psych)): ?>
                                        <input type="radio" name="motorisch_unruhig" checked disabled />
                                        <label for="motorisch_unruhig">motorisch unruhig</label>
                                    <?php else : ?>
                                        <input type="radio" name="motorisch_unruhig" disabled />
                                        <label for="motorisch_unruhig">motorisch unruhig</label>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function calculateAge(birthDateString) {
            const birthDate = new Date(birthDateString);
            const today = new Date();

            if (isNaN(birthDate)) return 0;

            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();

            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            return age >= 0 ? age : 0;
        }

        function updateAge() {
            const birthDateValue = document.getElementById('patgebdat').dataset.date;
            const age = calculateAge(birthDateValue);
            document.getElementById('_AGE_').value = age;
        }

        document.addEventListener('DOMContentLoaded', updateAge);
    </script>
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
                gcsInput.innerText = total;
            }

            selects.forEach(sel => {
                sel.addEventListener('change', updateGCS);
            });

            updateGCS();
        });
    </script>
</body>

</html>