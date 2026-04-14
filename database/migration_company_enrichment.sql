-- Add enrichment fields to companies table

ALTER TABLE companies 
ADD COLUMN website VARCHAR(500) NULL AFTER capital_social,
ADD COLUMN facebook VARCHAR(500) NULL,
ADD COLUMN instagram VARCHAR(500) NULL,
ADD COLUMN linkedin VARCHAR(500) NULL,
ADD COLUMN employees_estimate VARCHAR(20) NULL,
ADD COLUMN enriched_at DATETIME NULL,
ADD INDEX idx_website (website),
ADD INDEX idx_employees_estimate (employees_estimate);
