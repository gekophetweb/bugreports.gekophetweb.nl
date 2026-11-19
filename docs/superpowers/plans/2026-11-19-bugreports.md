# bugreports.gekophetweb.nl Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Dockerized Laravel site that aggregates security issues for programming languages and web servers from
OSV.dev and NVD, shows severity, emails subscribers, takes feedback with status, and lets the first user
(superadmin) configure mail and SSO.

**Architecture:** Laravel 13 monolith with Livewire 4 pages. Importers per source write to `issues` (many-to-many
with `languages`); the scheduler runs them every 5/10 minutes as queued jobs with per-feed cursors. Runtime settings
(mail, SSO) live in an encrypted `settings` table applied at boot and before every queued job.

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 4, Tailwind 4 + Vite, Pest 4, Socialite 5, Octane/FrankenPHP,
PostgreSQL 17, Redis 8, Docker Compose, cloudflared.

**Spec:** `docs/superpowers/specs/2026-11-19-bugreports-design.md`

## Global Constraints

- Branching: gitflow. Each task is a `feature/<task-slug>` branch off `develop`, finished with `git flow feature finish`, then `develop` pushed.
- Tests: Pest, run with `php artisan test`. Tests use the `pgsql` connection against a `bugreports_test` DB (jsonb queries must be exercised for real); `Http::fake()` for every external call — no test touches the network.
- Severity bands exactly: Critical 9.0–10.0, High 7.0–8.9, Medium 4.0–6.9, Low 0.1–3.9, else Unknown.
- Schedules: OSV every 5 minutes, NVD every 10 minutes, daily digest 08:00 `Europe/Amsterdam`. All `withoutOverlapping()->onOneServer()`.
- `SYNC_BACKFILL_DAYS=90`, `SYNC_INCLUDE_MALWARE=false`, `NVD_API_KEY=` (optional), announce window 7 days.
- Email code: 6 digits, 10 min TTL, hashed, max 5 attempts per code, max 5 codes per email per hour.
- App timezone `Europe/Amsterdam`; UI copy in English.
- Secrets (SMTP password, API keys, OAuth secrets) are never rendered back into forms; blank on save = keep current.
- Commit messages: conventional (`feat:`, `fix:`, `chore:`, `docs:`, `test:`); no co-author trailers.

## Review Focus

1. **NVD 403/429 or OSV 5xx mid-run** — the feed records a failed `sync_run`, its cursor does not advance, other feeds still complete. (Tested in Tasks 8 and 9.)
2. **First import floods subscribers** — backfilled issues with `published_at` older than 7 days produce zero emails. (Tested in Task 11.)
3. **Two simultaneous first sign-ins** — exactly one superadmin; the setup token works once. (Tested in Task 3.)
4. **Same CVE from OSV and NVD** — one issue row with both languages attached, not two. (Tested in Task 9.)
5. **Mail settings changed while the queue worker runs** — the next queued mail uses the new mailer without restart. (Tested in Task 2.)

---

## File Structure

```
app/
  Enums/            Severity.php, Role.php, FeedbackStatus.php, FeedbackType.php, LanguageKind.php, SubscriptionFrequency.php
  Models/           User, Setting, LoginCode, Language, Issue, SyncCursor, SyncRun, Subscription, Feedback
  Support/
    Settings.php                 typed get/set over settings table (cached, encrypted secrets)
    Mail/MailConfigurator.php    applies stored mail settings to config
    Auth/SsoProviders.php        enabled providers + applies credentials to config('services.*')
    Cvss/Cvss31.php              vector -> base score
  Sources/
    Contracts/IssueSource.php    interface: feeds(Language): iterable<string>; sync(Language, string $feed, SyncRun): void
    Osv/OsvSource.php, Osv/OsvMapper.php
    Nvd/NvdSource.php, Nvd/NvdMapper.php
    IssueData.php                DTO from mappers
    IssueUpserter.php            dedup + upsert + language attach
  Jobs/             SyncFeed.php, NotifySubscribers.php
  Console/Commands/ SetupUrl.php, SyncSource.php, SendDailyDigest.php
  Livewire/         Issues/Index, Issues/Show, Auth/Login, Setup, Feedback/Create, Feedback/Mine, Feedback/Status,
                    Subscriptions, Admin/Mail, Admin/SignIn, Admin/Users, Admin/Languages, Admin/Feedback, Admin/Sync
  Notifications/    LoginCodeNotification, NewIssuesNotification, DailyDigestNotification, FeedbackStatusChanged, FeedbackReceived
  Http/Controllers/ SsoController.php, UnsubscribeController.php
database/seeders/   LanguageSeeder.php
docker/             entrypoint.sh
Dockerfile, docker-compose.yml, docker-compose.override.example.yml, .env.example, README.md
tests/Feature/...  tests/Unit/...  tests/Fixtures/osv/*, tests/Fixtures/nvd/*
```

