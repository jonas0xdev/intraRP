<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;
use App\Helpers\Redirects;

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

if ($ist_freigegeben || ENOTF_PREREG === false) {
    header("Location: " . BASE_PATH . "enotf/protokoll/index.php?enr=" . $daten['enr']);
    exit();
}

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;
$defaultUrl = BASE_PATH . "enotf/protokoll/index.php?enr=" . $daten['enr'];

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new']) && $_POST['new'] == "1") {
    $requiredFields = [
        'priority',
        'arrival_date',
        'arrival_time',
        'fahrzeug',
        'diagnose',
        'kreislauf',
        'intubiert',
        'ziel'
    ];

    $allFilled = true;
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === 'NULL') {
            $allFilled = false;
            break;
        }
    }

    if ($allFilled) {
        $arrivalDateTime = $_POST['arrival_date'] . ' ' . $_POST['arrival_time'] . ':00';
        $stmt = $pdo->prepare("
            INSERT INTO intra_edivi_prereg (
                priority,
                arrival,
                fahrzeug,
                diagnose,
                geschlecht,
                `alter`,
                text,
                kreislauf,
                gcs,
                intubiert,
                ziel
            ) VALUES (
                :priority,
                :arrival,
                :fahrzeug,
                :diagnose,
                :geschlecht,
                :alter,
                :text,
                :kreislauf,
                :gcs,
                :intubiert,
                :ziel
            )
        ");

        $stmt->execute([
            'priority' => $_POST['priority'],
            'arrival' => $arrivalDateTime,
            'fahrzeug' => $_POST['fahrzeug'],
            'diagnose' => $_POST['diagnose'],
            'geschlecht' => $daten['patsex'] ?? NULL,
            'alter' => $_POST['_AGE_'] ?? NULL,
            'text' => $_POST['text'] ?? NULL,
            'kreislauf' => $_POST['kreislauf'],
            'gcs' => $_POST['_GCS_'] ?? NULL,
            'intubiert' => $_POST['intubiert'],
            'ziel' => $_POST['ziel']
        ]);

        Redirects::redirect($defaultUrl, []);
        exit();
    } else {
        $formError = "Bitte füllen Sie alle Pflichtfelder korrekt aus.";
    }
}

