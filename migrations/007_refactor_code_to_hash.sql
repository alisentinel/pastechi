-- Migration: Refactor code storage to use hash
-- Changes primary key from plaintext code to hashed code for privacy

SET FOREIGN_KEY_CHECKS=0;

-- Add codeHash column (will be null initially for existing rows)
ALTER TABLE pastes ADD COLUMN codeHash VARCHAR(64) UNIQUE COMMENT 'SHA256 hash of the 6-digit code';

-- Add id column with auto_increment
ALTER TABLE pastes ADD COLUMN id BIGINT AUTO_INCREMENT UNIQUE COMMENT 'Auto-increment ID';

-- Drop the old primary key on code
ALTER TABLE pastes DROP PRIMARY KEY;

-- Add id as the new primary key
ALTER TABLE pastes ADD PRIMARY KEY (id);

-- Make codeHash NOT NULL and maintain unique constraint
ALTER TABLE pastes MODIFY COLUMN codeHash VARCHAR(64) NOT NULL UNIQUE;

-- Modify code to be non-unique
ALTER TABLE pastes MODIFY COLUMN code VARCHAR(6) NOT NULL;

SET FOREIGN_KEY_CHECKS=1;
