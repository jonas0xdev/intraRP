<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">System-Konfiguration</h1>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createConfigModal">
                            <i class="fa-solid fa-plus"></i> Konfiguration hinzufügen
                        </button>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-config">
                            <thead>
                                <tr>
                                    <th scope="col">Variable</th>
                                    <th scope="col">Wert</th>
                                    <th scope="col">Beschreibung</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_config ORDER BY var ASC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $value = htmlspecialchars($row['value'] ?? '');
                                    $description = htmlspecialchars($row['description'] ?? '');

                                    echo "<tr>";
                                    echo "<td><code>" . htmlspecialchars($row['var']) . "</code></td>";
                                    echo "<td>" . $value . "</td>";
                                    echo "<td>" . $description . "</td>";
                                    echo "<td>
                                        <a href='#' class='btn btn-sm btn-primary edit-btn' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#editConfigModal' 
                                            data-id='{$row['id']}' 
                                            data-var='{$row['var']}' 
                                            data-value='" . htmlspecialchars($row['value'] ?? '') . "' 
                                            data-description='" . htmlspecialchars($row['description'] ?? '') . "'>
                                            <i class='fa-solid fa-pen'></i>
                                        </a>
                                    </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT CONFIG MODAL -->
    <div class="modal fade" id="editConfigModal" tabindex="-1" aria-labelledby="editConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/config/update.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editConfigModalLabel">Konfiguration bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="id" id="config-id">

                        <div class="mb-3">
                            <label for="config-var" class="form-label">Variable</label>
                            <input type="text" class="form-control" name="var" id="config-var" required>
                        </div>

                        <div class="mb-3">
                            <label for="config-value" class="form-label">Wert</label>
                            <input type="text" class="form-control" name="value" id="config-value">
                        </div>

                        <div class="mb-3">
                            <label for="config-description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" name="description" id="config-description" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- END EDIT CONFIG MODAL -->

    <!-- CREATE CONFIG MODAL -->
    <div class="modal fade" id="createConfigModal" tabindex="-1" aria-labelledby="createConfigModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/config/create.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createConfigModalLabel">Neue Konfiguration hinzufügen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new-config-var" class="form-label">Variable</label>
                            <input type="text" class="form-control" name="var" id="new-config-var" required>
                        </div>

                        <div class="mb-3">
                            <label for="new-config-value" class="form-label">Wert</label>
                            <input type="text" class="form-control" name="value" id="new-config-value">
                        </div>

                        <div class="mb-3">
                            <label for="new-config-description" class="form-label">Beschreibung</label>
                            <textarea class="form-control" name="description" id="new-config-description" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- END CREATE CONFIG MODAL -->

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#table-config').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 20,
                order: [
                    [0, 'asc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Konfigurationen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Konfigurationen pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Konfiguration suchen:",
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit button handler
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    document.getElementById('config-id').value = id;
                    document.getElementById('config-var').value = this.dataset.var;
                    document.getElementById('config-value').value = this.dataset.value;
                    document.getElementById('config-description').value = this.dataset.description;
                });
            });
        });
    </script>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>