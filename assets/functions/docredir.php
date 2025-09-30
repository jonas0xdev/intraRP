<?php
// assets/functions/docredir.php
// Zentrale Routing-Datei für alle Dokumente

require_once __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../src/Documents/DocumentRenderer.php';

use App\Documents\DocumentRenderer;

if (!isset($_GET['docid'])) {
    header("Location: " . BASE_PATH . "404.php");
    exit;
}

$docid = $_GET['docid'];

// Lade Dokument-Informationen
$stmt = $pdo->prepare("SELECT type, template_id FROM intra_mitarbeiter_dokumente WHERE docid = :docid");
$stmt->execute(['docid' => $docid]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    header("Location: " . BASE_PATH . "404.php");
    exit;
}

// Routing-Logik
if ($doc['type'] == 99 && $doc['template_id']) {
    // Custom Template Dokument - verwende den Renderer
    try {
        $renderer = new DocumentRenderer($pdo);
        echo $renderer->renderDocument($docid);
    } catch (Exception $e) {
        echo "<h1>Fehler</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    // Legacy Dokumente - alte Routing-Logik
    $routes = [
        0 => 'urkunden/ernennung.php',
        1 => 'urkunden/befoerderung.php',
        2 => 'urkunden/entlassung.php',
        3 => 'vertraege/ausbildung.php',
        5 => 'zertifikate/ausbildung.php',
        6 => 'zertifikate/lehrgang.php',
        7 => 'zertifikate/fachlehrgang.php',
        10 => 'schreiben/abmahnung.php',
        11 => 'schreiben/dienstenthebung.php',
        12 => 'schreiben/dienstentfernung.php',
        13 => 'schreiben/kuendigung.php',
    ];

    if (isset($routes[$doc['type']])) {
        $_GET['dok'] = $docid; // Für Kompatibilität mit alten Dateien
        include __DIR__ . '/../../dokumente/' . $routes[$doc['type']];
    } else {
        header("Location: " . BASE_PATH . "404.php");
        exit;
    }
}
