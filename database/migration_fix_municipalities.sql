-- Migration: Fix Municipalities Table
-- Adds missing columns slug and views to municipalities table
-- Run after migration_location_enrichment.sql

ALTER TABLE municipalities
    ADD COLUMN IF NOT EXISTS slug VARCHAR(120) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS views BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER ddd;

CREATE INDEX IF NOT EXISTS idx_municipalities_slug_state ON municipalities (slug, state_uf);
