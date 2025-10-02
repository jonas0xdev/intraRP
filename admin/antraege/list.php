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

if (!Permissions::check(['admin', 'application.edit'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "admin/index.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        a.uniqueid,
        at.name as typ_name,
        at.icon as typ_icon,
        a.name_dn,
        a.cirs_status,
        a.time_added
    FROM intra_antraege a
    JOIN intra_antrag_typen at ON a.antragstyp_id = at.id
    ORDER BY a.time_added DESC
");
$stmt->execute();
$antraege = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Antragsübersicht &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
</head>

<body data-bs-theme="dark" data-page="antraege">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="mb-0">
                            <i class="las la-clipboard-list me-2"></i>Antragsübersicht
                        </h1>
                        <?php if (Permissions::check(['admin'])): ?>
                            <a href="<?= BASE_PATH ?>admin/settings/antraege/list.php" class="btn btn-primary">
                                <i class="las la-cog me-2"></i>Typen verwalten
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-antrag">
                            <thead>
                                <tr>
                                    <th scope="col">Nr.</th>
                                    <th scope="col">Typ</th>
                                    <th scope="col">Von</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Datum</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($antraege) > 0) {
                                    foreach ($antraege as $row) {
                                        $adddat = date("d.m.Y | H:i", strtotime($row['time_added']));

                                        $cirs_state = "Unbekannt";
                                        $bgColor = "";
                                        $badgeClass = "secondary";

                                        switch ($row['cirs_status']) {
                                            case 0:
                                                $cirs_state = "In Bearbeitung";
                                                $badgeClass = "info";
                                                break;
                                            case 1:
                                                $bgColor = "rgba(255,0,0,.05)";
                                                $cirs_state = "Abgelehnt";
                                                $badgeClass = "danger";
                                                break;
                                            case 2:
                                                $cirs_state = "Aufgeschoben";
                                                $badgeClass = "warning";
                                                break;
                                            case 3:
                                                $bgColor = "rgba(0,255,0,.05)";
                                                $cirs_state = "Angenommen";
                                                $badgeClass = "success";
                                                break;
                                        }

                                        // URL zur Antragsansicht
                                        $view_url = BASE_PATH . "admin/antraege/view.php?antrag={$row['uniqueid']}";

                                        echo "<tr";
                                        if (!empty($bgColor)) {
                                            echo " style='--bs-table-striped-bg: {$bgColor}; --bs-table-bg: {$bgColor};'";
                                        }
                                        echo ">
                                            <td><strong>{$row['uniqueid']}</strong></td>
                                            <td>
                                                <i class='{$row['typ_icon']} me-1'></i>
                                                <span class='small'>{$row['typ_name']}</span>
                                            </td>
                                            <td>{$row['name_dn']}</td>
                                            <td>
                                                <span class='badge bg-{$badgeClass}'>{$cirs_state}</span>
                                            </td>
                                            <td>
                                                <span style='display:none'>{$row['time_added']}</span>
                                                {$adddat}
                                            </td>
                                            <td>
                                                <a class='btn btn-main-color btn-sm' href='{$view_url}'>
                                                    <i class='las la-eye me-1'></i>Öffnen
                                                </a>
                                            </td>
                                        </tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-antrag').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order: [
                    [4, 'desc']
                ], // Nach Datum absteigend sortieren
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_ | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Anträgen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Anträge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Anträge suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    },
                    "aria": {
                        "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                        "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                    }
                }
            });
        });
    </script>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>