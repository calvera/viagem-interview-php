-- Index for the owners list filtered by the owner's municipality (f_municipality_id: o.municipality_id = ?)
-- in the default sort (o.last_name, o.first_name, o.id). The page is read from the index in sort order,
-- without scanning all 500k owners and without a filesort; count(*) of the filter reads only the index.
-- It also serves the text "Obec" filter (municipalities first, then the owners of each match by index;
-- very broad substrings cost more than without it, MIGRATION.md 5.3)
-- and the "top municipalities" query of cron/nightly_report.php (group by municipality_id).
-- Idempotent (IF NOT EXISTS), online (LOCK=NONE: reads and writes continue); the code works without the index, only slower.
-- Rollback (sql_mode as below, DROP INDEX fails with 1067 under strict mode too):
--   SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_municipality;

-- Same sql_mode as the application (Database::connect()): with a strict server or client default
-- (NO_ZERO_DATE) ALTER TABLE owners fails with 1067 Invalid default value for 'duedate'.
SET SESSION sql_mode = '';

-- Give up after 10 s instead of blocking the table while a long query holds the metadata lock; then just run again.
SET SESSION lock_wait_timeout = 10;

ALTER TABLE owners
  ADD INDEX IF NOT EXISTS idx_owners_municipality (municipality_id, last_name, first_name, id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- Refresh the InnoDB persistent statistics, so the optimizer sees the new index with fresh numbers.
-- With use_stat_tables = PREFERABLY_FOR_QUERIES (default in 10.11) it samples a few pages per index
-- (innodb_stats_persistent_sample_pages), well under a second, harmless to repeat. With PREFERABLY or
-- COMPLEMENTARY it scans the whole table and rewrites mysql.*_stats for all columns (also the 002 histograms):
-- check the value first (MIGRATION.md, 5.2).
-- The histograms from 002 stay as they are: they describe the column values, not the indexes, and the data
-- did not change. For o.municipality_id = ? the optimizer now estimates from the index (plan the same without
-- the histograms). Did not wait for running queries nor block them on Docker MariaDB 10.11.19; check production
-- (MIGRATION.md, chapter 5).
ANALYZE TABLE owners;
