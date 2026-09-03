-- =====================================================================
--  THUGS(red) BBS - seed data
--  Loaded by:  php mysql/migrate.php --seed
--  Idempotent: uses INSERT ... ON DUPLICATE KEY UPDATE / INSERT IGNORE.
--  Pipe colour codes: |00-|15 = foreground, |16-|23 = background.
--  Template tokens: {{site_name}} {{phone}} {{ip}} {{node}} {{baud}}
--                   {{users_total}} {{calls_total}} {{messages_total}}
--                   {{files_total}} {{last_caller}} {{now}} {{handle}}
--                   {{version}} {{sysop_handle}} {{telnet_host}}
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  settings (global config - editable in SysOp -> Global Config)
-- ---------------------------------------------------------------------
INSERT INTO settings (`key`,`value`,`type`,`label`,`category`) VALUES
 ('site_name','THUGS(red) BBS','string','Board name','identity'),
 ('site_tagline','ANSI since the phone lines were warm','string','Tagline','identity'),
 ('sysop_handle','sysop','string','SysOp handle','identity'),
 ('telnet_host','bbs.thugs.red','string','Telnet host shown at connect','identity'),
 ('version','1.0.0','string','Software version','identity'),
 ('canonical','https://bbs.thugs.red','url','Canonical URL','identity'),
 ('nodes','8','int','Simultaneous nodes (phone lines)','system'),
 ('baud','57600','int','CONNECT baud string','system'),
 ('term_cols','104','int','Terminal columns','system'),
 ('term_rows','38','int','Terminal rows','system'),
 ('new_user_role','user','string','Role auto-granted on registration','system'),
 ('registration_open','1','bool','Allow new user registration','system'),
 ('guest_browsing','1','bool','Let guests read public areas','system'),
 ('crt_intensity','0.85','string','CRT effect intensity 0..1','appearance'),
 ('font_scale','1.0','string','Font scaling (0.1-3.0; bigger = larger text, fewer columns)','appearance'),
 ('crt_scanlines','1','bool','Scanline overlay','appearance'),
 ('crt_flicker','1','bool','Phosphor flicker','appearance'),
 ('crt_curvature','1','bool','Barrel distortion','appearance'),
 ('sound_default','0','bool','Sound on by default','appearance'),
 ('motd_screen','boot.motd','string','Screen shown at connect','appearance'),
 ('banner_screen','art.logo','string','Logo screen shown above the MOTD and Main Menu','appearance'),
 ('discord_enabled','0','bool','Enable Discord webhooks','integrations'),
 ('discord_events','user.register,ticket.new,ticket.reply,message.new,sysop.page','string','Events that fire webhooks','integrations'),
 ('news_feeds_it','https://www.theregister.com/headlines.atom','text','IT news RSS feeds (one per line)','news'),
 ('news_feeds_hacking',"https://www.bleepingcomputer.com/feed/\nhttps://krebsonsecurity.com/feed/",'text','Hacking news RSS feeds','news'),
 ('news_feeds_tech',"https://feeds.arstechnica.com/arstechnica/index\nhttps://hnrss.org/frontpage",'text','Tech news RSS feeds','news'),
 ('news_feeds_entertainment',"https://variety.com/feed/\nhttps://www.polygon.com/rss/index.xml",'text','Entertainment news RSS feeds','news'),
 ('news_max_per_cat','80','int','Max cached items per category','news'),
 ('chat_idle_secs','90','int','Seconds before a chatter is considered gone','chat'),
 ('maintenance','0','bool','Maintenance mode (non-staff hear a busy tone)','system'),
 ('maintenance_msg','The board is down for maintenance. The SysOp is elbow-deep in it. Dial back a bit later.','text','Message shown during maintenance','system'),
 ('ticker_sources','custom,news,oneliners','string','Bottom crawl: which feeds, in order (custom,news,oneliners)','ticker'),
 ('ticker_custom',"Welcome to THUGS(red) BBS - leave a message, sign the wall, play a door game.\nType the letter in [brackets] to move around. ESC backs out.\nYour IP shows up as a phone number. That is on purpose.",'text','Bottom crawl: custom messages, one per line','ticker'),
 ('ticker_news_count','4','int','Bottom crawl: number of latest news headlines (0 = none)','ticker'),
 ('ticker_oneliner_count','10','int','Bottom crawl: number of latest one-liners (0 = none)','ticker'),
 ('ticker_speed_secs','55','int','Bottom crawl: seconds for one full loop (lower = faster)','ticker'),
 ('seo_title','THUGS(red) BBS - an ANSI bulletin board inside a CRT','string','Homepage <title> / OG title','seo'),
 ('seo_description','A keyboard-driven ANSI bulletin board that runs inside a CRT terminal - messages, files, door games, chat and a live news wire.','text','Meta description','seo')
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `type`=VALUES(`type`), `category`=VALUES(`category`);

-- ---------------------------------------------------------------------
--  roles
-- ---------------------------------------------------------------------
INSERT INTO roles (slug,name,`rank`,color,is_default) VALUES
 ('guest','Guest',0,'8',0),
 ('user','User',10,'7',1),
 ('elite','Elite',30,'14',0),
 ('cosysop','Co-SysOp',80,'11',0),
 ('sysop','SysOp',100,'12',0)
ON DUPLICATE KEY UPDATE name=VALUES(name), `rank`=VALUES(`rank`), color=VALUES(color), is_default=VALUES(is_default);

-- ---------------------------------------------------------------------
--  permissions
-- ---------------------------------------------------------------------
INSERT INTO permissions (slug,description) VALUES
 ('message.read','Read message boards'),
 ('message.post','Post and reply to messages'),
 ('message.delete.any','Delete anyone''s message'),
 ('file.read','Browse file areas'),
 ('file.download','Download files'),
 ('file.upload','Upload files'),
 ('file.approve','Approve uploaded files'),
 ('chat.use','Join node chat'),
 ('oneliner.post','Add a one-liner to the wall'),
 ('poll.vote','Vote in the voting booth'),
 ('poll.manage','Create and close polls'),
 ('game.play','Play door games'),
 ('ticket.create','Open a SysOp ticket'),
 ('ticket.manage','Answer and manage tickets'),
 ('conference.join','Join non-default conferences'),
 ('admin.access','Enter the SysOp / Admin area'),
 ('admin.users','Manage users and roles'),
 ('admin.content','Manage boards, files, news, polls, one-liners'),
 ('admin.screens','Edit screens and menus'),
 ('admin.config','Edit global configuration'),
 ('admin.integrations','Manage Discord webhooks and integrations'),
 ('admin.audit','View the audit log'),
 ('admin.calls','View the call log'),
 ('admin.impersonate','Impersonate another user')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- role_permissions: user
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.slug='user' AND p.slug IN
 ('message.read','message.post','file.read','file.download','chat.use','oneliner.post',
  'poll.vote','game.play','ticket.create','conference.join');

-- guest: read-only
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.slug='guest' AND p.slug IN ('message.read','file.read');

