# Phase 1 Data Model: Roles & Permissions Management

**One migration, no new tables.** Spatie's four tables already exist and already carry the
relationships; this feature adds display and classification columns to two of them.

## Entity: Role

Existing Spatie table `roles`, surfaced through a new `App\Models\Role` that extends
`Spatie\Permission\Models\Role` (wired via `config/permission.php` → `models.role`).

| Field | Type | Role in this feature |
|---|---|---|
| `id` | bigint PK | — |
| `name` | string, unique | **Stable identifier, never user-editable.** What `hasRole('editor')`, `->role([...])` and `Rule::exists('roles','name')` match on. For a custom role, generated from the English name as a slug and then frozen |
| `guard_name` | string | Spatie's guard; always `web` here (single guard) |
| `name_ar` / `name_en` | string(80), nullable | **NEW.** Display names. Required for custom roles; back-filled for built-ins by the seeder |
| `description_ar` / `description_en` | string(255), nullable | **NEW.** What the role is for — shown on the list screen (FR-001) |
| `is_built_in` | boolean, default `false` | **NEW.** True for the nine seeded roles. The single source of the protection rules (FR-015, FR-023); stamped by the seeder, never by the API |
| `created_at` / `updated_at` | timestamps | — |

**Relationships**
- `Role belongsToMany Permission` via `role_has_permissions` (Spatie). Unchanged.
- `Role belongsToMany User` via `model_has_roles` (Spatie). Unchanged. Users hold exactly one role,
  enforced at the write path by `syncRoles([$one])` in `AdminUserUpdateController`.
- `users_count` is derived (a count over `model_has_roles`), not stored — FR-001's user count.

**Audit**: `LogsChanges` applied, so name/description changes are recorded field-level with
`path`-style exclusions not needed here. Permission grants are **not** attribute changes and are
audited separately — see "Audit behaviour" below.

## Entity: Permission

Existing Spatie table `permissions`, surfaced through `App\Models\Permission`.

| Field | Type | Role in this feature |
|---|---|---|
| `id` | bigint PK | — |
| `name` | string, unique | The identifier the product enforces (`artworks.manage`, `archive.publish`, …). **Created only in code** (FR-022); never writable through the API |
| `guard_name` | string | Always `web` |
| `label_ar` / `label_en` | string(160), nullable | **NEW.** Human-readable description of what the permission allows (FR-003) |
| `group` | string(40), nullable | **NEW.** Area for grouping in the UI — artists, artworks, holders, archive, review, administration (FR-002) |

19 permissions exist today. The catalogue grows only when code adds an enforcement point, so
`label_*` and `group` are authored alongside that code, in the seeder.

## Invariants

### I1 — `name` is immutable once created
> A role's `name` never changes after creation; renames change `name_ar`/`name_en` only.

Enforced by the update request (no `name` field accepted). Protects `hasRole()` call sites, the
seeder's `findByName` (which would otherwise create a duplicate), and `Rule::exists('roles','name')`
on user assignment.

### I2 — Built-in roles are immutable through the API
> `is_built_in = true` ⟹ no permission change, no rename, no delete via any endpoint.

Enforced by `RoleGuard` on every write controller. `is_built_in` is set only by the seeder, so the set
of protected roles cannot drift from the code that defines them.

### I3 — An administrator cannot exceed their own authority
> The permissions granted by a change ⊆ the permissions the acting administrator holds.

Enforced by `RoleGuard` (FR-024). Currently a no-op for `admin`, which holds all 19 — see research R3.

### I4 — Access administration can never be lost
> After any change, at least one **active** user holds `roles.manage`; and no administrator may
> remove `roles.manage` from their own role.

Two separate checks in `RoleGuard` (FR-009, FR-010). The active-user qualifier matters: a deactivated
superadmin is not a way back in.

### I5 — The permission catalogue is read-only
> No endpoint creates, renames or deletes a `Permission`.

Enforced by having no such endpoint. Guarantees SC-006: an administrator cannot grant an ability the
product does not enforce, because they can only pick from what code registered.

### I6 — A role's assignability follows from its existence
> Any role in `roles` is offered when assigning a user a role; nothing maintains a second list.

FR-017/FR-018. Today violated three times over — `ADMIN_ROLES` (frontend literal), the
`users.roles.*` i18n keys, and `StaffOptionsController`'s `->role([...])`. See research R5.

## State transitions

### A role's lifecycle

```text
                    (seeder)                          (API, custom only)
                        │                                     │
                        ▼                                     ▼
              ┌──────────────────┐                 ┌────────────────────┐
              │  built-in role   │                 │   custom role      │
              │  is_built_in=1   │                 │   is_built_in=0    │
              └──────────────────┘                 └────────────────────┘
                   │        ▲                        │      │        │
       permissions │        │ re-stamped             │      │        │ delete
       re-granted  │        │ every deploy   rename  │      │ grant/ │ (only if
       from code   └────────┘                (ar/en) │      │ revoke │  0 users)
                                                     └──────┘        ▼
   no API path can mutate this column                            (removed)
   or its permissions ─────────────────► refused by RoleGuard
```

A built-in role and a custom role are the same table row shape; only `is_built_in` separates what may
happen to them. There is no transition between the two states — the API never sets `is_built_in`, and
the seeder only ever sets it on the nine roles it names.

### A permission's lifecycle

```text
code adds an enforcement point → seeder registers the permission (+ label, group) → appears in catalogue
```

No runtime transitions. A permission removed from the seeder disappears from the catalogue, and any
custom role's grant of it disappears with it (cascade on `role_has_permissions`) — covered as an edge
case in the spec.

## Validation rules

| Rule | Source | Where enforced |
|---|---|---|
| `name_en` and `name_ar` required on create; max 80 | FR-012 | `StoreRoleRequest` |
| Generated `name` slug unique across `roles` | FR-016 | `StoreRoleRequest` (unique rule) |
| `permissions[]` must all exist in the catalogue | FR-022, I5 | `StoreRoleRequest` / `UpdateRoleRequest` (`exists:permissions,name`) |
| `permissions[]` ⊆ acting administrator's own permissions | FR-024, I3 | `RoleGuard` |
| Target role must not be built-in | FR-005/013/014/015, I2 | `RoleGuard` |
| Delete only when `users_count = 0` | FR-014 | `RoleGuard` + controller |
| Change must not orphan access administration | FR-009/010, I4 | `RoleGuard` |
| Concurrent edit detected | FR-011 | `updated_at` precondition on the update request |
| Caller holds `roles.manage` | FR-004 | Route middleware (`can:roles.manage`) |

## Audit behaviour

Two paths, because one is not enough (research R2):

1. **Attribute changes** (`name_ar`, `name_en`, `description_*`) — logged automatically by
   `LogsChanges`, field-level, with causer, like every other model in the project.
2. **Permission grants and revokes** — write to the `role_has_permissions` pivot and change **no
   attribute on `roles`**, so activitylog's dirty-diffing records nothing. The update controller
   therefore writes an **explicit** activity entry naming the permissions added and removed, following
   the precedent set by `ArtistCurationUpdateController` for encrypted contact fields (D57).

Both are visible only through the existing change-log surface, which is already gated by
`activity.view` (privacy rule 9 / D14 — the log is admin-only). Role creation and deletion are
ordinary model events and are logged by path 1.
