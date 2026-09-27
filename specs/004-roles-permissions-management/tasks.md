---

description: "Task list for Roles & Permissions Management"
---

# Tasks: Roles & Permissions Management

**Input**: Design documents from `/specs/004-roles-permissions-management/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/)

**Tests**: Test tasks ARE included — [plan.md](./plan.md) names the test files as part of the feature's
source structure, [contracts/roles-admin-ui.md](./contracts/roles-admin-ui.md) specifies a test
surface, and the repo gates every push on `make test`. Tests precede the implementation they cover
within each phase. This is also access-control code: an untested refusal is an unenforced refusal.

**Organization**: Grouped by user story. Note the dependency chain is real here — US3 and US4 both
build on US2, because there is nothing to adjust or audit until a custom role can be created.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: No logical dependency on incomplete work (not a licence for two writers on one file)
- **[Story]**: US1 / US2 / US3 / US4, mapping to the spec's user stories
- Every task names its exact file path

## Path Conventions

Monorepo web app per plan.md: `backend/` (Laravel API), `frontend/` (Vue SPA). All paths repo-relative.

## One migration, no new tables

Spatie's `roles`, `permissions`, `role_has_permissions` and `model_has_roles` already exist. This
feature adds display/classification columns to two of them and swaps the model classes. Do not create
new tables and do not touch how any existing permission is enforced.

---

## Phase 1: Setup (Baseline)

**Purpose**: A known-good starting point so any later failure is attributable.

- [X] T001 Record the baseline by running `make test` and `make lint` from the repo root; note the Pest and Vitest counts (expected: 329 Pest / 356 Vitest green, plus 4 pre-existing Larastan failures in `backend/app/Http/Controllers/Api/V1/HomeController.php` that are unrelated to this feature and must not be "fixed" here) — confirmed exactly as expected
- [X] T002 [P] Confirm the starting access-control shape: `backend/database/seeders/RolesAndPermissionsSeeder.php` defines 9 roles and 19 permissions, and `config/permission.php` currently points `models.role`/`models.permission` at the Spatie package classes — confirmed

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The data shape, the model swap, the new permission and the seeder stamping. Every user
story depends on all of it — US1 cannot display a built-in badge that nothing sets, and no endpoint
can be gated by a permission that does not exist.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 Create the migration `backend/database/migrations/..._add_display_fields_to_roles_and_permissions.php` adding to `roles`: `name_ar` string(80) nullable, `name_en` string(80) nullable, `description_ar` string(255) nullable, `description_en` string(255) nullable, `is_built_in` boolean default `false`; and to `permissions`: `label_ar` string(160) nullable, `label_en` string(160) nullable, `group` string(40) nullable. Do not alter `roles.name`, which stays the immutable identifier (data-model I1)
- [X] T004 Create `backend/app/Models/Role.php` extending `Spatie\Permission\Models\Role`, using the project's `LogsChanges` concern, casting `is_built_in` to boolean, and declaring `activityFieldLabels()` for the bilingual audit feed (`name_ar`, `name_en`, `description_ar`, `description_en`, `is_built_in`) — also carries `@property` docblocks (Larastan can't see the new columns because Spatie's base class sets `$table` dynamically in its constructor, not as a static property, so static reflection can't map the model to its schema) and an overridden `users()` (see T012's note — required to fix a real bug, not originally scoped to this task)
- [X] T005 [P] Create `backend/app/Models/Permission.php` extending `Spatie\Permission\Models\Permission` (no `LogsChanges` — the catalogue is code-defined and never edited at runtime, per FR-022)
- [X] T006 Point `config/permission.php` `models.role` at `App\Models\Role::class` and `models.permission` at `App\Models\Permission::class`, replacing the Spatie imports. This is Spatie's documented extension point; every existing `hasRole()`, `can:` middleware and `syncRoles()` call site keeps working because the package resolves models through this config
- [X] T007 Register the new `roles.manage` permission in `backend/database/seeders/RolesAndPermissionsSeeder.php` and grant it to `admin` (it reaches `superadmin` automatically via the existing `syncPermissions(all)` call)
- [X] T008 In the same seeder, stamp `is_built_in = true` plus `name_ar`/`name_en`/`description_ar`/`description_en` on each of the nine roles, sourcing the display names from the existing `users.roles.*` translations in `frontend/src/i18n/locales/{ar,en}/users.json` so nothing visibly changes for them
- [X] T009 In the same seeder, set `label_ar`/`label_en` and `group` on all 20 permissions (19 existing + `roles.manage`), grouping them as artists, artworks, holders, archive, review, administration — these are the labels FR-003 requires the interface to show instead of raw identifiers
- [X] T010 Add a `Route::middleware('can:roles.manage')` group to `backend/routes/api.php` alongside the existing `can:users.manage` group, with `{role}` constrained by `whereNumber('role')` per D19 (routes themselves are added per story) — also added `GET admin/roles` outside that group (both permissions can read it, per the contract's dual-audience note); routes reference controllers that don't exist until T017+, which is safe (`::class` is a compile-time string, and Laravel only resolves the class at registration-time invokability checks, not at file-parse time) but the app does not boot until they exist, so T017–T021/T036–T039/T055–T056 were built immediately after, out of strict phase order, to keep the app runnable
- [X] T011 Extend `backend/tests/Feature/SeederTest.php` (`it('seeds all roles and the activity view permission')`) to assert every one of the nine roles has `is_built_in = true`, non-null bilingual names, and that `roles.manage` exists and is held by `admin` and `superadmin`
- [X] T012 Run `php artisan migrate` and the full backend suite to confirm the model swap broke nothing — every existing `hasRole`/`can:`/`syncRoles` path must still pass untouched (this is the riskiest task in the phase; a failure here means the config swap is wrong, not that a test is wrong) — **the model swap itself was clean (350/350 on the pre-existing suite once reached), but building on it surfaced a real, non-obvious bug**: Laravel's `auth:sanctum` middleware calls `Auth::shouldUse('sanctum')`, which mutates `config('auth.defaults.guard')` to `'sanctum'` for the rest of the request. Spatie's `Role`/`Permission` guard-name auto-detection falls back to that same config value whenever it can't otherwise identify a guard — which happens for any *freshly constructed* Role during an authenticated request (the query builder's template model for `withCount`, and the static `Role::create()` path). The practical fallout: (1) `Role::query()->withCount(['users', ...])` crashed, because `Role::users()`'s `morphedByMany()` call resolved a null model class; (2) worse, `Role::create([...])` without an explicit `guard_name` silently saved `guard_name = 'sanctum'` on every custom role created through the API — invisible to every `hasRole()`/`can()` check in the app, all of which check guard `web`. Root-caused via bisection (isolated reproductions of `withCount` alone, `actingAs` alone, and the full request all behaved differently) rather than guessed at. Fixed in two places: `Role::users()` overridden in `app/Models/Role.php` to hardcode `User::class` instead of resolving it from guard name (this app has exactly one user-provider model, so the lookup was never necessary); `AdminRoleStoreController` explicitly passes `'guard_name' => 'web'` rather than relying on the default. Confirmed the fix by tracing that every other guard-resolution call site in this feature's code operates on an *already-persisted* model or a `User` instance, both of which resolve 'web' correctly regardless of the mutated default (documented inline in `Role::users()`'s docblock)

**Checkpoint**: Data shape in place, models swapped, `roles.manage` exists, built-ins stamped, whole
existing suite still green.

**Verified**: full backend suite 350/350 (329 baseline + 21 new `RolesManagementTest` — written across
this phase and the next two since the app couldn't boot with routes referencing non-existent
controllers), Pint clean, Larastan shows only the same 4 pre-existing `HomeController.php` failures
from the T001 baseline.

---

## Phase 3: User Story 1 — See what each role can actually do (Priority: P1)

**Goal**: An administrator can see every role, how many people hold it, and exactly which permissions
it grants — grouped and readable.

**Independent test**: Sign in as an administrator, open the roles screen, and confirm each of the nine
roles lists its permissions and user count, verifiable against the seeded configuration, with nothing
editable.

### Tests for US1

- [X] T013 [P] [US1] Create `backend/tests/Feature/RolesManagementTest.php` with a test that `GET /api/v1/admin/roles` returns all nine roles with `display_name` (ar/en), `description`, `is_built_in: true`, `users_count` and `permissions_count`, per [contracts/roles-api.md](./contracts/roles-api.md)
- [X] T014 [P] [US1] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting `GET /api/v1/admin/roles/{role}` returns one role with its full `permissions` array and an `updated_at` for later concurrency use
- [X] T015 [P] [US1] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting `GET /api/v1/admin/permissions` returns the catalogue grouped by `group`, each permission carrying a bilingual `label`, plus `meta.grantable` listing only the permissions the calling administrator holds — no built-in role holds `roles.manage` without also holding every other permission (`admin`) or holding neither (`reviewer`), so this test builds a narrow custom role directly via Eloquent to exercise the partial-grantable case
- [X] T016 [P] [US1] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting all three read endpoints are refused (403) for a user without `roles.manage` — a `reader` and an `editor` (FR-004)

### Implementation for US1

- [X] T017 [P] [US1] Create `backend/app/Http/Resources/RoleResource.php` serialising `id`, `name`, `display_name` as `{ar, en}`, `description` as `{ar, en}`, `is_built_in`, `users_count`, `permissions_count`, and (on the detail shape) `permissions` and `updated_at` — caught by Larastan: `whenCounted()` is a `JsonResource` method, not a model method; first draft called it on the model instance instead of `$this`
- [X] T018 [P] [US1] Create `backend/app/Http/Resources/PermissionResource.php` serialising `name` and `label` as `{ar, en}`
- [X] T019 [US1] Create `backend/app/Http/Controllers/Api/V1/AdminRoleIndexController.php` returning all roles with `users_count` derived via `withCount('users')`, ordered built-ins first then custom (contracts: built-ins are what people look for today) — this is the controller that surfaced the Sanctum/Spatie guard-name bug documented at T012
- [X] T020 [US1] Create `backend/app/Http/Controllers/Api/V1/AdminRoleShowController.php` returning one role with its permission names and `updated_at`
- [X] T021 [US1] Create `backend/app/Http/Controllers/Api/V1/PermissionCatalogueController.php` returning the catalogue grouped by `group`, with `meta.grantable` computed from the calling user's own permissions (FR-024 pre-check; the server re-checks on write regardless)
- [X] T022 [US1] Register the three GET routes in the `can:roles.manage` group in `backend/routes/api.php`. Note the caveat in [contracts/roles-admin-ui.md](./contracts/roles-admin-ui.md): `GET admin/roles` must also be readable by `users.manage` holders, since the user screens need the role list — gate it on either permission — implemented as an `abort_unless` inside `AdminRoleIndexController` itself (Laravel's `can:` middleware has no built-in "either" syntax), with the route registered outside the `roles.manage` group
- [X] T023 [P] [US1] Create `frontend/src/types/role.ts` with `Role`, `RoleDetail`, `Permission` and `PermissionGroup` types matching the contract
- [X] T024 [P] [US1] Create `frontend/src/api/roles.ts` with `listRoles()`, `getRole(id)` and `getPermissionCatalogue()` — also added `createRole`/`updateRole`/`deleteRole` in the same pass (T043/T058), since splitting one small file across three task numbers added nothing
- [X] T025 [US1] Create `frontend/src/pages/admin/RolesPage.vue` — the roles list per [contracts/roles-admin-ui.md](./contracts/roles-admin-ui.md): display name, description, `users_count` (showing `0`, never blank), `permissions_count`, and an unmistakable built-in badge with the reason stated on the list itself, not behind a click. In-page 403 `ErrorState` for a caller without permission, matching `UsersPage.vue`. Logical Tailwind properties only (D12) — backed by a new `composables/useRoles.ts` (fetch-once list with loading/error/retry), used here and by T046/T047
- [X] T026 [US1] Add the `/:locale/admin/roles` route to `frontend/src/router/index.ts` and a permission-filtered entry to the header's Workspace dropdown so a user without `roles.manage` never sees it — added all three role routes (list/new/edit) together since `RoleEditPage.vue` needed them immediately after; added `nav.roles` translation key (not originally scoped) since the Workspace entry needs its own label
- [X] T027 [P] [US1] Create `frontend/src/i18n/locales/en/roles.json` and `frontend/src/i18n/locales/ar/roles.json` with the screen copy and the built-in explanation; register the namespace wherever the other locale files are registered — written with the full key set needed by both `RolesPage.vue` and `RoleEditPage.vue` in one pass rather than incrementally; dropped the vue-i18n pluralization-pipe syntax after checking that nothing else in this codebase uses it, in favor of the plain `{count}` phrasing already established
- [X] T028 [P] [US1] Create `frontend/src/pages/admin/RolesPage.spec.ts` asserting the built-in badge renders, counts render (including `0`), and a caller without `roles.manage` gets the 403 state — 5 tests, also covers the custom-role badge and the "add role" link

**Checkpoint**: US1 independently shippable — full visibility, zero ability to change anything, zero risk.

---

## Phase 4: User Story 2 — Create a role for a new kind of staff member (Priority: P1)

**Goal**: An administrator creates a custom role with a bilingual name and a set of permissions,
assigns it to a user, and deletes it again when unused — no developer, no deploy.

**Independent test**: Create a role with two permissions, assign it to a test user, confirm that user
can do exactly those two things and nothing more, then retire the role and confirm it can no longer be
assigned.

**Why this is P1 and US3 is not**: built-in roles are code-defined (FR-023), so creating a custom role
is the *only* path by which an administrator changes what is possible — and it is the prerequisite for
US3, since there is nothing to adjust until a custom role exists.

### Tests for US2

- [X] T029 [P] [US2] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting `POST /api/v1/admin/roles` creates a custom role with `is_built_in = false`, a slug `name` derived from `name_en`, and exactly the permissions requested
- [X] T030 [P] [US2] Add a test asserting creation is rejected 422 when `name_ar`/`name_en` are missing or exceed 80 characters, when the derived slug collides with an existing role (FR-016), or when a requested permission is not in the catalogue (invariant I5)
- [X] T031 [P] [US2] Add a test asserting an administrator cannot create or edit a role granting a permission they do not themselves hold — 422, no role created (FR-024, invariant I3) — uses a purpose-built role holding `roles.manage` but not `artworks.manage`, since among built-ins only `admin`/`superadmin` hold `roles.manage` and both already hold every other permission
- [X] T032 [P] [US2] Add a test asserting every write against a **built-in** role is refused **409** — permission change, rename, and delete — for both `admin` and `superadmin` (FR-005, FR-015, invariant I2)
- [X] T033 [P] [US2] Add a test asserting `DELETE /api/v1/admin/roles/{role}` succeeds (200) for a custom role held by nobody, and is refused 409 naming `users_count` when users hold it (FR-014)
- [X] T034 [P] [US2] Add a test asserting a deletion that would leave zero **active** users holding `roles.manage` is refused (FR-010, invariant I4) — the active qualifier matters, since a deactivated superadmin is not a way back in
- [X] T035 [P] [US2] Add a test asserting a newly created custom role is accepted by `PATCH /api/v1/admin/users/{user}` as a role assignment, since `Rule::exists('roles','name')` admits it without change (FR-017)

### Implementation for US2

- [X] T036 [US2] Create `backend/app/Support/Access/RoleGuard.php` implementing all four rules from [research.md](./research.md) R3: (1) reject any write to a role with `is_built_in = true`; (2) reject granting a permission the actor does not hold; (3) reject removing `roles.manage` from the actor's own role; (4) reject any change or deletion leaving zero **active** users with `roles.manage`. Keyed off `is_built_in`, never a hardcoded name list, so protection cannot drift from the seeder
- [X] T037 [US2] Create `backend/app/Http/Requests/Role/StoreRoleRequest.php` validating `name_ar` and `name_en` required max 80, `description_ar`/`description_en` nullable max 255, `permissions` array of names each `exists:permissions,name`; derive the slug `name` from `name_en` and enforce uniqueness against `roles.name`. Do not accept `name` or `is_built_in` from the client (invariant I1, data-model) — added `app/Support/Access/RoleSlugGenerator.php` alongside it, mirroring `ArtistSlugGenerator`'s pattern but *without* its silent numeric-suffix disambiguation: FR-016 requires a name collision be reported, not accepted as a different-looking duplicate
- [X] T038 [US2] Create `backend/app/Http/Controllers/Api/V1/AdminRoleStoreController.php` — consult `RoleGuard` for the escalation rule, create the role with `is_built_in = false`, sync the requested permissions, return 201 in the detail shape — this is the controller that needed the explicit `guard_name => 'web'` fix documented at T012
- [X] T039 [US2] Create `backend/app/Http/Controllers/Api/V1/AdminRoleDestroyController.php` — consult `RoleGuard` (built-in → 409, users hold it → 409 with `users_count`, last-administrator → 422), then delete
- [X] T040 [US2] Register `POST admin/roles` and `DELETE admin/roles/{role}` in the `can:roles.manage` group in `backend/routes/api.php`
- [X] T041 [US2] Change `backend/app/Http/Controllers/Api/V1/StaffOptionsController.php` from `->role(['editor','admin','superadmin','reviewer'])` to a permission-based query. Without this a custom role's holders never appear in any staff picker, so "create a role for a new kind of staff member" produces staff who cannot be assigned anything ([research.md](./research.md) R5). Update the class docblock, which currently describes the role-name behaviour — implemented as an explicit allowlist of 16 "staff" permissions (content-management + review-queue + administration) rather than "holds any permission at all", since the naive version would incorrectly include `contributor` (holds only `proposals.submit`); also rejected matching the *literal* union of the four original roles' permissions, since `superadmin` was one of the four and holds everything, making that union trivially "every permission"
- [X] T042 [P] [US2] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting a user holding a custom role with `artworks.manage` appears in `GET /api/v1/staff-options`
- [X] T043 [US2] Add `createRole()` and `deleteRole()` to `frontend/src/api/roles.ts` — done as part of T024
- [X] T044 [US2] Create `frontend/src/pages/admin/RoleEditPage.vue` in **create mode** per [contracts/roles-admin-ui.md](./contracts/roles-admin-ui.md): bilingual name and description fields, permissions grouped by `group` showing bilingual labels (never raw identifiers), permissions outside `meta.grantable` visible but disabled with the reason (never hidden — hiding them makes a role's true permission set unreadable), and distinct messages for each refusal rather than a generic error — built together with edit mode (T059) and the built-in read-only state (data-driven, not a separate template), since a create/edit fork this small didn't warrant two passes; `ApiError.fromHttp` only populates `fieldErrors` for HTTP 422, so the 409 concurrency-conflict path is distinguished by checking `fieldErrors.updated_at` / the message text rather than status code alone
- [X] T045 [US2] Add the `/:locale/admin/roles/new` and `/:locale/admin/roles/:id(\d+)` routes to `frontend/src/router/index.ts`, and wire "New role" on `RolesPage.vue` (offered only to a caller who may create) — done as part of T026
- [X] T046 [US2] Make the role list data-driven in `frontend/src/pages/admin/UserEditPage.vue`: populate the role `<select>` from `listRoles()` and render each option from `display_name`, replacing `t('users.roles.…')` (FR-017, FR-018)
- [X] T047 [US2] Do the same for the role filter in `frontend/src/pages/admin/UsersPage.vue` — also fixed a **third** hardcoded-role-name lookup not named in the original task: the role *badges* in each user row used the same `t('users.roles.…')` pattern, which T048's key removal would have broken; added a `roleLabel()` helper resolving a role name against the fetched list
- [X] T048 [US2] Delete the `ADMIN_ROLES` literal and `AdminRole` type from `frontend/src/types/user.ts`, and remove the now-unused `roles.*` block from `frontend/src/i18n/locales/{ar,en}/users.json`. Leaving either in place lets the two lists drift again, which is the bug this feature exists to remove ([research.md](./research.md) R5) — confirmed via repo-wide grep that nothing else referenced either before deleting
- [X] T049 [P] [US2] Create `frontend/src/pages/admin/RoleEditPage.spec.ts` asserting permissions render grouped with labels, non-grantable permissions are visible but disabled, and a built-in role renders fully read-only with no save action — 11 tests, also covers create, the affected-count confirmation gate, the stale-conflict vs. escalation message distinction, and delete/delete-blocked
- [X] T050 [P] [US2] Update `frontend/src/pages/admin/UserEditPage.spec.ts` and `UsersPage.spec.ts` for the API-driven role list, including a custom role appearing in the dropdown with its `display_name` — added a `listRoles` mock to both; all 23 pre-existing tests across the two files still pass unmodified in substance (only the role-source mock changed)

**Checkpoint**: US2 independently shippable — an administrator can create a working custom role,
assign it, and its holders are assignable to records.

**Verified**: full backend suite 350/350, full frontend suite 372/372 (356 baseline + 5 `RolesPage`
+ 11 `RoleEditPage`), `npm run typecheck` and `npm run lint` clean, i18n parity green, `AppLayout`
tests unaffected by the new Workspace entry.

---

## Phase 5: User Story 3 — Adjust a custom role as needs change (Priority: P2)

**Goal**: An administrator changes a custom role's permissions or name, sees how many people it
affects before confirming, and the change reaches those people without a redeploy or a re-login.

**Independent test**: Grant a permission to a custom role, confirm a holder can then reach the
corresponding screen, revoke it, and confirm they cannot — with no deployment or seeder run between.

**Depends on US2**: there is nothing to adjust until a custom role exists.

### Tests for US3

- [X] T051 [P] [US3] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting `PATCH /api/v1/admin/roles/{role}` syncs `permissions` as a complete set (not a delta), and that holders' abilities change accordingly
- [X] T052 [P] [US3] Add a test asserting a rename changes `name_ar`/`name_en` while leaving `roles.name` untouched, and holders keep their abilities (FR-013, invariant I1)
- [X] T053 [P] [US3] Add a test asserting self-lockout is refused: an administrator whose own role is custom cannot remove `roles.manage` from it (FR-009)
- [X] T054 [P] [US3] Add a test asserting a stale `updated_at` precondition is refused **409** rather than silently overwriting a concurrent change (FR-011)

### Implementation for US3

- [X] T055 [US3] Create `backend/app/Http/Requests/Role/UpdateRoleRequest.php` — all fields optional (`name_ar`/`name_en` max 80, `description_*` max 255, `permissions` array of catalogue names), plus a required-when-changing-permissions `updated_at` precondition. Reject any attempt to send `name` or `is_built_in`
- [X] T056 [US3] Create `backend/app/Http/Controllers/Api/V1/AdminRoleUpdateController.php` — consult `RoleGuard` for all four rules, compare `updated_at` and return 409 on mismatch, then sync permissions and save attributes
- [X] T057 [US3] Register `PATCH admin/roles/{role}` in the `can:roles.manage` group in `backend/routes/api.php`
- [X] T058 [US3] Add `updateRole()` to `frontend/src/api/roles.ts`, sending the `updated_at` received on load as the concurrency precondition
- [X] T059 [US3] Extend `frontend/src/pages/admin/RoleEditPage.vue` to **edit mode**: load the role, require a confirmation naming the affected user count **before** saving a permission change (FR-006 — before, not a toast after), and on 409 tell the user the role changed underneath them and offer to reload rather than overwriting (FR-011)
- [X] T060 [US3] Refetch the signed-in user on navigation into admin routes in `frontend/src/stores/auth.ts` / `frontend/src/router/index.ts`. Today `can()` reads a snapshot fetched once behind `if (!auth.initialized)`, so an affected user keeps being offered abilities the server now refuses for the whole SPA session ([research.md](./research.md) R4, FR-008). Implementation note: while wiring this up, found and fixed a real bug it would have introduced — refetching on every permission-gated navigation meant a transient network error would silently log an established session out; `stores/auth.ts#fetchUser` now only clears the user on a genuine 401 or on the *initial* bootstrap failure, and just logs a refetch failure once already `initialized`, keeping the last-known permissions in place
- [X] T061 [P] [US3] Add tests to `frontend/src/pages/admin/RoleEditPage.spec.ts` for the affected-count confirmation, each distinct refusal message, and the 409 reload offer
- [X] T062 [P] [US3] Add a test to `frontend/src/stores/auth.spec.ts` asserting `can()` reflects refetched permissions after a change. Also added: a store-level test that a refetch failure post-bootstrap preserves the session (not a silent logout), and two router-level integration tests in `frontend/src/router/router.spec.ts` asserting `fetchUser()` is actually called on navigation into a `requiresPermission` route and that a mid-session permission grant is visible on the very next admin navigation

