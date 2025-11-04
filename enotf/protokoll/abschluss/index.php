<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

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

$ebesonderheiten = [];
if (!empty($daten['ebesonderheiten'])) {
    $decoded = json_decode($daten['ebesonderheiten'], true);
    if (is_array($decoded)) {
        $ebesonderheiten = array_map('intval', $decoded);
    }
}

$ebesonderheitenLabels = [
    1 => 'keine',
    2 => 'nächste geeignete Klinik nicht aufnahmebereit',
    3 => 'Patient lehnt indizierte Maßnahmen ab',
    4 => 'bewusster Therapieverzicht',
    5 => 'Patient lehnt Transport ab',
    6 => 'Vorsorgliche Bereitstellung',
    7 => 'Zwangsunterbringung',
    8 => 'aufwändige technische Rettung',
    9 => 'Einsatz mit LNA/OrgL',
    10 => 'mehrere Patienten',
    11 => 'MANV',
    12 => 'kein Notarzt in angemessener Zeit verfügbar',
    13 => 'erschwerter Patientenzugang',
    14 => 'verzögerte Patientenübergabe',
    99 => 'Sonstige'
];

$ebesonderheitenDisplayTexts = [];
if (!empty($ebesonderheiten) && is_array($ebesonderheiten)) {
    foreach ($ebesonderheiten as $value) {
        if (isset($ebesonderheitenLabels[$value])) {
            $ebesonderheitenDisplayTexts[] = $ebesonderheitenLabels[$value];
        }
    }
}

