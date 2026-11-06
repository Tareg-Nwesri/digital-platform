# 04 · Work: projects, robots, bill of materials

SRS: FR-WS-05…09, FR-PUB-06 · HLD: 7.2

## 1. Tables

### projects
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| team_id | FK teams | no | cascade; index(team_id, status) |
| slug | varchar(140) | no | unique |
| title_en / title_ar | varchar(160) | no / yes | |
| excerpt_en / excerpt_ar | varchar(400) | yes | |
| body_en / body_ar | longtext | yes | sanitised HTML (see 10 → RichText) |
| status | varchar(16) | no | `ProjectStatus` |
| published_at | timestamp | yes | index |
| is_featured | boolean | no | false; set by content staff (08) |
| created_by | FK users | yes | |
| timestamps, deleted_at | | | |

Media: `cover` (single), `gallery` (multiple, maximum 20).

### robots
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| team_id | FK teams | no | index(team_id, status) |
| project_id | FK projects | yes | nullOnDelete |
| name | varchar(120) | no | |
| slug | varchar(140) | no | unique |
| description_en / description_ar | text | yes | |
| status | varchar(16) | no | `RobotStatus` |
| weight_g | int unsigned | yes | used by event category limits |
| length_mm, width_mm, height_mm | int unsigned | yes | used by event category limits |
| is_public | boolean | no | false |
| is_featured | boolean | no | false |
| timestamps, deleted_at | | | |

Media: `photos` (multiple, maximum 20; the first is the cover).

### bom_lines
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| robot_id | FK robots | no | cascade |
| part_id | FK parts | no | restrictOnDelete (parts are archived, never deleted) |
| vendor_offer_id | FK vendor_offers | yes | nullOnDelete |
| quantity | int unsigned | no | min 1 |
| notes | varchar(500) | yes | |
| position | smallint | no | |
| timestamps | | | unique(robot_id, part_id, vendor_offer_id) |

## 2. Enums
- **`ProjectStatus`:** `Draft`, `Published`, `Archived`. Transitions: Draft ⇄ Published (publishing needs the feature and permission), any → Archived, Archived → Draft.
- **`RobotStatus`:** `Design`, `Building`, `Ready`, `Retired`. Free transitions between them; it is informational only.
- **`BomLineAvailability`** (computed, not stored):
  - `Available`;
  - `PartArchived`;
  - `OfferRemoved` (the offer id was nulled after deletion);
  - `OfferOutOfStock`;
  - `NoOffer`.

## 3. Models
**`Project`** (`BelongsToTeam`, `HasLocalizedFields`, `SoftDeletes`, `InteractsWithMedia`, `LogsActivity`, `HasStatusTransitions`):
- Relations: `robots()`, `fundingRequests()` (07), `budgetAccount()` (07).
- Scopes: `scopePublished()` (status published and published_at ≤ now); `scopePublic()` (published and `team` whereHas public).

**`Robot`** (`BelongsToTeam`, `SoftDeletes`, `InteractsWithMedia`, `LogsActivity`):
- Relations: `project()`, `bomLines()` (ordered by position), `registrations()` (belongsToMany via `registration_robot`).
- Scopes: `scopePublic()` (is_public and team public).

**`BomLine`:**
- Relations: `robot()`, `part()` (withTrashed, so archived parts still resolve), `offer()`.
- Computed values: `availability()`, `unitPrice(): ?Money`, `lineTotal(): ?Money`.

## 4. Services and actions

| Action | Signature | Notes |
| --- | --- | --- |
| `CreateProject` / `UpdateProject` | `handle(Team, ProjectData, User): Project` | Checks `projects.create`. Body is sanitised through `HtmlSanitizer` (allow-list: p, h2, h3, ul, ol, li, strong, em, a[href], blockquote, img[src from media only]) |
| `PublishProject` | `handle(Project, User): void` | Needs feature `projects.publish` and permission `projects.publish`; sets status Published and published_at ??= now; fires `ProjectPublished` (public cache bump, 09) |
| `UnpublishProject` | `handle(Project, User): void` | Status back to Draft; cache bump |
| `CreateRobot` / `UpdateRobot` | `handle(Team, RobotData, User): Robot` | Needs `robots.create`; `project_id` must belong to the same team (`WorkspaceMismatch` otherwise) |
| `SyncBom` | `handle(Robot, array $lines, User): void` | Transaction: validate each part with `Part::visibleTo($team)`; the offer belongs to the part; upsert by (part, offer); delete missing lines; reset positions; log a diff summary |

