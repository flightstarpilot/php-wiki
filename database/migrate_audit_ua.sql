-- Add the User-Agent column to audit_logs.
-- SQLite:
ALTER TABLE audit_logs ADD COLUMN ua TEXT NOT NULL DEFAULT '';

-- MySQL (run only the line below instead, if your driver is MySQL):
-- ALTER TABLE audit_logs ADD COLUMN ua VARCHAR(500) NOT NULL DEFAULT '';
