# Quickstart: Validating Roles & Permissions Management

How to prove this feature works end to end. Scenarios map to the spec's user stories and success
criteria; shapes and invariants live in
[contracts/roles-api.md](./contracts/roles-api.md) and [data-model.md](./data-model.md) rather than
being repeated here.

## Prerequisites

- PHP 8.4 + Composer, Node 22+, MySQL and Redis under Herd (see root `README.md`)
- Databases `bidayaat` and `bidayaat_test`

```bash
make install         # once
make migrate         # migrate:fresh --seed  (NOTE: wipes the DB, including user accounts)
make dev             # API on :8000, SPA on :5173
```

> Use the full `--seed` form, not individual seeders, so `TeamUserSeeder` reinstates the team logins.
> A `migrate:fresh` without it leaves you unable to sign in.

You need **two** accounts to exercise the privilege boundary, both seeded by `TeamUserSeeder`:

- a **superadmin** — `mbadis@hafezgallery.com`
- an **admin** — `mali@hafezgallery.com` or `valeria@hafezgallery.com`

Local password is `password` unless `SEED_TEAM_PASSWORD` is set.

## Automated checks

```bash
make test                                  # Pest + Vitest, both sides
make lint                                  # Pint, Larastan, ESLint, vue-tsc

cd backend  && php artisan test --filter=RolesManagementTest
cd frontend && npx vitest run src/pages/admin/RoleEditPage.spec.ts
cd frontend && npm run typecheck            # bare vue-tsc --noEmit is vacuous here
```

Baseline before this feature: **329 Pest / 356 Vitest** green, with 4 pre-existing Larastan failures in
`HomeController.php` that are unrelated to this work.

## Scenario 1 — See what every role can do (US1, SC-001)

1. Sign in as superadmin, open `/ar/admin/roles` (or `/en/...`).
2. **Expect**: all nine roles listed with display names, descriptions, user counts and permission
   counts; every one marked as **built-in**.
3. Open `editor`.
4. **Expect**: its 18 permissions shown grouped by area with readable labels, not raw identifiers like
   `artworks.manage`; the permissions it lacks are visible and distinguishable.
5. **Expect**: nothing on this screen is editable, and the reason (code-defined) is stated inline.

✅ SC-001 is met if you could answer "what can a reviewer do?" in under 30 seconds without reading PHP.

## Scenario 2 — A built-in role's identity is protected, its permissions aren't (SC-009, FR-015, FR-023)

Try these from the API directly — the UI shouldn't be the only thing stopping (or allowing) this:

Then, as admin or superadmin, attempt each of these against `editor` (or `reviewer`):

| Attempt | Expect |
|---|---|
| `PATCH admin/roles/{editor}` with a permission change | **200** — permissions are administrator-adjustable |
| `PATCH` with a rename | **409**, explained as code-defined |
| `DELETE admin/roles/{editor}` | **409** |

Then attempt the same three against `superadmin` specifically:

| Attempt | Expect |
|---|---|
| `PATCH admin/roles/{superadmin}` with a permission change | **409** — the one role that stays fully locked |
| `PATCH` with a rename | **409** |
| `DELETE admin/roles/{superadmin}` | **409** |

**Expect** each refusal to carry its own message, not a generic error.

## Scenario 3 — Create a custom role and use it (US2, SC-002)

1. As superadmin, `/ar/admin/roles` → **New role**.
2. Name it in Arabic and English ("أمين أرشيف" / "Archivist"), grant just
   `review_queue.archivist_review` and `activity.view`, save.
3. **Expect**: it appears in the list, marked **custom**, with 0 users.
4. Open a user's edit screen (`/ar/admin/users/{id}`) and assign the new role.
5. **Expect**: the new role appears in the dropdown with its Arabic/English name — this is the check
   that `ADMIN_ROLES` is genuinely gone (FR-018).
6. Sign in as that user.
7. **Expect**: they can reach the review queue and the activity log, and **nothing else** — no artwork
   registry, no archive, no user management.

✅ SC-002 is met if steps 1–5 took under 5 minutes with no code change and no deployment.

## Scenario 4 — Adjust a custom role, and see it take effect (US3, SC-003, FR-006, FR-008)

