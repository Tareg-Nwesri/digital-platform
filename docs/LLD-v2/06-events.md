# 06 · Events: events, categories, registrations, results

SRS: FR-EVT-01…15, FR-PUB-07…08 · HLD: 7.4 (state diagrams confirmed, D-12)

## 1. Tables

### events
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| department_id | FK departments | no | restrictOnDelete; index |
| slug | varchar(140) | no | unique |
| title_en / title_ar | varchar(160) | no / yes | |
| description_en / description_ar | text | yes | |
| rules_en / rules_ar | longtext | yes | sanitised HTML |
| terms_en / terms_ar | longtext | yes | sanitised HTML |
| venue | varchar(200) | yes | |
| city | varchar(120) | yes | |
| timezone | varchar(40) | no | `Africa/Tripoli` |
| starts_at, ends_at | datetime | no | ends ≥ starts |
| registration_opens_at, registration_closes_at | datetime | yes | closes ≤ starts |
| status | varchar(16) | no | `EventStatus`, index(status, starts_at) |
| visibility | varchar(16) | no | `EventVisibility` |
| capacity | smallint unsigned | yes | maximum approved registrations across categories; null = unlimited |
| results_published_at | timestamp | yes | |
| created_by | FK users | yes | |
| timestamps, deleted_at | | | |

Media: `banner` (single), `gallery` (multiple).

### event_categories
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| event_id | FK events | no | cascade |
| name_en / name_ar | varchar(120) | no / yes | |
| age_min, age_max | tinyint unsigned | yes | |
| members_min, members_max | tinyint unsigned | no | 1 / 10 |
| robots_per_team | tinyint unsigned | no | 1 |
| capacity | smallint unsigned | yes | per-category limit (optional) |
| max_weight_g | int unsigned | yes | |
| max_length_mm, max_width_mm, max_height_mm | int unsigned | yes | |
| position | smallint | no | |
| timestamps | | | |

### event_invitations
| Column | Type | Notes |
| --- | --- | --- |
| event_id | FK events | cascade |
| team_id | FK teams | cascade |
| invited_by | FK users | |
| timestamps | | unique(event_id, team_id) |

This table holds invitations for **invite-only events**, which control whether a team can see the event and apply. They are separate from organiser-added registrations in the `Invited` status.

### event_registrations
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| event_id | FK events | no | index(event_id, status) |
| category_id | FK event_categories | no | |
| team_id | FK teams | no | unique(category_id, team_id) for active statuses, enforced with the generated column below |
| source | varchar(16) | no | `RegistrationSource`: `organiser`, `applied` |
| status | varchar(16) | no | `RegistrationStatus` |
| active_key | varchar(40) as (if status in (invited,pending,approved,waitlisted) then concat(category_id,'-',team_id) else null) stored | yes | **unique**: stops duplicates while allowing re-entry after rejection or withdrawal |
| members_snapshot | json | yes | set on approval: `[{user_id, name, age, role}]` |
| applied_by, reviewed_by | FK users | yes | |
| reviewed_at | timestamp | yes | |
| decision_note | varchar(1000) | yes | reject reason or a note |
| override_reason | varchar(1000) | yes | when rule violations were overridden |
| override_violations | json | yes | the list of overridden rules |
| waitlist_position | smallint | yes | |
| withdrawn_at | timestamp | yes | |
| timestamps | | | |

### registration_robot
`registration_id` FK cascade, `robot_id` FK restrictOnDelete, primary key (both).

### registration_members (pending selection, before the snapshot)
`registration_id`, `user_id`, primary key (both). Holds the members chosen when applying or adding. The snapshot is built from these.

### event_results
| Column | Type | Notes |
| --- | --- | --- |
| registration_id | FK event_registrations | unique |
| placement | smallint unsigned null | 1 = first |
| score | decimal(10,2) null | |
| award_en / award_ar | varchar(120) null | e.g. "Best design" |
| notes | varchar(500) null | |
| timestamps | | |

## 2. Enums and transitions
- **`EventStatus`:** `Draft → Open → Closed ⇄ Open`, `Closed → Running → Finished → Archived`.
- **`EventVisibility`:** `Public`, `InviteOnly`.
- **`RegistrationStatus`:** `Invited`, `Pending`, `Approved`, `Waitlisted`, `Rejected`, `Withdrawn`. Transitions as in HLD 7.4:

```text
Invited    → Approved | Waitlisted (team accepts) | Withdrawn
Pending    → Approved | Waitlisted | Rejected | Withdrawn
Waitlisted → Approved | Withdrawn
Approved   → Withdrawn
```

The set of active statuses is `Invited`, `Pending`, `Approved` and `Waitlisted`.

## 3. Models
**`Event`** (`HasLocalizedFields`, `SoftDeletes`, `InteractsWithMedia`, `LogsActivity`, `HasStatusTransitions`):
- **Relations:** `department()`, `categories()` (ordered), `registrations()`, `invitations()`, `results()` (hasManyThrough).
- **Scopes:**
  - `scopePublic()`: either visibility is Public and status is not Draft, or visibility is InviteOnly and `results_published_at` is not null (SRS C-07);
  - `scopeVisibleToTeam(Team)`: status is not Draft, and either visibility is Public or the team is in `event_invitations`;
  - `scopeForDepartments(ids)`.
