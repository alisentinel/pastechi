-- Migration: Create discussions table
-- Stores encrypted discussion messages per paste

CREATE TABLE IF NOT EXISTS discussions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    paste_code VARCHAR(6) NOT NULL COMMENT 'Foreign key to pastes.code',
    message_ciphertext LONGTEXT NOT NULL COMMENT 'Base64-encoded encrypted message',
    message_iv VARCHAR(256) NOT NULL COMMENT 'Base64-encoded IV for message encryption',
    message_kdfIterations INT NOT NULL COMMENT 'PBKDF2 iterations for message KEY',
    createdAt BIGINT NOT NULL COMMENT 'Unix timestamp of message creation',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paste_code) REFERENCES pastes(code) ON DELETE CASCADE,
    INDEX idx_paste_code (paste_code),
    INDEX idx_created (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
