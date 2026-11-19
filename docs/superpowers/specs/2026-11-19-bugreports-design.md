# bugreports.gekophetweb.nl — Design

Date: 2026-11-19 · Status: draft for review

## Intent

One website that gathers published bug reports and security issues for many programming languages and web
servers (nginx, Apache httpd included), shows each issue's severity, and links to the original source. A
scheduled job checks the sources every 5–10 minutes. Visitors can send feedback and follow its status. Signed-in
users subscribe to the languages they care about and get emailed about new issues.

## Goals

- One list of issues across languages and servers, filterable by language, severity and text, with every entry
  linking to its source.
- Severity shown on every issue: Critical / High / Medium / Low / Unknown, from the CVSS score.
- New and changed issues picked up automatically every 5–10 minutes.
- Sign-in with Google (SSO, on by default once configured) or with a one-time code sent by email.
- Subscriptions per language, with a minimum severity, sent instantly, as a daily digest or as a weekly digest.
- Watching single issues: opt in per issue to get emailed when it is updated.
- Starring issues so they are shown first.
- A personal "My issues" view showing only subscribed languages and/or watched and starred issues.
- Filters on language, severity and date.
- A feedback form; the submitter can see its status and is emailed when the status changes.
- The first user becomes superadmin. The superadmin sets up outgoing mail (SMTP, Resend, Mailgun, Postmark) and
  SSO credentials in the website, without editing files.
- Runs in Docker, with the website and the database in separate containers, and can be exposed through a
  Cloudflare Tunnel.

## Non-goals

- Hosting or triaging issues ourselves: this site aggregates and links, the source stays the authority.
- Every ordinary bug tracker ticket (php-src issues, bugs.python.org, nginx Trac). These carry no severity and
  are far too noisy. *(My call; see Approaches.)*
- Malicious-package reports (OSV `MAL-` ids). They run to hundreds of thousands and would drown everything else.
  They are skipped by default and can be switched on in config. *(My call.)*
- A public API, and paid plans.

## Anti-goals

- A first visitor reaching the site through the public tunnel must never become superadmin.
- Subscribers must not get one email per issue, or emails about years-old issues during the first import.
- One source being down or rate-limited must not stop the other sources from syncing.

## Constraints

- Same stack as the other gekophetweb sites (filament, library): Laravel 13, PHP 8.5, Livewire 4, Tailwind,
  Pest 4, Octane on FrankenPHP, PostgreSQL 17, Redis 8, Docker Compose with separate `frontend` and internal
  `backend` networks, and `cloudflared` under a compose profile.
- NVD allows 5 requests per 30 s without an API key and 50 with one. The key is optional and goes in `.env`.
- Gitflow: `main` holds releases and work happens on `develop` and `feature/*` branches.

## Approaches considered

1. **Security advisory databases (chosen).** OSV.dev covers the package ecosystems (Packagist, PyPI, npm, Go,
   crates.io, Maven, NuGet, RubyGems, Hex, Pub) and already includes GitHub Security Advisories. NVD's CVE API
   covers the runtimes and servers themselves (PHP, CPython, Node.js, Go, nginx, Apache httpd…) through their CPE
   names. Both give CVSS data, so severity is real rather than guessed, and both support "changed since X"
   queries, which makes a 5–10 minute check cheap.
2. **Scrape each project's own bug tracker.** This covers ordinary bugs too, but needs a separate integration per
   tracker (GitHub Issues, Trac, Bugzilla, Jira), mostly has no severity, and drowns the useful items in noise.
   Rejected because severity was explicitly asked for.
3. **Only GitHub Security Advisories.** This needs one integration, but misses the CVEs for nginx, Apache and the
   language runtimes themselves. Rejected because nginx and Apache were named explicitly.

## Design

### Sources and sync

- `languages` are seeded and editable by the superadmin. Each one has a name, a slug, a kind (`language` |
  `server`), an enabled flag, a list of OSV ecosystems and a list of NVD CPE match strings.
- **OSV, every 5 minutes.** Per ecosystem, read
  `https://osv-vulnerabilities.storage.googleapis.com/{ecosystem}/modified_id.csv` (newest first), take the ids
  modified since that source's last cursor, and fetch each from `https://api.osv.dev/v1/vulns/{id}`.
- **NVD, every 10 minutes.** Per CPE, call `/rest/json/cves/2.0?virtualMatchString=…&lastModStartDate=…&lastModEndDate=…`
  and page through the results. NVD allows a window of at most 120 days per query.
- **First import:** goes back `SYNC_BACKFILL_DAYS` (default 90) days. *(My call.)*
- Each (source, language, feed) keeps a cursor in `sync_cursors`, and each run is logged in `sync_runs` (status,
  counts, error), which the superadmin sees. A failing feed logs its error and the rest carry on.
- **Deduplication:** an NVD CVE that an existing OSV issue lists as an alias updates that issue instead of creating
  a new one. Issues link to languages many-to-many.

### Severity

- If an issue has a CVSS v3.x vector, the score is computed from the vector by a built-in CVSS 3.1 calculator.
- Otherwise the source's own rating is used: NVD's `baseSeverity`, or the GHSA `database_specific.severity` in
  OSV records.
- Otherwise the severity is Unknown. CVSS v4 vectors are stored but not scored. *(My call: the v4 scoring tables are
  large, and the source label covers most cases.)*
- Bands: Critical 9.0–10, High 7.0–8.9, Medium 4.0–6.9, Low 0.1–3.9.

### Website

