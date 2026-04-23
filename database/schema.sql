CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'viewer') NOT NULL DEFAULT 'viewer',
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    notification_preferences JSON NULL,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_active (role, is_active),
    INDEX idx_users_lock (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(160) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reset_email (email),
    INDEX idx_reset_token (token),
    INDEX idx_reset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    group_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_company_favorite (user_id, company_id),
    INDEX idx_favorites_user (user_id),
    INDEX idx_favorites_company (company_id),
    INDEX idx_favorites_group (group_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_group FOREIGN KEY (group_id) REFERENCES favorite_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipality_vehicle_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL,
    vehicle_type VARCHAR(100) NOT NULL,
    vehicle_count INT UNSIGNED NULL,
    year YEAR NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ibge_type (ibge_code, vehicle_type),
    INDEX idx_ibge_code (ibge_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_failed_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL UNIQUE,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    first_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    INDEX idx_blocked_ip (ip),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorite_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(20) DEFAULT 'primary',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_groups_user (user_id),
    CONSTRAINT fk_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS states (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uf CHAR(2) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL,
    region VARCHAR(30) NOT NULL,
    capital_city VARCHAR(120) NULL,
    area_km2 DECIMAL(12,2) NULL,
    population BIGINT NULL,
    gdp DECIMAL(15,2) NULL,
    gdp_per_capita DECIMAL(12,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipalities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    state_code CHAR(2) NOT NULL,
    ibge_code INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    name_raw VARCHAR(120) NULL,
    population INT UNSIGNED NULL,
    gdp DECIMAL(15,2) NULL,
    ddd VARCHAR(8) NULL,
    region VARCHAR(120) NULL,
    mesoregion VARCHAR(120) NULL,
    microregion VARCHAR(120) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_municipality_state (state_code),
    INDEX idx_municipality_ibge (ibge_code),
    INDEX idx_municipality_name (name),
    INDEX idx_municipality_raw (name_raw)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_partners (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    partner_cnpj CHAR(14) NULL,
    partner_name VARCHAR(200) NOT NULL,
    partner_type VARCHAR(40) NOT NULL,
    partner_qualification VARCHAR(100) NULL,
    country_code VARCHAR(6) DEFAULT 'BR',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partners_company (company_id),
    INDEX idx_partners_cnpj (partner_cnpj),
    CONSTRAINT fk_partners_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnpj VARCHAR(14) NOT NULL UNIQUE,
    legal_name VARCHAR(255) NOT NULL,
    trade_name VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    status VARCHAR(20) NULL,
    opened_at DATE NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_legal_name (legal_name(50)),
    INDEX idx_state (state),
    INDEX idx_city (city),
    INDEX idx_status (status),
    INDEX idx_hidden (is_hidden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para monitoramento de mudanças de empresas
CREATE TABLE IF NOT EXISTS company_changes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('status', 'address', 'capital', 'cnae', 'name', 'contact', 'other') NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_change_type (change_type),
    INDEX idx_changed_at (changed_at),
    CONSTRAINT fk_changes_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para订阅 de monitoramento
CREATE TABLE IF NOT EXISTS company_change_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_cnpj VARCHAR(14) NOT NULL,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    notify_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_phone VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_cnpj (company_cnpj),
    UNIQUE KEY uq_user_company (user_id, company_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    changes JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS cnpj_blacklist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnpj CHAR(14) NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,
    requested_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    legal_document VARCHAR(180) NULL,
    legal_document_type VARCHAR(20) NULL,
    notes TEXT NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_blacklist_status (status),
    INDEX idx_blacklist_cnpj (cnpj),
    INDEX idx_blacklist_created (created_at),
    CONSTRAINT fk_blacklist_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_blacklist_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    data_json LONGTEXT NULL,
    sent TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user_created (user_id, created_at),
    INDEX idx_notification_type (type),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_verification_user (user_id),
    INDEX idx_verification_token (token),
    INDEX idx_verification_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cnae_activities (
    code VARCHAR(20) PRIMARY KEY,
    slug VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    section VARCHAR(10) NULL,
    INDEX idx_cnae_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_removal_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    cnpj CHAR(14) NOT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(160) NOT NULL,
    verification_type ENUM('email', 'document') NOT NULL DEFAULT 'document',
    verification_code CHAR(6) NULL,
    document_path VARCHAR(180) NULL,
    status ENUM('pending', 'verified', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    admin_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_removal_company (company_id),
    INDEX idx_removal_status (status),
    INDEX idx_removal_created (created_at),
    CONSTRAINT fk_removal_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exchange_rates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_code CHAR(3) NOT NULL,
    currency_name VARCHAR(60) NOT NULL,
    paridade_compra DECIMAL(12,6) NULL,
    paridade_venda DECIMAL(12,6) NULL,
    cotacao_compra DECIMAL(12,6) NOT NULL,
    cotacao_venda DECIMAL(12,6) NOT NULL,
    tipo_boletim VARCHAR(20) NULL,
    data_cotacao DATE NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_currency_date (currency_code, data_cotacao),
    INDEX idx_exchange_date (data_cotacao),
    INDEX idx_exchange_currency (currency_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS economic_indicators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_code VARCHAR(30) NOT NULL,
    indicator_name VARCHAR(60) NOT NULL,
    indicator_value DECIMAL(16,4) NOT NULL,
    indicator_unit VARCHAR(20) NOT NULL DEFAULT '%',
    indicator_period VARCHAR(20) NULL,
    data_referencia DATE NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_indicator_date (indicator_code, data_referencia),
    INDEX idx_indicator_code (indicator_code),
    INDEX idx_indicator_date (data_referencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impostometro_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(30) NOT NULL,
    item_code VARCHAR(30) NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    value DECIMAL(20,2) NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NULL,
    reference_period VARCHAR(20) NULL,
    collected_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_imposto_category (category),
    INDEX idx_imposto_period (reference_period),
    INDEX idx_imposto_collected (collected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS impostometro_arrecadacao (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ano INT NOT NULL,
    mes TINYINT NOT NULL,
    total DECIMAL(20,2) NOT NULL DEFAULT 0,
    rfb DECIMAL(20,2) NOT NULL DEFAULT 0,
    outros DECIMAL(20,2) NOT NULL DEFAULT 0,
    fonte VARCHAR(100) NULL,
    data_publicacao DATE NULL,
    oficial TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ano_mes (ano, mes),
    INDEX idx_arrecadacao_ano (ano),
    INDEX idx_arrecadacao_mes (mes),
    INDEX idx_arrecadacao_oficial (oficial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compliance cache for sanction check results
CREATE TABLE IF NOT EXISTS compliance_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cnpj VARCHAR(14) NOT NULL,
    result_json JSON NOT NULL,
    source VARCHAR(50) NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_cnpj (cnpj),
    INDEX idx_expires (expires_at),
    UNIQUE INDEX idx_cnpj_unique (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CNAE Statistics
CREATE TABLE IF NOT EXISTS cnae_statistics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cnae_code VARCHAR(20) NOT NULL UNIQUE,
    total_companies INT UNSIGNED DEFAULT 0,
    avg_capital DECIMAL(18,2) DEFAULT 0,
    avg_revenue DECIMAL(18,2) DEFAULT 0,
    revenue_median DECIMAL(18,2) DEFAULT 0,
    market_trend ENUM('crescendo','estavel','declinando') DEFAULT 'estavel',
    competition_score TINYINT UNSIGNED DEFAULT 50,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Address Cache
CREATE TABLE IF NOT EXISTS address_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cep VARCHAR(8) NOT NULL,
    result_json JSON NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_cep (cep),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Municipality Cache
CREATE TABLE IF NOT EXISTS municipality_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL,
    result_json JSON NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_ibge (ibge_code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked IPs

-- Blocked IPs
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    blocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_ip (ip),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP Failed Attempts
CREATE TABLE IF NOT EXISTS ip_failed_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL,
    INDEX idx_ip (ip),
    INDEX idx_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Keys
CREATE TABLE IF NOT EXISTS api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    permissions JSON NOT NULL,
    rate_limit INT UNSIGNED DEFAULT 1000,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_user (user_id),
    INDEX idx_key_hash (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Access Logs
CREATE TABLE IF NOT EXISTS api_access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT UNSIGNED,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code SMALLINT UNSIGNED,
    ip VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company Mentions
CREATE TABLE IF NOT EXISTS company_mentions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500),
    mention_date DATE,
    sentiment ENUM('positive','neutral','negative') DEFAULT 'neutral',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_source (source),
    INDEX idx_date (mention_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company Mentions History
CREATE TABLE IF NOT EXISTS company_mentions_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mention_id BIGINT UNSIGNED NOT NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mention (mention_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mention Alert Subscriptions
CREATE TABLE IF NOT EXISTS mention_alert_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    notify_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_company (company_id),
    UNIQUE KEY uk_user_company (user_id, company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company Competitors
CREATE TABLE IF NOT EXISTS company_competitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    competitor_cnpj VARCHAR(14) NOT NULL,
    competitor_name VARCHAR(200),
    similarity_score TINYINT UNSIGNED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_competitor (competitor_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partner History
CREATE TABLE IF NOT EXISTS partner_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    partner_name VARCHAR(200) NOT NULL,
    partner_type ENUM('socio','administrador') NOT NULL,
    qualification VARCHAR(50),
    entry_date DATE,
    exit_date DATE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_name (partner_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Municipality Vehicle Types
CREATE TABLE IF NOT EXISTS municipality_vehicle_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    vehicle_count INT UNSIGNED DEFAULT 0,
    year YEAR,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ibge (ibge_code),
    INDEX idx_vehicle (vehicle_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
