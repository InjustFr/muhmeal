# muhmeal — running on a server

This folder is all a server needs: `compose.yaml` runs the app image (FrankenPHP, assets built in) and a PostgreSQL 18 database. No source checkout, no PHP or Node on the host.

The repository is public, so the app host name is never committed. It only lives in the server `.env` (`DEFAULT_URI`), in the reverse proxy configuration and in the accounts server. Wherever this page says `<app host>`, use your own.

## Prerequisites

- Docker Engine with the Compose plugin (`docker compose version`).
- A [mossyleaf accounts](../../accounts/deploy/README.md) server (Authentik) with the `muhmeal` OIDC application: people sign in there.
- A reverse proxy (Caddy, nginx, Traefik…) that terminates HTTPS and forwards to the port you choose below.

## First install

```bash
mkdir -p ~/muhmeal && cd ~/muhmeal
# copy compose.yaml, .env.dist and README.md here (`make deploy-files DEPLOY_HOST=user@server` from a dev machine does it)
cp .env.dist .env
chmod 600 .env
```

Fill `.env`:

| Variable | Value |
|---|---|
| `IMAGE` / `TAG` | Image to run, e.g. `docker.io/injust/muhmeal` / `latest` or a commit tag |
| `APP_PORT` | Host port the app listens on (the proxy targets it) |
| `APP_BIND` | `0.0.0.0` when the proxy is on another machine, `127.0.0.1` when it runs on this server |
| `TRUSTED_PROXIES` | Who may set `X-Forwarded-*`: the proxy IP/CIDR (e.g. `203.0.113.10`), `private_ranges`, or `REMOTE_ADDR` (trust whoever connects — only when a firewall lets nothing but the proxy reach `APP_PORT`) |
| `DEFAULT_URI` | Public URL, `https://<app host>` |
| `APP_SECRET` | `openssl rand -hex 32` |
| `POSTGRES_PASSWORD` | `openssl rand -hex 24` (`POSTGRES_DB` / `POSTGRES_USER` can stay `app`) |
| `OIDC_CLIENT_SECRET` | Client secret of the `muhmeal` application in mossyleaf accounts (`MUHMEAL_CLIENT_SECRET` in its `.env`) |
| `ACCOUNTS_URL` | Optional, defaults to `https://accounts.mossyleaf.studio`; the `OIDC_*_URL` endpoints derive from it (override `OIDC_TOKEN_URL`/`OIDC_USERINFO_URL` to reach Authentik through an internal URL) |
| `LOGIN_RATE_LIMIT` | Optional, sign-in requests allowed per address every 15 minutes, 30 by default |

Never change `APP_SECRET` or `POSTGRES_PASSWORD` after the first start: sessions, remember-me cookies and the database depend on them. Keep a copy of `.env` with your backups.

## Start

```bash
docker compose pull
docker compose up -d
docker compose logs -f app
```

On every start the app waits for the database, runs pending migrations, warms the cache, then serves HTTP on `APP_PORT`.

## Accounts

There is no sign-up, password or invitation in muhmeal: people sign in with their mossyleaf account. To let someone in, invite them in mossyleaf accounts with the `muhmeal` group (`make invite EMAIL=… NAME=… GROUPS=muhmeal` in `accounts`). Their first sign-in adds them to the kitchen: everyone in the group sees and edits the same pantry, recipes, groceries and history.

## Reverse proxy

The app speaks plain HTTP. The proxy must forward `Host` and the `X-Forwarded-For`, `X-Forwarded-Proto` and `X-Forwarded-Host` headers, and its IP must match `TRUSTED_PROXIES`; otherwise the app thinks it is served over HTTP, rejects form and API submissions from the HTTPS origin and rate limits every visitor as one address.

The app sets its own security headers (content security policy, HSTS over HTTPS, `nosniff`, referrer and permissions policies, `X-Robots-Tag`): the proxy must not remove or replace them. `/robots.txt` is a static file that asks crawlers to index nothing; it must stay reachable without signing in.

Caddy:

```caddyfile
<app host> {
    reverse_proxy app-server:8080
}
```

nginx:

```nginx
server {
    listen 443 ssl;
    server_name <app host>;

    location / {
        proxy_pass http://app-server:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
    }
}
```

A Let's Encrypt certificate lists its host names in the public Certificate Transparency logs. Authentication protects the data, not the host name.

## Update / rollback

Every push to `main` runs the full test suite (`make ci`) and, only if it passes, publishes `docker.io/injust/muhmeal:<short sha>` and `:latest` (GitHub Actions, [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)). `make deploy` refuses a commit whose image is not published, so only tested commits reach the server. The image is public: the server pulls it without `docker login`.

From a dev machine, once the Actions run for the commit is green:

```bash
make deploy      # DEPLOY_HOST, DEPLOY_DIR and REMOTE_DOCKER default to the values at the top of the Makefile; override them for another server
```

It writes `IMAGE` and `TAG` (the current commit, override with `TAG=<sha>`) into the server `.env`, pulls the image and restarts the app. By hand on the server:

```bash
TAG=<tag> docker compose pull app && TAG=<tag> docker compose up -d
```

`latest` is the most recent push; rolling back means starting a previous tag. Migrations only move forward: restore a backup before rolling back across a schema change.

## Operations

```bash
docker compose ps
docker compose logs -f app
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
gunzip -c backup.sql.gz | docker compose exec -T database psql -U app app
docker compose down            # stop, data kept
docker compose down -v         # stop and DELETE the database
```

## Behind a shared nginx on the same Docker host

When the proxy is an nginx container of another Compose project on the same host, the app does not publish a port: a `compose.override.yaml` that exists on the server only joins the proxy network with an alias, and the nginx server block for `<app host>` proxies to `http://muhmeal`.

```yaml
services:
  app:
    ports: !reset []
    networks:
      default:
      proxy:
        aliases:
          - muhmeal
networks:
  proxy:
    name: <proxy network>
    external: true
```