- **Helpers:** `isAcceptingApplications(now)` (status Open and inside the window), `approvedCount()`, `hasCapacity()`.

**`EventRegistration`:** relations `event()`, `category()`, `team()`, `robots()`, `members()`, `result()`. It uses `HasStatusTransitions`.

## 4. Entry rules

### `EntryRules` (pure; no database writes)
```php
/** @param list<Robot> $robots  @param list<User> $members  @return list<RuleViolation> */
public function check(Event $e, EventCategory $c, Team $t, array $robots, array $members, EntryContext $ctx): array;
```
`EntryContext` carries `{mode: add|apply|approve, now, excludeRegistrationId}`.

| Code | Rule | Applies in |
| --- | --- | --- |
| `team_inactive` | `$t->status === Active` | all |
| `feature_off` | Feature `events.register` | apply |
| `not_visible` | Invite-only event and the team is not invited | apply |
| `window_closed` | Event status Open and `opens_at ≤ now ≤ closes_at` | apply |
| `event_closed` | Status in Open, Closed or Running (organiser actions) | add, approve |
| `duplicate` | No other active registration for (category, team) | all |
| `members_count` | `members_min ≤ count ≤ members_max` | all |
| `member_not_in_team` | Every member belongs to the team | all |
| `member_no_birthdate` | Every member has a birthdate when the category has age limits | all |
| `member_age` | Age on `starts_at` (in the event timezone) within `[age_min, age_max]` | all |
| `robots_count` | `count(robots) ≤ robots_per_team` and at least 1 | all |
| `robot_not_in_team` | Every robot belongs to the team and is not retired | all |
| `robot_weight`, `robot_size` | Within the category maximums when they are set; a robot with missing data fails | all |

Capacity is **not** an entry rule. It decides between Approved and Waitlisted (FR-EVT-08).

## 5. `RegistrationService`
```php
public function apply(Event $e, EventCategory $c, Team $t, array $robotIds, array $memberIds, User $by): EventRegistration;           // Pending
public function addByOrganiser(Event $e, EventCategory $c, Team $t, array $robotIds, array $memberIds, bool $asInvite, ?string $overrideReason, User $by): EventRegistration;
public function acceptInvite(EventRegistration $r, array $robotIds, array $memberIds, User $by): EventRegistration;
public function approve(EventRegistration $r, ?string $overrideReason, User $by): EventRegistration;
public function reject(EventRegistration $r, string $reason, User $by): void;
public function waitlist(EventRegistration $r, User $by): void;
public function withdraw(EventRegistration $r, User $by): void;
public function promoteFromWaitlist(Event $e, User $by): ?EventRegistration;   // the lowest waitlist_position that still passes the rules
```
**Common algorithm** for `approve`, `acceptInvite`, and `addByOrganiser` when not sent as an invite. It runs in a transaction and **locks the event row** (`Event::lockForUpdate()->find`):
1. `violations = EntryRules::check(...)`.
2. If there are violations:
   - with an `overrideReason` and a user holding `events.approve`: store `override_reason` and `override_violations`, then continue;
   - otherwise: throw `DomainRuleViolation(violations)`.
3. Capacity check:
   - event full: `approvedCount() >= capacity`;
   - category full: `category.approved >= category.capacity`.
   - If either is full, set status `Waitlisted` and `waitlist_position = max + 1`. Otherwise set `Approved`.
4. If Approved: build `members_snapshot` from `registration_members`, with ages computed on `starts_at`.
5. Save, log the activity, and fire `RegistrationStatusChanged`.

**Other rules:**
- `withdraw` is only allowed before `registration_closes_at` for teams; organisers can withdraw at any time. If the registration was Approved, call `promoteFromWaitlist` automatically. This is controlled by the setting `events.auto_promote_waitlist`, on by default (08). When it is off, a department admin promotes from the waitlist by hand.
- `apply` calls `EntryRules` in `apply` mode and creates the registration as `Pending`. Approval happens later.

### `EventService`
- `create`, `update` (a status change goes through `transition`), `transition(Event, EventStatus)`.
- Moving to Open requires at least one category, and `registration_closes_at` must be set or default to `starts_at`.
- `saveCategories(Event, array)` cannot remove a category that has active registrations.
- `inviteTeams(Event, teamIds)`, `uninviteTeam`.
- `recordResults(Event, array)` works only when the event is Finished.
- `publishResults(Event)` sets `results_published_at` and bumps the public cache.

## 6. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `EventPolicy` (console) | `viewAny` | `events.view` in any department |
| | `view`, `update`, `transition`, `manageCategories`, `inviteTeams`, `recordResults` | `view` needs `events.view`; the rest need `events.edit`, in the event's department |
| | `publishResults` | `events.approve` in the event's department |
| `RegistrationPolicy` (console) | `addTeam` | `events.edit` |
| | `approve`, `reject`, `waitlist`, `promote`, `override` | `events.approve` |
| | `withdraw` | `events.edit` |
| `RegistrationPolicy` (workspace) | `apply`, `acceptInvite`, `withdraw` | `events.apply` (captain or coach) and the team is active; feature `events.register` for apply |
| | `view` | Team member |

