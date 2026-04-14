-- Safe/idempotent migration for enrichment columns on `companies`.
-- Compatible with environments where direct multi-column ALTER may fail.

SET @db_name := DATABASE();

SET @has_website := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'companies'
      AND column_name = 'website'
);
SET @sql := IF(@has_website = 0,
    'ALTER TABLE companies ADD COLUMN website VARCHAR(500) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_logo_url := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'companies'
      AND column_name = 'logo_url'
);
SET @sql := IF(@has_logo_url = 0,
    'ALTER TABLE companies ADD COLUMN logo_url TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_employees_estimate := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'companies'
      AND column_name = 'employees_estimate'
);
SET @sql := IF(@has_employees_estimate = 0,
    'ALTER TABLE companies ADD COLUMN employees_estimate VARCHAR(20) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_enriched_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @db_name
      AND table_name = 'companies'
      AND column_name = 'enriched_at'
);
SET @sql := IF(@has_enriched_at = 0,
    'ALTER TABLE companies ADD COLUMN enriched_at DATETIME NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

