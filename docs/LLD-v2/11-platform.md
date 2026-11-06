# 11 · Platform: configuration, middleware, scheduler, queue, notifications, seeding, deployment, test matrix

SRS: 3.11–3.13 · HLD: 11–13 · Plan: phases 1 and 5

## 1. Packages and configuration

| Package | Config file | Settings |
| --- | --- | --- |
| spatie/laravel-permission | `config/permission.php` | `teams => true`, `team_foreign_key => 'workspace_id'`, `cache.expiration_time => 24h`; `PermissionRegistrar::$teamsKey` set by `WorkspaceContext` |
| laravel/pennant | `config/pennant.php` | `default => 'database'`; features discovered from `app/Domain/Teams/Features` (`Feature::discover`) |
| spatie/laravel-honeypot | `config/honeypot.php` | `randomize_name_field_name => true`, `amount_of_seconds => 3`, `respond_to_spam_with => BlankPageResponder`; the middleware runs on the routes listed in 01, 08 and 09 |
| njoguamos/laravel-turnstile | `config/turnstile.php` | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`, `TURNSTILE_ENABLED`. Local and testing use Cloudflare's always-pass test keys. Production refuses to boot without the keys (`AppServiceProvider` check) |
| spatie/laravel-medialibrary | `config/media-library.php` | `disk_name => env('MEDIA_DISK', 'public')`, `queue_conversions_by_default => true`, `image_driver => imagick` with a fallback to `gd`, WebP conversions, `max_file_size => 10 MB` |
| spatie/laravel-activitylog | `config/activitylog.php` | Custom `Activity` model; buffering on; `delete_records_older_than_days => 730` |
| spatie/laravel-backup | `config/backup.php` | Source: database + `storage/app/public` + `storage/app/private`. Destination: `local` (and optional `backup_remote`). Retention 7 daily, 4 weekly, 3 monthly. Notifications by mail to the super admin |
| lab404/laravel-impersonate | `config/laravel-impersonate.php` | `take_redirect_to => 'hub'`, `leave_redirect_to => 'console.users.index'` |
| brick/money | — | Used through `App\Support\Money` |
| laravel/fortify | `config/fortify.php` | Features: resetPasswords, updatePasswords, twoFactorAuthentication (confirm and confirmPassword). **No `registration`** |

`config/platform.php` holds the app-level defaults: `invitation_days`, `currencies`, and the settings defaults from 08.

## 2. Middleware stack

| Alias | Class | Purpose |
| --- | --- | --- |
| `public.locale` | `SetPublicLocale` | 09 §5 |
| (web, appended) | `SetLocale` | Uses the user's locale on app routes, the cookie otherwise; shares `locale` and `dir` with the views |
| `workspace:team` · `workspace:department` · `workspace:any` | `ResolveWorkspace` | Resolves the route-bound Team or Department, checks membership (a super admin bypasses for departments only), calls `WorkspaceContext::set`; a team non-member gets 404, a department non-member 403 |
| `team.active` | `EnsureTeamActive` | 403 on writes when the team is not active |
| `2fa.required` | `EnsureTwoFactorWhenRequired` | 01 §4 |
| `2fa.recent:{min}` | `RequireRecentTwoFactor` | Finance approvals (07) |
| `not-impersonating` | `EnsureNotImpersonating` | 01 §4 |
| `super-admin` | `EnsureSuperAdmin` | Global console areas |
| `public.cache-headers` | `PublicCacheHeaders` | 09 §4 |
| (web, appended) | `HandleInertiaRequests` | Shared props (10 §2) |
| (web, appended) | `SecurityHeaders` | CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy, and HSTS in production |
| `ProtectAgainstSpam` | spatie honeypot | Public and auth forms |

**Rate limiters** (`AppServiceProvider::boot`):
- `contact`: 3 per minute per IP;
- `auth`: 5 per minute per IP and e-mail;
- `apply`: 10 per minute per user;
- `uploads`: 30 per minute per user.

**CSP:**
- `default-src 'self'`;
- `script-src 'self' https://challenges.cloudflare.com`;
- `frame-src https://challenges.cloudflare.com`;
- `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com`;
- `font-src https://fonts.gstatic.com`;
- `img-src 'self' data: blob:`.

## 3. Scheduler (`routes/console.php`) and queue on shared hosting

| Task | Frequency | Notes |
| --- | --- | --- |
| `queue:work database --stop-when-empty --max-time=50 --tries=3 --backoff=30` | every minute, `withoutOverlapping()`, `runInBackground()` | Drains media conversions, mail and activity flushes |
| `posts:publish-due` | every minute | 08 |
| `invitations:prune` | daily 03:00 | 01 |
| `finance:ensure-periods` | daily 00:05 | 07 |
| `finance:verify` | daily 01:00 | 07 |
| `departments:verify-permissions` | daily 01:15 | 02 |
| `backup:clean` then `backup:run` | daily 02:00 | |
| `backup:monitor` | daily 06:00 | |
| `activitylog:clean` | monthly | Keeps finance entries |
| `retention:purge` | monthly | Removes contact messages and rejected registrations older than `retention.months` (24), and personal fields of users deactivated more than 24 months ago (anonymised, not deleted, if they are referenced by finance or registrations) |
| `queue:prune-failed --hours=168` | weekly | |

The host's cron entry is the only one needed:

```bash
* * * * * cd /home/<account>/app && php artisan schedule:run >> /dev/null 2>&1
```

