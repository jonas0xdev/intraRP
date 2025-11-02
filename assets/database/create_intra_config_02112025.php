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
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
