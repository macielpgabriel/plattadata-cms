-- Migration: Extended Company Data
-- Date: 2026-04-10
-- Adds: Financial, QSA Partners, Location, Market, Compliance, External Enrichment, Predictive Analysis, Contact Data

-- ============================================
-- COMPANIES TABLE - New Columns
-- ============================================

-- Financial Data
ALTER TABLE companies ADD COLUMN revenue_estimate DECIMAL(18,2) NULL AFTER revenue;
ALTER TABLE companies ADD COLUMN tax_history JSON NULL AFTER revenue_estimate;
ALTER TABLE companies ADD COLUMN installment_plans JSON NULL AFTER tax_history;
ALTER TABLE companies ADD COLUMN tax_debts JSON NULL AFTER installment_plans;

-- Partner Data (QSA)
ALTER TABLE companies ADD COLUMN partners_data JSON NULL AFTER legal_nature;
ALTER TABLE companies ADD COLUMN total_partners INT UNSIGNED NULL AFTER partners_data;

-- Location Data
ALTER TABLE companies ADD COLUMN latitude DECIMAL(10,8) NULL AFTER address_complement;
ALTER TABLE companies ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude;
ALTER TABLE companies ADD COLUMN map_url VARCHAR(500) NULL AFTER longitude;
ALTER TABLE companies ADD COLUMN region_type ENUM('metropolitana', 'interior', 'capital', 'rural') NULL AFTER map_url;

-- Market Data
ALTER TABLE companies ADD COLUMN competitors_count INT UNSIGNED NULL AFTER region_type;
ALTER TABLE companies ADD COLUMN market_trend ENUM('crescendo', 'estavel', 'declinando') NULL AFTER competitors_count;
ALTER TABLE companies ADD COLUMN competition_score TINYINT UNSIGNED NULL AFTER market_trend;

-- Compliance Data
ALTER TABLE companies ADD COLUMN compliance_status JSON NULL AFTER competition_score;
ALTER TABLE companies ADD COLUMN negative_certificates JSON NULL AFTER compliance_status;
ALTER TABLE companies ADD COLUMN last_balance_sheet DATE NULL AFTER negative_certificates;
ALTER TABLE companies ADD COLUMN risk_score TINYINT UNSIGNED NULL AFTER last_balance_sheet;
ALTER TABLE companies ADD COLUMN risk_level ENUM('baixo', 'medio', 'alto', 'critico') NULL AFTER risk_score;

-- External Enrichment
ALTER TABLE companies ADD COLUMN social_media JSON NULL AFTER risk_level;
ALTER TABLE companies ADD COLUMN photos JSON NULL AFTER social_media;
ALTER TABLE companies ADD COLUMN google_place_id VARCHAR(100) NULL AFTER photos;
ALTER TABLE companies ADD COLUMN ratings JSON NULL AFTER google_place_id;

-- Predictive Analysis
ALTER TABLE companies ADD COLUMN credit_score TINYINT UNSIGNED NULL AFTER ratings;
ALTER TABLE companies ADD COLUMN inactivity_probability DECIMAL(5,2) NULL AFTER credit_score;
ALTER TABLE companies ADD COLUMN growth_potential ENUM('alto', 'medio', 'baixo') NULL AFTER inactivity_probability;
ALTER TABLE companies ADD COLUMN recommended_porte VARCHAR(20) NULL AFTER growth_potential;

-- Contact Data
ALTER TABLE companies ADD COLUMN whatsapp_business_id VARCHAR(100) NULL AFTER recommended_porte;
ALTER TABLE companies ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER whatsapp_business_id;
ALTER TABLE companies ADD COLUMN website_verified TINYINT(1) DEFAULT 0 AFTER email_verified;

-- ============================================
-- NEW TABLES
-- ============================================

-- Table: Partner History (historical QSA data)
CREATE TABLE IF NOT EXISTS partner_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    partner_name VARCHAR(255) NOT NULL,
    partner_document VARCHAR(20) NULL,
    role VARCHAR(100) NULL,
    participation_percentage DECIMAL(5,2) NULL,
    entered_at DATE NULL,
    exited_at DATE NULL,
    is_current TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partner_company (company_id),
    INDEX idx_partner_document (partner_document),
    INDEX idx_partner_current (is_current),
    CONSTRAINT fk_partner_history_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Company Competitors
CREATE TABLE IF NOT EXISTS company_competitors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    competitor_cnpj CHAR(14) NOT NULL,
    competitor_name VARCHAR(255) NOT NULL,
    distance_km DECIMAL(8,2) NULL,
    similarity_score TINYINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company_competitor (company_id, competitor_cnpj),
    INDEX idx_competitor_cnpj (competitor_cnpj),
    CONSTRAINT fk_competitor_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: CNAE Statistics (for market data)
CREATE TABLE IF NOT EXISTS cnae_statistics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnae_code VARCHAR(20) NOT NULL UNIQUE,
    total_companies INT UNSIGNED DEFAULT 0,
    avg_capital DECIMAL(18,2) DEFAULT 0,
    avg_revenue DECIMAL(18,2) DEFAULT 0,
    revenue_median DECIMAL(18,2) DEFAULT 0,
    market_trend ENUM('crescendo', 'estavel', 'declinando') DEFAULT 'estavel',
    competition_score TINYINT UNSIGNED DEFAULT 50,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnae_code (cnae_code),
    INDEX idx_cnae_trend (market_trend)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Municipality Statistics (for location analysis)
CREATE TABLE IF NOT EXISTS municipality_statistics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL UNIQUE,
    total_companies INT UNSIGNED DEFAULT 0,
    competitors_per_km2 DECIMAL(8,2) DEFAULT 0,
    avg_competition_score TINYINT UNSIGNED DEFAULT 50,
    region_type ENUM('metropolitana', 'interior', 'capital', 'rural') DEFAULT 'interior',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_muni_ibge (ibge_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES for Performance
-- ============================================
CREATE INDEX idx_companies_revenue_estimate ON companies(revenue_estimate);
CREATE INDEX idx_companies_lat_lng ON companies(latitude, longitude);
CREATE INDEX idx_companies_competition_score ON companies(competition_score);
CREATE INDEX idx_companies_risk_score ON companies(risk_score);
CREATE INDEX idx_companies_credit_score ON companies(credit_score);
CREATE INDEX idx_companies_inactivity_prob ON companies(inactivity_probability);
