# Contributing

Thanks for your interest in improving Wiki. This document describes how to
report bugs, propose features, and submit pull requests.

## Reporting bugs

Open an issue using the **Bug report** template. Include:

- PHP version, database driver, and web server
- Exact steps to reproduce
- Expected behavior and actual behavior
- Any error messages from the browser console or the PHP error log

Do not paste secrets (SMTP credentials, OAuth client secrets, database
passwords) into issues.

## Proposing features

Open an issue using the **Feature request** template. Describe the problem
you are trying to solve, not only the solution you have in mind. Small,
focused proposals are easier to review than broad redesigns.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep the change focused. One logical change per pull request.
3. Match the existing code style. See **Code style** below.
4. Test locally against both SQLite and MySQL if your change touches storage.
5. Update documentation and language files when relevant.
6. Reference the issue your pull request closes, e.g. `Closes #42`.

We do not require signed commits.

## Code style

- PHP 8.0+ syntax, `declare(strict_types=1);` at the top of every file.
- PSR-12-ish: four spaces, opening brace on the same line for classes and
  methods, one blank line between methods.
- Comments in English. No emoji in source, templates, comments, or commit
  messages.
- All user-facing strings go through `I18nService::t('key')`, never hardcoded.
- All icons are inline SVG from the `icon()` helper. No emoji, no icon fonts.
- Prefer the standard library over new dependencies. The project has exactly
  one vendored dependency (Parsedown) and no build step. Keep it that way
  unless there is a strong reason.

Run a syntax check before committing:

    find src templates lang -name '*.php' -exec php -l {} \;

## Commit messages

Short imperative subject line, optionally followed by a blank line and a
longer explanation. Example:

    Add per-page lock bypass list

    Administrators can now name specific editors who may continue to edit a
    locked page. The list is stored in settings under feature.lock_bypass.

## Translations

Adding a UI language is one file. See
[docs/translation.md](docs/translation.md). Translation pull requests are
merged quickly.

## Licensing

By contributing you agree that your work is released under the MIT license,
as described in [LICENSE](LICENSE).
