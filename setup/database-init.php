<?php
if (getenv('GITHUB_ACTIONS') === 'true' || getenv('CI') === 'true') {
    echo "⚠️  Skipping database setup in CI (GitHub Actions).\n";
    exit(0);
}

function findProjectRoot()
{
    $currentDir = __DIR__;
    $workingDir = getcwd();

    echo "DEBUG: Script-Pfad: $currentDir\n";
    echo "DEBUG: Arbeitsverzeichnis: $workingDir\n";

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

        echo "DEBUG: Teste Root-Kandidat: $candidate\n";

        if (
            file_exists($candidate . '/composer.json') &&
            file_exists($candidate . '/.env') &&
            is_dir($candidate . '/vendor')
        ) {
            echo "✓ Project-Root gefunden: $candidate\n";
            return $candidate;
        }
    }

    throw new Exception("Konnte Project-Root nicht finden. Getestete Pfade: " . implode(', ', $candidates));
}

try {
    $projectRoot = findProjectRoot();
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    exit(1);
}

$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ Autoloader nicht gefunden: $autoloadPath\n";
    exit(1);
}

require_once $autoloadPath;
echo "✓ Autoloader geladen von: $autoloadPath\n";

try {
    $dotenv = Dotenv\Dotenv::createImmutable($projectRoot, null, false);
    $dotenv->load();
    echo "✓ .env geladen von: $projectRoot/.env\n";
} catch (Exception $e) {
    echo "❌ Konnte .env nicht laden: " . $e->getMessage() . "\n";
    echo "Suchpfad war: $projectRoot/.env\n";
    exit(1);
}

$requiredVars = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($requiredVars as $var) {
    if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
        echo "❌ Umgebungsvariable $var nicht gesetzt\n";
        exit(1);
    }
}

$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASS'];
$db_name = $_ENV['DB_NAME'];

$dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "✓ Datenbankverbindung erfolgreich\n";
} catch (PDOException $e) {
    echo "❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage() . "\n";
    exit(1);
}

function ensureMigrationsTable(PDO $pdo)
{
    $sql = "CREATE TABLE IF NOT EXISTS intra_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_migration (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
}

function migrationHasRun(PDO $pdo, string $migrationName): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_migrations WHERE migration = ?");
    $stmt->execute([$migrationName]);
    return $stmt->fetchColumn() > 0;
}

function markMigrationAsRun(PDO $pdo, string $migrationName)
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO intra_migrations (migration) VALUES (?)");
    $stmt->execute([$migrationName]);
}

function isTransactionActive(PDO $pdo): bool
{
    return $pdo->inTransaction();
}

