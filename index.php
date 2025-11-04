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

if (!isset($_SESSION['cirs_user']) || empty($_SESSION['cirs_user'])) {
    header("Location: " . BASE_PATH . "profil.php");
}

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="dashboard">
    <!-- PRELOAD -->

    <?php include __DIR__ . "/assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row" id="startpage">
                <div class="col">
                    <hr class="text-light my-3">
                    <h1>Dashboard</h1>
                    <?php
                    Flash::render();
                    ?>
                    <div class="alert alert-primary" role="alert">
                        <h3>Hallo, <?= $_SESSION['cirs_user'] ?>!</h3>
                        <span id="quote-of-the-day"></span>
                        <br>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col intra__tile">
                    <h4 class="mt-2 mb-3">Eigene Dokumente</h4>
                    <?php include __DIR__ . '/assets/components/index/documents.php' ?>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col intra__tile">
                    <div class="row">
                        <div class="col">
                            <h4 class="mt-2 mb-3">Eigene Anträge</h4>
                        </div>
                        <div class="col d-flex justify-content-end align-items-center">
                            <a href="<?= BASE_PATH ?>antrag/select.php" class="btn btn-success btn-sm"><i class="fa-solid fa-plus"></i> Antrag einreichen</a>
                        </div>
                    </div>
                    <?php include __DIR__ . '/assets/components/index/applications.php' ?>
                </div>
            </div>
            <div class="row mt-4 mb-5">
                <div class="col intra__tile">
                    <h4 class="mt-2 mb-3">Eigene eNOTF-Protokolle</h4>
                    <?php include __DIR__ . '/assets/components/index/protocols.php' ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const quotes = [
                "Willkommen im Intranet der <?php echo RP_ORGTYPE . " " . SERVER_CITY ?>.",
                "Fun Fact: Die ersten Rettungswagen waren Leichenwagen. Manchmal kamen Bestatter an und mussten feststellen, dass die Person noch gar nicht gestorben war.",
                "Das Schweizer Taschenmesser der <?php echo SERVER_CITY ?>er <?php echo RP_ORGTYPE ?>.",
                "Die <?php echo RP_ORGTYPE . " " . SERVER_CITY ?> - Immer für Sie da.",
                "<?php echo SYSTEM_NAME ?> powered by hypax."
            ];

            const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];

            document.getElementById("quote-of-the-day").textContent = randomQuote;
        });
    </script>
    <?php include __DIR__ . "/assets/components/footer.php"; ?>
</body>

</html>