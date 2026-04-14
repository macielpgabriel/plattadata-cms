-- Compliance cache table for storing sanction check results
-- Reduces API calls by caching results for 7 days

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
