<?php
require __DIR__ . '/../../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Personnel\PersonalLogManager;

$commentsPerPage = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Use PersonalLogManager to get comments only (not system logs)
$logManager = new PersonalLogManager($pdo);
$result = $logManager->getComments($_GET['id'], $page, $commentsPerPage);
$comments = $result['entries'];
$totalComments = $result['total'];

foreach ($comments as $comment) {
    $commentType = PersonalLogManager::getTypeName($comment['type']);

    echo "<div class='comment $commentType border shadow-sm'>";
    $comtime = date("d.m.Y H:i", strtotime($comment['datetime']));
    echo "<p>{$comment['content']}<br><small><span><i class='fa-solid fa-user'></i> {$comment['paneluser']} <i class='fa-solid fa-clock'></i> $comtime";

    if (Permissions::check('admin') && $comment['type'] <= 3) {
        echo " / <a href='" . BASE_PATH . "mitarbeiter/comment-delete.php?id={$comment['logid']}&pid={$comment['profilid']}'><i class='fa-solid fa-trash' style='color:red;margin-left:5px'></i></a></span></small></p>";
    } else {
        echo "</span></small></p>";
    }
    echo "</div>";
}

$totalPages = ceil($totalComments / $commentsPerPage);
if ($totalPages > 1) {
    echo '<nav aria-label="Comment Pagination">';
    echo '<ul class="pagination justify-content-center">';
    $editArgument = isset($_GET['edit']) ? '&edit' : '';
    $logPageArgument = isset($_GET['logpage']) ? '&logpage=' . $_GET['logpage'] : '';

    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=' . ($page - 1) . $logPageArgument . $editArgument . '">Zurück</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Zurück</span></li>';
    }

    if ($totalPages <= 10) {
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $page) {
                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=' . $i . $logPageArgument . $editArgument . '">' . $i . '</a></li>';
            }
        }
    } else {
        $maxButtons = 5;
        $startPage = max(1, $page - floor($maxButtons / 2));
        $endPage = min($totalPages, $startPage + $maxButtons - 1);

        if ($endPage - $startPage < $maxButtons - 1) {
            $startPage = max(1, $endPage - $maxButtons + 1);
        }

        if ($startPage > 1) {
            echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=1' . $logPageArgument . $editArgument . '">1</a></li>';
            if ($startPage > 2) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $page) {
                echo '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=' . $i . $logPageArgument . $editArgument . '">' . $i . '</a></li>';
            }
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=' . $totalPages . $logPageArgument . $editArgument . '">' . $totalPages . '</a></li>';
        }
    }

    if ($page < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="?id=' . $_GET['id'] . '&page=' . ($page + 1) . $logPageArgument . $editArgument . '">Weiter</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">Weiter</span></li>';
    }

    echo '</ul>';
    echo '</nav>';
}
