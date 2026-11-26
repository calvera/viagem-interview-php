-- Index for the owners list: parcel count and parcels of the owners on the page
-- (where owner_id in (...)). Without it every such query scans all 1.2M ownerships.
-- Idempotent (IF NOT EXISTS), online (LOCK=NONE: reads and writes continue); the code works without the index, only slower.
-- Rollback: SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE ownerships DROP INDEX IF EXISTS idx_ownerships_owner;

-- Same sql_mode as the application (Database::connect()). ownerships has no zero-date defaults, so this is
-- only for uniformity with 002 (there a strict mode with NO_ZERO_DATE makes ALTER TABLE owners fail with 1067).
SET SESSION sql_mode = '';

-- Give up after 10 s instead of blocking the table while a long query holds the metadata lock; then just run again.
SET SESSION lock_wait_timeout = 10;

ALTER TABLE ownerships
  ADD INDEX IF NOT EXISTS idx_ownerships_owner (owner_id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- Refresh the InnoDB persistent statistics, so the optimizer sees the new index with fresh numbers.
-- With use_stat_tables = PREFERABLY_FOR_QUERIES (default in 10.11) it samples a few pages per index
-- (innodb_stats_persistent_sample_pages), well under a second, harmless to repeat. With PREFERABLY or
-- COMPLEMENTARY it scans the whole table and writes mysql.*_stats for all columns: check the value first (MIGRATION.md, 5.2).
-- Did not wait for running queries nor block them on Docker MariaDB 10.11.19; check production (MIGRATION.md, chapter 5). No histograms (those need PERSISTENT FOR, a full scan).
ANALYZE TABLE ownerships;
