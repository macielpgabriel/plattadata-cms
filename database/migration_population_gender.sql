-- Migration: Population by Gender
-- Adds columns for male/female population breakdown to municipalities table
-- Run after migration_location_enrichment.sql

ALTER TABLE municipalities
    ADD COLUMN population_male BIGINT UNSIGNED NULL AFTER population,
    ADD COLUMN population_female BIGINT UNSIGNED NULL AFTER population_male,
    ADD COLUMN population_male_percent DECIMAL(5,2) NULL AFTER population_female,
    ADD COLUMN population_female_percent DECIMAL(5,2) NULL AFTER population_male_percent,
    ADD COLUMN age_groups JSON NULL AFTER population_female_percent,
    ADD INDEX idx_population_gender (population_male, population_female);

ALTER TABLE states
    ADD COLUMN population_male BIGINT UNSIGNED NULL,
    ADD COLUMN population_female BIGINT UNSIGNED NULL,
    ADD COLUMN population_male_percent DECIMAL(5,2) NULL,
    ADD COLUMN population_female_percent DECIMAL(5,2) NULL;