---

### Task 1: Laravel scaffold + Docker

**Files:**
- Create: Laravel 13 skeleton (`composer create-project laravel/laravel` into a temp dir, move into repo, keep `README.md` / `docs/`), `Dockerfile`, `docker-compose.yml`, `docker-compose.override.example.yml`, `docker/entrypoint.sh`, `.env.example`, `.dockerignore`
- Test: `tests/Feature/HealthTest.php`

**Interfaces:**
- Produces: compose services `app`, `queue`, `scheduler`, `db`, `redis`, `cloudflared` (profile `tunnel`); networks `frontend`, `backend` (internal); volumes `db_data`, `app_storage`. Compose project name `bugreports`.

Copy the structure of `F:\Personal Projects\filament.gekophetweb.nl` (Dockerfile stages vendor → assets → FrankenPHP runtime; entrypoint generating `APP_KEY` into `storage/app/.env.local`; same healthchecks). Differences: no MCP; entrypoint calls `php artisan setup:url` (Task 3 — guard with `|| true` until it exists). Install: `livewire/livewire:^4`, `laravel/octane`, `laravel/socialite`, `pestphp/pest:^4`, `pestphp/pest-plugin-laravel`, `resend/resend-php`, `symfony/mailgun-mailer`, `symfony/postmark-mailer`, `symfony/http-client`. `config/app.php` timezone `Europe/Amsterdam`. `bootstrap/app.php`: `$middleware->trustProxies(at: '*')`.

- [ ] **Step 1:** `git flow feature start scaffold`; scaffold Laravel and install packages; `php artisan livewire:layout` not used — layouts are written by hand in Tasks 6 and 11.
- [ ] **Step 2: Write tests**

```php
it('reports healthy', fn () => $this->get('/up')->assertOk());
it('builds https urls behind the tunnel', function () {
    Route::get('/_url', fn () => url('/x'));
    $this->get('/_url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'bugreports.gekophetweb.nl'])
        ->assertSee('https://bugreports.gekophetweb.nl/x');
});
```
- [ ] **Step 3:** Run `php artisan test` → PASS.
- [ ] **Step 4:** Write Docker files. Verify: `docker compose config -q` exits 0; `docker compose up -d --build` then `curl -fs http://127.0.0.1:8000/up` returns 200; `docker compose exec db pg_isready` OK; `docker compose port db 5432` prints nothing (DB not published).
- [ ] **Step 5:** Commit `chore: scaffold Laravel app with Docker and Cloudflare tunnel profile`; `git flow feature finish scaffold`; push `develop`.

### Task 2: Settings store + mail configuration

**Files:**
- Create: `app/Models/Setting.php`, migration `settings` (`key` string PK, `value` text nullable, `encrypted` bool), `app/Support/Settings.php`, `app/Support/Mail/MailConfigurator.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Settings/SettingsTest.php`, `tests/Feature/Settings/MailConfiguratorTest.php`

**Interfaces:**
- Produces: `Settings::get(string $key, mixed $default = null): mixed`, `Settings::set(string $key, mixed $value, bool $secret = false): void`, `Settings::many(array $keys): array` (cached under `settings.all`, cache flushed on set). `MailConfigurator::apply(): void`. Keys: `mail.mailer` (`smtp|resend|mailgun|postmark|log`), `mail.host`, `mail.port`, `mail.scheme`, `mail.username`, `mail.password`*, `mail.from_address`, `mail.from_name`, `mail.resend_key`*, `mail.mailgun_domain`, `mail.mailgun_secret`*, `mail.mailgun_endpoint`, `mail.postmark_token`* (* = secret).

`apply()` sets `mail.default`, `mail.mailers.smtp.*`, `mail.from.*`, `services.resend.key`, `services.mailgun.*`, `services.postmark.token`, then `app('mail.manager')->forgetMailers()`. Called in `AppServiceProvider::boot()` (skip when `settings` table missing) and in `Queue::before(...)`.

