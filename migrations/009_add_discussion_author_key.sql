-- Migration: Add discussion author key to pastes
-- Stores a stable author identity key so clients can label the paste writer in discussions.

ALTER TABLE pastes
ADD COLUMN discussion_author_key VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'Stable author identity key for discussion role mapping' AFTER discussion_salt;