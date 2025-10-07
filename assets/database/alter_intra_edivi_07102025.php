<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `diagnose_haupt` TEXT DEFAULT NULL 
    AFTER `medis`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `diagnose_weitere` TEXT DEFAULT NULL 
    AFTER `diagnose_haupt`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `psych` TEXT DEFAULT NULL 
    AFTER `medis`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
