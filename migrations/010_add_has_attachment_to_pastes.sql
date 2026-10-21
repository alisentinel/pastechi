-- Migration: Track pastes whose encrypted attachment is stored outside the database
-- (S3-compatible object storage or storage/attachments).

ALTER TABLE pastes
ADD COLUMN has_attachment BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Encrypted attachment stored outside the database' AFTER forensics_buckets;
