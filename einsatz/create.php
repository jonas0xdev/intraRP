<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

if (!Permissions::check(['admin', 'fire.incident.create'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

require __DIR__ . '/../assets/config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $keyword = trim($_POST['keyword'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $leader_id = !empty($_POST['leader_id']) ? (int)$_POST['leader_id'] : null;
    $incident_number = trim($_POST['incident_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_contact = trim($_POST['owner_contact'] ?? '');

    if ($incident_number === '') $errors[] = 'Einsatznummer ist erforderlich.';
    if ($location === '') $errors[] = 'Einsatzort ist erforderlich.';
    if ($keyword === '') $errors[] = 'Einsatzstichwort ist erforderlich.';
    if ($date === '' || $time === '') $errors[] = 'Datum und Uhrzeit sind erforderlich.';
    if ($leader_id === null) $errors[] = 'Einsatzleiter ist erforderlich.';

    $started_at = null;
    if ($date !== '' && $time !== '') {
        $started_at = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO intra_fire_incidents (incident_number, location, keyword, started_at, leader_id, owner_type, owner_name, owner_contact, notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?, 'in_sichtung', ?)");
            $stmt->execute([
                $incident_number,
                $location,
                $keyword,
                $started_at,
                $leader_id,
                null,
                $owner_name ?: null,
                $owner_contact ?: null,
                $notes ?: null,
                $_SESSION['userid']
            ]);

            $incidentId = (int)$pdo->lastInsertId();
            Flash::success('Einsatz wurde erstellt.');
            header('Location: ' . BASE_PATH . 'einsatz/view.php?id=' . $incidentId);
            exit();
        } catch (PDOException $e) {
            $errors[] = 'Fehler beim Speichern: ' . $e->getMessage();
        }
    }
}

// Fetch leaders (Mitarbeiter)
$leaders = [];
try {
    $stmt = $pdo->query("SELECT id, fullname FROM intra_mitarbeiter ORDER BY fullname ASC");
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ignore for now
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
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="protokolle">
    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <div class="sidebar-nav" style="width: 250px; min-height: 100vh; background-color: #1a1a1a; border-right: 1px solid #333;">
            <div class="p-3">
                <h5 class="text-white mb-4">Einsatz-System</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="<?= BASE_PATH ?>einsatz/create.php">
                            <i class="fa-solid fa-plus me-2"></i>Einsatz anlegen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>einsatz/admin/list.php">
                            <i class="fa-solid fa-list me-2"></i>Einsatzliste
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-white" href="<?= BASE_PATH ?>index.php">
                            <i class="fa-solid fa-arrow-left me-2"></i>Zurück
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1" style="overflow-y: auto;">
            <div class="container my-4">
                <h1>Feuerwehr-Einsatz anlegen</h1>
                <?php App\Helpers\Flash::render(); ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="intra__tile p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Einsatznummer*</label>
                            <input type="text" name="incident_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzort*</label>
                            <input type="text" name="location" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzstichwort*</label>
                            <input type="text" name="keyword" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Datum*</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Uhrzeit*</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Einsatzleiter*</label>
                            <select name="leader_id" class="form-select" required>
                                <option value="">– auswählen –</option>
                                <?php foreach ($leaders as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Name</label>
                            <input type="text" name="owner_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Geschädigter/Eigentümer/Halter – Kontakt</label>
                            <input type="text" name="owner_contact" class="form-control">
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Optional: Angaben zum Geschädigten, Eigentümer oder Halter (Name/Kontakt).</small>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Einsatzgeschehen</label>
                            <textarea name="notes" class="form-control" rows="5" placeholder="Optional: Kurzbeschreibung des Einsatzgeschehens..."></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <a href="<?= BASE_PATH ?>index.php" class="btn btn-secondary me-2">Abbrechen</a>
                        <button type="submit" class="btn btn-primary">Einsatz erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>