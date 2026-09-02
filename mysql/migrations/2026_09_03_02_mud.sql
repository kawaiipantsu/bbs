-- =====================================================================
--  Hackers-MUD  -  a cyberpunk MUD MMO that runs inside the BBS terminal.
--  Every table is prefixed mud_ .  Players map 1:1 to BBS users.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  Config (k/v)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_config (
    `key`   VARCHAR(48) NOT NULL PRIMARY KEY,
    `value` TEXT        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  World: zones -> rooms -> exits
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_zones (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(40)  NOT NULL,
    name          VARCHAR(80)  NOT NULL,
    description   VARCHAR(255) NOT NULL DEFAULT '',
    level_min     INT NOT NULL DEFAULT 1,
    level_max     INT NOT NULL DEFAULT 60,
    respawn_secs  INT NOT NULL DEFAULT 180,
    UNIQUE KEY uq_mud_zone_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_rooms (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vnum         INT UNSIGNED NOT NULL,
    zone_id      INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    description  TEXT         NOT NULL,
    x            SMALLINT     NOT NULL DEFAULT 0,
    y            SMALLINT     NOT NULL DEFAULT 0,
    z            SMALLINT     NOT NULL DEFAULT 0,
    flags        VARCHAR(160) NOT NULL DEFAULT '',   -- safe,indoors,dark,shop,noloot,regen,net,street
    light        TINYINT      NOT NULL DEFAULT 1,
    UNIQUE KEY uq_mud_room_vnum (vnum),
    KEY idx_mud_room_zone (zone_id),
    KEY idx_mud_room_xyz (zone_id, z, x, y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_exits (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    from_room    INT UNSIGNED NOT NULL,
    dir          VARCHAR(4)   NOT NULL,               -- n s e w u d ne nw se sw in out
    to_room      INT UNSIGNED NOT NULL,
    keyword      VARCHAR(40)  NOT NULL DEFAULT '',    -- door / gate keyword
    locked       TINYINT      NOT NULL DEFAULT 0,
    key_vnum     INT UNSIGNED NULL,
    hidden       TINYINT      NOT NULL DEFAULT 0,
    hack_dc      TINYINT      NOT NULL DEFAULT 0,      -- >0 = electronic lock, hackable
    descr        VARCHAR(200) NOT NULL DEFAULT '',
    UNIQUE KEY uq_mud_exit (from_room, dir),
    KEY idx_mud_exit_from (from_room)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_item_templates (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vnum         INT UNSIGNED NOT NULL,
    name         VARCHAR(80)  NOT NULL,               -- "a rusty combat knife"
    keywords     VARCHAR(120) NOT NULL,               -- "knife combat rusty"
    room_desc    VARCHAR(160) NOT NULL DEFAULT '',    -- line when on the ground
    long_desc    TEXT         NOT NULL,
    type         VARCHAR(20)  NOT NULL,               -- weapon armor implant food drink drug gadget computer container key light trash currency
    slot         VARCHAR(20)  NOT NULL DEFAULT '',    -- wield held head eyes face neck torso arms hands back waist legs feet + implant_*
    weight       DECIMAL(6,2) NOT NULL DEFAULT 1.0,
    value        INT          NOT NULL DEFAULT 0,
    damage_dice  VARCHAR(16)  NOT NULL DEFAULT '',    -- "2d4+1"
    armor        SMALLINT     NOT NULL DEFAULT 0,
    stat_mods    JSON         NULL,                   -- {"body":1,"tech":2}
    effect       JSON         NULL,                   -- {"heal":20} / {"buff":{"name":"Wired","secs":180,"mods":{"reflex":3}}}
    charges      SMALLINT     NOT NULL DEFAULT 0,
    level_req    INT          NOT NULL DEFAULT 1,
    flags        VARCHAR(120) NOT NULL DEFAULT '',    -- notrade cursed twohanded glow cyber illegal quest
    UNIQUE KEY uq_mud_item_vnum (vnum),
    KEY idx_mud_item_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_item_instances (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id   INT UNSIGNED NOT NULL,
    loc_type      VARCHAR(12)  NOT NULL,              -- room mob player container shop void
    loc_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    container_id  BIGINT UNSIGNED NULL,
    `condition`   TINYINT      NOT NULL DEFAULT 100,
    charges_left  SMALLINT     NOT NULL DEFAULT -1,
    custom_name   VARCHAR(80)  NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mud_inst_loc (loc_type, loc_id),
    KEY idx_mud_inst_tpl (template_id),
    CONSTRAINT fk_mud_inst_tpl FOREIGN KEY (template_id) REFERENCES mud_item_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Mobs (NPCs, enemies, shopkeepers)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_mob_templates (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vnum         INT UNSIGNED NOT NULL,
    name         VARCHAR(80)  NOT NULL,               -- "a sewer rat"
    keywords     VARCHAR(120) NOT NULL,
    room_desc    VARCHAR(200) NOT NULL,               -- line in the room
    long_desc    TEXT         NOT NULL,
    level        INT          NOT NULL DEFAULT 1,
    max_hp       INT          NOT NULL DEFAULT 10,
    stats        JSON         NULL,                   -- {"body":..,"reflex":..,"intel":..,"cool":..,"tech":..}
    ac           SMALLINT     NOT NULL DEFAULT 0,
    damage_dice  VARCHAR(16)  NOT NULL DEFAULT '1d4',
    xp_reward    INT          NOT NULL DEFAULT 5,
    money_min    INT          NOT NULL DEFAULT 0,
    money_max    INT          NOT NULL DEFAULT 0,
    faction      VARCHAR(24)  NOT NULL DEFAULT 'civilian', -- police gang corp civilian wildlife hacker boss vendor
    behavior     VARCHAR(120) NOT NULL DEFAULT '',    -- aggressive wander scavenger sentinel coward thief shopkeeper questgiver
    dialogue     JSON         NULL,
    loot_table   JSON         NULL,                   -- [{"vnum":1234,"chance":25}]
    respawn_secs INT          NOT NULL DEFAULT 180,
    flags        VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uq_mud_mob_vnum (vnum),
    KEY idx_mud_mob_faction (faction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_mob_instances (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    template_id    INT UNSIGNED NOT NULL,
    room_id        INT UNSIGNED NOT NULL,
    spawn_room_id  INT UNSIGNED NOT NULL,
    hp             INT NOT NULL,
    state          VARCHAR(12) NOT NULL DEFAULT 'idle', -- idle wander fighting fleeing dead
    target_player  INT UNSIGNED NULL,
    last_act_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    died_at        DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mud_mi_room (room_id),
    KEY idx_mud_mi_state (state),
    KEY idx_mud_mi_tpl (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Shops
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_shops (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    room_id       INT UNSIGNED NOT NULL,
    keeper_vnum   INT UNSIGNED NULL,
    name          VARCHAR(80)  NOT NULL,
    buy_types     VARCHAR(160) NOT NULL DEFAULT '',   -- csv of item types the shop will buy
    sell_markup   DECIMAL(4,2) NOT NULL DEFAULT 1.30,
    buy_markdown  DECIMAL(4,2) NOT NULL DEFAULT 0.40,
    greeting      VARCHAR(240) NOT NULL DEFAULT '',
    UNIQUE KEY uq_mud_shop_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_shop_stock (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    shop_id        INT UNSIGNED NOT NULL,
    template_vnum  INT UNSIGNED NOT NULL,
    qty            INT NOT NULL DEFAULT -1,            -- -1 = unlimited
    price_override INT NULL,
    KEY idx_mud_stock_shop (shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Players (1:1 with BBS users)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_players (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(32)  NOT NULL,
    archetype       VARCHAR(16)  NOT NULL DEFAULT 'netrunner',
    title           VARCHAR(48)  NOT NULL DEFAULT '',
    level           INT NOT NULL DEFAULT 1,
    xp              BIGINT NOT NULL DEFAULT 0,
    hp              INT NOT NULL DEFAULT 30,
    max_hp          INT NOT NULL DEFAULT 30,
    energy          INT NOT NULL DEFAULT 20,           -- "heat" / netrun cycles
    max_energy      INT NOT NULL DEFAULT 20,
    stats           JSON NULL,                         -- {"body":,"reflex":,"intel":,"cool":,"tech":}
    unspent_points  INT NOT NULL DEFAULT 0,
    street_cred     INT NOT NULL DEFAULT 0,
    money           BIGINT NOT NULL DEFAULT 50,        -- eddies carried
    bank            BIGINT NOT NULL DEFAULT 0,
    room_id         INT UNSIGNED NOT NULL DEFAULT 1,
    respawn_room_id INT UNSIGNED NOT NULL DEFAULT 1,
    pos             VARCHAR(12) NOT NULL DEFAULT 'standing', -- standing sitting resting sleeping incapacitated
    state           VARCHAR(12) NOT NULL DEFAULT 'idle',
    target_mob      BIGINT UNSIGNED NULL,
    hunger          TINYINT NOT NULL DEFAULT 60,
    thirst          TINYINT NOT NULL DEFAULT 60,
    kills           INT NOT NULL DEFAULT 0,
    deaths          INT NOT NULL DEFAULT 0,
    playtime_secs   BIGINT NOT NULL DEFAULT 0,
    data            JSON NULL,                         -- visited rooms, story flags, quest bits
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_cmd_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mud_player_user (user_id),
    KEY idx_mud_player_room (room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_player_equipment (
    player_id  INT UNSIGNED NOT NULL,
    slot       VARCHAR(20)  NOT NULL,
    instance_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_player_effects (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id  INT UNSIGNED NOT NULL,
    name       VARCHAR(40) NOT NULL,
    source     VARCHAR(40) NOT NULL DEFAULT '',
    stat_mods  JSON NULL,
    dmg_bonus  SMALLINT NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mud_eff_player (player_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_player_skills (
    player_id INT UNSIGNED NOT NULL,
    skill     VARCHAR(24)  NOT NULL,                  -- hacking netrun stealth melee firearms athletics streetwise engineering
    level     INT NOT NULL DEFAULT 1,
    xp        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (player_id, skill)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Quests
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_quests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vnum         INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    giver_vnum   INT UNSIGNED NULL,
    summary      VARCHAR(255) NOT NULL DEFAULT '',
    description  TEXT NOT NULL,
    goal_type    VARCHAR(20) NOT NULL,                -- kill collect visit hack talk
    goal_target  VARCHAR(40) NOT NULL DEFAULT '',
    goal_count   INT NOT NULL DEFAULT 1,
    reward_xp    INT NOT NULL DEFAULT 0,
    reward_money INT NOT NULL DEFAULT 0,
    reward_vnum  INT UNSIGNED NULL,
    level_req    INT NOT NULL DEFAULT 1,
    next_vnum    INT UNSIGNED NULL,
    UNIQUE KEY uq_mud_quest_vnum (vnum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mud_player_quests (
    player_id INT UNSIGNED NOT NULL,
    quest_id  INT UNSIGNED NOT NULL,
    status    VARCHAR(12) NOT NULL DEFAULT 'active',  -- active done failed
    progress  INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, quest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Log (deaths, big events, world feed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mud_events (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id  INT UNSIGNED NULL,
    type       VARCHAR(24) NOT NULL,
    detail     VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mud_event_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
