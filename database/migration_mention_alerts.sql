-- Migration: Mention Alerts Tables
-- Date: 2026-04-10

-- Table: Company Mentions History
CREATE TABLE IF NOT EXISTS company_mentions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnpj CHAR(14) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    mention_data JSON NULL,
    checked_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cnpj (cnpj),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Mention Alert Subscriptions
CREATE TABLE IF NOT EXISTS company_mentions_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnpj CHAR(14) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    mention_data JSON NULL,
    checked_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Alert Subscriptions
CREATE TABLE IF NOT EXISTS mention_alert_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnpj CHAR(14) NOT NULL,
    email VARCHAR(160) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cnpj_email (cnpj, email),
    INDEX idx_email (email),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
