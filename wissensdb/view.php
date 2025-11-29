<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

// Check if public access is enabled or user is logged in
$publicAccess = defined('KB_PUBLIC_ACCESS') && KB_PUBLIC_ACCESS === true;
$isLoggedIn = isset($_SESSION['userid']) && isset($_SESSION['permissions']);

if (!$publicAccess && !$isLoggedIn) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

// Get entry ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    Flash::error('Ungültige ID');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Fetch entry
$stmt = $pdo->prepare("
    SELECT kb.*, 
           creator.fullname as creator_name,
           updater.fullname as updater_name
    FROM intra_kb_entries kb
    LEFT JOIN intra_users creator ON kb.created_by = creator.id
    LEFT JOIN intra_users updater ON kb.updated_by = updater.id
    WHERE kb.id = :id
");
$stmt->execute(['id' => $id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    Flash::error('Eintrag nicht gefunden');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Don't show archived entries to non-privileged users
if ($entry['is_archived'] && (!$isLoggedIn || !Permissions::check(['admin', 'kb.archive']))) {
    Flash::error('Dieser Eintrag ist archiviert');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Helper function for competency level colors and labels
function getCompetencyInfo($level) {
    $competencies = [
        'basis' => ['label' => 'Basis', 'color' => '#808080', 'bg' => '#f0f0f0', 'text' => '#333333', 'desc' => 'Basismaßnahmen; durch jedes Rettungsdienstpersonal ausführbar'],
        'rettsan' => ['label' => 'RettSan', 'color' => '#dc0000', 'bg' => '#ffcccc', 'text' => '#dc0000', 'desc' => 'Durchführung durch RettSan bei Hinzuziehung/Nachsicht eines Arztes'],
        'notsan_2c' => ['label' => 'NotSan 2c', 'color' => '#f7b500', 'bg' => '#fff3cd', 'text' => '#000000', 'desc' => 'Eigenständige Durchführung durch NotSan im Rahmen § 4 Abs. 2c NotSanG'],
        'notsan_2a' => ['label' => 'NotSan 2a', 'color' => '#00a651', 'bg' => '#d4edda', 'text' => '#ffffff', 'desc' => 'Eigenverantwortliche Durchführung durch NotSan im Rahmen § 2a NotSanG'],
        'notarzt' => ['label' => 'Notarzt', 'color' => '#dc0000', 'bg' => '#f8d7da', 'text' => '#ffffff', 'desc' => 'Durchführung nur durch Notärzte vorgesehen']
    ];
    return $competencies[$level] ?? null;
}

function getTypeLabel($type) {
    $types = [
        'general' => 'Allgemein',
        'medication' => 'Medikament',
        'measure' => 'Maßnahme'
    ];
    return $types[$type] ?? $type;
}

$competency = getCompetencyInfo($entry['competency_level']);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = htmlspecialchars($entry['title']) . ' - Wissensdatenbank';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
    <style>
        .competency-header {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .competency-label {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .kb-section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
        }
        .kb-section-yellow {
            background-color: #fff3cd;
            border-left: 4px solid #f7b500;
        }
        .kb-section-blue {
            background-color: #cce5ff;
            border-left: 4px solid #004085;
        }
        .kb-section-red {
            background-color: #f8d7da;
            border: 2px solid #dc0000;
        }
        .kb-section-gray {
            background-color: #f8f9fa;
            border-left: 4px solid #6c757d;
        }
        .kb-section h5 {
            margin-bottom: 10px;
            font-weight: bold;
        }
        .edit-info {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        .measure-row {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .measure-row:last-child {
            border-bottom: none;
        }
        .measure-icon {
            width: 50px;
            text-align: center;
            font-size: 1.5rem;
        }
        .measure-label {
            width: 180px;
            font-weight: bold;
        }
        .measure-content {
            flex: 1;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="wissensdb">
    <?php if ($isLoggedIn): ?>
        <?php include __DIR__ . "/../assets/components/navbar.php"; ?>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
            <div class="container">
                <a class="navbar-brand" href="<?= BASE_PATH ?>">
                    <img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:48px;width:auto">
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="<?= BASE_PATH ?>login.php">Anmelden</a>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    
                    <!-- Breadcrumb and Actions -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>wissensdb/index.php">Wissensdatenbank</a></li>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($entry['title']) ?></li>
                            </ol>
                        </nav>
                        <?php if ($isLoggedIn): ?>
                            <div>
                                <?php if (Permissions::check(['admin', 'kb.edit'])): ?>
                                    <a href="<?= BASE_PATH ?>wissensdb/edit.php?id=<?= $entry['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-edit"></i> Bearbeiten
                                    </a>
                                <?php endif; ?>
                                <?php if (Permissions::check(['admin', 'kb.archive'])): ?>
                                    <?php if ($entry['is_archived']): ?>
                                        <form method="POST" action="<?= BASE_PATH ?>wissensdb/archive.php" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fa-solid fa-rotate-left"></i> Wiederherstellen
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= BASE_PATH ?>wissensdb/archive.php" class="d-inline">
                                            <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <i class="fa-solid fa-archive"></i> Archivieren
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($entry['is_archived']): ?>
                        <div class="alert alert-warning">
                            <i class="fa-solid fa-archive"></i> Dieser Eintrag ist archiviert.
                        </div>
                    <?php endif; ?>

                    <!-- Entry Content -->
                    <div class="intra__tile p-4">
                        <!-- Header with Title and Competency -->
                        <?php if ($competency): ?>
                            <div class="competency-header" style="background-color: <?= $competency['bg'] ?>; color: <?= $competency['text'] ?>;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge mb-2" style="background-color: <?= $entry['type'] === 'medication' ? '#17a2b8' : ($entry['type'] === 'measure' ? '#28a745' : '#6c757d') ?>">
                                            <?= getTypeLabel($entry['type']) ?>
                                        </span>
                                        <h2 class="mb-1"><?= htmlspecialchars($entry['title']) ?></h2>
                                        <?php if (!empty($entry['subtitle'])): ?>
                                            <p class="mb-0 opacity-75"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="competency-label p-2 rounded" style="background-color: <?= $competency['color'] ?>; color: <?= $competency['color'] === '#f7b500' ? '#000' : '#fff' ?>;">
                                            Freigabe: <?= $competency['label'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <span class="badge mb-2" style="background-color: <?= $entry['type'] === 'medication' ? '#17a2b8' : ($entry['type'] === 'measure' ? '#28a745' : '#6c757d') ?>">
                                    <?= getTypeLabel($entry['type']) ?>
                                </span>
                                <h2 class="mb-1"><?= htmlspecialchars($entry['title']) ?></h2>
                                <?php if (!empty($entry['subtitle'])): ?>
                                    <p class="text-muted"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($entry['type'] === 'medication'): ?>
                            <!-- Medication Layout -->
                            <div class="row">
                                <div class="col-12">
                                    <!-- Basic Info Table -->
                                    <table class="table table-bordered mb-4">
                                        <tbody>
                                            <?php if (!empty($entry['med_wirkstoff'])): ?>
                                                <tr>
                                                    <th style="width: 180px;">Wirkstoff:</th>
                                                    <td><?= htmlspecialchars($entry['med_wirkstoff']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['med_wirkstoffgruppe'])): ?>
                                                <tr>
                                                    <th>Wirkstoffgruppe:</th>
                                                    <td><?= htmlspecialchars($entry['med_wirkstoffgruppe']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['med_wirkmechanismus'])): ?>
                                                <tr>
                                                    <th>Wirkmechanismus:</th>
                                                    <td><?= nl2br(htmlspecialchars($entry['med_wirkmechanismus'])) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>

                                    <?php if (!empty($entry['med_indikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <h5>Indikationen:</h5>
                                            <div><?= nl2br(htmlspecialchars($entry['med_indikationen'])) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_kontraindikationen'])): ?>
                                        <div class="kb-section kb-section-yellow">
                                            <h5>Kontraindikationen:</h5>
                                            <div><?= nl2br(htmlspecialchars($entry['med_kontraindikationen'])) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_uaw'])): ?>
                                        <div class="kb-section kb-section-gray">
                                            <h5>Unerwünschte Arzneimittelwirkungen (UAW):</h5>
                                            <div><?= nl2br(htmlspecialchars($entry['med_uaw'])) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_dosierung'])): ?>
                                        <div class="kb-section kb-section-blue">
                                            <h5>Dosierung:</h5>
                                            <div><?= nl2br(htmlspecialchars($entry['med_dosierung'])) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['med_besonderheiten'])): ?>
                                        <div class="kb-section kb-section-red">
                                            <h5>Besonderheiten / CAVE:</h5>
                                            <div><?= nl2br(htmlspecialchars($entry['med_besonderheiten'])) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif ($entry['type'] === 'measure'): ?>
                            <!-- Measure Layout -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="border rounded">
                                        <?php if (!empty($entry['mass_wirkprinzip'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-lightbulb text-warning"></i></div>
                                                <div class="measure-label">Wirkprinzip</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_wirkprinzip'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($entry['mass_indikationen'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-check text-success"></i></div>
                                                <div class="measure-label">Indikationen</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_indikationen'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($entry['mass_kontraindikationen'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-xmark text-danger"></i></div>
                                                <div class="measure-label">Kontraindikationen</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_kontraindikationen'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($entry['mass_risiken'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-face-frown text-secondary"></i></div>
                                                <div class="measure-label">Risiken</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_risiken'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($entry['mass_alternativen'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-arrows-left-right text-info"></i></div>
                                                <div class="measure-label">Alternativen</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_alternativen'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($entry['mass_durchfuehrung'])): ?>
                                            <div class="measure-row">
                                                <div class="measure-icon"><i class="fa-solid fa-screwdriver-wrench text-primary"></i></div>
                                                <div class="measure-label">Durchführung</div>
                                                <div class="measure-content"><?= nl2br(htmlspecialchars($entry['mass_durchfuehrung'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- General Content (for all types) -->
                        <?php if (!empty($entry['content'])): ?>
                            <div class="mt-4">
                                <?php if ($entry['type'] !== 'general'): ?>
                                    <h5>Weitere Informationen:</h5>
                                <?php endif; ?>
                                <div class="content-area">
                                    <?= $entry['content'] ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Edit Info -->
                        <hr class="my-4">
                        <div class="edit-info text-muted">
                            <i class="fa-solid fa-clock"></i>
                            Erstellt am <?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?>
                            <?php if ($entry['creator_name']): ?>
                                von <?= htmlspecialchars($entry['creator_name']) ?>
                            <?php endif; ?>
                            <?php if ($entry['updated_at']): ?>
                                <br>
                                <i class="fa-solid fa-edit"></i>
                                Zuletzt bearbeitet am <?= date('d.m.Y H:i', strtotime($entry['updated_at'])) ?>
                                <?php if ($entry['updater_name']): ?>
                                    von <?= htmlspecialchars($entry['updater_name']) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>
