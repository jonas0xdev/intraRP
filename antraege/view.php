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
    exit();
}

use App\Helpers\Flash;
use App\Auth\Permissions;

$caseid = $_GET['antrag'] ?? null;

if (!$caseid) {
    Flash::set('error', 'Keine Antragsnummer angegeben.');
    header("Location: " . BASE_PATH . "admin/index.php");
    exit();
}

// Antrag aus der neuen Tabellenstruktur laden
$stmt = $pdo->prepare("
    SELECT a.*, at.name as typ_name, at.icon as typ_icon
    FROM intra_antraege a
    JOIN intra_antrag_typen at ON a.antragstyp_id = at.id
    WHERE a.uniqueid = ?
");
$stmt->execute([$caseid]);
$antrag = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$antrag) {
    Flash::set('error', 'Antrag nicht gefunden.');
    header("Location: " . BASE_PATH . "admin/index.php");
    exit();
}

// Sicherheit: Benutzer darf nur eigene Anträge sehen (außer Admin)
if (!Permissions::check(['admin', 'application.view'])) {
    if ($antrag['discordid'] !== $_SESSION['discordtag']) {
        Flash::set('error', 'Sie haben keine Berechtigung, diesen Antrag anzusehen.');
        header("Location: " . BASE_PATH . "admin/index.php");
        exit();
    }
}

