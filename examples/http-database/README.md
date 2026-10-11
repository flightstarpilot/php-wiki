# HTTP database reference servers

Two reference implementations of the HTTP storage protocol described in
`docs/http-database-protocol.md`. Both are thin authenticated proxies that
forward SQL to a local SQLite database. They exist so you can bring up the
wiki against the `http` driver without writing a server from scratch, and as
a starting point for a real backend.

Both servers are deliberately minimal. They are not hardened for production
and they run over plain HTTP by default. Do not expose them to the internet
without adding TLS, stronger authentication, and rate limiting.

## Python

Requires Python 3.9+ and Flask.

    pip install flask
    python server.py

Environment variables:

- `WIKI_TOKEN` - bearer token expected in `Authorization: Bearer ...`.
  Defaults to `change-me`.
- `WIKI_DB` - path to the SQLite file. Defaults to `wiki.sqlite` in the
  current directory.
- `WIKI_PORT` - listening port. Defaults to `8000`.

## Node.js

Requires Node 18+.

    npm install
    node server.js

Environment variables are the same as the Python server.

## Configuring the wiki

In `config/config.php`:

    'db' => [
        'driver'     => 'http',
        'endpoint'   => 'http://127.0.0.1:8000/wiki-db',
        'method'     => 'POST',
        'auth_type'  => 'bearer',
        'auth_token' => 'change-me',
        'timeout'    => 15,
    ],

Then import the schema into the SQLite file the server points at:

    sqlite3 wiki.sqlite < ../../database/sqlite.sql

## What the servers do

For every request they:

1. Read the JSON body.
2. Authenticate.
3. Execute the requested action against SQLite.
4. Return `{"ok": true, "data": ...}` or `{"ok": false, "error": "..."}`.

They do not parse or validate SQL. Whatever the wiki sends is passed
directly to the SQLite driver. This is intentional: the wiki is the only
client, and the endpoint is meant to be reachable only by the wiki.

## Adapting for a real backend

To use a different storage engine, replace the `handle` function. Keep the
action names and the response envelope; the wiki does not care what happens
underneath.

If your backend speaks a query API rather than SQL, you will need to either
parse the incoming SQL or run a compatibility layer such as a Postgres
instance. The wiki does not currently abstract away SQL.
