# bugreports.gekophetweb.nl

One place for the security issues in the languages and servers you use: PHP, Python, JavaScript/Node.js, Go,
Rust, Java, .NET, Ruby, Elixir/Erlang, Dart, nginx and Apache HTTP Server. Each issue shows its severity
(Critical, High, Medium, Low, from the CVSS score) and links to its source.

- **Sources:** [OSV.dev](https://osv.dev), which includes GitHub Security Advisories (checked every 5 minutes),
  and the [NVD](https://nvd.nist.gov) for the runtimes and servers themselves (checked every 10 minutes).
- **Accounts:** sign in with Google (or GitHub) or with a code sent by email. The first user is the superadmin.
- **Emails:**
  - Subscribe per language: straight away, daily or weekly, from a minimum severity.
  - New Critical issues, and updates to recent Critical ones, are always sent straight away.
  - Watch single issues to get an email when they change.
- **Your view:** star issues to show them first. My issues shows only what you follow, and every list can be
  filtered by language, severity and date.
- **Feedback:** a feedback form whose status the sender can follow.

Design: [docs/superpowers/specs/2026-11-19-bugreports-design.md](docs/superpowers/specs/2026-11-19-bugreports-design.md).

## Install with Docker

The website (`app`, `queue`, `mail-queue`, `scheduler`) and the database (`db`, PostgreSQL 17) run in separate
containers, next to Redis. The database and Redis are only on an internal network and are never published.

### 1. Install Docker

- **Linux server:** install Docker Engine with the Compose plugin
  ([docs.docker.com/engine/install](https://docs.docker.com/engine/install/)), then check with
  `docker compose version`.
- **Windows or macOS:** install [Docker Desktop](https://www.docker.com/products/docker-desktop/) and start it.

### 2. Get the code

```sh
git clone https://github.com/gekophetweb/bugreports.gekophetweb.nl.git bugreports
cd bugreports
git checkout main            # main holds the releases
```

### 3. Configure `.env`

```sh
cp .env.example .env
```

Change at least these values in `.env`:

| Setting | Set it to |
|---|---|
| `DB_PASSWORD` | A long random password, e.g. from `openssl rand -hex 24` |
| `REDIS_PASSWORD` | Another long random password |
| `APP_URL` | The public URL, e.g. `https://bugreports.gekophetweb.nl` |
| `SESSION_SECURE_COOKIE` | `true` behind HTTPS (the tunnel). Use `false` only to try it on `http://127.0.0.1:8000`, or signing in will not work |
| `APP_PORT` | Only if port 8000 is already taken on the host (`port is already allocated`), e.g. `8080`. The tunnel still uses `app:8000` |

Leave `APP_KEY` empty: the first start generates it into `storage/app/.env.local` in the `app_storage` volume.
Mail and SSO are set up later in the website, not here.

### 4. Build and start

```sh
docker compose up -d --build
docker compose ps            # app should become "healthy" within a minute
```

The first start migrates the database and adds the languages. The image is built locally, so the first build
takes a few minutes.

### 5. Create the superadmin

```sh
docker compose logs app | grep -A1 "No superadmin"
```

Open the setup link from the log. It works once and creates the superadmin. Until then nobody can sign in.
The app listens on `127.0.0.1:8000` only (or the `APP_PORT` you set); to reach it from the internet, set up the
[Cloudflare Tunnel](#cloudflare-tunnel) below.

### Update to a new release

```sh
git pull
docker compose up -d --build   # migrations run on start
```

### Stop or remove

```sh
docker compose down            # stops the containers; data stays in the db_data and app_storage volumes
docker compose down -v         # also DELETES the database and APP_KEY: only to start over from scratch
```

## First steps as superadmin

Start at **Admin → Setup** (where the setup link takes you). It shows what is still missing
and has step-by-step guides for creating the Google and GitHub OAuth apps and requesting an NVD API key, with this
site's exact redirect URLs to copy. The NVD key can be saved and tested right on that page.

1. **Admin → Mail:** choose SMTP, Resend, Mailgun or Postmark, fill in the from address, and use
   **Send test email to me**. Until this is set up, emails (including sign-in codes) only go to the log.
2. **Admin → Sign-in:** for Google, create an OAuth client in the Google Cloud console with the redirect URI
   shown on the page, e.g. `https://bugreports.gekophetweb.nl/auth/google/callback`, and paste the client id
   and secret. GitHub works the same way and is off by default.
3. **Admin → Languages:** switch languages and servers on or off, or change their OSV ecosystems and NVD CPEs.
4. **Admin → Sync:** watch the sync runs, or start one now.

## Cloudflare Tunnel

1. In Cloudflare Zero Trust, go to Networks → Tunnels and create a tunnel (type *Cloudflared*). Copy its token.
2. Add a public hostname: `bugreports.gekophetweb.nl` → service `HTTP` → `app:8000`.
3. Put the token in `.env` as `CLOUDFLARE_TUNNEL_TOKEN=...` and make sure `APP_URL=https://bugreports.gekophetweb.nl`.
4. Run `docker compose --profile tunnel up -d`.

`TRUSTED_PROXIES` must stay the `frontend` subnet from `docker-compose.yml` (`172.29.0.0/24`), so the app
trusts `cloudflared`'s forwarded headers and nobody else's.

## Settings in `.env`

| Setting | Default | What it does |
|---|---|---|
| `NVD_API_KEY` | empty | Optional [NVD API key](https://nvd.nist.gov/developers/request-an-api-key): 50 instead of 5 requests per 30 s. A key saved in Admin → Setup wins over this one |
| `SYNC_BACKFILL_DAYS` | 90 | How far back the first import of a feed goes |
| `SYNC_OSV_BATCH` | 1500 | OSV records per run at most; a big first import catches up over several runs |
| `SYNC_LOOKBACK_MINUTES` | 120 | Each run also re-reads this far before where the last one stopped, for records that reach OSV or NVD late. Records that are already up to date are not fetched again |
| `SYNC_INCLUDE_MALWARE` | false | Also import OSV `MAL-` (malicious package) reports, of which there are hundreds of thousands |
| `CRITICAL_UPDATE_WINDOW_DAYS` | 90 | Updates to Critical issues go to all language subscribers only for issues this recent |
| `DISPLAY_TIMEZONE` | Europe/Amsterdam | Times on the site and digest schedules (stored times are UTC) |

Mail and SSO are not in `.env`: the superadmin sets them in the website, and the secrets are stored
encrypted with `APP_KEY`. `APP_KEY` is generated on the first start into `storage/app/.env.local`, in the
`app_storage` volume. Back it up together with the database.

## Backups

```sh
docker compose exec -T db pg_dump -U bugreports bugreports | gzip > bugreports-$(date +%F).sql.gz
docker compose cp app:/app/storage/app/.env.local ./env.local.backup
```

## Development

The local PHP is not used: everything runs in Docker.

```sh
cp docker-compose.override.example.yml docker-compose.override.yml   # mounts the code, artisan serve
docker compose up -d --build
npm install && npm run build                                          # or: npm run dev
./docker/test.sh                                                      # Pest on PHP 8.5 + PostgreSQL 17
./docker/artisan.sh migrate                                           # artisan / composer in the dev container
```

Branches follow gitflow: work on `feature/*` from `develop`; `main` holds releases.
