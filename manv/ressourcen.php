<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVRessource;
use App\MANV\MANVLage;
use App\MANV\MANVLog;
use App\Auth\Permissions;

$lageId = $_GET['lage_id'] ?? null;
if (!$lageId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvRessource = new MANVRessource($pdo);
$manvLage = new MANVLage($pdo);
$manvLog = new MANVLog($pdo);

$lage = $manvLage->getById((int)$lageId);
if (!$lage) {
    header('Location: index.php');
    exit;
}

// Bearbeiten einer Ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $data = [
        'typ' => $_POST['typ'] ?? 'fahrzeug',
        'bezeichnung' => $_POST['bezeichnung'],
        'rufname' => $_POST['rufname'] ?? null,
        'fahrzeugtyp' => $_POST['fahrzeugtyp'] ?? null,
        'lokalisation' => $_POST['lokalisation'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];

    try {
        $manvRessource->update((int)$_POST['ressource_id'], $data);
        $manvLog->log(
            (int)$lageId,
            'ressource_bearbeitet',
            'Ressource ' . $data['bezeichnung'] . ' wurde bearbeitet',
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null,
            'ressource',
            (int)$_POST['ressource_id']
        );
        header('Location: ressourcen.php?lage_id=' . $lageId);
        exit;
    } catch (Exception $e) {
        $error = 'Fehler beim Bearbeiten: ' . $e->getMessage();
    }
}

// Erstellen einer neuen Ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'manv_lage_id' => (int)$lageId,
        'typ' => $_POST['typ'] ?? 'fahrzeug',
        'bezeichnung' => $_POST['bezeichnung'],
        'rufname' => $_POST['rufname'] ?? null,
        'fahrzeugtyp' => $_POST['fahrzeugtyp'] ?? null,
        'lokalisation' => $_POST['lokalisation'] ?? null,
        'status' => $_POST['status'] ?? 'verfuegbar',
        'besatzung' => $_POST['besatzung'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];

    // Prüfe ob Fahrzeug bereits existiert
    $checkStmt = $pdo->prepare("
        SELECT id, bezeichnung 
        FROM intra_manv_ressourcen 
        WHERE manv_lage_id = ? 
        AND bezeichnung = ?
    ");
    $checkStmt->execute([(int)$lageId, $data['bezeichnung']]);
    $existingFahrzeug = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFahrzeug) {
        $error = 'Das Fahrzeug ' . htmlspecialchars($data['bezeichnung']) . ' wurde bereits zu dieser MANV-Lage hinzugefügt.';
    } else {
        try {
            $ressourceId = $manvRessource->create($data);
            $manvLog->log(
                (int)$lageId,
                'ressource_erstellt',
                'Ressource ' . $data['bezeichnung'] . ' wurde erstellt',
                $_SESSION['user_id'] ?? null,
                $_SESSION['username'] ?? null,
                'ressource',
                $ressourceId
            );
            $success = 'Ressource erfolgreich erstellt.';
        } catch (Exception $e) {
            $error = 'Fehler beim Erstellen: ' . $e->getMessage();
        }
    }
}

// Löschen einer Ressource
if (isset($_GET['delete_id'])) {
    try {
        $manvRessource->delete((int)$_GET['delete_id']);
        $manvLog->log(
            (int)$lageId,
            'ressource_geloescht',
            'Ressource wurde gelöscht',
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null
        );
        header('Location: ressourcen.php?lage_id=' . $lageId);
        exit;
    } catch (Exception $e) {
        $error = 'Fehler beim Löschen: ' . $e->getMessage();
    }
}

