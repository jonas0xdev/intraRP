<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';

header('Content-Type: application/json');

function logSync($message, $level = 'INFO')
{
    try {
        $logFile = __DIR__ . '/logs/emd_sync.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = __DIR__ . '/logs/emd_sync_' . date('Y-m-d_His') . '.log';
            @rename($logFile, $archiveFile);

            $files = glob(__DIR__ . '/logs/emd_sync_*.log');
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $receivedData = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logSync('Ungültiges JSON empfangen: ' . json_last_error_msg(), 'ERROR');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiges JSON']);
        exit;
    }

    if (!isset($receivedData['intraRP_API_Key']) || $receivedData['intraRP_API_Key'] !== API_KEY) {
        logSync('Unberechtigter Zugriffsversuch', 'WARNING');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Nicht autorisiert',
            'hint' => 'API-Key stimmt nicht überein'
        ]);
        exit;
    }

    $vehicles = $receivedData['data']['vehicles'] ?? [];

    if (empty($vehicles)) {
        logSync('Keine Fahrzeuge in der Anfrage', 'INFO');
        echo json_encode(['success' => true, 'message' => 'Keine Fahrzeuge zu verarbeiten', 'processed' => 0]);
        exit;
    }

    logSync('Es wurden ' . count($vehicles) . ' Fahrzeuge zur Verarbeitung empfangen', 'INFO');

    $vehiclesByDispatch = [];

    foreach ($vehicles as $vehicle) {
        $dispatchId = intval($vehicle['dispatch'] ?? 0);

        if ($dispatchId <= 0) {
            continue;
        }

        if (!isset($vehiclesByDispatch[$dispatchId])) {
            $vehiclesByDispatch[$dispatchId] = [];
        }

        $vehiclesByDispatch[$dispatchId][] = $vehicle;
    }

    logSync('Es wurden ' . count($vehiclesByDispatch) . ' eindeutige Einsatznummern gefunden', 'INFO');

    $processedDispatches = 0;
    $createdEntries = 0;
    $skippedDispatches = 0;

    $pdo->beginTransaction();

    foreach ($vehiclesByDispatch as $dispatchId => $dispatchVehicles) {
        logSync("Verarbeite Einsatz #$dispatchId mit " . count($dispatchVehicles) . " Fahrzeugen", 'INFO');

        $validVehicles = [];

        foreach ($dispatchVehicles as $vehicle) {
            $valueName = $vehicle['value'] ?? null;

            $stmt = $pdo->prepare("
                SELECT id, name, identifier, veh_type, rd_type
                FROM intra_fahrzeuge 
                WHERE name = :name 
                LIMIT 1
            ");
            $stmt->execute([':name' => $valueName]);
            $dbVehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbVehicle) {
                logSync("Fahrzeug '$valueName' wurde in der Datenbank nicht gefunden, wird übersprungen", 'WARNING');
                continue;
            }

            $rdType = intval($dbVehicle['rd_type'] ?? 0);

            if ($rdType === 0) {
                logSync("Fahrzeug '$valueName' ist kein RD-Fahrzeug (rd_type=0), wird übersprungen", 'DEBUG');
                continue;
            }

            $validVehicles[] = [
                'name' => $valueName,
                'identifier' => $dbVehicle['identifier'],
                'rd_type' => $rdType,
                'is_notarzt' => ($rdType === 1),
                'is_transport' => ($rdType === 2)
            ];
        }

        if (empty($validVehicles)) {
            logSync("Keine gültigen RD-Fahrzeuge für Einsatz #$dispatchId, wird übersprungen", 'WARNING');
            $skippedDispatches++;
            continue;
        }

        logSync("Es wurden " . count($validVehicles) . " gültige RD-Fahrzeuge für Einsatz #$dispatchId gefunden", 'INFO');

        $checkExistingStmt = $pdo->prepare("
            SELECT enr
            FROM intra_edivi 
            WHERE enr = :enr OR enr LIKE :enr_pattern
        ");
        $checkExistingStmt->execute([
            ':enr' => $dispatchId,
            ':enr_pattern' => $dispatchId . '_%'
        ]);
        $existingEnrs = $checkExistingStmt->fetchAll(PDO::FETCH_COLUMN);

        logSync("Bereits vorhandene ENRs für Einsatz #$dispatchId: " . implode(', ', $existingEnrs), 'DEBUG');

        foreach ($validVehicles as $vehicle) {
            $vehicleIdentifier = $vehicle['identifier'];
            $fieldToCheck = $vehicle['is_notarzt'] ? 'fzg_na' : 'fzg_transp';

            $checkVehicleStmt = $pdo->prepare("
                SELECT enr
                FROM intra_edivi 
                WHERE (enr = :enr OR enr LIKE :enr_pattern)
                AND $fieldToCheck = :vehicle_id
                LIMIT 1
            ");
            $checkVehicleStmt->execute([
                ':enr' => $dispatchId,
                ':enr_pattern' => $dispatchId . '_%',
                ':vehicle_id' => $vehicleIdentifier
            ]);
            $existingEntry = $checkVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingEntry) {
                logSync("Fahrzeug '$vehicleIdentifier' existiert bereits in Einsatz {$existingEntry['enr']}, wird übersprungen", 'DEBUG');
                continue;
            }

            $enrToUse = null;

            if (!in_array((string)$dispatchId, $existingEnrs)) {
                $enrToUse = $dispatchId;
            } else {
                $suffix = 1;
                while (true) {
                    $testEnr = $dispatchId . '_' . $suffix;
                    if (!in_array($testEnr, $existingEnrs)) {
                        $enrToUse = $testEnr;
                        break;
                    }
                    $suffix++;
                }
            }

            if ($vehicle['is_notarzt']) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_na, created_at) 
                    VALUES (:enr, :fzg_na, NOW())
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_na' => $vehicle['identifier']
                ]);

                logSync("Notarzt-Eintrag $enrToUse erstellt: {$vehicle['identifier']}", 'INFO');
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_transp, created_at) 
                    VALUES (:enr, :fzg_transp, NOW())
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_transp' => $vehicle['identifier']
                ]);

                logSync("Transport-Eintrag $enrToUse erstellt: {$vehicle['identifier']}", 'INFO');
            }

            $existingEnrs[] = $enrToUse;
            $createdEntries++;
        }

        $processedDispatches++;
    }

    $pdo->commit();

    logSync("Synchronisation abgeschlossen: Einsätze=$processedDispatches, Einträge erstellt=$createdEntries, Übersprungen=$skippedDispatches", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Synchronisation erfolgreich abgeschlossen',
        'statistics' => [
            'total_vehicles' => count($vehicles),
            'unique_dispatches' => count($vehiclesByDispatch),
            'processed_dispatches' => $processedDispatches,
            'created_entries' => $createdEntries,
            'skipped_dispatches' => $skippedDispatches
        ]
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logSync('Datenbankfehler: ' . $e->getMessage(), 'ERROR');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logSync('Fehler: ' . $e->getMessage(), 'ERROR');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Interner Fehler',
        'message' => $e->getMessage()
    ]);
}
