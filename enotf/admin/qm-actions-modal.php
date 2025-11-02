<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Nicht berechtigt']));
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'edivi.view'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
}

$stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE id = :id");
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (count($row) == 0) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Protokoll nicht gefunden']));
}

$ist_freigegeben = ($row['freigegeben'] == 1);

$row['last_edit'] = (!empty($row['last_edit']))
    ? (new DateTime($row['last_edit']))->format('d.m.Y H:i')
    : "Noch nicht bearbeitet";

$old_status = $row['protokoll_status'];

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $bearbeiter = $_POST['bearbeiter'];
    $protokoll_status = $_POST['protokoll_status'];
    $qmkommentar = $_POST['qmkommentar'];
    $logEntries = [];

    switch ($protokoll_status) {
        case 0:
            $status_klar = "Ungesehen";
            $statusstring = '<span class="badge" style="line-height: var(--bs-body-line-height); border-radius: 0;">Ungesehen</span>';
            break;
        case 1:
            $status_klar = "in Prüfung";
            $statusstring = '<span class="badge text-bg-warning" style="line-height: var(--bs-body-line-height); border-radius: 0;">in Prüfung</span>';
            break;
        case 2:
            $status_klar = "Freigegeben";
            $statusstring = '<span class="badge text-bg-success" style="line-height: var(--bs-body-line-height); border-radius: 0;">Freigegeben</span>';
            break;
        case 3:
            $status_klar = "Ungenügend";
            $statusstring = '<span class="badge text-bg-danger" style="line-height: var(--bs-body-line-height); border-radius: 0;">Ungenügend</span>';
            break;
        case 4:
            $status_klar = "Ausgeblendet";
            $statusstring = '<span class="badge text-bg-dark" style="line-height: var(--bs-body-line-height); border-radius: 0;">Ausgeblendet</span>';
            break;
    }

    if ($protokoll_status != $old_status) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $statusstring, 'bearbeiter' => $bearbeiter, 'log_aktion' => 1];
    }

    if (!empty($qmkommentar)) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $qmkommentar, 'bearbeiter' => $bearbeiter, 'log_aktion' => 0];
    }

    if (!empty($logEntries)) {
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_qmlog (protokoll_id, kommentar, bearbeiter, log_aktion) VALUES (:id, :kommentar, :bearbeiter, :log_aktion)");

        foreach ($logEntries as $entry) {
            $stmt->execute([
                'id' => $_GET['id'],
                'kommentar' => $entry['kommentar'],
                'bearbeiter' => $entry['bearbeiter'],
                'log_aktion' => $entry['log_aktion']
            ]);
        }
    }

    $auditLogger = new AuditLogger($pdo);
    $auditLogger->log($_SESSION['userid'], 'Protokoll aktualisiert [ID: ' . $_GET['id'] . ']', NULL, 'eNOTF', 1);

    $stmt = $pdo->prepare("UPDATE intra_edivi SET bearbeiter = :bearbeiter, protokoll_status = :status WHERE id = :id");
    $stmt->execute([
        'bearbeiter' => $bearbeiter,
        'status' => $protokoll_status,
        'id' => $_GET['id']
    ]);

    exit(json_encode(['success' => true, 'message' => 'Erfolgreich gespeichert']));
}

// Generate form HTML for modal
?>
<div class="row edivi__box">
    <div class="col">
        <form id="qmActionsForm" action="<?= BASE_PATH ?>enotf/admin/qm-actions-modal.php?id=<?= $_GET['id'] ?>" method="post">
            <div class="row mt-2 mb-1">
                <div class="col-3 fw-bold">Gesichtet von</div>
                <div class="col">
                    <input style="border-radius: 0 !important" type="text" name="bearbeiter" id="bearbeiter" class="w-100 form-control edivi__admin" value="<?= $_SESSION['cirs_user'] ?>" readonly>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-3 fw-bold">Status</div>
                <div class="col">
                    <select name="protokoll_status" id="protokoll_status" class="form-select w-100 edivi__admin" style="border-radius: 0 !important">
                        <option value="0" <?php echo ($row['protokoll_status'] == 0 ? 'selected' : '') ?>>Ungesehen</option>
                        <option value="1" <?php echo ($row['protokoll_status'] == 1 ? 'selected' : '') ?>>in Prüfung</option>
                        <option value="2" <?php echo ($row['protokoll_status'] == 2 ? 'selected' : '') ?>>Freigegeben</option>
                        <option value="3" <?php echo ($row['protokoll_status'] == 3 ? 'selected' : '') ?>>Ungenügend</option>
                        <option value="4" <?php echo ($row['protokoll_status'] == 4 ? 'selected' : '') ?>>Ausgeblendet</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-3 fw-bold">Bemerkung</div>
                <div class="col">
                    <textarea name="qmkommentar" id="qmkommentar" rows="8" class="w-100 form-control edivi__admin" style="resize: none; border: 1px solid #fff;" placeholder="Optionale Bemerkung hinzufügen..."></textarea>
                </div>
            </div>
            <div class="row mt-4 mb-2">
                <div class="col text-center">
                    <input class="btn btn-success" name="submit" type="submit" value="Speichern" />
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!Permissions::check(['admin', 'edivi.edit'])) : ?>
    <script>
        document.querySelector('#qmActionsForm input[type="submit"]').disabled = true;
        document.querySelector('#qmActionsForm input[type="submit"]').value = 'Keine Berechtigung';
    </script>
<?php endif; ?>