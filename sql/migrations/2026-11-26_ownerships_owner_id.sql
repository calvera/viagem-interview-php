-- Index for the owners list: parcel count and parcels of the owners on the page
-- (where owner_id in (...)). Without it every such query scans all 1.2M ownerships.
-- Idempotent (IF NOT EXISTS), online (LOCK=NONE: reads and writes continue); the code works without the index, only slower.
-- Rollback: ALTER TABLE ownerships DROP INDEX IF EXISTS idx_ownerships_owner;

-- Give up after 10 s instead of blocking the table while a long query holds the metadata lock; then just run again.
SET SESSION lock_wait_timeout = 10;

ALTER TABLE ownerships
  ADD INDEX IF NOT EXISTS idx_ownerships_owner (owner_id),
  ALGORITHM=INPLACE, LOCK=NONE;
