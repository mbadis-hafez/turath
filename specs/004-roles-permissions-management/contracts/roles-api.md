# Contract: Roles & Permissions API

New endpoints, all under the existing `/api/v1` prefix, behind Sanctum SPA cookie auth and a **new**
`roles.manage` permission. `{role}` is constrained to numbers by `whereNumber`, per D19.

Registered inside a `Route::middleware('can:roles.manage')` group, mirroring how `users.manage` gates
the `admin/users` routes today.

> **The permission catalogue has no write endpoints, deliberately.** Permissions are created only by
> code + seeder (FR-022, invariant I5), which is what guarantees an administrator cannot grant an
> ability nothing enforces.

---

## GET `/api/v1/admin/roles` — list every role

Serves both the roles screen (US1) and, in a trimmed form, the role dropdowns on the user screens
(FR-017, FR-018).

### Response `200`

```json
{
  "data": [
    {
      "id": 6,
      "name": "editor",
      "display_name": { "ar": "محرِّر", "en": "Editor" },
      "description": { "ar": "…", "en": "…" },
      "is_built_in": true,
      "users_count": 3,
      "permissions_count": 18
    },
    {
      "id": 10,
      "name": "archivist",
      "display_name": { "ar": "أمين أرشيف", "en": "Archivist" },
      "description": { "ar": null, "en": "Works the queues, cannot publish" },
      "is_built_in": false,
      "users_count": 0,
      "permissions_count": 4
    }
  ]
}
```

`is_built_in` is what the interface keys its read-only affordances off — it must never infer that from
a hardcoded name list.

---

## GET `/api/v1/admin/roles/{role}` — one role with its permissions

### Response `200`

```json
{
  "data": {
    "id": 10,
    "name": "archivist",
    "display_name": { "ar": "أمين أرشيف", "en": "Archivist" },
    "description": { "ar": null, "en": "Works the queues, cannot publish" },
    "is_built_in": false,
    "users_count": 0,
    "updated_at": "2026-09-23T10:00:00Z",
    "permissions": ["review_queue.archivist_review", "activity.view"]
  }
}
```

`updated_at` is returned so the client can send it back as a concurrency precondition (FR-011).

---

## GET `/api/v1/admin/permissions` — the read-only catalogue

Every permission the product enforces, grouped for display (FR-002, FR-003).

### Response `200`

```json
{
  "data": [
    {
      "group": "artworks",
      "permissions": [
        { "name": "artworks.manage", "label": { "ar": "…", "en": "Create and edit artworks" } }
      ]
    },
    {
      "group": "administration",
      "permissions": [
        { "name": "users.manage", "label": { "ar": "…", "en": "Create and edit user accounts" } },
        { "name": "roles.manage", "label": { "ar": "…", "en": "Manage roles and their permissions" } }
      ]
    }
  ],
  "meta": { "grantable": ["artworks.manage", "users.manage"] }
}
```

- **`meta.grantable`** — the subset the *calling* administrator may grant, i.e. the permissions they
  themselves hold (FR-024, invariant I3). The interface uses it to disable the rest rather than letting
  a save fail. The server re-checks regardless; this is only so refusals are visible before saving.

---

## POST `/api/v1/admin/roles` — create a custom role

### Request

```json
{
  "name_ar": "أمين أرشيف",
  "name_en": "Archivist",
  "description_ar": null,
  "description_en": "Works the queues, cannot publish",
  "permissions": ["review_queue.archivist_review", "activity.view"]
}
```

`name` (the identifier) is **not** accepted — it is derived from `name_en` as a slug and frozen
(invariant I1). `is_built_in` is **not** accepted; created roles are always custom.

### Validation

| Rule | Failure |
|---|---|
| `name_ar`, `name_en` required, max 80 | 422 |
| Derived slug unique in `roles` | 422, naming the conflict (FR-016) |
| Every `permissions[]` entry exists in the catalogue | 422 (invariant I5) |
| Every `permissions[]` entry is one the caller holds | 422 (FR-024) |

### Response `201` — the created role, in the GET-by-id shape.

---

## PATCH `/api/v1/admin/roles/{role}` — rename, or change permissions

### Request

```json
{
  "name_ar": "أمين أرشيف أول",
  "name_en": "Senior archivist",
  "description_en": "…",
  "permissions": ["review_queue.archivist_review", "activity.view", "materials.review"],
  "updated_at": "2026-09-23T10:00:00Z"
}
```

All fields optional. `permissions` is the **complete intended set** (a sync, not a delta) — simpler to
reason about and to audit than add/remove lists. `updated_at` is the concurrency precondition.

### Validation and refusals

| Condition | Status | Why |
|---|---|---|
| Role is built-in **and** the request touches `name_*`/`description_*` | **409** | Identity is code-defined; cannot be renamed or redescribed here (FR-005, FR-015, I2). Distinct status so the interface can explain rather than show a generic error. Permissions are unaffected by this rule — a built-in role's permission set is administrator-adjustable |
| Role is `superadmin` **and** the request touches `permissions` | **409** | The one role that stays fully locked, permissions included (FR-024) |
| `permissions[]` contains something the caller doesn't hold | 422 | No escalation (FR-024, I3) |
| Change removes `roles.manage` from the caller's own role | 422 | Self-lockout (FR-009, I4) |
| Change would leave zero active users holding `roles.manage` | 422 | Last administrator (FR-010, I4) |
| `updated_at` doesn't match the stored value | **409** | Someone else changed it first (FR-011) |
| A permission name isn't in the catalogue | 422 | Invariant I5 |

### Response `200` — the updated role.

### Side effects

- Spatie flushes its permission cache automatically, so the change is enforced on every affected
  user's **next request** with no manual cache handling (FR-007).
- An explicit activity entry records the permissions added and removed, plus the causer (FR-019).
  Attribute changes (names, descriptions) are logged separately and automatically.
- Nothing about user↔role assignment changes; only what the role means.

---

## DELETE `/api/v1/admin/roles/{role}` — delete an unheld custom role

| Condition | Status |
|---|---|
| Role is built-in | **409** (FR-015) |
| Role is held by ≥1 user | **409**, body naming `users_count` so the interface can say how many (FR-014) |
| Custom, held by nobody | **200** |

### Response `200`

```json
{ "data": { "deleted": true } }
```

---

## Changed existing endpoints

| Endpoint | Change | Why |
|---|---|---|
| `GET /api/v1/staff-options` | Select staff by **permission** instead of `->role(['editor','admin','superadmin','reviewer'])` | Otherwise a custom role's holders never appear in any staff picker, making US2's "new kind of staff member" unassignable (research R5) |
| `PATCH /api/v1/admin/users/{user}` | No contract change — `role` still validated by `Rule::exists('roles','name')`, which now naturally admits custom roles | FR-017 |
| `GET /api/v1/user` | No shape change; the client now refetches it on admin navigation so `can()` isn't stale (research R4) | FR-008 |

## Consumed by

| Caller | Endpoints |
|---|---|
| `RolesPage.vue` (US1) | `GET admin/roles` |
| `RoleEditPage.vue` (US2, US3) | `GET admin/roles/{id}`, `GET admin/permissions`, `POST/PATCH/DELETE admin/roles` |
| `UserEditPage.vue`, `UsersPage.vue` | `GET admin/roles` — replacing the hardcoded `ADMIN_ROLES` literal and the `users.roles.*` i18n keys |
