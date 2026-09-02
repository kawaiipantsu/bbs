#!/usr/bin/env bash
# THUGS(red) BBS container entrypoint.
set -euo pipefail

cd /app

# make sure a config exists (env-driven by default via config.sample.php)
if [ ! -f app/config.php ]; then
  cp app/config.sample.php app/config.php
fi

mkdir -p storage/files storage/cache storage/tmp storage/logs
chown -R www-data:www-data storage app/config.php || true

# wait for the database
DB_HOST="${BBS_DB_HOST:-db}"
DB_PORT="${BBS_DB_PORT:-3306}"
echo "waiting for ${DB_HOST}:${DB_PORT} ..."
for i in $(seq 1 60); do
  if php -r '$c=@fsockopen(getenv("BBS_DB_HOST")?:"db",(int)(getenv("BBS_DB_PORT")?:3306),$e,$s,1); exit($c?0:1);'; then
    echo "database is up"; break
  fi
  sleep 2
done

# migrate (and seed on first run / when BBS_SEED=1)
if [ "${BBS_SEED:-0}" = "1" ]; then
  php mysql/migrate.php --seed || echo "migrate --seed failed (continuing)"
else
  php mysql/migrate.php || echo "migrate failed (continuing)"
fi

# apache wants its doc root
: "${APACHE_DOCUMENT_ROOT:=/app/html}"
sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf || true

exec "$@"
