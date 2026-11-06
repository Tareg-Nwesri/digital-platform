# 08 · Content, inbox, settings, reports and audit

SRS: FR-CON-01…08, FR-PUB-10…11, NFR-AUD-01 · HLD: 7.5, 11

## 1. Tables

### posts
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| department_id | FK departments | no | |
| slug | varchar(140) | no | unique |
| title_en / title_ar | varchar(160) | no / yes | |
| excerpt_en / excerpt_ar | varchar(400) | yes | |
| body_en / body_ar | longtext | yes | sanitised HTML |
| status | varchar(16) | no | `PostStatus`: Draft, InReview, Scheduled, Published, Archived |
| published_at | timestamp | yes | index |
| author_id, approved_by | FK users | yes | |
| timestamps, deleted_at | | | |

Media: `cover`.

### featured_items
`subject_type`, `subject_id` (Team, Project, Robot, Event), `slot` (`home.teams`, `home.projects`, `home.robots`), `position`, `starts_at null`, `ends_at null`, `created_by`, timestamps; unique(slot, subject_type, subject_id).

### contact_messages
| Column | Type | Notes |
| --- | --- | --- |
| department_id | FK departments, null | from the `inbox.department_id` setting at the time of submission |
| name, email, subject | varchar | |
| message | text | max 5000 |
| locale | char(2) | |
| status | varchar(16) | `MessageStatus`: New, InProgress, Closed |
| assigned_to | FK users, null | |
| ip_hash | char(64) | SHA-256 of the IP with an app-key salt, for abuse checks only |
| timestamps | | |

### contact_message_notes
`contact_message_id`, `user_id`, `body text`, timestamps.

### settings
`key varchar(120) primary`, `value json`, `updated_by`, `updated_at`.

`SettingsRepository` reads from cache (forever, cleared on write) and returns defaults from `config/platform.php`:

| Key | Default | Used by |
| --- | --- | --- |
| `inbox.department_id` | null (the super admin only) | 08 |
| `events.auto_promote_waitlist` | true | 06 |
| `finance.*` | see 07 §9 | 07 |
| `public.home_sections` | `["teams","projects","robots","events","news","sponsors"]` | 09 |
| `public.contact_email_display` | null | 09 |
| `retention.months` | 24 (SRS C-09) | 11 |

## 2. Services
- **`PostService`:** `create`, `update`, `submitForReview`, `publish(Post, ?Carbon $at)` (needs `content.approve`; a future `$at` gives Scheduled), `unpublish`, `archive`.
  - Scheduled task `posts:publish-due` (every minute) publishes due posts and bumps the public cache.
- **`FeaturedService`:** `feature(slot, model, window)` only works for public models (it checks `scopePublic`); `reorder(slot, ids)`; `remove`.
- **`ContactMessageService`:** `receive(ContactData)`, which sets the department, stores the message and queues `NewContactMessageNotification` to that department's admins (or the super admin); `assign`, `setStatus`, `addNote`.
- **`SettingsRepository`:** `get(key)`, `set(key, value, User)` (super admin; the `settings.manage` permission is reserved).

## 3. Reports and audit

### Activity log configuration
- spatie/laravel-activitylog v5, with buffering on (flushed after the response).
- A custom `Activity` model adds:
  - `workspace_id` (nullable) and `impersonator_id` (nullable), indexed;
  - an index on (subject_type, subject_id) and on causer_id.
- `ActivityContextTap` fills `workspace_id` from `WorkspaceContext` and `impersonator_id` from the impersonate manager.
- Models opt in with `LogsActivity` and `getActivitylogOptions()`: `logFillable()->logOnlyDirty()->dontLogIfAttributesChangedOnly(['updated_at'])`, with the log name set to the module.
- **Never logged:** `password`, `two_factor_*` and `remember_token`.
- Pruning: `activitylog:clean --days=730` (monthly), which keeps entries with log name `finance` (NFR-FIN-03).

