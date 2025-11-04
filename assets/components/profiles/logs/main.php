<?php
require __DIR__ . '/../../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Personnel\PersonalLogManager;

$logsPerPage = 6;
$logPage = isset($_GET['logpage']) ? (int)$_GET['logpage'] : 1;

// Use PersonalLogManager to get system logs only
$logManager = new PersonalLogManager($pdo);
$result = $logManager->getSystemLogs($_GET['id'], $logPage, $logsPerPage);
$logs = $result['entries'];
$totalLogs = $result['total'];

foreach ($logs as $log) {
    $logType = PersonalLogManager::getTypeName($log['type']);

    echo "<div class='comment $logType border shadow-sm'>";
    $logtime = date("d.m.Y H:i", strtotime($log['datetime']));
    echo "<p>{$log['content']}<br><small><span><i class='fa-solid fa-user'></i> {$log['paneluser']} <i class='fa-solid fa-clock'></i> $logtime</span></small></p>";
    echo "</div>";
}

$totalPages = ceil($totalLogs / $logsPerPage);
if ($totalPages > 1) {
    echo '<nav aria-label="System Log Pagination">';
    echo '<ul class="pagination justify-content-center">';
    $editArgument = isset($_GET['edit']) ? '&edit' : '';
    $pageArgument = isset($_GET['page']) ? '&page=' . $_GET['page'] : '';

    if ($logPage > 1) {
        echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=' . ($logPage - 1) . $editArgument . '">Zurück</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Zurück</span></li>';
    }

    if ($totalPages <= 10) {
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $logPage) {
                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=' . $i . $editArgument . '">' . $i . '</a></li>';
            }
        }
    } else {
        $maxButtons = 5;
        $startPage = max(1, $logPage - floor($maxButtons / 2));
        $endPage = min($totalPages, $startPage + $maxButtons - 1);

        if ($endPage - $startPage < $maxButtons - 1) {
            $startPage = max(1, $endPage - $maxButtons + 1);
        }

        if ($startPage > 1) {
            echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=1' . $editArgument . '">1</a></li>';
            if ($startPage > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $logPage) {
                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=' . $i . $editArgument . '">' . $i . '</a></li>';
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=' . $totalPages . $editArgument . '">' . $totalPages . '</a></li>';
        }
    }

    if ($logPage < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . $pageArgument . '&logpage=' . ($logPage + 1) . $editArgument . '">Weiter</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Weiter</span></li>';
    }

    echo '</ul>';
    echo '</nav>';
}
