-- Migration: Update discussions table to use codeHash foreign key
-- Changes the foreign key relationship from code to codeHash for privacy

SET FOREIGN_KEY_CHECKS=0;

-- Rename paste_code column to paste_codeHash
ALTER TABLE discussions CHANGE COLUMN paste_code paste_codeHash VARCHAR(64) COMMENT 'SHA256 hash of paste code';

-- Drop old foreign key
ALTER TABLE discussions DROP FOREIGN KEY discussions_ibfk_1;

-- Drop old index (ignore error if it doesn't exist)
-- ALTER TABLE discussions DROP INDEX idx_paste_code;

-- Add new foreign key to codeHash
ALTER TABLE discussions ADD CONSTRAINT discussions_ibfk_1 FOREIGN KEY (paste_codeHash) REFERENCES pastes(codeHash) ON DELETE CASCADE;

-- Add index for paste_codeHash  
ALTER TABLE discussions ADD INDEX idx_paste_codeHash (paste_codeHash);

SET FOREIGN_KEY_CHECKS=1;
