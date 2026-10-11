-- Wiki database schema (MySQL)
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------------
-- users
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(64)  NOT NULL UNIQUE,
  email           VARCHAR(191) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(128) NOT NULL DEFAULT '',
  avatar_url      VARCHAR(500) NOT NULL DEFAULT '',
  role            VARCHAR(16)  NOT NULL DEFAULT 'user',
  status          VARCHAR(16)  NOT NULL DEFAULT 'active',
  email_verified  TINYINT      NOT NULL DEFAULT 0,
  oauth_provider  VARCHAR(32)  DEFAULT NULL,
  oauth_id        VARCHAR(191) DEFAULT NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  INDEX idx_users_oauth (oauth_provider, oauth_id),
  INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- email_tokens (verify email, reset password)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  type        VARCHAR(32)  NOT NULL,
  token       VARCHAR(191) NOT NULL UNIQUE,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  INDEX idx_email_tokens_user (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- invites
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invites (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(64)  NOT NULL UNIQUE,
  created_by  INT          DEFAULT NULL,
  used_by     INT          DEFAULT NULL,
  used_at     DATETIME     DEFAULT NULL,
  expires_at  DATETIME     DEFAULT NULL,
  max_uses    INT          NOT NULL DEFAULT 1,
  used_count  INT          NOT NULL DEFAULT 0,
  note        VARCHAR(255) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- settings
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  key_name   VARCHAR(191) PRIMARY KEY,
  value      LONGTEXT,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- translation_groups
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS translation_groups (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(191) NOT NULL UNIQUE,
  default_lang  VARCHAR(16)  NOT NULL DEFAULT 'zh-CN',
  parent_id     INT          DEFAULT NULL,
  sort_order    INT          NOT NULL DEFAULT 0,
  is_locked     TINYINT      NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  INDEX idx_groups_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- pages
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  group_id        INT          NOT NULL,
  lang            VARCHAR(16)  NOT NULL DEFAULT 'zh-CN',
  title           VARCHAR(500) NOT NULL DEFAULT '',
  content         LONGTEXT,
  outline         LONGTEXT,
  current_version INT          NOT NULL DEFAULT 0,
  is_locked       TINYINT      NOT NULL DEFAULT 0,
  locked_by       INT          DEFAULT NULL,
  locked_reason   VARCHAR(255) DEFAULT NULL,
  locked_at       DATETIME     DEFAULT NULL,
  created_by      INT          DEFAULT NULL,
  updated_by      INT          DEFAULT NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  UNIQUE KEY uniq_pages_group_lang (group_id, lang),
  INDEX idx_pages_lang (lang),
  FULLTEXT INDEX ft_pages (title, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- page_versions
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_versions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  page_id     INT          NOT NULL,
  version     INT          NOT NULL,
  title       VARCHAR(500) NOT NULL DEFAULT '',
  content     LONGTEXT,
  comment     VARCHAR(500) NOT NULL DEFAULT '',
  created_by  INT          DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  UNIQUE KEY uniq_versions_page_version (page_id, version),
  INDEX idx_versions_page (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- tags
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(128) NOT NULL UNIQUE,
  slug  VARCHAR(191) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_tags (
  page_id  INT NOT NULL,
  tag_id   INT NOT NULL,
  PRIMARY KEY (page_id, tag_id),
  INDEX idx_page_tags_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- page_links
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_links (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  source_page_id  INT          NOT NULL,
  target_page_id  INT          DEFAULT NULL,
  target_slug     VARCHAR(191) NOT NULL,
  created_at      DATETIME     NOT NULL,
  INDEX idx_links_source (source_page_id),
  INDEX idx_links_target_slug (target_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- issues
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS issues (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  group_id    INT          NOT NULL,
  page_id     INT          DEFAULT NULL,
  title       VARCHAR(500) NOT NULL,
  content     LONGTEXT,
  status      VARCHAR(16)  NOT NULL DEFAULT 'open',
  created_by  INT          DEFAULT NULL,
  created_at  DATETIME     NOT NULL,
  updated_at  DATETIME     NOT NULL,
  INDEX idx_issues_group (group_id),
  INDEX idx_issues_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- proposals
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS proposals (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  page_id           INT          NOT NULL,
  title             VARCHAR(500) NOT NULL,
  description       LONGTEXT,
  status            VARCHAR(16)  NOT NULL DEFAULT 'open',
  base_version      INT          NOT NULL DEFAULT 0,
  proposed_title    VARCHAR(500) NOT NULL DEFAULT '',
  proposed_content  LONGTEXT,
  merged_version    INT          DEFAULT NULL,
  created_by        INT          DEFAULT NULL,
  reviewed_by       INT          DEFAULT NULL,
  created_at        DATETIME     NOT NULL,
  updated_at        DATETIME     NOT NULL,
  INDEX idx_proposals_page (page_id),
  INDEX idx_proposals_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- comments
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  target_type  VARCHAR(16) NOT NULL,
  target_id    INT         NOT NULL,
  content      LONGTEXT,
  created_by   INT         DEFAULT NULL,
  created_at   DATETIME    NOT NULL,
  updated_at   DATETIME    NOT NULL,
  INDEX idx_comments_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- attachments
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attachments (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  filename       VARCHAR(255) NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  mime_type      VARCHAR(128) NOT NULL DEFAULT '',
  size           INT          NOT NULL DEFAULT 0,
  storage        VARCHAR(16)  NOT NULL DEFAULT 'local',
  path           VARCHAR(500) NOT NULL DEFAULT '',
  url            VARCHAR(500) NOT NULL DEFAULT '',
  uploaded_by    INT          DEFAULT NULL,
  created_at     DATETIME     NOT NULL,
  INDEX idx_attachments_user (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- redirects
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS redirects (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  from_path    VARCHAR(500) NOT NULL UNIQUE,
  to_path      VARCHAR(500) NOT NULL,
  status_code  INT          NOT NULL DEFAULT 301,
  lang         VARCHAR(16)  DEFAULT NULL,
  hits         INT          NOT NULL DEFAULT 0,
  created_by   INT          DEFAULT NULL,
  created_at   DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- not_found_logs
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS not_found_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  path        VARCHAR(500) NOT NULL,
  referer     VARCHAR(500) NOT NULL DEFAULT '',
  user_agent  VARCHAR(500) NOT NULL DEFAULT '',
  hits        INT          NOT NULL DEFAULT 1,
  last_seen   DATETIME     NOT NULL,
  UNIQUE KEY uniq_404_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- audit_logs
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT          DEFAULT NULL,
  action       VARCHAR(64)  NOT NULL,
  target_type  VARCHAR(32)  NOT NULL DEFAULT '',
  target_id    INT          DEFAULT NULL,
  detail       LONGTEXT,
  ip           VARCHAR(64)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;