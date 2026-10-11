# Frequently asked questions

## Can I run this without Composer?

Yes. The project has no Composer dependencies. Everything it needs is either
in the standard library or vendored under `vendor/`. The `composer.json` file
exists only to declare project metadata; `composer install` is never required.

## Which database should I choose?

- **SQLite** if you are the only writer and expect under a few hundred
  concurrent readers. It is one file, backed up by copying it, and needs no
  separate server.
- **MySQL** if you already run a database server or expect more write
  concurrency. It is the better choice on shared hosting where SQLite files
  may be locked by the host's backup tooling.
- **HTTP** if you want to store the wiki somewhere SQLite and MySQL cannot
  reach: a serverless database, a SaaS API, another wiki's backend, or a
  Postgres instance behind a service you control.

## What is the HTTP driver for?

The HTTP driver lets you keep the wiki code untouched while storing its data
behind an API you implement. The wiki sends JSON containing SQL and bind
parameters; your endpoint executes it against any storage engine and returns
rows as JSON. See [http-database-protocol.md](http-database-protocol.md).

This is not a general-purpose database proxy. It is intended for operators
who want a supported way to slot in their own persistence without forking.

## Does it need Node.js or a build step?

No. The front end uses Tailwind CSS and Toast UI Editor from public CDNs.
There is no `package.json` and no bundler.

If your host blocks outbound CDN requests, mirror the two CSS and JS files
locally and point `templates/layout.php` and `templates/page/edit.php` at
your copies.

## How is this different from DokuWiki or Wiki.js?

- DokuWiki stores pages as files. This project stores them in a database
  and layers a proposal/issue workflow on top.
- Wiki.js is a Node.js application with a GraphQL API. This project is
  plain PHP with a JSON protocol for custom backends.
- Neither ships a first-class "propose an edit and let an admin merge it"
  flow. That is the main feature here.

## Can two people edit the same page at once?

The page editor saves a snapshot on submit. If the underlying page changed
since the editor was opened, the save still succeeds and a new version is
created. There is no automatic merge; the previous version remains in the
history.

For concurrent editing, use proposals. A proposal records the base version
it was created from, and merging warns when the page has moved past that
base.

## Can I disable registration entirely?

Yes. Settings -> Accounts. Turn off `auth.allow_register` and
`auth.invite_only`, and registration is closed. The admin can still create
accounts manually from the Users page.

## Can I import an existing wiki?

There is no import tool yet. Page content is Markdown; you can insert rows
into `pages` and `page_versions` directly. The slug is stored on
`translation_groups.slug`, and each language version is one row in `pages`
keyed by `(group_id, lang)`.

If you need an importer, open an issue describing the source format.

## How do attachments work?

Attachments are stored either on the local filesystem under
`uploads/YYYY/MM/` or in an S3-compatible bucket. Toggle the driver in
Settings -> Attachments. The local driver requires the `uploads/` directory
to be writable and blocks PHP execution through `.htaccess`; on nginx, add a
rule that denies `.php` under `/uploads/`.

## Is there a public API?

Not yet. The wiki links and search pages are the only machine-readable
outputs today: `/sitemap.xml` and `/rss.xml`. A read-only JSON API is on the
roadmap.

## Can I use my own domain and HTTPS?

Yes. Set `site.base_url` to the canonical URL in Settings -> Site. The wiki
does not terminate TLS itself; use a reverse proxy or the certificate tooling
your host provides.

## How do I report a bug or request a feature?

Open an issue using the templates in `.github/ISSUE_TEMPLATE/`. Security
problems should go through the private channel described in `SECURITY.md`.

## How do I contribute a translation?

See [translation.md](translation.md). A translation is one file plus a
one-line change to `I18nService::supported()`.
