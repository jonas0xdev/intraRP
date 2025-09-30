<?php
session_start();
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "admin/login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "admin/index.php");
    exit();
}

// Toggle Aktivierungsstatus
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE intra_antrag_typen SET aktiv = NOT aktiv WHERE id = ?");
    $stmt->execute([$id]);
    Flash::set('success', 'Status erfolgreich geändert');
    header("Location: " . BASE_PATH . "admin/settings/antraege/list.php");
    exit();
}

// Antragstyp löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Prüfen ob Anträge existieren
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_antraege WHERE antragstyp_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    // Prüfen ob es Beförderungsanträge gibt (alte Tabelle)
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM intra_antrag_bef WHERE 1");
    $stmt2->execute();
    $count_bef = $stmt2->fetchColumn();

    $stmt3 = $pdo->prepare("SELECT tabelle_name FROM intra_antrag_typen WHERE id = ?");
    $stmt3->execute([$id]);
    $tabelle = $stmt3->fetchColumn();

    if ($tabelle === 'intra_antrag_bef' && $count_bef > 0) {
        Flash::set('error', 'Dieser Antragstyp kann nicht gelöscht werden, da noch ' . $count_bef . ' Beförderungsanträge existieren.');
    } elseif ($count > 0) {
        Flash::set('error', 'Dieser Antragstyp kann nicht gelöscht werden, da noch ' . $count . ' Anträge existieren.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM intra_antrag_typen WHERE id = ?");
        $stmt->execute([$id]);
        Flash::set('success', 'Antragstyp erfolgreich gelöscht');
    }

    header("Location: " . BASE_PATH . "admin/settings/antraege/list.php");
    exit();
}

// Sortierung aktualisieren
if (isset($_POST['update_sortierung'])) {
    $sortierungen = $_POST['sortierung'] ?? [];
    foreach ($sortierungen as $id => $sort) {
        $stmt = $pdo->prepare("UPDATE intra_antrag_typen SET sortierung = ? WHERE id = ?");
        $stmt->execute([$sort, $id]);
    }
    Flash::set('success', 'Sortierung aktualisiert');
    header("Location: " . BASE_PATH . "admin/settings/antraege/list.php");
    exit();
}

// Antragstypen laden
$stmt = $pdo->prepare("
    SELECT 
        at.*,
        COUNT(DISTINCT af.id) as anzahl_felder,
        CASE 
            WHEN at.tabelle_name = 'intra_antrag_bef' THEN (SELECT COUNT(*) FROM intra_antrag_bef)
            ELSE (SELECT COUNT(*) FROM intra_antraege WHERE antragstyp_id = at.id)
        END as anzahl_antraege
    FROM intra_antrag_typen at
    LEFT JOIN intra_antrag_felder af ON at.id = af.antragstyp_id
    GROUP BY at.id
    ORDER BY at.sortierung ASC, at.name ASC
");
$stmt->execute();
$typen = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Antragstypen Verwaltung &rsaquo; <?php echo SYSTEM_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/admin.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/lineawesome/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/mavenpro/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="<?= BASE_PATH ?>vendor/components/jquery/jquery.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <meta name="theme-color" content="<?php echo SYSTEM_COLOR ?>" />
</head>

<body data-bs-theme="dark" data-page="antragstypen">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1><i class="las la-clipboard-list me-2"></i>Antragstypen verwalten</h1>
                        <a href="<?= BASE_PATH ?>admin/settings/antraege/create.php" class="btn btn-success">
                            <i class="las la-plus me-2"></i>Neuer Antragstyp
                        </a>
                    </div>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <?php if (empty($typen)): ?>
                        <div class="alert alert-info">
                            <i class="las la-info-circle me-2"></i>
                            Noch keine Antragstypen vorhanden. Erstellen Sie jetzt Ihren ersten Antragstyp!
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <div class="intra__tile p-3">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 60px;">Sort.</th>
                                                <th style="width: 60px;">Icon</th>
                                                <th>Name</th>
                                                <th>Beschreibung</th>
                                                <th style="width: 100px;" class="text-center">Felder</th>
                                                <th style="width: 100px;" class="text-center">Anträge</th>
                                                <th style="width: 100px;" class="text-center">Status</th>
                                                <th style="width: 200px;" class="text-end">Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($typen as $typ): ?>
                                                <tr>
                                                    <td>
                                                        <input type="number"
                                                            name="sortierung[<?= $typ['id'] ?>]"
                                                            value="<?= $typ['sortierung'] ?>"
                                                            class="form-control form-control-sm"
                                                            style="width: 60px;">
                                                    </td>
                                                    <td class="text-center">
                                                        <i class="<?= htmlspecialchars($typ['icon']) ?> fs-4"></i>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($typ['name']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars(substr($typ['beschreibung'] ?? '', 0, 80)) ?>
                                                            <?= strlen($typ['beschreibung'] ?? '') > 80 ? '...' : '' ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?= $typ['anzahl_felder'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary"><?= $typ['anzahl_antraege'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($typ['aktiv']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="las la-check"></i> Aktiv
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                <i class="las la-times"></i> Inaktiv
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="<?= BASE_PATH ?>admin/settings/antraege/edit.php?id=<?= $typ['id'] ?>"
                                                                class="btn btn-primary mx-1"
                                                                title="Bearbeiten">
                                                                <i class="las la-edit"></i>
                                                            </a>
                                                            <a href="?toggle=<?= $typ['id'] ?>"
                                                                class="btn btn-warning mx-1"
                                                                title="<?= $typ['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                                <i class="las la-power-off"></i>
                                                            </a>
                                                            <?php if ($typ['anzahl_antraege'] == 0): ?>
                                                                <a href="?delete=<?= $typ['id'] ?>"
                                                                    class="btn btn-danger mx-1"
                                                                    title="Löschen"
                                                                    onclick="return confirm('Antragstyp wirklich löschen?')">
                                                                    <i class="las la-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" name="update_sortierung" class="btn btn-primary">
                                        <i class="las la-save me-2"></i>Sortierung speichern
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="<?= BASE_PATH ?>admin/antraege/list.php" class="btn btn-secondary">
                            <i class="las la-arrow-left me-2"></i>Zurück zur Antragsübersicht
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>