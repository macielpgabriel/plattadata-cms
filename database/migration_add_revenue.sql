-- Migration: add_revenue_to_companies
-- Add revenue column to companies table for dynamic revenue rankings
-- NOTA: Esta migração é executada automaticamente pelo SetupService
-- Mantida apenas para referência

-- ALTER TABLE companies ADD COLUMN revenue DECIMAL(18,2) NULL AFTER capital_social;
-- ALTER TABLE companies ADD INDEX idx_companies_revenue (revenue);