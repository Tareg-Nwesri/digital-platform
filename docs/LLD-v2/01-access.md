# 01 · Access: users, workspaces, authentication, invitations, hub, impersonation

SRS: FR-ACC-01…15, NFR-SEC-01…03 · HLD: 6.1, 6.4–6.6

## 1. Tables

### users
| Column | Type | Null | Default / index |
| --- | --- | --- | --- |
| name_en | varchar(120) | no | |
| name_ar | varchar(120) | yes | |
| email | varchar(190) | yes | unique (MySQL allows multiple NULLs) |
| email_verified_at | timestamp | yes | |
| password | varchar(255) | yes | null for members without login |
| phone | varchar(32) | yes | |
| birthdate | date | yes | |
| locale | varchar(2) | no | `en` |
| is_active | boolean | no | true, index |
| can_login | boolean | no | true (false for members without login) |
| two_factor_secret, two_factor_recovery_codes | text | yes | Fortify |
| two_factor_confirmed_at | timestamp | yes | Fortify |
| last_login_at | timestamp | yes | |
| remember_token | varchar(100) | yes | |
| timestamps, deleted_at | | | soft delete |

Media: `avatar` (single, conversions `thumb`).

### workspaces
| Column | Type | Notes |
| --- | --- | --- |
| id | bigint | Spatie `team_foreign_key` points here |
| type | varchar(16) | `WorkspaceType` |
| timestamps | | |

### Spatie Permission tables
- Published with `teams => true` and `team_foreign_key => 'workspace_id'`.
- `roles.workspace_id` is nullable: global roles have null.
- `model_has_roles` and `model_has_permissions` include `workspace_id` in their primary keys.

### invitations
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| workspace_id | FK workspaces | no | cascade delete |
| email | varchar(190) | no | index |
| role | varchar(40) | no | `captain`, `coach`, `member`, `viewer`, `department-admin` or `department-member` |
| token_hash | char(64) | no | unique; SHA-256 of the random token |
| invited_by | FK users | yes | nullOnDelete |
| expires_at | timestamp | no | now + `config('platform.invitation_days', 7)` |
| accepted_at, revoked_at | timestamp | yes | |
| accepted_user_id | FK users | yes | |
| timestamps | | | unique(workspace_id, email) where pending, enforced in the service |

## 2. Enums
- `InvitationStatus` is derived, not stored:
  - `Pending` if accepted_at, revoked_at and expired are all unset;
  - `Accepted`, `Revoked` or `Expired` otherwise.
- `TeamRole`: `Captain`, `Coach`, `Member`, `Viewer`, with `permissions(): list<string>` (see 03).
- `DepartmentRole`: `Admin`, `Member`.

## 3. Models
- **`User`** (`Authenticatable`, `HasRoles`, `TwoFactorAuthenticatable`, `Impersonate`, `InteractsWithMedia`, `LogsActivity`, `HasLocalizedFields`, `SoftDeletes`):
  - **Relations:** `teams()` (belongsToMany via `team_members`), `departments()` (via `department_members`, pivot `is_admin`), `payouts()`.
  - **Impersonation hooks:** `canImpersonate(): bool` returns `$this->hasRole('super-admin')` (with the Spatie team id set to null); `canBeImpersonated(): bool` returns `! $this->hasRole('super-admin') && $this->is_active`.
  - **`isSuperAdmin(): bool`** is cached per request.
  - **`workspaces(): Collection<WorkspaceLink>`** lists the user's teams and departments for the hub.
- **`Workspace`:** `owner()` morph-like accessor (`Team` or `Department` by type), plus `team()` and `department()` hasOne.
- **`Invitation`:** `workspace()`, `inviter()`, `scopePending()`, `status(): InvitationStatus`.

## 4. Services and actions

### `WorkspaceContext` (singleton per request)
```php
final class WorkspaceContext
{
    public function set(Workspace $workspace, Team|Department $owner): void; // also setPermissionsTeamId($workspace->id)
    public function clear(): void;                                           // setPermissionsTeamId(null)
    public function workspace(): ?Workspace;
    public function team(): ?Team;
    public function department(): ?Department;
    public function require(): Workspace;                                    // throws WorkspaceMismatch
}
```
- Bound with `scoped()` in `AccessServiceProvider`, so queued jobs get a fresh instance.
- Jobs that need a scope call `set()` themselves.

