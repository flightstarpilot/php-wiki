# Installation

This guide covers installing the wiki on a typical shared host or VPS with
PHP 8.0 or later and either SQLite or MySQL.

## Requirements

- PHP 8.0 or later with the `pdo` extension enabled
- One of:
  - `pdo_sqlite` with a writable `data/` directory, or
  - `pdo_mysql` and a MySQL 5.7+ / MariaDB 10.2+ database
- A writable `config/` directory (the installer writes `config/config.php`)
- A writable `uploads/` directory if you plan to enable attachments
- Optional: `ext-curl` for the HTTP storage driver and GitHub OAuth
- Optional: `ext-openssl` for encrypting SMTP credentials at rest

No Composer, no npm, and no build step are required.

## Step 1: Upload the files

Copy the contents of the repository to your web root. The layout should look
like this:

    /var/www/wiki/
      admin.php
      index.php
      install.php
      config/
      data/
      database/
      docs/
      examples/
      lang/
      src/
      templates/
      uploads/
      vendor/

### Where to put it

Use a normal web root:

- `/var/www/wiki` on Debian and Ubuntu
- `/usr/share/nginx/html/wiki` on some RHEL-family systems
- `/www/wwwroot/wiki` on BaoTa panels
- `~/public_html/wiki` on shared hosting

**Do not place the wiki under `/root`.** The `/root` directory is mode 700
and cannot be traversed by the web server user. A wiki installed there will
appear to work from the command line but will fail with permission errors
when accessed through the browser.

### Setting permissions

Three directories must be writable by the **web server user**:

| Directory  | Why it must be writable                                        |
| ---------- | -------------------------------------------------------------- |
| `data/`    | SQLite database, `-wal`, and `-shm` files                      |
| `config/`  | `config.php`, written by the installer                         |
| `uploads/` | Attachments, when the local storage driver is enabled          |

Find the web server user first:

    ps aux | grep -E 'nginx|apache|php-fpm|httpd' | grep -v grep

Common values:

| Stack                          | User       |
| ------------------------------ | ---------- |
| Debian / Ubuntu + nginx + FPM  | `www-data` |
| RHEL / CentOS + nginx + FPM    | `nginx`    |
| RHEL / CentOS + Apache         | `apache`   |
| BaoTa panel                    | `www`      |
| cPanel / shared hosting        | your own account |
| Docker (official php image)    | `www-data` |

Then set ownership and mode, substituting the correct user:

    chown -R www-data:www-data data config uploads
    chmod 755 data config uploads

Do not use `chmod 777`. It grants write access to every user on the system,
which on shared hosting means any other tenant can replace your database.

### Why ownership matters

A directory owned by `root` with mode 755 is **not** writable by the web
server. The mode grants write permission to the owner only, and the owner is
root. Set the owner, not just the mode.

### SELinux

On RHEL, CentOS, Rocky, and AlmaLinux, SELinux may block writes even when
Unix permissions are correct. Set the file context:

    chcon -R -t httpd_sys_rw_content_t data config uploads

