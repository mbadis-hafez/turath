# Phase 0 Research: Roles & Permissions Management

Every unknown in the Technical Context is resolved below. Each entry records what was chosen, why,
and what was rejected. "Current state" notes describe what the code does today, read rather than
assumed.

## R0: What already exists (baseline)

| Concern | Today |
|---|---|
| Roles | 9, created by `RolesAndPermissionsSeeder`; the list is a PHP constant |
| Permissions | 19, created by the same seeder via `Permission::findOrCreate()` |
| Enforcement | `can:<permission>` route middleware groups in `routes/api.php`; no `Gate::before` bypass |
| Assigning a role to a user | Exists — `AdminUserUpdateController` calls `syncRoles([$role])`, validated by `Rule::exists('roles','name')`; one role per user |
| Seeing a role's permissions | **Nowhere in the product.** Only readable in seeder source |
| Changing a role's permissions | **Only by editing the seeder + deploying + re-running it** |
| Caching | Spatie caches the permission map for 24h and **auto-flushes on any write through its models** |
| Audit | `spatie/laravel-activitylog` via the `LogsChanges` concern, applied per model |

Two further facts shape the whole design:

- **`superadmin` is derived, not curated.** The seeder does
  `syncPermissions(Permission::where('guard_name', …)->get())` — every permission that exists. A new
  permission reaches it automatically on the next seeder run. Hand-editing it would be overwritten.
- **`admin` currently holds all 19 permissions** (the 18-permission editor set plus `users.manage`).
  This matters for R3.

## R1: Where the new role/permission fields live

**Decision**: Extend Spatie's models rather than adding a parallel table. Create
`App\Models\Role extends Spatie\Permission\Models\Role` and
`App\Models\Permission extends Spatie\Permission\Models\Permission`, and point
`config/permission.php`'s `models.role` / `models.permission` at them (that config already exists and
currently names the package classes). Add columns to the existing `roles`/`permissions` tables via one
migration:

- `roles`: `name_ar`, `name_en` (display), `description_ar`, `description_en`, `is_built_in` (boolean)
- `permissions`: `label_ar`, `label_en`, `group` (for grouping in the UI)

`roles.name` keeps its current role as the **stable identifier** — it is what `hasRole('editor')`,
`->role([...])` and `Rule::exists('roles','name')` all match on, so it must not become a
human-editable display string.

**Rationale**:
- Swapping the model classes is Spatie's own documented extension point; every existing call site
  (`$user->hasRole()`, `can:` middleware, `syncRoles()`) keeps working untouched because the package
  resolves models through that config.
- It lets `LogsChanges` be applied to roles, which is otherwise impossible on a vendor class.
- Keeping `name` as an immutable slug separate from the display names means a rename (FR-013) is a
  display-only change and cannot break code that looks a role up by name.

**Alternatives rejected**:
- *A separate `role_meta` table joined to `roles`* — a join and a second row lifecycle for what are
  plainly attributes of a role. No benefit.
- *Making `roles.name` itself the editable display name* — renaming `editor` would break
  `hasRole('editor')`, `StaffOptionsController`'s `->role([...])`, and cause the seeder's
  `Role::findByName('editor')` to create a *second* role on its next run. This is precisely why
  FR-015 protects built-in names.
- *Storing display names in i18n files* — impossible for roles created at runtime, which is the whole
  point of the feature.

## R2: How permission changes get audited

**Current state**: `LogsChanges` wraps activitylog with `logAll()->logOnlyDirty()` — it records
changes to a model's **attributes**. Granting a permission to a role writes to the
`role_has_permissions` **pivot**, changing no attribute on `roles`. So applying `LogsChanges` to
`Role` would faithfully log renames and log **nothing at all** for the single most important action
this feature adds.

**Decision**: Two layers.
- `LogsChanges` on `Role` for attribute changes (names, descriptions) — free, consistent with every
  other model.
- The update controller writes an **explicit** activity entry for permission changes, listing the
  permissions added and removed by name.

