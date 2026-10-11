# Security policy

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues.

Use GitHub's private vulnerability reporting feature, or send an email to the
maintainer address listed in `composer.json`.

Include:

- A description of the vulnerability
- Steps to reproduce, or a proof of concept
- The affected version(s)
- Any suggested fix, if you have one

You will receive an acknowledgement within a few days. Please give us a
reasonable window to release a fix before disclosing publicly.

## Supported versions

Only the latest release on the `main` branch receives security fixes. Older
releases are not maintained.

## Scope

In scope:

- Authentication and session handling
- Authorization checks in controllers
- SQL injection in the PDO driver
- Template injection and XSS in rendered Markdown
- CSRF protection gaps
- Path traversal in the attachment and local storage drivers
- Server-side request forgery in the HTTP database driver

Out of scope:

- Vulnerabilities in third-party services the administrator connects to
- Misconfiguration by the administrator (for example exposing
  `config/config.php`)
- Denial of service through resource exhaustion on shared hosting

## Hardening checklist

Administrators should verify:

- `install.php` has been removed after installation
- `config/`, `data/`, `src/`, `lang/`, `templates/`, and `vendor/PHPMailer/`
  are not reachable over HTTP
- `uploads/` does not execute PHP
- The web server user is not the same as the system root user
- Database credentials are unique to this application
- SMTP credentials are stored encrypted (they are by default)
- HTTPS is enabled if the site is reachable from the public internet
- The audit log is reviewed periodically

## Known limitations

- The HTTP database driver sends raw SQL to the configured endpoint. Treat
  the endpoint as you would a database connection: over TLS, with a long
  random token, and rate-limited.
- Session fixation protection relies on `session_regenerate_id()`, which is
  called on login and OAuth callback.
- There is no rate limiting on login attempts in this release. Put the site
  behind a reverse proxy or a WAF if that matters for your deployment.