To make the change persistent across relabels:

    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/data(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/config(/.*)?"
    semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/wiki/uploads(/.*)?"
    restorecon -Rv /var/www/wiki

Adjust the path to match your installation.

### Verifying write access

Run this from the wiki root, as the web server user:

    sudo -u www-data php -r 'var_dump(is_writable("data"), is_writable("config"), is_writable("uploads"));'

All three values must be `true`. If any is `false`, the installer will refuse
to proceed.

## Step 2: Configure the web server

### Apache

The repository ships with a `.htaccess` that rewrites every request to
`index.php` and blocks direct access to `config/`, `src/`, and `data/`.
Make sure `mod_rewrite` and `AllowOverride All` are enabled for the wiki
directory.

If `AllowOverride` cannot be enabled on your host, the wiki will still work
for the home page but links such as `/page/foo` will 404. Either enable
`AllowOverride` or move the rewrite rules into your Apache vhost.

### nginx

Add this to your server block:

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/(config|src|data)/ {
        deny all;
        return 404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

Adjust the `fastcgi_pass` line to match your PHP-FPM socket path. On
RHEL-family systems it is often `unix:/run/php-fpm/www.sock`.

### PHP built-in server (development only)

    php -S localhost:8080

Do not use the built-in server in production. It does not enforce the
`.htaccess` protections and does not handle concurrent requests well.

## Step 3: Run the installer

Open `/install.php` in a browser. The page performs an environment check and
then asks for:

1. **Database driver.** SQLite is the fastest path. MySQL is recommended if
   you already have a database and want to use it.
2. **Connection details.** For SQLite, nothing is needed: the installer
   writes `data/wiki.sqlite`. For MySQL, provide host, port, database name,
   username, and password.
3. **Administrator account.** Username, email, and password. This account
   receives the `admin` role and cannot be deleted from the panel.

The installer applies `database/sqlite.sql` or `database/mysql.sql`, seeds
the default settings, and writes `config/config.php`.

After a successful install, confirm the database file exists:

    ls -lh data/
    # -rw-r--r-- 1 www-data www-data ... wiki.sqlite
    # -rw-r--r-- 1 www-data www-data ... wiki.sqlite-shm
    # -rw-r--r-- 1 www-data www-data ... wiki.sqlite-wal

The `-shm` and `-wal` files appear when the database is in WAL mode, which is
the default. They are normal.

## Step 4: Delete the installer

**Delete `install.php` immediately** once the installer reports success. It
contains no authentication and can re-run setup if left in place.

    rm install.php

## Step 5: First login

Visit `/admin.php` and sign in with the administrator account. From the panel:

- Settings -> Site: set the site name, default language, and time zone.
- Settings -> Features: enable or disable issues, proposals, attachments,
  tags, search, and other modules.
- Users: optionally create invitations if you want registration to be
  invite-only.

## Step 6: Write your first page

Visit `/new` while signed in as the administrator or an editor. After saving,
the page appears in the sidebar tree. The page slug is generated from the
title; you can rename it later from the admin panel or from the edit form.

## Manual installation

If you prefer not to use the installer:

1. Import `database/sqlite.sql` or `database/mysql.sql` into a fresh database.
2. Copy `config/config.sample.php` to `config/config.php` and fill in the
   connection details and a random 64-character hex application key.
   Generate a key with:
       php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
3. Insert the first administrator row into `users` with a password hash
   produced by `password_hash()`.

## Troubleshooting

### "Database connection failed"

- SQLite: make sure `data/` is writable by the web server user. Check both
  ownership and mode; ownership is the common mistake.
- MySQL: verify host, port, database name, and credentials. On shared hosting
  the database host is often not `localhost`.

### "The requested URL was not found"

The rewrite rules are not in effect. On Apache, check that `mod_rewrite` is
enabled and that `.htaccess` is being read. On nginx, confirm the `try_files`
directive is present.

### Blank page or HTTP 500

Check the PHP error log. The most common causes are an unsupported PHP version
or a missing PDO extension.

### CSS or JavaScript does not load

The wiki loads Tailwind CSS and Toast UI Editor from public CDNs. If the host
blocks outbound requests, mirror those files locally and adjust
`templates/layout.php` and `templates/page/edit.php`.

### SQLite fails with "unable to open database file"

The web server user cannot write to `data/`, or the directory's parent chain
is not traversable. Check ownership with `ls -ld data` and confirm the web
server user can reach every directory from `/` down to `data/`.

### SELinux denials

Check `sudo ausearch -m avc -ts recent` and `sudo sealert -a /var/log/audit/audit.log`.
If denials mention `httpd_t`, set the file context as described above.

## Uninstalling

Delete the files and drop the database. Nothing else is stored on the server.
