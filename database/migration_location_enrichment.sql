-- Migration: Location Enrichment Tables
-- Creates tables for states, municipalities, and tax data caching
-- Run after base schema.sql

-- Estados brasileiros (cache IBGE)
CREATE TABLE IF NOT EXISTS states (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uf CHAR(2) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    region VARCHAR(20) NOT NULL,
    ibge_code TINYINT UNSIGNED NOT NULL,
    population BIGINT UNSIGNED NULL,
    gdp DECIMAL(18,2) NULL,
    gdp_per_capita DECIMAL(12,2) NULL,
    area_km2 DECIMAL(12,2) NULL,
    capital_city VARCHAR(100) NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_states_region (region),
    INDEX idx_states_ibge (ibge_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Municípios brasileiros (cache IBGE)
CREATE TABLE IF NOT EXISTS municipalities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    state_uf CHAR(2) NOT NULL,
    mesoregion VARCHAR(120) NULL,
    microregion VARCHAR(120) NULL,
    population BIGINT UNSIGNED NULL,
    gdp DECIMAL(18,2) NULL,
    gdp_per_capita DECIMAL(12,2) NULL,
    area_km2 DECIMAL(12,2) NULL,
    ddd VARCHAR(4) NULL,
    weather_updated_at DATETIME NULL,
    weather_data JSON NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_municipalities_state (state_uf),
    INDEX idx_municipalities_ibge (ibge_code),
    INDEX idx_municipalities_name_state (name, state_uf),
    CONSTRAINT fk_municipality_state FOREIGN KEY (state_uf) REFERENCES states(uf) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache de dados do Simples Nacional/IE por CNPJ
CREATE TABLE IF NOT EXISTS company_tax_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    cnpj CHAR(14) NOT NULL,
    simples_opt_in TINYINT(1) NULL,
    simples_since DATE NULL,
    mei_opt_in TINYINT(1) NULL,
    mei_since DATE NULL,
    state_registrations JSON NULL,
    source VARCHAR(32) NOT NULL,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tax_cnpj (cnpj),
    INDEX idx_tax_fetched (fetched_at),
    INDEX idx_tax_company (company_id),
    CONSTRAINT fk_tax_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir dados iniciais dos 27 estados brasileiros
INSERT INTO states (uf, name, region, ibge_code) VALUES
    ('AC', 'Acre', 'Norte', 12),
    ('AL', 'Alagoas', 'Nordeste', 27),
    ('AP', 'Amapá', 'Norte', 16),
    ('AM', 'Amazonas', 'Norte', 13),
    ('BA', 'Bahia', 'Nordeste', 29),
    ('CE', 'Ceará', 'Nordeste', 23),
    ('DF', 'Distrito Federal', 'Centro-Oeste', 53),
    ('ES', 'Espírito Santo', 'Sudeste', 32),
    ('GO', 'Goiás', 'Centro-Oeste', 52),
    ('MA', 'Maranhão', 'Nordeste', 21),
    ('MT', 'Mato Grosso', 'Centro-Oeste', 51),
    ('MS', 'Mato Grosso do Sul', 'Centro-Oeste', 50),
    ('MG', 'Minas Gerais', 'Sudeste', 31),
    ('PA', 'Pará', 'Norte', 15),
    ('PB', 'Paraíba', 'Nordeste', 25),
    ('PR', 'Paraná', 'Sul', 41),
    ('PE', 'Pernambuco', 'Nordeste', 26),
    ('PI', 'Piauí', 'Nordeste', 22),
    ('RJ', 'Rio de Janeiro', 'Sudeste', 33),
    ('RN', 'Rio Grande do Norte', 'Nordeste', 24),
    ('RS', 'Rio Grande do Sul', 'Sul', 43),
    ('RO', 'Rondônia', 'Norte', 11),
    ('RR', 'Roraima', 'Norte', 14),
    ('SC', 'Santa Catarina', 'Sul', 42),
    ('SP', 'São Paulo', 'Sudeste', 35),
    ('SE', 'Sergipe', 'Nordeste', 28),
    ('TO', 'Tocantins', 'Norte', 17)
ON DUPLICATE KEY UPDATE name = VALUES(name), region = VALUES(region), ibge_code = VALUES(ibge_code);