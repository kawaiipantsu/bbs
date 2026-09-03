-- =====================================================================
--  WEATHER TERMINAL (BBS module slug `weather`)
--
--  Per-location cache of the raw wttr.in response. The module serves a
--  cached reading for up to 30 minutes; on a fetch failure it falls back
--  to whatever stale row it has here. `loc` is a normalised lowercase key
--  (collapsed whitespace, trimmed, <=120 chars); `label` is the display
--  name the caller typed / picked.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS weather_cache (
    loc        VARCHAR(120) NOT NULL PRIMARY KEY,
    label      VARCHAR(120) NOT NULL DEFAULT '',
    body       MEDIUMTEXT   NOT NULL,
    fetched_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_weather_cache_fetched (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
