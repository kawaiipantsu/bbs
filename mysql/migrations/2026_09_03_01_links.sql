-- Links directory: categories + curated links (Gadgets, AI, OSINT, Red/Blue team, ...)

CREATE TABLE IF NOT EXISTS link_categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(40)  NOT NULL,
    name        VARCHAR(80)  NOT NULL,
    description VARCHAR(200) NOT NULL DEFAULT '',
    icon        VARCHAR(8)   NOT NULL DEFAULT '',
    sort        INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_link_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS links (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id  INT UNSIGNED NOT NULL,
    title        VARCHAR(120) NOT NULL,
    url          VARCHAR(600) NOT NULL,
    description  VARCHAR(300) NOT NULL DEFAULT '',
    added_by     INT UNSIGNED NULL,
    added_handle VARCHAR(32)  NOT NULL DEFAULT '',
    clicks       INT UNSIGNED NOT NULL DEFAULT 0,
    is_approved  TINYINT(1)   NOT NULL DEFAULT 1,
    sort         INT          NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at   DATETIME     NULL,
    KEY idx_links_cat (category_id, sort),
    KEY idx_links_approved (is_approved),
    CONSTRAINT fk_links_cat FOREIGN KEY (category_id) REFERENCES link_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
