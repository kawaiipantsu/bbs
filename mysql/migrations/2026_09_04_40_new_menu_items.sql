-- New Main Menu areas (Chiptune Radio, Weather, Users Directory, Achievements)
-- and a Games entry that opens the graphical 2D Hackers-MUD client.
-- Idempotent: each row is added only if no menu_item with that target exists yet.

INSERT INTO menu_items (menu_id, sort, hotkey, label, description, action, target, min_permission, min_role_rank)
SELECT m.id, 82, 'R', 'Chiptune Radio', 'Tracker music on rotation - .xm / .s3m / .mod', 'module', 'chiptune', NULL, 0
FROM menus m WHERE m.slug = 'main'
  AND NOT EXISTS (SELECT 1 FROM menu_items x WHERE x.menu_id = m.id AND x.target = 'chiptune');

INSERT INTO menu_items (menu_id, sort, hotkey, label, description, action, target, min_permission, min_role_rank)
SELECT m.id, 84, 'Y', 'Weather', 'Current conditions from the weather satellite', 'module', 'weather', NULL, 0
FROM menus m WHERE m.slug = 'main'
  AND NOT EXISTS (SELECT 1 FROM menu_items x WHERE x.menu_id = m.id AND x.target = 'weather');

INSERT INTO menu_items (menu_id, sort, hotkey, label, description, action, target, min_permission, min_role_rank)
SELECT m.id, 162, 'D', 'Users Directory', 'The yellow pages - every active member', 'module', 'userdir', NULL, 0
FROM menus m WHERE m.slug = 'main'
  AND NOT EXISTS (SELECT 1 FROM menu_items x WHERE x.menu_id = m.id AND x.target = 'userdir');

INSERT INTO menu_items (menu_id, sort, hotkey, label, description, action, target, min_permission, min_role_rank)
SELECT m.id, 164, 'H', 'Achievements', 'Your trophy case - badges and points', 'module', 'achievements', NULL, 0
FROM menus m WHERE m.slug = 'main'
  AND NOT EXISTS (SELECT 1 FROM menu_items x WHERE x.menu_id = m.id AND x.target = 'achievements');

INSERT INTO menu_items (menu_id, sort, hotkey, label, description, action, target, min_permission, min_role_rank)
SELECT m.id, 6, 'C', 'Hackers-MUD (2D client)', 'Open the graphical browser client in a new tab', 'url', '/hackers-mud/', NULL, 0
FROM menus m WHERE m.slug = 'games'
  AND NOT EXISTS (SELECT 1 FROM menu_items x WHERE x.menu_id = m.id AND x.target = '/hackers-mud/');
