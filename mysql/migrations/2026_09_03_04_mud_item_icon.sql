-- =====================================================================
--  Hackers-MUD: a dedicated icon key per item template, so the graphical
--  client can draw a recognisable glyph for every item (on the ground,
--  in inventory, on the paper-doll, in the loot screen).
--  Populated by mysql/mud_world.php via Bbs\Mud\Icons::forItem().
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE mud_item_templates
    ADD COLUMN IF NOT EXISTS icon VARCHAR(24) NOT NULL DEFAULT '' AFTER type;
