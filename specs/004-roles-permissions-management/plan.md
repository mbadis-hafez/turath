# Implementation Plan: Roles & Permissions Management

**Branch**: `004-roles-permissions-management` (spec directory; work is currently uncommitted on `main`) | **Date**: 2026-09-23 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-roles-permissions-management/spec.md`

## Summary

Give administrators a screen that shows every role and exactly what it grants, and lets them create
and manage their own **custom** roles — while the nine built-in roles stay code-defined and
reproducible from the seeder.

Technical approach, in five parts:

1. **Extend Spatie's `Role` and `Permission` models into app-level models.** `config/permission.php`
   already points at the package classes; swapping those for `App\Models\Role`/`App\Models\Permission`
   (each extending the Spatie class) gives them bilingual display names, an `is_built_in` flag, and
   the project's `LogsChanges` audit trait, without touching how enforcement works.
2. **Permission grants are audited explicitly, not by attribute diffing.** Granting a permission
   changes the `role_has_permissions` pivot, not a column on `roles`, so the activity log's
   dirty-attribute diffing would record nothing. The controller writes an explicit entry listing
   permissions added and removed — the same approach D57 already uses for encrypted contact fields.
3. **Built-in protection is enforced server-side on every write path**, keyed off `is_built_in`
   rather than a hardcoded name list, so the rule cannot drift from the seeder.
4. **The roles list becomes data-driven end to end.** The admin interface currently holds its own
   hardcoded copy of the nine role names plus a translation key per role; both are replaced by a
   roles endpoint serving bilingual names, so a custom role is assignable the moment it exists.
5. **Stale interface permissions are fixed by refetching the signed-in user**, because the SPA reads
   abilities from a snapshot taken once at app load. The server already enforces changes immediately;
   only the interface lags.

## Technical Context

**Language/Version**: PHP 8.4 (backend), TypeScript 5.x strict mode (frontend)

**Primary Dependencies**: Laravel (API-only) with Sanctum SPA cookie auth; **spatie/laravel-permission
^8.3** (roles/permissions, teams disabled); **spatie/laravel-activitylog** (audit, via the project's
`LogsChanges` concern); Vue 3 + Vite + Pinia + vue-i18n + Tailwind v4 (logical properties only, D12)

**Storage**: MySQL 8 via Herd. Spatie's `roles`, `permissions`, `role_has_permissions`,
`model_has_roles` tables already exist. This feature adds display/flag columns to `roles` and
`permissions`; no new tables.

**Testing**: Pest (backend feature tests), Vitest + jsdom (frontend). Gates: Pint, Larastan, ESLint,
`npm run typecheck`. `make test` / `make lint` run both sides.

**Target Platform**: Modern evergreen browsers; bilingual RTL-first (Arabic primary) admin screens.

**Project Type**: Web application in a monorepo — `backend/` (Laravel API) + `frontend/` (Vue SPA).

**Performance Goals**: Not a hot path. Spatie caches the full permission map for 24h and flushes it
automatically on any role/permission write through its models, so a change is effective on the
affected user's next request with no manual cache handling.

**Constraints**:
- Built-in roles must remain reproducible from the seeder and must survive it being re-run —
  including when `TeamUserSeeder` invokes it indirectly (FR-023).
- An administrator may not grant a permission they do not hold, nor modify `superadmin` (FR-024).
- No sequence of actions may leave nobody able to administer access (FR-009, FR-010).
- Every role/permission change must be attributable field-level (privacy rule 9, D14).
- The permission catalogue is read-only — creation is code-only (FR-022).
- Arabic/English translation parity is asserted by the suite; Tailwind logical properties only.

**Scale/Scope**: 9 roles, 19 permissions, a handful of administrators. Two new admin screens (roles
list, role detail/edit), one new permission, ~4 new endpoints, one migration, and the removal of
three hardcoded role lists.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**⚠️ `.specify/memory/constitution.md` is still unpopulated** — every section is the scaffold's
placeholder tokens, so **no project-specific gates can be derived from it**, exactly as recorded in
feature 003's plan. This plan does not assert a pass against principles that have not been written.
Running `/speckit-constitution` remains recommended.

Gated instead against the governance the project *has* written down:

| Source | Rule | Applies here? | Verdict |
|---|---|---|---|
| `docs/privacy-rules.md` #9 | Every create/update/delete on a tracked model is audited field-level, and the log is admin-only | Yes — directly, and this is the highest-privilege data in the system | **PASS** — FR-019–FR-021; note the pivot-diffing gap forces explicit entries (research R2), otherwise grants would go unaudited |
| `docs/privacy-rules.md` #6 | Access levels enforced in policies and resources, not just stored | Yes — this feature *administers* that enforcement | **PASS** — enforcement paths untouched; only the contents of roles change |
| `docs/decisions.md` D12 | Tailwind logical properties only | Yes — two new screens | **PASS** — enforced by lint |
| `docs/decisions.md` D16 | Statuses are string columns + PHP enums, not MySQL ENUM | Partially — new `is_built_in` is boolean, no new status column | **N/A** |
| `docs/decisions.md` D28 | Archive access tiers map onto existing roles (`institution`, `verified_researcher`) by **name** | Yes — constrains what custom roles can do | **PASS with a documented limitation** — custom roles cannot carry an archive tier; see research R5 |
| Team-accounts seeder entry | "There is deliberately no `Gate::before` bypass, so what the server allows and what the interface shows cannot disagree" | Yes | **PASS** — FR-018 and the refetch in R4 exist precisely to keep the two in step |
| Repo convention (D32, D57) | Pivot/excluded-field changes need explicit activity entries, since attribute diffing misses them | Yes | **PASS** — research R2 follows it |

**Gate result: PASS**, with one flagged process gap (the empty constitution) and one documented
functional limitation (custom roles and role-name-driven behaviour, R5). No unjustified violations,
so Complexity Tracking below records only the deliberate trade-off.

**Post-Phase-1 re-check**: still PASS. The design adds one migration (display columns + a flag), two
model subclasses, one permission, four endpoints and two screens. It introduces no new public read
path, no change to how any existing permission is enforced, and no bypass.

## Project Structure

### Documentation (this feature)

```text
specs/004-roles-permissions-management/
├── plan.md              # This file
├── research.md          # Phase 0 — the six design decisions and what was rejected
├── data-model.md        # Phase 1 — Role/Permission shape, invariants, protection rules
├── quickstart.md        # Phase 1 — how to validate this end to end
├── contracts/
│   ├── roles-api.md            # HTTP contract for the roles/permissions endpoints
│   └── roles-admin-ui.md       # Screen + component contract
├── checklists/
│   └── requirements.md  # Spec quality checklist (16/16 passing)
└── tasks.md             # Phase 2 — NOT created by /speckit-plan
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Models/
│   │   ├── Role.php                             # NEW — extends Spatie Role; bilingual names, is_built_in, LogsChanges
│   │   └── Permission.php                       # NEW — extends Spatie Permission; bilingual labels, group
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── AdminRoleIndexController.php     # NEW — roles list with user counts
│   │   │   ├── AdminRoleShowController.php      # NEW — one role + its permissions
│   │   │   ├── AdminRoleStoreController.php     # NEW — create a custom role
│   │   │   ├── AdminRoleUpdateController.php    # NEW — rename / change permissions
│   │   │   ├── AdminRoleDestroyController.php   # NEW — delete an unheld custom role
│   │   │   ├── PermissionCatalogueController.php # NEW — read-only catalogue, grouped
│   │   │   └── StaffOptionsController.php       # CHANGED — pick staff by permission, not role name (R5)
│   │   ├── Requests/Role/
│   │   │   ├── StoreRoleRequest.php             # NEW
│   │   │   └── UpdateRoleRequest.php            # NEW
│   │   └── Resources/
│   │       ├── RoleResource.php                 # NEW
│   │       └── PermissionResource.php           # NEW
│   ├── Support/Access/
│   │   └── RoleGuard.php                        # NEW — built-in protection, self-lockout, last-admin, escalation
│   └── Policies/ or route middleware            # `roles.manage` permission gate
├── config/permission.php                        # CHANGED — point models.role/models.permission at the app models
├── database/
│   ├── migrations/
│   │   └── ..._add_display_fields_to_roles_and_permissions.php   # NEW
│   └── seeders/
│       └── RolesAndPermissionsSeeder.php        # CHANGED — stamp is_built_in, bilingual labels; never touch custom roles
└── tests/Feature/
    ├── RolesManagementTest.php                  # NEW — CRUD, protection, escalation, audit
    └── ...existing user/permission tests        # extended where role lists are asserted

