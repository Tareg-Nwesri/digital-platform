# 03 · Teams: teams, tiers, features, members

SRS: FR-TEAM-01…08, FR-WS-01…04, FR-WS-10…11, B.2, B.3 · HLD: 6.3, 7.1

## 1. Tables

### tiers
| Column | Type | Notes |
| --- | --- | --- |
| name_en / name_ar | varchar(80) | |
| slug | varchar(80) | unique |
| features | json | `{ "members.max": 8, "projects.create": true, … }`, keys from `TeamFeature::all()` |
| is_default | boolean | exactly one tier is the default for new teams |
| position | smallint | |
| timestamps | | |

### teams
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| workspace_id | FK workspaces | no | unique |
| tier_id | FK tiers | no | restrictOnDelete |
| department_id | FK departments | yes | supervising department, nullOnDelete |
| name | varchar(120) | no | |
| slug | varchar(120) | no | unique |
| status | varchar(16) | no | `TeamStatus`, index |
| status_reason | varchar(500) | yes | |
| location | varchar(120) | yes | city |
| description_en / description_ar | text | yes | |
| focus_en / focus_ar | varchar(255) | yes | |
| official_url | varchar(255) | yes | http(s) only (mutator rejects other schemes) |
| is_public | boolean | no | false |
| approved_at, approved_by | timestamp / FK | yes | |
| timestamps, deleted_at | | | soft delete = archive |

Media: `logo` (single, conversions `thumb` 128, `card` 400).

### team_members
| Column | Type | Notes |
| --- | --- | --- |
| team_id | FK teams | cascade |
| user_id | FK users | cascade |
| joined_at | timestamp | |
| added_by | FK users, null | |
| | | unique(team_id, user_id); index(user_id) |

The role is **not** a column. It is a Spatie role assigned in the team's workspace. `TeamMember::role()` reads it.

### features (Pennant database store)
The standard Pennant `features` table holds the scope (`App\Domain\Teams\Models\Team|<id>`), the name and the value. **A stored value is an override.**

## 2. Enums
- **`TeamStatus`:** `Pending`, `Active`, `Suspended`, `Archived`.

  ```text
  allowedTransitions():
    Pending   → Active, Archived
    Active    → Suspended, Archived
    Suspended → Active, Archived
  ```

- **`TeamRole`** (`captain`, `coach`, `member`, `viewer`). Its `permissions()` method seeds the role:

| Permission (team scope) | captain | coach | member | viewer |
| --- | --- | --- | --- | --- |
| `team.view` | ✓ | ✓ | ✓ | ✓ |
| `team.settings` | ✓ | ✓ | | |
| `members.invite`, `members.manage` | ✓ | ✓ | | |
| `projects.edit`, `robots.edit` | ✓ | ✓ | ✓ | |
| `projects.publish` | ✓ | ✓ | | |
| `catalogue.suggest` | ✓ | ✓ | ✓ | |
| `events.apply` | ✓ | ✓ | | |
| `budget.manage` (requests, expenses, view budget) | ✓ | ✓ | | |
| `team.transfer`, `team.archive` | ✓ | | | |

Team roles are created **per team workspace** when the team is created, with these permissions. The permission names are shared; Spatie scopes the role-to-user link by `workspace_id`.

- **`TeamFeature`** constants with type and default:

| Name | Type | Fallback if missing in tier |
| --- | --- | --- |
| `members.max` | int | 8 |
| `projects.create`, `robots.create`, `parts.access` | bool | true |
| `projects.publish`, `parts.suggest`, `events.register`, `public.profile`, `budget.request` | bool | false |

## 3. Pennant feature classes (`app/Domain/Teams/Features`)
```php
final class RobotsCreate
{
    public string $name = 'robots.create';

    public function resolve(Team $team): bool
    {
        return (bool) ($team->tier->features[$this->name] ?? TeamFeature::fallback($this->name));
    }
}
```
- There is one class per feature. `MembersMax::resolve()` returns an int.
- `TeamFeatures` facade-like service:
```php
public function effective(Team $t): array;                 // name => value (with overrides)
public function tierValue(Team $t, string $name): bool|int;
public function override(Team $t, string $name, bool|int $value, User $by): void;   // Feature::for($t)->activate($name, $value)
public function clearOverride(Team $t, string $name, User $by): void;              // Feature::for($t)->forget($name)
public function overrides(Team $t): array;                 // names whose stored value differs from tier value
public function assertActive(Team $t, string $name): void; // throws FeatureUnavailable
public function onTierChanged(Tier $tier): void;           // Feature::purge for teams of this tier without overrides
```
**Override storage problem:** Pennant stores resolved values too, so a stored value can't tell an override apart from a cached default. To solve this:
- Overrides are recorded in a small `team_feature_overrides` table: `team_id`, `feature`, `value` (json), `set_by`, `timestamps`, unique (team_id, feature).
- `resolve()` checks that table first, then the tier.
- Pennant then caches the result, and `override()` and `clearOverride()` call `Feature::for($t)->forget($name)`.

