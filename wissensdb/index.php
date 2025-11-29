<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\KnowledgeBase\KBHelper;

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

// Build query with names from linked Discord profiles
$sql = "SELECT kb.*, 
        COALESCE(creator_m.fullname, creator.fullname) as creator_name, 
        COALESCE(updater_m.fullname, updater.fullname) as updater_name
        FROM intra_kb_entries kb
        LEFT JOIN intra_users creator ON kb.created_by = creator.id
        LEFT JOIN intra_mitarbeiter creator_m ON creator.discord_id = creator_m.discordtag
        LEFT JOIN intra_users updater ON kb.updated_by = updater.id
        LEFT JOIN intra_mitarbeiter updater_m ON updater.discord_id = updater_m.discordtag
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
    $sql .= " AND (kb.title LIKE :search1 OR kb.subtitle LIKE :search2 OR kb.content LIKE :search3)";
    $searchParam = '%' . $searchQuery . '%';
    $params['search1'] = $searchParam;
    $params['search2'] = $searchParam;
    $params['search3'] = $searchParam;
}

// Order by pinned first, then by update/creation date
$sql .= " ORDER BY kb.is_pinned DESC, kb.updated_at DESC, kb.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            background-color: rgba(255,255,255,0.05);
            border: 1px solid #444;
        }
        .kb-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            background-color: rgba(255,255,255,0.08);
        }
        .kb-type-badge {
            font-size: 0.7rem;
        }
        .kb-archived {
            opacity: 0.6;
        }
        /* Card styling for dark theme */
        .kb-card .card-body {
            color: #e0e0e0;
            background-color: transparent;
        }
        .kb-card .card-title {
            color: #ffffff;
        }
        .kb-card .card-text {
            color: #aaaaaa;
        }
        .kb-card .card-footer {
            color: #888888;
            background-color: rgba(255,255,255,0.03);
            border-top: 1px solid #444;
        }
        .kb-card .card-header {
            color: #ffffff;
            border-bottom: none;
        }
        /* Quick action buttons on cards */
        .kb-card-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10;
        }
        .col-card-wrapper:hover .kb-card-actions {
            opacity: 1;
        }
        .kb-quick-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s ease;
        }
        .kb-quick-btn:hover {
            transform: scale(1.15);
        }
        .kb-quick-btn-edit {
            background-color: #0d6efd;
            color: #fff;
        }
        .kb-quick-btn-pin {
            background-color: #0dcaf0;
            color: #000;
        }
        .kb-quick-btn-unpin {
            background-color: #6c757d;
            color: #fff;
        }
        .col-card-wrapper {
            position: relative;
        }
        /* Search autocomplete styling */
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: #2d2d2d;
            border: 1px solid #444;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .search-suggestions.active {
            display: block;
        }
        .search-suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #444;
            color: #e0e0e0;
            text-decoration: none;
            display: block;
        }
        .search-suggestion-item:last-child {
            border-bottom: none;
        }
        .search-suggestion-item:hover {
            background-color: rgba(255,255,255,0.1);
            color: #ffffff;
        }
        .search-suggestion-title {
            font-weight: bold;
            color: #ffffff;
        }
        .search-suggestion-subtitle {
            font-size: 0.85rem;
            color: #aaaaaa;
        }
        .search-suggestion-meta {
            display: flex;
            gap: 8px;
            margin-top: 4px;
        }
        .search-suggestion-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 3px;
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
                                <div class="position-relative">
                                    <input type="text" name="search" id="search" class="form-control" 
                                           placeholder="Titel, Beschreibung..." value="<?= htmlspecialchars($searchQuery) ?>"
                                           autocomplete="off">
                                    <div id="search-suggestions" class="search-suggestions"></div>
                                </div>
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
                                $competency = KBHelper::getCompetencyInfo($entry['competency_level']);
                            ?>
                                <div class="col">
                                    <div class="col-card-wrapper">
                                        <?php if ($isLoggedIn && Permissions::check(['admin', 'kb.edit'])): ?>
                                            <!-- Quick Action Buttons -->
                                            <div class="kb-card-actions">
                                                <form method="POST" action="<?= BASE_PATH ?>wissensdb/pin.php" style="margin: 0;" onclick="event.stopPropagation();">
                                                    <input type="hidden" name="id" value="<?= $entry['id'] ?>">
                                                    <input type="hidden" name="action" value="<?= !empty($entry['is_pinned']) ? 'unpin' : 'pin' ?>">
                                                    <button type="submit" class="kb-quick-btn <?= !empty($entry['is_pinned']) ? 'kb-quick-btn-unpin' : 'kb-quick-btn-pin' ?>" 
                                                            title="<?= !empty($entry['is_pinned']) ? 'Lösen' : 'Anpinnen' ?>">
                                                        <i class="fa-solid fa-thumbtack"></i>
                                                    </button>
                                                </form>
                                                <a href="<?= BASE_PATH ?>wissensdb/edit.php?id=<?= $entry['id'] ?>" 
                                                   class="kb-quick-btn kb-quick-btn-edit" title="Bearbeiten"
                                                   onclick="event.stopPropagation();">
                                                    <i class="fa-solid fa-edit"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
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
                                                    <div>
                                                        <?php if (!empty($entry['is_pinned'])): ?>
                                                            <span class="badge bg-primary me-1" title="Angepinnt"><i class="fa-solid fa-thumbtack"></i></span>
                                                        <?php endif; ?>
                                                        <span class="badge kb-type-badge" style="background-color: <?= KBHelper::getTypeColor($entry['type']) ?>; color: #ffffff;"><?= KBHelper::getTypeLabel($entry['type']) ?></span>
                                                    </div>
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
                                                        <?php if ($entry['updater_name'] && empty($entry['hide_editor'])): ?>
                                                            von <?= htmlspecialchars($entry['updater_name']) ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        Erstellt: <?= date('d.m.Y H:i', strtotime($entry['created_at'])) ?>
                                                        <?php if ($entry['creator_name'] && empty($entry['hide_editor'])): ?>
                                                            von <?= htmlspecialchars($entry['creator_name']) ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                    </div><!-- /.col-card-wrapper -->
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        const suggestionsContainer = document.getElementById('search-suggestions');
        let debounceTimer;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestionsContainer.classList.remove('active');
                suggestionsContainer.innerHTML = '';
                return;
            }
            
            // Debounce the search
            debounceTimer = setTimeout(function() {
                fetch('<?= BASE_PATH ?>wissensdb/search-api.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.results && data.results.length > 0) {
                            let html = '';
                            data.results.forEach(function(item) {
                                html += '<a href="<?= BASE_PATH ?>wissensdb/view.php?id=' + item.id + '" class="search-suggestion-item">';
                                html += '<div class="search-suggestion-title">' + escapeHtml(item.title) + '</div>';
                                if (item.subtitle) {
                                    html += '<div class="search-suggestion-subtitle">' + escapeHtml(item.subtitle) + '</div>';
                                }
                                html += '<div class="search-suggestion-meta">';
                                html += '<span class="search-suggestion-badge" style="background-color: ' + item.type_color + '; color: #fff;">' + item.type_label + '</span>';
                                if (item.competency_label) {
                                    html += '<span class="search-suggestion-badge" style="background-color: ' + item.competency_color + '; color: #fff;">' + item.competency_label + '</span>';
                                }
                                html += '</div>';
                                html += '</a>';
                            });
                            suggestionsContainer.innerHTML = html;
                            suggestionsContainer.classList.add('active');
                        } else {
                            suggestionsContainer.classList.remove('active');
                            suggestionsContainer.innerHTML = '';
                        }
                    })
                    .catch(function(error) {
                        console.error('Search error:', error);
                    });
            }, 300);
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.remove('active');
            }
        });
        
        // Show suggestions again when focusing on input
        searchInput.addEventListener('focus', function() {
            if (suggestionsContainer.innerHTML.trim() !== '') {
                suggestionsContainer.classList.add('active');
            }
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
    </script>
</body>

</html>
