<?php
// Output buffering starten um ungewollte Ausgaben zu vermeiden
ob_start();

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';

// Alle bisherigen Ausgaben verwerfen
ob_end_clean();

header('Content-Type: application/json');

// Nur für eingeloggte User
// if (!isset($_SESSION['userid'])) {
//     echo json_encode(['error' => 'Unauthorized']);
//     exit();
// }

$searchTerm = $_GET['search'] ?? '';

try {
    if (empty($searchTerm)) {
        // Alle aktiven POIs zurückgeben
        $stmt = $pdo->prepare("SELECT id, name, strasse, hnr, ort, ortsteil, typ FROM intra_edivi_pois WHERE active = 1 ORDER BY name ASC LIMIT 50");
        $stmt->execute();
    } else {
        // Suche nach Name oder Ort
        $stmt = $pdo->prepare("SELECT id, name, strasse, hnr, ort, ortsteil, typ FROM intra_edivi_pois WHERE active = 1 AND (name LIKE :search OR ort LIKE :search) ORDER BY name ASC LIMIT 50");
        $stmt->execute(['search' => '%' . $searchTerm . '%']);
    }

    $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($pois);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