### `BomCalculator`
```php
/** @return array{lines: list<array{line: BomLine, unit: ?Money, total: ?Money, availability: BomLineAvailability}>, totals: array<string, Money>, byVendor: list<array{vendor: Vendor, lines: list<BomLine>, total: Money}>, unpriced: int} */
public function forRobot(Robot $robot): array;
public function forTeam(Team $team): array;   // sums robots not retired; used by the dashboard
```
- **Totals are per currency** (`totals['LYD']`, `totals['USD']`). They are never converted (D-10).
- **Unpriced lines** (no offer, or offer removed) count towards `unpriced` and are left out of the totals.
- **Arithmetic** uses brick/money `multipliedBy($qty)` and `plus()`, with no floats.

## 5. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `ProjectPolicy` | `viewAny`, `view` | Team member (`team.view`); console: `teams.view` for the team |
| | `create` | `projects.edit` and feature `projects.create` and the team is active |
| | `update`, `delete` | `projects.edit` and the project belongs to the context team and the team is active |
| | `publish`, `unpublish` | `projects.publish` (permission) and feature `projects.publish` |
| `RobotPolicy` | `create` | `robots.edit` and feature `robots.create` and active |
| | `update`, `delete`, `editBom` | `robots.edit` and same team and active; `delete` is refused when the robot has approved registrations |

## 6. Routes (workspace: `/t/{team:slug}`, middleware auth, workspace:team)

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET | `/projects` | `workspace.projects.index` | list, filter by status |
| GET | `/projects/create` · POST `/projects` | `.create` · `.store` | |
| GET | `/projects/{project:slug}` | `.show` | content, robots, budget panel (07) |
| GET/PATCH/DELETE | `/projects/{project}/edit` · `/projects/{project}` | `.edit` · `.update` · `.destroy` | |
| POST · DELETE | `/projects/{project}/publish` | `.publish` · `.unpublish` | |
| GET | `/robots` | `workspace.robots.index` | |
| GET/POST | `/robots/create` · `/robots` | `.create` · `.store` | |
| GET | `/robots/{robot:slug}` | `.show` | specs, photos, BOM with totals, registrations |
| GET/PATCH/DELETE | `/robots/{robot}/edit` · `/robots/{robot}` | | |
| PUT | `/robots/{robot}/bom` | `workspace.robots.bom` | SyncBom |
| GET | `/robots/{robot}/shopping-list` | `.shopping-list` | printable list grouped by vendor |

Media uploads use `POST /t/{team}/media/{model}/{id}/{collection}` (shared `MediaController`, 10) with the model's policy `update`.

## 7. Form Requests

| Request | Rules |
| --- | --- |
| `StoreProjectRequest` | `title_en` required max 160; `title_ar` nullable; `excerpt_*` nullable max 400; `body_*` nullable string max 100000; `robot_ids` array, each exists in robots where team_id = context team |
| `StoreRobotRequest` | `name` required max 120; `description_*` nullable max 3000; `status` enum; `project_id` nullable, exists for the team; `weight_g`, `length_mm`, `width_mm`, `height_mm` nullable integer min 1 max 1000000; `is_public` boolean |
| `SyncBomRequest` | `lines` array max 300; `lines.*.part_id` required exists parts; `lines.*.vendor_offer_id` nullable exists vendor_offers; `lines.*.quantity` required integer 1..10000; `lines.*.notes` nullable max 500 |

## 8. Pages and props

| Page | Props |
| --- | --- |
| `workspace/Projects/Index.vue` | paginated `ProjectListResource` (title, status, robotsCount, publishedAt, coverUrl); `can.create`, `features.projects.publish` |
| `workspace/Projects/Edit.vue` | `project`, `teamRobots: {id, name}[]` |
| `workspace/Robots/Show.vue` | `robot: RobotResource`, `bom: {lines: BomLineResource[], totals: MoneyDTO[], unpriced}`, `registrations[]`, `can.editBom` |
| `workspace/Robots/BomEditor.vue` (component) | Search parts with `GET /t/{team}/catalogue/parts/search?q=` (05); choose an offer; quantity; drag to reorder; save sends `PUT bom` |
| `workspace/Robots/ShoppingList.vue` | `byVendor[]` with vendor contacts and totals; print stylesheet |

`MoneyDTO` = `{ amountMinor: number, currency: 'LYD'|'USD'|'EUR', formatted: string }`, formatted on the server (D-11).

## 9. Events
- `ProjectPublished` and `ProjectUnpublished`: listener `BumpPublicCacheVersion('work')` (09).
- `RobotUpdated` (when public): same listener.

## 10. Tests
- `publishing needs both the projects.publish feature and the captain/coach permission`
- `members can edit projects but not publish them`
- `a robot cannot be linked to another team's project`
- `bom totals are per currency and ignore unpriced lines`
- `archived parts stay on the bom flagged as unavailable`
- `teams cannot add another team's suggested parts to a bom`
- `the shopping list groups lines by vendor with correct totals`
- `robots with approved registrations cannot be deleted`
- `project body html is sanitised` (script, onerror and javascript: href are removed)
