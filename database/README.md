# Database

This directory contains the reference SQL schema for the PHP Wiki project.

- `mysql.sql`  - MySQL / MariaDB schema
- `sqlite.sql` - SQLite schema

## Usage

The bundled installer (`install.php`) applies these schemas automatically.
You may also import them manually.

MySQL:

    mysql -u user -p database_name < database/mysql.sql

SQLite:

    sqlite3 data/wiki.sqlite < database/sqlite.sql

## Full-text search

The FTS5 virtual table used by SQLite for full-text search is intentionally
not created by `sqlite.sql`. Enable it from the admin panel after
installation:

    Settings -> Search -> Rebuild search index

On MySQL, a FULLTEXT index on `pages(title, content)` is created by
`mysql.sql`.

## Idempotency

Every `CREATE TABLE` and `CREATE INDEX` statement uses `IF NOT EXISTS`, so
re-importing the schema on an existing database is safe. Re-running will not
drop or alter existing tables.

Adding a column to an existing table requires an explicit `ALTER TABLE`.
Those statements are listed in the release notes, not in the base schema.

## Migrations

Migration scripts for existing installations live under `migrations/`. They
are numbered and applied in order. The migration runner is not part of the
current release; see `docs/roadmap.md`.
