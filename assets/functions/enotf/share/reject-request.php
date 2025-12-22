<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Remove ALL existing CSP headers
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');

    // Set permissive headers for FiveM
    header('Content-Security-Policy: frame-ancestors *', true);
    header('Access-Control-Allow-Origin: *');
}


session_start();
require_once __DIR__ . '/../../../../assets/config/config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfragemethode']);
    exit();
}

if (!isset($_SESSION['protfzg']) || !isset($_SESSION['fahrername'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$request_id = $input['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Prüfe ob Anfrage existiert und für dieses Fahrzeug bestimmt ist
    $checkStmt = $pdo->prepare("
        SELECT id FROM intra_edivi_share_requests 
        WHERE id = :request_id 
        AND target_vehicle = :vehicle 
        AND status = 'pending'
    ");
    $checkStmt->execute([
        ':request_id' => $request_id,
        ':vehicle' => $_SESSION['protfzg']
    ]);

    if ($checkStmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Anfrage nicht gefunden oder bereits bearbeitet']);
        exit();
    }

    // Update Status
    $updateStmt = $pdo->prepare("
        UPDATE intra_edivi_share_requests 
        SET status = 'rejected',
            response_at = NOW(),
            response_by = :response_by
        WHERE id = :request_id
    ");

    $updateStmt->execute([
        ':request_id' => $request_id,
        ':response_by' => $_SESSION['fahrername']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Anfrage wurde abgelehnt'
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Fehler beim Ablehnen der Share-Anfrage: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
