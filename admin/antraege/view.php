<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "admin/login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'application.edit'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "admin/index.php");
}

$caseid = $_GET['antrag'];

// MYSQL QUERY
$stmt = $pdo->prepare("SELECT * FROM intra_antrag_bef WHERE uniqueid = :caseid");
$stmt->bindParam(':caseid', $caseid);
$stmt->execute();
$row = $stmt->fetch();

if (isset($_POST['new']) && $_POST['new'] == 1) {
    $id = $_REQUEST['case_id'];
    $cirs_manager = $_SESSION['cirs_user'] ?? "Fehler Fehler";
    $cirs_status = $_REQUEST['cirs_status'];
    $cirs_text = $_REQUEST['cirs_text'];
    $jetzt = date("Y-m-d H:i:s");

    $auditLogger = new AuditLogger($pdo);

    if ($row['cirs_manager'] != $cirs_manager) {
        $auditLogger->log($_SESSION['userid'], 'Bearbeiter geändert [ID: ' . $id . ']', $cirs_manager, 'Anträge',  1);
    }
    if ($row['cirs_status'] != $cirs_status) {
        $auditLogger->log($_SESSION['userid'], 'Status geändert [ID: ' . $id . ']', 'Neuer Status: ' . $cirs_status, 'Anträge',  1);
    }
    if ($row['cirs_text'] != $cirs_text) {
        $auditLogger->log($_SESSION['userid'], 'Bemerkung geändert [ID: ' . $id . ']', '"' . $cirs_text . '"', 'Anträge',  1);
    }

    $stmt = $pdo->prepare("UPDATE intra_antrag_bef SET cirs_manager = :cirs_manager, cirs_status = :cirs_status, cirs_text = :cirs_text, cirs_time = :jetzt WHERE id = :id");
    $stmt->execute([
        ':cirs_manager' => $cirs_manager,
        ':cirs_status' => $cirs_status,
        ':cirs_text' => $cirs_text,
        ':jetzt' => $jetzt,
        ':id' => $id
    ]);

    header("Refresh:0");
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
    <title>Antrag bearbeiten [#<?php echo htmlspecialchars($caseid ?? 'N/A') ?>] &rsaquo; <?php echo SYSTEM_NAME ?></title>
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

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include "../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>Beförderungsantrag bearbeiten #<?php echo htmlspecialchars($caseid ?? 'N/A'); ?></h1>
                    </div>
                    <?php Flash::render(); ?>
                    <hr class="text-light my-3">

                    <form action="" id="cirs-form" method="post">
                        <input type="hidden" name="new" value="1" />
                        <input type="hidden" name="case_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="cirs_manger" value="<?= $_SESSION['cirs_user'] ?? "Fehler Fehler" ?>">

                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Antragsteller Information -->
                                <div class="intra__tile mb-4">
                                    <h5 class="mb-3">
                                        <i class="las la-user me-2"></i>Antragsteller
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name_dn" class="form-label text-muted small">Name und Dienstnummer</label>
                                            <input type="text" class="form-control" id="name_dn" name="name_dn" placeholder="" value="<?= htmlspecialchars($row['name_dn'] ?? '') ?>" required readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="dienstgrad" class="form-label text-muted small">Aktueller Dienstgrad</label>
                                            <input type="text" class="form-control" id="dienstgrad" name="dienstgrad" placeholder="" value="<?= htmlspecialchars($row['dienstgrad'] ?? '') ?>" required readonly>
                                        </div>
                                    </div>
                                </div>

                                <!-- Antragsinhalt -->
                                <div class="intra__tile mb-4">
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
                                <div class="intra__tile mb-4">
                                    <h5 class="mb-3">
                                        <i class="las la-clipboard-check me-2"></i>Bearbeitung
                                    </h5>

                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Aktueller Bearbeiter</label>
                                        <div class="form-control-plaintext">
                                            <?php if (!empty($row['cirs_manager'])): ?>
                                                <span class="fw-bold"><?php echo htmlspecialchars($row['cirs_manager']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i>Noch nicht zugewiesen</i></span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">Wird automatisch auf "<?= htmlspecialchars($_SESSION['cirs_user'] ?? "Fehler Fehler") ?>" gesetzt beim Speichern</small>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cirs_status" class="form-label fw-bold">Status setzen <span class="text-main-color">*</span></label>
                                        <select class="form-select" id="cirs_status" name="cirs_status" autocomplete="off" required>
                                            <option value="0" <?php if ($row['cirs_status'] == "0") echo 'selected'; ?>>
                                                <i class="las la-clock"></i> In Bearbeitung
                                            </option>
                                            <option value="1" <?php if ($row['cirs_status'] == "1") echo 'selected'; ?>>
                                                <i class="las la-times-circle"></i> Abgelehnt
                                            </option>
                                            <option value="2" <?php if ($row['cirs_status'] == "2") echo 'selected'; ?>>
                                                <i class="las la-pause-circle"></i> Aufgeschoben
                                            </option>
                                            <option value="3" <?php if ($row['cirs_status'] == "3") echo 'selected'; ?>>
                                                <i class="las la-check-circle"></i> Angenommen
                                            </option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cirs_text" class="form-label fw-bold">Bemerkung durch Bearbeiter</label>
                                        <textarea class="form-control" id="cirs_text" name="cirs_text" rows="5" placeholder="Fügen Sie hier Ihre Bemerkungen zum Antrag hinzu..."><?= htmlspecialchars($row['cirs_text'] ?? '') ?></textarea>
                                        <small class="text-muted">Diese Bemerkung wird dem Antragsteller angezeigt</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <!-- Seitliche Informationen -->
                                <div class="intra__tile mb-4">
                                    <h6 class="mb-3">
                                        <i class="las la-info-circle me-2"></i>Antragsdetails
                                    </h6>
                                    <div class="small">
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Antragsnummer:</span>
                                            <span class="fw-bold">#<?php echo htmlspecialchars($caseid ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Erstellt am:</span>
                                            <span><?php echo $createDate->format('d.m.Y H:i'); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Discord-ID:</span>
                                            <span class="font-monospace small"><?php echo htmlspecialchars($row['discordid'] ?? 'N/A'); ?></span>
                                        </div>
                                        <?php if (!empty($row['cirs_time'])): ?>
                                            <div class="d-flex justify-content-between py-2">
                                                <span class="text-muted">Letzte Bearbeitung:</span>
                                                <span><?php echo date('d.m.Y H:i', strtotime($row['cirs_time'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Status:</span>
                                            <span class="badge text-bg-<?php echo $currentStatus['class']; ?>">
                                                <i class="<?php echo $currentStatus['icon']; ?> me-1"></i>
                                                <?php echo $currentStatus['text']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Aktionen -->
                                <div class="intra__tile">
                                    <h6 class="mb-3">
                                        <i class="las la-tools me-2"></i>Aktionen
                                    </h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" type="submit">
                                            <i class="las la-save me-2"></i>Änderungen speichern
                                        </button>
                                        <a href="<?= BASE_PATH ?>admin/antraege/list.php" class="btn btn-secondary">
                                            <i class="las la-arrow-left me-2"></i>Zurück zur Übersicht
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
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

        .intra__tile {
            background: rgba(var(--bs-dark-rgb), 0.5);
            border: 1px solid rgba(var(--bs-light-rgb), 0.1);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
    </style>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>