## 4. Models
**`Team`** (`HasLocalizedFields`, `SoftDeletes`, `InteractsWithMedia`, `LogsActivity`, `HasStatusTransitions`, Pennant `HasFeatures`):
- **Relations:** `workspace()`, `tier()`, `department()`, `members()` (users via `team_members`), `projects()`, `robots()`, `registrations()`, `budgetAccount()` (07), `featureOverrides()`.
- **Scopes:**
  - `scopeActive()`;
  - `scopePublic()`: active, `is_public`, and the `public.profile` feature on (implemented as a join on overrides and the tier JSON, so it stays SQL-only);
  - `scopeSupervisedBy(Department)`.
- **Helpers:** `captain(): ?User`, `memberCount(): int`, `memberLimit(): int`, `isActive(): bool`.

**SQL-only public scope:**

```text
WHERE status='active' AND is_public=1 AND deleted_at IS NULL
AND COALESCE(
  (SELECT JSON_EXTRACT(value,'$') FROM team_feature_overrides o WHERE o.team_id=teams.id AND o.feature='public.profile'),
  (SELECT JSON_EXTRACT(features,'$."public.profile"') FROM tiers WHERE tiers.id=teams.tier_id)
) = true
```

## 5. Services and actions

| Action / service | Signature | Algorithm |
| --- | --- | --- |
| `CreateTeam` | `handle(TeamData $d, User $by): Team` | Transaction: create the workspace, the team (status `Active` if created by staff with `teams.approve`, else `Pending`), the 4 team roles with permissions, and the budget account (07, lazily on first use); log activity |
| `UpdateTeamProfile` | `handle(Team, TeamProfileData, User): Team` | Updates profile and logo. Setting `is_public` requires feature `public.profile` |
| `ChangeTeamStatus` | `handle(Team, TeamStatus $to, ?string $reason, User): void` | `transitionTo`; a reason is required for Suspended and Archived; notify the captain and coaches |
| `AssignTier` | `handle(Team, Tier, User): void` | Update `tier_id`, then `Feature::for($team)->forget(all)`; log the old and new tier |
| `TierService` | `create`, `update`, `delete` (refused if teams use it), `setDefault` | `update` calls `TeamFeatures::onTierChanged` |

**`TeamMemberService`:**
```php
public function join(Team $t, User $u, TeamRole $role, ?User $by): void;        // from an invitation or adding a member without login
public function addWithoutLogin(Team $t, MemberData $d, User $by): User;         // creates User(can_login=false, email null) then join
public function changeRole(Team $t, User $u, TeamRole $role, User $by): void;
public function transferCaptaincy(Team $t, User $to, User $by): void;
public function remove(Team $t, User $u, User $by): void;
public function leave(Team $t, User $u): void;
```
- **`join`:** lock the team row (`lockForUpdate`), then refuse if `memberCount() >= TeamFeatures::effective(t)['members.max']`. Then attach and assign the role in the workspace.
- **Captain rules:** `changeRole` cannot set a second captain or demote the only captain; use `transferCaptaincy` instead. It swaps the roles in one transaction: the old captain becomes coach.
- **Removal:** `remove` and `leave` refuse to remove the captain, and revoke all roles in the workspace.
- **Members without login** cannot be assigned captain or coach.

## 6. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `TeamPolicy` (console) | `viewAny` | `DepartmentAccess::can(user, 'teams.view')` |
| | `view(User, Team)` | `teams.view.all`, or `teams.view` in the team's supervising department |
| | `create`, `update`, `assignTier`, `overrideFeatures` | `teams.edit` (same department scoping) |
| | `changeStatus` | `teams.approve` |
| `TeamPolicy` (workspace, with the context set) | `viewWorkspace` | Member of the team (`team.view`) |
| | `updateSettings` | `team.settings` and the team is active |
| | `transfer`, `archive` | `team.transfer` / `team.archive` |
| `TeamMemberPolicy` | `invite`, `addWithoutLogin`, `changeRole`, `remove` | `members.manage`, the team is active, and the target is not the captain (for remove and changeRole) |
| `TierPolicy` | all | Super admin (`tiers.manage` is reserved) |