frontend/
├── src/
│   ├── api/roles.ts                             # NEW — roles + catalogue client
│   ├── types/
│   │   ├── role.ts                              # NEW — Role, Permission, PermissionGroup
│   │   └── user.ts                              # CHANGED — delete the hardcoded ADMIN_ROLES literal
│   ├── composables/useRoles.ts                  # NEW — list/load roles
│   ├── pages/admin/
│   │   ├── RolesPage.vue                        # NEW — roles list (US1)
│   │   ├── RoleEditPage.vue                     # NEW — detail / create / edit (US2, US3)
│   │   ├── UsersPage.vue                        # CHANGED — role filter from the API
│   │   └── UserEditPage.vue                     # CHANGED — role dropdown from the API, data-driven labels
│   ├── stores/auth.ts                           # CHANGED — refetch user so abilities aren't stale (R4)
│   ├── router/index.ts                          # CHANGED — two routes behind `roles.manage`
│   └── i18n/locales/{ar,en}/roles.json          # NEW — screen copy + permission descriptions
└── src/pages/admin/*.spec.ts                    # NEW/CHANGED — RolesPage, RoleEditPage, UserEditPage
```

**Structure Decision**: The existing monorepo split is used unchanged. New backend files follow the
established one-controller-per-action convention already used by `AdminUser*Controller`, and the
guard logic lands in `app/Support/` alongside `ArchiveAccessResolver`, which plays the analogous role
for archive tiers. On the frontend the two new screens sit beside the existing `UsersPage`/
`UserEditPage`, which they are the natural companions to.

## Complexity Tracking

> No Constitution Check violations. One deliberate trade-off is recorded here because it constrains
> what the feature can deliver, and it was chosen by the user rather than by this plan.

| Decision | Why | What it costs |
|---|---|---|
| Built-in roles stay code-defined (spec Q2 → option B) | Keeps the nine roles the platform depends on reproducible from the seeder and identical across environments | Changing what an `editor` or `reviewer` can do remains a code change + deploy, permanently. The module's editing capability applies only to roles an administrator created — which is nothing on day one |
| Custom roles carry permissions only, not role-name behaviour | `ArchiveAccessResolver` maps archive tiers off role *names* (D28), a locked decision this feature does not reopen | A custom role cannot be given an archive access tier. Documented in research R5 rather than silently discovered later |
