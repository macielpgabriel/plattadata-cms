-- Migration: Standardize companies columns used by admin analytics comparison
-- Date: 2026-04-10

ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS views INT UNSIGNED NOT NULL DEFAULT 0 AFTER query_failures,
    ADD COLUMN IF NOT EXISTS risk_level ENUM('baixo', 'medio', 'alto', 'critico') NULL AFTER views,
    ADD COLUMN IF NOT EXISTS credit_score TINYINT UNSIGNED NULL AFTER risk_level;

CREATE INDEX IF NOT EXISTS idx_companies_credit_score ON companies (credit_score);