## 7. Routes

**Console** (`/admin/events`):

| Method | URI | Name |
| --- | --- | --- |
| GET / POST | `/events` | `console.events.index` / `.store` |
| GET / PATCH / DELETE | `/events/{event:slug}` | `.show` (tabs: Overview, Categories, Registrations, Invitations, Results, Activity) / `.update` / `.destroy` (draft only) |
| PATCH | `/events/{event}/status` | `.status` |
| PUT | `/events/{event}/categories` | `.categories` |
| POST / DELETE | `/events/{event}/invitations`, `/events/{event}/invitations/{team}` | `.invitations.*` |
| POST | `/events/{event}/registrations` | `.registrations.store` (organiser add) |
| POST | `/registrations/{registration}/approve` · `/reject` · `/waitlist` · `/withdraw` | `console.registrations.*` |
| POST | `/events/{event}/waitlist/promote` | `.waitlist.promote` |
| PUT | `/events/{event}/results` · POST `/events/{event}/results/publish` | `.results`, `.results.publish` |
| GET | `/events/{event}/export` | `.export` (CSV: team, category, status, members, ages, robots) |

**Workspace** (`/t/{team:slug}/events`):

| Method | URI | Name |
| --- | --- | --- |
| GET | `/events` | `workspace.events.index` (open, invited, and our registrations) |
| GET | `/events/{event:slug}` | `.show` (categories with an eligibility preview per member and robot) |
| POST | `/events/{event}/apply` | `.apply` (throttle:apply) |
| POST | `/registrations/{registration}/accept` · `/withdraw` | `workspace.registrations.*` |

## 8. Form Requests

| Request | Rules |
| --- | --- |
| `EventRequest` | `title_en` required max 160; `title_ar` nullable; `description_*`, `rules_*`, `terms_*` nullable; `venue`, `city` nullable; `starts_at` required date; `ends_at` required after_or_equal starts_at; `registration_opens_at` nullable before registration_closes_at; `registration_closes_at` nullable before_or_equal starts_at; `visibility` enum; `capacity` nullable integer 1..1000; `department_id` required in `departmentsWith(user, 'events.edit')` |
| `SaveCategoriesRequest` | `categories` array min 1 max 30; each item: `id` nullable exists; `name_en` required; `age_min`/`age_max` nullable integer 3..99 with age_max ≥ age_min; `members_min` 1..20; `members_max` ≥ members_min max 20; `robots_per_team` 1..10; `capacity` nullable; `max_*` nullable positive |
| `ApplyRequest` / `AddTeamRequest` | `category_id` required, belongs to the event; `robot_ids` array min 1, each robot of the team; `member_ids` array min 1, each a member of the team; (add) `team_id` required exists and not archived; `as_invite` boolean; `override_reason` nullable max 1000 |
| `RejectRegistrationRequest` | `reason` required max 1000 |
| `ResultsRequest` | `results` array; `results.*.registration_id` approved in this event; `placement` nullable integer 1..500; `score` nullable numeric; `award_*` nullable max 120 |

## 9. Pages and props

| Page | Props |
| --- | --- |
| `console/Events/Index.vue` | paginated `EventListResource` (title, department, dates, status, visibility, counts by status, capacity) |
| `console/Events/Show.vue` | `event`, `categories[]`, `registrations` (paginated, filterable by status and category; each with team, robots, members, violations preview, override info), `invitations[]`, `results[]`, `can.{edit, approve}` |
| `workspace/Events/Index.vue` | `open[]`, `invited[]`, `mine[]` (registration status, category, decision note) |
| `workspace/Events/Show.vue` | `event`, `categories[]` with `eligibility: {members: {id, name, age, ok, reason?}[], robots: {id, name, ok, reason?}[]}` computed by `EntryRules` per category, `myRegistrations[]`, `can.apply` |

## 10. Notifications
`RegistrationStatusChanged` sends `RegistrationStatusNotification` (mail and database) to the team's captain and coaches, with the event, category, new status and note, in each recipient's locale. Organiser additions as an invite send `EventInvitationNotification`.

## 11. Tests
- `applications outside the registration window are refused`
- `category age limits use age on the event start date in the event timezone`
- `member and robot counts must be within category limits` (dataset)
- `robots over the weight or size limit fail; robots missing data fail when limits are set`
- `a team cannot hold two active registrations in one category but can re-apply after rejection`
- `approvals beyond event or category capacity go to the waitlist in order`
- `withdrawing an approved entry promotes the first eligible waitlisted team`
- `department admins can override violations with a recorded reason; members cannot`
- `invite-only events are hidden from teams that were not invited and from the public until results are published`
- `organiser-added teams count against capacity like approved applications`
- `staff of another department cannot see or manage the event`
- `members snapshot freezes on approval`
