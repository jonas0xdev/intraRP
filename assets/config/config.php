<?php
// Autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Permissions;

if (!session_status() === PHP_SESSION_NONE) {
    // Initialisiere Permissions für eingeloggte User
    if (isset($_SESSION['userid']) && !isset($_SESSION['permissions'])) {
        require_once __DIR__ . '/database.php'; // Falls noch nicht geladen
        $_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid']);
    }
}

// BASIS DATEN
define('SYSTEM_NAME', 'intraRP'); // Eigenname des Intranets
define('SYSTEM_VERSION', '0.4.4'); // Versionsnummer
define('SYSTEM_COLOR', '#d10000'); // Hauptfarbe des Systems
define('SYSTEM_URL', 'CHANGE_ME'); // Domain des Systems
define('SYSTEM_LOGO', '/assets/img/defaultLogo.webp'); // Ort des Logos (entweder als relativer Pfad oder Link)
define('META_IMAGE_URL', ''); // Ort des Bildes, welches in der Link-Vorschau angezeigt werden soll (immer als Link angeben!)
// SERVER DATEN
define('SERVER_NAME', 'CHANGE_ME'); // Name des Servers
define('SERVER_CITY', 'Musterstadt'); // Name der Stadt in welcher der Server spielt
// RP DATEN
define('RP_ORGTYPE', 'Berufsfeuerwehr'); // Art/Name der Organisation
define('RP_STREET', 'Musterweg 0815'); // Straße der Organisation
define('RP_ZIP', '1337'); // PLZ der Organisation
// FUNKTIONEN
define('CHAR_ID', true); // Wird eine eindeutige Charakter-ID verwendet? (true = ja, false = nein)
define('ENOTF_PREREG', true); // Wird das Voranmeldungssystem des eNOTF verwendet? (true = ja, false = nein)
define('LANG', 'de'); // Sprache des Systems (de = Deutsch, en = Englisch) // AKTUELL OHNE FUNKTION!
define('BASE_PATH', '/'); // Basis-Pfad des Systems (z.B. /intraRP/ für https://domain.de/intraRP/)