# 02 · Organisation: departments and permission delegation

SRS: FR-DEP-01…12, Appendix B.1 · HLD: 6.1, 6.2

## 1. Tables

### departments
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| workspace_id | FK workspaces | no | unique |
| name_en / name_ar | varchar(120) | no / yes | |
| slug | varchar(120) | no | unique |
| description_en / description_ar | text | yes | |
| is_public | boolean | no | false, shown on the About page |
| timestamps, deleted_at | | | soft delete = archive |

### department_members
| Column | Type | Notes |
| --- | --- | --- |
| department_id | FK departments | cascade |
| user_id | FK users | cascade |
| is_admin | boolean | default false |
| joined_at | timestamp | |
| | | unique(department_id, user_id); index(user_id) |

### department_permission_ceilings
| Column | Type | Notes |
| --- | --- | --- |
| department_id | FK departments | cascade |
| permission_id | FK permissions | cascade |
| | | primary(department_id, permission_id) |

## 2. Permission catalogue (seeded, `guard_name = web`)
Seeded by `PermissionCatalogueSeeder` from `config/permissions.php`:

```php
return [
    'areas' => [
        'teams'     => ['view', 'view.all', 'edit', 'approve'],
        'events'    => ['view', 'edit', 'approve'],
        'catalogue' => ['view', 'edit', 'approve'],
        'content'   => ['view', 'edit', 'approve'],
        'inbox'     => ['view', 'edit'],
        'finance'   => ['view', 'edit', 'approve'],
        'reports'   => ['view', 'edit'],
    ],
    'department_admin_only' => ['*.approve', 'members.manage'],
    'reserved' => ['departments.manage', 'tiers.manage', 'settings.manage', 'backups.run', 'users.impersonate'],
];
```

The seeder:
- creates every `<area>.<action>`, plus the department-admin-only permissions and the reserved ones;
- creates the global role `super-admin` (workspace null);
- creates the template role `department-admin` per department workspace when the department is created (see `CreateDepartment`).

## 3. Models
**`Department`** (`HasLocalizedFields`, `SoftDeletes`, `LogsActivity`):
- **Relations:** `workspace()`, `members()` (belongsToMany users with `is_admin`, `joined_at`), `admins()`, `ceiling()` (belongsToMany permissions), `events()`, `posts()`, `supervisedTeams()`.
- **Scopes:** `scopePublic()` (is_public and not trashed).
- **`ceilingNames(): Collection<string>`** is cached on the model instance.

## 4. Services

### `DepartmentService`
```php
public function create(DepartmentData $data, User $by): Department;      // workspace + department + 'department-admin' role in that workspace
public function update(Department $d, DepartmentData $data, User $by): Department;
public function archive(Department $d, User $by): void;                 // soft delete, revokes all member roles and permissions
public function setAdmin(Department $d, User $user, bool $isAdmin, User $by): void;
public function addMember(Department $d, User $user, User $by): void;   // used by InvitationService::accept
public function removeMember(Department $d, User $user, User $by): void;// detach + revoke all permissions in that workspace
```
**`setAdmin`**, in a transaction:
1. Update the pivot `is_admin`.
2. Assign or remove the `department-admin` role in the workspace.
3. Sync the admin role's permissions to the ceiling, plus the `.approve` permissions for areas in the ceiling, plus `members.manage`.

### `DepartmentPermissionService` (the only writer of department permissions)
```php
public function setCeiling(Department $d, array $permissionNames, User $superAdmin): void;
public function grant(Department $d, User $member, string $permission, User $admin): void;
public function revoke(Department $d, User $member, string $permission, User $admin): void;
public function syncMember(Department $d, User $member, array $permissionNames, User $admin): void;
public function assertConsistent(Department $d): array;  // returns the violations found (used by the nightly check)
```

**Rules** (each failure throws `DomainRuleViolation` with a translated message):
1. `setCeiling`:
   - the caller is a super admin;
   - every name exists, is not reserved, and is not `*.approve` or `members.manage` (those are implied for admins);
   - in a transaction:
     1. save the ceiling;
     2. `removed = old − new`;
     3. for each member, revoke the permissions in `removed`;
     4. re-sync the admin role (see `setAdmin`).
2. `grant` or `syncMember`:
   - `$admin` is an admin of `$d`;
   - `$member` belongs to `$d` and is not an admin;
   - the permission is in the ceiling;
   - the permission's action is `view`, `view.all` or `edit`.
   - Then `setPermissionsTeamId($d->workspace_id)` and `$member->givePermissionTo()`. `syncMember` uses `syncPermissions` limited to the ceiling.
