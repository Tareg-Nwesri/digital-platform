# 05 · Catalogue: parts, categories, vendors, offers, suggestions

SRS: FR-CAT-01…07 · HLD: 7.3

## 1. Tables

### part_categories
| Column | Type | Notes |
| --- | --- | --- |
| parent_id | FK part_categories, null | two levels maximum (enforced in the request) |
| name_en / name_ar | varchar(120) | |
| slug | varchar(120) | unique |
| position | smallint | |
| timestamps | | |

### parts
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| category_id | FK part_categories | yes | nullOnDelete |
| name | varchar(160) | no | fulltext(name, part_number, description_en) |
| part_number | varchar(80) | yes | index |
| description_en / description_ar | text | yes | |
| specs | json | yes | `[{key, value, unit?}]` |
| status | varchar(16) | no | `CatalogueStatus`, index |
| suggested_by_team_id | FK teams | yes | nullOnDelete |
| suggested_by | FK users | yes | |
| reviewed_by, reviewed_at, review_note | | yes | |
| merged_into_id | FK parts | yes | set when a suggestion is merged |
| timestamps, deleted_at | | | soft delete = archive |

Media: `image` (single, thumb and card), `datasheet` (single PDF, public disk, because datasheets are vendor documents).

### vendors
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| name | varchar(160) | no | |
| website, email, phone | varchar | yes | |
| city | varchar(120) | yes | |
| address | varchar(255) | yes | |
| latitude / longitude | decimal(10,7) / decimal(11,7) | yes | |
| status, suggested_by_team_id, suggested_by, reviewed_*, merged_into_id | | | same as parts |
| timestamps, deleted_at | | | |

### vendor_offers
| Column | Type | Null | Notes |
| --- | --- | --- | --- |
| part_id | FK parts | no | cascade |
| vendor_id | FK vendors | no | cascade |
| price_minor | bigint | no | |
| currency | char(3) | no | LYD |
| url | varchar(500) | yes | |
| in_stock | boolean | no | true |
| checked_at | date | yes | |
| status, suggested_by_team_id, suggested_by, reviewed_* | | | |
| timestamps | | | unique(part_id, vendor_id, currency) where approved (enforced in the service) |

## 2. Enums
- **`CatalogueStatus`:** `Approved`, `Suggested`, `Rejected`, `Archived`. Transitions: Suggested → Approved / Rejected / (merged, which is Rejected with `merged_into_id` set); Approved ⇄ Archived.

## 3. Models
- **`Part`**, **`Vendor`** and **`VendorOffer`** share the `IsCatalogueItem` trait:
  - `scopeApproved()`;
  - `scopeVisibleTo(Team $t)`: approved, or suggested with `suggested_by_team_id = t.id`;
  - `scopeSuggestions()`;
  - `isSuggestionOf(Team)`.
- **`Part` relations:** `category()`, `offers()` (with vendor, approved), `bestOffer()` (lowest approved LYD price that is in stock), `bomLines()`.
- **Price cast:** `MoneyCast('price_minor', 'currency')` on `VendorOffer`.

## 4. Services

### `CatalogueService` (staff)
`createPart`, `updatePart`, `archivePart`, `createVendor`, `updateVendor`, `archiveVendor`, `upsertOffer` and `removeOffer`.
- Parts can only be archived, never deleted (FR-CAT-06), so `bom_lines` stay valid.
- Removing an offer:
  - with no BOM lines using it: delete the row;
  - otherwise: set `status=Archived` and keep the row.

### `SuggestionService`
```php
public function suggestPart(Team $t, PartData $d, User $by): Part;             // status Suggested
public function suggestVendor(Team $t, VendorData $d, User $by): Vendor;
public function suggestOffer(Team $t, OfferData $d, User $by): VendorOffer;    // part/vendor may themselves be the team's suggestions
public function approve(Model $item, ?array $edits, User $by): Model;          // apply edits, status Approved, reviewed_*
public function reject(Model $item, string $note, User $by): void;
public function merge(Part|Vendor $item, Part|Vendor $into, User $by): void;   // repoint bom_lines and offers to $into, status Rejected + merged_into_id
```
- **Approving an offer** whose part or vendor is still a suggestion also approves those suggestions in the same transaction (cascade). The review dialog lists everything that will be approved.
- **Every decision** sends `SuggestionReviewed` to the suggesting team's captain and coaches.

### `CatalogueSearch`
`search(Team|null $t, array $filters, int $perPage = 20)`:
- **Filters:**
  - `q`: fulltext in boolean mode when the term has 3 or more characters, otherwise LIKE on name and part_number;
  - `category_id` (including children);
  - `vendor_id`;
  - `price_min` and `price_max` (in LYD minor units);
  - `in_stock`.
