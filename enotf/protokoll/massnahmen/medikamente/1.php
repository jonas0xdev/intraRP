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

$daten['last_edit'] = !empty($daten['last_edit']) ? (new DateTime($daten['last_edit']))->format('d.m.Y H:i') : NULL;

$enr = $daten['enr'];

$prot_url = "https://" . SYSTEM_URL . "/enotf/prot/index.php?enr=" . $enr;
$defaultUrl = BASE_PATH . "enotf/protokoll/massnahmen/medikamente/index.php?enr=" . $daten['enr'];

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i:s');
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

    <style>
        .medikament-item {
            transition: all 0.2s ease;
        }

        #medis-list {
            overflow-y: auto;
            max-height: 60vh;
        }

        #medis-list::-webkit-scrollbar {
            width: 8px;
        }

        #medis-list::-webkit-scrollbar-track {
            background: #222;
        }

        #medis-list::-webkit-scrollbar-thumb {
            background: #666;
            border-radius: 4px;
        }

        #medis-list::-webkit-scrollbar-thumb:hover {
            background: #888;
        }

        #medis-select,
        #medis-time,
        #medis-unit,
        #medis-admission,
        #medis-concentration {
            font-size: 1.75rem !important;
        }

        #medis-list {
            font-size: 1.5rem;
        }
    </style>
</head>

