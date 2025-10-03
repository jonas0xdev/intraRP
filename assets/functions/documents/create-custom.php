<?php
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Documents\DocumentTemplateManager;
use App\Documents\DocumentRenderer;
use App\Documents\DocumentPDFGenerator;
use App\Documents\DocumentIdGenerator;
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

    // Generiere neue 12-stellige Dokument-ID
    $documentId = DocumentIdGenerator::generate($pdo);

    // Stelle sicher dass ausstellungsdatum vorhanden ist
    $ausstellungsdatum = $input['fields']['ausstellungsdatum'] ?? $input['ausstellungsdatum'] ?? date('Y-m-d');

    $manager = new DocumentTemplateManager($pdo);

    // Erstelle Dokument mit der generierten ID
    $dbId = $manager->createDocument(
        $input['template_id'],
        $input['profileid'],
        array_merge(
            [
                'erhalter' => $input['erhalter'],
                'erhalter_gebdat' => $input['erhalter_gebdat'] ?? null,
                'anrede' => $input['anrede'] ?? null,
                'ausstellungsdatum' => $ausstellungsdatum,
                'document_id' => $documentId  // Übergebe die generierte ID
            ],
            $input['fields'] ?? []
        ),
        $documentId  // Übergebe auch als docid
    );

    // PDF generieren
    try {
        $renderer = new DocumentRenderer($pdo);
        $pdfGenerator = new DocumentPDFGenerator($pdo, $renderer);
        $pdfPath = $pdfGenerator->generateAndStore($dbId);  // Verwende Database ID!
        error_log("PDF erfolgreich generiert: $pdfPath");
    } catch (Exception $e) {
        error_log("PDF-Generierung fehlgeschlagen für Dokument-DB-ID {$dbId}: " . $e->getMessage());
        // Dokument wurde trotzdem erstellt, nur PDF fehlt
    }

    // Lade Template-Infos für Log-Eintrag
    $template = $manager->getTemplate($input['template_id']);

    // Erstelle Log-Eintrag mit Link zum PDF (ohne Bindestriche im Dateinamen!)
    $logStmt = $pdo->prepare("
        INSERT INTO intra_mitarbeiter_log 
        (profilid, type, content, paneluser, datetime) 
        VALUES (?, 7, ?, ?, NOW())
    ");

    // Dateiname ohne Bindestriche für PDF-Link
    $pdfFilename = str_replace('-', '', $documentId) . '.pdf';
    $pdfLink = BASE_PATH . 'storage/documents/' . $pdfFilename;

    $logContent = "Dokument erstellt: <a href='{$pdfLink}' target='_blank'>" .
        htmlspecialchars($template['name']) .
        " (ID: {$documentId})</a>";
    $panelUser = $_SESSION['cirs_user'] ?? 'System';

    $logStmt->execute([
        $input['profileid'],
        $logContent,
        $panelUser
    ]);

    // Logge die Aktion im Audit-Log
    logAction($pdo, 'document_created', [
        'db_id' => $dbId,
        'document_id' => $documentId,
        'template_id' => $input['template_id'],
        'template_name' => $template['name'],
        'profileid' => $input['profileid'],
        'created_by' => $_SESSION['discordtag'] ?? null
    ]);

    echo json_encode([
        'success' => true,
        'db_id' => $dbId,
        'document_id' => $documentId,
        'pdf_url' => $pdfLink,
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
            (user, action, data, created_at) 
            VALUES (:user_id, :action, :data, NOW())
        ");

        $stmt->execute([
            'user_id' => $_SESSION['userid'] ?? null,
            'action' => $action,
            'data' => json_encode($data)
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Fehler: " . $e->getMessage());
    }
}
