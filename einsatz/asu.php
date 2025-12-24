<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Remove ALL existing CSP headers
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');

    // Set permissive headers for FiveM
    header('Content-Security-Policy: frame-ancestors *', true);
    header('Access-Control-Allow-Origin: *');
}


session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;

// Check permissions
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Helper for date/time formatting
function fmt_dt(?string $ts): string
{
    if (!$ts) return '-';
    try {
        $dt = new DateTime($ts, new DateTimeZone('Europe/Berlin'));
        return $dt->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

// Get parameters from URL (pre-filled from incident if available)
$prefill_number = $_GET['incident_number'] ?? '';
$prefill_location = $_GET['location'] ?? '';
$prefill_incident_id = $_GET['incident_id'] ?? null;

// Load existing ASU protocols if incident_id is provided
$asuProtocols = [];
if ($prefill_incident_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_fire_incident_asu WHERE incident_id = ? ORDER BY created_at DESC");
        $stmt->execute([$prefill_incident_id]);
        $asuProtocols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Silently fail if query doesn't work
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <style>
        .sidebar-nav .nav-link {
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .sidebar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-nav .nav-link.active {
            background-color: rgba(13, 110, 253, 0.5);
            font-weight: 500;
        }
    </style>
    <script src="<?= BASE_PATH ?>assets/js/dialogs.js"></script>
    <script>
        const basePath = '<?= BASE_PATH ?>';
    </script>
</head>

<body data-bs-theme="dark" data-page="asu">
    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <div class="sidebar-nav" style="width: 250px; min-height: 100vh; background-color: #1a1a1a; border-right: 1px solid #333;">
            <div class="p-3">
                <div class="text-center mb-3">
                    <img src="https://dev.intrarp.de/assets/img/defaultLogo.webp" alt="Logo" style="max-width: 120px; height: auto;">
                </div>
                <ul class="nav flex-column" style="list-style: none; padding: 0; margin: 0;">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/create.php">
                            <i class="fa-solid fa-plus me-2"></i>Neuer Einsatz
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/list.php">
                            <i class="fa-solid fa-list me-2"></i>Meine Einsätze
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="<?= BASE_PATH ?>einsatz/asu.php">
                            <i class="fa-solid fa-mask-ventilator me-2"></i>AS-Überwachung
                        </a>
                    </li>

                    <?php if (Permissions::check(['admin', 'fire.incident.qm'])): ?>
                        <li class="nav-item mt-4">
                            <small class="text-muted px-3 d-block mb-2">VERWALTUNG</small>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/admin/list.php">
                                <i class="fa-solid fa-shield-alt me-2"></i>Alle Einsätze
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($prefill_incident_id): ?>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= $prefill_incident_id ?>&tab=bericht">
                                <i class="fa-solid fa-arrow-left me-2"></i>Zurück zum Einsatz
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1><i class="fa-solid fa-lungs me-2"></i>Atemschutzüberwachung (ASU)</h1>
                    <div class="current-time-display">
                        <i class="fa-solid fa-clock me-2"></i>
                        <span id="currentTime" style="font-family: monospace; font-size: 1.1rem; font-weight: bold;">00:00</span>
                    </div>
                </div>

                <!-- Gespeicherte ASU-Protokolle -->
                <?php if (count($asuProtocols) > 0): ?>
                    <div class="intra__tile p-3 mb-3">
                        <h4 class="mb-3">Gespeicherte ASU-Protokolle</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Überwacher</th>
                                        <th>Einsatzort</th>
                                        <th>Datum</th>
                                        <th>Trupps</th>
                                        <th>Erstellt am</th>
                                        <th width="100">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($asuProtocols as $asu):
                                        $data = json_decode($asu['data'], true) ?? [];
                                        $truppCount = 0;
                                        if (!empty($data['trupp1']['tf'])) $truppCount++;
                                        if (!empty($data['trupp2']['tf'])) $truppCount++;
                                        if (!empty($data['trupp3']['tf'])) $truppCount++;
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($asu['supervisor'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($data['missionLocation'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($data['missionDate'] ?? '-') ?></td>
                                            <td><span class="badge bg-info"><?= $truppCount ?> Trupp<?= $truppCount !== 1 ? 's' : '' ?></span></td>
                                            <td><?= fmt_dt($asu['created_at']) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" onclick="viewASUProtocol(<?= htmlspecialchars(json_encode($data)) ?>)">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                                <?php if (Permissions::check('fire.incident.edit')): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteASUProtocol(<?= (int)$asu['id'] ?>)">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Neue ASU-Überwachung erstellen -->
                <div class="intra__tile p-3">
                    <!-- <h4 class="mb-3"><i class="fa-solid fa-plus me-2"></i>Neue Atemschutzüberwachung</h4> -->

                    <form id="asuForm">
                        <input type="hidden" id="incidentId" value="<?= htmlspecialchars($prefill_incident_id ?? '') ?>">

                        <!-- Einsatzinformationen -->
                        <div class="card bg-dark mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">Einsatzinformationen</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatznummer *</label>
                                        <input type="text" class="form-control" id="missionNumber" value="<?= htmlspecialchars($prefill_number) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatzort *</label>
                                        <input type="text" class="form-control" id="missionLocation" value="<?= htmlspecialchars($prefill_location) ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Einsatzdatum *</label>
                                        <input type="text" class="form-control" id="missionDate" placeholder="TT.MM.JJJJ" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Überwacher *</label>
                                        <input type="text" class="form-control" id="supervisor" placeholder="Name des Überwachenden" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php require __DIR__ . '/tabs/asu_trupps.php'; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View ASU Protocol Modal -->
    <div class="modal fade" id="viewASUModal" tabindex="-1" aria-labelledby="viewASUModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="viewASUModalLabel">ASU-Protokoll Ansicht</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="asuProtocolView">
                    <!-- Content will be injected by JavaScript -->
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= BASE_PATH ?>assets/js/asu.js"></script>
</body>

</html>