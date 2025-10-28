<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS intra_support_passwords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        support_token VARCHAR(64) UNIQUE NOT NULL COMMENT 'Eindeutiger Token für Support-Login',
        hashed_password VARCHAR(255) NOT NULL COMMENT 'Gehashte Ticket-ID',
        ticket_id VARCHAR(50) NOT NULL COMMENT 'Lesbare Ticket-ID (z.B. intrarp-123)',
        created_by INT NOT NULL COMMENT 'User-ID des Admins der das Passwort erstellt hat',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL COMMENT 'Ablaufzeitpunkt',
        used_at DATETIME NULL COMMENT 'Zeitpunkt der Verwendung',
        used BOOLEAN DEFAULT FALSE COMMENT 'Wurde bereits verwendet',
        user_agent TEXT NULL COMMENT 'Browser Info bei Erstellung',
        notes TEXT NULL COMMENT 'Notizen zum Support-Fall',
        INDEX idx_token (support_token),
        INDEX idx_expires (expires_at),
        INDEX idx_used (used),
        INDEX idx_created_by (created_by),
        FOREIGN KEY (created_by) REFERENCES intra_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;
    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}

try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS intra_support_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        support_password_id INT NOT NULL COMMENT 'Referenz zum Support-Passwort',
        session_id VARCHAR(128) NOT NULL COMMENT 'Session-ID',
        login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        logout_time DATETIME NULL COMMENT 'Zeitpunkt des Logouts',
        user_agent TEXT NULL COMMENT 'Browser Info bei Login',
        actions_performed INT DEFAULT 0 COMMENT 'Anzahl durchgeführter Aktionen',
        last_activity DATETIME NULL COMMENT 'Zeitpunkt der letzten Aktivität',
        UNIQUE KEY unique_session (session_id),
        INDEX idx_session (session_id),
        INDEX idx_login_time (login_time),
        INDEX idx_support_password (support_password_id),
        FOREIGN KEY (support_password_id) REFERENCES intra_support_passwords(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;
    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}

try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS intra_support_actions_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        support_session_id INT NOT NULL COMMENT 'Referenz zur Support-Session',
        action_type VARCHAR(50) NOT NULL COMMENT 'Art der Aktion (z.B. user_updated)',
        action_description TEXT NOT NULL COMMENT 'Beschreibung der Aktion',
        affected_table VARCHAR(100) NULL COMMENT 'Betroffene Tabelle',
        affected_record_id INT NULL COMMENT 'Betroffener Datensatz',
        old_value TEXT NULL COMMENT 'Alter Wert (JSON)',
        new_value TEXT NULL COMMENT 'Neuer Wert (JSON)',
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (support_session_id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_action_type (action_type),
        FOREIGN KEY (support_session_id) REFERENCES intra_support_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;
    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}

try {
    $sql = <<<SQL
    CREATE EVENT IF NOT EXISTS cleanup_expired_support_passwords
        ON SCHEDULE EVERY 1 HOUR
        DO
        DELETE FROM intra_support_passwords 
        WHERE expires_at < NOW() 
        AND used = FALSE;
    SQL;
    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
