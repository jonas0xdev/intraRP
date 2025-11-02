<?php
session_start();
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $var = trim($_POST['var'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($var)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/config/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_config (var, value, description) VALUES (:var, :value, :description)");
        $stmt->execute([
            ':var' => $var,
            ':value' => $value !== '' ? $value : NULL,
            ':description' => $description !== '' ? $description : NULL,
        ]);

        Flash::set('config', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Konfiguration erstellt', 'Variable: ' . $var, 'System', 1);
        header("Location: " . BASE_PATH . "settings/config/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("Config creation failed: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/config/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/config/index.php");
    exit;
}
