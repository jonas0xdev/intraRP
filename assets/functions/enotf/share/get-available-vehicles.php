<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['protfzg'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$current_vehicle = $_SESSION['protfzg'];

try {
    // Lade alle Rettungsdienstfahrzeuge außer dem aktuellen (rd_type <> 0)
    $stmt = $pdo->prepare("
        SELECT identifier, name, kennzeichen, rd_type 
        FROM intra_fahrzeuge 
        WHERE identifier != :current_vehicle 
        AND rd_type <> 0
        AND active = 1
        ORDER BY name ASC, identifier ASC
    ");
    $stmt->execute([':current_vehicle' => $current_vehicle]);

    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles
    ]);
} catch (PDOException $e) {
    error_log("Fehler beim Laden der Fahrzeuge: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
