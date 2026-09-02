-- =====================================================================
--  THUGS(red) BBS - database schema
--  MariaDB 10.11+ / InnoDB / utf8mb4
--  Applied by:  php mysql/migrate.php
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Core
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
    version     VARCHAR(64)  NOT NULL PRIMARY KEY,
    applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key`       VARCHAR(64)  NOT NULL PRIMARY KEY,
    `value`     TEXT         NOT NULL,
    `type`      VARCHAR(16)  NOT NULL DEFAULT 'string',   -- string|int|bool|json|text|url|secret
    `label`     VARCHAR(120) NOT NULL DEFAULT '',
    `category`  VARCHAR(40)  NOT NULL DEFAULT 'general',
    `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Users / RBAC
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    handle               VARCHAR(32)  NOT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
    status               ENUM('active','pending','suspended','banned') NOT NULL DEFAULT 'active',
    tagline              VARCHAR(120) NOT NULL DEFAULT '',
    location             VARCHAR(80)  NOT NULL DEFAULT '',
    signature            VARCHAR(500) NOT NULL DEFAULT '',
    avatar_ansi          TEXT         NULL,
    last_login_at        DATETIME     NULL,
    last_login_ip        VARCHAR(45)  NULL,
    last_login_phone     VARCHAR(24)  NULL,
    calls                INT UNSIGNED NOT NULL DEFAULT 0,
    posts                INT UNSIGNED NOT NULL DEFAULT 0,
    uploads              INT UNSIGNED NOT NULL DEFAULT 0,
    downloads            INT UNSIGNED NOT NULL DEFAULT 0,
    time_bank_secs       INT          NOT NULL DEFAULT 3600,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at           DATETIME     NULL,
    UNIQUE KEY uq_users_handle (handle),
    KEY idx_users_status (status),
    KEY idx_users_last_login (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(32)  NOT NULL,
    name       VARCHAR(60)  NOT NULL,
    `rank`     INT          NOT NULL DEFAULT 0,   -- higher = more privileged
    color      VARCHAR(8)   NOT NULL DEFAULT '7',  -- ANSI colour index for the user list
    is_default TINYINT(1)   NOT NULL DEFAULT 0,   -- auto-granted to new registrations
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(64)  NOT NULL,
    description VARCHAR(160) NOT NULL DEFAULT '',
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id    INT UNSIGNED NOT NULL,
    role_id    INT UNSIGNED NOT NULL,
    granted_by INT UNSIGNED NULL,
    granted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_secrets (
    user_id         INT UNSIGNED NOT NULL PRIMARY KEY,
    email_cipher    TEXT         NULL,
    email_index     CHAR(64)     NULL,
    realname_cipher TEXT         NULL,
    notes_cipher    TEXT         NULL,   -- sysop notes about the user
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_secrets_email (email_index),
    CONSTRAINT fk_secrets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id          CHAR(64)     NOT NULL PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    ip          VARCHAR(45)  NOT NULL DEFAULT '',
    ip_phone    VARCHAR(24)  NOT NULL DEFAULT '',
    net_hash    CHAR(64)     NOT NULL DEFAULT '',
    user_agent  VARCHAR(255) NOT NULL DEFAULT '',
    node        SMALLINT     NOT NULL DEFAULT 0,
    data        MEDIUMTEXT   NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME     NOT NULL,
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    handle     VARCHAR(64) NOT NULL DEFAULT '',
    ip         VARCHAR(45) NOT NULL DEFAULT '',
    ok         TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip, created_at),
    KEY idx_attempts_handle (handle, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BBS structure: menus / screens / conferences / boards
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS screens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug         VARCHAR(64)  NOT NULL,
    title        VARCHAR(120) NOT NULL DEFAULT '',
    kind         ENUM('ansi','text','template') NOT NULL DEFAULT 'template',
    content_type ENUM('pipe','ansi','plain') NOT NULL DEFAULT 'pipe',
    body         MEDIUMTEXT   NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_screens_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menus (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(32)  NOT NULL,
    title         VARCHAR(120) NOT NULL DEFAULT '',
    header_screen VARCHAR(64)  NULL,
    prompt        VARCHAR(160) NOT NULL DEFAULT 'Command',
    columns       TINYINT      NOT NULL DEFAULT 2,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_menus_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    menu_id        INT UNSIGNED NOT NULL,
    sort           INT          NOT NULL DEFAULT 0,
    hotkey         VARCHAR(8)   NOT NULL DEFAULT '',
    label          VARCHAR(80)  NOT NULL DEFAULT '',
    description    VARCHAR(160) NOT NULL DEFAULT '',
    action         VARCHAR(32)  NOT NULL DEFAULT 'screen',  -- menu|screen|module|url|logoff|divider
    target         VARCHAR(160) NOT NULL DEFAULT '',
    min_permission VARCHAR(64)  NULL,
    min_role_rank  INT          NOT NULL DEFAULT 0,
    flags          VARCHAR(32)  NOT NULL DEFAULT '',
    enabled        TINYINT(1)   NOT NULL DEFAULT 1,
    KEY idx_menu_items_menu (menu_id, sort),
    CONSTRAINT fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conferences (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(32)  NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    description   VARCHAR(200) NOT NULL DEFAULT '',
    min_role_rank INT          NOT NULL DEFAULT 0,
    sort          INT          NOT NULL DEFAULT 0,
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conferences_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boards (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conference_id  INT UNSIGNED NOT NULL,
    slug           VARCHAR(40)  NOT NULL,
    name           VARCHAR(80)  NOT NULL,
    description    VARCHAR(200) NOT NULL DEFAULT '',
    kind           ENUM('local','announce','netmail') NOT NULL DEFAULT 'local',
    min_read_rank  INT          NOT NULL DEFAULT 0,
    min_post_rank  INT          NOT NULL DEFAULT 0,
    sort           INT          NOT NULL DEFAULT 0,
    post_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_post_at   DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_boards_conf_slug (conference_id, slug),
    CONSTRAINT fk_boards_conf FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Messages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    board_id     INT UNSIGNED NOT NULL,
    thread_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,   -- 0 until set to own id for roots
    parent_id    BIGINT UNSIGNED NULL,
    from_user_id INT UNSIGNED NULL,
    from_handle  VARCHAR(32)  NOT NULL DEFAULT '',
    to_handle    VARCHAR(32)  NOT NULL DEFAULT 'All',
    subject      VARCHAR(120) NOT NULL DEFAULT '',
    body         MEDIUMTEXT   NOT NULL,
    body_format  VARCHAR(10)  NOT NULL DEFAULT 'plain',  -- plain|pipe|ansi
    ip           VARCHAR(45)  NOT NULL DEFAULT '',
    ip_phone     VARCHAR(24)  NOT NULL DEFAULT '',
    is_pinned    TINYINT(1)   NOT NULL DEFAULT 0,
    is_locked    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at    DATETIME     NULL,
    deleted_at   DATETIME     NULL,
    KEY idx_messages_board (board_id, created_at),
    KEY idx_messages_thread (thread_id, id),
    KEY idx_messages_from (from_user_id),
    FULLTEXT KEY ft_messages (subject, body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_reads (
    user_id      INT UNSIGNED NOT NULL,
    board_id     INT UNSIGNED NOT NULL,
    last_read_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, board_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Files / Library
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS file_areas (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(40)  NOT NULL,
    name             VARCHAR(80)  NOT NULL,
    description      VARCHAR(200)  NOT NULL DEFAULT '',
    kind             ENUM('files','library') NOT NULL DEFAULT 'files',
    min_read_rank    INT          NOT NULL DEFAULT 0,
    min_upload_rank  INT          NOT NULL DEFAULT 999,
    sort             INT          NOT NULL DEFAULT 0,
    file_count       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_file_areas_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    area_id          INT UNSIGNED NOT NULL,
    filename         VARCHAR(160) NOT NULL,
    title            VARCHAR(160) NOT NULL DEFAULT '',
    description      TEXT         NULL,
    size_bytes       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sha256           CHAR(64)     NOT NULL DEFAULT '',
    storage_path     VARCHAR(255) NOT NULL DEFAULT '',   -- relative to app/storage/files
    external_url     VARCHAR(600) NULL,                  -- for library links instead of blobs
    uploader_id      INT UNSIGNED NULL,
    uploader_handle  VARCHAR(32)  NOT NULL DEFAULT '',
    downloads        INT UNSIGNED NOT NULL DEFAULT 0,
    is_approved      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at      DATETIME     NULL,
    deleted_at       DATETIME     NULL,
    KEY idx_files_area (area_id, created_at),
    KEY idx_files_approved (is_approved),
    CONSTRAINT fk_files_area FOREIGN KEY (area_id) REFERENCES file_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  One-liners / wall
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oneliners (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    handle     VARCHAR(32)  NOT NULL DEFAULT '',
    body       VARCHAR(160) NOT NULL,
    ip_phone   VARCHAR(24)  NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME     NULL,
    KEY idx_oneliners_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  News (RSS-fed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS news_items (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category     ENUM('it','hacking','tech','entertainment') NOT NULL,
    source       VARCHAR(80)  NOT NULL DEFAULT '',
    title        VARCHAR(300) NOT NULL,
    url          VARCHAR(600) NOT NULL,
    url_hash     CHAR(40)     NOT NULL,
    summary      TEXT         NULL,
    published_at DATETIME     NULL,
    fetched_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_news_url (url_hash),
    KEY idx_news_cat (category, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Voting booth
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS polls (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question    VARCHAR(200) NOT NULL,
    is_open     TINYINT(1)   NOT NULL DEFAULT 1,
    allow_multi TINYINT(1)   NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closes_at   DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_options (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    poll_id INT UNSIGNED NOT NULL,
    label   VARCHAR(120) NOT NULL,
    votes   INT UNSIGNED NOT NULL DEFAULT 0,
    sort    INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_poll_options_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_votes (
    poll_id    INT UNSIGNED NOT NULL,
    option_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    ip_phone   VARCHAR(24)  NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (poll_id, user_id, option_id),
    CONSTRAINT fk_poll_votes_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Games / doors
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS games (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(32)  NOT NULL,
    name             VARCHAR(80)  NOT NULL,
    description      VARCHAR(200)  NOT NULL DEFAULT '',
    module           VARCHAR(40)  NOT NULL,
    score_label      VARCHAR(40)  NOT NULL DEFAULT 'Score',
    score_order      ENUM('desc','asc') NOT NULL DEFAULT 'desc',
    plays            INT UNSIGNED NOT NULL DEFAULT 0,
    enabled          TINYINT(1)   NOT NULL DEFAULT 1,
    sort             INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uq_games_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_scores (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    game_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    handle     VARCHAR(32)  NOT NULL DEFAULT '',
    score      INT          NOT NULL DEFAULT 0,
    meta       VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_game_scores (game_id, score),
    CONSTRAINT fk_game_scores_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Chat
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chat_messages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    channel    VARCHAR(32)  NOT NULL DEFAULT 'main',
    user_id    INT UNSIGNED NULL,
    handle     VARCHAR(32)  NOT NULL DEFAULT '',
    body       VARCHAR(500) NOT NULL,
    kind       ENUM('say','me','system') NOT NULL DEFAULT 'say',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_channel (channel, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_presence (
    session_id   CHAR(64)    NOT NULL PRIMARY KEY,
    handle       VARCHAR(32) NOT NULL DEFAULT '',
    channel      VARCHAR(32) NOT NULL DEFAULT 'main',
    last_seen_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_presence_channel (channel, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SysOp tickets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sysop_tickets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    handle     VARCHAR(32)  NOT NULL DEFAULT '',
    subject    VARCHAR(160) NOT NULL,
    body       TEXT         NOT NULL,
    category   VARCHAR(32)  NOT NULL DEFAULT 'general',
    status     ENUM('open','pending','answered','closed') NOT NULL DEFAULT 'open',
    priority   ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    ip_phone   VARCHAR(24)  NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at  DATETIME     NULL,
    KEY idx_tickets_status (status, updated_at),
    KEY idx_tickets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_replies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id  BIGINT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    handle     VARCHAR(32)  NOT NULL DEFAULT '',
    is_staff   TINYINT(1)   NOT NULL DEFAULT 0,
    body       TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_replies (ticket_id, created_at),
    CONSTRAINT fk_treplies_ticket FOREIGN KEY (ticket_id) REFERENCES sysop_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Ops: audit, calls, discord, jobs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_handle  VARCHAR(32)  NOT NULL DEFAULT 'anonymous',
    ip            VARCHAR(45)  NOT NULL DEFAULT '',
    ip_phone      VARCHAR(24)  NOT NULL DEFAULT '',
    action        VARCHAR(64)  NOT NULL,
    target_type   VARCHAR(40)  NOT NULL DEFAULT '',
    target_id     VARCHAR(64)  NOT NULL DEFAULT '',
    summary       VARCHAR(255) NOT NULL DEFAULT '',
    meta          JSON         NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_actor (actor_user_id, created_at),
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id      CHAR(64)     NOT NULL DEFAULT '',
    user_id         INT UNSIGNED NULL,
    handle          VARCHAR(32)  NOT NULL DEFAULT 'guest',
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    ip_phone        VARCHAR(24)  NOT NULL DEFAULT '',
    node            SMALLINT     NOT NULL DEFAULT 0,
    baud            INT          NOT NULL DEFAULT 0,
    user_agent      VARCHAR(255) NOT NULL DEFAULT '',
    connected_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    disconnected_at DATETIME     NULL,
    seconds         INT          NULL,
    pages           INT UNSIGNED NOT NULL DEFAULT 0,
    actions         INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_call_log_connected (connected_at),
    KEY idx_call_log_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS discord_webhooks (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL,
    url        VARCHAR(400) NOT NULL,
    events     VARCHAR(255) NOT NULL DEFAULT '',   -- csv: user.register,ticket.new,message.new,sysop.page,...
    enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tube       VARCHAR(40)  NOT NULL,
    payload    MEDIUMTEXT   NOT NULL,
    status     ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    attempts   INT          NOT NULL DEFAULT 0,
    result     TEXT         NULL,
    run_after  DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_jobs_status (status, run_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