function processConfigCache(PDO $pdo, string $projectRoot)
{
    $cacheFile = $projectRoot . '/setup_config_cache.json';

    if (!file_exists($cacheFile)) {
        echo "ℹ️  Keine gecachte Config-Datei gefunden.\n";
        return;
    }

    echo "\n=== Verarbeite gecachte Config-Daten ===\n";

    try {
        $cacheData = json_decode(file_get_contents($cacheFile), true);

        if (!$cacheData || !isset($cacheData['config']) || !isset($cacheData['envConfig'])) {
            echo "⚠️  Gecachte Config-Datei ist ungültig oder beschädigt.\n";
            return;
        }

        $config = $cacheData['config'];
        $timestamp = $cacheData['timestamp'] ?? 'unbekannt';

        echo "✓ Cache-Datei geladen (erstellt: $timestamp)\n";

        // Tabelle erstellen falls nicht vorhanden
        $createTableSql = <<<SQL
        CREATE TABLE IF NOT EXISTS `intra_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `var` varchar(255) NOT NULL,
            `value` varchar(255) DEFAULT NULL,
            `description` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `var` (`var`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;
        $pdo->exec($createTableSql);
        echo "✓ Tabelle intra_config sichergestellt\n";

        // Config-Werte in Datenbank einfügen/aktualisieren
        $insertStmt = $pdo->prepare("
            INSERT INTO `intra_config` (`var`, `value`, `description`) 
            VALUES (:var, :value, :description)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `description` = VALUES(`description`)
        ");

        $configData = [
            ['var' => 'API_KEY', 'value' => $config['API_KEY'], 'description' => 'Wird automatisch beim Setup erstellt, sonst selbst einen sicheren Key festlegen'],
            ['var' => 'SYSTEM_NAME', 'value' => $config['SYSTEM_NAME'], 'description' => 'Eigenname des Intranets'],
            ['var' => 'SYSTEM_COLOR', 'value' => $config['SYSTEM_COLOR'], 'description' => 'Hauptfarbe des Systems'],
            ['var' => 'SYSTEM_URL', 'value' => $config['SYSTEM_URL'], 'description' => 'Domain des Systems'],
            ['var' => 'SYSTEM_LOGO', 'value' => $config['SYSTEM_LOGO'], 'description' => 'Ort des Logos (entweder als relativer Pfad oder Link)'],
            ['var' => 'META_IMAGE_URL', 'value' => $config['META_IMAGE_URL'], 'description' => 'Ort des Bildes, welches in der Link-Vorschau angezeigt werden soll (immer als Link angeben!)'],
            ['var' => 'SERVER_NAME', 'value' => $config['SERVER_NAME'], 'description' => 'Name des Servers'],
            ['var' => 'SERVER_CITY', 'value' => $config['SERVER_CITY'], 'description' => 'Name der Stadt in welcher der Server spielt'],
            ['var' => 'RP_ORGTYPE', 'value' => $config['RP_ORGTYPE'], 'description' => 'Art/Name der Organisation'],
            ['var' => 'RP_STREET', 'value' => $config['RP_STREET'], 'description' => 'Straße der Organisation'],
            ['var' => 'RP_ZIP', 'value' => $config['RP_ZIP'], 'description' => 'PLZ der Organisation'],
            ['var' => 'CHAR_ID', 'value' => $config['CHAR_ID'], 'description' => 'Wird eine eindeutige Charakter-ID verwendet? (true = ja, false = nein)'],
            ['var' => 'ENOTF_PREREG', 'value' => $config['ENOTF_PREREG'], 'description' => 'Wird das Voranmeldungssystem des eNOTF verwendet? (true = ja, false = nein)'],
            ['var' => 'ENOTF_USE_PIN', 'value' => $config['ENOTF_USE_PIN'], 'description' => 'Wird die PIN-Funktion des eNOTF verwendet? (true = ja, false = nein)'],
            ['var' => 'ENOTF_PIN', 'value' => $config['ENOTF_PIN'], 'description' => 'PIN für den Zugang zum eNOTF - 4-6 Zahlen (nur relevant, wenn ENOTF_USE_PIN auf true gesetzt ist)'],
            ['var' => 'LANG', 'value' => 'de', 'description' => 'Sprache des Systems (de = Deutsch, en = Englisch) // AKTUELL OHNE FUNKTION!'],
            ['var' => 'BASE_PATH', 'value' => $config['BASE_PATH'], 'description' => 'Basis-Pfad des Systems (z.B. /intraRP/ für https://domain.de/intraRP/)']
        ];

        $inserted = 0;
        foreach ($configData as $configItem) {
            $insertStmt->execute($configItem);
            $inserted++;
        }

        echo "✓ $inserted Config-Einträge in Datenbank gespeichert\n";

        // Cache-Datei löschen nach erfolgreicher Übertragung
        if (unlink($cacheFile)) {
            echo "✓ Cache-Datei erfolgreich gelöscht\n";
        } else {
            echo "⚠️  Cache-Datei konnte nicht gelöscht werden. Bitte manuell löschen: $cacheFile\n";
        }

        echo "=== Config-Cache erfolgreich verarbeitet ===\n\n";
    } catch (PDOException $e) {
        echo "❌ Fehler beim Verarbeiten des Config-Cache: " . $e->getMessage() . "\n";
        echo "   Die Cache-Datei bleibt erhalten und kann später erneut verarbeitet werden.\n\n";
    } catch (Exception $e) {
        echo "❌ Allgemeiner Fehler: " . $e->getMessage() . "\n\n";
    }
}

ensureMigrationsTable($pdo);

$migrationPath = $projectRoot . '/assets/database';
if (!is_dir($migrationPath)) {
    echo "❌ Migration-Verzeichnis nicht gefunden: $migrationPath\n";
    exit(1);
}

echo "✓ Migration-Verzeichnis: $migrationPath\n\n";

$migrationFiles = [
    // 07.06.2025
    ['file' => 'create_intra_users_roles_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_users_roles_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_users_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_audit_log_07062025.php', 'type' => 'create'],
    ['file' => 'add_foreign_keys_07062025.php', 'type' => 'alter'],
    ['file' => 'create_intra_dashboard_categories_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_dashboard_tiles_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_fahrzeuge_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_qmlog_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_edivi_ziele_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_edivi_ziele_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_dienstgrade_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_dienstgrade_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_fwquali_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_fwquali_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_log_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_mitarbeiter_rdquali_07062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_rdquali_07062025.php', 'type' => 'insert'],
    ['file' => 'create_intra_mitarbeiter_07062025.php', 'type' => 'create'],
    ['file' => 'create_intra_mitarbeiter_dokumente_07062025.php', 'type' => 'create'],
    //['file' => 'create_intra_uploads_07062025.php', 'type' => 'create'],

    // 13.06.2025
    ['file' => 'create_intra_mitarbeiter_fdquali_13062025.php', 'type' => 'create'],
    ['file' => 'insert_intra_mitarbeiter_fdquali_13062025.php', 'type' => 'insert'],

    // 18.06.2025
    ['file' => 'create_intra_edivi_prereg_18062025.php', 'type' => 'create'],

    // 23.06.2025
    ['file' => 'update_intra_edivi_fahrzeuge_23062025.php', 'type' => 'alter'],
    ['file' => 'create_intra_fahrzeuge_beladung_categories_23062025.php', 'type' => 'create'],
    ['file' => 'create_intra_fahrzeuge_beladung_tiles_23062025.php', 'type' => 'create'],
    ['file' => 'update_intra_mitarbeiter_23062025.php', 'type' => 'alter'],
    ['file' => 'update_intra_mitarbeiter_dokumente_23062025.php', 'type' => 'alter'],

    // 06.07.2025
    ['file' => 'update_intra_edivi_06072025.php', 'type' => 'alter'],
    ['file' => 'create_intra_edivi_vitalparameter_einzelwerte_06072025.php', 'type' => 'create'],

    // 03.09.2025
    ['file' => 'alter_intra_edivi_03092025.php', 'type' => 'alter'],

    // 08.09.2025
    ['file' => 'alter_intra_edivi_08092025.php', 'type' => 'alter'],

    // 30.09.2025
    ['file' => 'update_intra_mitarbeiter_dokumente_30092025_templates.php', 'type' => 'alter'],
    ['file' => 'insert_system_templates_30092025.php', 'type' => 'insert'],
    ['file' => 'migrate_existing_documents_30092025.php', 'type' => 'data'],
    ['file' => 'insert_template_fields_30092025.php', 'type' => 'insert'],
    ['file' => 'create_intra_antrag_typen_30092025.php', 'type' => 'create'],
    ['file' => 'create_intra_antrag_felder_30092025.php', 'type' => 'create'],
    ['file' => 'insert_intra_dynamic_antraege_30092025.php', 'type' => 'insert'],
    ['file' => 'create_intra_antraege_30092025.php', 'type' => 'create'],
    ['file' => 'create_intra_antraege_daten_30092025.php', 'type' => 'create'],

    // 03.10.2025
    ['file' => 'alter_intra_mitarbeiter_dokumente_03102025.php', 'type' => 'alter'],

    // 07.10.2025
    ['file' => 'alter_intra_edivi_07102025.php', 'type' => 'alter'],

    // 09.10.2025
    ['file' => 'alter_intra_edivi_09102025.php', 'type' => 'alter'],

    // 13.10.2025
    ['file' => 'alter_intra_edivi_13102025.php', 'type' => 'alter'],

    // 28.10.2025
    ['file' => 'create_intra_support_db_28102025.php', 'type' => 'create'],

    // 02.11.2025
    ['file' => 'alter_intra_mitarbeiter_02112025.php', 'type' => 'alter'],
    ['file' => 'create_intra_config_02112025.php', 'type' => 'create'],
];

$executed = 0;
$skipped = 0;
$alreadyRun = 0;

echo "=== Starte Migrations-Prozess ===\n\n";

foreach ($migrationFiles as $migration) {
    $file = $migration['file'];
    $type = $migration['type'] ?? 'unknown';
    $fullPath = $migrationPath . '/' . $file;

    if (!file_exists($fullPath)) {
        $skipped++;
        echo "⚠️  Migration nicht gefunden: $file\n";
        continue;
    }

    if (migrationHasRun($pdo, $file)) {
        $alreadyRun++;
        echo "⏭️  Bereits ausgeführt: $file\n";
        continue;
    }

    $transactionStarted = false;
    try {
        if (!isTransactionActive($pdo)) {
            $pdo->beginTransaction();
            $transactionStarted = true;
        }

        echo "▶️  Führe aus [$type]: $file\n";
        include $fullPath;

        markMigrationAsRun($pdo, $file);

        if ($transactionStarted && isTransactionActive($pdo)) {
            $pdo->commit();
        }

        $executed++;
        echo "✅ Erfolgreich: $file\n\n";
    } catch (Exception $e) {
        if ($transactionStarted && isTransactionActive($pdo)) {
            $pdo->rollBack();
        }

        echo "❌ Fehlgeschlagen: $file\n";
        echo "   Fehler: " . $e->getMessage() . "\n\n";

        if ($type === 'create' || $type === 'alter') {
            echo "⚠️  Kritischer Fehler bei $type-Migration. Abbruch.\n";
            exit(1);
        }

        echo "⚠️  Überspringe diese Migration und fahre fort...\n\n";
    }
}

echo "\n=== Database Setup Abgeschlossen ===\n";
echo "Project Root: $projectRoot\n";
echo "Neue Migrationen ausgeführt: $executed\n";
echo "Bereits ausgeführt (übersprungen): $alreadyRun\n";
echo "Nicht gefunden: $skipped\n";
echo "Gesamt: " . count($migrationFiles) . " Migrationen\n\n";

// Nach erfolgreichen Migrationen: Gecachte Config-Daten verarbeiten
processConfigCache($pdo, $projectRoot);

exit(0);
