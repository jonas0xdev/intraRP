<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

require __DIR__ . '/../assets/config/database.php';

// Handle logout from vehicle
if (isset($_GET['logout'])) {
    unset($_SESSION['einsatz_vehicle_id']);
    unset($_SESSION['einsatz_vehicle_name']);
    unset($_SESSION['einsatz_operator_id']);
    unset($_SESSION['einsatz_operator_name']);
    Flash::success('Von Fahrzeug abgemeldet.');
    header('Location: ' . BASE_PATH . 'einsatz/login-fahrzeug.php');
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
    $operator_id = !empty($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;

    if ($vehicle_id <= 0) {
        Flash::error('Bitte wählen Sie ein Fahrzeug aus.');
    } elseif ($operator_id <= 0) {
        Flash::error('Bitte wählen Sie einen Mitarbeiter aus.');
    } else {
        try {
            // Get vehicle info
            $stmt = $pdo->prepare("SELECT id, name, identifier FROM intra_fahrzeuge WHERE id = ? AND active = 1 AND rd_type = 3");
            $stmt->execute([$vehicle_id]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get operator info
            $stmt = $pdo->prepare("SELECT id, fullname FROM intra_mitarbeiter WHERE id = ?");
            $stmt->execute([$operator_id]);
            $operator = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$vehicle) {
                Flash::error('Fahrzeug nicht gefunden oder nicht verfügbar.');
            } elseif (!$operator) {
                Flash::error('Mitarbeiter nicht gefunden.');
            } else {
                // Store in session
                $_SESSION['einsatz_vehicle_id'] = (int)$vehicle['id'];
                $_SESSION['einsatz_vehicle_name'] = $vehicle['name'] . ($vehicle['identifier'] ? ' (' . $vehicle['identifier'] . ')' : '');
                $_SESSION['einsatz_operator_id'] = (int)$operator['id'];
                $_SESSION['einsatz_operator_name'] = $operator['fullname'];

                Flash::success('Erfolgreich auf ' . $_SESSION['einsatz_vehicle_name'] . ' angemeldet als ' . $_SESSION['einsatz_operator_name']);
                header('Location: ' . BASE_PATH . 'einsatz/list.php');
                exit();
            }
        } catch (PDOException $e) {
            Flash::error('Fehler: ' . $e->getMessage());
        }
    }
}

// Load available vehicles (rd_type = 3)
$vehicles = [];
try {
    $vehicles = $pdo->query("SELECT id, name, identifier, rd_type FROM intra_fahrzeuge WHERE active = 1 AND rd_type = 3 ORDER BY priority ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden der Fahrzeuge.');
}

// Load all personnel
$personnel = [];
try {
    $personnel = $pdo->query("SELECT id, fullname FROM intra_mitarbeiter ORDER BY fullname ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Flash::error('Fehler beim Laden der Mitarbeiter.');
}
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/enotf-custom-dropdown.css">
    
    <style>
        .login-container {
            max-width: 600px;
            margin: 100px auto;
        }

        .vehicle-card {
            transition: all 0.2s;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .vehicle-card:hover {
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .vehicle-card.selected {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
        }
    </style>
</head>

<body data-bs-theme="dark">
    <div class="container">
        <div class="login-container">
            <?php if (isset($_SESSION['einsatz_vehicle_id'])): ?>
                <!-- Already logged in -->
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-4">
                            <i class="fa-solid fa-truck text-primary me-2"></i>
                            Angemeldet
                        </h3>
                        <div class="alert alert-success">
                            <strong>Fahrzeug:</strong> <?= htmlspecialchars($_SESSION['einsatz_vehicle_name']) ?><br>
                            <strong>Besatzung:</strong> <?= htmlspecialchars($_SESSION['einsatz_operator_name']) ?>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="<?= BASE_PATH ?>einsatz/list.php" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-list me-2"></i>Zur Einsatzliste
                            </a>
                            <a href="<?= BASE_PATH ?>einsatz/login-fahrzeug.php?logout=1" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-sign-out-alt me-2"></i>Abmelden
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Login form -->
                <div class="card">
                    <div class="card-body">
                        <h3 class="text-center mb-4">
                            <i class="fa-solid fa-truck me-2"></i>
                            Fahrzeug-Anmeldung
                        </h3>
                        <p class="text-muted text-center mb-4">
                            Bitte melden Sie sich auf einem Fahrzeug an, um Einsätze zu erstellen oder anzuzeigen.
                        </p>

                        <?php App\Helpers\Flash::render(); ?>

                        <?php if (empty($vehicles)): ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                Keine Einsatzfahrzeuge verfügbar.
                            </div>
                        <?php elseif (empty($personnel)): ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                Keine Mitarbeiter hinterlegt.
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fa-solid fa-truck me-1"></i>
                                        Fahrzeug auswählen *
                                    </label>
                                    <select name="vehicle_id" id="vehicleSelect" class="form-select form-select-lg" required data-custom-dropdown="true" data-search-threshold="5">
                                        <option value="">-- Bitte Fahrzeug wählen --</option>
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?= (int)$v['id'] ?>">
                                                <?= htmlspecialchars($v['name']) ?>
                                                <?php if ($v['identifier']): ?>
                                                    (<?= htmlspecialchars($v['identifier']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fa-solid fa-user me-1"></i>
                                        Besatzung / Name *
                                    </label>
                                    <select name="operator_id" id="operatorSelect" class="form-select form-select-lg" required data-custom-dropdown="true" data-search-threshold="5">
                                        <option value="">-- Bitte Mitarbeiter wählen --</option>
                                        <?php foreach ($personnel as $p): ?>
                                            <option value="<?= (int)$p['id'] ?>">
                                                <?= htmlspecialchars($p['fullname']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Wählen Sie Ihren Namen aus der Liste</small>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fa-solid fa-sign-in-alt me-2"></i>
                                        Anmelden
                                    </button>
                                    <a href="<?= BASE_PATH ?>index.php" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-arrow-left me-2"></i>
                                        Zurück
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>assets/js/enotf-custom-dropdown.js"></script>
    <script>
        // Initialize custom dropdowns when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                eNotfCustomDropdown.init();
            });
        } else {
            eNotfCustomDropdown.init();
        }
    </script>
</body>

</html>