- **Home / issues list:** newest first, with the signed-in user's starred issues above the rest (still under the
  same filters). Filters for language (several can be picked), minimum severity, date, and search text (id,
  alias, title, package). The date filter has quick choices (last 24 hours, 7 days, 30 days, 90 days) and a custom
  from/to range. It filters on the published date by default and can be switched to the last-updated date.
  *(My call.)* Each row shows a severity badge, the id, the title, the languages, the packages, the published
  date, and a link to the source.
- **Issue page:** description, CVSS score and vector, aliases, affected packages, references, and a link to the
  source (`osv.dev/vulnerability/{id}`, `github.com/advisories/{id}` or `nvd.nist.gov/vuln/detail/{id}`).
- **Feedback:** anyone can send it (name, email, type bug / idea / other, message). Statuses are New → In
  progress / Planned → Done / Declined. Signed-in users see their feedback under My account → My feedback. Guests get a
  private status link by email. The superadmin changes the status and can add a public note, and the submitter
  is emailed about the change.

### Accounts

- **First run:** while no users exist, the container log prints a one-time `/setup/{token}` link. Opening it
  creates the first user as superadmin and signs them in. The setup page is gone once a user exists. This
  satisfies "the first user is superadmin" without letting a stranger on the tunnel claim the role.
- **Email code:** 6 digits, valid 10 minutes, stored hashed, at most 5 attempts per code and 5 codes per email
  per hour. An unknown email creates a normal user on first sign-in.
- **SSO:** Laravel Socialite. Google is on by default; GitHub is available as a second provider. The superadmin
  enters the client ids and secrets in Admin → Sign-in. A provider appears on the login page only when it is
  enabled and has credentials. Accounts are matched by verified email.
- **Roles:** `superadmin` and `user`. The superadmin can promote other users to superadmin, but cannot demote
  the last superadmin.

### Mail settings (superadmin)

- The mailer is one of SMTP, Resend, Mailgun, Postmark or log-only, plus the from address and from name.
- Settings are stored in a `settings` table, with secrets encrypted. They are applied to the config at boot and
  before every queued job, so the queue worker picks up changes without a restart.
- A "Send test email" button checks the settings work.

### Subscriptions, watching and starring

- **Language subscriptions:** a user subscribes per language, with a minimum severity (default High) and a
  frequency (`instant` | `daily` | `weekly`).
  - **Instant:** after each sync run, each subscriber gets one email listing that run's new matching issues.
  - **Daily:** a digest at 08:00 Europe/Amsterdam covering the last 24 hours.
  - **Weekly:** a digest on Monday at 08:00 Europe/Amsterdam covering the last 7 days.
  - **Critical alerts:** a new Critical issue is emailed instantly to *every* subscriber of its language, whatever
    their frequency, as soon as the sync run that added it finishes. Daily and weekly subscribers still see it in
    their next digest too, marked "already alerted". *(My call: the digest stays a complete overview.)* Instant
    subscribers get it in their usual per-run email, so nobody gets it twice from one run.
  - **Critical updates:** when a Critical issue changes (by the same rule as watched issues), every subscriber of
    its language is emailed straight after the sync run, as if they were watching it. This also covers an issue
    that *becomes* Critical through an update, which counts as a critical alert, and one that is downgraded from
    Critical, so subscribers learn it is less urgent. *(My call.)* Only issues published in the last 90 days count
    (`CRITICAL_UPDATE_WINDOW_DAYS`), because NVD keeps re-touching old CVEs and that would flood inboxes.
    *(My call.)* A user gets one "issues updated" email per run, covering both their watched issues and the
    critical updates.
- **Watching an issue:** a "Watch" toggle on the issue page (signed-in users only) opts in to emails when that
  issue changes. A change counts when the severity, CVSS score, description, references or withdrawn status
  changes; a bare "modified" timestamp does not. Each user gets at most one "watched issues updated" email per
  sync run. Watched-issue updates also go into the user's daily or weekly digest if they have one. *(My call: the
  instant email is the opt-in; the digest repeats it as a summary.)*
- **Starring an issue:** the star toggle shows on the list and on the issue page. Starred issues go to the top of
  every list the user sees. Starring and watching are separate: a star pins an issue, watching sends email.
  *(My call.)*
- **Shortening long digests:** the first 20 items (ranked by severity, then newest) are shown in full with
  severity, title, packages and link. The next 100 are shown as one line each (severity · id · title). Anything
  beyond that becomes "and N more", linking to My issues with the matching filters already set. Watched-issue
  updates always come first and are never shortened.
- Only issues first seen by us *and* published in the last 7 days are announced as new, so the first import stays
  quiet.
- Every email has a one-click unsubscribe link (for the subscription or the watch it came from) and a link to the
  email settings.

### My account

- **My issues** (`/my`): the issue list limited to the user's subscribed languages and/or their watched and
  starred issues, picked with the toggles "Subscribed languages", "Watched" and "Starred" (all on by default).
  Starred issues come first, and the language, severity, date and search filters all work here too.
- **Settings** (`/my/settings`): language subscriptions (language, minimum severity, frequency), the list of
  watched issues with an unwatch button, and the user's name.
- **My feedback** (`/my/feedback`): see Feedback.

### Docker and the tunnel

- Services: `app` (Octane), `queue`, `scheduler`, `db` (Postgres, internal network only, its own volume), `redis`,
  and `cloudflared` (profile `tunnel`, `CLOUDFLARE_TUNNEL_TOKEN`).
- `app` publishes only `127.0.0.1:8000`. With the tunnel, Cloudflare points `bugreports.gekophetweb.nl` at
  `http://app:8000`.
- Trusted proxies are on, so HTTPS URLs are generated correctly behind the tunnel.

## Left to the builder

- The exact Tailwind look. Keep it clean and readable, with a dark mode.
- Pagination size, index tuning, and log retention for `sync_runs` (suggestion: keep 14 days).
