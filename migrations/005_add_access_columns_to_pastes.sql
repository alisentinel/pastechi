-- Migration: Add access metadata columns to pastes
-- Required for optional fragment/password UX behavior

ALTER TABLE pastes
    ADD COLUMN requires_fragment BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Whether URL fragment key is required' AFTER discussion_salt,
    ADD COLUMN password_protected BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Whether password is required for decrypt' AFTER requires_fragment;
