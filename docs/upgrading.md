# Upgrading

The wiki has no build step and no Composer install, so upgrading is a file
copy followed by any necessary schema migrations. There is currently no
automated migration runner; the release notes for each version describe the
SQL to run, if any.

## Before you upgrade

1. Back up the database.
   - SQLite: copy `data/wiki.sqlite`.
   - MySQL: `mysqldump -u user -p database > backup.sql`.
   - HTTP driver: back up whatever backend your endpoint uses.
2. Back up `config/config.php`. It contains your database credentials and
   application key and is not overwritten by an upgrade, but keep a copy.
3. Back up `uploads/` if attachments are enabled.
4. Read the release notes for the target version. Breaking changes are
   listed there.

## Upgrade procedure

1. Put the site into maintenance mode from the admin panel:
   Settings -> Site -> `site.maintenance`.
2. Replace the application files with the new release. Preserve:
   - `config/config.php`
   - `data/`
   - `uploads/`
   - `vendor/PHPMailer/` if you installed it manually
3. Apply any schema changes described in the release notes.
4. Clear the browser cache and reload the admin panel.
5. Turn off maintenance mode.

## Applying schema changes

The reference schemas under `database/` are always up to date with the
current `main` branch. To diff your installation against the schema, compare
table definitions manually or run the schema through your database client
against a scratch copy of the database.

For SQLite, applying the full schema is safe: every statement uses
`CREATE TABLE IF NOT EXISTS` or `CREATE INDEX IF NOT EXISTS`, so re-running
it adds anything missing without touching existing tables.

    sqlite3 data/wiki.sqlite < database/sqlite.sql

For MySQL, the schema uses `CREATE TABLE IF NOT EXISTS`, so the same trick
works, but note that adding a column to an existing table requires an
explicit `ALTER TABLE` statement. Those statements are listed in the release
notes, not in `database/mysql.sql`.

## Rolling back

Restore the files and the database backup. The application key in
`config/config.php` must match the one used when the backup was taken;
otherwise encrypted SMTP credentials become unreadable.

## Version compatibility

- Releases before 0.1.0 are not supported.
- The project does not maintain an upgrade path from forks or unofficial
  builds.

## Staying informed

Watch the repository's releases page. Breaking changes are announced there
and in the top section of `CHANGELOG.md`.
