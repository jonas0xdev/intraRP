<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\SystemUpdater;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$updater = new SystemUpdater();
$currentVersion = $updater->getCurrentVersion();
$updateInfo = null;
$checking = false;
$versionAge = $updater->getVersionAge();
$isUpdateRecommended = $updater->isUpdateRecommended();
$isPreRelease = $updater->isPreRelease();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_updates'])) {
        $checking = true;
        $updateInfo = $updater->checkForUpdatesCached();

        // Log the check action
        require_once __DIR__ . '/../../assets/config/database.php';
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
                $_SESSION['userid'],
                'system_update_check',
                json_encode(['result' => $updateInfo, 'cached' => $updateInfo['cached'] ?? false]),
                'System',
                0
        );
    } elseif (isset($_POST['install_update'])) {
        $downloadUrl = $_POST['download_url'] ?? '';
        $newVersion = $_POST['new_version'] ?? '';
        
        if ($downloadUrl && $newVersion) {
            $installResult = $updater->downloadAndApplyUpdate($downloadUrl, $newVersion);
            
            // Log the installation attempt
            require_once __DIR__ . '/../../assets/config/database.php';
            $auditLogger = new AuditLogger($pdo);
            $auditLogger->log(
                    $_SESSION['userid'],
                    'system_update_install',
                    json_encode(['version' => $newVersion, 'result' => $installResult]),
                    'System',
                    0
            );
            
            if ($installResult['success']) {
                Flash::set('success', $installResult['message']);
                // Redirect to reload with new version
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                Flash::set('error', $installResult['message']);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'System Updates';
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
<?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
<div class="container-full position-relative" id="mainpageContainer">
    <div class="container">
        <div class="row">
            <div class="col mb-5">
                <hr class="text-light my-3">
                <div class="d-flex justify-content-between align-items-center mb-5">
                    <h1 class="mb-0">System-Updates</h1>
                </div>
                <?php Flash::render(); ?>

                <!-- Current Version Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Aktuelle Version</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($isPreRelease): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fa-solid fa-flask"></i> <strong>Pre-Release Version:</strong>
                                Sie verwenden eine Entwickler- oder Vorschau-Version.
                            </div>
                        <?php endif; ?>

                        <?php if ($isUpdateRecommended && !$checking): ?>
                            <div class="alert alert-info mb-3">
                                <strong>Update empfohlen:</strong>
                                Ihre Version ist <?= $versionAge ?> Tage alt. Es wird empfohlen, auf Updates zu prüfen.
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Version:</dt>
                                    <dd class="col-sm-8">
                                        <strong><?= htmlspecialchars($currentVersion['version']) ?></strong>
                                        <?php if ($isPreRelease): ?>
                                            <span class="badge bg-warning text-dark ms-1">Pre-Release</span>
                                        <?php endif; ?>
                                    </dd>

                                    <dt class="col-sm-4">Aktualisiert am:</dt>
                                    <dd class="col-sm-8">
                                        <?= htmlspecialchars($currentVersion['updated_at']) ?>
                                        <small class="text-muted">(vor <?= $versionAge ?> Tagen)</small>
                                    </dd>

                                    <dt class="col-sm-4">Build-Nummer:</dt>
                                    <dd class="col-sm-8"><?= htmlspecialchars($currentVersion['build_number']) ?></dd>

                                    <dt class="col-sm-4">Commit-Hash:</dt>
                                    <dd class="col-sm-8"><code><?= htmlspecialchars(substr($currentVersion['commit_hash'], 0, 8)) ?></code></dd>
                                </dl>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <form method="post" class="w-100">
                                    <button type="submit" name="check_updates" class="btn btn-primary w-100">
                                        <i class="fa-solid fa-sync"></i> Auf Updates prüfen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($checking && $updateInfo): ?>
                    <!-- Update Information Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                Update-Informationen
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($updateInfo['error'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars($updateInfo['message']) ?>
                                </div>
                            <?php elseif ($updateInfo['available']): ?>
                                <?php
                                $urgency = $updater->getUpdateUrgency();
                                $urgencyColors = [
                                        'low' => 'info',
                                        'medium' => 'warning',
                                        'high' => 'danger',
                                        'critical' => 'danger'
                                ];
                                $urgencyLabels = [
                                        'low' => 'Niedrige Priorität',
                                        'medium' => 'Mittlere Priorität',
                                        'high' => 'Hohe Priorität',
                                        'critical' => 'Kritisch'
                                ];
                                $alertClass = $urgencyColors[$urgency] ?? 'success';
                                ?>
                                <div class="alert alert-<?= $alertClass ?>">
                                    <h5><i class="fa-solid fa-check-circle"></i> Neues Update verfügbar!</h5>
                                    <p class="mb-0">
                                        Eine neue Version ist verfügbar: <strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong>
                                        <span class="badge bg-<?= $alertClass ?> ms-2"><?= $urgencyLabels[$urgency] ?? 'Update verfügbar' ?></span>
                                        <?php if (isset($updateInfo['cached']) && $updateInfo['cached']): ?>
                                            <span class="badge bg-secondary ms-1" title="Gecachte Daten"><i class="fa-solid fa-clock"></i> Gecacht</span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Version Details:</h6>
                                        <dl class="row">
                                            <dt class="col-sm-5">Aktuelle Version:</dt>
                                            <dd class="col-sm-7"><?= htmlspecialchars($updateInfo['current_version']) ?></dd>

                                            <dt class="col-sm-5">Neue Version:</dt>
                                            <dd class="col-sm-7"><strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong></dd>

                                            <dt class="col-sm-5">Release-Name:</dt>
                                            <dd class="col-sm-7"><?= htmlspecialchars($updateInfo['release_name']) ?></dd>

                                            <?php if (isset($updateInfo['published_at'])): ?>
                                                <dt class="col-sm-5">Veröffentlicht:</dt>
                                                <dd class="col-sm-7"><?= date('d.m.Y H:i', strtotime($updateInfo['published_at'])) ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Aktionen:</h6>
                                        
                                        <!-- Install Update Button -->
                                        <form method="post" id="install-update-form" class="mb-2">
                                            <input type="hidden" name="install_update" value="1">
                                            <input type="hidden" name="download_url" value="<?= htmlspecialchars($updateInfo['download_url']) ?>">
                                            <input type="hidden" name="new_version" value="<?= htmlspecialchars($updateInfo['latest_version']) ?>">
                                            <button type="button" id="install-update-btn" class="btn btn-success w-100">
                                                <i class="fa-solid fa-download"></i> Update jetzt installieren
                                            </button>
                                        </form>
                                        
                                        <!-- Progress Modal -->
                                        <div class="modal fade" id="update-progress-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fa-solid fa-download me-2"></i>
                                                            Update wird installiert
                                                        </h5>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center mb-3">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Wird geladen...</span>
                                                            </div>
                                                        </div>
                                                        <div class="progress mb-3" style="height: 25px;">
                                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                                 role="progressbar" 
                                                                 id="update-progress-bar"
                                                                 style="width: 0%">
                                                                <span id="update-progress-text">0%</span>
                                                            </div>
                                                        </div>
                                                        <div id="update-status-text" class="text-center">
                                                            <small class="text-muted">Update wird vorbereitet...</small>
                                                        </div>
                                                        <div class="alert alert-info mt-3 mb-0">
                                                            <small>
                                                                <i class="fa-solid fa-info-circle me-1"></i>
                                                                <strong>Hinweis:</strong> Bitte schließen Sie dieses Fenster nicht.
                                                                Der Vorgang kann mehrere Minuten dauern.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <script>
                                        document.getElementById('install-update-btn').addEventListener('click', async function() {
                                            const newVersion = '<?= htmlspecialchars($updateInfo['latest_version']) ?>';
                                            const confirmed = await showConfirm(
                                                'Update auf Version ' + newVersion + ' installieren?\n\n' +
                                                'Ein Backup wird automatisch erstellt.\n' +
                                                'Dieser Vorgang kann einige Minuten dauern.',
                                                {
                                                    title: 'Update installieren?',
                                                    confirmText: 'Installieren',
                                                    cancelText: 'Abbrechen',
                                                    confirmClass: 'btn-success',
                                                    danger: false
                                                }
                                            );
                                            
                                            if (confirmed) {
                                                // Show progress modal
                                                const progressModal = new bootstrap.Modal(document.getElementById('update-progress-modal'));
                                                progressModal.show();
                                                
                                                // Simulate progress (since we can't get real-time updates from PHP)
                                                let progress = 0;
                                                const progressBar = document.getElementById('update-progress-bar');
                                                const progressText = document.getElementById('update-progress-text');
                                                const statusText = document.getElementById('update-status-text');
                                                
                                                const steps = [
                                                    { percent: 10, text: 'Download wird vorbereitet...' },
                                                    { percent: 25, text: 'Update wird heruntergeladen...' },
                                                    { percent: 40, text: 'Dateien werden extrahiert...' },
                                                    { percent: 55, text: 'Backup wird erstellt...' },
                                                    { percent: 70, text: 'Update wird installiert...' },
                                                    { percent: 85, text: 'Dateien werden kopiert...' },
                                                    { percent: 95, text: 'Installation wird abgeschlossen...' }
                                                ];
                                                
                                                let currentStep = 0;
                                                const updateProgress = () => {
                                                    if (currentStep < steps.length) {
                                                        const step = steps[currentStep];
                                                        progressBar.style.width = step.percent + '%';
                                                        progressText.textContent = step.percent + '%';
                                                        statusText.innerHTML = '<small class="text-muted">' + step.text + '</small>';
                                                        currentStep++;
                                                    }
                                                };
                                                
                                                // Update progress every 2 seconds
                                                const progressInterval = setInterval(updateProgress, 2000);
                                                updateProgress(); // Start immediately
                                                
                                                // Submit the form
                                                document.getElementById('install-update-form').submit();
                                            }
                                        });
                                        </script>
                                        
                                        <?php if (isset($updateInfo['html_url'])): ?>
                                            <a href="<?= htmlspecialchars($updateInfo['html_url']) ?>"
                                               target="_blank"
                                               class="btn btn-outline-primary w-100 mb-2">
                                                <i class="fa-solid fa-external-link-alt"></i> Release auf GitHub ansehen
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($updateInfo['download_url'])): ?>
                                            <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>"
                                               class="btn btn-outline-secondary w-100">
                                                <i class="fa-solid fa-file-zipper"></i> ZIP manuell herunterladen
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($updateInfo['release_notes'])): ?>
                                    <hr>
                                    <h6>Release-Notizen:</h6>
                                    <div class="border rounded p-3 bg-dark" style="max-height: 400px; overflow-y: auto;">
                                        <?= $updater->getFormattedReleaseNotes($updateInfo['release_notes']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-info mt-3">
                                    <strong><i class="fa-solid fa-info-circle"></i> Hinweis:</strong>
                                    Das Update wird automatisch installiert und ein Backup wird im Verzeichnis <code>system/updates/</code> erstellt.
                                    Bei Problemen können Sie das Backup manuell wiederherstellen.
                                    <br><strong>Wichtig:</strong> Erstellen Sie zusätzlich ein manuelles Backup Ihrer Datenbank!
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fa-solid fa-check-circle"></i>
                                    Ihr System ist auf dem neuesten Stand. Es sind keine Updates verfügbar.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
