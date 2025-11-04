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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_updates'])) {
        $checking = true;
        $updateInfo = $updater->checkForUpdates();
        
        // Log the check action
        require_once __DIR__ . '/../../assets/config/database.php';
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log(
            $_SESSION['userid'],
            'system_update_check',
            'system',
            null,
            null,
            ['result' => $updateInfo]
        );
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
                            <h5 class="mb-0"><i class="fa-solid fa-info-circle"></i> Aktuelle Version</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Version:</dt>
                                        <dd class="col-sm-8"><strong><?= htmlspecialchars($currentVersion['version']) ?></strong></dd>
                                        
                                        <dt class="col-sm-4">Aktualisiert am:</dt>
                                        <dd class="col-sm-8"><?= htmlspecialchars($currentVersion['updated_at']) ?></dd>
                                        
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
                                    <i class="fa-solid fa-download"></i> Update-Informationen
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($updateInfo['error'])): ?>
                                    <div class="alert alert-danger">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        <?= htmlspecialchars($updateInfo['message']) ?>
                                    </div>
                                <?php elseif ($updateInfo['available']): ?>
                                    <div class="alert alert-success">
                                        <h5><i class="fa-solid fa-check-circle"></i> Neues Update verfügbar!</h5>
                                        <p class="mb-0">Eine neue Version ist verfügbar: <strong><?= htmlspecialchars($updateInfo['latest_version']) ?></strong></p>
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
                                            <?php if (isset($updateInfo['html_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['html_url']) ?>" 
                                                   target="_blank" 
                                                   class="btn btn-primary mb-2 w-100">
                                                    <i class="fa-solid fa-external-link-alt"></i> Release auf GitHub ansehen
                                                </a>
                                            <?php endif; ?>
                                            <?php if (isset($updateInfo['download_url'])): ?>
                                                <a href="<?= htmlspecialchars($updateInfo['download_url']) ?>" 
                                                   class="btn btn-success w-100">
                                                    <i class="fa-solid fa-download"></i> Update herunterladen
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($updateInfo['release_notes'])): ?>
                                        <hr>
                                        <h6>Release-Notizen:</h6>
                                        <div class="border rounded p-3 bg-dark" style="max-height: 400px; overflow-y: auto;">
                                            <?php
                                            // Simple and safe markdown-style formatting to HTML
                                            $notes = htmlspecialchars($updateInfo['release_notes']);
                                            $lines = explode("\n", $notes);
                                            $inList = false;
                                            $output = '';
                                            
                                            foreach ($lines as $line) {
                                                $line = trim($line);
                                                
                                                // Headers
                                                if (preg_match('/^### (.+)$/', $line, $matches)) {
                                                    if ($inList) { $output .= '</ul>'; $inList = false; }
                                                    $output .= '<h6>' . $matches[1] . '</h6>';
                                                } elseif (preg_match('/^## (.+)$/', $line, $matches)) {
                                                    if ($inList) { $output .= '</ul>'; $inList = false; }
                                                    $output .= '<h5>' . $matches[1] . '</h5>';
                                                } elseif (preg_match('/^# (.+)$/', $line, $matches)) {
                                                    if ($inList) { $output .= '</ul>'; $inList = false; }
                                                    $output .= '<h4>' . $matches[1] . '</h4>';
                                                }
                                                // List items
                                                elseif (preg_match('/^[\*\-] (.+)$/', $line, $matches)) {
                                                    if (!$inList) { $output .= '<ul>'; $inList = true; }
                                                    $output .= '<li>' . $matches[1] . '</li>';
                                                }
                                                // Regular text
                                                elseif (!empty($line)) {
                                                    if ($inList) { $output .= '</ul>'; $inList = false; }
                                                    $output .= '<p>' . $line . '</p>';
                                                }
                                                // Empty line
                                                else {
                                                    if ($inList) { $output .= '</ul>'; $inList = false; }
                                                }
                                            }
                                            
                                            if ($inList) { $output .= '</ul>'; }
                                            echo $output;
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="alert alert-info mt-3">
                                        <strong><i class="fa-solid fa-info-circle"></i> Hinweis:</strong> 
                                        Bitte erstellen Sie vor dem Update ein Backup Ihrer Daten und Konfiguration. 
                                        Folgen Sie den Anweisungen in der Dokumentation zur manuellen Installation des Updates.
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

                    <!-- Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fa-solid fa-question-circle"></i> Informationen</h5>
                        </div>
                        <div class="card-body">
                            <h6>Über Updates</h6>
                            <p>
                                Das Update-System prüft auf neue Versionen von intraRP auf GitHub. 
                                Wenn ein Update verfügbar ist, können Sie die Release-Notizen einsehen 
                                und das Update herunterladen.
                            </p>
                            <p>
                                <strong>Wichtig:</strong> Updates müssen manuell installiert werden. 
                                Das System lädt automatisch keine Updates herunter oder installiert diese ohne Ihre Zustimmung.
                            </p>
                            
                            <h6 class="mt-4">Update-Prozess</h6>
                            <ol>
                                <li>Prüfen Sie auf verfügbare Updates mit dem Button "Auf Updates prüfen"</li>
                                <li>Lesen Sie die Release-Notizen sorgfältig durch</li>
                                <li>Erstellen Sie ein Backup Ihrer Daten und Konfiguration</li>
                                <li>Laden Sie das Update von GitHub herunter</li>
                                <li>Folgen Sie den Installations-Anweisungen in der Dokumentation</li>
                            </ol>

                            <div class="alert alert-warning">
                                <strong><i class="fa-solid fa-exclamation-triangle"></i> Warnung:</strong>
                                Stellen Sie sicher, dass Sie vor jedem Update ein vollständiges Backup erstellen!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