- [ ] **Step 1: Write failing tests**

```php
it('encrypts secrets at rest', function () {
    Settings::set('mail.password', 'hunter2', secret: true);
    expect(DB::table('settings')->where('key', 'mail.password')->value('value'))->not->toBe('hunter2')
        ->and(Settings::get('mail.password'))->toBe('hunter2');
});
it('applies smtp settings', function () {
    Settings::set('mail.mailer', 'smtp'); Settings::set('mail.host', 'smtp.example.test'); Settings::set('mail.port', '2525');
    app(MailConfigurator::class)->apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525);
});
it('applies resend and mailgun keys', ...); // config('services.resend.key'), config('services.mailgun.domain')
it('reapplies before each queued job', function () {
    Settings::set('mail.mailer', 'log'); app(MailConfigurator::class)->apply();
    Settings::set('mail.mailer', 'array');      // changed "from the web" after worker boot
    Event::dispatch(new JobProcessing('redis', Mockery::mock(Job::class)));
    expect(config('mail.default'))->toBe('array');
});
```
(Allow `array` as an internal mailer value for tests only via `app()->runningUnitTests()`.)
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement. **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `feat: encrypted settings store and runtime mail configuration`; finish feature; push.

### Task 3: First-run setup → superadmin

**Files:**
- Create: `app/Enums/Role.php` (`Superadmin='superadmin'`, `User='user'`), migration adding `role` to `users` (default `user`), `app/Console/Commands/SetupUrl.php` (`setup:url`), `app/Livewire/Setup.php` + view, route `GET /setup/{token}`
- Test: `tests/Feature/Auth/SetupTest.php`

**Interfaces:**
- Consumes: `Settings` (Task 2) — token stored as secret key `setup.token`.
- Produces: `User::isSuperadmin(): bool`; route name `setup`; middleware alias `superadmin` (403 unless superadmin).

`setup:url`: if no users, ensure a random 40-char `setup.token` exists and print `URL: {APP_URL}/setup/{token}`; else print nothing. Setup form: name + email. On submit, inside `DB::transaction` with `lockForUpdate` on a `settings` row `setup.token`: abort 404 if any user exists or token mismatch; create superadmin; delete token; log in; redirect `admin.mail`.

- [ ] **Step 1: Tests**

```php
it('prints a setup link only while there are no users', ...);         // artisan('setup:url')->expectsOutputToContain('/setup/')
it('creates the first user as superadmin', ...);                       // Livewire::test(Setup::class, ['token'=>$t])->set(...)->call('save'); role superadmin; authenticated
it('rejects a wrong token with 404', ...);
it('is gone once a user exists', ...);                                 // second use of same token → 404, users count 1
it('requires superadmin for admin routes', ...);                       // actingAs(user)->get('/admin/mail')->assertForbidden()
```
- [ ] **Steps 2–4:** FAIL → implement → PASS. **Step 5:** Commit `feat: one-time setup link creates the superadmin`; finish; push.

### Task 4: Email-code sign-in

**Files:**
- Create: `app/Models/LoginCode.php` + migration (`email`, `code_hash`, `attempts` int, `expires_at`, `consumed_at`), `app/Livewire/Auth/Login.php` + view, `app/Notifications/LoginCodeNotification.php`, route `GET /login`, `POST /logout`
- Test: `tests/Feature/Auth/EmailCodeTest.php`

**Interfaces:**
- Produces: `LoginCode::issue(string $email): string` (returns plain code), `LoginCode::verify(string $email, string $code): bool`. Livewire `Login` steps `email` → `code`. Named rate limiter `login-codes` (5/hour per lowercased email).

