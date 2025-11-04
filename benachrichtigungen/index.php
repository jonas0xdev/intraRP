<?php
session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Helpers\Flash;
use App\Notifications\NotificationManager;

$notificationManager = new NotificationManager($pdo);
$userId = $_SESSION['userid'];

// Handle POST actions (state-changing operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify action exists
    $action = $_POST['action'] ?? null;
    
    if ($action === 'mark_read' && isset($_POST['id']) && is_numeric($_POST['id'])) {
        $notificationManager->markAsRead((int)$_POST['id'], $userId);
        Flash::set('success', 'Benachrichtigung als gelesen markiert');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }
    
    if ($action === 'mark_all_read') {
        $notificationManager->markAllAsRead($userId);
        Flash::set('success', 'Alle Benachrichtigungen als gelesen markiert');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }
    
    if ($action === 'delete' && isset($_POST['id']) && is_numeric($_POST['id'])) {
        $notificationManager->delete((int)$_POST['id'], $userId);
        Flash::set('success', 'Benachrichtigung gelöscht');
        header("Location: " . BASE_PATH . "benachrichtigungen/index.php");
        exit();
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
if ($filter === 'unread') {
    $notifications = $notificationManager->getUnread($userId, 100);
} else {
    $notifications = $notificationManager->getAll($userId, 100);
}

$unreadCount = $notificationManager->getUnreadCount($userId);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="dark">

<head>
    <?php
    $SITE_TITLE = 'Benachrichtigungen';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
    <style>
        .notification-item {
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }

        .notification-item.unread {
            border-left-color: var(--bs-primary);
            background-color: rgba(13, 110, 253, 0.05);
        }

        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.2rem;
        }

        .notification-icon.antrag {
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--bs-primary);
        }

        .notification-icon.protokoll {
            background-color: rgba(25, 135, 84, 0.1);
            color: var(--bs-success);
        }

        .notification-icon.dokument {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--bs-warning);
        }

        .notification-date {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .notification-actions {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .notification-item:hover .notification-actions {
            opacity: 1;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="benachrichtigungen">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col">
                    <hr class="text-light my-3">
                    <h1>
                        <i class="fa-solid fa-bell me-2"></i>
                        Benachrichtigungen
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-primary"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </h1>

                    <?php Flash::render(); ?>

                    <div class="my-3 d-flex justify-content-between align-items-center">
                        <div>
                            <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                                Alle
                            </a>
                            <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-secondary' ?>">
                                Ungelesen (<?= $unreadCount ?>)
                            </a>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-sm btn-outline-light">
                                    <i class="fa-solid fa-check-double me-1"></i> Alle als gelesen markieren
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <hr class="text-light my-3">

                    <div class="intra__tile p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                                <p class="mb-0">Keine Benachrichtigungen vorhanden</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification):
                                $isUnread = $notification['is_read'] == 0;
                                
                                // MySQL stores timestamps in its system timezone, we need to convert to PHP's timezone
                                // Create DateTime in MySQL's timezone (system timezone), then convert to PHP's timezone
                                $datetime = new DateTime($notification['created_at'], new DateTimeZone('Europe/Berlin')); // MySQL SYSTEM timezone
                                $datetime->setTimezone(new DateTimeZone(date_default_timezone_get())); // Convert to PHP timezone (UTC)
                                $now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
                                $diff = $now->diff($datetime);

                                // Check if the notification is in the future (clock skew)
                                if ($diff->invert == 0) {
                                    // Notification is in the future, show as "Gerade eben"
                                    $timeAgo = 'Gerade eben';
                                } elseif ($diff->days > 0) {
                                    $timeAgo = $diff->days . ' Tag' . ($diff->days > 1 ? 'en' : '') . ' her';
                                } elseif ($diff->h > 0) {
                                    $timeAgo = $diff->h . ' Stunde' . ($diff->h > 1 ? 'n' : '') . ' her';
                                } elseif ($diff->i > 0) {
                                    $timeAgo = $diff->i . ' Minute' . ($diff->i > 1 ? 'n' : '') . ' her';
                                } else {
                                    $timeAgo = 'Gerade eben';
                                }

                                $iconClass = [
                                    'antrag' => 'fa-file-alt',
                                    'protokoll' => 'fa-file-medical',
                                    'dokument' => 'fa-file-upload'
                                ];
                                $icon = $iconClass[$notification['type']] ?? 'fa-bell';
                            ?>
                                <div class="notification-item <?= $isUnread ? 'unread' : '' ?> p-3 border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="notification-icon <?= htmlspecialchars($notification['type']) ?>">
                                                <i class="fa-solid <?= $icon ?>"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 <?= $isUnread ? 'fw-bold' : '' ?>">
                                                        <?= htmlspecialchars($notification['title']) ?>
                                                    </h6>
                                                    <?php if ($notification['message']): ?>
                                                        <p class="mb-1 text-muted">
                                                            <?= htmlspecialchars($notification['message']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <small class="notification-date">
                                                        <i class="fa-solid fa-clock me-1"></i>
                                                        <?= $timeAgo ?>
                                                    </small>
                                                </div>
                                                <div class="notification-actions ms-3">
                                                    <?php if ($notification['link']): ?>
                                                        <a href="<?= htmlspecialchars($notification['link']) ?>" 
                                                           class="btn btn-sm btn-outline-primary me-1"
                                                           title="Öffnen">
                                                            <i class="fa-solid fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($isUnread): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="id" value="<?= $notification['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Als gelesen markieren">
                                                                <i class="fa-solid fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Benachrichtigung wirklich löschen?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $notification['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>
