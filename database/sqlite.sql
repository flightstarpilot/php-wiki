-- Wiki database schema (SQLite 3.9+)
-- Requires FTS5 for full-text search (optional)

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS users (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  username        TEXT NOT NULL UNIQUE,
  email           TEXT NOT NULL UNIQUE,
  password_hash   TEXT NOT NULL,
  display_name    TEXT NOT NULL DEFAULT '',
  avatar_url      TEXT NOT NULL DEFAULT '',
  role            TEXT NOT NULL DEFAULT 'user',
  status          TEXT NOT NULL DEFAULT 'active',
  email_verified  INTEGER NOT NULL DEFAULT 0,
  oauth_provider  TEXT DEFAULT NULL,
  oauth_id        TEXT DEFAULT NULL,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_users_oauth ON users (oauth_provider, oauth_id);
CREATE INDEX IF NOT EXISTS idx_users_role  ON users (role);

CREATE TABLE IF NOT EXISTS email_tokens (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL,
  type        TEXT NOT NULL,
  token       TEXT NOT NULL UNIQUE,
  expires_at  TEXT NOT NULL,
  used_at     TEXT DEFAULT NULL,
  created_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_email_tokens_user ON email_tokens (user_id, type);

CREATE TABLE IF NOT EXISTS invites (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  code        TEXT NOT NULL UNIQUE,
  created_by  INTEGER DEFAULT NULL,
  used_by     INTEGER DEFAULT NULL,
  used_at     TEXT DEFAULT NULL,
  expires_at  TEXT DEFAULT NULL,
  max_uses    INTEGER NOT NULL DEFAULT 1,
  used_count  INTEGER NOT NULL DEFAULT 0,
  note        TEXT NOT NULL DEFAULT '',
  created_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  key_name   TEXT PRIMARY KEY,
  value      TEXT,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS translation_groups (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  slug          TEXT NOT NULL UNIQUE,
  default_lang  TEXT NOT NULL DEFAULT 'zh-CN',
  parent_id     INTEGER DEFAULT NULL,
  sort_order    INTEGER NOT NULL DEFAULT 0,
  is_locked     INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_groups_parent ON translation_groups (parent_id);

CREATE TABLE IF NOT EXISTS pages (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id        INTEGER NOT NULL,
  lang            TEXT NOT NULL DEFAULT 'zh-CN',
  title           TEXT NOT NULL DEFAULT '',
  content         TEXT,
  outline         TEXT,
  current_version INTEGER NOT NULL DEFAULT 0,
  is_locked       INTEGER NOT NULL DEFAULT 0,
  locked_by       INTEGER DEFAULT NULL,
  locked_reason   TEXT DEFAULT NULL,
  locked_at       TEXT DEFAULT NULL,
  created_by      INTEGER DEFAULT NULL,
  updated_by      INTEGER DEFAULT NULL,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL,
  UNIQUE (group_id, lang)
);
CREATE INDEX IF NOT EXISTS idx_pages_lang ON pages (lang);

CREATE TABLE IF NOT EXISTS page_versions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  page_id     INTEGER NOT NULL,
  version     INTEGER NOT NULL,
  title       TEXT NOT NULL DEFAULT '',
  content     TEXT,
  comment     TEXT NOT NULL DEFAULT '',
  created_by  INTEGER DEFAULT NULL,
  created_at  TEXT NOT NULL,
  UNIQUE (page_id, version)
);
CREATE INDEX IF NOT EXISTS idx_versions_page ON page_versions (page_id);

CREATE TABLE IF NOT EXISTS tags (
  id    INTEGER PRIMARY KEY AUTOINCREMENT,
  name  TEXT NOT NULL UNIQUE,
  slug  TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS page_tags (
  page_id INTEGER NOT NULL,
  tag_id  INTEGER NOT NULL,
  PRIMARY KEY (page_id, tag_id)
);
CREATE INDEX IF NOT EXISTS idx_page_tags_tag ON page_tags (tag_id);

CREATE TABLE IF NOT EXISTS page_links (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  source_page_id  INTEGER NOT NULL,
  target_page_id  INTEGER DEFAULT NULL,
  target_slug     TEXT NOT NULL,
  created_at      TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_links_source ON page_links (source_page_id);
CREATE INDEX IF NOT EXISTS idx_links_target_slug ON page_links (target_slug);

CREATE TABLE IF NOT EXISTS issues (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id    INTEGER NOT NULL,
  page_id     INTEGER DEFAULT NULL,
  title       TEXT NOT NULL,
  content     TEXT,
  status      TEXT NOT NULL DEFAULT 'open',
  created_by  INTEGER DEFAULT NULL,
  created_at  TEXT NOT NULL,
  updated_at  TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_issues_group ON issues (group_id);
CREATE INDEX IF NOT EXISTS idx_issues_status ON issues (status);

CREATE TABLE IF NOT EXISTS proposals (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  page_id           INTEGER NOT NULL,
  title             TEXT NOT NULL,
  description       TEXT,
  status            TEXT NOT NULL DEFAULT 'open',
  base_version      INTEGER NOT NULL DEFAULT 0,
  proposed_title    TEXT NOT NULL DEFAULT '',
  proposed_content  TEXT,
  merged_version    INTEGER DEFAULT NULL,
  created_by        INTEGER DEFAULT NULL,
  reviewed_by       INTEGER DEFAULT NULL,
  created_at        TEXT NOT NULL,
  updated_at        TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_proposals_page ON proposals (page_id);
CREATE INDEX IF NOT EXISTS idx_proposals_status ON proposals (status);

CREATE TABLE IF NOT EXISTS comments (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  target_type  TEXT NOT NULL,
  target_id    INTEGER NOT NULL,
  content      TEXT,
  created_by   INTEGER DEFAULT NULL,
  created_at   TEXT NOT NULL,
  updated_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_comments_target ON comments (target_type, target_id);

CREATE TABLE IF NOT EXISTS attachments (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  filename       TEXT NOT NULL,
  original_name  TEXT NOT NULL,
  mime_type      TEXT NOT NULL DEFAULT '',
  size           INTEGER NOT NULL DEFAULT 0,
  storage        TEXT NOT NULL DEFAULT 'local',
  path           TEXT NOT NULL DEFAULT '',
  url            TEXT NOT NULL DEFAULT '',
  uploaded_by    INTEGER DEFAULT NULL,
  created_at     TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attachments_user ON attachments (uploaded_by);

CREATE TABLE IF NOT EXISTS redirects (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  from_path    TEXT NOT NULL UNIQUE,
  to_path      TEXT NOT NULL,
  status_code  INTEGER NOT NULL DEFAULT 301,
  lang         TEXT DEFAULT NULL,
  hits         INTEGER NOT NULL DEFAULT 0,
  created_by   INTEGER DEFAULT NULL,
  created_at   TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS not_found_logs (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  path        TEXT NOT NULL,
  referer     TEXT NOT NULL DEFAULT '',
  user_agent  TEXT NOT NULL DEFAULT '',
  hits        INTEGER NOT NULL DEFAULT 1,
  last_seen   TEXT NOT NULL,
  UNIQUE (path)
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER DEFAULT NULL,
  action       TEXT NOT NULL,
  target_type  TEXT NOT NULL DEFAULT '',
  target_id    INTEGER DEFAULT NULL,
  detail       TEXT,
  ip           TEXT NOT NULL DEFAULT '',
  created_at   TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs (user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs (action);

-- Optional FTS5 virtual table (created by admin via "Rebuild search index")
-- Kept out of the base schema so that builds without FTS5 can still install.