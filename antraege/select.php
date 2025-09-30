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

// Alle aktiven Antragstypen laden
$stmt = $pdo->prepare("
    SELECT 
        at.*,
        COUNT(af.id) as anzahl_felder
    FROM intra_antrag_typen at
    LEFT JOIN intra_antrag_felder af ON at.id = af.antragstyp_id
    WHERE at.aktiv = 1
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
    <title>Antrag stellen &rsaquo; <?php echo SYSTEM_NAME ?></title>
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
        .antrag-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid rgba(var(--bs-light-rgb), 0.1);
            background: rgba(var(--bs-dark-rgb), 0.5);
        }

        .antrag-card:hover {
            transform: translateY(-5px);
            border-color: var(--bs-primary);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .antrag-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--bs-primary);
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="antrag-select">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <h1><i class="las la-file-medical me-2"></i>Neuen Antrag stellen</h1>
                    <p class="text-muted">Wählen Sie den gewünschten Antragstyp aus</p>

                    <?php Flash::render(); ?>

                    <hr class="text-light my-3">

                    <?php if (empty($typen)): ?>
                        <div class="alert alert-info">
                            <i class="las la-info-circle me-2"></i>
                            Aktuell sind keine Antragstypen verfügbar.
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($typen as $typ): ?>
                                <div class="col-md-6 col-lg-4">
                                    <a href="<?= BASE_PATH . 'antraege/create.php?typ=' . $typ['id'] ?>"
                                        class="text-decoration-none">
                                        <div class="antrag-card p-4 rounded text-center h-100">
                                            <div class="antrag-icon">
                                                <i class="<?= htmlspecialchars($typ['icon']) ?>"></i>
                                            </div>
                                            <h4 class="mb-3"><?= htmlspecialchars($typ['name']) ?></h4>

                                            <?php if (!empty($typ['beschreibung'])): ?>
                                                <p class="text-muted small mb-3">
                                                    <?= htmlspecialchars($typ['beschreibung']) ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="mt-3">
                                                <button class="btn btn-primary btn-sm">
                                                    <i class="las la-arrow-right me-1"></i>
                                                    Antrag stellen
                                                </button>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="<?= BASE_PATH ?>admin/index.php" class="btn btn-secondary">
                            <i class="las la-arrow-left me-2"></i>Zurück zum Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>