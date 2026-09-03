-- =====================================================================
--  Hackers-MUD: in-game SMS between players (the graphical client's
--  "Online / Messages" panel). Cheap direct messages, kept trimmed.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS mud_messages (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    from_id    INT UNSIGNED NOT NULL,
    from_name  VARCHAR(32)  NOT NULL,
    to_id      INT UNSIGNED NOT NULL,
    body       VARCHAR(280) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at    DATETIME NULL,
    KEY idx_mud_msg_to (to_id, id),
    KEY idx_mud_msg_from (from_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