### `InvitationService`
```php
public function invite(Workspace $ws, string $email, string $role, User $by): Invitation;
public function resend(Invitation $inv, User $by): Invitation;   // new token, new expiry
public function revoke(Invitation $inv, User $by): void;
public function findPendingByToken(string $plainToken): ?Invitation;
public function accept(Invitation $inv, ?User $existing, array $newUser = []): User;
```
**`invite`:**
1. Check the role is valid for the workspace type.
2. Revoke any pending invitation for the same workspace and e-mail.
3. Create the invitation with `token = Str::random(48)`, storing `hash('sha256', token)`.
4. Queue `InvitationNotification` with the plain token.
5. Log the activity.

**`accept`**, in a database transaction:
1. Lock the invitation, and refuse it if it is not pending.
2. Use the given `$existing` user, or create one from `$newUser` (`name_en`, `name_ar`, `password`).
3. Attach the membership (team or department) through that module's service.
4. Assign the role scoped to `workspace_id`.
5. Set `accepted_at`, `accepted_user_id`.
6. Log the activity.

Team member limits are checked by `TeamMemberService::join()`, which throws `DomainRuleViolation`.

### `AuthenticatedRedirect` (Fortify `LoginResponse` override)
- **Super admin:** `console.dashboard`.
- **Exactly one workspace:** that workspace's dashboard.
- **Otherwise:** `hub`.

### Two-factor requirement
- Middleware `EnsureTwoFactorWhenRequired`: if the user is a super admin or any department admin, **and** `two_factor_confirmed_at` is null, redirect to `account.security` with a flash.
- Allowed through regardless: `account.security.*`, `logout`, and the Fortify 2FA routes.
- `RequireRecentTwoFactor:{minutes}` checks `session('auth.2fa_confirmed_at')` and is used by finance approvals (07). A step-up prompt re-asks for the TOTP code.

### `ImpersonationGuard`
- Middleware `EnsureNotImpersonating` returns 403 when `app('impersonate')->isImpersonating()`.
- It is applied to:
  - `account.security.*`;
  - `console.departments.permissions.*`;
  - `console.finance.*.approve`, `*.post`, `*.close`;
  - `impersonate.take`.
- Listeners:
  - `LogImpersonationStarted` and `LogImpersonationEnded` (on the package events) write activity records with `{impersonator_id, impersonated_id, ip, started_at, ended_at}`;
  - an activity-log tap adds `properties.impersonator_id` while impersonating.

## 5. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `InvitationPolicy` | `create(User, Workspace)` | Team: role captain or coach (the `members.invite` permission). Department: the `members.manage` permission (department admin) |
| | `revoke`, `resend` | Same as create, on that invitation's workspace |
| `UserPolicy` | `impersonate(User $actor, User $target)` | `$actor->canImpersonate() && $target->canBeImpersonated()` |
| | `deactivate` | Super admin, and the target is not themselves |

## 6. Routes

