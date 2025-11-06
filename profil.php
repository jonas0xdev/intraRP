<?php
session_start();
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Utils\AuditLogger;

$userid = $_SESSION['userid'];
$userHelper = new UserHelper($pdo);

$stmt = $pdo->prepare("SELECT * FROM intra_users WHERE id = :id");
$stmt->bindParam(':id', $userid, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['new']) && $_POST['new'] == 1) {
    $id = $_REQUEST['id'];
    $fullname = trim($_POST['fullname']);

    try {
        $stmt = $pdo->prepare("UPDATE intra_users SET fullname = :fullname WHERE id = :id");
        $stmt->bindValue(':fullname', $fullname, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        Flash::set('own', 'data-changed');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($userid, 'Daten geändert [ID: ' . $id . ']', null, 'Selbst', 0);
        header("Refresh:0");
        exit();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "benutzer/editprofile.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = "Profil bearbeiten &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;
    include __DIR__ . "/assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark">
    <?php include __DIR__ . "/assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <h1 class="mb-5">Eigene Daten bearbeiten</h1>
                    <?php
                    $currentFullname = $userHelper->getCurrentUserFullname();
                    $hasLinkedProfile = $userHelper->hasLinkedProfile();
                    $isNewSystem = $userHelper->isNewSystem();
                    
                    if (!$hasLinkedProfile && $isNewSystem) {
                        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
                        echo '<h4 class="alert-heading"><i class="fa-solid fa-info-circle"></i> Information</h4>';
                        echo 'Dies ist ein neues System ohne verknüpfte Mitarbeiterprofile. Du kannst trotzdem mit dem System arbeiten - dein Name wird automatisch aus deinem verknüpften Mitarbeiterprofil übernommen, sobald eines erstellt wurde.';
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
                        echo '</div>';
                    } elseif (!$hasLinkedProfile) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                        echo '<h4 class="alert-heading"><i class="fa-solid fa-exclamation-triangle"></i> Warnung</h4>';
                        echo 'Dein Discord-Account ist noch nicht mit einem Mitarbeiterprofil verknüpft. Bitte wende dich an einen Administrator, um dein Profil zu verknüpfen.';
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
                        echo '</div>';
                    }
                    
                    if (empty($currentFullname) || $currentFullname === 'Unknown') {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
                        echo '<h4 class="alert-heading">Achtung!</h4>';
                        echo 'Du hast noch keinen Namen hinterlegt. <u style="font-weight:bold">Bitte hinterlege deinen Namen jetzt!</u><br>Bei fehlendem Namen kann es zu technischen Problemen kommen.';
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
                        echo '</div>';
                    }

                    Flash::render();
                    ?>
                    <form name="form" method="post" action="">
                        <div class="intra__tile py-2 px-3">
                            <input type="hidden" name="new" value="1" />
                            <input name="id" type="hidden" value="<?= $row['id'] ?>" />
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="fullname" class="form-label fw-bold">Vor- und Zuname</label>
                                    <input type="text" class="form-control" id="fullname" name="fullname" placeholder="" value="<?= $row['fullname'] ?>">
                                </div>
                                <?php if (!empty($_SESSION['permissions'])): ?>
                                    <!-- <div class="col-6 mb-3">
                                        <label for="aktenid" class="form-label fw-bold">Mitarbeiterakten-ID</label>
                                        <input type="number" class="form-control" id="aktenid" name="aktenid" placeholder="" value="<?= htmlspecialchars($row['aktenid']) ?>">
                                    </div> -->
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col mb-3 mx-auto">
                                <input class="mt-4 btn btn-success btn-sm" name="submit" type="submit" value="Änderungen speichern" />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>