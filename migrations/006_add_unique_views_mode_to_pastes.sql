-- Migration: Add unique viewer counting mode to pastes
-- Enables delete-after-N-unique-viewers using cookie marker detection

ALTER TABLE pastes
    ADD COLUMN uniqueViewsOnly BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Count only unique viewers using cookie markers';