**Checkpoint**: Custom roles are durable — adjustable, and changes reach live sessions.

---

## Phase 6: User Story 4 — Account for who changed access, and when (Priority: P2)

**Goal**: Every role created, renamed, deleted, and every permission granted or revoked, is
attributable to an administrator with a timestamp and a readable before/after.

**Independent test**: Grant a permission to a role, open the change log, and confirm an entry naming
the administrator, the role, the permission granted, and the time.

**⚠️ Sequencing note**: this is P2 and separable, but `docs/privacy-rules.md` #9 requires every
create/update/delete on a tracked model to be audited. Shipping US2/US3 to production *without* US4
means the highest-privilege actions in the platform are unlogged. Treat it as release-blocking
alongside US2/US3 even though it can be built after them.

### Tests for US4

- [X] T063 [P] [US4] Add a test to `backend/tests/Feature/RolesManagementTest.php` asserting a rename produces an activity entry with the causer and the old → new values for `name_ar`/`name_en` (this comes free from `LogsChanges`)
- [X] T064 [P] [US4] Add a test asserting a permission change produces an activity entry naming the permissions **added** and **removed** — this is the one that fails without T065, because granting a permission writes to the `role_has_permissions` pivot and changes no attribute on `roles`, so attribute diffing logs nothing ([research.md](./research.md) R2)
- [X] T065 [P] [US4] Add a test asserting role creation and deletion are both logged, and that the entries are visible only to a caller holding `activity.view` (FR-021, privacy rule 9)

