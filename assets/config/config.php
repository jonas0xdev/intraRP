<?php
// Autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Permissions;

if (session_status() === PHP_SESSION_NONE) {
    if (isset($_SESSION['userid']) && !isset($_SESSION['permissions'])) {
        require_once __DIR__ . '/database.php';
        $_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid']);
    }
}

require_once __DIR__ . '/database.php';
$stmt = $pdo->prepare("SELECT `var`, `value` FROM intra_config");
$stmt->execute();
$configData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

define('API_KEY', $configData['API_KEY'] ?? 'CHANGE_ME');
define('SYSTEM_NAME', $configData['SYSTEM_NAME'] ?? 'intraRP');
define('SYSTEM_COLOR', $configData['SYSTEM_COLOR'] ?? '#d10000');
define('SYSTEM_URL', $configData['SYSTEM_URL'] ?? 'CHANGE_ME');
define('SYSTEM_LOGO', $configData['SYSTEM_LOGO'] ?? '/assets/img/defaultLogo.webp');
define('META_IMAGE_URL', $configData['META_IMAGE_URL'] ?? '');
define('SERVER_NAME', $configData['SERVER_NAME'] ?? 'CHANGE_ME');
define('SERVER_CITY', $configData['SERVER_CITY'] ?? 'Musterstadt');
define('RP_ORGTYPE', $configData['RP_ORGTYPE'] ?? 'Berufsfeuerwehr');
define('RP_STREET', $configData['RP_STREET'] ?? 'Musterweg 0815');
define('RP_ZIP', $configData['RP_ZIP'] ?? '1337');
define('CHAR_ID', filter_var($configData['CHAR_ID'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('ENOTF_PREREG', filter_var($configData['ENOTF_PREREG'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('ENOTF_USE_PIN', filter_var($configData['ENOTF_USE_PIN'] ?? '1', FILTER_VALIDATE_BOOLEAN));
define('ENOTF_PIN', $configData['ENOTF_PIN'] ?? '1234');
define('LANG', $configData['LANG'] ?? 'de');
define('BASE_PATH', $configData['BASE_PATH'] ?? '/');
