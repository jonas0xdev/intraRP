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

/**
 * Verarbeitet abgeschlossene Einsätze (Dispatch Logs)
 * Wird nur zur Validierung verwendet - keine Speicherung nötig
 */
function handleDispatchLogs($data, $pdo)
{
    try {
        logSync('Dispatch-Log-Sync empfangen (keine Speicherung erforderlich)', 'INFO');

        $missions = $data['missions'] ?? [];

        if (empty($missions)) {
            logSync('Keine Einsätze in der Anfrage', 'INFO');
            echo json_encode(['success' => true, 'message' => 'Keine Einsätze zu verarbeiten', 'processed' => 0]);
            return;
        }

        logSync('Es wurden ' . count($missions) . ' abgeschlossene Einsätze empfangen (werden nicht gespeichert)', 'INFO');

        echo json_encode([
            'success' => true,
            'type' => 'dispatch_logs',
            'processed' => count($missions),
            'total_received' => count($missions),
            'message' => 'Dispatch-Logs empfangen, Statusmeldungen werden über Status-Sync verarbeitet'
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei Dispatch-Log-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Dispatch-Log-Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Verarbeitet Echtzeit-Statusmeldungen und aktualisiert intra_edivi
 */
function handleStatusUpdates($data, $pdo)
{
    try {
        logSync('Starte Status-Update-Verarbeitung', 'INFO');

        $statuses = $data['statuses'] ?? [];

        if (empty($statuses)) {
            logSync('Keine Statusmeldungen in der Anfrage', 'INFO');
            echo json_encode(['success' => true, 'message' => 'Keine Statusmeldungen zu verarbeiten', 'processed' => 0]);
            return;
        }

        logSync('Es wurden ' . count($statuses) . ' Statusmeldungen empfangen', 'INFO');

        $updated = 0;
        $notFound = 0;
        $successfulIds = [];

        foreach ($statuses as $status) {
            $statusValue = $status['status'];
            $missionNumber = $status['mission_number'];
            $sender = $status['sender'];
            $statusId = $status['id'];

            // Konvertiere Zeit-Format
            $statusTime = DateTime::createFromFormat('d.m.Y H:i', $status['timestamp']);
            if (!$statusTime) {
                logSync("Ungültiges Zeitformat für Status-Update $statusId", 'WARNING');
                continue;
            }

            logSync("Verarbeite Status '$statusValue' (Typ: " . gettype($statusValue) . ") für Einsatz $missionNumber von Fahrzeug $sender (ID: $statusId)", 'DEBUG');

            // Mapping: Status -> Spaltenname
            $statusColumn = 's' . $statusValue; // s1, s2, s3, s4, s7, s8

            // Schritt 1: Finde das Fahrzeug-Kennzeichen (identifier) in intra_fahrzeuge
            $findVehicleStmt = $pdo->prepare("
                SELECT identifier, rd_type 
                FROM intra_fahrzeuge 
                WHERE name = :name 
                LIMIT 1
            ");
            $findVehicleStmt->execute([':name' => $sender]);
            $vehicleRow = $findVehicleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$vehicleRow) {
                $notFound++;
                logSync("Fahrzeug $sender nicht in intra_fahrzeuge gefunden - wird beim nächsten Mal erneut versucht (ID: $statusId)", 'WARNING');
                continue;
            }

            $vehicleIdentifier = $vehicleRow['identifier'];
            $rdType = intval($vehicleRow['rd_type'] ?? 0);

            logSync("Fahrzeug $sender hat Kennzeichen: $vehicleIdentifier (rd_type=$rdType)", 'DEBUG');

            // Schritt 2: Suche in intra_edivi nach dem Einsatz, wo dieses Kennzeichen in fzg_na oder fzg_transp steht
            $findEnrStmt = $pdo->prepare("
                SELECT enr 
                FROM intra_edivi 
                WHERE (enr = :mission OR enr LIKE :mission_pattern)
                  AND (fzg_na = :vehicle_id1 OR fzg_transp = :vehicle_id2)
                LIMIT 1
            ");
            $findEnrStmt->execute([
                ':mission' => $missionNumber,
                ':mission_pattern' => $missionNumber . '_%',
                ':vehicle_id1' => $vehicleIdentifier,
                ':vehicle_id2' => $vehicleIdentifier
            ]);
            $enrRow = $findEnrStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrRow) {
                $notFound++;
                logSync("Einsatz mit Kennzeichen $vehicleIdentifier für Mission $missionNumber nicht in intra_edivi gefunden - wird beim nächsten Mal erneut versucht (ID: $statusId)", 'WARNING');
                continue;
            }

            $enr = $enrRow['enr'];
            $formattedTime = $statusTime->format('Y-m-d H:i:s');

            logSync("Fahrzeug $sender (Kennzeichen: $vehicleIdentifier) ist Einsatz $enr zugeordnet", 'DEBUG');

            // Schritt 3: Update NUR diesen spezifischen Einsatz
            // Status C ist die Alarmierung - wird in salarm gespeichert
            // Status 1, 2, 3, 4, 7, 8 werden in s1-s8 gespeichert

            if (strtoupper(trim($statusValue)) === 'C') {
                // Status C (Alarmierung) -> nur salarm
                logSync("Versuche salarm zu setzen für ENR $enr mit Zeit $formattedTime", 'DEBUG');
                $sql = "UPDATE intra_edivi SET salarm = :salarm WHERE enr = :enr";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute([
                    ':salarm' => $formattedTime,
                    ':enr' => $enr
                ]);
                $rowCount = $updateStmt->rowCount();
                logSync("Status C (Alarmierung) für Fahrzeug $sender in Einsatz $enr: salarm = $formattedTime, rowCount = $rowCount (ID: $statusId)", 'INFO');
            } else {
                // Status 1-8 -> in s1-s8 Spalten
                $allowedColumns = ['s1', 's2', 's3', 's4', 's7', 's8'];
                if (!in_array($statusColumn, $allowedColumns)) {
                    logSync("Ungültige Status-Spalte: $statusColumn (ID: $statusId)", 'WARNING');
                    continue;
                }

                $sql = "UPDATE intra_edivi SET $statusColumn = :status_time WHERE enr = :enr";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute([
                    ':status_time' => $formattedTime,
                    ':enr' => $enr
                ]);
                logSync("Status $statusValue für Fahrzeug $sender in Einsatz $enr: $statusColumn = $formattedTime (ID: $statusId)", 'INFO');
            }

            $updatedThisStatus = false;
            if ($updateStmt->rowCount() > 0) {
                $updated++;
                $updatedThisStatus = true;
            } else {
                logSync("Status $statusValue für Einsatz $enr war bereits gesetzt oder Spalte existiert nicht", 'WARNING');
            }

            // Nur zu successfulIds hinzufügen, wenn mindestens ein Update erfolgreich war
            if ($updatedThisStatus) {
                $successfulIds[] = $statusId;
            }
        }

        logSync("Status-Update-Verarbeitung abgeschlossen: $updated intra_edivi-Updates, $notFound nicht gefunden von " . count($statuses) . " Statusmeldungen", 'INFO');

        echo json_encode([
            'success' => true,
            'type' => 'status_updates',
            'updated_edivi' => $updated,
            'not_found' => $notFound,
            'successful_ids' => $successfulIds,
            'total_received' => count($statuses)
        ]);
    } catch (Exception $e) {
        logSync('Fehler bei Status-Update-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Status-Update-Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
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

    // Routing basierend auf type
    if (isset($receivedData['type'])) {
        switch ($receivedData['type']) {
            case 'dispatch_logs':
                handleDispatchLogs($receivedData, $pdo);
                exit;
            case 'status_updates':
                handleStatusUpdates($receivedData, $pdo);
                exit;
            default:
                logSync('Unbekannter Sync-Typ: ' . $receivedData['type'], 'WARNING');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unbekannter Sync-Typ']);
                exit;
        }
    }

    // Normale Fahrzeug-Synchronisierung
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
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_na, edatum, ezeit, prot_by, created_at) 
                    VALUES (:enr, :fzg_na, :edatum, :ezeit, :prot_by, NOW())
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_na' => $vehicle['identifier'],
                    ':edatum' => $currentDate,
                    ':ezeit' => $currentTime,
                    ':prot_by' => 1
                ]);

                logSync("Notarzt-Eintrag $enrToUse erstellt: {$vehicle['identifier']}", 'INFO');
            } else {
                $currentDate = date('Y-m-d');
                $currentTime = date('H:i');

                $insertStmt = $pdo->prepare("
                    INSERT INTO intra_edivi (enr, fzg_transp, edatum, ezeit, prot_by, created_at) 
                    VALUES (:enr, :fzg_transp, :edatum, :ezeit, :prot_by, NOW())
                ");
                $insertStmt->execute([
                    ':enr' => $enrToUse,
                    ':fzg_transp' => $vehicle['identifier'],
                    ':edatum' => $currentDate,
                    ':ezeit' => $currentTime,
                    ':prot_by' => 0
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
