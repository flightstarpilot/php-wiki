#!/usr/bin/env python3
"""
Reference HTTP database server for the Wiki project.

Forwards SQL from the wiki to a local SQLite database. Intended as a
starting point for a custom backend, not as production code.

Environment variables:
    WIKI_TOKEN  Bearer token the wiki must present. Default: change-me
    WIKI_DB     Path to the SQLite file. Default: wiki.sqlite
    WIKI_PORT   Listening port. Default: 8000

Run:
    pip install flask
    python server.py
"""

import os
import sqlite3
import sys

from flask import Flask, jsonify, request

TOKEN = os.environ.get("WIKI_TOKEN", "change-me")
DB_PATH = os.environ.get("WIKI_DB", "wiki.sqlite")
PORT = int(os.environ.get("WIKI_PORT", "8000"))

app = Flask(__name__)


def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


def authorized(req):
    header = req.headers.get("Authorization", "")
    if not header.startswith("Bearer "):
        return False
    return header[len("Bearer "):].strip() == TOKEN


def handle(action, sql, params, table):
    conn = connect()
    try:
        cur = conn.cursor()

        if action == "query":
            cur.execute(sql, params)
            rows = [dict(row) for row in cur.fetchall()]
            return rows

        if action == "fetchOne":
            cur.execute(sql, params)
            row = cur.fetchone()
            return dict(row) if row is not None else None

        if action == "fetchValue":
            cur.execute(sql, params)
            row = cur.fetchone()
            if row is None:
                return None
            return row[0]

        if action == "execute":
            cur.execute(sql, params)
            conn.commit()
            return cur.rowcount

        if action == "lastInsertId":
            cur.execute("SELECT last_insert_rowid()")
            return cur.fetchone()[0]

        if action == "begin":
            conn.execute("BEGIN")
            return True

        if action == "commit":
            conn.commit()
            return True

        if action == "rollback":
            conn.rollback()
            return True

        if action == "tableExists":
            cur.execute(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                [table],
            )
            return cur.fetchone() is not None

        raise ValueError("unknown action: %s" % action)

    finally:
        conn.close()


@app.route("/wiki-db", methods=["POST"])
def wiki_db():
    if not authorized(request):
        return jsonify({"ok": False, "error": "unauthorized"}), 401

    body = request.get_json(silent=True)
    if not isinstance(body, dict):
        return jsonify({"ok": False, "error": "invalid JSON body"}), 400

    action = body.get("action", "")
    sql = body.get("sql", "")
    params = body.get("params") or []
    table = body.get("table")

    if not isinstance(params, list):
        return jsonify({"ok": False, "error": "params must be an array"}), 400

    try:
        data = handle(action, sql, params, table)
        return jsonify({"ok": True, "data": data})
    except Exception as exc:
        return jsonify({"ok": False, "error": str(exc)})


if __name__ == "__main__":
    if not os.path.exists(DB_PATH):
        print("warning: database file %s does not exist" % DB_PATH, file=sys.stderr)
        print("         import database/sqlite.sql first", file=sys.stderr)

    print("listening on http://127.0.0.1:%d/wiki-db" % PORT)
    app.run(host="127.0.0.1", port=PORT)