### Department report (`DepartmentReport` query service)
`forDepartment(Department $d, CarbonPeriod $p): array` returns:
- **events:** counts by status, registrations by status, average approval time;
- **catalogue:** suggestions received, approved and rejected;
- **content:** posts published;
- **inbox:** messages new, in progress and closed, and median time to close;
- **finance** (when finance is in the ceiling): income and spending in LYD;
- **activity:** paginated activity for subjects in this department's areas.

Exports use `?format=csv` with `CsvExporter` (a native `fputcsv` stream response). spatie/simple-excel is not required (SRS C-05).

### Super admin audit
`console/Audit/Index.vue` shows activity across the platform, filterable by causer, impersonator, workspace, log name, subject type and date range.

## 4. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `PostPolicy` | `viewAny`, `view` | `content.view` in the post's department |
| | `create`, `update`, `submit` | `content.edit` |
| | `publish`, `unpublish` | `content.approve` |
| `FeaturedPolicy` | manage | `content.edit` |
| `ContactMessagePolicy` | `viewAny`, `view` | `inbox.view` in the message's department, or the super admin |
| | `update`, `addNote`, `assign` | `inbox.edit` |
| `ReportPolicy` | `viewDepartment` | `reports.view` in that department, or admin of it |
| | `export` | `reports.edit` |
| | `viewAudit` | Super admin |
| `SettingsPolicy` | all | Super admin |

## 5. Routes (console)

| Method | URI | Name |
| --- | --- | --- |
| GET/POST/PATCH/DELETE | `/admin/posts…` | `console.posts.*` |
| POST | `/admin/posts/{post}/submit` · `/publish` · `/unpublish` | |
| GET/PUT | `/admin/featured` | `console.featured.*` (one page with the slots and drag order) |
| GET | `/admin/inbox` · `/admin/inbox/{message}` | `console.inbox.*` |
| PATCH | `/admin/inbox/{message}` (status, assignee) · POST `/notes` | |
| GET | `/admin/departments/{department}/report` | `console.reports.department` (`?from&to&format=csv`) |
| GET | `/admin/audit` | `console.audit.index` |
| GET/PUT | `/admin/settings` | `console.settings.*` |

Public contact form: `POST /{locale}/contact`, named `public.contact.store`, with middleware throttle:contact, honeypot and turnstile (see 09).

## 6. Form Requests

| Request | Rules |
| --- | --- |
| `PostRequest` | `department_id` in `departmentsWith('content.edit')`; `title_en` required max 160; `title_ar` nullable; `excerpt_*` max 400; `body_*` max 100000; `cover` image max 5120 |
| `PublishPostRequest` | `published_at` nullable date (future means Scheduled) |
| `ContactRequest` | `name` required max 120; `email` required email:rfc,dns max 190; `subject` required max 160; `message` required max 5000; `privacy` accepted; `cf-turnstile-response` turnstile; honeypot fields through middleware |
| `UpdateSettingsRequest` | Typed per key from a `SettingsSchema` map (int, bool, json shape) |

## 7. Pages
- `console/Posts/Index.vue` and `Edit.vue`: the rich text editor (10) and a schedule picker.
- `console/Featured/Index.vue`: one column per slot, a searchable picker limited to public items, drag to order.
- `console/Inbox/Index.vue` and `Show.vue`: list filtered by status and assignee; detail with notes and a "reply by e-mail" link (`mailto:`, because there is no outbound mail composer in v2).
- `console/Reports/Department.vue`: KPI tiles, two charts (registrations by status, inbox throughput), and an activity table.
- `console/Audit/Index.vue` and `console/Settings/Index.vue`.

## 8. Tests
- `scheduled posts publish at their time and bump the public cache`
- `content editors cannot publish; approvers can`
- `featured slots accept only public items`
- `contact messages go to the configured department and notify its admins`
- `inbox staff of one department cannot read another department's messages`
- `activity entries carry the workspace and the impersonator`
- `passwords and 2fa secrets never appear in activity properties`
- `activity cleanup keeps finance entries`
- `department report counts only that department's records`
