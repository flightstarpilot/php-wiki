# Architecture

A short tour of how the code is put together.

## Request lifecycle

    Browser
       |
       v
    .htaccess  --->  /admin/* -> admin.php
                      everything else -> index.php
       |
       v
    src/Bootstrap.php
       - autoloader
       - session start
       - config load
       - database connection
       - model initialization
       - timezone, i18n, cache
       - maintenance mode check
       - HTTPS redirect check
       |
       v
    src/Router.php
       - matches the request against registered routes
       - calls the matching controller method
       |
       v
    src/Controllers/*
       - checks authentication and permissions
       - queries models
       - renders a template
       |
       v
    templates/*
       - produces HTML
       - requires templates/layout.php for the surrounding chrome

## Directory responsibilities

    src/Auth/           Session, CSRF, GitHub OAuth
    src/Controllers/    HTTP request handlers
    src/Database/       Storage abstraction and drivers
    src/Helpers/        Global functions (url, e, icon, adminUrl)
    src/Models/         Data access objects
    src/Services/       Cross-cutting services (search, mail, markdown, ...)
    templates/          PHP templates
    templates/admin/    Admin panel templates
    templates/auth/     Login, registration, password reset

## Storage abstraction

Every database access goes through `DatabaseInterface`. Three implementations
ship with the project:

- `PdoDatabase` handles SQLite and MySQL through PDO.
- `HttpDatabase` sends JSON to an endpoint you control.
- The interface itself is small: `query`, `fetchOne`, `fetchValue`, `execute`,
  `lastInsertId`, `beginTransaction`, `commit`, `rollBack`, `tableExists`.

Drivers are instantiated by `DatabaseFactory::make($config)`.

## Search abstraction

`SearchService` picks a strategy based on the active driver and the configured
`search.mode`:

- SQLite with FTS5: virtual table `pages_fts`, ranked by `bm25`.
- MySQL: `MATCH ... AGAINST` against a FULLTEXT index.
- HTTP or no FTS: `LIKE` queries against `title`, `content`, and `outline`.

The FTS index is maintained on every page write when available and can be
rebuilt from the admin panel.

## Internationalization

Two independent axes:

- **Content language** is stored per page and per translation group.
- **UI language** is negotiated from the session or the browser's
  `Accept-Language` header, falling back to `site.default_lang`.

UI strings live in `lang/{code}.php`. Incremental additions can be placed in
`lang/{code}.extra.php`; the loader merges extra over base.

## Audit log

`AuditLog::log()` records the actor, action, target, detail (as JSON), IP, and
User-Agent for every write operation. The admin panel renders entries in two
views:

- a friendly, multi-line view for reading
- a HAR-style single-line view for copy and paste into tickets or chats

Both views can be filtered, paginated, and exported.

## Front-end

- Tailwind CSS is loaded from `assets/tailwind.min.js` (locally hosted).
- Toast UI Editor is loaded from `vendor/toastui/`.
- All icons are inline SVG from the `icon()` helper.
- JavaScript is included inline in templates; there is no bundler.

## Design rules

- One setting change should not require editing code.
- No hardcoded user-facing strings. Everything goes through `I18nService`.
- No emoji in code, templates, comments, or commit messages.
- Prefer the standard library. Every dependency has to justify itself.
- Templates never query the database directly. They receive data from a
  controller or call a model method through a well-defined interface.