**Rationale**:
- Directly mirrors two established decisions in this repo: **D32** ("a plain pivot wouldn't get
  audited", solved by giving `archive_item_links` a real model with `LogsChanges`) and **D57**
  (`ArtistCurationUpdateController` "writes an explicit activity entry listing
  `contact_fields_changed`" because encrypted fields are excluded from diffs). The second is the
  closer analogue and the simpler mechanism.
- Privacy rule 9 requires the change be attributable field-level; an explicit entry naming
  added/removed permissions satisfies FR-021's "reconstruct what a role could do at an earlier
  point".

**Alternatives rejected**:
- *Giving `role_has_permissions` its own auditable model* (the D32 route) — the pivot has no columns
  of its own beyond the two foreign keys, so an entry per pivot row would produce noisy,
  hard-to-read history ("row added") instead of one readable "granted X, revoked Y" per save.
- *Relying on the roles list screen as the record* — a current-state view is not an audit trail.

## R3: Preventing escalation and lockout

**Decision**: One guard class, `App\Support\Access\RoleGuard`, consulted by every write controller,
enforcing four rules:

1. **Built-in protection** — reject any permission change, rename or delete where `is_built_in` is
   true (FR-005, FR-015, FR-023).
2. **No escalation** — reject granting any permission the acting administrator does not themselves
   hold (FR-024). Also reject modifying `superadmin` (already covered by rule 1, since it is
   built-in; kept explicit because it is the case people will ask about).
3. **No self-lockout** — reject a change that removes `roles.manage` from the acting
   administrator's own role (FR-009).
4. **Last administrator** — reject any change or deletion that would leave zero active users holding
   `roles.manage` (FR-010).

**Rationale**:
- Keyed off `is_built_in`, not a hardcoded name list, so the protection cannot drift from the seeder
  the way `ADMIN_ROLES` has drifted from `RolesAndPermissionsSeeder::ROLES`.
- A single guard, consulted server-side, means the rules hold for any caller — not just the UI.
  `AdminUserUpdateController` already establishes this pattern with its `abort_if(self)` guard.
- Rule 4 must count **active** users (`is_active`), since a deactivated superadmin is not a way back
  in. `TeamUserSeeder` already reactivates the superadmin for exactly this reason.

**Worth recording**: rule 2 is currently a **no-op for `admin`**, because `admin` holds all 19
existing permissions. It only becomes load-bearing once a permission exists that `admin` lacks — most
likely `roles.manage` itself, if that is reserved to superadmin. Flagged so nobody later reports the
rule as broken because it never fires.

**Alternatives rejected**:
- *Enforcing in each controller inline* — four rules across five controllers is where inconsistency
  comes from.
- *A policy class* — policies answer "may this actor touch this record"; rules 3 and 4 depend on the
  *content* of the change and on global state (how many admins remain), which policies model poorly.

## R4: Making a change take effect for signed-in users

**Current state**: the server is already correct — permissions are checked per request, Spatie
auto-flushes its cache on write, so an affected user's very next request enforces the new set. The
**interface** is the stale part: `stores/auth.ts` exposes `can()` from `state.user.permissions`, a
snapshot fetched by `auth.fetchUser()` which the router calls **once**, guarded by
`if (!auth.initialized)`. It is never refetched for the life of the SPA session. So a user whose
permissions changed keeps seeing (and being offered) abilities they no longer have until a hard
reload — and the server then refuses them, which reads as a broken screen.

**Decision**: Refetch the signed-in user's abilities on route navigation into admin areas, so the
interface converges within one navigation (FR-008). The server stays the only real gate; this is
purely about not showing people doors that no longer open.

**Rationale**:
- Cheapest correct fix: one small endpoint already exists (`/user`) and is already the source of
  truth for `can()`.
- Matches the project's stated principle that "what the server allows and what the interface shows
  cannot disagree" — today they silently can, for the whole session.
- Bounded cost: one extra lightweight request per admin navigation, not a poll.

**Alternatives rejected**:
- *Polling on a timer* — traffic with no upper bound on staleness anyway.
- *Push/websockets* — no realtime infrastructure exists in this project; enormous overkill for an
  event that happens a few times a year.
- *Doing nothing and relying on the server* — FR-008 explicitly requires it to work without signing
  out, and "the button is there but 403s" is a worse experience than the button disappearing.

## R5: Role names drive behaviour in six places — custom roles cannot participate in all of them

**This is the most consequential finding of the research, and it limits the feature as specified.**

A grep for role-name checks found six places where behaviour keys off a role **name** rather than a
permission:

| Location | What it decides | Custom role impact |
|---|---|---|
| `ArchiveAccessResolver::userTier()` | Archive access tier from `institution` / `verified_researcher` | A custom role always falls through to `Registered` |
| `StaffOptionsController` | Who appears in staff pickers — `->role(['editor','admin','superadmin','reviewer'])` | **A custom role's holders never appear in any staff picker** |
| `AdminUserInvitationController` | Superadmins can't be invited | Fine as-is |
| `AdminUserDestroyController` | Superadmins can't be deleted | Fine as-is |
| `frontend/types/user.ts` `ADMIN_ROLES` | The assignable-role dropdowns | A custom role is **unassignable** until this is data-driven |
| `i18n .../users.json` `roles.*` | Role display names, 9 keys × 2 languages | A custom role has no key and would render a raw slug |

**Decision**, per row:
- **`ADMIN_ROLES` and the i18n role keys**: replaced by API-served bilingual names. Required — FR-017
  and FR-018 are unmeetable otherwise, and a role nobody can assign is not a feature.
- **`StaffOptionsController`**: change `->role([...])` to a permission check (the pickers exist to
  find people who can be assigned records, which is a permission question, not a role-name one). Small
  change, and without it "create a role for a new kind of staff member" (US2, P1) produces staff who
  cannot be assigned anything — which would read as a bug.
- **`ArchiveAccessResolver`**: **left alone, out of scope.** D28 deliberately mapped archive tiers onto
  those two role names, and those tiers describe *visitor* access to archive material, not staff
  capability. Custom staff roles have no business carrying one.
- The two superadmin guards: unchanged.

**Consequence to state plainly**: a custom role can carry any combination of the 19 permissions, and
(after the `StaffOptionsController` change) its holders can be assigned records. It **cannot** be
given an archive access tier. So SC-002's "onboarding a new kind of staff member with zero developer
involvement" holds for staff roles, and does **not** hold for a role that needs an archive tier —
that remains a code change. Recorded here rather than discovered after shipping.

## R6: Keeping the seeder from reverting custom roles

**Current state**: `RolesAndPermissionsSeeder` runs on deploy (via `DatabaseSeeder`) *and* is invoked
internally by `TeamUserSeeder` ("the roles must exist first, whichever way this seeder is invoked").
It calls `findOrCreate` per role/permission, re-grants a fixed set to `editor`/`admin`, and re-syncs
*all* permissions to `superadmin`.

**Decision**: The seeder keeps doing exactly that for the nine built-ins, and additionally stamps
`is_built_in = true` plus bilingual labels on them. It must never enumerate or touch roles it does not
name — which it already doesn't, since every write is addressed to a specific `findByName`. One test
pins this: create a custom role, run the seeder, assert the custom role and its permissions are
untouched and still `is_built_in = false`.

**Rationale**:
- This is the user's chosen model (spec Q2 → B): built-ins reproducible from code, custom roles
  administered. The seeder is already written in a shape that respects it; the risk is a future edit
  adding a `syncPermissions` over all roles, which the test above is there to catch.
- `is_built_in` is stamped by the seeder rather than hardcoded in app code so there is exactly one
  place that decides which roles are built in.

**Alternatives rejected**:
- *Removing the seeder's permission grants and migrating the nine roles into administered data* —
  that is option 2A, explicitly not chosen; it would also make fresh installs and test databases
  depend on data rather than code.
- *A config-file list of built-in role names* — a second source of truth alongside the seeder, which
  is the exact problem `ADMIN_ROLES` already demonstrates.
