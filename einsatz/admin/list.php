<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

if (!Permissions::check(['admin', 'fire.incident.qm'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

require __DIR__ . '/../../assets/config/database.php';

$incidents = [];
try {
    $stmt = $pdo->query("SELECT i.*, m.fullname AS leader_name FROM intra_fire_incidents i LEFT JOIN intra_mitarbeiter m ON i.leader_id = m.id ORDER BY i.created_at DESC");
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Einsatzprotokolle (QM)</h1>
            <a href="<?= BASE_PATH ?>einsatz/create.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> Neu</a>
        </div>
        <?php App\Helpers\Flash::render(); ?>

        <div class="intra__tile p-3">
            <table class="table table-striped" id="table-incidents">
                <thead>
                    <tr>
                        <th>Einsatznummer</th>
                        <th>Beginn</th>
                        <th>Ort</th>
                        <th>Stichwort</th>
                        <th>Leiter</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $i): ?>
                        <?php
                        if (!$i['finalized']) {
                            $badge = 'bg-secondary';
                            $statusText = 'Unfertig';
                        } else {
                            $badge = 'bg-danger';
                            $statusText = 'Ungesichtet';
                            if ($i['status'] === 'gesichtet') {
                                $badge = 'bg-success';
                                $statusText = 'Gesichtet';
                            }
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($i['incident_number'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($i['started_at']) ?></td>
                            <td><?= htmlspecialchars($i['location']) ?></td>
                            <td><?= htmlspecialchars($i['keyword']) ?></td>
                            <td><?= htmlspecialchars($i['leader_name'] ?? '-') ?></td>
                            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($statusText) ?></span></td>
                            <td>
                                <a class="btn btn-sm btn-primary" href="<?= BASE_PATH ?>einsatz/view.php?id=<?= (int)$i['id'] ?>">Öffnen</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-incidents').DataTable({
                stateSave: true,
                order: [
                    [0, 'desc']
                ],
                pageLength: 20,
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Einträgen",
                    "lengthMenu": "_MENU_ Einträge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Suche:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    }
                }
            });
        });
    </script>
    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>