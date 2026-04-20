ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS postal_code CHAR(8) NULL AFTER raw_data,
    ADD COLUMN IF NOT EXISTS district VARCHAR(120) NULL AFTER postal_code,
    ADD COLUMN IF NOT EXISTS street VARCHAR(180) NULL AFTER district,
    ADD COLUMN IF NOT EXISTS address_number VARCHAR(30) NULL AFTER street,
    ADD COLUMN IF NOT EXISTS address_complement VARCHAR(120) NULL AFTER address_number,
    ADD COLUMN IF NOT EXISTS municipal_ibge_code INT UNSIGNED NULL AFTER address_complement,
    ADD COLUMN IF NOT EXISTS cnae_main_code VARCHAR(16) NULL AFTER municipal_ibge_code,
    ADD COLUMN IF NOT EXISTS legal_nature VARCHAR(180) NULL AFTER cnae_main_code,
    ADD COLUMN IF NOT EXISTS company_size VARCHAR(100) NULL AFTER legal_nature,
    ADD COLUMN IF NOT EXISTS simples_opt_in TINYINT(1) NULL AFTER company_size,
    ADD COLUMN IF NOT EXISTS mei_opt_in TINYINT(1) NULL AFTER simples_opt_in,
    ADD COLUMN IF NOT EXISTS capital_social DECIMAL(18,2) NULL AFTER mei_opt_in,
    ADD COLUMN IF NOT EXISTS source_provider VARCHAR(64) NULL AFTER capital_social,
    ADD COLUMN IF NOT EXISTS last_synced_at DATETIME NULL AFTER source_provider,
    ADD COLUMN IF NOT EXISTS query_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_synced_at;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
    ADD COLUMN IF NOT EXISTS failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER failed_login_attempts,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER locked_until;

CREATE INDEX IF NOT EXISTS idx_users_role_active ON users (role, is_active);
CREATE INDEX IF NOT EXISTS idx_users_lock ON users (locked_until);

CREATE INDEX IF NOT EXISTS idx_companies_trade_name ON companies (trade_name);
CREATE INDEX IF NOT EXISTS idx_companies_status_state ON companies (status, state);
CREATE INDEX IF NOT EXISTS idx_companies_ibge ON companies (municipal_ibge_code);
CREATE INDEX IF NOT EXISTS idx_companies_source_sync ON companies (source_provider, last_synced_at);
CREATE INDEX IF NOT EXISTS idx_companies_updated ON companies (updated_at);
CREATE INDEX IF NOT EXISTS idx_companies_postal_code ON companies (postal_code);

CREATE TABLE IF NOT EXISTS company_enrichments (
    company_id BIGINT UNSIGNED PRIMARY KEY,
    cep_source VARCHAR(32) NULL,
    ddd VARCHAR(8) NULL,
    region_name VARCHAR(80) NULL,
    mesoregion VARCHAR(120) NULL,
    microregion VARCHAR(120) NULL,
    geocode_source VARCHAR(64) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    ibge_code INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enrichment_ibge (ibge_code),
    INDEX idx_enrichment_region (region_name),
    INDEX idx_enrichment_geo (latitude, longitude),
    CONSTRAINT fk_enrichment_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_source_payloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    cnpj CHAR(14) NOT NULL,
    provider VARCHAR(64) NOT NULL,
    request_url VARCHAR(255) NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    error_message VARCHAR(255) NULL,
    response_json LONGTEXT NULL,
    payload_checksum CHAR(64) NULL,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source_payloads_cnpj_fetched (cnpj, fetched_at),
    INDEX idx_source_payloads_provider_fetched (provider, fetched_at),
    INDEX idx_source_payloads_status_fetched (succeeded, fetched_at),
    INDEX idx_source_payloads_checksum (payload_checksum),
    CONSTRAINT fk_source_payloads_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(32) NOT NULL,
    raw_data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_snapshots_company_created (company_id, created_at),
    CONSTRAINT fk_snapshots_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_query_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    source VARCHAR(32) NOT NULL,
    ip_address VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_company_created (company_id, created_at),
    INDEX idx_logs_user_created (user_id, created_at),
    CONSTRAINT fk_logs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_audit_logs (

CREATE TABLE IF NOT EXISTS lgpd_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    cnpj CHAR(14) NOT NULL,
    user_id INT UNSIGNED NULL,
    user_role VARCHAR(20) NOT NULL,
    action_name VARCHAR(40) NOT NULL,
    accessed_fields_json LONGTEXT NULL,
    masking_profile VARCHAR(20) NOT NULL,
    ip_address VARCHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lgpd_audit_company_created (company_id, created_at),
    INDEX idx_lgpd_audit_user_created (user_id, created_at),
    INDEX idx_lgpd_audit_action_created (action_name, created_at),
    CONSTRAINT fk_lgpd_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_lgpd_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(200) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    sent TINYINT(1) NOT NULL DEFAULT 0,
    error_message VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(120) NOT NULL UNIQUE,
    value_text TEXT NOT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_settings (key_name, value_text, updated_at)
VALUES
    ('site_name', 'CMS Empresarial', NOW()),
    ('site_description', 'Consulta de CNPJ, QSA, CNAE e dados empresariais em tempo real.', NOW()),
    ('homepage_title', 'Consulta CNPJ', NOW()),
    ('homepage_subtitle', 'Busque CNPJ gratuitamente sem precisar de login.', NOW()),
    ('homepage_search_placeholder', '00.000.000/0001-00', NOW()),
    ('homepage_public_notice', 'A busca publica consulta e salva cache local para acelerar futuras consultas.', NOW()),
    ('contact_email', '', NOW()),
    ('contact_phone', '', NOW()),
    ('contact_whatsapp', '', NOW()),
    ('footer_text', 'Dados empresariais publicos com foco em transparencia e compliance.', NOW()),
    ('seo_default_robots', 'index,follow', NOW()),
    ('seo_default_keywords', 'cnpj, consulta cnpj, razao social, qsa, cnae', NOW()),
    ('companies_per_page', '15', NOW()),
    ('public_search_rate_limit_per_minute', '20', NOW()),
    ('sitemap_company_limit', '10000', NOW()),
    ('public_search_enabled', '1', NOW())
ON DUPLICATE KEY UPDATE value_text = value_text;