| Method | URI | Name | Controller@action | Middleware |
| --- | --- | --- | --- | --- |
| GET | `/login` | `login` | Fortify view → `Auth/Login.vue` | guest |
| POST | `/login` | — | Fortify | guest, throttle:auth, honeypot, turnstile |
| GET/POST | `/forgot-password`, `/reset-password/{token}` | Fortify names | Fortify | guest, throttle:auth, honeypot, turnstile |
| GET/POST | `/two-factor-challenge` | Fortify | Fortify | guest |
| GET | `/invitations/{token}` | `invitations.show` | `Auth\InvitationController@show` | throttle:auth |
| POST | `/invitations/{token}` | `invitations.accept` | `Auth\InvitationController@accept` | throttle:auth, honeypot, turnstile |
| GET | `/hub` | `hub` | `HubController` | auth, 2fa.required |
| GET | `/account/profile` | `account.profile` | `Account\ProfileController@edit` | auth |
| PATCH | `/account/profile` | `account.profile.update` | `@update` | auth |
| GET | `/account/security` | `account.security` | `Account\SecurityController@edit` | auth, not-impersonating |
| PUT | `/account/password` | `account.password.update` | `@updatePassword` | auth, not-impersonating, password.confirm |
| POST | `/account/locale/{locale}` | `account.locale` | `Account\LocaleController` | (also works for guests via cookie) |
| GET | `/admin/users` | `console.users.index` | `Console\UserController@index` | auth, super-admin |
| PATCH | `/admin/users/{user}/active` | `console.users.active` | `@toggleActive` | auth, super-admin |
| POST | `/admin/users/{user}/impersonate` | `impersonate.take` | `Console\ImpersonationController@take` | auth, not-impersonating, can:impersonate,user |
| POST | `/impersonate/leave` | `impersonate.leave` | `@leave` | auth |
| POST | `/t/{team}/invitations` · `/admin/departments/{department}/invitations` | `workspace.invitations.store` · `console.departments.invitations.store` | `InvitationController@store` | auth, workspace, can:create |
| POST | `…/invitations/{invitation}/resend` · DELETE `…/{invitation}` | `.resend` · `.destroy` | | same |
| POST | `/logout` | `logout` | Fortify | auth |

Fortify `registration` is **disabled** in `config/fortify.php`, so no `/register` route exists.

## 7. Form Requests

| Request | Rules |
| --- | --- |
| `AcceptInvitationRequest` | For a new user: `name_en` required string max 120; `name_ar` nullable string max 120; `password` required, confirmed, `Password::min(10)->letters()->numbers()->uncompromised()`. For an existing user: `current_password` required when the invitation e-mail matches an existing account and the user is not logged in. Always: `cf-turnstile-response` with the turnstile rule. |
| `StoreInvitationRequest` | `email` required email:rfc max 190; `role` required in the roles valid for the route's workspace type; prohibited if that e-mail is already a member |
| `UpdateProfileRequest` | `name_en` required; `name_ar` nullable; `email` required unique (ignore self) unless `can_login` is false; `phone` nullable max 32; `birthdate` nullable date before_or_equal today; `locale` in en,ar; `avatar` nullable image mimes jpg,png,webp max 5120 |
| `UpdatePasswordRequest` | `current_password` current_password; `password` as in AcceptInvitationRequest |

## 8. Props and pages

| Page | Props |
| --- | --- |
| `Auth/Login.vue` | `status?`, `canResetPassword`, shared `honeypot`, `turnstile` |
| `Auth/Invitation.vue` | `invitation: {workspaceName, workspaceType, role, email, expiresAt}`, `existingAccount: bool`, `loggedInAsInvitee: bool` |
| `Hub.vue` | `teams: {slug, name, role, logoUrl}[]`, `departments: {slug, name, isAdmin}[]`, `isSuperAdmin` |
| `account/Profile.vue`, `account/Security.vue` | `user`, `twoFactorEnabled`, `recoveryCodes?` |
| `console/Users/Index.vue` | paginated `UserListResource` (name, email, active, memberships count, last login) with filters `q`, `active` |

## 9. Notifications and jobs
- **`InvitationNotification`** (mail, queued, locale = the inviter's choice or the workspace default): accept link `route('invitations.show', token)`, the workspace name, the role, the expiry date.
- **Scheduled `invitations:prune`** (daily): deletes invitations that expired more than 30 days ago, and revoked ones.

## 10. Tests
- `invitations are single use and expire after the configured days`
- `accepting an invitation for an existing account adds the membership without a new user`
- `registration route does not exist`
- `login redirects the super admin to the console, single-membership users to their workspace and others to the hub`
- `department admins without 2fa are redirected to security until enabled`
- `only the super admin can impersonate, and not another super admin`
- `security, permission and finance approval routes return 403 while impersonating`
- `impersonation start and end are logged with both users`
- `honeypot and turnstile reject login and invitation acceptance` (dataset: honeypot filled, too fast, missing token)
- `members without login cannot authenticate`
