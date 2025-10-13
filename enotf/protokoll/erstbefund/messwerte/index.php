<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

use App\Auth\Permissions;

$daten = array();
$message = '';
$messageType = '';

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

if ($ist_freigegeben) {
    header("Location: " . BASE_PATH . "enotf/protokoll/erstbefund/index.php?enr=" . $_GET['enr']);
    exit();
}

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/protokoll/index.php?enr=" . $enr;

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
$currentDateTime = date('Y-m-d\TH:i');
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
    <meta property="og:title" content="[#<?= $daten['enr'] ?>] Vitalparameter hinzufügen &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?>" />
    <meta property="og:image" content="https://<?php echo SYSTEM_URL ?>/assets/img/aelrd.png" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
</head>

<body data-page="verlauf" data-pin-enabled="<?= $pinEnabled ?>">
    <div class="container-fluid" id="edivi__container">
        <div class="row h-100">
            <div class="col" id="edivi__content">
                <?php if (!empty($message)): ?>
                    <div class="row mb-3">
                        <div class="col">
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($message) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form name="form" id="vitalsForm" method="post" action="">
                    <div class="row">
                        <div class="col position-relative">
                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="SpO₂" data-after="%">
                                    <input type="text" name="spo2" id="spo2"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="100" placeholder="96" value="<?= $daten['spo2'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="AF" data-after="/min">
                                    <input type="text" name="atemfreq" id="atemfreq"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="60" placeholder="16" value="<?= $daten['atemfreq'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="etCO₂" data-after="mmHg">
                                    <input type="text" name="etco2" id="etco2"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="100" placeholder="35" value="<?= $daten['etco2'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="HF" data-after="/min">
                                    <input type="text" name="herzfreq" id="herzfreq"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="300" placeholder="80" value="<?= $daten['herzfreq'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="NIBP/RR" data-after="mmHg">
                                    <input type="text" name="rrsys" id="rrsys"
                                        class="form-control edivi__vitalparam-shared keypad-input"
                                        min="0" max="300" placeholder="120" style="border-right:0!important" value="<?= $daten['rrsys'] ?>" data-ignore-autosave>
                                    <div class="edivi_vitalparam-spacer">/</div>
                                    <input type="text" name="rrdias" id="rrdias"
                                        class="form-control edivi__vitalparam-shared keypad-input"
                                        min="0" max="300" placeholder="80" style="border-left:0!important" value="<?= $daten['rrdias'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row my-3">
                                <div class="col edivi__vitalparam-box" data-before="BZ" data-after="mg/dl">
                                    <input type="text" name="bz" id="bz"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="0" max="1000" placeholder="90" value="<?= $daten['bz'] ?>" data-ignore-autosave>
                                </div>

                                <div class="col edivi__vitalparam-box" data-before="Temperatur" data-after="°C">
                                    <input type="text" name="temp" id="temp"
                                        class="form-control edivi__vitalparam keypad-input"
                                        min="10" max="45" step="0.1" placeholder="36,5" value="<?= $daten['temp'] ?>" data-ignore-autosave>
                                </div>
                            </div>

                            <div class="row edivi__vitalparam-mainbuttons">
                                <div class="col"><a href="<?= BASE_PATH ?>enotf/protokoll/erstbefund/index.php?enr=<?= $enr ?>">Abbrechen</a></div>
                                <div class="col" style="border-left:2px solid #191919;">
                                    <button type="button" id="saveVitalsBtn">Speichern</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-5">
                            <!-- Range Strip -->
                            <div class="range-strip-wrapper">
                                <div class="range-strip-container">
                                    <div class="range-strip" id="rangeStrip"></div>
                                </div>
                            </div>
                            <!-- Keypad -->
                            <div class="keypad-container">
                                <div class="keypad-grid">
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('7')">7</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('8')">8</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('9')">9</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('4')">4</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('5')">5</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('6')">6</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('1')">1</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('2')">2</button>
                                    <button type="button" class="keypad-btn" onclick="keypadAddDigit('3')">3</button>
                                    <button type="button" class="keypad-btn wide" onclick="keypadAddDigit('0')">0</button>
                                    <button type="button" class="keypad-btn special" onclick="keypadAddDigit(',')">,</button>
                                </div>
                                <div class="function-grid">
                                    <button type="button" class="keypad-btn danger" onclick="keypadClearField()">
                                        Löschen
                                    </button>
                                    <button type="button" class="keypad-btn danger" onclick="keypadBackspace()">⌫</button>
                                    <button type="button" class="keypad-btn special" onclick="keypadSetNG()">ng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
    include __DIR__ . '/../../../../assets/functions/enotf/field_checks.php';
    ?>
    <script>
        let keypadCurrentField = null;

        const fieldNames = {
            'spo2': 'SpO₂ (%)',
            'atemfreq': 'Atemfrequenz (/min)',
            'etco2': 'etCO₂ (mmHg)',
            'herzfreq': 'Herzfrequenz (/min)',
            'rrsys': 'RR systolisch (mmHg)',
            'rrdias': 'RR diastolisch (mmHg)',
            'bz': 'Blutzucker (mg/dl)',
            'temp': 'Temperatur (°C)'
        };

        const requiredFields = ['spo2', 'atemfreq', 'herzfreq', 'rrsys', 'bz'];

        function isFieldEmpty(field) {
            const value = (field.value || '').trim();
            return value === '';
        }

        function updateRequiredClass(field) {
            const fieldId = field.id || field.name;

            if (!requiredFields.includes(fieldId)) {
                return;
            }

            if (isFieldEmpty(field)) {
                field.classList.add('edivi__vitalparam-required');
            } else {
                field.classList.remove('edivi__vitalparam-required');
            }
        }

        function initializeRequiredFields() {
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    updateRequiredClass(field);

                    field.addEventListener('input', function() {
                        updateRequiredClass(this);
                    });

                    field.addEventListener('blur', function() {
                        updateRequiredClass(this);
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeRequiredFields();

            const keypadInputs = document.querySelectorAll('.keypad-input');
            keypadInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    selectField(this);
                });
                input.addEventListener('click', function() {
                    selectField(this);
                });
            });

            const vitalInputs = document.querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared');
            vitalInputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateField(this);
                });
            });

            document
                .querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared')
                .forEach(function(el) {
                    validateField(el);
                });
        });

        function validateField(field) {
            const raw = ((field && field.value) ? String(field.value) : '').trim();
            const lower = raw.toLowerCase();

            field.classList.remove('text-warning', 'text-danger', 'text-success', 'text-info', 'text-semiwarning');

            updateRequiredClass(field);

            if (raw === '') return;

            const value = parseFloat(raw.replace(',', '.'));
            if (Number.isNaN(value)) return;

            const name = field.name;
            let isWarning = false,
                isSemiWarning = false,
                isDanger = false,
                isSuccess = false;

            switch (name) {
                case 'spo2':
                    if (value < 87) isDanger = true;
                    else if (value < 92) isWarning = true;
                    else if (value < 97 && value >= 92) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'atemfreq':
                    if (value < 5 || value > 25) isDanger = true;
                    else if (value === 5 || value === 6 || value > 20 && value < 26) isWarning = true;
                    else if (value > 6 && value < 9 || value > 15 && value < 21) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'etco2':
                    if (value < 6 || value > 55) isDanger = true;
                    else if (value < 36 || value > 45) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'rrsys':
                    if (value < 80 || value > 199) isDanger = true;
                    else if (value >= 80 && value < 90 || value > 169) isWarning = true;
                    else if (value >= 90 && value < 101 || value > 149) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'rrdias':
                    if (value < 80 || value > 199) isDanger = true;
                    else if (value >= 80 && value < 90 || value > 169) isWarning = true;
                    else if (value >= 90 && value < 101 || value > 149) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'herzfreq':
                    if (value < 41 || value > 160) isDanger = true;
                    else if (value < 51 || value > 130) isWarning = true;
                    else if (value < 61 || value > 100) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'bz':
                    if (value < 40 || value > 250) isDanger = true;
                    else if (value < 51 || value > 180) isWarning = true;
                    else if (value < 81 || value > 150) isSemiWarning = true;
                    else isSuccess = true;
                    break;
                case 'temp':
                    if (value <= 34 || value > 40) isDanger = true;
                    else if (value < 36.1 || value > 38) isSemiWarning = true;
                    else isSuccess = true;
                    break;
            }

            if (isDanger) field.classList.add('text-danger');
            else if (isSemiWarning) field.classList.add('text-semiwarning');
            else if (isWarning) field.classList.add('text-warning');
            else if (isSuccess) field.classList.add('text-success');
        }

        function selectField(field) {
            document.querySelectorAll('.keypad-input').forEach(input => {
                input.classList.remove('active-field');
                if (input.dataset.originalType) {
                    input.type = input.dataset.originalType;
                    delete input.dataset.originalType;
                }
            });

            field.classList.add('active-field');
            keypadCurrentField = field;

            if (field.type === 'number') {
                field.dataset.originalType = 'number';
                field.type = 'text';
            }

            setTimeout(() => {
                const length = field.value.length;
                field.setSelectionRange(length, length);
                field.focus();
            }, 0);

            updateFieldInfo();
        }

        function updateFieldInfo() {
            const info = document.getElementById('fieldInfo');
            if (keypadCurrentField && info) {
                const fieldName = fieldNames[keypadCurrentField.id] || keypadCurrentField.id;
                info.textContent = `Aktives Feld: ${fieldName}`;
            } else if (info) {
                info.textContent = 'Feld auswählen für Eingabe';
            }
        }

        function keypadSetNG() {
            if (!keypadCurrentField) {
                alert('Bitte wählen Sie zuerst ein Eingabefeld aus.');
                return;
            }
            keypadUpdateFieldValue('ng');
        }

        function keypadAddDigit(digit) {
            if (!keypadCurrentField) {
                alert('Bitte wählen Sie zuerst ein Eingabefeld aus.');
                return;
            }

            let currentValue = keypadCurrentField.value || '';

            if (digit === ',') {
                if (currentValue.includes(',')) {
                    return;
                }
                if (currentValue === '') {
                    currentValue = '0,';
                } else {
                    currentValue += ',';
                }
            } else {
                if (currentValue.includes(',')) {
                    const parts = currentValue.split(',');
                    const nachKomma = parts[1] || '';

                    if (nachKomma.length >= 2) {
                        return;
                    }

                    currentValue = parts[0] + ',' + nachKomma + digit;
                } else {
                    if (currentValue.length >= 3) {
                        return;
                    }

                    currentValue += digit;
                }
            }

            keypadUpdateFieldValue(currentValue);
        }

        function keypadBackspace() {
            if (!keypadCurrentField) {
                return;
            }

            let currentValue = keypadCurrentField.value || '';
            if (currentValue.length > 0) {
                currentValue = currentValue.slice(0, -1);
                keypadUpdateFieldValue(currentValue);
            }
        }

        function keypadClearField() {
            if (keypadCurrentField) {
                keypadUpdateFieldValue('');
            }
        }

        function keypadUpdateFieldValue(value) {
            if (!keypadCurrentField) return;

            const field = keypadCurrentField;
            const fieldId = field.id;

            if (String(value).toLowerCase() === 'ng') {
                field.value = 'ng';
                field.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');
                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
                return;
            }

            if (value === '') {
                field.value = '';
                field.classList.remove('text-danger', 'text-warning', 'text-success', 'text-semiwarning');

                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
                return;
            }

            const numericValue = parseFloat(value.replace(',', '.'));
            if (!isNaN(numericValue)) {
                let isValid = true;
                switch (fieldId) {
                    case 'spo2':
                        isValid = numericValue >= 0 && numericValue <= 100;
                        break;
                    case 'atemfreq':
                        isValid = numericValue >= 0 && numericValue <= 40;
                        break;
                    case 'etco2':
                        isValid = numericValue >= 0 && numericValue <= 100;
                        break;
                    case 'herzfreq':
                        isValid = numericValue >= 0 && numericValue <= 300;
                        break;
                    case 'rrsys':
                    case 'rrdias':
                        isValid = numericValue >= 0 && numericValue <= 300;
                        break;
                    case 'bz':
                        isValid = numericValue >= 0 && numericValue <= 700;
                        break;
                    case 'temp':
                        isValid = numericValue >= 10 && numericValue <= 45;
                        break;
                }

                field.value = value;

                field.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                validateField(field);
            }
        }

        function checkAllRequiredFields() {
            let allFilled = true;
            const emptyFields = [];

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && isFieldEmpty(field)) {
                    allFilled = false;
                    emptyFields.push(fieldNames[fieldId] || fieldId);
                }
            });

            return {
                allFilled: allFilled,
                emptyFields: emptyFields
            };
        }

        function validateBeforeSave() {
            const result = checkAllRequiredFields();

            // if (!result.allFilled) {
            //     const message = `Folgende Pflichtfelder sind noch nicht ausgefüllt:\n${result.emptyFields.join('\n')}`;

            //     if (!confirm(message + '\n\nTrotzdem speichern?')) {
            //         return false;
            //     }
            // }

            return true;
        }
    </script>
    <script>
        (function() {
            const enr = <?= json_encode($enr) ?>;
            const fields = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
            const endpoint = '<?= BASE_PATH ?>assets/functions/save_fields.php';

            async function postField(field, value) {
                const normalized = String(value).replace(',', '.').trim();
                if (normalized === '') return;

                const body = new URLSearchParams();
                body.set('enr', enr);
                body.set('field', field);
                body.set('value', normalized);

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body.toString()
                });
                const text = await res.text();
                if (!res.ok) throw new Error(text || ('Fehler bei ' + field));
                return text;
            }

            async function saveAll() {
                const fields = ['spo2', 'atemfreq', 'etco2', 'herzfreq', 'rrsys', 'rrdias', 'bz', 'temp'];
                const endpoint = '<?= BASE_PATH ?>assets/functions/save_fields.php';
                const enr = <?= json_encode($enr) ?>;

                const jobs = [];
                for (const f of fields) {
                    const el = document.getElementById(f);
                    if (!el) continue;

                    const raw = (el.value ?? '').toString().trim();
                    let toSend;
                    if (raw.toLowerCase() === 'ng') {
                        toSend = raw;
                    } else if (raw === '') {
                        toSend = raw;
                    } else {
                        toSend = raw.replace(',', '.');
                    }

                    const body = new URLSearchParams();
                    body.set('enr', enr);
                    body.set('field', f);
                    body.set('value', toSend);

                    jobs.push(fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    }).then(async r => {
                        const t = await r.text();
                        if (!r.ok) throw new Error(t || ('Fehler bei ' + f));
                        return t;
                    }));
                }

                await Promise.all(jobs);
                (window.showToast ? window.showToast('Vitalparameter gespeichert.', 'success') : alert('Vitalparameter gespeichert.'));
            }

            document.getElementById('saveVitalsBtn')?.addEventListener('click', function() {
                if (!validateBeforeSave()) {
                    return;
                }

                saveAll().catch(err => {
                    console.error(err);
                    (window.showToast ? window.showToast('Speichern fehlgeschlagen: ' + err.message, 'error') : alert('Speichern fehlgeschlagen: ' + err.message));
                });
            });
        })();
    </script>
    <script>
        (function enforceNumericOrNG() {
            const inputs = document.querySelectorAll('.edivi__vitalparam, .edivi__vitalparam-shared');

            function sanitizeNumeric(v) {
                v = (v || '').replace(/[^\d,\.]/g, '');

                const sepIndex = v.search(/[,\.]/);
                if (sepIndex >= 0) {
                    const before = v.slice(0, sepIndex).replace(/[,\.]/g, '');
                    const after = v.slice(sepIndex + 1).replace(/[,\.]/g, '');
                    return before.slice(0, 3) + v[sepIndex] + after.slice(0, 2);
                }
                return v.slice(0, 3);
            }

            inputs.forEach(el => {
                el.addEventListener('input', function() {
                    const raw = String(this.value || '').trim();
                    const lower = raw.toLowerCase();

                    if (raw === '') {
                        return;
                    }
                    if (lower === 'ng') {
                        return;
                    }

                    const sanitized = sanitizeNumeric(raw);
                    if (sanitized !== raw) {
                        const pos = this.selectionStart;
                        this.value = sanitized;
                        try {
                            this.setSelectionRange(pos - (raw.length - sanitized.length),
                                pos - (raw.length - sanitized.length));
                        } catch {}
                    }
                });

                el.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey || e.altKey) return;
                    const k = e.key;

                    if (/^[a-zA-Z]$/.test(k)) {
                        const next = (String(this.value || '') + k).trim().toLowerCase();
                        if (next !== 'n' && next !== 'ng') {
                            e.preventDefault();
                        }
                        return;
                    }

                    if (!/^[0-9]$/.test(k) && k !== ',' && k !== '.' && k.length === 1) {
                        e.preventDefault();
                    }
                });
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';

            const rangeConfigs = {
                'temp': {
                    label: 'Temperatur (°C)',
                    values: [32, 34, 36, 38, 40, 42],
                    getClass: (val) => {
                        if (val <= 34 || val > 40) return 'danger';
                        if (val < 36.1 || val > 38) return 'semiwarning';
                        return 'success';
                    }
                },
                'bz': {
                    label: 'Blutzucker (mg/dl)',
                    values: [20, 40, 60, 80, 100, 120, 140, 160, 180, 200, 220, 240, 260, 280, 300, 320, 340],
                    getClass: (val) => {
                        if (val < 40 || val > 250) return 'danger';
                        if (val < 51 || val > 180) return 'warning';
                        if (val < 81 || val > 150) return 'semiwarning';
                        return 'success';
                    }
                },
                'rrsys': {
                    label: 'RR systolisch (mmHg)',
                    values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250],
                    getClass: (val) => {
                        if (val < 80 || val > 199) return 'danger';
                        if ((val >= 80 && val < 90) || val > 169) return 'warning';
                        if ((val >= 90 && val < 101) || val > 149) return 'semiwarning';
                        return 'success';
                    }
                },
                'rrdias': {
                    label: 'RR diastolisch (mmHg)',
                    values: [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250],
                    getClass: (val) => {
                        if (val < 80 || val > 199) return 'danger';
                        if ((val >= 80 && val < 90) || val > 169) return 'warning';
                        if ((val >= 90 && val < 101) || val > 149) return 'semiwarning';
                        return 'success';
                    }
                },
                'herzfreq': {
                    label: 'Herzfrequenz (/min)',
                    values: [40, 60, 80, 100, 120, 140, 160, 180, 200],
                    getClass: (val) => {
                        if (val < 41 || val > 160) return 'danger';
                        if (val < 51 || val > 130) return 'warning';
                        if (val < 61 || val > 100) return 'semiwarning';
                        return 'success';
                    }
                },
                'etco2': {
                    label: 'etCO₂ (mmHg)',
                    values: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
                    getClass: (val) => {
                        if (val < 6 || val > 55) return 'danger';
                        if (val < 36 || val > 45) return 'semiwarning';
                        return 'success';
                    }
                },
                'spo2': {
                    label: 'SpO₂ (%)',
                    values: [72, 74, 76, 78, 80, 82, 84, 86, 88, 90, 92, 94, 96, 98],
                    getClass: (val) => {
                        if (val < 87) return 'danger';
                        if (val < 92) return 'warning';
                        if (val < 97) return 'semiwarning';
                        return 'success';
                    }
                },
                'atemfreq': {
                    label: 'Atemfrequenz (/min)',
                    values: [5, 10, 15, 20, 25, 30],
                    getClass: (val) => {
                        if (val < 5 || val > 25) return 'danger';
                        if (val === 5 || val === 6 || (val > 20 && val < 26)) return 'warning';
                        if ((val > 6 && val < 9) || (val > 15 && val < 21)) return 'semiwarning';
                        return 'success';
                    }
                }
            };

            function renderRangeStrip(fieldId) {
                const stripElement = document.getElementById('rangeStrip');

                if (!stripElement) return;

                if (!fieldId || !rangeConfigs[fieldId]) {
                    stripElement.innerHTML = '';
                    return;
                }

                const config = rangeConfigs[fieldId];

                let html = '';
                if (fieldId === 'spo2') {
                    html += '<div class="range-item success"><span class="range-value"></span></div>';
                } else {
                    html += '<div class="range-item danger"><span class="range-value"></span></div>';
                }

                for (let i = config.values.length - 1; i >= 0; i--) {
                    const val = config.values[i];
                    const className = config.getClass(val);
                    html += `<div class="range-item ${className}"><span class="range-value">${val}</span></div>`;
                }

                html += '<div class="range-item danger"><span class="range-value"></span></div>';

                stripElement.innerHTML = html;
            }

            const originalSelectField = window.selectField;
            window.selectField = function(field) {
                if (originalSelectField) {
                    originalSelectField(field);
                }

                renderRangeStrip(field.id);
            };

            document.addEventListener('DOMContentLoaded', function() {
                const keypadInputs = document.querySelectorAll('.keypad-input');

                keypadInputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        renderRangeStrip(this.id);
                    });
                });

                renderRangeStrip(null);
            });

            window.updateRangeStrip = renderRangeStrip;
        })();
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>