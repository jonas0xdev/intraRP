<?php

/**
 * Update config.php to new database-driven format
 * Called after migrations to replace old define() statements with DB queries
 */

if (getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true') {
    echo "⚠️  Skipping config update in CI (GitHub Actions).\n";
    exit(0);
}

function findProjectRoot()
{
    $currentDir = __DIR__;
    $workingDir = getcwd();

    $candidates = [
        dirname($currentDir),
        dirname($workingDir),
        dirname($workingDir, 2),
        dirname($workingDir, 3),
        dirname($workingDir, 4),
        $workingDir,
        dirname($currentDir, 2),
        dirname($currentDir, 3),
        dirname($currentDir, 4),
    ];

    foreach ($candidates as $candidate) {
        $candidate = realpath($candidate);
        if (!$candidate) continue;

        if (
            file_exists($candidate . '/composer.json') &&
            file_exists($candidate . '/.env') &&
            is_dir($candidate . '/vendor')
        ) {
            return $candidate;
        }
    }

    throw new Exception("Konnte Project-Root nicht finden.");
}

try {
    $projectRoot = findProjectRoot();
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    exit(1);
}

$configPath = $projectRoot . '/assets/config/config.php';

if (!file_exists($configPath)) {
    echo "❌ config.php nicht gefunden: $configPath\n";
    exit(1);
}

$configContent = file_get_contents($configPath);

// Prüfe, ob config.php bereits im neuen Format ist
if (strpos($configContent, '$stmt = $pdo->prepare') !== false) {
    echo "✓ config.php bereits im neuen Format\n";
    exit(0);
}

// Prüfe, ob es die alte Struktur ist
if (strpos($configContent, "define('API_KEY'") === false) {
    echo "⚠️  config.php hat unbekanntes Format, überspringe Update\n";
    exit(0);
}

echo "▶️  Aktualisiere config.php auf neues Format...\n";

// Neue config.php Inhalt
$newConfigContent = '<?php
// Autoloader
require_once __DIR__ . \'/../../vendor/autoload.php\';

use App\\Auth\\Permissions;

if (session_status() === PHP_SESSION_NONE) {
    if (isset($_SESSION[\'userid\']) && !isset($_SESSION[\'permissions\'])) {
        require_once __DIR__ . \'/database.php\';
        $_SESSION[\'permissions\'] = Permissions::retrieveFromDatabase($pdo, $_SESSION[\'userid\']);
    }
}

require_once __DIR__ . \'/database.php\';
$stmt = $pdo->prepare("SELECT `var`, `value` FROM intra_config");
$stmt->execute();
$configData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

define(\'API_KEY\', $configData[\'API_KEY\'] ?? \'CHANGE_ME\');
define(\'SYSTEM_NAME\', $configData[\'SYSTEM_NAME\'] ?? \'intraRP\');
define(\'SYSTEM_COLOR\', $configData[\'SYSTEM_COLOR\'] ?? \'#d10000\');
define(\'SYSTEM_URL\', $configData[\'SYSTEM_URL\'] ?? \'CHANGE_ME\');
define(\'SYSTEM_LOGO\', $configData[\'SYSTEM_LOGO\'] ?? \'/assets/img/defaultLogo.webp\');
define(\'META_IMAGE_URL\', $configData[\'META_IMAGE_URL\'] ?? \'\');
define(\'SERVER_NAME\', $configData[\'SERVER_NAME\'] ?? \'CHANGE_ME\');
define(\'SERVER_CITY\', $configData[\'SERVER_CITY\'] ?? \'Musterstadt\');
define(\'RP_ORGTYPE\', $configData[\'RP_ORGTYPE\'] ?? \'Berufsfeuerwehr\');
define(\'RP_STREET\', $configData[\'RP_STREET\'] ?? \'Musterweg 0815\');
define(\'RP_ZIP\', $configData[\'RP_ZIP\'] ?? \'1337\');
define(\'CHAR_ID\', filter_var($configData[\'CHAR_ID\'] ?? \'1\', FILTER_VALIDATE_BOOLEAN));
define(\'ENOTF_PREREG\', filter_var($configData[\'ENOTF_PREREG\'] ?? \'1\', FILTER_VALIDATE_BOOLEAN));
define(\'ENOTF_USE_PIN\', filter_var($configData[\'ENOTF_USE_PIN\'] ?? \'1\', FILTER_VALIDATE_BOOLEAN));
define(\'ENOTF_PIN\', $configData[\'ENOTF_PIN\'] ?? \'1234\');
define(\'LANG\', $configData[\'LANG\'] ?? \'de\');
define(\'BASE_PATH\', $configData[\'BASE_PATH\'] ?? \'/\');
';

// Backup der alten config.php erstellen
$backupPath = $configPath . '.backup_' . date('Y-m-d_His');
if (copy($configPath, $backupPath)) {
    echo "✓ Backup erstellt: " . basename($backupPath) . "\n";
}

// Neue config.php schreiben
if (file_put_contents($configPath, $newConfigContent)) {
    echo "✓ config.php erfolgreich aktualisiert\n";
    exit(0);
} else {
    echo "❌ Fehler beim Schreiben der config.php\n";
    exit(1);
}
