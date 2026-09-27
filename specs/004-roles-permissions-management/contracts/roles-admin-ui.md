# Contract: Roles Admin Screens

Two new screens plus changes to the two existing user screens. Both new screens sit behind the
`roles.manage` permission, checked in-page the way the other admin pages do it (an `ErrorState` with a
403, not a silent redirect — matching `UsersPage`/`ArtworksRegistryPage`).

Routes follow the established locale-prefixed pattern:

| Route | Screen | Story |
|---|---|---|
| `/:locale/admin/roles` | `RolesPage.vue` | US1 |
| `/:locale/admin/roles/new` | `RoleEditPage.vue` (create mode) | US2 |
| `/:locale/admin/roles/:id(\d+)` | `RoleEditPage.vue` (edit mode) | US1, US3 |

Reachable from the header's **Workspace** dropdown, which already gathers staff tools and filters them
by permission — a user without `roles.manage` never sees the entry.

---

## `RolesPage.vue` — the roles list (US1)

**Reads**: `GET admin/roles`.

**Must show**, per role: display name in the active locale, description, `users_count`,
`permissions_count`, and an unmistakable **built-in vs custom** distinction (FR-001).

**Requirements**

1. Built-in roles must be visibly marked as code-defined — a badge, not merely a disabled edit button.
   This is the *most common* interaction in the module (all nine current roles are built-in), so the
   explanation has to be present on the list, not discovered after clicking (spec edge case, SC-009).
2. A role held by nobody shows `0`, not a blank (FR-001 scenario 3).
3. A custom role with no permissions must read as granting nothing, rather than looking identical to
   one whose permissions failed to load (spec edge case).
4. "New role" is offered only to a caller who may create one.
5. Sorted built-ins first, then custom — the built-ins are what people are looking for today.

---

## `RoleEditPage.vue` — detail, create and edit (US1, US2, US3)

**Reads**: `GET admin/roles/{id}` (edit mode), `GET admin/permissions` (always).
**Writes**: `POST admin/roles`, `PATCH admin/roles/{id}`, `DELETE admin/roles/{id}`.

**Requirements**

1. **Permissions are shown grouped** by `group`, with each permission's `label` in the active locale —
   never the bare `artworks.manage` identifier (FR-002, FR-003). Held and not-held must be
   distinguishable at a glance.
2. **A built-in role's name and description render read-only**, with the reason stated inline (FR-005,
   FR-015) — but its permission checkboxes stay interactive and the save action present, since its
   permissions are administrator-adjustable (FR-023). `superadmin` alone (`permissions_editable: false`
   in the API response) renders fully read-only, checkboxes included, with the save action absent
   rather than present-and-failing.
3. **Permissions outside `meta.grantable` are shown but not selectable**, with the reason — so an
   administrator sees the full picture of what a role holds while being unable to widen beyond their own
   authority (FR-024). They must never be silently hidden: hiding them would make a role's true
   permission set unreadable to the person administering it.
4. **Affected-user count before confirming**: saving a permission change on a role held by N users
   requires a confirmation naming N (FR-006). Not a toast afterwards — before.
5. **Refusals are explained, not generic.** The `RoleGuard` outcomes (built-in identity, superadmin fully
   locked, escalation, self-lockout, last-administrator) and the concurrency conflict each need their
   own message. A
   generic "something went wrong" here is actively harmful: the administrator cannot tell whether they
   did something wrong or the platform is protecting them.
6. **Concurrency**: send the `updated_at` received on load; on `409`, tell the user the role changed
   underneath them and offer to reload rather than overwriting (FR-011).
7. **Delete** is offered only for a custom role with `users_count = 0`; when users hold it, the action
   explains how many and what to do instead (FR-014).
8. RTL-safe throughout: logical Tailwind properties only (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`), per D12.

---

## Changed: `UserEditPage.vue` and `UsersPage.vue`

Both currently import a hardcoded `ADMIN_ROLES` array and render each role's name through a
`users.roles.{name}` translation key. Neither works for a role created at runtime.

**Required changes**

1. Populate the role `<select>` (UserEditPage) and the role filter (UsersPage) from
   `GET admin/roles` (FR-017, FR-018).
2. Render each role's name from the API's `display_name`, not from an i18n key. The nine built-ins keep
   their existing translations as their seeded `name_ar`/`name_en`, so nothing visibly changes for them.
3. Delete the `ADMIN_ROLES` literal from `types/user.ts` — with it present, the two lists can drift
   again, which is the bug this feature exists to remove.
4. The `users.roles.*` i18n keys become unused for role labels; remove them rather than leaving two
   competing sources of a role's name.

> Note: these screens are gated by `users.manage`, not `roles.manage`. Someone who may assign roles but
> not manage them still needs the list — so the roles list endpoint must be readable with either
> permission, or a trimmed variant offered. Worth settling during `/speckit-tasks`; the simplest answer
> is to allow `GET admin/roles` for `users.manage` holders too, since it exposes nothing sensitive
> beyond what the role dropdown already shows.

---

## Changed: `stores/auth.ts`

`can()` reads `state.user.permissions`, a snapshot taken once per app load
(`if (!auth.initialized) await auth.fetchUser()`). After an administrator changes permissions, an
affected user's interface keeps offering abilities the server now refuses.

**Required change**: refetch the signed-in user on navigation into admin routes, so the interface
converges within one navigation (FR-008, research R4). The server remains the only real gate; this
stops the UI showing doors that no longer open.

---

## Test surface

| Spec file | Must cover |
|---|---|
| `RolesPage.spec.ts` | built-in badge present; user/permission counts render; zero renders as 0; "new role" hidden without permission; 403 state for a caller without `roles.manage` |
| `RoleEditPage.spec.ts` | permissions render grouped with labels; a built-in role's name/description are read-only but its permissions are editable; `superadmin` is fully read-only with no save action; non-grantable permissions visible but disabled; affected-count confirmation before save; each refusal message distinct; `409` offers reload |
| `UserEditPage.spec.ts` | role dropdown populated from the API, including a custom role; label comes from `display_name` |
| `UsersPage.spec.ts` | role filter populated from the API |
| `auth.spec.ts` | `can()` reflects refetched permissions after a change |
| i18n parity (existing) | new `roles.json` keys stay in lockstep across `ar`/`en` |
