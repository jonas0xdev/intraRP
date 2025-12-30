<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Berlin');

session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

function logSync($message, $level = 'INFO')
{
    try {
        $logFile = __DIR__ . '/logs/enotf_billing.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = __DIR__ . '/logs/enotf_billing_' . date('Y-m-d_His') . '.log';
            @rename($logFile, $archiveFile);

            $files = glob(__DIR__ . '/logs/enotf_billing_*.log');
            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
            foreach ($files as $file) {
                if (filemtime($file) < $thirtyDaysAgo) {
                    @unlink($file);
                }
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        // Logging-Fehler ignorieren
    }
}

/**
 * Verarbeitet Billing-Anfragen für eNotf-Protokolle
 * Gibt Protokolle zurück, die noch nicht abgerechnet wurden (billing_sent = 0)
 * und markiert diese anschließend als versendet (billing_sent = 1)
 */
function handleBillingRequest($data, $pdo)
{
    try {
        logSync('Billing-Anfrage empfangen', 'INFO');

        $timestamp = $data['timestamp'] ?? null;

        if (!$timestamp) {
            logSync('Timestamp fehlt in der Anfrage', 'WARNING');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Erforderliche Felder fehlen',
                'message' => 'timestamp ist erforderlich'
            ]);
            return;
        }

        // Konvertiere Timestamp zu MySQL DateTime
        $date = date('Y-m-d H:i:s', $timestamp);
        logSync("Abfrage für Timestamp: $timestamp ($date)", 'INFO');

        // Hole alle Protokolle, die noch nicht abgerechnet wurden (billing_sent = 0 oder NULL)
        // und die vor oder zum angegebenen Timestamp erstellt wurden
        $stmt = $pdo->prepare("
            SELECT 
                id,
                enr as missionNumber,
                patname as name,
                patgebdat as birthdate,
                transportziel,
                created_at
            FROM intra_edivi 
            WHERE (billing_sent IS NULL OR billing_sent = 0)
            AND freigegeben = 1
            AND created_at <= :date
            ORDER BY created_at ASC
        ");
        $stmt->execute(['date' => $date]);
        $protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($protocols)) {
            logSync('Keine Protokolle für Billing gefunden', 'INFO');
            echo json_encode([
                'success' => true,
                'count' => 0,
                'protocols' => []
            ]);
            return;
        }

        // Verarbeite die Protokolle und erstelle Response
        $responseProtocols = [];
        $protocolIds = [];

        foreach ($protocols as $protocol) {
            $protocolIds[] = $protocol['id'];

            // Transport ist true, wenn transportziel 2, 21 oder 22 ist
            $transport = in_array($protocol['transportziel'], [2, 21, 22]);

            $responseProtocols[] = [
                'name' => $protocol['name'] ?? '',
                'birthdate' => $protocol['birthdate'] ?? '',
                'transport' => $transport,
                'missionNumber' => $protocol['missionNumber'] ?? ''
            ];
        }

        // Markiere alle abgerufenen Protokolle als billing_sent = 1
        if (!empty($protocolIds)) {
            $placeholders = implode(',', array_fill(0, count($protocolIds), '?'));
            $updateStmt = $pdo->prepare("
                UPDATE intra_edivi 
                SET billing_sent = 1,
                    billing_sent_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $updateStmt->execute($protocolIds);

            logSync('Markierte ' . count($protocolIds) . ' Protokolle als billing_sent', 'INFO');
        }

        logSync('Billing-Anfrage erfolgreich verarbeitet: ' . count($responseProtocols) . ' Protokolle', 'INFO');

        echo json_encode([
            'success' => true,
            'count' => count($responseProtocols),
            'protocols' => $responseProtocols
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei Billing-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Billing-Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

// Hauptlogik
try {
    // API-Key Validierung
    $headers = getallheaders();
    $apiKey = $headers['intraRP_API_Key'] ?? $_POST['intraRP_API_Key'] ?? $_GET['intraRP_API_Key'] ?? null;

    if (!$apiKey) {
        logSync('API-Key fehlt', 'WARNING');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'API-Key erforderlich'
        ]);
        exit;
    }

    // Validiere API-Key gegen Config
    if (!defined('API_KEY') || $apiKey !== API_KEY) {
        logSync('Ungültiger API-Key: ' . substr($apiKey, 0, 10) . '...', 'WARNING');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'Ungültiger API-Key'
        ]);
        exit;
    }

    // Request-Body parsen
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Fallback auf POST-Parameter
        $data = $_POST;
    }

    // Stelle sicher, dass API-Key auch im Body vorhanden ist
    if (isset($data['intraRP_API_Key'])) {
        unset($data['intraRP_API_Key']);
    }

    logSync('Verarbeite Billing-Request', 'INFO');
    handleBillingRequest($data, $pdo);
} catch (Exception $e) {
    logSync('Kritischer Fehler: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Interner Serverfehler',
        'message' => $e->getMessage()
    ]);
}
