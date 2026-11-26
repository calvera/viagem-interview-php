-- Index for the default sort of the owners list (order by o.last_name, o.first_name, o.id).
-- With it the page is read from the index instead of sorting all 500k owners (filesort).
-- Idempotent (IF NOT EXISTS), online (LOCK=NONE: reads and writes continue); the code works without the index, only slower.
-- Rollback (sql_mode as below, DROP INDEX fails with 1067 under strict mode too):
--   SET SESSION sql_mode = ''; SET SESSION lock_wait_timeout = 10; ALTER TABLE owners DROP INDEX IF EXISTS idx_owners_name;

-- Same sql_mode as the application (Database::connect()): with a strict server or client default
-- (NO_ZERO_DATE) ALTER TABLE owners fails with 1067 Invalid default value for 'duedate'.
SET SESSION sql_mode = '';

-- Give up after 10 s instead of blocking the table while a long query holds the metadata lock; then just run again.
SET SESSION lock_wait_timeout = 10;

ALTER TABLE owners
  ADD INDEX IF NOT EXISTS idx_owners_name (last_name, first_name, id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- Statistics for the optimizer: InnoDB persistent statistics (also for the new index) and histograms
-- of the filtered columns without an index. Without the histograms the optimizer assumes that every row
-- matches o.municipality_id = ?, o.ico = ? or a rare type and do-not-contact combination and reads the
-- whole index in name order instead of sorting the few matching owners. One full scan of the table
-- (under a second on 500k rows), gives the same statistics when repeated. Did not wait for running queries
-- nor block them on Docker MariaDB 10.11.19; check production (MIGRATION.md, chapter 5).
ANALYZE TABLE owners PERSISTENT FOR COLUMNS (type, ico, municipality_id, do_not_contact) INDEXES ();
