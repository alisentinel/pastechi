-- Migration: Initial merged schema for fresh installs
-- This file represents the final schema state after applying historical migrations.

CREATE TABLE IF NOT EXISTS pastes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    codeHash VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA256 hash of code + server pepper',
    ciphertext LONGTEXT NOT NULL COMMENT 'Base64-encoded AES-256-GCM ciphertext',
    iv VARCHAR(256) NOT NULL COMMENT 'Base64-encoded initialization vector',
    salt VARCHAR(256) NOT NULL COMMENT 'Base64-encoded key derivation salt',
    kdfIterations INT NOT NULL COMMENT 'PBKDF2 iteration count',
    createdAt BIGINT NOT NULL COMMENT 'Unix timestamp of creation',
    expireAt BIGINT NOT NULL COMMENT 'Unix timestamp for TTL expiry (0 = no expiry)',
    views INT NOT NULL DEFAULT 0 COMMENT 'Current view count',
    maxViews INT NOT NULL DEFAULT 0 COMMENT 'Max views before deletion (0 = unlimited)',
    burnAfterRead BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Delete after first view',
    uniqueViewsOnly BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Count only unique viewers using cookie markers',
    lockUntil BIGINT NOT NULL DEFAULT 0 COMMENT 'Unix timestamp for time-lock gate',
    binding_type VARCHAR(32) NOT NULL DEFAULT 'none' COMMENT 'none|ip|fingerprint',
    binding_hash VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Optional binding hash (empty string = not persisted)',
    modes_discussion BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Enable E2EE discussion polling',
    modes_forensics BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Enable forensics aggregation',
    discussion_salt VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Salt for discussion message KDF',
    requires_fragment BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether URL fragment key is required',
    password_protected BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether password is required for decrypt',
    forensics_buckets JSON DEFAULT NULL COMMENT 'Hourly aggregated view counts',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expires (expireAt),
    INDEX idx_created (createdAt),
    INDEX idx_codeHash (codeHash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discussions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    paste_codeHash VARCHAR(64) NOT NULL COMMENT 'Foreign key to pastes.codeHash',
    message_ciphertext LONGTEXT NOT NULL COMMENT 'Base64-encoded encrypted message',
    message_iv VARCHAR(256) NOT NULL COMMENT 'Base64-encoded IV for message encryption',
    message_kdfIterations INT NOT NULL COMMENT 'PBKDF2 iterations for message key',
    createdAt BIGINT NOT NULL COMMENT 'Unix timestamp of message creation',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paste_codeHash) REFERENCES pastes(codeHash) ON DELETE CASCADE,
    INDEX idx_paste_codeHash (paste_codeHash),
    INDEX idx_created (createdAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(128) PRIMARY KEY COMMENT 'Rate limit bucket key',
    window_start BIGINT NOT NULL COMMENT 'Unix timestamp of current window start',
    count INT NOT NULL DEFAULT 0 COMMENT 'Request count in current window',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ts BIGINT NOT NULL COMMENT 'Unix timestamp of log entry',
    level VARCHAR(16) NOT NULL COMMENT 'Log level: debug, info, warn, error',
    message VARCHAR(256) NOT NULL COMMENT 'Log message (sanitized, no secrets)',
    path VARCHAR(256) NOT NULL DEFAULT '' COMMENT 'Request URL path',
    context_json JSON COMMENT 'Additional context data (redacted)',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ts (ts),
    INDEX idx_level (level),
    INDEX idx_message (message)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
