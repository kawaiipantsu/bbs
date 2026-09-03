-- Achievements: a catalogue of badges + a per-user earned ledger.
CREATE TABLE IF NOT EXISTS achievements (
  code        VARCHAR(40)  NOT NULL PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  description VARCHAR(200) NOT NULL,
  category    VARCHAR(32)  NOT NULL DEFAULT 'general',
  points      SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  icon        VARCHAR(8)   NOT NULL DEFAULT '*',
  is_secret   TINYINT(1)   NOT NULL DEFAULT 0,
  rule        VARCHAR(255) NOT NULL DEFAULT '',     -- JSON: {"t":"stat","k":"posts","n":10} etc.
  sort        SMALLINT     NOT NULL DEFAULT 100,
  enabled     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_achievements (
  user_id     INT UNSIGNED NOT NULL,
  code        VARCHAR(40)  NOT NULL,
  earned_at   DATETIME     NOT NULL,
  PRIMARY KEY (user_id, code),
  KEY code (code),
  KEY user_earned (user_id, earned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO achievements (code,name,description,category,points,icon,is_secret,rule,sort) VALUES
 ('carrier',        'Carrier Detect',      'Dial in and reach the board for the first time.',        'getting-started', 5,  '>', 0, '{"t":"stat","k":"calls","n":1}',      10),
 ('regular',        'Regular Caller',      'Log 10 calls to the board.',                             'getting-started', 15, '>', 0, '{"t":"stat","k":"calls","n":10}',     20),
 ('local',          'Local Fixture',      'Log 50 calls. The SysOp knows your ring.',               'getting-started', 30, '>', 0, '{"t":"stat","k":"calls","n":50}',     30),
 ('week-old',       'One Week Online',     'Your account is 7 days old.',                            'getting-started', 10, '#', 0, '{"t":"age","n":7}',                  40),
 ('veteran',        'Old Timer',           'Your account is a year old.',                            'dedication',     50, '#', 0, '{"t":"age","n":365}',                45),

 ('first-post',     'First Words',         'Post your first message.',                               'community',      10, '@', 0, '{"t":"stat","k":"posts","n":1}',      50),
 ('scribe',         'Scribe',              'Post 25 messages.',                                      'community',      20, '@', 0, '{"t":"stat","k":"posts","n":25}',     55),
 ('loudmouth',      'Loudmouth',           'Post 100 messages.',                                     'community',      40, '@', 0, '{"t":"stat","k":"posts","n":100}',    60),
 ('one-liner',      'Wall Writer',         'Leave a one-liner on the wall.',                         'community',      10, '~', 0, '{"t":"stat","k":"oneliners","n":1}',  65),
 ('graffiti',       'Graffiti Artist',     'Leave 10 one-liners.',                                   'community',      20, '~', 0, '{"t":"stat","k":"oneliners","n":10}', 66),
 ('voter',          'Voting Booth',        'Cast a vote in the Voting Booth.',                       'community',      10, '+', 0, '{"t":"stat","k":"poll_votes","n":1}', 70),
 ('democracy',      'Civic Duty',          'Vote 10 times.',                                         'community',      20, '+', 0, '{"t":"stat","k":"poll_votes","n":10}',71),
 ('ticket',         'Squeaky Wheel',       'File a SysOp ticket.',                                   'community',      10, '!', 0, '{"t":"stat","k":"tickets","n":1}',    75),
 ('uploader',       'Contributor',         'Upload a file to the libraries.',                        'community',      15, '^', 0, '{"t":"stat","k":"uploads","n":1}',    80),
 ('leecher',        'Leecher',             'Download 25 files. No judgement.',                       'community',      15, 'v', 0, '{"t":"stat","k":"downloads","n":25}', 85),

 ('gamer',          'Door Kicker',         'Play a door game.',                                      'games',         10, '&', 0, '{"t":"stat","k":"games_plays","n":1}',   100),
 ('arcade',         'Arcade Rat',          'Play 5 different door games.',                           'games',         25, '&', 0, '{"t":"stat","k":"games_variety","n":5}', 105),
 ('completionist',  'Completionist',       'Play 10 different door games.',                          'games',         50, '&', 0, '{"t":"stat","k":"games_variety","n":10}',110),
 ('trivia-ace',     'Know-It-All',         'Score a perfect 10 in Trivia Bot.',                      'games',         40, '?', 0, '{"t":"stat","k":"trivia_best","n":10}',  115),

 ('jack-in',        'Jacked In',           'Roll a runner in Hackers-MUD.',                          'night-city',    10, '%', 0, '{"t":"stat","k":"mud_level","n":1}',     130),
 ('street-lvl5',    'Making a Name',       'Reach level 5 in Hackers-MUD.',                          'night-city',    25, '%', 0, '{"t":"stat","k":"mud_level","n":5}',     135),
 ('street-lvl10',   'Night City Legend',   'Reach level 10 in Hackers-MUD.',                         'night-city',    60, '%', 0, '{"t":"stat","k":"mud_level","n":10}',    140),
 ('body-count',     'Problem Solver',      'Rack up 25 kills in Hackers-MUD.',                       'night-city',    30, 'x', 0, '{"t":"stat","k":"mud_kills","n":25}',    145),

 ('night-owl',      'Night Owl',           'Dial in between midnight and 5am.',                      'secret',        15, 'o', 1, '{"t":"hour","from":0,"to":4}',           200),
 ('early-bird',     'Early Bird',          'Dial in between 5am and 8am.',                           'secret',        15, 'o', 1, '{"t":"hour","from":5,"to":7}',           205)
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), category=VALUES(category),
  points=VALUES(points), icon=VALUES(icon), is_secret=VALUES(is_secret), rule=VALUES(rule), sort=VALUES(sort);