### Implementation for US4

- [X] T066 [US4] In `backend/app/Http/Controllers/Api/V1/AdminRoleUpdateController.php`, write an **explicit** activity entry for permission changes listing the names added and removed, following the precedent set by `ArtistCurationUpdateController` for encrypted contact fields (D57). Do not rely on `LogsChanges` for this — it diffs attributes, and the pivot is not an attribute
- [X] T067 [US4] Confirm creation and deletion produce sensible entries via `LogsChanges` on `App\Models\Role`, and that `activityFieldLabels()` (T004) renders the bilingual field names in the existing activity feed
- [X] T068 [P] [US4] Verify the role entries render correctly in the existing change-log screen at `/{locale}/admin/activity` — no new screen, but the new subject type must display readably rather than as a bare class name. Confirmed: `ActivityResource::subjectLabel()` already falls back generically to any subject's `activitySubjectLabel()` method (the same pattern used by every other model — Artist, Artwork, etc.), and `Role` implements it, so no new frontend code was needed

**Checkpoint**: All four user stories complete, and access changes are accountable.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T069 [P] Add the seeder-idempotence regression test to `backend/tests/Feature/SeederTest.php`: create a custom role with permissions, run `RolesAndPermissionsSeeder` **and** `TeamUserSeeder` (which invokes it internally), then assert the custom role still exists, still has `is_built_in = false`, and still holds exactly its original permissions ([research.md](./research.md) R6, FR-023, SC-007). This is the test that catches a future seeder edit adding a blanket `syncPermissions` over all roles
- [X] T070 [P] Confirm no role-name-driven behaviour was missed: re-run the sweep for `hasRole(`/`->role(` across `backend/app` and confirm the only remaining name-based checks are `ArchiveAccessResolver` (intentionally out of scope per D28) and the two superadmin guards in `AdminUserInvitationController`/`AdminUserDestroyController` ([research.md](./research.md) R5). Swept clean — the only other `->role()` call is `AdminUserIndexController`'s user-list filter, which is a caller-driven query, not gating logic
- [X] T071 [P] Verify Arabic/English key parity for `frontend/src/i18n/locales/{ar,en}/roles.json` and for the removals in `users.json` by running the existing i18n parity test
- [X] T072 Run `make lint` (Pint, Larastan, ESLint, `npm run typecheck`) and fix every finding introduced by this feature — the 4 pre-existing `HomeController.php` Larastan failures from T001 are out of scope. Fixed one Pint finding this feature introduced (`fully_qualified_strict_types` in the new `SeederTest.php` test, from an inline FQN — replaced with a `use` import); everything else is clean
- [X] T073 Run `make test` and confirm the whole suite is green against the T001 baseline, with the new Pest and Vitest coverage added. 351/351 backend (Pest), 377/377 frontend (Vitest)
- [ ] T074 Walk through the nine scenarios in [quickstart.md](./quickstart.md) against `make dev`, using **two** accounts (superadmin `mbadis@hafezgallery.com` and admin `mali@hafezgallery.com`). Scenario 4 in particular needs two live browser sessions — the stale-permissions problem only shows up across them and cannot be covered by the test suite. **Not done**: this needs an actual interactive two-browser-session walkthrough, which is outside what could be driven here — every scenario's underlying assertion is covered by the automated suite (`RolesManagementTest.php` for 1/2/3/5/6/7/8/9; the new `router.spec.ts` cross-navigation tests are the closest automated proxy for scenario 4's cross-session refetch), but the actual UX pass is still owed before shipping
- [X] T075 Append a decision entry to `docs/decisions.md` recording: the built-in vs custom role split and why built-ins stay code-defined; the model-extension approach and that `roles.name` is an immutable slug; the explicit-activity-entry workaround for pivot changes; the four `RoleGuard` rules; the `StaffOptionsController` change from role-name to permission; and the documented limitation that custom roles cannot carry an archive access tier. Follow the file's own rule — amendments are appended, never silently edited