- **Synchronous mail exceptions:** invitations and password reset are sent synchronously, so the user is not left waiting up to a minute (HLD risk table).

## 4. Notifications

| Notification | Channels | Recipients | Module |
| --- | --- | --- | --- |
| InvitationNotification | mail (sync) | invitee | 01 |
| TeamStatusChanged | mail, database | captain and coaches | 03 |
| SuggestionReviewed | database (+ mail digest) | suggester, captain and coaches | 05 |
| EventInvitationNotification, RegistrationStatusNotification | mail, database | captain and coaches | 06 |
| FundingRequestDecided, ExpenseDecided | mail, database | requester, captain and coaches | 07 |
| PayoutApproved, PayoutPaid | mail, database | payee (or the guardian contact as text only) | 07 |
| FinanceApprovalRequested | database | finance approvers | 07 |
| NewContactMessageNotification | mail, database | inbox department admins | 08 |
| SystemAlert (finance verify, permission verify, backup failure) | mail | super admin | 02, 07, 11 |

- **Locale:** every notification uses `HasLocalePreference` on `User` to send in the user's locale.
- **Notification bell:** the database notifications appear in `AppShell` through a lazy shared prop `notifications.unread`, and a `GET /account/notifications` page.

## 5. Seeders and factories
- `DatabaseSeeder` (production-safe):
  - `PermissionCatalogueSeeder` (02);
  - the `super-admin` role;
  - tiers `starter` (default) and `competition` (SRS B.2 values);
  - the finance accounts GeneralFund (LYD) and ReinvestmentPool (LYD);
  - the fiscal periods for the current and next month;
  - the settings defaults.
- `SuperAdminSeeder`: reads `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_NAME` from the environment, creates the user with a random password, and prints a password-reset link.
- `DemoSeeder` (local and staging only; refuses to run in production):
  - departments Events, Procurement, Finance and Media, each with an admin and members, and ceilings matching their names;
  - two teams (one Starter, one Competition) with members, including two without login;
  - projects and robots with BOMs;
  - 40 parts, 6 vendors and offers in LYD and USD;
  - one open event with two categories and mixed registrations;
  - a sponsor with a 60/30/remainder split and a posted receipt;
  - a funding request, an expense and payouts;
  - posts and contact messages.
- Every model has a factory with states (for example `Team::factory()->suspended()`, `Event::factory()->open()`, `EventRegistration::factory()->approved()`, `LedgerTransaction::factory()->posted()` via `LedgerService`).

## 6. Environments and deployment

| Env | Host | Notes |
| --- | --- | --- |
| local | Herd `digital-platform.test`, PHP 8.5, MySQL `digital_platform` | `MAIL_MAILER=log`, Turnstile test keys, `QUEUE_CONNECTION=database` (or `sync` while developing) |
| staging (optional) | Subdomain on the shared host | Separate database; `DemoSeeder` allowed |
| production | Shared host, PHP 8.5 | `APP_DEBUG=false`, real keys, SMTP |

**Release** (scripted as `composer release`, run locally):
1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build` (Wayfinder and i18n generated)
3. Zip without `node_modules`, `tests` or `.git`.

**Deploy:**
1. `php artisan down --retry=30`.
2. Upload and extract next to the current release, then swap the folders (keep `storage/` and `.env` outside).
3. `php artisan migrate --force`
4. `php artisan optimize` and `php artisan storage:link` (or `MEDIA_DISK=public_media` rooted at `public/media`).
5. `php artisan up`.
6. Smoke check: `/up`, `/en`, `/ar`, login.

**Rollback:**
1. Swap back to the previous folder.
2. Migrations are written to be backwards-compatible within one release. Destructive column drops wait one release.

## 7. CI (GitHub Actions, optional)
1. PHP 8.5 with a MySQL 8 service.
2. `composer install`.
3. `vendor/bin/pint --test` (CI only).
4. `php artisan test --parallel`.
5. `npm ci`, then `npm run lint`, `npm run types`, `npm run test` and `npm run build`.
6. Upload the release zip as an artefact on the main branch.

## 8. Test matrix

| Module | Unit | Feature | Isolation (A vs B) | Security |
| --- | --- | --- | --- | --- |
| 01 Access | Invitation token hashing | Login redirects, invitations, 2FA requirement | — | Honeypot, Turnstile, impersonation guards |
| 02 Organisation | DepartmentPermissionService rules | Ceiling, grants, removal | Department A vs B | Reserved permissions |
| 03 Teams | TeamFeatures resolution | Members, captaincy, status | Team A vs B (every workspace route) | Suspended read-only |
| 04 Work | BomCalculator | Publish rules, BOM sync | Cross-team project and robot | HTML sanitising |
| 05 Catalogue | Search filters | Suggest, approve, merge | Suggestion visibility | — |
| 06 Events | EntryRules (datasets) | Apply, add, approve, waitlist, withdraw | Department scoping | Invite-only visibility |
| 07 Finance | SplitCalculator, Money | Ledger post, reverse, documents | Team budgets, My earnings | Maker-checker, 2FA recent, private files |
| 08 Content | — | Posts, inbox, featured, report | Inbox per department | Secrets never logged |
| 09 Public | SeoMeta | Every page | Public scopes (no private data) | Contact protections |
| 10 Front end | Vitest composables | — | — | — |

**Coverage target:** every SRS requirement with priority M has at least one named test (tracked in `tests/REQUIREMENTS.md`, mapping requirement IDs to test names).
