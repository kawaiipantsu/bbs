-- =====================================================================
--  Hackers-MUD content expansion #1
--   - readable room lore (mud_room_extras)
--   - NCPD "wanted" heat on players
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE mud_players
    ADD COLUMN IF NOT EXISTS wanted TINYINT NOT NULL DEFAULT 0 AFTER street_cred;

CREATE TABLE IF NOT EXISTS mud_room_extras (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id   INT UNSIGNED NOT NULL,
    keywords  VARCHAR(120) NOT NULL,
    body      TEXT         NOT NULL,
    KEY idx_mud_extra_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
