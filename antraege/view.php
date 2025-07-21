<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "admin/login.php");
    exit();
}

if (!isset($_SESSION['cirs_user']) || empty($_SESSION['cirs_user'])) {
    header("Location: " . BASE_PATH . "admin/users/editprofile.php");
}

use App\Helpers\Flash;
use App\Auth\Permissions;

$caseid = $_GET['antrag'] ?? null;

if (!$caseid) {
    Flash::set('error', 'Keine Antragsnummer angegeben.');
    header("Location: " . BASE_PATH . "dashboard.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM intra_antrag_bef WHERE uniqueid = ?");
$stmt->execute([$caseid]);
$row = $stmt->fetch();

if (!$row) {
    Flash::set('error', 'Antrag nicht gefunden.');
    header("Location: " . BASE_PATH . "dashboard.php");
    exit();
}

// Status-Mapping für bessere Lesbarkeit
$statusMapping = [
    0 => ['class' => 'info', 'text' => 'In Bearbeitung', 'icon' => 'las la-clock'],
    1 => ['class' => 'danger', 'text' => 'Abgelehnt', 'icon' => 'las la-times-circle'],
    2 => ['class' => 'warning', 'text' => 'Aufgeschoben', 'icon' => 'las la-pause-circle'],
    3 => ['class' => 'success', 'text' => 'Angenommen', 'icon' => 'las la-check-circle'],
];

$currentStatus = $statusMapping[$row['cirs_status']] ?? ['class' => 'dark', 'text' => 'Unbekannt', 'icon' => 'las la-question-circle'];

// Formatiere Datum für bessere Anzeige
$createDate = new DateTime($row['createdate'] ?? 'now');
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Antrag [#<?php echo $caseid ?>] &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
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
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
    <meta property="og:site_name" content="<?php echo SERVER_NAME ?>" />
    <meta property="og:url" content="https://<?php echo SYSTEM_URL . BASE_PATH ?>/dashboard.php" />
    <meta property="og:title" content="<?php echo SYSTEM_NAME ?> - Intranet <?php echo SERVER_CITY ?>" />
    <meta property="og:image" content="<?php echo META_IMAGE_URL ?>" />
    <meta property="og:description" content="Verwaltungsportal der <?php echo RP_ORGTYPE . " " .  SERVER_CITY ?>" />

</head>

<body data-bs-theme="dark" data-page="antrag-view">
    <!-- PRELOAD -->

    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>Beförderungsantrag #<?php echo htmlspecialchars($caseid); ?></h1>
                    </div>
                    <?php Flash::render(); ?>
                    <hr class="text-light my-3">

                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Hauptinformationen -->
                            <div class="intra__tile p-2 mb-4">
                                <h5 class="mb-3">
                                    <i class="las la-user me-2"></i>Antragsteller
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Name und Dienstnummer</label>
                                        <div class="form-control-plaintext fw-bold">
                                            <?php echo htmlspecialchars($row['name_dn']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Aktueller Dienstgrad</label>
                                        <div class="form-control-plaintext fw-bold">
                                            <?php echo htmlspecialchars($row['dienstgrad']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Antragsinhalt -->
                            <div class="intra__tile p-2 mb-4">
                                <h5 class="mb-3">
                                    <i class="las la-file-alt me-2"></i>Schriftlicher Antrag
                                </h5>
                                <div class="bg-dark rounded p-3">
                                    <?php if (!empty($row['freitext'])): ?>
                                        <p class="mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($row['freitext']); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0"><i>Kein Antragstext vorhanden</i></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Bearbeitung -->
                            <div class="intra__tile p-2">
                                <h5 class="mb-3">
                                    <i class="las la-clipboard-check me-2"></i>Bearbeitung
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Bearbeiter</label>
                                        <div class="form-control-plaintext">
                                            <?php if (!empty($row['cirs_manager'])): ?>
                                                <span class="fw-bold"><?php echo htmlspecialchars($row['cirs_manager']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i>Noch nicht zugewiesen</i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Status</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge text-bg-<?php echo $currentStatus['class']; ?>">
                                                <i class="<?php echo $currentStatus['icon']; ?> me-1"></i>
                                                <?php echo $currentStatus['text']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($row['cirs_text'])): ?>
                                    <div class="mt-3">
                                        <label class="form-label text-muted small">Bemerkung</label>
                                        <div class="bg-dark rounded p-3">
                                            <p class="mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($row['cirs_text']); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Seitliche Informationen -->
                            <div class="intra__tile p-2 mb-4">
                                <h6 class="mb-3">
                                    <i class="las la-info-circle me-2"></i>Antragsdetails
                                </h6>
                                <div class="small">
                                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                        <span class="text-muted">Antragsnummer:</span>
                                        <span class="fw-bold">#<?php echo htmlspecialchars($caseid); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                        <span class="text-muted">Erstellt am:</span>
                                        <span><?php echo $createDate->format('d.m.Y H:i'); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                        <span class="text-muted">Discord-ID:</span>
                                        <span class="font-monospace small"><?php echo htmlspecialchars($row['discordid'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <?php if (Permissions::check(['admin', 'application.view'])): ?>
                                <!-- Aktionen -->
                                <div class="intra__tile p-2">
                                    <h6 class="mb-3">
                                        <i class="las la-tools me-2"></i>Aktionen
                                    </h6>
                                    <div class="d-grid gap-2">
                                        <a href="<?= BASE_PATH ?>admin/antraege/list.php" class="btn btn-secondary">
                                            <i class="las la-arrow-left me-2"></i>Zurück zur Übersicht
                                        </a>
                                        <?php if (Permissions::check(['admin', 'application.edit'])): ?>
                                            <a href="<?= BASE_PATH ?>admin/antraege/view.php?antrag=<?php echo $caseid; ?>" class="btn btn-primary">
                                                <i class="las la-edit me-2"></i>Bearbeiten
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .badge-sm {
            font-size: 0.7em;
        }

        .form-control-plaintext {
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 0.375rem;
            min-height: calc(1.5em + 0.75rem + 2px);
        }
    </style>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>