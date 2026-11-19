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

## Run it

You need Docker with Compose. The website (`app`, `queue`, `scheduler`) and the database (`db`, PostgreSQL 17)
run in separate containers. The database and Redis are only on an internal network and are never published.

```sh
cp .env.example .env
# edit .env: set DB_PASSWORD and REDIS_PASSWORD; APP_URL is your public URL
docker compose up -d --build
docker compose logs app | grep -A1 "No superadmin"
```

Open the setup link from the log. It works once and creates the superadmin. Until then nobody can sign in.
The app listens on `127.0.0.1:8000` only.

Then, as superadmin:

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
| `NVD_API_KEY` | empty | Optional [NVD API key](https://nvd.nist.gov/developers/request-an-api-key): 50 instead of 5 requests per 30 s |
| `SYNC_BACKFILL_DAYS` | 90 | How far back the first import of a feed goes |
| `SYNC_OSV_BATCH` | 1500 | OSV records per run at most; a big first import catches up over several runs |
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
