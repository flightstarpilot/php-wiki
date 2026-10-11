# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-10-11

Everything below ships in the first public release. It is listed as a single
block rather than as incremental history because there is no earlier
published version to compare against.

### Added

**Content**

- Translation groups: one page, N language versions, each with an independent
  version history and a shared discussion thread.
- Page version history with line-level diff in side-by-side and unified modes.
- Rollback to any previous version.
- Tree structure with parent/child relationships and manual ordering.
- Wiki links (`[[slug]]`) with an automatic backlinks panel.
- Tags and a tag index page.
- Per-page lock for protecting core documents.
- Automatic redirects on slug change.

**Collaboration**

- Issues with comments, close, and reopen.
- Proposals with diff view, comments, conflict detection, merge, close, and
  reopen.
- Email notifications on new comments in issues and proposals.
- Audit log for every write operation, exportable as plain-text log lines.
- Audit log has a HAR-style text view and a friendly reading view.
- Audit log supports filtering by event prefix, user id, target type, keyword,
  and date range, with pagination.

**Users**

- Open, invite-only, and administrator-created registration.
- Email verification and password reset.
- GitHub OAuth single sign-on.
- Three roles: user, editor, admin.
- User list with filters, pagination, and batch operations.
- Manual user creation from the admin panel.
- Self-protection: administrators cannot demote or delete the last active
  administrator, nor change their own role or status.

**Search and discovery**

- Full-text search on SQLite FTS5 or MySQL FULLTEXT with a LIKE fallback.
- Sitemap.xml and RSS feed.

**Attachments**

- Local filesystem storage.
- S3-compatible object storage with a bundled SigV4 signer.
- Drag-and-drop, click, and paste upload in the editor.
- Attachment manager in the admin panel.

**Administration**

- Admin panel with eleven sections.
- Tabbed settings page with conditional field visibility.
- Real-time warnings for dangerous configuration combinations.
- Storage driver status and connection tests.
- Cache backend status, test, and flush.
- Redirects manager.
- 404 log with a one-click redirect creation action.

**Interface**

- Eight built-in UI languages with browser-based auto-detection.
- Toast UI Editor with Markdown and WYSIWYG modes.
- Responsive layout.
- Custom CSS, JavaScript, and footer HTML injection.
- Clean URLs without `.php` suffixes.
- Path traversal hardened `.htaccess`.

**Storage**

- SQLite driver.
- MySQL driver.
- Generic HTTP driver with a documented JSON protocol.
- Redis cache backend (optional).
- Reference HTTP database servers in Python and Node under
  `examples/http-database/`.

**Project**

- Reference SQL schemas under `database/`.
- Full documentation under `docs/`.
- GitHub issue templates, pull request template, and CI workflows.
- MIT license.

### Security

- All database access goes through prepared statements.
- Markdown rendering in safe mode.
- CSRF tokens on every state-changing form.
- Session cookie set to HttpOnly and SameSite=Lax.
- Password hashing through `password_hash()`.
- SMTP password encrypted at rest with the application key.
- PHP execution disabled under `uploads/`.
- Direct access to `config/`, `src/`, `data/`, `lang/`, `templates/`, and
  `vendor/PHPMailer/` blocked at the web server level.
- Every PHP file except the three entry points is denied.
- Automatic HTTPS redirect available as an opt-in setting.
- 
[Unreleased]: https://github.com/flightstarpilot/php-wiki/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/flightstarpilot/php-wiki/compare/v0.1.0...v0.2.0