// Fahrzeuge aus System laden (nur nicht bereits hinzugefügte)
$systemFahrzeugeStmt = $pdo->prepare("
    SELECT f.id, f.name, f.identifier, f.veh_type 
    FROM intra_fahrzeuge f
    WHERE f.active = 1 
    AND f.name NOT IN (
        SELECT bezeichnung 
        FROM intra_manv_ressourcen 
        WHERE manv_lage_id = ?
    )
    ORDER BY f.priority ASC
");
$systemFahrzeugeStmt->execute([(int)$lageId]);
$systemFahrzeuge = $systemFahrzeugeStmt->fetchAll(PDO::FETCH_ASSOC);

// Benutzer für LNA/OrgL laden
$usersStmt = $pdo->prepare("
    SELECT u.id, COALESCE(m.fullname, u.fullname) as fullname 
    FROM intra_users u 
    LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag 
    WHERE COALESCE(m.fullname, u.fullname) IS NOT NULL
    ORDER BY COALESCE(m.fullname, u.fullname) ASC
");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$fahrzeuge = $manvRessource->getByLage((int)$lageId, 'fahrzeug');
$personal = $manvRessource->getByLage((int)$lageId, 'personal');
$material = $manvRessource->getByLage((int)$lageId, 'material');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Fahrzeugverwaltung - ' . htmlspecialchars($lage['einsatznummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" id="manv-ressourcen">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <hr class="text-light my-3">
            <div class="row mb-5">
                <div class="col-md-8">
                    <h1>Fahrzeugverwaltung</h1>
                    <p class="text-muted">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i>Neues Fahrzeug
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Fahrzeuge -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Fahrzeuge (<?= count($fahrzeuge) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fahrzeuge)): ?>
                        <p class="text-muted">Keine Fahrzeuge vorhanden.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Bezeichnung</th>
                                        <th>Art</th>
                                        <th>Lokalisation</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fahrzeuge as $fzg): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($fzg['bezeichnung']) ?></strong></td>
                                            <td><?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($fzg['lokalisation'] ?? '-') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1 edit-ressource-btn"
                                                    data-id="<?= $fzg['id'] ?>"
                                                    data-typ="<?= htmlspecialchars($fzg['typ']) ?>"
                                                    data-bezeichnung="<?= htmlspecialchars($fzg['bezeichnung']) ?>"
                                                    data-rufname="<?= htmlspecialchars($fzg['rufname'] ?? '') ?>"
                                                    data-fahrzeugtyp="<?= htmlspecialchars($fzg['fahrzeugtyp'] ?? '') ?>"
                                                    data-lokalisation="<?= htmlspecialchars($fzg['lokalisation'] ?? '') ?>"
                                                    data-notizen="<?= htmlspecialchars($fzg['notizen'] ?? '') ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?lage_id=<?= $lageId ?>&delete_id=<?= $fzg['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Wirklich löschen?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-4">
                <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Zurück zum Board
                </a>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="ressource_id" id="edit_ressource_id">
                        <div class="modal-header">
                            <h5 class="modal-title">Ressource bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_typ" class="form-label">Typ</label>
                                <input type="text" class="form-control" id="edit_typ" name="typ" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="edit_bezeichnung" class="form-label">Bezeichnung *</label>
                                <input type="text" class="form-control" id="edit_bezeichnung" name="bezeichnung" required>
                            </div>
                            <div class="mb-3" id="edit_fahrzeugtyp_group">
                                <label for="edit_fahrzeugtyp" class="form-label">Art</label>
                                <input type="text" class="form-control" id="edit_fahrzeugtyp" name="fahrzeugtyp">
                            </div>
                            <div class="mb-3">
                                <label for="edit_lokalisation" class="form-label">Lokalisation an Einsatzstelle</label>
                                <input type="text" class="form-control" id="edit_lokalisation" name="lokalisation">
                            </div>
                            <div class="mb-3">
                                <label for="edit_notizen" class="form-label">Notizen</label>
                                <textarea class="form-control" id="edit_notizen" name="notizen" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content bg-dark">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-header">
                            <h5 class="modal-title">Neue Ressource</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3" style="display: none;">
                                <label for="typ" class="form-label">Typ</label>
                                <input type="hidden" id="typ" name="typ" value="fahrzeug">
                            </div>
                            <div class="mb-3">
                                <label for="fahrzeug_id" class="form-label">Fahrzeug aus System *</label>
                                <select class="form-control" id="fahrzeug_id" name="fahrzeug_id" required>
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($systemFahrzeuge as $fzg): ?>
                                        <option value="<?= $fzg['id'] ?>" data-name="<?= htmlspecialchars($fzg['name']) ?>" data-identifier="<?= htmlspecialchars($fzg['identifier']) ?>" data-type="<?= htmlspecialchars($fzg['veh_type']) ?>">
                                            <?= htmlspecialchars($fzg['identifier']) ?> - <?= htmlspecialchars($fzg['name']) ?> (<?= htmlspecialchars($fzg['veh_type']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="bezeichnung" class="form-label">Bezeichnung *</label>
                                <input type="text" class="form-control" id="bezeichnung" name="bezeichnung" required readonly>
                                <small class="text-muted">Wird automatisch aus Fahrzeugauswahl übernommen</small>
                            </div>
                            <div class="mb-3">
                                <label for="fahrzeugtyp" class="form-label">Art</label>
                                <input type="text" class="form-control" id="fahrzeugtyp" name="fahrzeugtyp" readonly>
                                <small class="text-muted">Wird automatisch aus Fahrzeugauswahl übernommen</small>
                            </div>
                            <div class="mb-3">
                                <label for="lokalisation" class="form-label">Lokalisation an Einsatzstelle</label>
                                <input type="text" class="form-control" id="lokalisation" name="lokalisation">
                            </div>
                            <div class="mb-3">
                                <label for="notizen" class="form-label">Notizen</label>
                                <textarea class="form-control" id="notizen" name="notizen" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    </div>
    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Auto-fill fields when vehicle is selected
        document.getElementById('fahrzeug_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('bezeichnung').value = selectedOption.dataset.name;
                document.getElementById('fahrzeugtyp').value = selectedOption.dataset.type;
            } else {
                document.getElementById('bezeichnung').value = '';
                document.getElementById('fahrzeugtyp').value = '';
            }
        });

        // Edit modal population
        document.querySelectorAll('.edit-ressource-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_ressource_id').value = this.dataset.id;
                document.getElementById('edit_typ').value = this.dataset.typ;
                document.getElementById('edit_bezeichnung').value = this.dataset.bezeichnung;
                document.getElementById('edit_lokalisation').value = this.dataset.lokalisation || '';
                document.getElementById('edit_notizen').value = this.dataset.notizen || '';
                document.getElementById('edit_fahrzeugtyp').value = this.dataset.fahrzeugtyp || '';
            });
        });
    </script>
</body>

</html>