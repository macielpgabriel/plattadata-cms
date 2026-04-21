-- Migration: company_reviews table
CREATE TABLE IF NOT EXISTS company_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reply TEXT NULL,
    reply_at DATETIME NULL,
    reports_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_review_company (company_id),
    INDEX idx_review_user (user_id),
    INDEX idx_review_status (status),
    INDEX idx_review_created (created_at),
    CONSTRAINT fk_review_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: company_edit_requests table
CREATE TABLE IF NOT EXISTS company_edit_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    cnpj VARCHAR(14) NOT NULL,
    requester_name VARCHAR(120) NOT NULL,
    requester_email VARCHAR(160) NOT NULL,
    verification_type ENUM('email', 'document') NOT NULL,
    verification_code CHAR(6) NULL,
    document_path VARCHAR(255) NULL,
    status ENUM('pending', 'verified', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_edit_cnpj (cnpj),
    INDEX idx_edit_status (status),
    CONSTRAINT fk_edit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: add profile fields to companies
ALTER TABLE companies ADD COLUMN description TEXT NULL;
ALTER TABLE companies ADD COLUMN facebook VARCHAR(255) NULL;
ALTER TABLE companies ADD COLUMN instagram VARCHAR(50) NULL;
ALTER TABLE companies ADD COLUMN linkedin VARCHAR(255) NULL;
ALTER TABLE companies ADD COLUMN whatsapp VARCHAR(20) NULL;