<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `na_nachf` TINYINT(1) DEFAULT NULL;
    AFTER `transportziel`;
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
    ADD COLUMN `ebesonderheiten` TEXT DEFAULT NULL;
    AFTER `na_nachf`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