---

## Dependencies & Execution Order

### Phase order

```text
Phase 1 (Setup)
   └─> Phase 2 (Foundational — migration, model swap, roles.manage, seeder stamping)
          └─> Phase 3 (US1, P1 — read-only visibility)
                 └─> Phase 4 (US2, P1 — create/delete custom roles)
                        ├─> Phase 5 (US3, P2 — adjust custom roles)
                        └─> Phase 6 (US4, P2 — audit)
                               └─> Phase 7 (Polish)
```

### Story dependencies

Unlike a typical feature, these stories form a **chain** rather than a set of independent slices:

- **US1** depends only on Phase 2. Genuinely independent and shippable alone.
- **US2** depends on US1's resources, routes and catalogue endpoint (the create screen renders the
  grouped catalogue US1 built). Not independent of US1.
- **US3** depends on US2 — there is nothing to adjust until a custom role can exist.
- **US4** depends on US2/US3 for something to audit, but see its sequencing note: privacy rule 9 makes
  it release-blocking even though it is buildable last.

### Within-phase ordering

- **T003 → T004/T005 → T006 → T012** is strictly sequential: columns before models, models before the
  config swap, and the full-suite check after the swap.
- **T007/T008/T009 → T011**: seeder changes before the test that pins them.
- **T036 → T037/T038/T039**: `RoleGuard` must exist before the controllers that consult it.
- **T044 → T059**: `RoleEditPage.vue` create mode before edit mode; same file.
- **T046/T047 → T048**: make both screens data-driven *before* deleting `ADMIN_ROLES`, or they break.
- **T055 → T056 → T066**: request, then controller, then the audit entry inside it.

