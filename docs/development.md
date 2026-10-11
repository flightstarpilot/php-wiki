# Development

## Setting up a local instance

1. Clone the repository.
2. Point a web server at the project root. The PHP built-in server works:

       php -S localhost:8080

   Do not use the built-in server in production.

3. Make `config/`, `data/`, and `uploads/` writable by the user running the
   server.
4. Visit `/install.php` and complete the wizard.
5. Delete `install.php` if the installer did not delete it for you.

## Turning on error display

Production mode hides PHP errors. For development, edit `config/config.php`
and set:

    ini_set('display_errors', '1');
    error_reporting(E_ALL);

or set the same options in your `php.ini`.

## Linting

The project has no build step and no test runner. Before committing:

    find src templates lang -name '*.php' -exec php -l {} \;

The CI workflow runs the same command across PHP 8.0 through 8.3.

## Writing a controller

Controllers live in `src/Controllers/` and are registered in `index.php` or
`admin.php`.

A typical write handler:

1. Checks authentication with `Auth::check()` or `Auth::isEditor()`.
2. Verifies the CSRF token with `Auth::verifyCsrf($_POST['csrf'] ?? null)`.
3. Validates input.
4. Calls a model method.
5. Records the change with `AuditLog::log(...)`.
6. Redirects to the appropriate page.

## Writing a template

Templates receive variables from the including controller and render HTML.
They never query the database directly.

- Escape every dynamic value with `e(...)`.
- Route user-facing strings through `I18nService::t('key')`.
- Use the `icon()` helper for icons.
- End the template with:

      <?php
      $content = ob_get_clean();
      require WIKI_ROOT . '/templates/layout.php';

## Adding a UI string

1. Add the key to `lang/en-US.php` (or `lang/en-US.extra.php` for incremental
   additions).
2. Add the translation to every other language pack you maintain.
3. Use the key in the template.

Missing keys fall back to English silently.

## Adding a setting

1. Add the default value to `Settings::defaults()` in
   `src/Models/Settings.php`.
2. Add a label to `lang/{code}.extra.php` under
   `settings.label.<key>`.
3. Add a hint to `lang/{code}.extra.php` under
   `settings.hint.<key>`.
4. If the field needs a special control (dropdown, password, textarea),
   add it to the `$typeMap` array in `templates/admin/settings.php`.
5. If the field should only appear under certain conditions, add it to the
   `$dependsMap` array in the same file.
6. Run `Settings::seedDefaults()` on existing databases to add the new key.

## Adding a database column

1. Update `database/sqlite.sql` and `database/mysql.sql`.
2. Ship a migration file under `database/migrations/` for existing
   installations.
3. Document the change in `CHANGELOG.md`.

## Commit hygiene

- One logical change per commit.
- Imperative subject line, no trailing period.
- No emoji.
- Reference the issue number if one exists.

## Reporting problems

See [CONTRIBUTING.md](../CONTRIBUTING.md).
