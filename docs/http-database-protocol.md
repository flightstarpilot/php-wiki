# HTTP database protocol

The `http` storage driver lets the wiki run against any backend you choose,
in any language, without changing the wiki source. The wiki sends a JSON
request to an endpoint you control; your endpoint translates it into whatever
storage engine you have (Postgres, MongoDB, another HTTP API, a file, a
queue) and returns a JSON response.

This document specifies the wire protocol. Reference servers in Python and
Node are provided under `examples/http-database/`.

## Configuration

In `config/config.php`:

    'db' => [
        'driver'      => 'http',
        'endpoint'    => 'https://example.com/wiki-db',
        'method'      => 'POST',
        'auth_type'   => 'bearer',
        'auth_token'  => 'secret-token',
        'auth_header' => 'X-API-Key',
        'timeout'     => 15,
    ],

- `method` is usually `POST`. `GET` is accepted but the payload is sent in
  the body, so most servers will still read it as `POST`; use `POST`.
- `auth_type` is one of:
  - `none` - no authentication header
  - `bearer` - `Authorization: Bearer <token>`
  - `basic` - `Authorization: Basic <base64(token)>`, where `token` is
    `username:password`
  - `header` - `<auth_header>: <token>`
  - `query` - appends `?token=<token>` to the URL
- `timeout` is the total request timeout in seconds.

## Request

Every request is an HTTPS `POST` with `Content-Type: application/json`.

    {
        "action": "query",
        "sql":    "SELECT id, title FROM pages WHERE group_id = ?",
        "params": [42],
        "table":  null
    }

Fields:

| Field    | Type            | Present when                              |
| -------- | --------------- | ----------------------------------------- |
| `action` | string          | always                                    |
| `sql`    | string          | actions `query`, `fetchOne`, `fetchValue`, `execute` |
| `params` | array           | same as above; positional bind values     |
| `table`  | string          | action `tableExists`                      |

### Actions

| Action         | Expected `data` in response                        |
| -------------- | -------------------------------------------------- |
| `query`        | array of row objects                               |
| `fetchOne`     | one row object, or `null`                          |
| `fetchValue`   | scalar value of the first column of the first row, or `null` |
| `execute`      | integer row count affected                         |
| `lastInsertId` | integer ID of the most recent insert               |
| `begin`        | `true` on success                                  |
| `commit`       | `true` on success                                  |
| `rollback`     | `true` on success                                  |
| `tableExists`  | `true` if the named table exists                   |

The wiki issues all SQL itself. Your endpoint is responsible for translating
that SQL, or, if you prefer, for parsing it and dispatching to a real database
driver. A common approach is to forward the SQL verbatim to a real database
server that the endpoint has access to; in that case the endpoint is a thin
authenticated proxy.

## Response

Every response is JSON with `Content-Type: application/json`.

Success:

    {
        "ok": true,
        "data": <any>
    }

Failure:

    {
        "ok": false,
        "error": "human readable message"
    }

`data` may be any JSON value depending on the action. For `query`, it is an
array of objects where each key is a column name and each value is the column
value. Column types are best-effort: numbers as JSON numbers, strings as JSON
strings, `NULL` as `null`. The wiki coerces types as needed.

The HTTP status code must be `2xx` for success. The wiki reads `ok` to
determine whether the operation succeeded; a `4xx` or `5xx` status is treated
as a transport failure and the `error` field is raised as a PHP exception.

## HTTP headers

The wiki sends:

    Content-Type: application/json
    Accept: application/json
    X-Wiki-Action: <action>

Plus exactly one of the following, depending on `auth_type`:

    Authorization: Bearer <token>
    Authorization: Basic <base64>
    <auth_header>: <token>
    (query string) ?token=<token>

The `X-Wiki-Action` header duplicates the JSON `action` field so you can
route on it without parsing the body.

## Transactions

The wiki calls `begin`, then a sequence of `execute`, then either `commit` or
`rollback`. If your backend does not support transactions, return `true` from
all three actions and treat each `execute` as its own atomic operation. The
wiki tolerates this and, when a transaction is rolled back, will still have
applied the intervening writes; make sure this is acceptable for your use
case.

## Searching

The wiki issues search queries as ordinary `query` actions against the
`pages` table using either FTS5 syntax (SQLite) or `MATCH ... AGAINST`
(MySQL). If your backend does not support either, return an empty result set
for those queries. The wiki will fall back to LIKE-based search when the
full-text path returns no rows and `search.mode` is `simple`.

To avoid the full-text path entirely, set `search.mode` to `simple` in the
admin panel.

## Idempotency

`query`, `fetchOne`, `fetchValue`, and `tableExists` are read-only and must
be safe to retry. `execute` and `lastInsertId` are not; the wiki does not
retry them.

## Security

- Serve the endpoint over HTTPS. The wiki refuses to send credentials over
  plain HTTP by default.
- Use a long random `auth_token`. Treat it as you would a database password.
- Do not expose the endpoint publicly without authentication. The wiki sends
  raw SQL, so an unauthenticated endpoint is equivalent to an open database.
- Rate limit by token and log every request. The wiki sends a lot of small
  queries; a burst of requests from one IP is normal during a page render.

## Minimal reference implementation (pseudo-code)

    on POST /wiki-db:
        if not authenticated(request):
            return 401, {"ok": false, "error": "unauthorized"}

        body = json(request.body)
        action = body.action
        sql = body.sql or ""
        params = body.params or []

        try:
            if action == "query":
                rows = db.query(sql, params)
                return 200, {"ok": true, "data": rows}
            if action == "fetchOne":
                row = db.query_one(sql, params)
                return 200, {"ok": true, "data": row}
            if action == "fetchValue":
                value = db.query_value(sql, params)
                return 200, {"ok": true, "data": value}
            if action == "execute":
                affected = db.execute(sql, params)
                return 200, {"ok": true, "data": affected}
            if action == "lastInsertId":
                return 200, {"ok": true, "data": db.last_insert_id()}
            if action == "begin":
                db.begin()
                return 200, {"ok": true, "data": true}
            if action == "commit":
                db.commit()
                return 200, {"ok": true, "data": true}
            if action == "rollback":
                db.rollback()
                return 200, {"ok": true, "data": true}
            if action == "tableExists":
                exists = db.table_exists(body.table)
                return 200, {"ok": true, "data": exists}
        except Exception as exc:
            return 200, {"ok": false, "error": str(exc)}

        return 200, {"ok": false, "error": "unknown action"}

See `examples/http-database/` for full runnable servers.
