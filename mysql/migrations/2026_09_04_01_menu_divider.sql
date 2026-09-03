-- Drop the mid-list divider on the Main Menu. In the column-major layout it
-- landed at the foot of the left column as a short rule "hanging in the air";
-- the full-width footer rule already separates the menu from the hint line.
DELETE mi FROM menu_items mi
  JOIN menus m ON m.id = mi.menu_id
 WHERE m.slug = 'main' AND mi.action = 'divider' AND mi.sort = 89;