- [ ] **Step 1: Tests** — `it('emails a 6-digit code')` (Notification::fake, `/^\d{6}$/`); `it('signs in with the right code and creates the user')` (role `user`); `it('rejects an expired code')` (travel 11 minutes); `it('locks a code after 5 wrong attempts')` (6th with the right code fails); `it('limits codes to 5 per hour per email')` (6th request shows a validation error, only 5 notifications); `it('does not reuse a consumed code')`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: sign in with an emailed one-time code`; finish; push.

### Task 5: SSO (Google default, GitHub optional)

**Files:**
- Create: `app/Support/Auth/SsoProviders.php`, `app/Http/Controllers/SsoController.php`, migration `social_accounts` (`user_id`, `provider`, `provider_id`, unique(provider, provider_id)), `app/Models/SocialAccount.php`
- Modify: login view (provider buttons), `routes/web.php` (`/auth/{provider}/redirect`, `/auth/{provider}/callback`)
- Test: `tests/Feature/Auth/SsoTest.php`

**Interfaces:**
- Consumes: `Settings` keys `sso.google.enabled` (default `true`), `sso.google.client_id`, `sso.google.client_secret`*, `sso.github.enabled` (default `false`), `sso.github.client_id`, `sso.github.client_secret`*.
- Produces: `SsoProviders::enabled(): array<string,string>` (slug → label, only enabled AND credentialed), `SsoProviders::apply(): void` (sets `services.{p}.client_id|client_secret|redirect` = `route('sso.callback', $p)`).

Callback: find `SocialAccount` → user; else user by email (Google: require `user['email_verified']`); else create `user`. Unknown/disabled provider → 404.

- [ ] **Step 1: Tests** (mock `Socialite::driver('google')->user()` returning a `Laravel\Socialite\Two\User`): `it('hides google until credentials are set')`; `it('signs in a new google user')`; `it('links google to an existing email-code user')`; `it('refuses an unverified google email')`; `it('404s for a disabled provider')`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: Google and GitHub sign-in via Socialite`; finish; push.

### Task 6: Admin — mail, sign-in, users

**Files:**
- Create: `app/Livewire/Admin/Mail.php`, `Admin/SignIn.php`, `Admin/Users.php` + views, `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/Admin/MailSettingsTest.php`, `SignInSettingsTest.php`, `UsersTest.php`

**Interfaces:**
- Consumes: `Settings`, `MailConfigurator`, `SsoProviders`, middleware `superadmin`.
- Produces: routes `admin.mail`, `admin.sign-in`, `admin.users`.

Mail page shows only fields for the chosen mailer; "Send test email" sends `TestMail` to the current admin and shows the transport error message on failure.

- [ ] **Step 1: Tests** — saves SMTP fields; blank password keeps the stored one; secret never appears in rendered HTML (`assertDontSee('hunter2')`); test email sent (`Mail::fake`, `assertSent`); validation (`mail.from_address` email, `mail.port` 1–65535); SignIn saves Google credentials and `SsoProviders::enabled()` then contains `google`; Users: promote user; `it('cannot demote the last superadmin')`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: superadmin pages for mail, sign-in and users`; finish; push.

### Task 7: Domain model, languages, severity, CVSS

**Files:**
- Create: enums `Severity` (`Critical|High|Medium|Low|Unknown`, `fromScore(?float): self`, `fromLabel(?string): self`, `rank(): int` Critical=4…Unknown=0), `LanguageKind`; models + migrations `languages` (`slug` unique, `name`, `kind`, `enabled` bool, `osv_ecosystems` jsonb, `nvd_cpes` jsonb), `issues` (`source` `osv|nvd`, `external_id` unique, `aliases` jsonb GIN index, `title`, `description` text, `severity`, `cvss_score` decimal(3,1) null, `cvss_vector` null, `packages` jsonb, `references` jsonb, `source_url`, `published_at`, `modified_at`, `withdrawn_at` null, `first_seen_at`), pivot `issue_language`; `app/Support/Cvss/Cvss31.php`; `database/seeders/LanguageSeeder.php`
- Test: `tests/Unit/Cvss31Test.php`, `tests/Unit/SeverityTest.php`, `tests/Feature/LanguageSeederTest.php`

**Interfaces:**
- Produces: `Cvss31::score(string $vector): ?float` (null for non-3.x/invalid), `Severity::fromScore`, `Issue::scopeForLanguages`, `Issue::scopeMinSeverity(Severity)`, `Issue::sourceUrlFor(string $source, string $id): string` (`GHSA-*` → `https://github.com/advisories/{id}`, other OSV → `https://osv.dev/vulnerability/{id}`, NVD → `https://nvd.nist.gov/vuln/detail/{id}`).

