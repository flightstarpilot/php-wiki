# Wiki

A lightweight, self-hosted wiki engine in plain PHP. Public read, authenticated
collaboration through issues and proposals, multi-language content, tree
structure, tags, backlinks, full-text search, attachments, and pluggable
storage drivers.

No build step. No Composer. Upload the folder to any PHP host and it runs.

[中文说明](README.zh-CN.md) | [Documentation](docs/) | [Roadmap](docs/roadmap.md)

## Features

### Content

- Translation groups: one logical page, N language versions, each with its
  own version history but a shared discussion thread.
- Version history for every page with side-by-side and unified diff views.
- One-click rollback to any earlier version.
- Tree structure with parent/child relationships and drag-free reordering.
- Wiki links (`[[slug]]`) with an automatic backlinks panel.
- Tags with a browsable tag index.
- Per-page lock to protect core documents.
- Automatic redirects when a slug changes.

### Collaboration

- Issues: discussion threads with comments, opened and closed by any
  authenticated user.
- Proposals: user-submitted edits that editors review and merge. Diff view,
  multi-round discussion, and conflict detection against a base version.
- Comments on both issues and proposals.
- Email notifications when someone participates in a thread you are in.
- Audit log of every write operation, exportable as plain-text log lines.

### Users and access

- Open registration, invite-only registration, or manual creation by an admin.
- Email verification and password reset.
- GitHub OAuth single sign-on.
- Three roles: user (read), editor (write pages), admin (everything).

### Search and discovery

- Full-text search on SQLite FTS5 or MySQL FULLTEXT with automatic LIKE
  fallback.
- Header search box, tag pages, sitemap.xml, and RSS feed.

### Attachments

- Local filesystem storage or any S3-compatible object store.
- Bundled SigV4 signer compatible with AWS S3, Cloudflare R2, MinIO, and
  Aliyun OSS.
- Drag-and-drop, click-to-upload, and paste-to-upload in the editor.

### Administration

- 11-section admin panel covering site metadata, SEO, mail, accounts,
  features, storage, attachments, search, redirects, audit log, and
  appearance.
- Tabbed settings page with conditional field visibility.
- Real-time configuration warnings for dangerous combinations.
- Batch user operations with filters, sorting, and pagination.
- Manual user creation.
- Full audit log with HAR-style text output and a friendly reading view.

### Interface

- Eight built-in UI languages auto-detected from the browser.
- Toast UI Editor with Markdown and WYSIWYG modes.
- Responsive layout, light and dark themes.
- Clean URLs without `.php` suffixes.
- Custom CSS, custom JavaScript, and footer HTML injection.

### Storage

| Driver   | Description                                                  |
| -------- | ------------------------------------------------------------ |
| `sqlite` | Single-file database. Zero configuration.                    |
| `mysql`  | MySQL 5.7+ / MariaDB 10.2+.                                  |
| `http`   | Custom JSON endpoint. Bring your own backend in any language. |

Redis is available as an optional cache backend.

## Requirements

- PHP 8.0 or later
- PDO with either `pdo_sqlite` or `pdo_mysql`
- Optional: `pdo_sqlite` compiled with FTS5 for full-text search
- Optional: `curl` for the HTTP database driver and GitHub OAuth
- Optional: `openssl` for encrypting SMTP credentials at rest

No Composer, no npm, no build tooling.

## Quick start

1. Download or clone this repository into your web root.
2. Set the ownership and permissions described below.
3. Visit `/install.php` in a browser and follow the wizard.
4. The installer deletes itself when it succeeds.
5. Visit `/admin` and sign in with the admin account you created.
6. Write your first page at `/new`.

Detailed instructions: [docs/installation.md](docs/installation.md).

## File permissions

Three directories must be writable by the web server user, not by root:

    data/      SQLite database, write-ahead log, and shared memory files
    config/    config.php written by the installer
    uploads/   uploaded attachments when the local driver is enabled

Find the web server user first:

    ps aux | grep -E 'nginx|apache|php-fpm|httpd' | grep -v grep

Common values: `www-data` (Debian, Ubuntu), `nginx` or `apache` (RHEL,
CentOS), `www` (BaoTa panel), or your own account on shared hosting. Then:

    chown -R www-data:www-data data config uploads
    chmod 755 data config uploads

Substitute the correct user for your system. Do not use `chmod 777`.

Ownership matters more than the mode number. A directory owned by root with
mode 755 is not writable by the web server, even though the mode looks
permissive.

Do not place the wiki under `/root`. The `/root` directory itself is mode
700, so the web server cannot traverse into it regardless of the permissions
on the wiki directory. Use a normal web root such as `/var/www/wiki` or
`/www/wwwroot/wiki`.

On systems with SELinux enabled (RHEL, CentOS, Rocky, AlmaLinux):

    chcon -R -t httpd_sys_rw_content_t data config uploads

## Configuration

Everything is configured from the admin panel. The only file you edit by
hand is `config/config.php`, which is generated by the installer. See
[docs/configuration.md](docs/configuration.md) for the full reference.

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Architecture](docs/architecture.md)
- [HTTP database protocol](docs/http-database-protocol.md)
- [Translation](docs/translation.md)
- [Development](docs/development.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Upgrading](docs/upgrading.md)
- [FAQ](docs/faq.md)
- [Roadmap](docs/roadmap.md)

## Directory layout

    admin.php                Admin entry point (rewritten to /admin)
    index.php                Public front controller
    install.php              Installer, self-deletes on success
    .htaccess                URL rewriting and access hardening
    assets/                  Locally hosted front-end libraries
    config/                  Generated configuration (writable)
    data/                    SQLite database (writable)
    database/                Reference SQL schemas and migrations
    docs/                    Project documentation
    examples/                Reference HTTP database servers
    lang/                    UI language packs
    src/                     Application source
    templates/               PHP templates
    uploads/                 Uploaded attachments (writable)
    vendor/                  Vendored third-party libraries

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Translation contributions are
particularly welcome; see [docs/translation.md](docs/translation.md).

## Security

Please do not open public issues for security problems. See
[SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).

Third-party components:

- [Parsedown](https://github.com/erusev/parsedown) and
  [Parsedown Extra](https://github.com/erusev/parsedown-extra) - MIT
- [Toast UI Editor](https://github.com/nhn/tui.editor) - MIT
- [Tailwind CSS](https://tailwindcss.com/) - MIT
- [Lucide icons](https://lucide.dev/) - ISC
- Optional: [PHPMailer](https://github.com/PHPMailer/PHPMailer) - LGPL-2.1
