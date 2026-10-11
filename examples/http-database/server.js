#!/usr/bin/env node
/**
 * Reference HTTP database server for the Wiki project.
 *
 * Forwards SQL from the wiki to a local SQLite database. Intended as a
 * starting point for a custom backend, not as production code.
 *
 * Environment variables:
 *   WIKI_TOKEN  Bearer token the wiki must present. Default: change-me
 *   WIKI_DB     Path to the SQLite file. Default: wiki.sqlite
 *   WIKI_PORT   Listening port. Default: 8000
 *
 * Setup:
 *   npm install express better-sqlite3
 *   node server.js
 */

'use strict';

const express = require('express');
const Database = require('better-sqlite3');

const TOKEN = process.env.WIKI_TOKEN || 'change-me';
const DB_PATH = process.env.WIKI_DB || 'wiki.sqlite';
const PORT = parseInt(process.env.WIKI_PORT || '8000', 10);

const db = new Database(DB_PATH);
db.pragma('foreign_keys = ON');

const app = express();
app.use(express.json({ limit: '8mb' }));

function authorized(req) {
    const header = req.headers.authorization || '';
    if (!header.startsWith('Bearer ')) return false;
    return header.slice('Bearer '.length).trim() === TOKEN;
}

function handle(action, sql, params, table) {
    switch (action) {
        case 'query': {
            const rows = db.prepare(sql).all(...params);
            return rows;
        }
        case 'fetchOne': {
            const row = db.prepare(sql).get(...params);
            return row === undefined ? null : row;
        }
        case 'fetchValue': {
            const row = db.prepare(sql).get(...params);
            if (row === undefined) return null;
            const keys = Object.keys(row);
            return keys.length === 0 ? null : row[keys[0]];
        }
        case 'execute': {
            const info = db.prepare(sql).run(...params);
            return info.changes;
        }
        case 'lastInsertId': {
            const row = db.prepare('SELECT last_insert_rowid() AS id').get();
            return row.id;
        }
        case 'begin':
            db.prepare('BEGIN').run();
            return true;
        case 'commit':
            db.prepare('COMMIT').run();
            return true;
        case 'rollback':
            db.prepare('ROLLBACK').run();
            return true;
        case 'tableExists': {
            const row = db
                .prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?")
                .get(table);
            return row !== undefined;
        }
        default:
            throw new Error('unknown action: ' + action);
    }
}

app.post('/wiki-db', (req, res) => {
    if (!authorized(req)) {
        return res.status(401).json({ ok: false, error: 'unauthorized' });
    }

    const body = req.body;
    if (!body || typeof body !== 'object') {
        return res.status(400).json({ ok: false, error: 'invalid JSON body' });
    }

    const action = body.action || '';
    const sql = body.sql || '';
    const params = body.params || [];
    const table = body.table;

    if (!Array.isArray(params)) {
        return res.status(400).json({ ok: false, error: 'params must be an array' });
    }

    try {
        const data = handle(action, sql, params, table);
        res.json({ ok: true, data: data });
    } catch (exc) {
        res.json({ ok: false, error: String(exc.message || exc) });
    }
});

const server = app.listen(PORT, '127.0.0.1', () => {
    console.log(`listening on http://127.0.0.1:${PORT}/wiki-db`);
});

process.on('SIGINT', () => {
    server.close(() => {
        db.close();
        process.exit(0);
    });
});
