<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
require_once __DIR__ . '/../assets/functions/enotf/pin_middleware.php';

$prot_url = "https://" . SYSTEM_URL . "/enotf/index.php";

date_default_timezone_set('Europe/Berlin');
$currentTime = date('H:i');
$currentDate = date('d.m.Y');

if (!isset($_SESSION['fahrername']) || !isset($_SESSION['protfzg'])) {
    header("Location: " . BASE_PATH . "enotf/login.php");
    exit();
}

$pinEnabled = (defined('ENOTF_USE_PIN') && ENOTF_USE_PIN === true) ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>eNOTF &rsaquo; <?php echo SYSTEM_NAME ?></title>
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
    <meta property="og:title" content="eNOTF &rsaquo; <?php echo SYSTEM_NAME ?>" />
    <meta property="og:image" content="https://<?php echo SYSTEM_URL ?>/assets/img/aelrd.png" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />
</head>

<body data-bs-theme="dark" style="overflow-x:hidden" id="edivi__login" data-pin-enabled="<?= $pinEnabled ?>">
    <form name="form" method="post" action="<?= BASE_PATH ?>assets/functions/enotf/enrbridge.php" id="enrForm">
        <input type="hidden" name="new" value="1" />
        <input type="hidden" name="action" value="openOrCreate" />
        <input type="hidden" name="prot_by" id="prot_by" value="" />
        <input type="hidden" name="force_create" id="force_create" value="0" />
        <div class="container-fluid" id="edivi__container">
            <div class="row h-100">
                <div class="col" id="edivi__content">
                    <div class="hr my-5" style="color:transparent"></div>
                    <div class="row mx-5">
                        <div class="col">
                            <input type="text" class="form-control mb-3" name="enr" id="enr" placeholder="Einsatznummer" required />
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton-primary w-100" id="rdprot" name="rdprot" onclick="setProtBy(0)">Rettungsdienst-Protokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col">
                            <button class="edivi__nidabutton-primary w-100" id="naprot" name="naprot" onclick="setProtBy(1)">Notarzt-Protokoll</button>
                        </div>
                    </div>
                    <div class="row my-5 mx-5">
                        <div class="col text-center">
                            <a href="overview.php" class="edivi__nidabutton-secondary w-100" style="display:inline-block">zurück</a>
                        </div>
                    </div>
                </div>
            </div>
    </form>

    <!-- Konflikt Modal -->
    <div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="conflictModalLabel">Protokoll bereits vorhanden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="conflictMessage"></p>
                    <p><strong>Möchten Sie trotzdem ein neues Protokoll für diese Einsatznummer erstellen?</strong></p>
                    <p class="text-muted small">Das neue Protokoll wird mit einer Nummerierung versehen (z.B. _1, _2, etc.)</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" id="confirmCreate">Trotzdem erstellen</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setProtBy(value) {
            document.getElementById('prot_by').value = value;
        }

        function checkForConflict(enr, protBy) {
            return fetch('<?= BASE_PATH ?>assets/functions/enotf/check_conflict.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'enr=' + encodeURIComponent(enr) + '&prot_by=' + encodeURIComponent(protBy)
                })
                .then(response => response.json());
        }

        document.getElementById('enr').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9_]/g, '');
        });

        document.getElementById('enrForm').addEventListener('submit', function(e) {
            const protBy = document.getElementById('prot_by').value;
            const enr = document.getElementById('enr').value;
            const forceCreate = document.getElementById('force_create').value;

            if (protBy !== '0' && protBy !== '1') {
                e.preventDefault();
                alert("Bitte wähle ein Protokoll aus (RD oder NA).");
                return;
            }

            if (!enr) {
                e.preventDefault();
                alert("Bitte gib eine Einsatznummer ein.");
                return;
            }

            // Wenn force_create gesetzt ist, normale Weiterleitung
            if (forceCreate === '1') {
                return;
            }

            // Konfliktprüfung
            e.preventDefault();
            checkForConflict(enr, protBy)
                .then(result => {
                    if (result.conflict) {
                        // Konflikt gefunden - Modal anzeigen
                        document.getElementById('conflictMessage').textContent = result.message;
                        const modal = new bootstrap.Modal(document.getElementById('conflictModal'));
                        modal.show();
                    } else {
                        // Kein Konflikt - normal weiterleiten
                        document.getElementById('enrForm').submit();
                    }
                })
                .catch(error => {
                    console.error('Fehler bei der Konfliktprüfung:', error);
                    // Bei Fehler normal weiterleiten
                    document.getElementById('enrForm').submit();
                });
        });

        document.getElementById('confirmCreate').addEventListener('click', function() {
            document.getElementById('force_create').value = '1';
            document.getElementById('enrForm').submit();
        });

        var modalCloseButton = document.querySelector('#myModal4 .btn-close');
        var freigeberInput = document.getElementById('freigeber');

        if (modalCloseButton && freigeberInput) {
            modalCloseButton.addEventListener('click', function() {
                freigeberInput.value = '';
            });
        }
    </script>
    <script src="<?= BASE_PATH ?>assets/js/pin_activity.js"></script>
</body>

</html>