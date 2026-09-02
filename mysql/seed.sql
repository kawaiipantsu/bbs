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
 ('term_cols','112','int','Terminal columns','system'),
 ('term_rows','40','int','Terminal rows','system'),
 ('new_user_role','user','string','Role auto-granted on registration','system'),
 ('registration_open','1','bool','Allow new user registration','system'),
 ('guest_browsing','1','bool','Let guests read public areas','system'),
 ('crt_intensity','0.85','string','CRT effect intensity 0..1','appearance'),
 ('crt_scanlines','1','bool','Scanline overlay','appearance'),
 ('crt_flicker','1','bool','Phosphor flicker','appearance'),
 ('crt_curvature','1','bool','Barrel distortion','appearance'),
 ('sound_default','0','bool','Sound on by default','appearance'),
 ('motd_screen','boot.motd','string','Screen shown at connect','appearance'),
 ('discord_enabled','0','bool','Enable Discord webhooks','integrations'),
 ('discord_events','user.register,ticket.new,ticket.reply,message.new,sysop.page','string','Events that fire webhooks','integrations'),
 ('news_feeds_it','https://www.theregister.com/headlines.atom','text','IT news RSS feeds (one per line)','news'),
 ('news_feeds_hacking',"https://www.bleepingcomputer.com/feed/\nhttps://krebsonsecurity.com/feed/",'text','Hacking news RSS feeds','news'),
 ('news_feeds_tech',"https://feeds.arstechnica.com/arstechnica/index\nhttps://hnrss.org/frontpage",'text','Tech news RSS feeds','news'),
 ('news_feeds_entertainment',"https://variety.com/feed/\nhttps://www.polygon.com/rss/index.xml",'text','Entertainment news RSS feeds','news'),
 ('news_max_per_cat','80','int','Max cached items per category','news'),
 ('chat_idle_secs','90','int','Seconds before a chatter is considered gone','chat'),
 ('ticker_sources','custom,news,oneliners','string','Bottom crawl: which feeds, in order (custom,news,oneliners)','ticker'),
 ('ticker_custom',"Welcome to THUGS(red) BBS - leave a message, sign the wall, play a door game.\nType the letter in [brackets] to move around. ESC backs out.\nYour IP shows up as a phone number. That is on purpose.",'text','Bottom crawl: custom messages, one per line','ticker'),
 ('ticker_news_count','4','int','Bottom crawl: number of latest news headlines (0 = none)','ticker'),
 ('ticker_oneliner_count','10','int','Bottom crawl: number of latest one-liners (0 = none)','ticker'),
 ('ticker_speed_secs','55','int','Bottom crawl: seconds for one full loop (lower = faster)','ticker'),
 ('seo_description','THUGS(red) BBS - a fully keyboard-driven ANSI/ASCII bulletin board system rendered inside a CRT terminal. Dial in, leave a message, browse the files, play a door game.','text','Meta description','seo')
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
('boot.motd','Message of the Day','template','pipe',
'|08+============================================================================+
|09             █████ █   █ █   █  ████  ████     |12████  █████ ████
|09               █   █   █ █   █ █     █        |12█   █ █     █   █
|09               █   █████ █   █ █  ██  ███     |12████  ████  █   █
|09               █   █   █ █   █ █   █     █    |12█  █  █     █   █
|09               █   █   █ █████  ████ ████     |12█   █ █████ ████
|08 .--------------------------------------------------------------------------.
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
 ('main','Main Menu',NULL,'Main',2),
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
  -- ============ MAIN ============
  SELECT 'main' mslug, 10 sort,'M' hotkey,'Message Base' label,'Read and post messages' descr,'menu' action,'messages' target, NULL perm, 0 rank UNION ALL
  SELECT 'main', 20,'F','File Libraries','Browse and download files','menu','files',NULL,0 UNION ALL
  SELECT 'main', 30,'N','News Wire','IT / Hacking / Tech / Entertainment','menu','news',NULL,0 UNION ALL
  SELECT 'main', 40,'C','Communication','Chat, one-liners, user list','menu','comms',NULL,0 UNION ALL
  SELECT 'main', 50,'G','Game Room','Door games and high scores','menu','games',NULL,0 UNION ALL
  SELECT 'main', 60,'V','Voting Booth','Cast your vote','module','poll',NULL,0 UNION ALL
  SELECT 'main', 70,'I','System Information','About this board','screen','sys.info',NULL,0 UNION ALL
  SELECT 'main', 75,'S','Statistics','Board and caller stats','module','stats',NULL,0 UNION ALL
  SELECT 'main', 80,'T','SysOp Ticket','Page the SysOp / support','module','ticket',NULL,0 UNION ALL
  SELECT 'main', 85,'W','Who / SysOps','Staff roster','module','sysops',NULL,0 UNION ALL
  SELECT 'main', 90,'A','Account','Your profile and settings','module','account',NULL,0 UNION ALL
  SELECT 'main', 95,'#','SysOp Area','Board administration','menu','sysop','admin.access',80 UNION ALL
  SELECT 'main', 99,'','-','','divider','',NULL,0 UNION ALL
  SELECT 'main',100,'?','Help','How to drive this thing','screen','help.main',NULL,0 UNION ALL
  SELECT 'main',110,'O','Goodbye / Log off','Hang up the modem','logoff','',NULL,0 UNION ALL
  -- ============ MESSAGES ============
  SELECT 'messages',10,'L','List Boards','Pick a message board','module','msg.boards',NULL,0 UNION ALL
  SELECT 'messages',20,'R','Read Messages','Read the current board','module','msg.read',NULL,0 UNION ALL
  SELECT 'messages',30,'P','Post Message','Start a new thread','module','msg.post','message.post',10 UNION ALL
  SELECT 'messages',40,'S','Scan New','New messages since last call','module','msg.scan',NULL,0 UNION ALL
  SELECT 'messages',50,'F','Find','Full-text search the message base','module','msg.find',NULL,0 UNION ALL
  SELECT 'messages',60,'N','Change Conference','Join another conference','module','msg.conf',NULL,0 UNION ALL
  SELECT 'messages',90,'X','Back','Return to main menu','menu','main',NULL,0 UNION ALL
  -- ============ FILES ============
  SELECT 'files',10,'L','List Areas','Pick a file area','module','file.areas',NULL,0 UNION ALL
  SELECT 'files',20,'B','Browse Files','List files in current area','module','file.list',NULL,0 UNION ALL
  SELECT 'files',30,'F','Find File','Search the file catalogue','module','file.find',NULL,0 UNION ALL
  SELECT 'files',40,'U','Upload','Contribute a file','module','file.upload','file.upload',30 UNION ALL
  SELECT 'files',50,'Y','Library','Text-files & reference library','module','file.library',NULL,0 UNION ALL
  SELECT 'files',90,'X','Back','Return to main menu','menu','main',NULL,0 UNION ALL
  -- ============ NEWS ============
  SELECT 'news',10,'I','IT News','The Register & friends','module','news.it',NULL,0 UNION ALL
  SELECT 'news',20,'H','Hacking News','BleepingComputer, Krebs','module','news.hacking',NULL,0 UNION ALL
  SELECT 'news',30,'T','Tech News','Ars Technica, Hacker News','module','news.tech',NULL,0 UNION ALL
  SELECT 'news',40,'E','Entertainment','Variety, Polygon','module','news.entertainment',NULL,0 UNION ALL
  SELECT 'news',90,'X','Back','Return to main menu','menu','main',NULL,0 UNION ALL
  -- ============ COMMS ============
  SELECT 'comms',10,'C','Node Chat','Talk to other callers live','module','chat','chat.use',10 UNION ALL
  SELECT 'comms',20,'O','One-liners','Read / sign the wall','module','oneliners',NULL,0 UNION ALL
  SELECT 'comms',30,'U','User List','Everyone who has an account','module','users.list',NULL,0 UNION ALL
  SELECT 'comms',40,'W','Whos Online','Callers connected right now','module','users.online',NULL,0 UNION ALL
  SELECT 'comms',50,'S','Send a Comment','Private note to the SysOp','module','ticket',NULL,0 UNION ALL
  SELECT 'comms',90,'X','Back','Return to main menu','menu','main',NULL,0 UNION ALL
  -- ============ GAMES ============
  SELECT 'games',10,'D','Door Games','Pick a game to play','module','game.list',NULL,0 UNION ALL
  SELECT 'games',20,'H','High Scores','Hall of fame','module','game.scores',NULL,0 UNION ALL
  SELECT 'games',90,'X','Back','Return to main menu','menu','main',NULL,0 UNION ALL
  -- ============ SYSOP ============
  SELECT 'sysop',10,'U','Users & Roles','Accounts, RBAC, bans','module','admin.users','admin.users',80 UNION ALL
  SELECT 'sysop',20,'M','Message Admin','Boards, conferences, prune','module','admin.messages','admin.content',80 UNION ALL
  SELECT 'sysop',30,'F','File Admin','Areas & upload approvals','module','admin.files','admin.content',80 UNION ALL
  SELECT 'sysop',40,'N','News & Feeds','RSS sources, refresh now','module','admin.news','admin.content',80 UNION ALL
  SELECT 'sysop',45,'P','Polls','Create / close voting booths','module','admin.polls','admin.content',80 UNION ALL
  SELECT 'sysop',50,'S','Screens & Menus','Edit ANSI screens and menu tree','module','admin.screens','admin.screens',80 UNION ALL
  SELECT 'sysop',60,'G','Global Config','Every setting, live','module','admin.config','admin.config',100 UNION ALL
  SELECT 'sysop',65,'D','Discord Hooks','Webhooks & events','module','admin.discord','admin.integrations',100 UNION ALL
  SELECT 'sysop',70,'T','Tickets','Answer support tickets','module','admin.tickets','ticket.manage',80 UNION ALL
  SELECT 'sysop',80,'A','Audit Log','Everything that happened','module','admin.audit','admin.audit',80 UNION ALL
  SELECT 'sysop',85,'C','Call Log','Who dialled in','module','admin.calls','admin.calls',80 UNION ALL
  SELECT 'sysop',90,'X','Back','Return to main menu','menu','main',NULL,0
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
