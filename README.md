# APFSPN Backend

REST API for the Association of Professional Food Service Providers of Nigeria (APFSPN). It serves three clients:

- **Public website**: published events and articles.
- **Member portal**: registration (individual, corporate, institutional), login with email OTP, dashboard, profile, security settings and event RSVPs.
- **Admin dashboard**: member approval and management, events and attendees, articles with an editorial workflow, admin users and roles, audit trail and dashboard metrics.

Payments and the other modules listed under [Not built yet](#not-built-yet-from-the-brd) are out of scope for this release.

## Stack

- PHP 8.3+ and Laravel 12. Laravel 13 is held back until `cloudinary-labs/cloudinary-laravel` supports it.
- MySQL in production, SQLite for local work and tests.
- Laravel Sanctum for token auth and `spatie/laravel-permission` for roles and permissions.
- Cloudinary for uploaded files: public images, plus private registration documents served through short-lived signed URLs.
- Database or Redis queues for email, and the Laravel scheduler for recurring jobs.

## Architecture

| Concern | How it works |
|---|---|
| Routing | Everything is under `/api/v1`, split by audience into `routes/api/v1/public.php`, `member.php` and `admin.php`. |
| Controllers | Thin. Each endpoint has its own form request class (`app/Http/Requests`, extending `ApiFormRequest`), reads only `$request->validated()` and returns an API resource. |
| Services | All business logic lives in `app/Services`, organised by domain: Auth, Membership, Events, Articles, AdminUsers, Dashboard, Audit. Controllers, queued jobs and console commands all call services. |
| Repositories | Services never query models. Each domain has an interface in `app/Repositories/Contracts` and an Eloquent implementation, bound in `RepositoryServiceProvider`. |
| Response envelope | Every JSON response is `{"error": bool, "message": string, "data": mixed}`. Lists return `data.items` plus `data.pagination` (`current_page`, `per_page`, `total`, `last_page`). One handler in `bootstrap/app.php` renders every exception in the same envelope, with a machine-readable `data.code` for refusals (for example `blocked`, `invalid_status_transition`, `wrong_audience`). |
| Identifiers | Every table has a UUID primary key named `uuid`; foreign keys are `<model>_uuid`. No integer ID appears in any URL or response. |
| Enums | String-backed enums in `app/Enums` for every status and type. They share one `EnumHelpers` trait. |
| Auth | One Sanctum guard for members and admins. Access tokens and refresh tokens are issued as a pair, with token abilities that keep member and admin tokens apart. A refresh token can't be used as a bearer token. Member login has an email OTP by default, and admin login can have one too. Email OTP codes are stored only as bcrypt hashes. Unknown emails get the same responses as real ones, so accounts can't be probed. |
| Roles and permissions | `App\Enums\ePermission` is the source of truth (32 permissions). `RolesAndPermissionsSeeder` creates six built-in roles: `super_admin`, `association_manager`, `editor`, `author`, `events_coordinator` and `membership_coordinator`. Every admin route is behind a `permission:` middleware, and some also have a policy check (for example, authors may only edit their own drafts). |
| Concurrency | Member approval, member ID generation, event capacity and RSVPs each run in a transaction with `lockForUpdate()` and re-check state before acting, so retries and double submits change nothing. Member IDs are `APFSPN` + state code + sequence, for example `APFSPNLA001`. |
| Audit | Every admin write, plus admin login, logout and password reset, is stored in `audit_logs`. Each entry has the sanitised request body (secrets redacted, emails and phones hashed, files dropped) and a `{changed_fields, previous}` change set. |
| Member activity | Member actions (application, approval, RSVPs, profile and security changes) are recorded in `member_activities`, inside the action's own transaction. Only field names are stored, never values. |

## Getting started

Requirements: PHP 8.3+ with the `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl` and `fileinfo` extensions, and Composer.

### SQLite (quickest)

```bash
git clone <repository-url> apfspn-backend
cd apfspn-backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder
php artisan serve
```

The API is now at `http://localhost:8000/api/v1` (try `GET /api/v1/health`). On Windows, create the empty database file with `type nul > database\database.sqlite`.

### MySQL

Create a database, then in `.env` comment out `DB_CONNECTION=sqlite` and fill in the MySQL block:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apfspn
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan migrate` and `php artisan db:seed --class=DemoSeeder`.

### Emails and uploads in local development

- **Email:** `MAIL_MAILER=log` writes every email (OTP codes, invites, status notices) to `storage/logs/laravel.log`. Emails are queued, so run `php artisan queue:work` alongside the server. For quick local testing, `QUEUE_CONNECTION=sync` in `.env` sends them immediately instead.
- **Uploads:** profile photos, event images, article covers and registration documents need Cloudinary credentials (`CLOUDINARY_URL`). Without them, everything else works, but uploads fail and signed document URLs come back as `null`. Individual registration without a photo works without Cloudinary.

## Demo data

`php artisan db:seed --class=DemoSeeder` fills a local database with realistic data. It refuses to run unless `APP_ENV=local`, and running it again does nothing. It goes through the real services, so member IDs, waitlists, the audit trail, member activity and the dashboards all look the way they would in use. No emails are sent or queued while it runs.

**Admins** (password `Password123!`):

| Email | Role |
|---|---|
| superadmin@apfspn.test | super_admin |
| manager@apfspn.test | association_manager |
| editor@apfspn.test | editor |
| author@apfspn.test | author |
| events@apfspn.test | events_coordinator |
| membership@apfspn.test | membership_coordinator |

**Members** (password `Member123!`):

| Email | State |
|---|---|
| adaobi@apfspn.test | Active individual, `APFSPNLA001`, two-factor off. Registered for events and on a waitlist. |
| chinedu@apfspn.test | Active individual, `APFSPNED001`, two-factor on (the login code is in `storage/logs/laravel.log`). |
| tunde@apfspn.test | Active corporate member (Chow Co Limited), `APFSPNLA002`. |
| kemi@, yusuf@, amaka@apfspn.test | Active members who fill the workshop's three seats. |
| ngozi@apfspn.test | Institutional applicant awaiting approval. |
| emeka@apfspn.test | Applicant who has not verified their email (cannot sign in). |
| bola@apfspn.test | Rejected. |
| musa@apfspn.test | Suspended (sign-in refused with `membership_suspended`). |
| funke@apfspn.test | Expired. |

**Events:**
- three published events: a summit, a members-only masterclass, and a three-seat workshop with a two-person waitlist;
- one draft;
- one cancelled event;
- one completed event with attendance recorded.

**Articles:** one in every status (draft, in review, changes requested, scheduled, published, archived).

To start over: `php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder`.

## Running the tests

```bash
php artisan test                                  # everything
php artisan test --exclude-group=concurrency      # skip the two multi-process tests
php artisan test --group=concurrency              # only the concurrency tests
```

Tests use an in-memory SQLite database and need no setup. There are feature tests for every endpoint's happy path and main failure, a test that checks each role's permissions on each endpoint, and `tests/Feature/Architecture/ConventionsTest.php`, which enforces the architecture rules above.

The concurrency group starts real parallel PHP processes:
- several approvals racing for member IDs in one state;
- members racing for an event's last seat.

By default they use a temporary SQLite file. To run them against MySQL/InnoDB, point them at a throwaway database (it is wiped):

```bash
CONCURRENCY_MYSQL_DATABASE=apfspn_concurrency php artisan test --group=concurrency
```

## Local helper commands

Both commands refuse to run outside `APP_ENV=local`.

| Command | Purpose |
|---|---|
| `php artisan apfspn:admin-reset-token {email}` | Prints a password reset token for an admin, for testing the reset or invite flow without email. |
| `php artisan apfspn:member-verify-code {email}` | Issues and prints a fresh email verification code and challenge token for an unverified member, without sending email. |

## Queue and scheduler

```bash
php artisan queue:work        # sends queued emails
php artisan schedule:work     # runs scheduled commands locally
```

Scheduled commands (`routes/console.php`). Each runs with `withoutOverlapping()` and `onOneServer()`, and each supports `--dry-run`:

| Command | Schedule | Purpose |
|---|---|---|
| `articles:publish-scheduled` | every minute | Publishes scheduled articles whose time has come. |
| `events:complete-past` | hourly | Marks published events that have ended as completed. |
| `auth:prune-challenges` | daily | Deletes expired or used OTP challenges. |

## API documentation

| Where | What |
|---|---|
| `public/docs/index.html` | Browsable docs for all 89 endpoints, with request fields, auth, required permission and example responses. With `php artisan serve` running, open `http://localhost:8000/docs/index.html`. |
| `public/docs/openapi.yaml` | OpenAPI 3.0 spec. |
| `public/docs/collection.json` | Postman collection generated from the docs. |
| `postman/apfspn.postman_collection.json` and `postman/apfspn-local.postman_environment.json` | Hand-built Postman collection and environment. Their scripts save tokens and IDs as you go (log in, then list, then show). The request bodies use the demo accounts. |

The example requests and responses are real ones, recorded from the feature test suite, so they match the code. To regenerate the docs after a change:

```bash
DOCS_CAPTURE=1 php artisan test --exclude-group=concurrency   # records examples into tests/Docs/examples
php artisan scribe:generate
```

## Deploying

1. **Install:** `composer install --no-dev --optimize-autoloader`.
2. **Environment:** copy `.env.example` to `.env`, then set at least:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and `APP_KEY` (`php artisan key:generate`);
   - the MySQL `DB_*` block;
   - `QUEUE_CONNECTION` (`database` or `redis`) and `CACHE_STORE`;
   - the mail settings (step 4);
   - `CLOUDINARY_URL`;
   - `FRONTEND_MEMBER_URL` and `FRONTEND_ADMIN_URL`, which links in emails point to;
   - `SUPER_ADMIN_NAME` and `SUPER_ADMIN_EMAIL`, for the first admin invite.
3. **Cloudinary:** create a Cloudinary account and copy the API environment variable (`cloudinary://<api_key>:<api_secret>@<cloud_name>`) into `CLOUDINARY_URL`. Uploads go to the `cloudinary` disk. Registration documents use Cloudinary's `authenticated` delivery and are only served through signed URLs that expire after 10 minutes. Only image URLs on your own Cloudinary cloud are accepted as existing files.
4. **Mail:** set `MAIL_MAILER=smtp` with `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME` and a verified `MAIL_FROM_ADDRESS`. All emails except the admin invite are queued, so a worker must be running.
5. **Database:** run migrations and the production seeders only. Do not run the demo seeder in production; it refuses outside `local` anyway.
   ```bash
   php artisan migrate --force
   php artisan db:seed --force    # roles and permissions, then the super admin invite
   ```
   `db:seed` runs `RolesAndPermissionsSeeder` and `SuperAdminSeeder`. The super admin gets an invite email and sets a password through the reset flow. Re-running the seeder is safe: it adds new permissions without undoing changes admins made to roles.
6. **Caches:** `php artisan config:cache && php artisan route:cache`.
7. **Queue worker:** keep one running under a process manager such as Supervisor or systemd:
   ```bash
   php artisan queue:work --tries=3 --backoff=10 --max-time=3600
   ```
8. **Scheduler:** add one cron entry:
   ```cron
   * * * * * cd /path/to/apfspn-backend && php artisan schedule:run >> /dev/null 2>&1
   ```
9. **Before the first production release:** run the concurrency tests against MySQL (see [Running the tests](#running-the-tests)).

## Decisions and trade-offs

- **No payments yet.** Membership activation goes through a single method, `MemberApprovalService::approve()`, so a payment step can be added in front of it without touching anything else. Events have free registration only, so there is no price to snapshot yet.
- **Member IDs** come from a per-state sequence row locked with `lockForUpdate()`. The rows are created by the migration, so concurrent approvals always lock an existing row. A member ID never changes, even if the member's state is edited later. Locking is proven on SQLite by the multi-process tests; run the same tests on MySQL before production.
- **Admins are disabled, not deleted, once they have activity.** Deleting an admin is allowed only while they have no audit entries, articles or events. Logins are audited, so an admin who has signed in even once can only be disabled, which revokes all their tokens. This keeps the audit trail tied to a real admin record. An invite that was never accepted has no activity and can still be deleted, for example to fix a typo in the email.
- **Admin login, logout and password reset are audited**, with the admin as the actor. Failed logins are not written to the audit table, which would let anyone fill it.
- **Dashboards respect permissions per section.** An admin without, say, `members.view` gets a dashboard without the members section rather than a 403 for the whole page. Each section is cached for 60 seconds, and the cache is cleared after commit whenever members, events, articles or audit entries change. "This week" and "this month" are calendar periods in `Africa/Lagos`; timestamps are stored in UTC.
- **Member activity stores field names, never values,** so the feed can say "profile updated (phone, bio)" without copying personal data into another table.
- **Rich text is sanitised on save** with `symfony/html-sanitizer` and a strict allow-list (headings, lists, links, images, YouTube embeds). Every content save writes a revision, and revisions can be restored.
- **Suspended and rejected members are locked out immediately.** Suspension revokes every token in the same transaction, and login, refresh and every request with an existing token re-check membership status.
- **Waitlists stay contiguous.** When a member cancels, the first person on the waitlist is promoted in the same transaction, and everyone behind them moves up.
- **Docs examples come from the tests** rather than being written by hand, so an example response cannot drift from the real envelope without a test failing.
- **No signed URL without Cloudinary.** When Cloudinary isn't configured, signed document URLs come back as `null` and the error is reported, instead of the whole member record returning a 500.
- **The CSV exports** (members, attendees) are the only responses outside the JSON envelope. Validation and permission failures before the download starts still use it. Cells that start with `=`, `+`, `-` or `@` are escaped against formula injection.

## Not built yet (from the BRD)

Out of scope for this release:

- Payments: Paystack, Flutterwave, bank transfer, receipts, renewal billing, paid events.
- Deals and promotions.
- Resource library.
- CMS pages (home, about, footer).
- Newsletter.
- Contact forms.
- Reports and analytics.

Known gaps in the modules that are built:

- **Memberships** don't expire automatically yet. `expires_at` is set on approval, but no scheduled job moves members to `expired` or applies a grace period.
- **Events:** no reminder emails, post-event follow-ups or Zoom integration.
- **Registration:** no reCAPTCHA; the registration rate limiter is the only protection.
- **Members:** no bulk member operations, sensitive-change requests or tier change requests.
- **Audit:** no system-wide audit log viewer; each admin's own trail is available.
