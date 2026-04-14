-- Migration to add company removal request system

-- 1. Add moderator role to users
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'moderator', 'editor', 'viewer') NOT NULL DEFAULT 'viewer';

-- 2. Add is_hidden flag to companies
ALTER TABLE companies ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_companies_hidden ON companies(is_hidden);

-- 3. Create company_removal_requests table
CREATE TABLE IF NOT EXISTS company_removal_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    cnpj CHAR(14) NOT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(160) NOT NULL,
    verification_type ENUM('email', 'document') NOT NULL,
    verification_code CHAR(6) NULL,
    document_path VARCHAR(255) NULL,
    status ENUM('pending', 'verified', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    verified_at DATETIME NULL,
    resolved_at DATETIME NULL,
    resolved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_removal_status (status),
    INDEX idx_removal_cnpj (cnpj),
    CONSTRAINT fk_removal_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_removal_admin FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
