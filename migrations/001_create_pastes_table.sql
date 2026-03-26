-- Migration: Create pastes table
-- Stores encrypted paste content and metadata

CREATE TABLE IF NOT EXISTS pastes (
    code VARCHAR(6) PRIMARY KEY COMMENT '6-digit tracking code',
    ciphertext LONGTEXT NOT NULL COMMENT 'Base64-encoded AES-256-GCM ciphertext',
    iv VARCHAR(256) NOT NULL COMMENT 'Base64-encoded initialization vector',
    salt VARCHAR(256) NOT NULL COMMENT 'Base64-encoded key derivation salt',
    kdfIterations INT NOT NULL COMMENT 'PBKDF2 iteration count',
    createdAt BIGINT NOT NULL COMMENT 'Unix timestamp of creation',
    expireAt BIGINT NOT NULL COMMENT 'Unix timestamp for TTL expiry (0 = no expiry)',
    views INT NOT NULL DEFAULT 0 COMMENT 'Current view count',
    maxViews INT NOT NULL DEFAULT 0 COMMENT 'Max views before deletion (0 = unlimited)',
    burnAfterRead BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Delete after first view',
    lockUntil BIGINT NOT NULL DEFAULT 0 COMMENT 'Unix timestamp for time-lock gate',
    binding_type VARCHAR(32) NOT NULL DEFAULT 'none' COMMENT 'none|ip|fingerprint',
    binding_hash VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Optional binding hash (empty string = not persisted)',
    modes_discussion BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Enable E2EE discussion polling',
    modes_forensics BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Enable forensics aggregation',
    discussion_salt VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Salt for discussion message KDF',
    forensics_buckets JSON DEFAULT NULL COMMENT 'Hourly aggregated view counts',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expireAt),
    INDEX idx_created (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
