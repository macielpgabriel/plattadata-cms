-- Migration: Add GDP Sectors to Municipalities and States
-- Adds columns for Agriculture, Industry, Services and Public Admin GDP components

ALTER TABLE municipalities
    ADD COLUMN IF NOT EXISTS gdp_agri DECIMAL(18,2) NULL AFTER gdp_per_capita,
    ADD COLUMN IF NOT EXISTS gdp_industry DECIMAL(18,2) NULL AFTER gdp_agri,
    ADD COLUMN IF NOT EXISTS gdp_services DECIMAL(18,2) NULL AFTER gdp_industry,
    ADD COLUMN IF NOT EXISTS gdp_admin DECIMAL(18,2) NULL AFTER gdp_services;

ALTER TABLE states
    ADD COLUMN IF NOT EXISTS gdp_agri DECIMAL(18,2) NULL AFTER gdp_per_capita,
    ADD COLUMN IF NOT EXISTS gdp_industry DECIMAL(18,2) NULL AFTER gdp_agri,
    ADD COLUMN IF NOT EXISTS gdp_services DECIMAL(18,2) NULL AFTER gdp_industry,
    ADD COLUMN IF NOT EXISTS gdp_admin DECIMAL(18,2) NULL AFTER gdp_services;