$ebesonderheitenDisplay = !empty($ebesonderheitenDisplayTexts) ? implode(', ', $ebesonderheitenDisplayTexts) : '';

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $SITE_TITLE = "[#" . $daten['enr'] . "] &rsaquo; eNOTF";
    include __DIR__ . '/../../../assets/components/enotf/_head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="abschluss" data-pin-enabled="<?= $pinEnabled ?>">
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
                            <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" data-requires="ebesonderheiten">
                                <span>Einsatzverlauf Besonderheiten</span>
                            </a>
                            <?php if ($daten['prot_by'] != 1) : ?>
                                <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/2.php?enr=<?= $daten['enr'] ?>" data-requires="na_nachf">
                                    <span>Nachforderung NA</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col edivi__overview-container">
                            <div class="row">
                                <div class="col">
                                    <div class="row edivi__box">
                                        <h5 class="text-light px-2 py-1">Transportdaten</h5>
                                        <div class="col">
                                            <div class="row mt-2">
                                                <div class="col-3">
                                                    <label for="fzg_transp" class="edivi__description">Fahrzeug Transport</label>
                                                    <?php if ($daten['fzg_transp'] === NULL) : ?>
                                                        <select name="fzg_transp" id="fzg_transp" class="w-100 form-select">
                                                            <option selected value="NULL">Fzg. Transp.</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 2 AND active = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();
                                                            foreach ($fahrzeuge as $row) {
                                                                echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <select name="fzg_transp" id="fzg_transp" class="w-100 form-select">
                                                            <option selected value="NULL">Fzg. Transp.</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 2 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();

                                                            foreach ($fahrzeuge as $row) {
                                                                if ($row['identifier'] == $daten['fzg_transp'] && $row['active'] == 1) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected>' . $row['name'] . '</option>';
                                                                } elseif ($row['identifier'] == $daten['fzg_transp'] && $row['active'] == 0) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected disabled>' . $row['name'] . '</option>';
                                                                } else {
                                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <label for="fzg_transp_perso" class="edivi__description">Besatzung Transpormittel</label>
                                                    <input type="text" name="fzg_transp_perso" id="fzg_transp_perso" class="w-100 form-control" placeholder="Transportführer RTW/KTW" value="<?= $daten['fzg_transp_perso'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-3">
                                                </div>
                                                <div class="col">
                                                    <input type="text" name="fzg_transp_perso_2" id="fzg_transp_perso_2" class="w-100 form-control" placeholder="Fahrzeugführer RTW/KTW" value="<?= $daten['fzg_transp_perso_2'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-3">
                                                    <label for="fzg_na" class="edivi__description">Fahrzeug Notarzt</label>
                                                    <?php if ($daten['fzg_na'] === NULL) : ?>
                                                        <select name="fzg_na" id="fzg_na" class="w-100 form-select">
                                                            <option selected value="NULL">Fzg. NA</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 1 AND active = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();
                                                            foreach ($fahrzeuge as $row) {
                                                                echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php else : ?>
                                                        <select name="fzg_na" id="fzg_na" class="w-100 form-select">
                                                            <option selected value="NULL">Fzg. NA</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge WHERE rd_type = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();

                                                            foreach ($fahrzeuge as $row) {
                                                                if ($row['identifier'] == $daten['fzg_na'] && $row['active'] == 1) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected>' . $row['name'] . '</option>';
                                                                } elseif ($row['identifier'] == $daten['fzg_na'] && $row['active'] == 0) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected disabled>' . $row['name'] . '</option>';
                                                                } else {
                                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col">
                                                    <label for="fzg_na_perso" class="edivi__description">Besatzung Notarztzubringer</label>
                                                    <input type="text" name="fzg_na_perso" id="fzg_na_perso" class="w-100 form-control" placeholder="Notarzt" value="<?= $daten['fzg_na_perso'] ?>">
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-3">
                                                </div>
                                                <div class="col">
                                                    <input type="text" name="fzg_na_perso_2" id="fzg_na_perso_2" class="w-100 form-control" placeholder="Fahrzeugführer NEF/HEMS-TC" value="<?= $daten['fzg_na_perso_2'] ?>">
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="fzg_sonst" class="edivi__description">Sonstige Fahrzeuge</label>
                                                    <input type="text" name="fzg_sonst" id="fzg_sonst" class="w-100 form-control" placeholder="Weitere Rettungsmittel" value="<?= $daten['fzg_sonst'] ?>">
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="transportziel" class="edivi__description">Transportart oder Transportziel</label>
                                                    <?php
                                                    if ($daten['transportziel'] === NULL) {
                                                    ?>
                                                        <select name="transportziel" id="transportziel" class="w-100 form-select edivi__input-check" required>
                                                            <option disabled hidden selected value="NULL">---</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_ziele WHERE active = 1 ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $ziele = $stmt->fetchAll();
                                                            foreach ($ziele as $row) {
                                                                echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <select name="transportziel" id="transportziel" class="w-100 mb-2 form-select edivi__input-check" autocomplete="off">
                                                            <option disabled hidden selected value="NULL">---</option>
                                                            <?php
                                                            require __DIR__ . '/../../../assets/config/database.php';
require_once __DIR__ . '/../../../assets/functions/enotf/user_auth_middleware.php';
                                                            require_once __DIR__ . '/../../../assets/functions/enotf/pin_middleware.php';

                                                            $stmt = $pdo->prepare("SELECT * FROM intra_edivi_ziele ORDER BY priority ASC");
                                                            $stmt->execute();
                                                            $fahrzeuge = $stmt->fetchAll();

                                                            foreach ($fahrzeuge as $row) {
                                                                if ($row['identifier'] == $daten['transportziel'] && $row['active'] == 1) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected>' . $row['name'] . '</option>';
                                                                } elseif ($row['identifier'] == $daten['transportziel'] && $row['active'] == 0) {
                                                                    echo '<option value="' . $row['identifier'] . '" selected disabled>' . $row['name'] . '</option>';
                                                                } else {
                                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="row edivi__box">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Protokolldaten</h5>
                                        <div class="col">
                                            <?php
                                            $stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
                                            $fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);
                                            ?>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="pfname" class="edivi__description">Protokollant</label>
                                                    <input type="text" name="pfname" id="pfname" class="w-100 form-control edivi__input-check" placeholder="Max Mustermann" value="<?= htmlspecialchars($daten['pfname'] ?? '') ?>" list="nameSuggestions" required>
                                                    <datalist id="nameSuggestions">
                                                        <?php foreach ($fullnames as $name): ?>
                                                            <option value="<?= htmlspecialchars($name) ?>"></option>
                                                        <?php endforeach; ?>
                                                    </datalist>
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="prot_by" class="edivi__description">Protokoll durch</label>
                                                    <select name="prot_by" id="prot_by" class="w-100 form-select edivi__input-check" readonly required autocomplete="off">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="0" <?php echo ($daten['prot_by'] == 0 ? 'selected' : '') ?>>Transportmittel</option>
                                                        <option value="1" <?php echo ($daten['prot_by'] == 1 ? 'selected' : '') ?>>Notarzt</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row edivi__box">
                                        <h5 class="text-light px-2 py-1">Übergabe</h5>
                                        <div class="col">
                                            <?php
                                            $stmtfn = $pdo->query("SELECT fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
                                            $fullnames = $stmtfn->fetchAll(PDO::FETCH_COLUMN);
                                            ?>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="uebergabe_ort" class="edivi__description">Übergabe-Ort</label>
                                                    <select name="uebergabe_ort" id="uebergabe_ort" class="w-100 form-select" autocomplete="off">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="1" <?php echo ($daten['uebergabe_ort'] == 1 ? 'selected' : '') ?>>Schockraum</option>
                                                        <option value="2" <?php echo ($daten['uebergabe_ort'] == 2 ? 'selected' : '') ?>>Praxis</option>
                                                        <option value="3" <?php echo ($daten['uebergabe_ort'] == 3 ? 'selected' : '') ?>>ZNA / INA</option>
                                                        <option value="4" <?php echo ($daten['uebergabe_ort'] == 4 ? 'selected' : '') ?>>Stroke Unit</option>
                                                        <option value="5" <?php echo ($daten['uebergabe_ort'] == 5 ? 'selected' : '') ?>>Intensivstation</option>
                                                        <option value="6" <?php echo ($daten['uebergabe_ort'] == 6 ? 'selected' : '') ?>>OP direkt</option>
                                                        <option value="7" <?php echo ($daten['uebergabe_ort'] == 7 ? 'selected' : '') ?>>Hausarzt</option>
                                                        <option value="8" <?php echo ($daten['uebergabe_ort'] == 8 ? 'selected' : '') ?>>Fachambulanz</option>
                                                        <option value="9" <?php echo ($daten['uebergabe_ort'] == 9 ? 'selected' : '') ?>>Chest Pain Unit</option>
                                                        <option value="10" <?php echo ($daten['uebergabe_ort'] == 10 ? 'selected' : '') ?>>HKL</option>
                                                        <option value="11" <?php echo ($daten['uebergabe_ort'] == 11 ? 'selected' : '') ?>>Allgemeinstation</option>
                                                        <option value="12" <?php echo ($daten['uebergabe_ort'] == 12 ? 'selected' : '') ?>>Einsatzstelle</option>
                                                        <option value="99" <?php echo ($daten['uebergabe_ort'] == 99 ? 'selected' : '') ?>>Sonstige</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="uebergabe_an" class="edivi__description">Übergabe an</label>
                                                    <select name="uebergabe_an" id="uebergabe_an" class="w-100 form-select" autocomplete="off">
                                                        <option disabled hidden selected>---</option>
                                                        <option value="1" <?php echo ($daten['uebergabe_an'] == 1 ? 'selected' : '') ?>>Arzt</option>
                                                        <option value="2" <?php echo ($daten['uebergabe_an'] == 2 ? 'selected' : '') ?>>Pflegepersonal</option>
                                                        <option value="3" <?php echo ($daten['uebergabe_an'] == 3 ? 'selected' : '') ?>>Rettungssanitäter</option>
                                                        <option value="4" <?php echo ($daten['uebergabe_an'] == 4 ? 'selected' : '') ?>>Rettungsassistent</option>
                                                        <option value="5" <?php echo ($daten['uebergabe_an'] == 5 ? 'selected' : '') ?>>Notfallsanitäter</option>
                                                        <option value="6" <?php echo ($daten['uebergabe_an'] == 6 ? 'selected' : '') ?>>Polizei</option>
                                                        <option value="7" <?php echo ($daten['uebergabe_an'] == 7 ? 'selected' : '') ?>>Angehörige</option>
                                                        <option value="99" <?php echo ($daten['uebergabe_an'] == 99 ? 'selected' : '') ?>>Sonstige</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row edivi__box edivi__box-clickable" data-href="<?= BASE_PATH ?>enotf/protokoll/abschluss/1.php?enr=<?= $daten['enr'] ?>" style="cursor:pointer">
                                        <h5 class="text-light px-2 py-1 edivi__group-check">Einsatzverlauf</h5>
                                        <div class="col">
                                            <div class="row my-2">
                                                <div class="col">
                                                    <label for="einsatzverlauf_besonderheiten" class="edivi__description">Besonderheiten</label>
                                                    <input type="text" name="einsatzverlauf_besonderheiten" id="einsatzverlauf_besonderheiten" class="w-100 form-control edivi__input-check" value="<?= !empty($ebesonderheitenDisplay) ? htmlspecialchars($ebesonderheitenDisplay) : '' ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!$ist_freigegeben) : ?>
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/freigabe.php?enr=<?= $daten['enr'] ?>" id="abschluss__btn">Abschließen</a>
                    <?php endif; ?>
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
    <script>
        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        modalCloseButton.addEventListener('click', function() {
            freigeberInput.value = '';
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>