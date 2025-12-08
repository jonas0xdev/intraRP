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
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/2.php?enr=<?= $daten['enr'] ?>" data-requires="uebergabe_an">
                            <span>Nachforderung NA</span>
                        </a>
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3.php?enr=<?= $daten['enr'] ?>" class="active">
                            <span>Übergabe</span>
                        </a>
                    </div>
                    <div class="col-2 d-flex flex-column edivi__interactbutton-more">
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3_1.php?enr=<?= $daten['enr'] ?>">
                            <span>Ort</span>
                        </a>
                        <a href="<?= BASE_PATH ?>enotf/protokoll/abschluss/3_2.php?enr=<?= $daten['enr'] ?>" class="active">
                            <span>An</span>
                        </a>
                    </div>
                    <div class="col-2 d-flex flex-column edivi__interactbutton">
                        <input type="radio" class="btn-check" id="uebergabe_an-1" name="uebergabe_an" value="1" <?php echo ($daten['uebergabe_an'] == 1 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-1">Arzt</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-2" name="uebergabe_an" value="2" <?php echo ($daten['uebergabe_an'] == 2 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-2">Pflegepersonal</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-3" name="uebergabe_an" value="3" <?php echo ($daten['uebergabe_an'] == 3 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-3">Rettungssanitäter</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-4" name="uebergabe_an" value="4" <?php echo ($daten['uebergabe_an'] == 4 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-4">Rettungsassistent</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-5" name="uebergabe_an" value="5" <?php echo ($daten['uebergabe_an'] == 5 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-5">Notfallsanitäter</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-6" name="uebergabe_an" value="6" <?php echo ($daten['uebergabe_an'] == 6 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-6">Polizei</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-7" name="uebergabe_an" value="7" <?php echo ($daten['uebergabe_an'] == 7 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-7">Angehörige</label>

                        <input type="radio" class="btn-check" id="uebergabe_an-99" name="uebergabe_an" value="99" <?php echo ($daten['uebergabe_an'] == 99 ? 'checked' : '') ?> autocomplete="off">
                        <label for="uebergabe_an-99">Sonstige</label>
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