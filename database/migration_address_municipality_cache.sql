-- Address cache table for CEP lookups
-- Reduces API calls by caching results for 30 days

CREATE TABLE IF NOT EXISTS address_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cep VARCHAR(8) NOT NULL,
    result_json JSON NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_cep (cep),
    INDEX idx_expires (expires_at),
    UNIQUE INDEX idx_cep_unique (cep)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Municipality cache table for IBGE lookups
-- Reduces API calls by caching results for 30 days

CREATE TABLE IF NOT EXISTS municipality_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT NOT NULL,
    result_json JSON NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_ibge (ibge_code),
    INDEX idx_expires (expires_at),
    UNIQUE INDEX idx_ibge_unique (ibge_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