$diagnose_labels = [
    // ZNS (1-9)
    1 => 'Schlaganfall / TIA / ICB',
    2 => 'ICB (klin. Diagn.)',
    3 => 'SAB (klin. Diagn.)',
    4 => 'Krampfanfall',
    5 => 'Status Epilepticus',
    6 => 'Meningitis / Encephalitis',
    9 => 'sonstige Erkrankung ZNS',

    // Herz-Kreislauf (11-29)
    11 => 'ACS / NSTEMI',
    12 => 'ACS / STEMI',
    13 => 'Kardiogener Schock',
    14 => 'tachykarde Arrhythmie',
    15 => 'bradykarde Arrhythmie',
    16 => 'Schrittmacher-/ICD Fehlfunktion',
    17 => 'Lungenembolie',
    18 => 'Lungenödem',
    19 => 'hypertensiver Notfall',
    20 => 'Aortenaneurysma',
    21 => 'Hypotonie',
    22 => 'Synkope',
    23 => 'Thrombose / art. Verschluss',
    24 => 'Herz-Kreislauf-Stillstand',
    25 => 'Schock unklarer Genese',
    26 => 'unklarer Thoraxschmerz',
    27 => 'orthostatische Fehlregulation',
    28 => 'hypertensive Krise / Entgleisung',
    29 => 'sonstige Erkrankung Herz-Kreislauf',

    // Atemwege (31-49)
    31 => 'Asthma (Anfall)',
    32 => 'Status Asthmaticus',
    33 => 'exacerbierte COPD',
    34 => 'Aspiration',
    35 => 'Pneumonie / Bronchitis',
    36 => 'Hyperventilation',
    37 => 'Spontanpneumothorax',
    38 => 'Hämoptysis',
    39 => 'Dyspnoe unklarer Ursache',
    49 => 'sonstige Erkrankung Atmung',

    // Abdomen (51-59)
    51 => 'akutes Abdomen',
    52 => 'obere GI-Blutung',
    53 => 'untere GI-Blutung',
    54 => 'Gallenkolik',
    55 => 'Nierenkolik',
    56 => 'Kolik allgemein',
    59 => 'sonstige Erkrankung Abdomen',

    // Psychiatrie (61-69)
    61 => 'psychischer Ausnahmezustand',
    62 => 'Depression',
    63 => 'Manie',
    64 => 'Intoxikation',
    65 => 'Entzug, Delir',
    66 => 'Suizidalität',
    67 => 'psychosoziale Krise',
    69 => 'sonstige Erkrankung Psychiatrie',

    // Stoffwechsel (71-79)
    71 => 'Hypoglykämie',
    72 => 'Hyperglykämie',
    73 => 'Urämie / ANV',
    74 => 'Exsikkose',
    75 => 'bek. Dialyspflicht',
    79 => 'sonstige Erkrankung Stoffwechsel',

    // Sonstige (81-99)
    81 => 'Anaphylaxie Grad 1/2',
    82 => 'Anaphylaxie Grad 3/4',
    83 => 'Infekt / Sepsis / Schock',
    84 => 'Hitzeerschöpfung / Hitzschlag',
    85 => 'Unterkühlung / Erfrierung',
    86 => 'akute Lumbago',
    87 => 'Palliative Situation',
    88 => 'medizinische Behandlungskompl.',
    89 => 'Epistaxis / HNO-Erkrankung',
    91 => 'urologische Erkrankung',
    92 => 'unklare Erkrankung',
    93 => 'akzidentelle Intoxikation',
    94 => 'Augenerkrankung',
    99 => 'Keine Erkrankung / Verletzung feststellbar',

    // Trauma - Schädel-Hirn (101-104)
    101 => 'Trauma Schädel-Hirn leicht',
    102 => 'Trauma Schädel-Hirn mittel',
    103 => 'Trauma Schädel-Hirn schwer',
    104 => 'Trauma Schädel-Hirn tödlich',

    // Trauma - Gesicht (111-114)
    111 => 'Trauma Gesicht leicht',
    112 => 'Trauma Gesicht mittel',
    113 => 'Trauma Gesicht schwer',
    114 => 'Trauma Gesicht tödlich',

    // Trauma - HWS (121-124)
    121 => 'Trauma HWS leicht',
    122 => 'Trauma HWS mittel',
    123 => 'Trauma HWS schwer',
    124 => 'Trauma HWS tödlich',

    // Trauma - Thorax (131-134)
    131 => 'Trauma Thorax leicht',
    132 => 'Trauma Thorax mittel',
    133 => 'Trauma Thorax schwer',
    134 => 'Trauma Thorax tödlich',

    // Trauma - Abdomen (141-144)
    141 => 'Trauma Abdomen leicht',
    142 => 'Trauma Abdomen mittel',
    143 => 'Trauma Abdomen schwer',
    144 => 'Trauma Abdomen tödlich',

    // Trauma - BWS / LWS (151-153)
    151 => 'Trauma BWS / LWS leicht',
    152 => 'Trauma BWS / LWS mittel',
    153 => 'Trauma BWS / LWS schwer',

    // Trauma - Becken (161-163)
    161 => 'Trauma Becken leicht',
    162 => 'Trauma Becken mittel',
    163 => 'Trauma Becken schwer',

    // Trauma - obere Extremitäten (171-173)
    171 => 'Trauma obere Extremitäten leicht',
    172 => 'Trauma obere Extremitäten mittel',
    173 => 'Trauma obere Extremitäten schwer',

    // Trauma - untere Extremitäten (181-183)
    181 => 'Trauma untere Extremitäten leicht',
    182 => 'Trauma untere Extremitäten mittel',
    183 => 'Trauma untere Extremitäten schwer',

    // Trauma - Weichteile (191-193)
    191 => 'Trauma Weichteile leicht',
    192 => 'Trauma Weichteile mittel',
    193 => 'Trauma Weichteile schwer',

    // Trauma - spezielle (201-209)
    201 => 'Verbrennung / Verbrühung',
    202 => 'Inhalationstrauma',
    203 => 'Elektrounfall',
    204 => '(beinahe-) Ertrinken',
    205 => 'Tauchunfall',
    206 => 'Verätzung',
    209 => 'Sonstige',
];

