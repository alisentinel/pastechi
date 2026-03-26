-- Migration: Create logs table
-- Stores sanitized server and client trace logs

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
