<?php

/**
 * Migration: migrate_config_to_db_02112025.php
 * Migriert bestehende Config-Werte aus alter config.php in die Datenbank
 */

echo "   → Migriere Config-Werte in Datenbank...\n";

// Prüfe, ob config.php existiert und alte Struktur hat
$configPath = $projectRoot . '/assets/config/config.php';
if (!file_exists($configPath)) {
    echo "   ⚠️  Keine config.php gefunden, überspringe Migration\n";
    return;
}

$configContent = file_get_contents($configPath);

// Prüfe, ob es die alte Struktur ist (mit define() Statements)
if (strpos($configContent, "define('API_KEY'") === false) {
    echo "   ℹ️  Config bereits im neuen Format, überspringe Migration\n";
    return;
}

// Funktion zum Extrahieren von Config-Werten
function extractConfigValue($content, $key)
{
    $pattern = "/define\('{$key}',\s*'([^']*)'\)/";
    if (preg_match($pattern, $content, $matches)) {
        return $matches[1];
    }

    // Auch boolean Werte unterstützen
    $pattern = "/define\('{$key}',\s*(true|false)\)/";
    if (preg_match($pattern, $content, $matches)) {
        return $matches[1] === 'true' ? '1' : '0';
    }

    return null;
}

// Config-Werte aus der Datei extrahieren
$configValues = [
    'API_KEY' => extractConfigValue($configContent, 'API_KEY'),
    'SYSTEM_NAME' => extractConfigValue($configContent, 'SYSTEM_NAME'),
    'SYSTEM_COLOR' => extractConfigValue($configContent, 'SYSTEM_COLOR'),
    'SYSTEM_URL' => extractConfigValue($configContent, 'SYSTEM_URL'),
    'SYSTEM_LOGO' => extractConfigValue($configContent, 'SYSTEM_LOGO'),
    'META_IMAGE_URL' => extractConfigValue($configContent, 'META_IMAGE_URL'),
    'SERVER_NAME' => extractConfigValue($configContent, 'SERVER_NAME'),
    'SERVER_CITY' => extractConfigValue($configContent, 'SERVER_CITY'),
    'RP_ORGTYPE' => extractConfigValue($configContent, 'RP_ORGTYPE'),
    'RP_STREET' => extractConfigValue($configContent, 'RP_STREET'),
    'RP_ZIP' => extractConfigValue($configContent, 'RP_ZIP'),
    'CHAR_ID' => extractConfigValue($configContent, 'CHAR_ID'),
    'ENOTF_PREREG' => extractConfigValue($configContent, 'ENOTF_PREREG'),
    'ENOTF_USE_PIN' => extractConfigValue($configContent, 'ENOTF_USE_PIN'),
    'ENOTF_PIN' => extractConfigValue($configContent, 'ENOTF_PIN'),
    'LANG' => extractConfigValue($configContent, 'LANG'),
    'BASE_PATH' => extractConfigValue($configContent, 'BASE_PATH'),
];

// Nur Werte übertragen, die auch gefunden wurden
$migratedCount = 0;
$stmt = $pdo->prepare("INSERT INTO intra_config (`var`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

foreach ($configValues as $key => $value) {
    if ($value !== null) {
        try {
            $stmt->execute([$key, $value]);
            $migratedCount++;
            echo "   ✓ {$key} migriert\n";
        } catch (Exception $e) {
            echo "   ⚠️  Fehler bei {$key}: " . $e->getMessage() . "\n";
        }
    }
}

echo "   ✓ {$migratedCount} Config-Werte in Datenbank übertragen\n";
