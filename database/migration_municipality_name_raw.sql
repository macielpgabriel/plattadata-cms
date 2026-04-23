<?php

declare(strict_types=1);

$db = Database::connection();

$db->exec("ALTER TABLE municipalities ADD COLUMN name_raw VARCHAR(120) NULL AFTER name");

$db->exec("ALTER TABLE municipalities MODIFY COLUMN ibge_code INT UNSIGNED NULL");

$db->exec("ALTER TABLE municipalities ADD INDEX idx_municipality_raw (name_raw)");

$db->exec("ALTER TABLE municipalities DROP FOREIGN KEY fk_municipality_state");

echo "Migration concluída: name_raw adicionado, ibge_code agora nullable\n";