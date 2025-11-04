<?php

use App\Auth\Permissions;
use App\Notifications\NotificationManager;
?>
<nav class="navbar navbar-expand-lg" id="intra-nav">
    <div class="container">
        <a class="navbar-brand" href="#"><img src="<?php echo SYSTEM_LOGO ?>" alt="<?php echo SYSTEM_NAME ?>" style="height:48px;width:auto"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <a class="nav-link" href="<?= BASE_PATH ?>index.php" data-page="dashboard"><i class="fa-solid fa-home" style="margin-right:3px"></i> Dashboard</a>
                <?php if (Permissions::check(['admin', 'users.view'])) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-page="benutzer" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-tie" style="margin-right:3px"></i> Benutzer
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= BASE_PATH ?>benutzer/list.php">Übersicht</a></li>
                            <?php if (Permissions::check(['admin', 'users.create'])) { ?>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>benutzer/registration-codes.php">Registrierungscodes</a></li>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <?php if (Permissions::check(['admin', 'audit.view'])) { ?>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>benutzer/auditlog.php">Audit-Log</a></li>
                            <?php } ?>
                            <li><a class="dropdown-item" href="<?= BASE_PATH ?>benutzer/rollen/index.php">Rollenverwaltung</a></li>
                        </ul>
                    </li>
                <?php }
                if (Permissions::check(['admin', 'personnel.view'])) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-page="mitarbeiter" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-users" style="margin-right:3px"></i> Mitarbeiter
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= BASE_PATH ?>mitarbeiter/list.php">Übersicht</a></li>
                            <?php if (Permissions::check(['admin', 'personnel.edit'])) { ?>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>mitarbeiter/create.php">Erstellen</a></li>
                            <?php } ?>
                            <?php if (Permissions::check(['admin', 'application.view'])) { ?>
                                <div class="dropdown-divider"></div>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>antrag/admin/list.php">Anträge bearbeiten</a></li>
                            <?php } ?>

                        </ul>
                    </li>
                <?php } ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-page="edivi" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-house-medical-flag" style="margin-right:3px"></i> eNOTF
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>enotf/" target="_blank">Neues Protokoll</a></li>
                        <?php if (Permissions::check(['admin', 'edivi.view'])) { ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?= BASE_PATH ?>enotf/admin/list.php">Prüfliste</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_PATH ?>enotf/admin/zielverwaltung/index.php">Transportziele</a></li>
                        <?php } ?>
                    </ul>
                </li>
                <?php if (Permissions::check(['admin', 'personnel.view', 'vehicles.view', 'edivi.view', 'dashboard.manage'])) { ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-page="settings" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-sliders" style="margin-right:3px"></i> Einstellungen
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (Permissions::check(['admin', 'personnel.view'])) { ?>
                                <li>
                                    <h6 class="dropdown-header">Personal</h6>
                                </li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/personal/dienstgrade/index.php">Dienstgrade verwalten</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/personal/qualifw/index.php">FW Qualifikationen verwalten</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/personal/qualird/index.php">RD Qualifikationen verwalten</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/personal/qualifd/index.php">Fachdienste verwalten</a></li>
                                <?php if (Permissions::check(['admin'])) { ?>
                                    <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/documents/templates.php">Dokumente verwalten</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/antrag/list.php">Antragstypen verwalten</a></li>
                                <?php } ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            <?php }
                            if (Permissions::check(['admin', 'vehicles.view'])) { ?>
                                <li>
                                    <h6 class="dropdown-header">Fahrzeuge</h6>
                                </li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/fahrzeuge/fahrzeuge/index.php">Bearbeiten</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/fahrzeuge/beladelisten/index.php">Beladelisten</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            <?php } ?>
                            <li>
                                <h6 class="dropdown-header">Sonstiges</h6>
                            </li>
                            <?php if (Permissions::check(['admin', 'dashboard.manage'])) { ?>
                                <li><a class="dropdown-item" href="<?= BASE_PATH ?>settings/dashboard/index.php">Dashboard</a></li>
                            <?php } ?>
                        </ul>
                    </li>
                <?php } ?>
                <?php
                // Get unread notification count and recent notifications
                $unreadCount = 0;
                $recentNotifications = [];
                try {
                    // Ensure database connection is available
                    if (!isset($pdo)) {
                        require_once __DIR__ . '/../config/database.php';
                    }
                    
                    if (isset($pdo)) {
                        $notificationManager = new NotificationManager($pdo);
                        $unreadCount = $notificationManager->getUnreadCount($_SESSION['userid']);
                        // Get last 5 notifications for the dropdown
                        $recentNotifications = $notificationManager->getAll($_SESSION['userid'], 5);
                    }
                } catch (Exception $e) {
                    // Silently fail if database connection is not available
                    // This prevents navbar from breaking on pages where DB is not set up
                    error_log("Notification count error: " . $e->getMessage());
                }
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-page="benachrichtigungen">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
                                <span class="visually-hidden">ungelesene Benachrichtigungen</span>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 320px; max-width: 400px;">
                        <li><h6 class="dropdown-header">Benachrichtigungen</h6></li>
                        <?php if (empty($recentNotifications)): ?>
                            <li><span class="dropdown-item-text text-muted small">Keine Benachrichtigungen</span></li>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notification): 
                                $isUnread = $notification['is_read'] == 0;
                                $datetime = new DateTime($notification['created_at']);
                                $now = new DateTime();
                                $diff = $now->diff($datetime);
                                
                                if ($diff->days > 0) {
                                    $timeAgo = $diff->days . 'd';
                                } elseif ($diff->h > 0) {
                                    $timeAgo = $diff->h . 'h';
                                } elseif ($diff->i > 0) {
                                    $timeAgo = $diff->i . 'm';
                                } else {
                                    $timeAgo = 'jetzt';
                                }
                                
                                $iconClass = [
                                    'antrag' => 'fa-file-alt',
                                    'protokoll' => 'fa-file-medical',
                                    'dokument' => 'fa-file-upload'
                                ];
                                $icon = $iconClass[$notification['type']] ?? 'fa-bell';
                            ?>
                                <li>
                                    <a class="dropdown-item <?= $isUnread ? 'fw-bold' : '' ?>" href="<?= htmlspecialchars($notification['link'] ?: BASE_PATH . 'benachrichtigungen/index.php') ?>" style="white-space: normal;">
                                        <div class="d-flex align-items-start">
                                            <i class="fa-solid <?= $icon ?> me-2 mt-1" style="font-size: 0.9rem;"></i>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="small"><?= htmlspecialchars($notification['title']) ?></div>
                                                <?php if ($notification['message']): ?>
                                                    <div class="text-muted" style="font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?= htmlspecialchars($notification['message']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted ms-2" style="font-size: 0.7rem; white-space: nowrap;"><?= $timeAgo ?></small>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item text-center small" href="<?= BASE_PATH ?>benachrichtigungen/index.php">
                                Alle Benachrichtigungen anzeigen
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown" id="intra-usermenu">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= $_SESSION['cirs_username'] ?> <span class="badge text-bg-<?= $_SESSION['role_color'] ?>"><?= $_SESSION['role_name'] ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>profil.php">Profil bearbeiten</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_PATH ?>logout.php">Abmelden</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    $(document).ready(function() {
        var currentPage = $("body").data("page");
        $(".nav-link").removeClass("active");
        $(".nav-link[data-page='" + currentPage + "']").addClass("active");
    });

    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
</script>

<?php
include __DIR__ . '/support/banner.php';
