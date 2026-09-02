# Install

## Requirements

- PHP **8.2+** with: `pdo_mysql`, `mbstring`, `json`, `openssl`, `sodium`,
  `curl`, `gd`. `redis` is optional.
- MariaDB 10.6+ / MySQL 8 (utf8mb4).
- Apache with `mod_rewrite` + `mod_headers` (or nginx — translate `html/.htaccess`).
- Optional: Redis, beanstalkd.

No Composer packages are required. `composer install` only regenerates an
autoloader that `app/bootstrap.php` already ships.

## Bare-metal / shared host

```bash
git clone git@github.com:kawaiipantsu/bbs.git
cd bbs

cp app/config.sample.php app/config.php
$EDITOR app/config.php            # DB creds + generate the 3 crypto keys:
                                  #   php -r 'echo base64_encode(random_bytes(32)),"\n";'
chmod 640 app/config.php

mkdir -p storage/{files,cache,tmp,logs}
chown -R www-data:www-data .      # web user must own the tree

php mysql/migrate.php --seed      # schema + demo content + the first SysOp
php mysql/mud_world.php           # build the Hackers-MUD world (see docs/mud.md)
```

Point the vhost `DocumentRoot` at `html/`. That's it — open the site, watch it
dial in, log in as `sysop` with the password from `app/config.php` (you're
forced to change it on first login).

### Background worker (recommended)

```bash
cp contrib/bbs-worker.service /etc/systemd/system/
systemctl enable --now bbs-worker
```

or, cron-only — drop the fragment into `/etc/cron.d` (worker fallback, news
wire, the Hackers-MUD world tick, nightly maintenance):

```bash
install -o root -g root -m 644 contrib/bbs.cron /etc/cron.d/bbs
```

(`crontab -u www-data contrib/crontab` installs the same jobs as a user
crontab instead, if you prefer that.)

### News wire

Feed URLs live in **SysOp → Global Config** (`news_feeds_it`,
`news_feeds_hacking`, `news_feeds_tech`, `news_feeds_entertainment`). The worker
pulls them hourly; force one with `php contrib/worker.php --news` or the
**R** key on the SysOp → News screen.

## Docker (try it / hack on it)

```bash
docker compose -f docker/docker-compose.yml up --build
# http://localhost:8080   (sysop / letmein  → forced change)
```

`config.sample.php` reads `BBS_*` environment variables, so the container needs
no hand-editing. The compose file wires MariaDB, Redis and beanstalkd; drop the
`beanstalkd` service and the app falls back to the DB queue automatically.

## Updating

```bash
git pull
php mysql/migrate.php            # applies mysql/migrations/*.sql
```

`mysql/migrate.php --fresh` drops **every** table first — only for a clean
re-install.

## Verify

```bash
curl -sI https://your-host/ | grep -i content-security-policy   # CSP present
curl -s  https://your-host/sitemap.xml | head                   # SEO
curl -s  https://your-host/app/config.php                       # -> 404
```