### Parallel opportunities

- **Phase 3**: T013–T016 are independent test cases (same new file — write together, one writer);
  T017/T018 (two resources), T023/T024 (types, client) and T027 (locales) are genuinely concurrent.
- **Phase 4**: T029–T035 are independent test cases; T043 and T044 touch different files.
- **Phase 7**: T069, T070, T071 touch different files and run concurrently.

> **On `[P]` and shared files**: many `[P]` tasks here append to the same new test file
> (`RolesManagementTest.php`) or the same locale pair. The marker means "no logical dependency on
> incomplete work" — it does not license two concurrent writers on one file. Where two tasks name the
> same path, serialise them.

## Implementation Strategy

### MVP (recommended first increment)

**Phase 1 + Phase 2 + Phase 3 (US1)** — read-only visibility. It ships alone, carries no risk of
breaking access control, and immediately answers the question nobody can currently answer: what can
each role actually do? It also de-risks the scariest part of the feature (the model swap, T006/T012)
before any write path exists.

### Second increment

**Phase 4 (US2)** — custom role creation, plus the three hardcoded-list removals and the
`StaffOptionsController` change. This is where the feature's value lands: onboarding a new kind of
staff member without a developer (SC-002).

### Then

**Phase 5 (US3)** and **Phase 6 (US4)**, with US4 treated as release-blocking rather than optional
(privacy rule 9), then **Phase 7**.

### Two things not to skip in Phase 7

- **T069** (seeder idempotence) is the only guard against a future seeder edit silently reverting every
  custom role — the exact failure mode the user's Q2 choice makes possible.
- **T074** (browser walkthrough) covers what the suites cannot: Scenario 4's stale-permissions
  behaviour needs two live sessions, and the built-in read-only affordances are visual.
