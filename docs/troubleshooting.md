# Troubleshooting

## The installer reports "Permission denied" writing config.php

The `config/` directory is not writable by the web server user. Fix ownership,
not just the mode:

    chown -R www-data:www-data config data uploads
    chmod 755 config data uploads

Replace `www-data` with the user your web server runs as. Ownership matters
more than the mode number.

## The installer succeeded but the site returns 404 on /

The rewrite rules are not in effect. On Apache, check that `mod_rewrite` is
enabled and `AllowOverride All` is set for the wiki directory.

    apachectl -M | grep rewrite

On nginx, confirm the `try_files` directive is present:

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

## The site returns 500 on every page

Check the PHP error log. Common causes:

- PHP version below 8.0
- Missing PDO extension
- Broken `config/config.php` after a manual edit
- A file uploaded with the wrong line endings

Temporarily enable error display in `config/config.php`:

    ini_set('display_errors', '1');

Remember to turn it off again.

## Styles are missing or the editor does not load

The front end loads Tailwind CSS and Toast UI Editor from local copies under
`assets/` and `vendor/toastui/`. If those directories are missing or empty,
the page will render without styles and the editor will be blank.

    ls -lh assets/
    ls -lh vendor/toastui/

Download them again if needed. Both are listed in the README.

## Images fail to upload

Check the PHP upload limits:

    php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'

The defaults are often 2M. Raise them in `php.ini` or the hosting panel. The
wiki's own size limit is configured under Settings -> Attachments.

Also check:

- `uploads/` is writable by the web server user
- The file extension is in `attachment.allowed_types`
- `feature.attachments` is enabled

## Login fails with "Security check failed"

CSRF token mismatch. This usually means the session expired. Refresh the
login page and try again.

If it happens repeatedly, check that the browser accepts cookies from the
site and that the time on the server is roughly correct.

## Sitemap or RSS returns 404

Both are individually toggleable:

- `seo.sitemap_enabled` for `/sitemap.xml`
- `feature.rss` for `/rss.xml`

Enable them under Settings.

## SQLite reports "database is locked"

SQLite serializes writes. Under high write concurrency, it can time out.
Options:

- Switch to MySQL for the production instance.
- Reduce the write rate from background jobs.
- Ensure the database file is on local storage, not a network mount.

## SELinux denies writes

Check the audit log:

    sudo ausearch -m avc -ts recent

If denials mention `httpd_t`, set the file context:

    chcon -R -t httpd_sys_rw_content_t data config uploads

To make it permanent:

    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/data(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/config(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/uploads(/.*)?"
    restorecon -Rv /var/www/wiki

## The admin panel shows a blank page

Check the PHP error log. Most often this is a template syntax error introduced
by a manual edit. Run:

    find src templates lang -name '*.php' -exec php -l {} \;

Any file with a syntax error will be reported.

## The HTTP database driver returns errors

See [http-database-protocol.md](http-database-protocol.md). Common causes:

- The endpoint is not reachable over the network from the PHP host
- The bearer token is wrong or expired
- The endpoint returns a non-2xx status code
- The response body is not valid JSON

Test the connection from the admin panel under Storage.

## Something else

Open an issue with:

- PHP version, database driver, and web server
- Exact steps to reproduce
- Any error messages from the browser console or PHP error log