3. **Implied view:** granting `<area>.edit` also grants `<area>.view`; revoking `view` also revokes `edit`.
4. Every change is logged as `department.permission.granted`, `revoked` or `ceiling.updated`, with `{department_id, member_id, permission}`.

### `DepartmentAccess` (read helper used by policies and navigation)
```php
public function can(User $u, string $permission, ?Department $d = null): bool; // with no $d, checks any department the user belongs to
public function departmentsWith(User $u, string $permission): Collection;     // e.g. which departments' events the user may edit
```
- Console screens that span departments (for example the events list) filter records to `department_id IN departmentsWith(user, 'events.view')`.
- The super admin skips the filter.

## 5. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `DepartmentPolicy` | `viewAny`, `create`, `update`, `archive` | Super admin (`departments.manage` is reserved) |
| | `view(User, Department)` | Super admin or a member of the department |
| | `manageMembers(User, Department)` | Super admin or an admin of the department |
| | `setCeiling` | Super admin |
| | `grant(User, Department, User $member)` | `manageMembers` and the member is not an admin |

## 6. Routes (all under `/admin`, middleware `auth`)

| Method | URI | Name | Controller@action | Ability |
| --- | --- | --- | --- | --- |
| GET | `/departments` | `console.departments.index` | `Console\DepartmentController@index` | viewAny |
| POST | `/departments` | `.store` | `@store` | create |
| GET | `/departments/{department:slug}` | `.show` | `@show` (overview, members, ceiling) | view |
| PATCH | `/departments/{department}` | `.update` | `@update` | update |
| DELETE | `/departments/{department}` | `.destroy` | `@destroy` (archive) | archive |
| PUT | `/departments/{department}/ceiling` | `.ceiling` | `DepartmentCeilingController@update` | setCeiling |
| PATCH | `/departments/{department}/members/{user}/admin` | `.members.admin` | `DepartmentMemberController@setAdmin` | setCeiling (super admin) |
| DELETE | `/departments/{department}/members/{user}` | `.members.destroy` | `@destroy` | manageMembers |
| PUT | `/departments/{department}/members/{user}/permissions` | `.permissions.sync` | `DepartmentPermissionController@sync` | grant; not-impersonating |

Invitations are covered in 01, under the route name `console.departments.invitations.*`.

## 7. Form Requests

| Request | Rules |
| --- | --- |
| `StoreDepartmentRequest` / `UpdateDepartmentRequest` | `name_en` required max 120; `name_ar` nullable max 120; `slug` nullable alpha_dash unique (ignore self); `description_*` nullable max 2000; `is_public` boolean |
| `UpdateCeilingRequest` | `permissions` array; `permissions.*` in the delegable catalogue (the config minus reserved and admin-only) |
| `SyncMemberPermissionsRequest` | `permissions` array; `permissions.*` string, then checked against the ceiling in the service |

## 8. Pages and props

| Page | Props |
| --- | --- |
| `console/Departments/Index.vue` | `departments: {id, slug, name, membersCount, adminsCount, isPublic}[]` |
| `console/Departments/Show.vue` | Tabs: **Overview** (counts per area, recent activity), **Members** (`{id, name, email, isAdmin, permissions: string[]}[]`), **Permissions** (a matrix of areas × actions; ceiling checkboxes for the super admin; per-member toggles for admins, limited to the ceiling), **Invitations** (pending list) |
| | `catalogue: {area: string, actions: string[]}[]`, `ceiling: string[]`, `canEditCeiling`, `canManageMembers` |

**Permission matrix UI:**
- Rows are members; columns are `area.view` and `area.edit`.
- Cells outside the ceiling are not rendered.
- Saving a row sends `PUT permissions.sync` with the full list for that member.

## 9. Jobs and scheduled tasks
- `departments:verify-permissions` runs nightly. It calls `assertConsistent` for every department and logs plus mails the super admin when any member holds a permission outside the ceiling. It does not fix anything automatically.

## 10. Tests
- `a department admin can grant view and edit inside the ceiling`
- `granting a permission outside the ceiling is refused`
- `reserved and approve permissions can never be granted to members` (dataset)
- `lowering the ceiling revokes members' permissions immediately`
- `granting edit implies view and revoking view removes edit`
- `a member with events.view of department A cannot see department B's events`
- `removing a member revokes every permission in that department`
- `archiving a department removes all its roles and permissions`
- `the nightly check reports permissions outside the ceiling`
