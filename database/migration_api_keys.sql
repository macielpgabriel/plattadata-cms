-- Migration: API Keys and Webhooks Support for Integrations
-- Date: 2026-04-10

-- Tabela de API Keys para autenticação
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NULL,
    webhook_secret VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    UNIQUE KEY uk_api_key (api_key),
    UNIQUE KEY uk_webhook_secret (webhook_secret)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de acesso à API
CREATE TABLE IF NOT EXISTS api_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    resource VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_api_key (api_key),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para performance em rankings
CREATE INDEX IF NOT EXISTS idx_companies_state ON companies(state);
CREATE INDEX IF NOT EXISTS idx_companies_city_state ON companies(city, state);
CREATE INDEX IF NOT EXISTS idx_companies_cnae ON companies(cnae_main_code);
CREATE INDEX IF NOT EXISTS idx_companies_capital ON companies(capital_social);