<body>
    <form name="form" method="post" action="">
        <input type="hidden" name="new" value="1" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content edivi__medikamente">
                    <div class="row mt-4 mx-4">
                        <div class="col">
                            <div class="w-100 p-3" style="background-color: #333333; min-height: 60vh; border-radius: 8px;" id="medis-list">
                                <div class="text-center text-muted p-4">
                                    <i class="las la-spinner la-spin" style="font-size: 2em;"></i>
                                    <div class="mt-2">Medikamente werden geladen...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="row">
                                <div class="col">
                                    <select class="form-select medikament-field-ignore" name="medis-select" id="medis-select" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true">
                                        <option value="" disabled hidden selected>Wirkstoff</option>
                                        <option value="Acetylsalicylsäure">Acetylsalicylsäure</option>
                                        <option value="Adenosin">Adenosin</option>
                                        <option value="Alteplase">Alteplase</option>
                                        <option value="Amiodaron">Amiodaron</option>
                                        <option value="Atropinsulfat">Atropinsulfat</option>
                                        <option value="Butylscopolamin">Butylscopolamin</option>
                                        <option value="Calciumgluconat">Calciumgluconat</option>
                                        <option value="Ceftriaxon">Ceftriaxon</option>
                                        <option value="Dimenhydrinat">Dimenhydrinat</option>
                                        <option value="Dimetinden">Dimetinden</option>
                                        <option value="Epinephrin">Epinephrin</option>
                                        <option value="Esketamin">Esketamin</option>
                                        <option value="Fentanyl">Fentanyl</option>
                                        <option value="Flumazenil">Flumazenil</option>
                                        <option value="Furosemid">Furosemid</option>
                                        <option value="Gelatinepolysuccinat">Gelatinepolysuccinat</option>
                                        <option value="Glukose">Glukose</option>
                                        <option value="Heparin">Heparin</option>
                                        <option value="Humanblut">Humanblut</option>
                                        <option value="Hydroxycobolamin">Hydroxycobolamin</option>
                                        <option value="Ipratropiumbromid">Ipratropiumbromid</option>
                                        <option value="Lidocain">Lidocain</option>
                                        <option value="Lorazepam">Lorazepam</option>
                                        <option value="Metamizol">Metamizol</option>
                                        <option value="Metoprolol">Metoprolol</option>
                                        <option value="Midazolam">Midazolam</option>
                                        <option value="Morphin">Morphin</option>
                                        <option value="Naloxon">Naloxon</option>
                                        <option value="Natriumhydrogencarbonat">Natriumhydrogencarbonat</option>
                                        <option value="Natriumthiosulfat">Natriumthiosulfat</option>
                                        <option value="Nitroglycerin">Nitroglycerin</option>
                                        <option value="Norepinephrin">Norepinephrin</option>
                                        <option value="Ondansetron">Ondansetron</option>
                                        <option value="Paracetamol">Paracetamol</option>
                                        <option value="Piritramid">Piritramid</option>
                                        <option value="Prednisolon">Prednisolon</option>
                                        <option value="Propofol">Propofol</option>
                                        <option value="Reproterol">Reproterol</option>
                                        <option value="Rocuronium">Rocuronium</option>
                                        <option value="Salbutamol">Salbutamol</option>
                                        <option value="Succinylcholin">Succinylcholin</option>
                                        <option value="Sufentanil">Sufentanil</option>
                                        <option value="Theodrenalin-Cafedrin">Theodrenalin-Cafedrin</option>
                                        <option value="Thiopental">Thiopental</option>
                                        <option value="Tranexamsäure">Tranexamsäure</option>
                                        <option value="Urapidil">Urapidil</option>
                                        <option value="Vollelektrolytlösung">Vollelektrolytlösung</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <input type="time" name="medis-time" id="medis-time" class="form-control medikament-field-ignore" style="background-color: #333333; color: white;" step="1" value="<?= $currentTime ?>" data-ignore-autosave="true">
                                </div>
                                <div class="col">
                                    <div class="col">
                                        <select class="form-select medikament-field-ignore" name="medis-admission" id="medis-admission" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true">
                                            <option value="" disabled hidden selected>Applikationsart</option>
                                            <option value="i.v.">i.v.</option>
                                            <option value="i.o.">i.o.</option>
                                            <option value="i.m.">i.m.</option>
                                            <option value="s.c.">s.c.</option>
                                            <option value="s.l.">s.l.</option>
                                            <option value="p.o.">p.o.</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <input class="form-control medikament-field-ignore" type="text" placeholder="Dosierung" name="medis-concentration" id="medis-concentration" style="background-color: #333333; color: white; --bs-secondary-color: #a2a2a2" data-ignore-autosave="true">
                                </div>
                                <div class="col">
                                    <select class="form-select medikament-field-ignore" name="medis-unit" id="medis-unit" required autocomplete="off" style="background-color: #333333; color: white;" data-ignore-autosave="true">
                                        <option value="" disabled hidden selected>Einheit</option>
                                        <option value="mcg">&micro;g</option>
                                        <option value="mg">mg</option>
                                        <option value="g">g</option>
                                        <option value="ml">ml</option>
                                        <option value="IE">IE</option>
                                    </select>
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
                                <a href="#" id="delete-btn">Löschen</a>
                            </div>
                            <div class="col">
                                <a href="#" id="save-btn">Speichern</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php
    include __DIR__ . '/../../../../assets/functions/enotf/notify.php';
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
        function showMedikamentToast(message, type = 'success') {
            let toastContainer = document.getElementById('medikament-toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'medikament-toast-container';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
                document.body.appendChild(toastContainer);
            }

            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107'
            };

            const bgColor = colors[type] || colors.success;

            const toast = document.createElement('div');
            toast.style.cssText = `
                border-left: 4px solid ${bgColor};
                background-color: #2c2c2c;
                color: #fff;
                padding: 12px 16px;
                margin-bottom: 10px;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                font-family: Arial, sans-serif;
                font-size: 14px;
                opacity: 0;
                transform: translateX(300px);
                transition: all 0.3s ease;
                max-width: 300px;
                word-wrap: break-word;
            `;
            toast.textContent = message;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(300px)';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const enr = new URLSearchParams(window.location.search).get('enr');

            loadMedikamente();

            const saveButton = document.getElementById('save-btn');
            if (saveButton) {
                saveButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    saveMedikament();
                });
            }

            const deleteButton = document.getElementById('delete-btn');
            if (deleteButton) {
                deleteButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    deleteSelectedMedikament();
                });
            }
        });

        function saveMedikament() {
            const wirkstoff = document.getElementById('medis-select').value;
            const zeit = document.getElementById('medis-time').value;
            const applikation = document.getElementById('medis-admission').value;
            const dosierung = document.getElementById('medis-concentration').value;
            const einheit = document.getElementById('medis-unit').value;
            const enr = new URLSearchParams(window.location.search).get('enr');

            if (!wirkstoff || !zeit || !applikation || !dosierung || !einheit) {
                showMedikamentToast('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }

            const medikament = {
                wirkstoff: wirkstoff,
                zeit: zeit,
                applikation: applikation,
                dosierung: dosierung,
                einheit: einheit,
                timestamp: new Date().getTime()
            };

            fetch('./save_medikament.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${enr}&action=add&medikament=${encodeURIComponent(JSON.stringify(medikament))}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('erfolgreich')) {
                        resetForm();
                        loadMedikamente();
                        showMedikamentToast('Medikament erfolgreich hinzugefügt.', 'success');
                    } else {
                        showMedikamentToast('Fehler beim Speichern: ' + data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMedikamentToast('Fehler beim Speichern des Medikaments.', 'error');
                });
        }

        function loadMedikamente() {
            const enr = new URLSearchParams(window.location.search).get('enr');

            fetch('./load_medikamente.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${enr}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    displayMedikamente(data);
                })
                .catch(error => {
                    console.error('Error loading medikamente:', error);
                    document.getElementById('medis-list').innerHTML = '<div class="text-center text-danger p-4">Fehler beim Laden der Medikamente: ' + error.message + '</div>';
                });
        }

        function displayMedikamente(medikamente) {
            const listContainer = document.getElementById('medis-list');
            listContainer.innerHTML = '';

            if (!medikamente || medikamente.length === 0) {
                listContainer.innerHTML = '<div class="text-center p-4"><i class="las la-pills" style="font-size: 3em;"></i><div class="mt-2">Keine Medikamente eingetragen</div></div>';
                return;
            }

            medikamente.sort((a, b) => {
                return a.zeit.localeCompare(b.zeit);
            });

            medikamente.forEach((med, index) => {
                const medDiv = document.createElement('div');
                medDiv.className = 'medikament-item p-1 mb-1';
                medDiv.style.cssText = 'cursor: pointer; transition: all 0.2s;';
                medDiv.dataset.index = index;
                medDiv.dataset.timestamp = med.timestamp;

                medDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center text-white">
                        <div class="medikament-compact">
                            <span style="color:#a2a2a2">${med.zeit}</span>
                            <span>${med.wirkstoff}</span>
                            <span>${med.dosierung} ${med.einheit}</span>
                            <span>${med.applikation}</span>
                        </div>
                    </div>
                `;

                medDiv.addEventListener('click', function() {
                    selectMedikament(this);
                });

                medDiv.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#555';
                });

                medDiv.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.backgroundColor = 'transparent';
                    }
                });

                listContainer.appendChild(medDiv);
            });
        }

        function selectMedikament(element) {
            document.querySelectorAll('.medikament-item').forEach(item => {
                item.classList.remove('selected');
                item.style.backgroundColor = 'transparent';
            });

            element.classList.add('selected');
            element.style.backgroundColor = '#666';
        }

        function deleteSelectedMedikament() {
            const selectedItem = document.querySelector('.medikament-item.selected');
            if (!selectedItem) {
                showMedikamentToast('Bitte wählen Sie ein Medikament zum Löschen aus.', 'warning');
                return;
            }

            const timestamp = selectedItem.dataset.timestamp;
            const enr = new URLSearchParams(window.location.search).get('enr');

            fetch('./save_medikament.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `enr=${enr}&action=delete&timestamp=${timestamp}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('erfolgreich')) {
                        loadMedikamente();
                        showMedikamentToast('Medikament erfolgreich gelöscht.', 'success');
                    } else {
                        showMedikamentToast('Fehler beim Löschen: ' + data, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMedikamentToast('Fehler beim Löschen des Medikaments.', 'error');
                });
        }

        function resetForm() {
            document.getElementById('medis-select').value = '';
            document.getElementById('medis-time').value = getCurrentTime();
            document.getElementById('medis-admission').value = '';
            document.getElementById('medis-concentration').value = '';
            document.getElementById('medis-unit').value = '';
        }

        function getCurrentTime() {
            const now = new Date();
            return now.toTimeString().slice(0, 8);
        }
    </script>
</body>

</html>