## 7. Routes

**Console** (`/admin`, middleware auth):

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET | `/teams` | `console.teams.index` | list, filters `q`, `status`, `tier`, `department` |
| POST | `/teams` | `.store` | CreateTeam + optional captain invite (`captain_email`) |
| GET | `/teams/{team:slug}` | `.show` | tabs: Overview, Members, Projects, Robots, Registrations, Features, Finance, Activity |
| PATCH | `/teams/{team}` | `.update` | profile, department |
| PATCH | `/teams/{team}/status` | `.status` | ChangeTeamStatus |
| PATCH | `/teams/{team}/tier` | `.tier` | AssignTier |
| PUT | `/teams/{team}/features` | `.features` | sync overrides `{feature: value or null}` |
| GET/POST/PATCH/DELETE | `/tiers…` | `console.tiers.*` | TierService (super admin) |

**Workspace** (`/t/{team:slug}`, middleware auth, workspace:team):

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET | `/` | `workspace.dashboard` | dashboard |
| GET | `/members` | `workspace.members.index` | members and pending invitations |
| POST | `/members/offline` | `.offline` | addWithoutLogin |
| PATCH | `/members/{user}/role` | `.role` | changeRole |
| POST | `/members/{user}/captain` | `.captain` | transferCaptaincy |
| DELETE | `/members/{user}` | `.destroy` | remove |
| POST | `/leave` | `workspace.leave` | leave |
| GET/PATCH | `/settings` | `workspace.settings.*` | UpdateTeamProfile |

## 8. Form Requests

| Request | Rules |
| --- | --- |
| `StoreTeamRequest` (console) | `name` required max 120; `slug` nullable alpha_dash unique; `tier_id` exists; `department_id` nullable exists; `location` nullable max 120; `captain_email` nullable email |
| `UpdateTeamFeaturesRequest` | `features` array; keys in `TeamFeature::all()`; each value `nullable` plus a type rule (`integer min:1 max:500` for `members.max`, otherwise boolean); null clears the override |
| `UpdateTeamSettingsRequest` | `description_en/ar` nullable max 3000; `focus_*` nullable max 255; `location` nullable; `official_url` nullable url with protocols http,https; `is_public` boolean; `logo` nullable image max 5120 |
| `AddOfflineMemberRequest` | `name_en` required; `name_ar` nullable; `birthdate` required date before today; `role` in member,viewer; `photo` nullable image |
| `ChangeMemberRoleRequest` | `role` in the TeamRole values except captain |

## 9. Pages and props

| Page | Props |
| --- | --- |
| `workspace/Dashboard.vue` | `stats: {members: {used, limit}, projects: {draft, published}, robots: {byStatus}, bomTotal: MoneyDTO, budget?: {allocated, spent, remaining}}`, `upcomingRegistrations[]`, `activity[]` (latest 10) |
| `workspace/Members/Index.vue` | `members: {id, name, role, canLogin, age?, joinedAt}[]`, `invitations[]`, `limit`, `can: {invite, manage}` |
| `workspace/Settings.vue` | `team: TeamResource`, `features: {public.profile: bool}` |
| `console/Teams/Index.vue`, `Show.vue` | `TeamListResource` (name, status, tier, department, members/limit, createdAt); in Show, `features: {name, tierValue, override, effective, type}[]` |
| `console/Tiers/Index.vue` | `tiers: {id, name, features, teamsCount, isDefault}[]` |

## 10. Tests
- `effective features use the override, else the tier, else the fallback`
- `clearing an override falls back to the tier value`
- `changing a tier updates teams without overrides only`
- `joining a full team is refused, including under concurrent invitations` (two accepts in parallel, using a lock test)
- `there is always exactly one captain; transfer swaps captain and coach`
- `members without login cannot be captain or coach`
- `suspended teams are read-only in the workspace and hidden publicly`
- `a member of team A gets 404 on team B's workspace URLs` (dataset over every workspace route)
- `public scope requires active, is_public and the public.profile feature`
- `official_url rejects javascript: and data: schemes`
