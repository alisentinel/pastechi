-- Migration: Create rate_limits table
-- Stores rate-limit window counters per endpoint

CREATE TABLE IF NOT EXISTS rate_limits (
    `key` VARCHAR(128) PRIMARY KEY COMMENT 'Rate limit bucket key (e.g., create, get, discussion_post, etc.)',
    window_start BIGINT NOT NULL COMMENT 'Unix timestamp of current window start',
    count INT NOT NULL DEFAULT 0 COMMENT 'Request count in current window',
    INSERT_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATE_TS TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
