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

// Get filter parameters
$typeFilter = $_GET['type'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';

// Build query
$sql = "SELECT kb.*, 
        creator.fullname as creator_name, 
        updater.fullname as updater_name
        FROM intra_kb_entries kb
        LEFT JOIN intra_users creator ON kb.created_by = creator.id
        LEFT JOIN intra_users updater ON kb.updated_by = updater.id
        WHERE 1=1";

$params = [];

if (!$showArchived) {
    $sql .= " AND kb.is_archived = 0";
}

if ($typeFilter !== 'all') {
    $sql .= " AND kb.type = :type";
    $params['type'] = $typeFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (kb.title LIKE :search OR kb.subtitle LIKE :search OR kb.content LIKE :search)";
    $params['search'] = '%' . $searchQuery . '%';
}

$sql .= " ORDER BY kb.updated_at DESC, kb.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for competency level colors and labels
function getCompetencyInfo($level) {
    $competencies = [
        'basis' => ['label' => 'Basis', 'color' => '#808080', 'bg' => '#f0f0f0'],
        'rettsan' => ['label' => 'RettSan', 'color' => '#dc0000', 'bg' => '#ffcccc'],
        'notsan_2c' => ['label' => 'NotSan 2c', 'color' => '#ffffff', 'bg' => '#f7b500', 'text' => '#000000'],
        'notsan_2a' => ['label' => 'NotSan 2a', 'color' => '#ffffff', 'bg' => '#00a651'],
        'notarzt' => ['label' => 'Notarzt', 'color' => '#ffffff', 'bg' => '#dc0000']
    ];
    return $competencies[$level] ?? null;
}

// Helper for type labels
function getTypeLabel($type) {
    $types = [
        'general' => 'Allgemein',
        'medication' => 'Medikament',
        'measure' => 'Maßnahme'
    ];
    return $types[$type] ?? $type;
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'Wissensdatenbank';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
    <style>
        .competency-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .kb-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .kb-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .kb-type-badge {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        .kb-archived {
            opacity: 0.6;
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="mb-0">Wissensdatenbank</h1>
                        <?php if ($isLoggedIn && Permissions::check(['admin', 'kb.edit'])): ?>
                            <a href="<?= BASE_PATH ?>wissensdb/create.php" class="btn btn-success">
                                <i class="fa-solid fa-plus"></i> Neuer Eintrag
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php Flash::render(); ?>

                    <!-- Filter Section -->
                    <div class="intra__tile py-3 px-4 mb-4">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Kategorie</label>
                                <select name="type" id="type" class="form-select">
                                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Alle Kategorien</option>
                                    <option value="general" <?= $typeFilter === 'general' ? 'selected' : '' ?>>Allgemein</option>
                                    <option value="medication" <?= $typeFilter === 'medication' ? 'selected' : '' ?>>Medikamente</option>
                                    <option value="measure" <?= $typeFilter === 'measure' ? 'selected' : '' ?>>Maßnahmen</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="search" class="form-label">Suche</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Titel, Beschreibung..." value="<?= htmlspecialchars($searchQuery) ?>">
                            </div>
                            <?php if ($isLoggedIn && Permissions::check(['admin', 'kb.archive'])): ?>
                                <div class="col-md-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="archived" value="1" 
                                               id="showArchived" <?= $showArchived ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="showArchived">Archiviert</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa-solid fa-search"></i> Filtern
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Entries Grid -->
                    <?php if (empty($entries)): ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-info-circle"></i> Keine Einträge gefunden.
                            <?php if ($isLoggedIn && Permissions::check(['admin', 'kb.edit'])): ?>
                                <a href="<?= BASE_PATH ?>wissensdb/create.php">Erstellen Sie den ersten Eintrag.</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($entries as $entry): 
                                $competency = getCompetencyInfo($entry['competency_level']);
                            ?>
                                <div class="col">
                                    <a href="<?= BASE_PATH ?>wissensdb/view.php?id=<?= $entry['id'] ?>" class="text-decoration-none">
                                        <div class="card h-100 kb-card <?= $entry['is_archived'] ? 'kb-archived' : '' ?>">
                                            <?php if ($competency): ?>
                                                <div class="card-header p-2" style="background-color: <?= $competency['bg'] ?>; border-bottom: 3px solid <?= $competency['color'] === '#ffffff' ? $competency['bg'] : $competency['color'] ?>;">
                                                    <span class="competency-badge" style="background-color: <?= $competency['bg'] ?>; color: <?= $competency['text'] ?? $competency['color'] ?>;">
                                                        <?= $competency['label'] ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-secondary kb-type-badge"><?= getTypeLabel($entry['type']) ?></span>
                                                    <?php if ($entry['is_archived']): ?>
                                                        <span class="badge bg-warning text-dark">Archiviert</span>
                                                    <?php endif; ?>
                                                </div>
                                                <h5 class="card-title"><?= htmlspecialchars($entry['title']) ?></h5>
                                                <?php if (!empty($entry['subtitle'])): ?>
                                                    <p class="card-text text-muted small"><?= htmlspecialchars($entry['subtitle']) ?></p>
                                                <?php endif; ?>
                                                
                                                <?php if ($entry['type'] === 'medication' && !empty($entry['med_wirkstoffgruppe'])): ?>
                                                    <p class="card-text small"><strong>Wirkstoffgruppe:</strong> <?= htmlspecialchars($entry['med_wirkstoffgruppe']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <small class="text-muted">
                                                    <?php if ($entry['updated_at']): ?>
                                                        Aktualisiert: <?= date('d.m.Y H:i', strtotime($entry['updated_at'])) ?>
                                                        <?php if ($entry['updater_name']): ?>
                                                            von <?= htmlspecialchars($entry['updater_name']) ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        Erstellt: <?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?>
                                                        <?php if ($entry['creator_name']): ?>
                                                            von <?= htmlspecialchars($entry['creator_name']) ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>