Seed (slug: OSV ecosystems / NVD CPEs):
php: Packagist / `cpe:2.3:a:php:php` · python: PyPI / `cpe:2.3:a:python:python` · javascript: npm / `cpe:2.3:a:nodejs:node.js` · go: Go / `cpe:2.3:a:golang:go` · rust: crates.io / `cpe:2.3:a:rust-lang:rust` · java: Maven / `cpe:2.3:a:oracle:openjdk` · dotnet: NuGet / `cpe:2.3:a:microsoft:.net` · ruby: RubyGems / `cpe:2.3:a:ruby-lang:ruby` · elixir: Hex / `cpe:2.3:a:erlang:erlang\/otp` · dart: Pub / – · nginx (server): – / `cpe:2.3:a:f5:nginx`, `cpe:2.3:a:nginx:nginx` · apache (server): – / `cpe:2.3:a:apache:http_server`.

- [ ] **Step 1: Tests**

```php
it('scores CVSS 3.1 vectors', function (string $v, float $s) { expect(Cvss31::score($v))->toBe($s); })->with([
    ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8],
    ['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N', 6.1],
    ['CVSS:3.1/AV:L/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N', 5.5],
    ['CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N', 3.7],
    ['CVSS:3.0/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H', 10.0],
]);
it('returns null for v4 and garbage', ...);          // 'CVSS:4.0/AV:N/...', 'nope'
it('bands scores', ...);                               // 9.0→Critical, 8.9→High, 7.0→High, 4.0→Medium, 0.1→Low, 0.0→Unknown, null→Unknown
it('maps labels', ...);                                // 'MODERATE'→Medium, 'high'→High, null→Unknown
it('seeds nginx and apache as servers', ...);
```
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: issues, languages, severity and CVSS 3.1 scoring`; finish; push.

### Task 8: OSV importer + sync bookkeeping

**Files:**
- Create: `app/Sources/Contracts/IssueSource.php`, `app/Sources/IssueData.php`, `app/Sources/IssueUpserter.php`, `app/Sources/Osv/OsvSource.php`, `app/Sources/Osv/OsvMapper.php`, models + migrations `sync_cursors` (`source`, `feed`, `cursor` timestamptz, unique(source, feed)), `sync_runs` (`source`, `feed`, `status` `running|ok|failed`, `created_count`, `updated_count`, `error` text null, `started_at`, `finished_at`, `new_issue_ids` jsonb), `app/Jobs/SyncFeed.php`
- Fixtures: `tests/Fixtures/osv/Packagist-modified_id.csv`, `tests/Fixtures/osv/GHSA-example.json`, `tests/Fixtures/osv/PYSEC-v4-only.json` — record real responses once (`curl`) and trim.
- Test: `tests/Feature/Sources/OsvSourceTest.php`, `tests/Unit/OsvMapperTest.php`

**Interfaces:**
- Produces: `IssueSource::feeds(Language $l): array<string>` (OSV: the ecosystems), `IssueSource::sync(Language $l, string $feed, SyncRun $run): void`; `IssueData` readonly props mirroring `issues` columns + `languageId`; `IssueUpserter::upsert(IssueData $d): array{issue: Issue, created: bool}`; `SyncFeed::__construct(string $sourceClass, int $languageId, string $feed)`.

OSV flow: GET csv (lines `RFC3339,ID`, newest first); collect until timestamp ≤ cursor (or ≤ now−`SYNC_BACKFILL_DAYS` on first run); skip `MAL-` unless `SYNC_INCLUDE_MALWARE`; GET `https://api.osv.dev/v1/vulns/{id}` each (Http pool of 10). Mapper: title = `summary` ?: first 160 chars of `details`; severity = Cvss31 on first `CVSS_V3` entry, else `database_specific.severity` label, else Unknown; packages = `affected[].package.name` unique; `withdrawn` → `withdrawn_at`. Cursor advances to the newest processed timestamp only when the run ends `ok`. `SyncFeed` wraps in try/catch: on exception mark run `failed` with message, do not rethrow (so other feeds continue).