$diagnose_haupt_text = '';
if (isset($daten['diagnose_haupt']) && !empty($daten['diagnose_haupt'])) {
    $diagnose_haupt_text = $diagnose_labels[$daten['diagnose_haupt']] ?? 'Unbekannte Diagnose';
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
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

<body data-bs-theme="dark" style="overflow-x:hidden" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <h2 class="text-center my-3">Klinik-Voranmeldung</h2>
                    <div class="row">
                        <div class="col">
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="ziel" class="edivi__description">Zielklinik</label>
                                            <select name="ziel" id="ziel" class="w-100 form-select" required>
                                                <option disabled hidden selected value="NULL">---</option>
                                                <?php
                                                require __DIR__ . '/../../assets/config/database.php';

                                                $stmt = $pdo->prepare("SELECT * FROM intra_edivi_ziele WHERE transport = 1 AND active = 1 ORDER BY priority ASC");
                                                $stmt->execute();
                                                $ziele = $stmt->fetchAll();
                                                foreach ($ziele as $row) {
                                                    echo '<option value="' . $row['identifier'] . '">' . $row['name'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <div class="col">
                                                <label for="fahrzeug" class="edivi__description">Fahrzeug Transport</label>
                                                <?php
                                                require __DIR__ . '/../../assets/config/database.php';

                                                $selectedFzg = $daten['fzg_transp'] ?? $daten['fzg_na'] ?? 'NULL';

                                                $stmt = $pdo->prepare("SELECT * FROM intra_fahrzeuge ORDER BY priority ASC");
                                                $stmt->execute();
                                                $fahrzeuge = $stmt->fetchAll();
                                                ?>

                                                <select name="fahrzeug" id="fahrzeug" class="w-100 form-select" required>
                                                    <option value="NULL" <?= $selectedFzg === 'NULL' ? 'selected' : '' ?>>Fzg. Transp.</option>
                                                    <?php foreach ($fahrzeuge as $row): ?>
                                                        <?php
                                                        $isSelected = $row['identifier'] === $selectedFzg;
                                                        $isActive = $row['active'] == 1;
                                                        ?>
                                                        <option value="<?= htmlspecialchars($row['name']) ?>"
                                                            <?= $isSelected ? 'selected' : '' ?>
                                                            <?= !$isActive ? 'disabled' : '' ?>>
                                                            <?= htmlspecialchars($row['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box p-2 mt-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="diagnose" class="edivi__description">Diagnose</label>
                                            <input type="text" name="diagnose" id="diagnose" class="w-100 form-control" maxlength="255" placeholder="..." value="<?= !empty($diagnose_haupt_text) ? $diagnose_haupt_text : '' ?>" readonly required>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="text" class="edivi__description">Anmeldetext</label>
                                            <textarea name="text" id="text" rows="3" class="w-100 form-control" style="resize: none" placeholder="..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <label for="geschlecht" class="edivi__description">Geschlecht</label>
                                            <select name="geschlecht" id="geschlecht" class="w-100 form-select" readonly autocomplete="off">
                                                <option disabled hidden selected>---</option>
                                                <option value="0" <?php echo ($daten['patsex'] == 0 ? 'selected' : '') ?>>männlich</option>
                                                <option value="1" <?php echo ($daten['patsex'] == 1 ? 'selected' : '') ?>>weiblich</option>
                                                <option value="2" <?php echo ($daten['patsex'] == 2 ? 'selected' : '') ?>>divers</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label for="_AGE_" class="edivi__description">Alter</label>
                                            <input type="text" name="_AGE_" id="_AGE_" class="w-100 form-control" value="0" readonly>
                                            <input type="hidden" name="patgebdat" id="patgebdat" value="<?= $daten['patgebdat'] ?>">
                                        </div>
                                        <div class="col">
                                            <label for="_GCS_" class="edivi__description">GCS</label>
                                            <input type="text" class="form-control" id="_GCS_" name="_GCS_" placeholder="3" value="" readonly>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="kreislauf" class="edivi__description">Kreislauf</label>
                                            <select name="kreislauf" id="kreislauf" class="w-100 form-select" required autocomplete="off">
                                                <option disabled hidden selected>---</option>
                                                <option value="1">stabil</option>
                                                <option value="0">instabil</option>
                                            </select>
                                        </div>
                                        <div class="col">
                                            <label for="intubiert" class="edivi__description">Intubiert</label>
                                            <select name="intubiert" id="intubiert" class="w-100 form-select" required autocomplete="off">
                                                <option disabled hidden selected>---</option>
                                                <option value="0">nein</option>
                                                <option value="1">ja</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row edivi__box p-2">
                                <div class="col">
                                    <div class="row">
                                        <div class="col">
                                            <?php
                                            $currentDate = date('Y-m-d');
                                            ?>
                                            <label for="arrival_date" class="edivi__description">Ankunftsdatum</label>
                                            <div class="input-group mb-2">
                                                <input type="date" name="arrival_date" id="arrival_date" class="form-control" value="<?= $currentDate ?>" required>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <label for="arrival_time" class="edivi__description">Ankunftszeit</label>
                                            <div class="input-group">
                                                <input type="time" name="arrival_time" id="arrival_time" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="priority" class="edivi__description">Priorität</label>
                                            <select name="priority" id="priority" class="w-100 form-select" required autocomplete="off">
                                                <option disabled hidden selected>---</option>
                                                <option value="0">Nicht dringlich</option>
                                                <option value="1">Dringlich</option>
                                                <option value="2">Sofort</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="edivi__freigabe-buttons">
                        <div class="row">
                            <div class="col">
                                <a href="<?= Redirects::getRedirectUrl($defaultUrl); ?>">zurück</a>
                            </div>
                            <div class="col">
                                <button type="submit">versenden</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display:none">
                <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_1" id="d_gcs_1" data-mapping="4,3,2,1" autocomplete="off">
                    <option disabled hidden selected>---</option>
                    <option value="0" <?php echo ($daten['d_gcs_1'] == 0 ? 'selected' : '') ?>>spontan (4)</option>
                    <option value="1" <?php echo ($daten['d_gcs_1'] == 1 ? 'selected' : '') ?>>auf Aufforderung (3)</option>
                    <option value="2" <?php echo ($daten['d_gcs_1'] == 2 ? 'selected' : '') ?>>auf Schmerzreiz (2)</option>
                    <option value="3" <?php echo ($daten['d_gcs_1'] == 3 ? 'selected' : '') ?>>kein Öffnen (1)</option>
                </select>
                <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_2" id="d_gcs_2" data-mapping="5,4,3,2,1" autocomplete="off">
                    <option disabled hidden selected>---</option>
                    <option value="0" <?php echo ($daten['d_gcs_2'] == 0 ? 'selected' : '') ?>>orientiert (5)</option>
                    <option value="1" <?php echo ($daten['d_gcs_2'] == 1 ? 'selected' : '') ?>>desorientiert (4)</option>
                    <option value="2" <?php echo ($daten['d_gcs_2'] == 2 ? 'selected' : '') ?>>inadäquate Äußerungen (3)</option>
                    <option value="3" <?php echo ($daten['d_gcs_2'] == 3 ? 'selected' : '') ?>>unverständliche Laute (2)</option>
                    <option value="4" <?php echo ($daten['d_gcs_2'] == 4 ? 'selected' : '') ?>>keine Reaktion (1)</option>
                </select>
                <select class="w-100 form-select gcs-select edivi__input-check" name="d_gcs_3" id="d_gcs_3" data-mapping="6,5,4,3,2,1" autocomplete="off">
                    <option disabled hidden selected>---</option>
                    <option value="0" <?php echo ($daten['d_gcs_3'] == 0 ? 'selected' : '') ?>>folgt Aufforderung (6)</option>
                    <option value="1" <?php echo ($daten['d_gcs_3'] == 1 ? 'selected' : '') ?>>gezielte Abwehrbewegungen (5)</option>
                    <option value="2" <?php echo ($daten['d_gcs_3'] == 2 ? 'selected' : '') ?>>ungezielte Abwehrbewegungen (4)</option>
                    <option value="3" <?php echo ($daten['d_gcs_3'] == 3 ? 'selected' : '') ?>>Beugesynergismen (3)</option>
                    <option value="4" <?php echo ($daten['d_gcs_3'] == 4 ? 'selected' : '') ?>>Strecksynergismen (2)</option>
                    <option value="5" <?php echo ($daten['d_gcs_3'] == 5 ? 'selected' : '') ?>>keine Reaktion (1)</option>
                </select>
            </div>
    </form>
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
            const birthDateValue = document.getElementById('patgebdat').value;
            const age = calculateAge(birthDateValue);
            document.getElementById('_AGE_').value = age;
        }

        document.addEventListener('DOMContentLoaded', updateAge);
        document.getElementById('patgebdat').addEventListener('input', updateAge);
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
                gcsInput.value = total;
            }

            selects.forEach(sel => {
                sel.addEventListener('change', updateGCS);
            });


            updateGCS();
        });
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>