- **Results:** eager-loads the category, the image and the approved offers with their vendor.
- **Variants:** with `$t` set, it uses `visibleTo($t)`; staff get every status, with a status filter.

## 5. Policies

| Policy | Ability | Rule |
| --- | --- | --- |
| `CataloguePolicy` (workspace) | `browse` | Team member and feature `parts.access` |
| | `suggest` | `catalogue.suggest` and feature `parts.suggest` and the team is active |
| `CataloguePolicy` (console) | `viewAny` | `catalogue.view` |
| | `manage` | `catalogue.edit` |
| | `review` (approve, reject, merge) | `catalogue.approve` |

The catalogue is global: department scoping only checks that the user holds the permission in any department.

## 6. Routes

**Workspace** (`/t/{team:slug}/catalogue`):

| Method | URI | Name |
| --- | --- | --- |
| GET | `/catalogue` | `workspace.catalogue.index` (parts search page) |
| GET | `/catalogue/parts/search` | `workspace.catalogue.search` (JSON for the BOM editor; `Accept: application/json`) |
| GET | `/catalogue/parts/{part}` | `workspace.catalogue.parts.show` (offers comparison) |
| GET | `/catalogue/vendors` · `/catalogue/vendors/{vendor}` | `workspace.catalogue.vendors.*` |
| GET | `/catalogue/suggestions` | `workspace.catalogue.suggestions` (the team's own suggestions and their status) |
| POST | `/catalogue/suggestions/parts` · `/vendors` · `/offers` | `workspace.catalogue.suggest.*` |

**Console** (`/admin/catalogue`):

| Method | URI | Name |
| --- | --- | --- |
| GET/POST/PATCH/DELETE | `/parts`, `/parts/{part}` | `console.catalogue.parts.*` |
| PUT | `/parts/{part}/offers` | `console.catalogue.parts.offers` (sync offers) |
| GET/POST/PATCH/DELETE | `/vendors…`, `/categories…` | `console.catalogue.vendors.*`, `.categories.*` |
| GET | `/suggestions` | `console.catalogue.suggestions.index` (queue filtered by type) |
| POST | `/suggestions/{type}/{id}/approve` · `/reject` · `/merge` | `console.catalogue.suggestions.*` |

## 7. Form Requests

| Request | Rules |
| --- | --- |
| `PartRequest` | `name` required max 160; `part_number` nullable max 80; `category_id` nullable exists; `description_*` nullable max 5000; `specs` nullable array max 40; `specs.*.key` required max 60; `specs.*.value` required max 120; `specs.*.unit` nullable max 20; `image` nullable image max 5120; `datasheet` nullable mimes:pdf max 10240 |
| `VendorRequest` | `name` required; `website` nullable url; `email` nullable email; `phone` nullable max 32; `city` nullable; `latitude` nullable numeric between -90,90; `longitude` nullable numeric between -180,180 |
| `OfferRequest` / `SyncOffersRequest` | `vendor_id` required exists; `price` required decimal:0,3 min 0 (converted to minor units in `OfferData`); `currency` in Currency; `url` nullable url; `in_stock` boolean; `checked_at` nullable date |
| `ReviewSuggestionRequest` | approve: optional edits using the same rules as the item request; reject: `note` required max 500; merge: `into_id` required exists, approved and the same type |

## 8. Pages and props

| Page | Props |
| --- | --- |
| `workspace/Catalogue/Index.vue` | `parts` (paginated `PartCardResource`: name, partNumber, category, imageUrl, bestOffer `MoneyDTO`, offersCount, isOwnSuggestion), `filters`, `categories` tree, `vendors` (id, name) |
| `workspace/Catalogue/PartShow.vue` | `part`, `offers: {vendor, price: MoneyDTO, inStock, url, checkedAt}[]` sorted by price, `usedInRobots: {name, slug}[]` (the team's own robots only) |
| `console/Catalogue/Suggestions.vue` | `suggestions` (paginated; type, name, team, suggestedBy, createdAt), with an approve dialog that pre-fills an edit form |

## 9. Tests
- `teams see approved items plus only their own suggestions`
- `suggesting needs the parts.suggest feature`
- `approving an offer for a suggested part also approves that part and vendor`
- `merging a suggestion repoints bom lines and offers to the target`
- `parts in use cannot be deleted, only archived`
- `search filters by category including children, vendor, price range and stock`
- `prices are stored in minor units with 3 decimals for LYD`
