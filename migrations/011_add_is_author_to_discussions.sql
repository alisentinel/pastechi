-- Migration: Verify discussion authorship server-side.
-- The paste author key is now stored as a SHA-256 hash and never sent to viewers.

ALTER TABLE discussions
ADD COLUMN is_author BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Posted by the verified paste author';