- [ ] **Step 1: Tests** — imports two ids from fixture csv, creates issues linked to php; second run with same csv creates 0; `it('maps GHSA severity label when no v3 vector')`; `it('skips MAL ids by default')`; `it('records a failed run and keeps the cursor on 500')` (`Http::fake([... => Http::response('', 500)])`, cursor unchanged, run status `failed`); `it('respects the backfill window')`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: OSV importer with cursors and sync runs`; finish; push.

### Task 9: NVD importer + dedup

**Files:**
- Create: `app/Sources/Nvd/NvdSource.php`, `app/Sources/Nvd/NvdMapper.php`
- Modify: `app/Sources/IssueUpserter.php` (alias dedup)
- Fixtures: `tests/Fixtures/nvd/nginx-page1.json`, `nginx-page2.json`
- Test: `tests/Feature/Sources/NvdSourceTest.php`, `tests/Feature/Sources/DedupTest.php`

**Interfaces:**
- Consumes: `IssueSource`, `IssueData`, `IssueUpserter`, `SyncFeed` (Task 8).
- Produces: `NvdSource` with `feeds()` = the language's CPEs.

GET `https://services.nvd.nist.gov/rest/json/cves/2.0` with `virtualMatchString`, `lastModStartDate`/`lastModEndDate` (ISO-8601 with offset; split into ≤120-day windows), `resultsPerPage=2000`, `startIndex` paging; header `apiKey` when `NVD_API_KEY` set; sleep 6 s between requests without a key, 0.6 s with (inject a `Sleeper` so tests don't sleep). Mapper: title = first sentence of English description (≤160 chars); score from `cvssMetricV31[0]` ?: `cvssMetricV30[0]` (`baseScore`, `vectorString`), else `cvssMetricV2` baseSeverity label; `vulnStatus = 'Rejected'` → `withdrawn_at`. Upserter dedup: before inserting an NVD CVE, look for an issue whose `aliases @> '["CVE-…"]'`; if found, attach language, fill missing score, and count as updated.

- [ ] **Step 1: Tests** — pages through 2 fixture pages (asserts `startIndex=0` then `2000` requested); sends `apiKey` header when configured; `it('merges an NVD CVE into the OSV issue that aliases it')` (one `issues` row, two languages); failed 403 → run `failed`, cursor unchanged; rejected CVE gets `withdrawn_at`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: NVD importer with alias deduplication`; finish; push.

### Task 10: Scheduler + admin sync & languages pages

**Files:**
- Create: `app/Console/Commands/SyncSource.php` (`issues:sync {source : osv|nvd} {--language=}`), `app/Livewire/Admin/Sync.php`, `app/Livewire/Admin/Languages.php` + views
- Modify: `routes/console.php`
- Test: `tests/Feature/SchedulerTest.php`, `tests/Feature/Admin/SyncPageTest.php`, `tests/Feature/Admin/LanguagesTest.php`

**Interfaces:**
- Consumes: `SyncFeed`, `OsvSource`, `NvdSource`, `Language`.
- Produces: `issues:sync` dispatches one `SyncFeed` per (enabled language × feed). Routes `admin.sync`, `admin.languages`.

`routes/console.php`: `Schedule::command('issues:sync osv')->everyFiveMinutes()`, `Schedule::command('issues:sync nvd')->everyTenMinutes()`, `Schedule::command('sync-runs:prune')->daily()` (delete >14 days), `Schedule::command('digest:send')->dailyAt('08:00')->timezone('Europe/Amsterdam')` — all `->withoutOverlapping()->onOneServer()`.

- [ ] **Step 1: Tests** — `it('schedules osv every five and nvd every ten minutes')` (inspect `app(Schedule::class)->events()` expressions `*/5 * * * *`, `*/10 * * * *`); `issues:sync osv` dispatches jobs only for enabled languages with ecosystems (`Queue::fake`); sync page lists last runs with failed error text; languages page toggles `enabled` and edits ecosystems/CPEs (comma-separated input → array).
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: scheduled syncing and admin sync/language pages`; finish; push.

### Task 11: Public issues UI

**Files:**
- Create: `app/Livewire/Issues/Index.php`, `Issues/Show.php` + views, `resources/views/components/severity-badge.blade.php`, `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/Issues/IndexTest.php`, `ShowTest.php`

**Interfaces:**
- Consumes: `Issue` scopes, `Severity`, `Language`.
- Produces: routes `home` (`/`), `issues.show` (`/issues/{issue:external_id}`). Query-string bound filters `language`, `severity` (minimum), `q`.

Default list hides withdrawn; 25 per page; badge colours Critical red, High orange, Medium amber, Low slate, Unknown gray (text label always present, not colour-only). Search matches `external_id`, `aliases`, `title`, `packages` (ILIKE / jsonb text).

