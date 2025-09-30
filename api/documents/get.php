<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new Exception('Template-ID fehlt');
    }

    $manager = new DocumentTemplateManager($pdo);
    $template = $manager->getTemplate($id);

    if (!$template) {
        throw new Exception('Template nicht gefunden');
    }

    echo json_encode($template);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()]);
}