1. With that user still signed in **in another browser**, return to the superadmin session.
2. Edit the Archivist role and grant `artworks.manage`.
3. **Expect**: before saving, you are told how many users this affects (1).
4. Save. In the *user's* browser, navigate to another admin page.
5. **Expect**: the artworks registry is now reachable **without** signing out and back in (FR-008).
6. Revoke it again, and navigate once more in the user's browser.
7. **Expect**: the registry is gone from their interface, and reaching its URL directly is refused.

This is the scenario the automated tests can't fully cover — the stale-permissions problem (research
R4) only shows up across two live sessions.

## Scenario 5 — Nobody can lock everyone out (SC-005, FR-009, FR-010)

1. As superadmin, assign yourself a **custom** role that holds `roles.manage` (built-ins won't let you
   reproduce this).
2. Try to revoke `roles.manage` from that role.
3. **Expect**: refused, explained as self-lockout.
4. Now try any change that would leave zero active users holding `roles.manage`.
5. **Expect**: refused, explained as last-administrator.

```bash
cd backend && php artisan tinker --execute="
echo App\Models\User::where('is_active',true)->permission('roles.manage')->count().' active admins';"
```

**Expect**: never `0`, at any point during this scenario.

## Scenario 6 — An admin cannot exceed their own authority (FR-024, SC-006)

1. Sign in as the **admin** account (not superadmin).
2. Open a custom role.
3. **Expect**: permissions the admin doesn't hold are visible but not selectable, with the reason given.

> **Currently expected to be a no-op**: `admin` holds all 19 permissions today, so nothing is disabled.
> To actually exercise the rule, temporarily reserve `roles.manage` to superadmin only, then repeat —
> `roles.manage` should be visible-but-disabled for the admin. See research R3.

4. **Expect**: `superadmin` cannot be opened for editing by either account.

## Scenario 7 — Both custom roles and a built-in role's permission edits survive a deployment (SC-007, FR-023)

The thing most likely to be broken by a future seeder edit, for either kind of role.

1. With the Archivist role in place, remove a permission from `editor` (e.g. `artworks.manage`) via the
   UI or API. Re-run seeding the way a deploy does:

```bash
cd backend && php artisan db:seed --class=RolesAndPermissionsSeeder
cd backend && php artisan db:seed --class=TeamUserSeeder   # invokes the roles seeder internally
```

2. **Expect**: Archivist still exists, still `is_built_in = false`, with exactly the permissions you
   left it with.
3. **Expect**: `editor` still does **not** have `artworks.manage` — the seeder's baseline grant only
   ever applies the first time a built-in role is created, so it does not silently restore a permission
   an administrator deliberately removed.
4. **Expect** (unrelated to either role edited above): every other built-in role that was **not** edited
   still matches its code definition exactly, since nothing here should have touched them.

```bash
cd backend && php artisan tinker --execute="
\$r = App\Models\Role::where('name','archivist')->first();
echo \$r ? \$r->permissions->pluck('name')->implode(', ') : 'LOST — regression';"
```

## Scenario 8 — Every change is accountable (US4, SC-004)

1. Make one permission change and one rename on a custom role.
2. Open the change log at `/{locale}/admin/activity`.
3. **Expect**: an entry for the rename showing old → new, **and** an entry for the permission change
   naming which permissions were added and removed, both attributed to you with a timestamp.
4. Sign in as a user without `activity.view`.
5. **Expect**: no access to any of it (privacy rule 9 — the log is admin-only).

The permission-change entry is the one to check carefully: it comes from an explicit write, not from
attribute diffing, because permission grants touch a pivot table and would otherwise be logged
**nowhere** (research R2).

## Scenario 9 — Custom-role holders appear in staff pickers (research R5)

1. Assign the Archivist role to a user, ensuring it holds `artworks.manage`.
2. Open an artwork's curation screen and use the "assigned to" staff picker.
3. **Expect**: the Archivist user appears.

Before the `StaffOptionsController` change they would **not**, because that endpoint filtered by role
name (`editor`, `admin`, `superadmin`, `reviewer`) — which would make a brand-new staff role unable to
be assigned anything.

## What is deliberately *not* validated here

- **Changing what a built-in role can do** — impossible by design (spec Q2 → B); it remains a code change.
- **Giving a custom role an archive access tier** — out of scope; `ArchiveAccessResolver` maps tiers off
  role names per D28, so custom roles always fall through to the `registered` tier (research R5).
- **Creating new permissions** — code-only by design (FR-022).
- **Multiple roles per user** — the model assigns exactly one; unchanged.
- **An approval workflow for access changes** — a permitted administrator's change applies immediately.