- [ ] **Step 1: Tests** — lists newest first; filter by language; `severity=high` shows High+Critical only; search by alias CVE finds the OSV issue; show page links to `source_url` with `rel="noopener"`; withdrawn hidden from list but show page still 200 with "Withdrawn" label.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: issue list and detail pages with severity badges`; finish; push.

### Task 12: Subscriptions + notifications

**Files:**
- Create: `app/Models/Subscription.php` + migration (`user_id`, `language_id`, `min_severity`, `frequency`, unique(user_id, language_id)), `app/Enums/SubscriptionFrequency.php`, `app/Livewire/Subscriptions.php` + view, `app/Jobs/NotifySubscribers.php`, `app/Console/Commands/SendDailyDigest.php` (`digest:send`), `app/Notifications/NewIssuesNotification.php`, `DailyDigestNotification.php`, `app/Http/Controllers/UnsubscribeController.php` (signed URL)
- Modify: `app/Jobs/SyncFeed.php` (dispatch `NotifySubscribers($run->id)` when run ok and `new_issue_ids` non-empty)
- Test: `tests/Feature/Subscriptions/*Test.php`

**Interfaces:**
- Consumes: `SyncRun.new_issue_ids` (Task 8), `Issue::scopeMinSeverity`.
- Produces: `Issue::scopeAnnounceable()` = `first_seen_at` set AND `published_at >= now()->subDays(7)` AND not withdrawn; route `subscriptions` (auth); signed route `unsubscribe` (`/unsubscribe/{subscription}`).

- [ ] **Step 1: Tests** — user subscribes to php High/instant; `it('sends one email per user per run')` (3 new php issues → 1 `NewIssuesNotification` with 3 items); below min severity → none; `it('stays quiet for backfilled old issues')` (published 30 days ago → none); daily subscriber gets nothing instantly, one digest at `digest:send` covering last 24 h; unsigned unsubscribe → 403, signed → deletes subscription; guests redirected to login.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: language subscriptions with instant and daily emails`; finish; push.

### Task 13: Feedback with status

**Files:**
- Create: `app/Models/Feedback.php` + migration (`user_id` null, `name`, `email`, `type`, `message` text, `status`, `public_note` text null, `token` string(40) unique), enums `FeedbackStatus` (`New|InProgress|Planned|Done|Declined`), `FeedbackType` (`Bug|Idea|Other`), Livewire `Feedback/Create`, `Feedback/Mine`, `Feedback/Status`, `Admin/Feedback` + views, `FeedbackReceived`, `FeedbackStatusChanged` notifications
- Test: `tests/Feature/Feedback/*Test.php`

**Interfaces:**
- Produces: routes `feedback.create` (`/feedback`), `feedback.mine` (auth), `feedback.status` (`/feedback/{token}`), `admin.feedback`. Rate limiter `feedback` 5/hour per IP.

- [ ] **Step 1: Tests** — guest submits, receives `FeedbackReceived` with status link, status page shows "New"; signed-in submit prefills email and appears in "My feedback"; admin changes status to Planned with note → submitter gets `FeedbackStatusChanged`, status page shows note; wrong token 404; 6th submission in an hour rejected; non-admin cannot reach `admin.feedback`.
- [ ] **Steps 2–4.** **Step 5:** Commit `feat: feedback form with status tracking`; finish; push.

### Task 14: Docs, release

**Files:**
- Modify: `README.md` (local dev, `.env` values, first-run setup link via `docker compose logs app`, mail/SSO setup, Google OAuth redirect URI `https://bugreports.gekophetweb.nl/auth/google/callback`, Cloudflare Tunnel: create tunnel → public hostname `bugreports.gekophetweb.nl` → service `http://app:8000` → `CLOUDFLARE_TUNNEL_TOKEN` → `docker compose --profile tunnel up -d`, NVD API key, backups).

- [ ] **Step 1:** Fresh-clone smoke test: `cp .env.example .env`, fill DB/Redis passwords, `docker compose up -d --build`, open setup link from logs, create admin, set mailer `log`, run `docker compose exec app php artisan issues:sync osv --language=php`, wait for queue, confirm issues on `/`.
- [ ] **Step 2:** `php artisan test` all green; `vendor/bin/pint --test` clean.
- [ ] **Step 3:** Commit `docs: deployment and setup guide`; push `develop`. `git flow release start 0.1.0`, `git flow release finish 0.1.0`, push `main`, `develop`, tags.
