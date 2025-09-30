<?php
// api/documents/create-custom.php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentTemplateManager.php';

use App\Documents\DocumentTemplateManager;
use App\Auth\Permissions;

header('Content-Type: application/json');

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Keine Daten empfangen');
    }

    // Validiere erforderliche Felder
    $required = ['profileid', 'template_id', 'ausstellerid', 'erhalter'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Feld '$field' ist erforderlich");
        }
    }

    // Stelle sicher dass ausstellungsdatum vorhanden ist (entweder direkt oder in fields)
    $ausstellungsdatum = $input['fields']['ausstellungsdatum'] ?? $input['ausstellungsdatum'] ?? date('Y-m-d');

    $manager = new DocumentTemplateManager($pdo);

    // Erstelle Dokument
    $docId = $manager->createDocument(
        $input['template_id'],
        $input['profileid'],
        array_merge(
            [
                'erhalter' => $input['erhalter'],
                'erhalter_gebdat' => $input['erhalter_gebdat'] ?? null,
                'anrede' => $input['anrede'] ?? null,
                'ausstellungsdatum' => $ausstellungsdatum
            ],
            $input['fields'] ?? []
        )
    );

    // Logge die Aktion
    logAction($pdo, 'document_created', [
        'doc_id' => $docId,
        'template_id' => $input['template_id'],
        'profileid' => $input['profileid'],
        'created_by' => $_SESSION['discordtag'] ?? null
    ]);

    echo json_encode([
        'success' => true,
        'doc_id' => $docId,
        'message' => 'Dokument erfolgreich erstellt'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function logAction($pdo, $action, $data)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO intra_audit_log 
            (user_id, action, data, created_at) 
            VALUES (:user_id, :action, :data, NOW())
        ");

        $stmt->execute([
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'data' => json_encode($data)
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Fehler: " . $e->getMessage());
    }
}
