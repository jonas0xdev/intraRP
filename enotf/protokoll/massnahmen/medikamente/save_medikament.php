<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../../assets/config/config.php';
require __DIR__ . '/../../../../assets/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_POST['enr']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo "Fehlende Parameter";
    exit();
}

$enr = $_POST['enr'];
$action = $_POST['action'];

try {
    if ($action === 'add') {
        if (!isset($_POST['medikament'])) {
            http_response_code(400);
            echo "Medikament-Daten fehlen";
            exit();
        }

        $medikamentData = json_decode($_POST['medikament'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo "Ungültiges JSON-Format";
            exit();
        }

        // Validierung der Medikament-Daten
        $requiredFields = ['wirkstoff', 'zeit', 'applikation', 'dosierung', 'einheit'];
        foreach ($requiredFields as $field) {
            if (!isset($medikamentData[$field]) || empty($medikamentData[$field])) {
                http_response_code(400);
                echo "Pflichtfeld fehlt: $field";
                exit();
            }
        }

        // Erlaubte Werte validieren
        $allowedWirkstoffe = [
            'Acetylsalicylsäure',
            'Adenosin',
            'Alteplase',
            'Amiodaron',
            'Atropinsulfat',
            'Butylscopolamin',
            'Calciumgluconat',
            'Ceftriaxon',
            'Dimenhydrinat',
            'Dimetinden',
            'Epinephrin',
            'Esketamin',
            'Fentanyl',
            'Flumazenil',
            'Furosemid',
            'Gelatinepolysuccinat',
            'Glukose',
            'Heparin',
            'Humanblut',
            'Hydroxycobolamin',
            'Ipratropiumbromid',
            'Lidocain',
            'Lorazepam',
            'Metamizol',
            'Metoprolol',
            'Midazolam',
            'Morphin',
            'Naloxon',
            'Natriumhydrogencarbonat',
            'Natriumthiosulfat',
            'Nitroglycerin',
            'Norepinephrin',
            'Ondansetron',
            'Paracetamol',
            'Piritramid',
            'Prednisolon',
            'Propofol',
            'Reproterol',
            'Rocuronium',
            'Salbutamol',
            'Succinylcholin',
            'Sufentanil',
            'Theodrenalin-Cafedrin',
            'Thiopental',
            'Tranexamsäure',
            'Urapidil',
            'Vollelektrolytlösung'
        ];

        $allowedApplikationen = ['i.v.', 'i.o.', 'i.m.', 's.c.', 's.l.', 'p.o.'];
        $allowedEinheiten = ['mcg', 'mg', 'g', 'ml', 'IE'];

        if (!in_array($medikamentData['wirkstoff'], $allowedWirkstoffe)) {
            http_response_code(400);
            echo "Ungültiger Wirkstoff";
            exit();
        }

        if (!in_array($medikamentData['applikation'], $allowedApplikationen)) {
            http_response_code(400);
            echo "Ungültige Applikationsart";
            exit();
        }

        if (!in_array($medikamentData['einheit'], $allowedEinheiten)) {
            http_response_code(400);
            echo "Ungültige Einheit";
            exit();
        }

        // Zeit-Format validieren (HH:MM:SS)
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $medikamentData['zeit'])) {
            http_response_code(400);
            echo "Ungültiges Zeitformat";
            exit();
        }

        // Dosierung validieren (nur Zahlen und Punkt/Komma)
        if (!preg_match('/^[0-9]+([.,][0-9]+)?$/', $medikamentData['dosierung'])) {
            http_response_code(400);
            echo "Ungültige Dosierung";
            exit();
        }

        // Aktuelle Medikamente aus der Datenbank laden
        $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['enr' => $enr]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $medikamente = [];
        if ($result && !empty($result['medis']) && $result['medis'] !== '0') {
            $medikamente = json_decode($result['medis'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $medikamente = [];
            }
        }

        // Neues Medikament hinzufügen
        $medikamente[] = $medikamentData;

        // In Datenbank speichern
        $medikamenteJson = json_encode($medikamente, JSON_UNESCAPED_UNICODE);
        $updateQuery = "UPDATE intra_edivi SET medis = :medis, last_edit = NOW() WHERE enr = :enr";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute(['medis' => $medikamenteJson, 'enr' => $enr]);

        echo "Medikament erfolgreich hinzugefügt";
    } elseif ($action === 'delete') {
        if (!isset($_POST['timestamp'])) {
            http_response_code(400);
            echo "Timestamp fehlt";
            exit();
        }

        $timestamp = $_POST['timestamp'];

        // Aktuelle Medikamente aus der Datenbank laden
        $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['enr' => $enr]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $medikamente = [];
        if ($result && !empty($result['medis']) && $result['medis'] !== '0') {
            $medikamente = json_decode($result['medis'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo "Fehler beim Laden der Medikamente";
                exit();
            }
        }

        // Medikament mit entsprechendem Timestamp entfernen
        $medikamente = array_filter($medikamente, function ($med) use ($timestamp) {
            return $med['timestamp'] != $timestamp;
        });

        // Array neu indizieren
        $medikamente = array_values($medikamente);

        // In Datenbank speichern
        $medikamenteJson = empty($medikamente) ? '0' : json_encode($medikamente, JSON_UNESCAPED_UNICODE);
        $updateQuery = "UPDATE intra_edivi SET medis = :medis, last_edit = NOW() WHERE enr = :enr";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute(['medis' => $medikamenteJson, 'enr' => $enr]);

        echo "Medikament erfolgreich gelöscht";
    } else {
        http_response_code(400);
        echo "Ungültige Aktion";
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo "Datenbankfehler";
    error_log("Database error in save_medikament.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo "Unerwarteter Fehler";
    error_log("Error in save_medikament.php: " . $e->getMessage());
}