// Felddaten laden
$stmt = $pdo->prepare("
    SELECT af.*, ad.wert
    FROM intra_antrag_felder af
    LEFT JOIN intra_antraege_daten ad ON af.feldname = ad.feldname AND ad.antrag_id = (
        SELECT id FROM intra_antraege WHERE uniqueid = ?
    )
    WHERE af.antragstyp_id = ?
    ORDER BY af.sortierung ASC
");
$stmt->execute([$caseid, $antrag['antragstyp_id']]);
$felder_daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status-Mapping
$statusMapping = [
    0 => ['class' => 'info', 'text' => 'In Bearbeitung', 'icon' => 'las la-clock'],
    1 => ['class' => 'danger', 'text' => 'Abgelehnt', 'icon' => 'las la-times-circle'],
    2 => ['class' => 'warning', 'text' => 'Aufgeschoben', 'icon' => 'las la-pause-circle'],
    3 => ['class' => 'success', 'text' => 'Angenommen', 'icon' => 'las la-check-circle'],
];

$currentStatus = $statusMapping[$antrag['cirs_status']] ?? ['class' => 'dark', 'text' => 'Unbekannt', 'icon' => 'las la-question-circle'];
$createDate = new DateTime($antrag['time_added'] ?? 'now');
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Antrag [#<?php echo $caseid ?>] &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
    <style>
        .intra__tile {
            background: rgba(var(--bs-dark-rgb), 0.5);
            border: 1px solid rgba(var(--bs-light-rgb), 0.1);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .field-label {
            font-weight: 600;
            color: #aaa;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .field-value {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.75rem;
            border-radius: 0.375rem;
            min-height: 2.5rem;
        }

        .form-control-plaintext {
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 0.375rem;
            min-height: calc(1.5em + 0.75rem + 2px);
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="antrag-view">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1>
                            <i class="<?= htmlspecialchars($antrag['typ_icon']) ?> me-2"></i>
                            <?= htmlspecialchars($antrag['typ_name']) ?> #<?= htmlspecialchars($caseid) ?>
                        </h1>
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
                                            <?= htmlspecialchars($antrag['name_dn']) ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Aktueller Dienstgrad</label>
                                        <div class="form-control-plaintext fw-bold">
                                            <?= htmlspecialchars($antrag['dienstgrad']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Antragsinhalt -->
                            <div class="intra__tile p-2 mb-4">
                                <h5 class="mb-3">
                                    <i class="las la-file-alt me-2"></i>Antragsinhalt
                                </h5>

                                <!-- Dynamische Felder -->
                                <?php if (!empty($felder_daten)): ?>
                                    <?php
                                    $current_row = [];
                                    foreach ($felder_daten as $index => $feld):
                                        $breite_class = $feld['breite'] === 'half' ? 'col-md-6' : 'col-12';

                                        if (empty($current_row)) {
                                            echo '<div class="row">';
                                        }

                                        $current_row[] = $feld['breite'];
                                    ?>
                                        <div class="<?= $breite_class ?> mb-3">
                                            <div class="field-label"><?= htmlspecialchars($feld['label']) ?></div>
                                            <div class="field-value">
                                                <?php if ($feld['feldtyp'] === 'checkbox'): ?>
                                                    <?= $feld['wert'] ? '<i class="las la-check-square text-success"></i> Ja' : '<i class="las la-square text-muted"></i> Nein' ?>
                                                <?php elseif (empty($feld['wert'])): ?>
                                                    <span class="text-muted"><i>Keine Angabe</i></span>
                                                <?php else: ?>
                                                    <?= nl2br(htmlspecialchars($feld['wert'])) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php
                                        $is_last = $index === count($felder_daten) - 1;
                                        $row_complete = ($feld['breite'] === 'full') || (count($current_row) >= 2) || $is_last;

                                        if ($row_complete) {
                                            echo '</div>';
                                            $current_row = [];
                                        }
                                    endforeach;
                                    ?>
                                <?php else: ?>
                                    <div class="bg-dark rounded p-3">
                                        <p class="text-muted mb-0"><i>Keine Felddaten vorhanden</i></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Bearbeitung -->
                            <div class="intra__tile p-2 mb-5">
                                <h5 class="mb-3">
                                    <i class="las la-clipboard-check me-2"></i>Bearbeitung
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Bearbeiter</label>
                                        <div class="form-control-plaintext">
                                            <?php if (!empty($antrag['cirs_manager'])): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($antrag['cirs_manager']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i>Noch nicht zugewiesen</i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label text-muted small">Status</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                                <i class="<?= $currentStatus['icon'] ?> me-1"></i>
                                                <?= $currentStatus['text'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($antrag['cirs_text'])): ?>
                                    <div class="mt-3">
                                        <label class="form-label text-muted small">Bemerkung</label>
                                        <div class="bg-dark rounded p-3">
                                            <p class="mb-0" style="white-space: pre-line;"><?= htmlspecialchars($antrag['cirs_text']) ?></p>
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
                                        <span class="fw-bold">#<?= htmlspecialchars($caseid) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                        <span class="text-muted">Typ:</span>
                                        <span><?= htmlspecialchars($antrag['typ_name']) ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                        <span class="text-muted">Erstellt am:</span>
                                        <span><?= $createDate->format('d.m.Y H:i') ?></span>
                                    </div>
                                    <?php if (!empty($antrag['cirs_time'])): ?>
                                        <div class="d-flex justify-content-between py-2 border-bottom border-secondary">
                                            <span class="text-muted">Bearbeitet am:</span>
                                            <span><?= date('d.m.Y H:i', strtotime($antrag['cirs_time'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between py-2">
                                        <span class="text-muted">Status:</span>
                                        <span class="badge text-bg-<?= $currentStatus['class'] ?>">
                                            <i class="<?= $currentStatus['icon'] ?> me-1"></i>
                                            <?= $currentStatus['text'] ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Aktionen -->
                            <div class="intra__tile p-2">
                                <h6 class="mb-3">
                                    <i class="las la-tools me-2"></i>Aktionen
                                </h6>
                                <div class="d-grid gap-2">
                                    <a href="<?= BASE_PATH ?>admin/index.php" class="btn btn-secondary">
                                        <i class="las la-arrow-left me-2"></i>Zurück zum Dashboard
                                    </a>
                                    <?php if (Permissions::check(['admin', 'application.edit'])): ?>
                                        <a href="<?= BASE_PATH ?>admin/antraege/view.php?antrag=<?= $caseid ?>" class="btn btn-primary">
                                            <i class="las la-edit me-2"></i>Bearbeiten (Admin)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>