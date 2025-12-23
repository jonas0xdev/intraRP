<?php
try {
    $pdo->exec("ALTER TABLE intra_fire_incidents ADD COLUMN incident_number VARCHAR(50) NULL AFTER id");
    $pdo->exec("ALTER TABLE intra_fire_incidents ADD UNIQUE KEY uniq_incident_number (incident_number)");
} catch (PDOException $e) {
    echo $e->getMessage();
}
