-- DDD cache table for DDD lookups
-- Reduces API calls by caching results for 30 days

CREATE TABLE IF NOT EXISTS ddd_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ddd VARCHAR(2) NOT NULL,
    result_json JSON NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_ddd (ddd),
    INDEX idx_expires (expires_at),
    UNIQUE INDEX idx_ddd_unique (ddd)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;