-- elite: user + uploads + more
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.slug='elite' AND p.slug IN
 ('message.read','message.post','file.read','file.download','file.upload','chat.use',
  'oneliner.post','poll.vote','game.play','ticket.create','conference.join');

-- cosysop: everything except a few sysop-only bits
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p
 WHERE r.slug='cosysop' AND p.slug IN
 ('message.read','message.post','message.delete.any','file.read','file.download','file.upload',
  'file.approve','chat.use','oneliner.post','poll.vote','poll.manage','game.play','ticket.create',
  'ticket.manage','conference.join','admin.access','admin.users','admin.content','admin.screens',
  'admin.audit','admin.calls');

-- sysop: all permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
 SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.slug='sysop';

-- ---------------------------------------------------------------------
--  screens  (pipe colour)
-- ---------------------------------------------------------------------
INSERT INTO screens (slug,title,kind,content_type,body) VALUES
-- The connect banner is prepended from the `art.logo` screen (banner_screen
-- setting) by Engine::renderMotd(), so this body starts straight at the rule.
('boot.motd','Message of the Day','template','pipe',
'|08 .--------------------------------------------------------------------------.
|07            B U L L E T I N   B O A R D   S Y S T E M   |08v{{version}}
|08 `--------------------------------------------------------------------------`

|07   Connected to |14{{site_name}}|07 at |10{{baud}}|07 baud, node |10{{node}}|07 of {{nodes_total}}.
|07   You are calling from |12{{phone}}|07   (|08{{ip}}|07)
|07   Local time is |11{{now}}|07.

|15   >> {{site_tagline}} <<

|07   Callers:  |14{{calls_total}}|07     Users: |14{{users_total}}|07     Messages: |14{{messages_total}}|07     Files: |14{{files_total}}
|07   Last caller: |11{{last_caller}}

|08   Press |15ENTER|08 to continue...'),

('auth.welcome','Login','template','pipe',
'|08.----------------------------------------------------------------------------.|07
|08|  |12{{site_name}} |08- |07who goes there?                                       |08|
|08''----------------------------------------------------------------------------''|07

|07   [|14L|07] Log in with an existing handle
|07   [|14N|07] New user - apply for an account
|07   [|14G|07] Continue as Guest (read-only in public areas)
|07   [|14Q|07] Hang up

|07   The SysOp reads everything. Be excellent to each other.
'),

('logoff','Goodbye','template','pipe',
'|08+============================================================================+|07

|11   NO CARRIER
|07
|07   Thank you for calling |14{{site_name}}|07, |15{{handle}}|07.
|07   You were connected for |11{{session_time}}|07 and viewed |11{{session_pages}}|07 screens.
|07
|07   The line is now free for the next caller.
|07   Dial back soon - the SysOp gets lonely.
|07
|08+============================================================================+|07'),

('sys.info','System Information','template','pipe',
'|08== |14SYSTEM INFORMATION|08 =========================================================|07

|07   Board name .......: |15{{site_name}}
|07   Software .........: THUGS(red) BBS Engine v{{version}} (PHP {{php_version}})
|07   SysOp ............: |11{{sysop_handle}}
|07   Telnet ...........: |14{{telnet_host}}|07   (also: this web terminal)
|07   Nodes ............: {{nodes_total}} simultaneous lines
|07   Connect speed ....: {{baud}} baud (emulated)
|07   Host uptime ......: {{host_uptime}}
|07   Database .........: MariaDB, everything lives in the DB
|07
|07   Statistics
|07   ----------
|07   Total callers ....: |14{{calls_total}}
|07   Registered users .: |14{{users_total}}
|07   Messages posted ..: |14{{messages_total}}
|07   Files catalogued .: |14{{files_total}}
|07   One-liners .......: |14{{oneliners_total}}
|07
|08============================================================================|07'),

('credits','Credits','template','pipe',
'|08== |14CREDITS|08 ===================================================================|07

|07   |14{{site_name}}|07 is an original build for |12thugs.red|07.
|07
|07   Concept & SysOp ....: {{sysop_handle}}
|07   Engine .............: THUGS(red) BBS (PHP 8, MariaDB, Redis, vanilla JS)
|07   Terminal font ......: PxPlus IBM VGA - VileR / int10h.org (CC BY-SA 4.0)
|07   Palette ............: wardrive.thugs.red
|07   Modem sounds .......: synthesised live in your browser, no samples
|07
|07   Greets to everyone who ever waited for a 200KB ANSI to redraw at 2400 baud.
|07
|08============================================================================|07'),

('help.main','Help','template','pipe',
'|08== |14HELP|08 ======================================================================|07

|07   {{site_name}} is driven entirely from the keyboard.
|07
|07   |15Navigation|07
|07     - Type the |14hotkey|07 letter shown in brackets to pick a menu item.
|07     - |14ARROW KEYS|07 move the highlight, |14ENTER|07 selects it.
|07     - |14ESC|07 or |14Q|07 backs out to the previous menu.
|07     - |14/|07 from most screens jumps to global search (Find).
|07
|07   |15Reading|07
|07     - |14SPACE|07 / |14ENTER|07 pages forward, |14B|07 pages back, |14Q|07 stops.
|07
|07   |15Toggles|07
|07     - |14CTRL-S|07 sound on/off      - |14CTRL-L|07 redraw screen
|07
|07   Stuck? Open a |14SysOp Ticket|07 from the main menu.
|07
|08============================================================================|07'),

('newuser.form','New User Application','template','pipe',
'|08== |14NEW USER APPLICATION|08 ======================================================|07

|07   Welcome, new caller from |12{{phone}}|07.
|07   Pick a handle and a password. Passwords can be short here - this is a BBS,
|07   not a bank - but the SysOp still hashes them.
|07
|07   Your e-mail (optional) is stored encrypted and only the SysOp can read it.
'),

('board.header','Message Boards','template','pipe',
'|08== |14MESSAGE BASE|08  -  conference: |11{{conference_name}}|08 ==============================|07'),

('bulletin.rules','Board Rules','template','pipe',
'|08== |14BOARD RULES|08 =====================================================

|07  1. Be excellent to each other. The SysOp reads everything.
|07  2. No spam, no warez dumps, no illegal content.
|07  3. ANSI art in messages is encouraged. Keep it under 80 wide.
|07  4. One account per person. Share nicely on the nodes.
|07  5. Have fun. This is a hobby board.

|08  Breaking these gets you a friendly warning, then a not-so-friendly ban.'),

('bulletin.nodes','Node Information','template','pipe',
'|08== |14NODE INFO|08 ========================================================

|07  {{nodes_total}} nodes, {{baud}} baud emulated.
|07  Your session times out after 60 minutes idle.
|07  Chat is on node channel #main - press C from Communication.

|07  Calling from: |12{{phone}}|07  on node |14{{node}}|07.'),

('bulletin.scene','The Scene','template','pipe',
'|08== |14THE SCENE|08 ========================================================

|07  This board keeps the flame lit for the dial-up era.
|07  See the Links Directory -> Retro / BBS Scene for pack archives,
|07  textfile collections, the demoscene and other still-running boards.

|15  ANSI is a lifestyle, not a file format.'),

('art.banner','THUGS(red) - fire banner','template','pipe',
'|03       .    ''      ,     ^    .     ''      :    ,     .    ''      ^
|09     ,    ''     .    ^    ,     ''     .     ^    ,    ''    .     ^
|01              ███████  ██   ██  ██   ██   ██████   ██████
|09                ███    ██   ██  ██   ██  ██       ██
|09                ███    ███████  ██   ██  ██  ███   █████
|03                ███    ██   ██  ██   ██  ██   ██        ██
|11                ███    ██   ██  ██   ██  ██   ██        ██
|15                ███    ██   ██   █████    ██████  ██████
|11          ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄
|03          ▓▓▒█████████████████████████████████████▒▓▓
|09          ▒▓▓▓▒▒░░▒▒▓▓▒▒░░  ░░  ▒▒  ░░ ░ ░ ▒▒▓▓▒
|01           ░▒▓▓▒░   ░ ▒ ░  ░   ▒  ░ ░   ░  ▒ ░   ░ ▒▓▒░
|08          >> |09( r e d )|08   B U L L E T I N   B O A R D   S Y S T E M  <<'),

-- Fallback logo. `contrib/install-logo.php` overwrites this with the real
-- graffiti ANSI from assets/THUGSred.ans (content_type -> ansi).
('art.logo','THUGS(red) - logo','template','pipe',
'|09     ,    ''     .    ^    ,     ''     .     ^    ,    ''    .     ^
|01              ███████  ██   ██  ██   ██   ██████   ██████
|09                ███    ██   ██  ██   ██  ██       ██
|09                ███    ███████  ██   ██  ██  ███   █████
|03                ███    ██   ██  ██   ██  ██   ██        ██
|15                ███    ██   ██   █████    ██████  ██████
|03          ▓▓▒█████████████████████████████████████▒▓▓
|08          >> |09( r e d )|08   B U L L E T I N   B O A R D   S Y S T E M  <<'),

('nonode','No Free Nodes','template','pipe',
'|11   ALL LINES BUSY|07

|07   Every one of {{nodes_total}} nodes on {{site_name}} is in use right now.
|07   This is the authentic experience. Try again in a minute.
')
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), content_type=VALUES(content_type);

-- ---------------------------------------------------------------------
--  menus
-- ---------------------------------------------------------------------
INSERT INTO menus (slug,title,header_screen,prompt,columns) VALUES
 ('main','Main Menu','art.logo','Main',2),
 ('messages','Message Menu',NULL,'Messages',2),
 ('files','File Menu',NULL,'Files',2),
 ('news','News Menu',NULL,'News',1),
 ('comms','Communication',NULL,'Comms',2),
 ('games','Game Room',NULL,'Games',2),
 ('sysop','SysOp Area',NULL,'SysOp',2)
ON DUPLICATE KEY UPDATE title=VALUES(title), prompt=VALUES(prompt), columns=VALUES(columns);

-- Wipe & rebuild menu_items for a clean, ordered tree on every seed run.
DELETE mi FROM menu_items mi JOIN menus m ON m.id = mi.menu_id
 WHERE m.slug IN ('main','messages','files','news','comms','games','sysop');

INSERT INTO menu_items (menu_id,sort,hotkey,label,description,action,target,min_permission,min_role_rank)
SELECT m.id, x.sort, x.hotkey, x.label, x.descr, x.action, x.target, x.perm, x.rank FROM menus m JOIN (
  SELECT 'comms' mslug,10 sort,'C' hotkey,'Node Chat' label,'Talk to other callers live' descr,'module' action,'chat' target,'chat.use' perm,10 rank UNION ALL
  SELECT 'comms' mslug,20 sort,'O' hotkey,'One-liners' label,'Read / sign the wall' descr,'module' action,'oneliners' target,NULL perm,0 rank UNION ALL
  SELECT 'comms' mslug,30 sort,'U' hotkey,'User List' label,'Everyone who has an account' descr,'module' action,'users.list' target,NULL perm,0 rank UNION ALL
  SELECT 'comms' mslug,40 sort,'W' hotkey,'Whos Online' label,'Callers connected right now' descr,'module' action,'users.online' target,NULL perm,0 rank UNION ALL
  SELECT 'comms' mslug,50 sort,'L' hotkey,'Last Callers' label,'Recent dial-ins' descr,'module' action,'lastcallers' target,NULL perm,0 rank UNION ALL
  SELECT 'comms' mslug,60 sort,'S' hotkey,'Send a Comment' label,'Private note to the SysOp' descr,'module' action,'ticket' target,NULL perm,0 rank UNION ALL
  SELECT 'comms' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank UNION ALL
  SELECT 'files' mslug,10 sort,'L' hotkey,'List Areas' label,'Pick a file area' descr,'module' action,'file.areas' target,NULL perm,0 rank UNION ALL
  SELECT 'files' mslug,20 sort,'B' hotkey,'Browse Files' label,'List files in current area' descr,'module' action,'file.list' target,NULL perm,0 rank UNION ALL
  SELECT 'files' mslug,30 sort,'F' hotkey,'Find File' label,'Search the file catalogue' descr,'module' action,'file.find' target,NULL perm,0 rank UNION ALL
  SELECT 'files' mslug,40 sort,'U' hotkey,'Upload' label,'Contribute a file' descr,'module' action,'file.upload' target,'file.upload' perm,30 rank UNION ALL
  SELECT 'files' mslug,50 sort,'Y' hotkey,'Library' label,'Text-files & reference library' descr,'module' action,'file.library' target,NULL perm,0 rank UNION ALL
  SELECT 'files' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank UNION ALL
  SELECT 'games' mslug,5 sort,'M' hotkey,'Hackers-MUD' label,'Jack into Night City - a live multiplayer MUD' descr,'module' action,'mud.play' target,NULL perm,0 rank UNION ALL
  SELECT 'games' mslug,10 sort,'D' hotkey,'Door Games' label,'Pick a game to play' descr,'module' action,'game.list' target,NULL perm,0 rank UNION ALL
  SELECT 'games' mslug,20 sort,'H' hotkey,'High Scores' label,'Hall of fame' descr,'module' action,'game.scores' target,NULL perm,0 rank UNION ALL
  SELECT 'games' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,10 sort,'M' hotkey,'Message Base' label,'Read and post messages' descr,'menu' action,'messages' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,20 sort,'F' hotkey,'File Libraries' label,'Browse and download files' descr,'menu' action,'files' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,30 sort,'N' hotkey,'News Wire' label,'IT / Hacking / Tech / Entertainment' descr,'menu' action,'news' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,40 sort,'K' hotkey,'Links Directory' label,'Curated links: AI, OSINT, red/blue team, ...' descr,'module' action,'links' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,45 sort,'P' hotkey,'THUGS(red) Projects' label,'Everything the crew builds and runs' descr,'module' action,'links.thugs' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,50 sort,'C' hotkey,'Communication' label,'Chat, one-liners, user list' descr,'menu' action,'comms' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,60 sort,'G' hotkey,'Game Room' label,'16 door games and high scores' descr,'menu' action,'games' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,70 sort,'V' hotkey,'Voting Booth' label,'Cast your vote' descr,'module' action,'poll' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,80 sort,'B' hotkey,'Bulletins' label,'Notices from the SysOp' descr,'module' action,'bulletins' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,89 sort,'' hotkey,'-' label,'' descr,'divider' action,'' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,100 sort,'I' hotkey,'System Information' label,'About this board' descr,'screen' action,'sys.info' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,110 sort,'S' hotkey,'Statistics' label,'Board and caller stats' descr,'module' action,'stats' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,120 sort,'U' hotkey,'What\'s New' label,'New messages, files and headlines' descr,'module' action,'whatsnew' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,130 sort,'E' hotkey,'Fortune Cookie' label,'A little wisdom from the wire' descr,'module' action,'fortune' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,140 sort,'T' hotkey,'SysOp Ticket' label,'Page the SysOp / support' descr,'module' action,'ticket' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,150 sort,'W' hotkey,'Who / SysOps' label,'Staff roster and who is online' descr,'module' action,'sysops' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,160 sort,'A' hotkey,'Account' label,'Your profile and settings' descr,'module' action,'account' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,170 sort,'#' hotkey,'SysOp Area' label,'Board administration' descr,'menu' action,'sysop' target,'admin.access' perm,80 rank UNION ALL
  SELECT 'main' mslug,179 sort,'' hotkey,'-' label,'' descr,'divider' action,'' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,190 sort,'?' hotkey,'Help' label,'How to drive this thing' descr,'screen' action,'help.main' target,NULL perm,0 rank UNION ALL
  SELECT 'main' mslug,200 sort,'O' hotkey,'Goodbye / Log off' label,'Hang up the modem' descr,'logoff' action,'' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,10 sort,'L' hotkey,'List Boards' label,'Pick a message board' descr,'module' action,'msg.boards' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,20 sort,'R' hotkey,'Read Messages' label,'Read the current board' descr,'module' action,'msg.read' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,30 sort,'P' hotkey,'Post Message' label,'Start a new thread' descr,'module' action,'msg.post' target,'message.post' perm,10 rank UNION ALL
  SELECT 'messages' mslug,40 sort,'S' hotkey,'Scan New' label,'New messages since last call' descr,'module' action,'msg.scan' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,50 sort,'F' hotkey,'Find' label,'Full-text search the message base' descr,'module' action,'msg.find' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,60 sort,'N' hotkey,'Change Conference' label,'Join another conference' descr,'module' action,'msg.conf' target,NULL perm,0 rank UNION ALL
  SELECT 'messages' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank UNION ALL
  SELECT 'news' mslug,10 sort,'I' hotkey,'IT News' label,'The Register & friends' descr,'module' action,'news.it' target,NULL perm,0 rank UNION ALL
  SELECT 'news' mslug,20 sort,'H' hotkey,'Hacking News' label,'BleepingComputer, Krebs' descr,'module' action,'news.hacking' target,NULL perm,0 rank UNION ALL
  SELECT 'news' mslug,30 sort,'T' hotkey,'Tech News' label,'Ars Technica, Hacker News' descr,'module' action,'news.tech' target,NULL perm,0 rank UNION ALL
  SELECT 'news' mslug,40 sort,'E' hotkey,'Entertainment' label,'Variety, Polygon' descr,'module' action,'news.entertainment' target,NULL perm,0 rank UNION ALL
  SELECT 'news' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank UNION ALL
  SELECT 'sysop' mslug,10 sort,'U' hotkey,'Users & Roles' label,'Accounts, RBAC, bans' descr,'module' action,'admin.users' target,'admin.users' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,20 sort,'M' hotkey,'Message Admin' label,'Boards, conferences, prune' descr,'module' action,'admin.messages' target,'admin.content' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,30 sort,'F' hotkey,'File Admin' label,'Areas & upload approvals' descr,'module' action,'admin.files' target,'admin.content' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,40 sort,'N' hotkey,'News & Feeds' label,'RSS sources, refresh now' descr,'module' action,'admin.news' target,'admin.content' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,45 sort,'P' hotkey,'Polls' label,'Create / close voting booths' descr,'module' action,'admin.polls' target,'admin.content' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,50 sort,'S' hotkey,'Screens & Menus' label,'Edit ANSI screens and menu tree' descr,'module' action,'admin.screens' target,'admin.screens' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,60 sort,'G' hotkey,'Global Config' label,'Every setting, live' descr,'module' action,'admin.config' target,'admin.config' perm,100 rank UNION ALL
  SELECT 'sysop' mslug,65 sort,'D' hotkey,'Discord Hooks' label,'Webhooks & events' descr,'module' action,'admin.discord' target,'admin.integrations' perm,100 rank UNION ALL
  SELECT 'sysop' mslug,68 sort,'!' hotkey,'Maintenance Mode' label,'Toggle the busy-tone lockout' descr,'module' action,'admin.maint' target,'admin.config' perm,100 rank UNION ALL
  SELECT 'sysop' mslug,70 sort,'T' hotkey,'Tickets' label,'Answer support tickets' descr,'module' action,'admin.tickets' target,'ticket.manage' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,80 sort,'A' hotkey,'Audit Log' label,'Everything that happened' descr,'module' action,'admin.audit' target,'admin.audit' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,85 sort,'C' hotkey,'Call Log' label,'Who dialled in' descr,'module' action,'admin.calls' target,'admin.calls' perm,80 rank UNION ALL
  SELECT 'sysop' mslug,90 sort,'X' hotkey,'Back' label,'Return to main menu' descr,'menu' action,'main' target,NULL perm,0 rank
) x ON x.mslug = m.slug;

-- ---------------------------------------------------------------------
--  conferences & boards
-- ---------------------------------------------------------------------
INSERT INTO conferences (slug,name,description,min_role_rank,sort,is_default) VALUES
 ('main','Main Hall','General chatter and board business',0,10,1),
 ('scene','The Scene','ANSI, demos, retro computing, phreak history',0,20,0),
 ('sec','Security','Defensive & offensive security discussion',10,30,0)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort=VALUES(sort);

INSERT INTO boards (conference_id,slug,name,description,kind,sort)
SELECT c.id, b.slug, b.name, b.descr, b.kind, b.sort FROM conferences c JOIN (
  SELECT 'main' cslug,'announce' slug,'Announcements' name,'Read-only news from the SysOp' descr,'announce' kind,10 sort UNION ALL
  SELECT 'main','general','General','Anything goes, keep it civil','local',20 UNION ALL
  SELECT 'main','forsale','Swap Meet','Buy / sell / trade old hardware','local',30 UNION ALL
  SELECT 'main','feedback','Feedback','Suggestions for the board','local',40 UNION ALL
  SELECT 'scene','ansi','ANSI & Art','Share your work, request colly','local',10 UNION ALL
  SELECT 'scene','retro','Retro Iron','8-bit, 16-bit, and the machines we miss','local',20 UNION ALL
  SELECT 'scene','phreak','History','Phone phreaking & BBS folklore','local',30 UNION ALL
  SELECT 'sec','blueteam','Blue Team','Detection, hardening, IR','local',10 UNION ALL
  SELECT 'sec','redteam','Red Team','Tradecraft, tooling, CTF','local',20
) b ON b.cslug = c.slug
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort=VALUES(sort);

-- ---------------------------------------------------------------------
--  file areas
-- ---------------------------------------------------------------------
INSERT INTO file_areas (slug,name,description,kind,min_read_rank,min_upload_rank,sort) VALUES
 ('ansi','ANSI Art Packs','.ANS / .ZIP collections',        'files',0,30,10),
 ('textfiles','Text Files','e-zines, docs, phrack-style',    'files',0,10,20),
 ('tools','Utilities','Small tools and source',             'files',0,30,30),
 ('doorgames','Door Games','Game data and mods',            'files',0,80,40),
 ('library','Reference Library','Curated links & docs',      'library',0,80,50)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), sort=VALUES(sort);

-- ---------------------------------------------------------------------
--  games
-- ---------------------------------------------------------------------
INSERT INTO games (slug,name,description,module,score_label,score_order,sort) VALUES
 ('guess','Guess The Number','1-1000, how few tries can you do it in?','guess','Tries','asc',10),
 ('hangman','Hangman','Save the little ASCII man','hangman','Wins','desc',20),
 ('dice','Ten Thousand','Push-your-luck dice, first to 10,000','dice','High Roll','desc',30),
 ('blackjack','One-Armed Bandit','Blackjack vs the house','blackjack','Chips','desc',40),
 ('wumpus','Hunt The Wumpus','Classic cave crawl','wumpus','Rooms','asc',50),
 ('lorc','Legend of the Red Console','A tiny LORD-style adventure','lorc','Level','desc',60),
 ('rps','Rock Paper Scissors','Best of nine against the machine','rps','Margin','desc',70),
 ('ttt','Tic-Tac-Toe','X vs a CPU that blocks and wins','ttt','Wins','desc',80),
 ('nim','21 Matchsticks','Take 1-3, do not take the last','nim','Wins','desc',90),
 ('mastermind','Mastermind','Crack the 4-digit code in 10 tries','mastermind','Tries','asc',100),
 ('anagram','Anagram','Unscramble eight BBS words','anagram','Score','desc',110),
 ('hilo','Hi-Lo','Higher or lower? Build a streak, cash out','hilo','Streak','desc',120),
 ('craps','Craps','A bankroll and a pass-line bet','craps','Bankroll','desc',130),
 ('mines','Minesweeper','8x8, ten mines, steady hands','mines','Squares','desc',140),
 ('g2048','2048','Slide and merge tiles to 2048','g2048','Score','desc',150),
 ('lightsout','Lights Out','Turn every light off on a 5x5 grid','lightsout','Moves','asc',160)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), module=VALUES(module),
 score_label=VALUES(score_label), score_order=VALUES(score_order), sort=VALUES(sort);

-- ---------------------------------------------------------------------
--  a starter poll
-- ---------------------------------------------------------------------
INSERT INTO polls (id,question,is_open,allow_multi) VALUES
 (1,'Best baud rate to have grown up on?',1,0)
ON DUPLICATE KEY UPDATE question=VALUES(question);
INSERT INTO poll_options (poll_id,label,sort) VALUES
 (1,'300 baud - I read faster than it printed',10),
 (1,'2400 baud - the sweet spot',20),
 (1,'14.4k - luxury',30),
 (1,'33.6k / 56k - basically broadband',40),
 (1,'I am too young, I had DSL',50);

-- ---------------------------------------------------------------------
--  one-liners
-- ---------------------------------------------------------------------
INSERT INTO oneliners (handle,body,ip_phone) VALUES
 ('sysop','First! Welcome to the wall. Keep it under 160 chars.','(000) 000-0000'),
 ('sysop','ANSI is a lifestyle, not a file format.','(000) 000-0000'),
 ('phoneb0y','Remember: the S in SysOp stands for ''reads your mail''.','(212) 555-0110');

-- ---------------------------------------------------------------------
--  welcome messages
-- ---------------------------------------------------------------------
INSERT INTO messages (board_id,thread_id,from_handle,to_handle,subject,body,created_at)
SELECT b.id, 0, 'sysop', 'All', 'Welcome to THUGS(red) BBS',
 CONCAT('This board is a love letter to the dial-up era.\n\n',
        'Everything here is keyboard driven. Hit the letter in [brackets] to move around,\n',
        'arrow keys + ENTER also work, ESC backs out.\n\n',
        'Post a message, sign the wall, play a door game, read the news wire.\n',
        'Your IP shows up as a phone number - that is on purpose.\n\n',
        '- the SysOp'),
 NOW()
FROM boards b JOIN conferences c ON c.id=b.conference_id
WHERE c.slug='main' AND b.slug='announce'
LIMIT 1;

UPDATE messages SET thread_id = id WHERE thread_id = 0;
UPDATE boards b SET post_count = (SELECT COUNT(*) FROM messages m WHERE m.board_id=b.id AND m.deleted_at IS NULL),
                    last_post_at = (SELECT MAX(created_at) FROM messages m WHERE m.board_id=b.id);

-- ---------------------------------------------------------------------
--  links directory
-- ---------------------------------------------------------------------
INSERT INTO link_categories (slug,name,description,icon,sort) VALUES
 ('search','Search Engines','Ways to find things without the ad-tech','?',10),
 ('ai','AI Sites','Assistants, model hubs and playgrounds','*',20),
 ('news','News & Zines','Tech, security and scene reading','!',30),
 ('offensive','Hacking / Offensive','Exploitation, web, tradecraft, wordlists','#',40),
 ('osint','OSINT','Recon, attribution and exposure checks','@',50),
 ('blue','Blue Team / Defense','Detection, IR, hardening, threat intel','+',60),
 ('research','Security Research','Vuln research, advisories and disclosure','$',70),
 ('net','Networking','BGP, DNS, peering and the plumbing','~',80),
 ('prog','Programming','Docs, references and cheat sheets','>',90),
 ('hw','Gadgets & Hardware','Makers, boards, repair and parts','=',100),
 ('scene','Retro / BBS Scene','ANSI, textfiles, BBS lists and the demoscene','&',110)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), icon=VALUES(icon), sort=VALUES(sort);

INSERT INTO links (category_id,title,url,description,added_handle,sort)
SELECT c.id, x.title, x.url, x.descr, 'sysop', x.sort FROM link_categories c JOIN (
  SELECT 'search' cslug,'DuckDuckGo' title,'https://duckduckgo.com/' url,'Privacy-first web search' descr,10 sort UNION ALL
  SELECT 'search' cslug,'Startpage' title,'https://www.startpage.com/' url,'Google results, no tracking' descr,20 sort UNION ALL
  SELECT 'search' cslug,'Brave Search' title,'https://search.brave.com/' url,'Independent index' descr,30 sort UNION ALL
  SELECT 'search' cslug,'Marginalia Search' title,'https://search.marginalia.nu/' url,'Old-web, text-first discovery' descr,40 sort UNION ALL
  SELECT 'search' cslug,'Mojeek' title,'https://www.mojeek.com/' url,'Independent crawler & index' descr,50 sort UNION ALL
  SELECT 'search' cslug,'searx.be' title,'https://searx.be/' url,'Public SearXNG metasearch' descr,60 sort UNION ALL
  SELECT 'search' cslug,'Kagi' title,'https://kagi.com/' url,'Paid, ad-free premium search' descr,70 sort UNION ALL
  SELECT 'ai' cslug,'Claude' title,'https://claude.ai/' url,'Anthropic assistant' descr,10 sort UNION ALL
  SELECT 'ai' cslug,'ChatGPT' title,'https://chat.openai.com/' url,'OpenAI assistant' descr,20 sort UNION ALL
  SELECT 'ai' cslug,'Hugging Face' title,'https://huggingface.co/' url,'Models, datasets, spaces' descr,30 sort UNION ALL
  SELECT 'ai' cslug,'Perplexity' title,'https://www.perplexity.ai/' url,'Answer engine with citations' descr,40 sort UNION ALL
  SELECT 'ai' cslug,'Google AI Studio' title,'https://aistudio.google.com/' url,'Gemini prompt playground' descr,50 sort UNION ALL
  SELECT 'ai' cslug,'LMArena' title,'https://lmarena.ai/' url,'Blind model comparison / leaderboard' descr,60 sort UNION ALL
  SELECT 'ai' cslug,'Ollama' title,'https://ollama.com/' url,'Run local LLMs' descr,70 sort UNION ALL
  SELECT 'news' cslug,'The Register' title,'https://www.theregister.com/' url,'IT news with an attitude' descr,10 sort UNION ALL
  SELECT 'news' cslug,'Ars Technica' title,'https://arstechnica.com/' url,'Tech, science, policy' descr,20 sort UNION ALL
  SELECT 'news' cslug,'Hacker News' title,'https://news.ycombinator.com/' url,'The orange site' descr,30 sort UNION ALL
  SELECT 'news' cslug,'Lobsters' title,'https://lobste.rs/' url,'Computing-focused link aggregator' descr,40 sort UNION ALL
  SELECT 'news' cslug,'Phrack' title,'http://www.phrack.org/' url,'The underground e-zine' descr,50 sort UNION ALL
  SELECT 'news' cslug,'2600' title,'https://www.2600.com/' url,'The Hacker Quarterly' descr,60 sort UNION ALL
  SELECT 'news' cslug,'tl;dr sec' title,'https://tldrsec.com/' url,'Weekly security newsletter' descr,70 sort UNION ALL
  SELECT 'offensive' cslug,'Exploit-DB' title,'https://www.exploit-db.com/' url,'Public exploit archive' descr,10 sort UNION ALL
  SELECT 'offensive' cslug,'GTFOBins' title,'https://gtfobins.github.io/' url,'Unix binaries for privesc/bypass' descr,20 sort UNION ALL
  SELECT 'offensive' cslug,'LOLBAS' title,'https://lolbas-project.github.io/' url,'Living-off-the-land Windows binaries' descr,30 sort UNION ALL
  SELECT 'offensive' cslug,'HackTricks' title,'https://book.hacktricks.xyz/' url,'Pentest methodology wiki' descr,40 sort UNION ALL
  SELECT 'offensive' cslug,'PayloadsAllTheThings' title,'https://github.com/swisskyrepo/PayloadsAllTheThings' url,'Payloads & bypass cheatsheets' descr,50 sort UNION ALL
  SELECT 'offensive' cslug,'PortSwigger Academy' title,'https://portswigger.net/web-security' url,'Free web security labs' descr,60 sort UNION ALL
  SELECT 'offensive' cslug,'MITRE ATT&CK' title,'https://attack.mitre.org/' url,'Adversary TTP knowledge base' descr,70 sort UNION ALL
  SELECT 'offensive' cslug,'HackerOne Hacktivity' title,'https://hackerone.com/hacktivity' url,'Disclosed bug bounty reports' descr,80 sort UNION ALL
  SELECT 'osint' cslug,'OSINT Framework' title,'https://osintframework.com/' url,'Categorised tool directory' descr,10 sort UNION ALL
  SELECT 'osint' cslug,'Shodan' title,'https://www.shodan.io/' url,'Search engine for exposed devices' descr,20 sort UNION ALL
  SELECT 'osint' cslug,'Censys Search' title,'https://search.censys.io/' url,'Internet-wide host & cert scans' descr,30 sort UNION ALL
  SELECT 'osint' cslug,'crt.sh' title,'https://crt.sh/' url,'Certificate transparency log search' descr,40 sort UNION ALL
  SELECT 'osint' cslug,'Have I Been Pwned' title,'https://haveibeenpwned.com/' url,'Breach exposure check' descr,50 sort UNION ALL
  SELECT 'osint' cslug,'urlscan.io' title,'https://urlscan.io/' url,'Scan & inspect suspicious URLs' descr,60 sort UNION ALL
  SELECT 'osint' cslug,'WiGLE' title,'https://wigle.net/' url,'Wireless network geolocation' descr,70 sort UNION ALL
  SELECT 'osint' cslug,'Wayback Machine' title,'https://web.archive.org/' url,'Historical page snapshots' descr,80 sort UNION ALL
  SELECT 'blue' cslug,'MITRE D3FEND' title,'https://d3fend.mitre.org/' url,'Defensive countermeasure ontology' descr,10 sort UNION ALL
  SELECT 'blue' cslug,'Sigma HQ' title,'https://github.com/SigmaHQ/sigma' url,'Generic detection rule format' descr,20 sort UNION ALL
  SELECT 'blue' cslug,'Atomic Red Team' title,'https://atomicredteam.io/' url,'Small, portable detection tests' descr,30 sort UNION ALL
  SELECT 'blue' cslug,'CISA KEV Catalog' title,'https://www.cisa.gov/known-exploited-vulnerabilities-catalog' url,'Known exploited vulns' descr,40 sort UNION ALL
  SELECT 'blue' cslug,'MalwareBazaar' title,'https://bazaar.abuse.ch/' url,'Malware sample sharing' descr,50 sort UNION ALL
  SELECT 'blue' cslug,'Wazuh' title,'https://wazuh.com/' url,'Open-source XDR/SIEM' descr,60 sort UNION ALL
  SELECT 'blue' cslug,'TheHive Project' title,'https://thehive-project.org/' url,'Incident response platform' descr,70 sort UNION ALL
  SELECT 'research' cslug,'Project Zero' title,'https://googleprojectzero.blogspot.com/' url,'Google\'s 0-day research blog' descr,10 sort UNION ALL
  SELECT 'research' cslug,'PortSwigger Research' title,'https://portswigger.net/research' url,'Web security research' descr,20 sort UNION ALL
  SELECT 'research' cslug,'GitHub Security Lab' title,'https://securitylab.github.com/' url,'Vuln research & advisories' descr,30 sort UNION ALL
  SELECT 'research' cslug,'NVD' title,'https://nvd.nist.gov/' url,'US National Vulnerability Database' descr,40 sort UNION ALL
  SELECT 'research' cslug,'CVE.org' title,'https://www.cve.org/' url,'CVE program & records' descr,50 sort UNION ALL
  SELECT 'research' cslug,'oss-security' title,'https://www.openwall.com/lists/oss-security/' url,'Open-source security list' descr,60 sort UNION ALL
  SELECT 'net' cslug,'Cloudflare Radar' title,'https://radar.cloudflare.com/' url,'Internet traffic & trends' descr,10 sort UNION ALL
  SELECT 'net' cslug,'bgp.tools' title,'https://bgp.tools/' url,'BGP / ASN / prefix explorer' descr,20 sort UNION ALL
  SELECT 'net' cslug,'RIPEstat' title,'https://stat.ripe.net/' url,'IP, ASN and routing data' descr,30 sort UNION ALL
  SELECT 'net' cslug,'PeeringDB' title,'https://www.peeringdb.com/' url,'Interconnection database' descr,40 sort UNION ALL
  SELECT 'net' cslug,'DNSViz' title,'https://dnsviz.net/' url,'Visualise & debug DNSSEC' descr,50 sort UNION ALL
  SELECT 'net' cslug,'MXToolbox' title,'https://mxtoolbox.com/' url,'DNS / mail / blacklist checks' descr,60 sort UNION ALL
  SELECT 'net' cslug,'Wireshark' title,'https://www.wireshark.org/' url,'The packet analyser' descr,70 sort UNION ALL
  SELECT 'prog' cslug,'MDN Web Docs' title,'https://developer.mozilla.org/' url,'Web platform reference' descr,10 sort UNION ALL
  SELECT 'prog' cslug,'DevDocs' title,'https://devdocs.io/' url,'Fast offline-able API docs' descr,20 sort UNION ALL
  SELECT 'prog' cslug,'cppreference' title,'https://en.cppreference.com/' url,'C and C++ reference' descr,30 sort UNION ALL
  SELECT 'prog' cslug,'The Rust Book' title,'https://doc.rust-lang.org/book/' url,'Learn Rust' descr,40 sort UNION ALL
  SELECT 'prog' cslug,'Go by Example' title,'https://gobyexample.com/' url,'Annotated Go snippets' descr,50 sort UNION ALL
  SELECT 'prog' cslug,'regex101' title,'https://regex101.com/' url,'Build & debug regex' descr,60 sort UNION ALL
  SELECT 'prog' cslug,'crontab.guru' title,'https://crontab.guru/' url,'Cron expression editor' descr,70 sort UNION ALL
  SELECT 'prog' cslug,'explainshell' title,'https://explainshell.com/' url,'Break down shell commands' descr,80 sort UNION ALL
  SELECT 'hw' cslug,'Hackaday' title,'https://hackaday.com/' url,'Hardware hacks daily' descr,10 sort UNION ALL
  SELECT 'hw' cslug,'Adafruit' title,'https://www.adafruit.com/' url,'Boards, parts, tutorials' descr,20 sort UNION ALL
  SELECT 'hw' cslug,'SparkFun' title,'https://www.sparkfun.com/' url,'Electronics & breakouts' descr,30 sort UNION ALL
  SELECT 'hw' cslug,'Pimoroni' title,'https://shop.pimoroni.com/' url,'Maker boards & add-ons' descr,40 sort UNION ALL
  SELECT 'hw' cslug,'iFixit' title,'https://www.ifixit.com/' url,'Repair guides & teardowns' descr,50 sort UNION ALL
  SELECT 'hw' cslug,'Raspberry Pi' title,'https://www.raspberrypi.com/' url,'The little board' descr,60 sort UNION ALL
  SELECT 'hw' cslug,'Arduino' title,'https://www.arduino.cc/' url,'Open-source electronics' descr,70 sort UNION ALL
  SELECT 'scene' cslug,'Telnet BBS Guide' title,'https://www.telnetbbsguide.com/' url,'Dial in to boards still running' descr,10 sort UNION ALL
  SELECT 'scene' cslug,'16colo.rs' title,'https://16colo.rs/' url,'ANSI/ASCII art pack archive' descr,20 sort UNION ALL
  SELECT 'scene' cslug,'textfiles.com' title,'http://textfiles.com/' url,'Jason Scott\'s BBS-era text archive' descr,30 sort UNION ALL
  SELECT 'scene' cslug,'int10h Oldschool Fonts' title,'https://int10h.org/oldschool-pc-fonts/' url,'The VGA text-mode font pack' descr,40 sort UNION ALL
  SELECT 'scene' cslug,'Demozoo' title,'https://demozoo.org/' url,'Demoscene productions database' descr,50 sort UNION ALL
  SELECT 'scene' cslug,'pouet.net' title,'https://www.pouet.net/' url,'Demoscene community & prods' descr,60 sort UNION ALL
  SELECT 'scene' cslug,'The BBS Documentary' title,'http://www.bbsdocumentary.com/' url,'Jason Scott, 2005' descr,70 sort
) x ON x.cslug = c.slug
ON DUPLICATE KEY UPDATE title=VALUES(title);

-- THUGS(red) Projects (thugs.red/projects)
INSERT INTO link_categories (slug,name,description,icon,sort) VALUES
 ('thugs','THUGS(red) Projects','Everything the crew builds and runs','!',5)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), icon=VALUES(icon), sort=VALUES(sort);

INSERT INTO links (category_id,title,url,description,added_handle,sort)
SELECT c.id, x.title, x.url, x.descr, 'sysop', x.sort FROM link_categories c JOIN (
  SELECT 'thugs' cslug,'DarkWeb Monitor' title,'https://darkweb.thugs.red' url,'Onion services, leak sites & criminal marketplaces' descr,10 sort UNION ALL
  SELECT 'thugs' cslug,'THUGS(red) Blacklist' title,'https://blacklist.thugs.red' url,'Curated blocklists of hostile infrastructure' descr,20 sort UNION ALL
  SELECT 'thugs' cslug,'Live Activity Map' title,'https://activitymap.thugs.red' url,'Live view of sensor & honeypot activity' descr,30 sort UNION ALL
  SELECT 'thugs' cslug,'SMTP Latrine' title,'https://smtplatrine.thugs.red' url,'SMTP honeypot for open-relay traffic' descr,40 sort UNION ALL
  SELECT 'thugs' cslug,'Telegram Grabber' title,'https://telegram-grabber.thugs.red' url,'Collection from channels where leaks surface' descr,50 sort UNION ALL
  SELECT 'thugs' cslug,'githubxhunter' title,'https://github.com/kawaiipantsu/githubxhunter' url,'Hunt malicious code on GitHub' descr,60 sort UNION ALL
  SELECT 'thugs' cslug,'RansomWatch' title,'https://github.com/kawaiipantsu/ransomwatch' url,'Ransomware leak-site monitoring, Nordics' descr,70 sort UNION ALL
  SELECT 'thugs' cslug,'THUGS(red) Wardrive' title,'https://wardrive.thugs.red' url,'Wardriving data collection & mapping' descr,80 sort UNION ALL
  SELECT 'thugs' cslug,'Dangling DNS' title,'https://dangling.thugs.red' url,'Dangling DNS records & subdomain takeovers' descr,90 sort UNION ALL
  SELECT 'thugs' cslug,'Gates of Valhalla' title,'https://valhalla.thugs.red' url,'OSINT platform: emails, usernames, IPs, domains' descr,100 sort UNION ALL
  SELECT 'thugs' cslug,'NetworkSambaScanner' title,'https://github.com/kawaiipantsu/NetworkSambaScanner' url,'SMB/Samba exposure scanning + severity' descr,110 sort UNION ALL
  SELECT 'thugs' cslug,'Wardrive (Android)' title,'https://github.com/kawaiipantsu/thugsred-wardrive-apk' url,'WiFi & Bluetooth wardriving app' descr,120 sort UNION ALL
  SELECT 'thugs' cslug,'RedJoust' title,'https://github.com/kawaiipantsu/redjoust' url,'Desktop recon workbench for OSINT / red team' descr,130 sort UNION ALL
  SELECT 'thugs' cslug,'WiFi Jumper' title,'https://github.com/kawaiipantsu/wifijumper' url,'ESP8266 hopping between open WiFi' descr,140 sort UNION ALL
  SELECT 'thugs' cslug,'Evil Project' title,'https://evil.thugs.red' url,'Offensive research & red team tradecraft' descr,150 sort UNION ALL
  SELECT 'thugs' cslug,'MailChumHum' title,'https://mailchumhum.com' url,'Credential phishing simulation infrastructure' descr,160 sort UNION ALL
  SELECT 'thugs' cslug,'CupATM' title,'https://cupatm.com' url,'Bitcoin / extortion-themed phishing simulation' descr,170 sort UNION ALL
  SELECT 'thugs' cslug,'Security Check' title,'https://securitycheck.thugs.red' url,'EDR / AV / SOC reaction benchmark testing' descr,180 sort UNION ALL
  SELECT 'thugs' cslug,'URL Bully' title,'https://github.com/kawaiipantsu/urlbully' url,'Templated web requests & timing analysis' descr,190 sort UNION ALL
  SELECT 'thugs' cslug,'DuckyScript Payloads' title,'https://github.com/kawaiipantsu/duckyscript-payloads' url,'BadUSB scripts for Flipper Zero' descr,200 sort UNION ALL
  SELECT 'thugs' cslug,'THUGS(red) APT' title,'https://apt.thugs.red' url,'Debian APT repo with custom binaries & tools' descr,210 sort UNION ALL
  SELECT 'thugs' cslug,'Tools Overview' title,'https://tools.thugs.red' url,'The team\'s collected tooling in one place' descr,220 sort UNION ALL
  SELECT 'thugs' cslug,'Dump File' title,'https://dump.thugs.red' url,'File analysis service for suspicious files' descr,230 sort UNION ALL
  SELECT 'thugs' cslug,'Suricata Rules' title,'https://suricata-rules.thugs.red' url,'Detection rules from real-world observations' descr,240 sort UNION ALL
  SELECT 'thugs' cslug,'Canary Tokens' title,'https://canary.dk' url,'Plant a tripwire anywhere' descr,250 sort UNION ALL
  SELECT 'thugs' cslug,'Filio File Sharing' title,'https://filio.dk' url,'Files up to 2 GB, no account, no trackers' descr,260 sort UNION ALL
  SELECT 'thugs' cslug,'ipdigger' title,'https://github.com/kawaiipantsu/ipdigger' url,'IP extraction & enrichment tool' descr,270 sort UNION ALL
  SELECT 'thugs' cslug,'telegramdigger' title,'https://github.com/kawaiipantsu/telegramdigger' url,'Telegram Bot API from your terminal' descr,280 sort UNION ALL
  SELECT 'thugs' cslug,'subdigger' title,'https://github.com/kawaiipantsu/subdigger' url,'Fast multi-threaded subdomain discovery' descr,290 sort UNION ALL
  SELECT 'thugs' cslug,'SynapseIDS' title,'https://github.com/kawaiipantsu/synapseids' url,'Network IDS using neural nets for classification' descr,300 sort UNION ALL
  SELECT 'thugs' cslug,'susfile' title,'https://github.com/kawaiipantsu/susfile' url,'CLI file-forensics visualiser (entropy/structure)' descr,310 sort UNION ALL
  SELECT 'thugs' cslug,'boop' title,'https://github.com/kawaiipantsu/boop' url,'Local-first AI client & agent runtime' descr,320 sort UNION ALL
  SELECT 'thugs' cslug,'LogIO Devlog' title,'https://github.com/kawaiipantsu/logio-devlog' url,'PHP integration for real-time log viewing' descr,330 sort UNION ALL
  SELECT 'thugs' cslug,'theZoo WebUI' title,'https://github.com/kawaiipantsu/theZoo-WebUI' url,'Browser interface for a live malware repo' descr,340 sort UNION ALL
  SELECT 'thugs' cslug,'The Lab' title,'https://lab.thugs.red' url,'Practice range infrastructure for testing' descr,350 sort UNION ALL
  SELECT 'thugs' cslug,'Takedown Authority' title,'https://takedown.thugs.red' url,'Seizure-notice domain for verified abuse actions' descr,360 sort UNION ALL
  SELECT 'thugs' cslug,'Bitbasher' title,'https://bitbasher.dk' url,'Browser Eurorack rendered in ANSI' descr,370 sort UNION ALL
  SELECT 'thugs' cslug,'CRIT ZONE' title,'https://critzone.dk' url,'Neon vector arena shooter' descr,380 sort UNION ALL
  SELECT 'thugs' cslug,'Tank Wars' title,'https://tankwars.dk' url,'Massively multiplayer top-down tank combat' descr,390 sort UNION ALL
  SELECT 'thugs' cslug,'Egg Heist' title,'https://eggheist.dk' url,'Browser party game: steal a golden egg' descr,400 sort UNION ALL
  SELECT 'thugs' cslug,'Stick Fight Arena' title,'https://fight.xxc.dk/' url,'Neural-network-evolved stick figure combat' descr,410 sort
) x ON x.cslug = c.slug
ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description);
