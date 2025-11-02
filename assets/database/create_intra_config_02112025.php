<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `var` varchar(255) NOT NULL,
        `value` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  SQL;

    $pdo->exec($sql);

    // Insert default configuration data
    $insertSql = <<<SQL
    INSERT IGNORE INTO `intra_config` (`id`, `var`, `value`, `description`) VALUES
    (1, 'API_KEY', 'CHANGE_ME', 'Wird automatisch beim Setup erstellt, sonst selbst einen sicheren Key festlegen'),
    (2, 'SYSTEM_NAME', 'intraRP', 'Eigenname des Intranets'),
    (3, 'SYSTEM_COLOR', '#d10000', 'Hauptfarbe des Systems'),
    (4, 'SYSTEM_URL', 'CHANGE_ME', 'Domain des Systems'),
    (5, 'SYSTEM_LOGO', '/assets/img/defaultLogo.webp', 'Ort des Logos (entweder als relativer Pfad oder Link)'),
    (6, 'META_IMAGE_URL', NULL, 'Ort des Bildes, welches in der Link-Vorschau angezeigt werden soll (immer als Link angeben!)'),
    (7, 'SERVER_NAME', 'CHANGE_ME', 'Name des Servers'),
    (8, 'SERVER_CITY', 'Musterstadt', 'Name der Stadt in welcher der Server spielt'),
    (9, 'RP_ORGTYPE', 'Berufsfeuerwehr', 'Art/Name der Organisation'),
    (10, 'RP_STREET', 'Musterweg 0815', 'Straße der Organisation'),
    (11, 'RP_ZIP', '1337', 'PLZ der Organisation'),
    (12, 'CHAR_ID', 'true', 'Wird eine eindeutige Charakter-ID verwendet? (true = ja, false = nein)'),
    (13, 'ENOTF_PREREG', 'true', 'Wird das Voranmeldungssystem des eNOTF verwendet? (true = ja, false = nein)'),
    (14, 'ENOTF_USE_PIN', 'true', 'Wird die PIN-Funktion des eNOTF verwendet? (true = ja, false = nein)'),
    (15, 'ENOTF_PIN', '1234', 'PIN für den Zugang zum eNOTF - 4-6 Zahlen (nur relevant, wenn ENOTF_USE_PIN auf true gesetzt ist)'),
    (16, 'LANG', 'de', 'Sprache des Systems (de = Deutsch, en = Englisch) // AKTUELL OHNE FUNKTION!'),
    (17, 'BASE_PATH', '/', 'Basis-Pfad des Systems (z.B. /intraRP/ für https://domain.de/intraRP/)')
    ON DUPLICATE KEY UPDATE `id` = `id`;
  SQL;

    $pdo->exec($insertSql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
