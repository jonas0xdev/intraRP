<?php

use App\Auth\Permissions;
use App\Config\ConfigManager;

if (session_status() === PHP_SESSION_NONE) {
    if (isset($_SESSION['userid']) && !isset($_SESSION['permissions'])) {
        require_once __DIR__ . '/database.php';
        $_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid']);
    }
}

// Load configuration from database
require_once __DIR__ . '/database.php';

try {
    $configManager = new ConfigManager($pdo);
    $configManager->loadAndDefineConfig();
} catch (Exception $e) {
    // Fallback to default values if database is not available or table doesn't exist
    error_log("Could not load config from database: " . $e->getMessage());
    
    // BASIS DATEN - Fallback defaults
    if (!defined('API_KEY')) define('API_KEY', 'CHANGE_ME');
    if (!defined('SYSTEM_NAME')) define('SYSTEM_NAME', 'intraRP');
    if (!defined('SYSTEM_COLOR')) define('SYSTEM_COLOR', '#d10000');
    if (!defined('SYSTEM_URL')) define('SYSTEM_URL', 'CHANGE_ME');
    if (!defined('SYSTEM_LOGO')) define('SYSTEM_LOGO', '/assets/img/defaultLogo.webp');
    if (!defined('META_IMAGE_URL')) define('META_IMAGE_URL', '');
    
    // SERVER DATEN
    if (!defined('SERVER_NAME')) define('SERVER_NAME', 'CHANGE_ME');
    if (!defined('SERVER_CITY')) define('SERVER_CITY', 'Musterstadt');
    
    // RP DATEN
    if (!defined('RP_ORGTYPE')) define('RP_ORGTYPE', 'Berufsfeuerwehr');
    if (!defined('RP_STREET')) define('RP_STREET', 'Musterweg 0815');
    if (!defined('RP_ZIP')) define('RP_ZIP', '1337');
    
    // FUNKTIONEN
    if (!defined('CHAR_ID')) define('CHAR_ID', true);
    if (!defined('ENOTF_PREREG')) define('ENOTF_PREREG', true);
    if (!defined('ENOTF_USE_PIN')) define('ENOTF_USE_PIN', true);
    if (!defined('ENOTF_PIN')) define('ENOTF_PIN', '1234');
    if (!defined('ENOTF_REQUIRE_USER_AUTH')) define('ENOTF_REQUIRE_USER_AUTH', false);
    if (!defined('REGISTRATION_MODE')) define('REGISTRATION_MODE', 'open');
    if (!defined('LANG')) define('LANG', 'de');
    if (!defined('BASE_PATH')) define('BASE_PATH', '/');
}