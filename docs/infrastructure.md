# Infrastructure

## Live deployment (thugs.red)

```
Cloudflare  ──►  upstream WAF proxy (TLS terminates here)  ──►  this host :80
                                                                   │
                                                        Apache 2.4 vhost
                                                        DocumentRoot = html/
```

- PHP only ever sees **HTTP**. Never emit an HTTPS redirect from `.htaccess`
  (it would loop behind the proxy). Scheme is detected from `X-Forwarded-Proto`
  for canonical/OG URLs and the `Secure` cookie flag. HSTS is owned upstream.
- The vhost sets `RemoteIPHeader CF-Connecting-IP`, so `$_SERVER['REMOTE_ADDR']`
  is already the real visitor IP — used directly for the phone-number render.
- Files are forced `www-data:www-data` (the tree is also a Samba share).

### Hosts & ports

Set the real addresses in `app/config.php` (or `BBS_*` env vars). On the live
deployment these are private-network services on the LAN.

| service    | address (config)        | notes |
|------------|-------------------------|-------|
| MariaDB    | `db.host:3306`          | db `projects_bbs`, user `bbs`, utf8mb4 |
| Redis      | `127.0.0.1:6379`        | session cache + chat pub/sub (optional) |
| beanstalkd | `beanstalk.host:11300`  | may be **shared** — every tube is prefixed `bbs/` |

### Apache

`/var/www/vhosts-external/` already has `AllowOverride All` + `Require all
granted`, and `mod_rewrite`, `mod_headers`, `mod_deflate`, `mod_remoteip` are
enabled, so `html/.htaccess` is authoritative. No vhost change is required to
deploy the BBS.

## Processes

| what | how |
|---|---|
| Web | mod_php / php-fpm under Apache, docroot `html/` |
| Worker | `contrib/bbs-worker.service` (systemd) **or** `contrib/crontab` |
| Maintenance | `contrib/maintenance.php` nightly via cron |

The worker handles Discord webhooks, the news wire (hourly + on demand) and the
mail tube. Without it the board still runs; queued jobs just wait in `jobs_log`.

## Backups

Everything that matters is in MariaDB. `mysqldump projects_bbs` + the
`./storage/files` tree is a complete backup. `assets/` and the repo are
reproducible.

## Local / third-party (Docker)

`docker/docker-compose.yml` brings up web + worker + MariaDB + Redis +
beanstalkd with one command. Not used in production. See [install.md](install.md).
