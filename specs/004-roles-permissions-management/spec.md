# Feature Specification: Roles & Permissions Management

**Feature Branch**: `004-roles-permissions-management` (spec directory; no git branch created — no branch hook configured)

**Created**: 2026-09-23

**Status**: Draft

**Input**: User description: "build roles and permissions management module in a way admin and super admin can manage them from"

## Context: what exists today

Access control already works — it just cannot be administered from inside the platform.

- **Nine roles exist**: reader, contributor, verified_researcher, institution, artist_claimed, editor,
  reviewer, admin, superadmin. The list is hardcoded in **two** places that must be kept in step by
  hand: the roles seeder on the server, and a fixed list in the admin interface used to populate the
  role dropdowns.
- **About twenty permissions exist** (managing artists, artworks, holders, archive material, events,
  imports, the review queues, users, and so on). They are created by the same seeder and enforced per
  route.
- **What an administrator can do today**: assign a user exactly **one** of those nine roles, from the
  user edit screen. That is the entirety of access administration available in the product.
- **What nobody can do today**: see which permissions a role actually holds, change them, create a
  role, rename one, or retire one. Every one of those requires editing server code, redeploying, and
  re-running a seeder. There is no screen anywhere that answers "what can a reviewer actually do?"

Two consequences make this more than an inconvenience. Onboarding a new kind of staff member (an
archivist who should work queues but not publish, say) is a code change rather than an
administrative act. And because the permission sets live in code that only developers read, the
people accountable for who can do what cannot inspect or verify it.

This feature gives administrators a place to see every role and what it grants, and to create and
manage their own roles directly.

**One deliberate boundary**: the nine built-in roles stay defined in code and reproducible from it.
They become *visible* here but not editable. Administrators who need a different permission set create
a custom role instead. That keeps the roles the platform itself depends on predictable across
deployments, at the cost of built-in roles still needing a developer to change — an accepted
trade-off, not an oversight.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See what each role can actually do (Priority: P1)

An administrator opens the roles screen and sees every role, how many people hold it, and exactly
which permissions it grants — grouped so the list is readable rather than twenty raw flags.

**Why this priority**: It is the foundation of the module and delivers value on its own, with no
ability to change anything and therefore no risk. Today this information exists only in server code,
so the people accountable for access cannot audit it. Every later story builds on this screen.

**Independent Test**: Sign in as an administrator, open the roles screen, and confirm each of the
nine roles lists the permissions it holds and the number of users assigned to it — verifiable against
the seeded configuration without changing anything.

**Acceptance Scenarios**:

1. **Given** an administrator on the roles screen, **When** the list loads, **Then** every role appears with its name, a readable description of what it is for, and the number of users currently holding it.
2. **Given** an administrator viewing a role, **When** they open it, **Then** every permission the role holds is shown, grouped by the area it governs (artists, artworks, archive, review queues, administration), with the permissions it does not hold clearly distinguishable from those it does.
3. **Given** a role held by nobody, **When** it is listed, **Then** its user count reads zero rather than being hidden or blank.
4. **Given** a user without permission to administer access, **When** they attempt to reach the roles screen, **Then** they are refused and see nothing about how roles are configured.

---

### User Story 2 - Create a role for a new kind of staff member (Priority: P1)

An administrator creates a role, names it in both Arabic and English, picks the permissions it holds
from the existing catalogue, and it becomes available to assign to users — without a developer.

**Why this priority**: This is where the module's value actually lands. The nine built-in roles stay
code-defined (FR-023), so creating a custom role is the *only* way an administrator changes what is
possible — and it is also the prerequisite for US3, since there is nothing whose permissions can be
edited until a custom role exists.

**Independent Test**: Create a role with two permissions, assign it to a test user, confirm that user
can do exactly those two things and nothing more, then retire the role and confirm it can no longer
be assigned.

**Acceptance Scenarios**:

1. **Given** an administrator on the roles screen, **When** they create a role with a name and a set of permissions, **Then** the role appears in the list and becomes selectable when assigning a role to a user.
2. **Given** a newly created role, **When** a user is assigned it, **Then** that user can do exactly what its permissions allow and nothing else.
3. **Given** an administrator creating a role, **When** they choose a name that already exists, **Then** they are told rather than silently ending up with two roles that look alike.
4. **Given** an administrator creating a role, **When** they pick permissions, **Then** only permissions they themselves hold are offered, so a role cannot be built that exceeds its creator's authority.
5. **Given** a custom role held by nobody, **When** an administrator deletes it, **Then** it disappears from the list and can no longer be assigned.
6. **Given** a custom role currently held by one or more users, **When** an administrator tries to delete it, **Then** the deletion is refused until those users are moved to another role, and the administrator is told how many users are affected.
7. **Given** any of the nine built-in roles, **When** an administrator tries to rename or delete it, **Then** the attempt is refused and explained as code-defined rather than failing silently.

---

### User Story 3 - Adjust a custom role as needs change (Priority: P2)

An administrator grants or revokes individual permissions on a custom role they created earlier, sees
plainly how many people that will affect before confirming, and the change takes effect for those
people without anyone redeploying anything.

**Why this priority**: Custom roles are rarely right first time, so this is what makes US2 durable
rather than one-shot. It is P2 only because a role created correctly in US2 is already useful; it
depends on US2 because there is nothing to adjust until a custom role exists.

**Independent Test**: Grant one permission to a custom role, confirm a user holding that role can then
reach the corresponding screen, revoke it again, and confirm they can no longer reach it — without a
deployment or a seeder run in between.

**Acceptance Scenarios**:

1. **Given** an administrator editing a custom role, **When** they grant a permission and save, **Then** every user holding that role gains the corresponding ability, and the change is visible on the roles screen immediately.
2. **Given** an administrator editing a custom role, **When** they revoke a permission and save, **Then** every user holding that role loses the corresponding ability.
3. **Given** a custom role held by several users, **When** an administrator changes its permissions, **Then** they are told how many people the change affects **before** they confirm it.
4. **Given** an administrator whose own role is a custom one, **When** they attempt to revoke from it the very permission that lets them administer access, **Then** the change is refused with an explanation, so nobody can lock themselves — and potentially everybody — out.
5. **Given** a change that would leave the platform with nobody able to administer access at all, **When** it is attempted, **Then** it is refused with an explanation.
6. **Given** a permission change was saved, **When** an affected user is in the middle of a session, **Then** their abilities reflect the change without them signing out and back in.
7. **Given** an administrator renaming a custom role, **When** they save, **Then** the new name appears everywhere the role is shown, and users holding it keep their abilities uninterrupted.
8. **Given** two administrators editing the same custom role at once, **When** the second saves, **Then** they are told the role changed underneath them rather than silently overwriting the first change.

---

### User Story 4 - Account for who changed access, and when (Priority: P2)

Any change to a role's permissions, and any role created, renamed or deleted, is recorded with who
did it, when, and exactly what changed — reviewable after the fact.

**Why this priority**: Permission changes are the highest-privilege actions in the platform, and the
project already requires field-level audit of every tracked record. This should follow US2 closely
rather than trail the release; it is P2 only because US2 is demonstrable without it.

**Independent Test**: Grant a permission to a role, then open the change log and confirm an entry
naming the administrator, the role, the permission granted, and the time.

**Acceptance Scenarios**:

1. **Given** an administrator grants or revokes a permission, **When** the change saves, **Then** the change log records who made it, which role, which permissions were added and removed, and when.
2. **Given** a role is created, renamed or deleted, **When** the change saves, **Then** the change log records it the same way.
3. **Given** someone reviewing the change log, **When** they look at a role's history, **Then** they can reconstruct what that role could do at any earlier point in time.
4. **Given** a change to a role's permissions, **When** it is recorded, **Then** the record is visible only to those allowed to see the change log, like every other audit record.

---

### Edge Cases

- **Self-lockout**: an administrator holding a custom role revokes access administration from it (FR-009). Not reachable via built-in roles, since those are read-only here.
- **Last administrator**: a change or deletion would leave nobody at all able to administer access (FR-010).
- **An administrator tries to edit a built-in role**: must be refused with a clear explanation that it is code-defined — the common case, since the nine built-ins are the roles everyone currently holds, so this refusal will be the first thing most administrators meet (FR-005, FR-015).
- **Deployment-time seeding runs**: built-in roles are re-established from code by design; custom roles must be left entirely untouched, including when the seeder is invoked indirectly by the team-accounts seeder (FR-023).
- **A custom role holding a permission later removed from the code catalogue**: the stale grant must not linger as an unrecognised entry; it disappears with the permission.
- **An administrator whose own permissions are narrower than a custom role they are editing**: they must not be able to widen it beyond what they themselves hold, and must not be able to silently strip permissions they cannot see (FR-024).
- **A role assigned to users loses a permission mid-session**: affected users must not retain the ability until they next sign in (FR-008).
- **Two administrators editing the same custom role at once**: the second save must not silently discard the first (FR-011).
- **A user holding no role at all**, or a custom role that was deleted from under them: they fall back to no permissions rather than to unchecked access.
- **A custom role with no permissions at all**: allowed (useful when building one up), but it must be obvious in the list that it grants nothing.

## Requirements *(mandatory)*

### Functional Requirements

#### Visibility

- **FR-001**: Administrators MUST be able to see every role in the platform, with the number of users holding each.
- **FR-002**: Administrators MUST be able to see, for any role, every permission it holds and every permission it does not, grouped by the area each permission governs.
- **FR-003**: Each permission MUST be presented with a human-readable description of what it allows, in Arabic and English, rather than only its internal identifier.
- **FR-004**: Access to the roles and permissions module MUST itself be governed by a permission, and MUST be refused to anyone without it.

#### Managing a custom role's permissions

- **FR-005**: Administrators MUST be able to grant and revoke individual permissions on a **custom** role. Built-in roles MUST be read-only here, and the interface MUST make clear that they are code-defined rather than simply appearing broken or unresponsive.
- **FR-006**: Before a change is confirmed, the system MUST tell the administrator how many users it will affect.
- **FR-007**: A saved change MUST take effect for every user holding that role without a deployment, a seeder run, or any manual cache clearing.
- **FR-008**: A saved change MUST take effect for affected users who are currently signed in, without requiring them to sign out and back in.
- **FR-009**: The system MUST refuse any change that would remove the ability to administer access from the role held by the administrator making the change.
- **FR-010**: The system MUST refuse any change or deletion that would leave the platform with no user able to administer access.
- **FR-011**: When two administrators change the same role concurrently, the system MUST NOT silently discard either change; the second one to save MUST be told the role changed underneath them.

#### Managing roles

- **FR-012**: Administrators MUST be able to create a role with a name in Arabic and English and an initial set of permissions.
- **FR-013**: Administrators MUST be able to rename a custom role without interrupting the abilities of users holding it.
- **FR-014**: Administrators MUST be able to delete a custom role that no user holds; deletion of a role still held by users MUST be refused, naming how many users hold it.
- **FR-015**: The nine built-in roles MUST be protected from renaming and deletion, and the reason MUST be explained when an attempt is refused. Renaming one would break the code that looks it up by name and would cause deployment-time seeding to recreate it, leaving two roles where there was one. **Amended**: their *permissions*, unlike their identity, are administrator-adjustable through this module — see FR-023. `superadmin` alone is the exception, protected from every kind of change including permissions (FR-024).
- **FR-016**: Role names MUST be unique; an attempt to reuse an existing name MUST be reported rather than accepted.
- **FR-017**: A newly created role MUST become available for assignment to users, in every place a role can be chosen, without a deployment.
- **FR-018**: The system MUST NOT require the set of available roles to be duplicated in more than one place; the interface's list of assignable roles MUST come from the same source the server enforces.

#### Accountability

- **FR-019**: Every permission grant and revoke MUST be recorded with the administrator who made it, the role affected, the specific permissions added and removed, and the time.
- **FR-020**: Every role creation, rename and deletion MUST be recorded the same way.
- **FR-021**: These records MUST be visible only to those permitted to see the platform's change log, and MUST be readable enough to reconstruct what a role could do at an earlier point in time.

#### Scope and integrity

- **FR-022**: The permission catalogue MUST be read-only in this module. Administrators assign existing permissions to roles; they cannot define new permission names. The catalogue always reflects exactly what the product enforces, so it is impossible to grant an ability that has no effect.
- **FR-023**: The nine built-in roles MUST remain defined in code for their *name, description and existence*, which stay seeder-authoritative and are **not** editable through this module — renaming, deleting or reinterpreting what a built-in role means is a code change. **Amended**: a built-in role's *permission set*, unlike its identity, is administrator-adjustable through this module (except `superadmin`, see FR-024); a permission change made this way MUST survive deployment and maintenance untouched, exactly as a custom role's does, since the seeder never re-syncs a built-in role's permissions once seeded (see [decisions.md](../../docs/decisions.md), "Roles & permissions management").
- **FR-024**: An administrator MUST NOT be able to grant a permission they do not themselves hold, and MUST NOT be able to modify the `superadmin` role in any way — not its name, not its description, not its permissions. `superadmin` is the one role that stays fully protected, so there is always a way back into the system. Widening access beyond an administrator's own authority otherwise requires a superadmin.

### Key Entities

- **Role**: A named bundle of permissions that users are assigned. Carries a name in Arabic and English, a description of its purpose, whether it is protected from deletion/renaming, and the permissions it holds. Currently nine exist; users hold exactly one.
- **Permission**: A single ability the product enforces at a specific point (managing artworks, publishing archive material, resolving source conflicts, administering users, …). Carries an internal identifier, a human-readable Arabic and English description, and the area it belongs to for grouping. Roughly twenty exist today.
- **Role assignment**: The link between a user and their role. Already exists and is already administered from the user screens; this feature changes what the roles on the other end of that link mean, not how users are assigned to them.
- **Access change record**: An audit entry describing one change to a role or its permissions — who, when, what changed from, what changed to.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can answer "what can this role do?" for any role — built-in or custom — in under 30 seconds, without reading server code or asking a developer.
- **SC-002**: Onboarding a new kind of staff member — a custom role with its own permission set, assigned to a user — takes an administrator under 5 minutes and requires **zero** developer involvement and zero deployments.
- **SC-003**: A permission change takes effect for every affected user, including those already signed in, within one page interaction of saving.
- **SC-004**: 100% of permission and role changes are attributable after the fact to the administrator who made them, with the before and after visible.
- **SC-005**: No sequence of actions available in the module can leave the platform with nobody able to administer access — verified by attempting it.
- **SC-006**: No administrator can grant an ability through this module that the product does not actually enforce, and none can grant an ability they do not themselves hold — verified by attempting both.
- **SC-007**: Custom roles and their permissions survive a routine deployment unchanged — measured by creating one, deploying, and confirming it still holds. Built-in roles are re-established from code by design and are expected to match their code definition after every deployment.
- **SC-008**: The roles offered when assigning a user a role always match the roles the server recognises — zero cases of a role appearing in one and not the other, including custom roles created minutes earlier.
- **SC-009**: An attempt to edit a built-in role is refused with an explanation in 100% of cases, never failing silently or appearing to save.

## Assumptions

- **This module manages roles and their permissions, not which role a given user holds.** Assigning a role to a user already exists on the user screens and is unchanged; this feature is about what those roles mean.
- **Users continue to hold exactly one role.** The current model assigns a single role per user, and nothing in the request asks to change that. Multiple roles per user is out of scope.
- **Self-lockout and last-administrator protection are required, not optional** — assumed rather than asked, because the alternative is a product that can permanently lock its owners out.
- **The nine existing roles keep their current permission sets permanently**, defined in code and re-established on deploy. They are visible in this module (US1) but never editable through it. Changing what an editor or a reviewer can do remains a code change, by deliberate choice.
- **"Custom role" means a role created through this module.** Only these have administrator-managed permissions. The built-in/custom distinction is the organising idea of the whole feature and must be obvious in the interface, not buried in a disabled button.
- **Permission descriptions are content, not configuration.** Human-readable Arabic and English descriptions of each permission are authored as part of this work; administrators do not edit them.
- **The "only grant what you hold" rule is currently non-binding for `admin`**, because `admin` already holds every permission that exists. It starts to bite the moment a permission exists that `admin` lacks — most likely the permission governing this module itself, if that is reserved to superadmin. Worth knowing so the rule is not mistaken for broken when it appears to do nothing.
- **Existing behaviour is preserved**: the interface shows only what the server actually allows, with no bypass for any role — so what an administrator sees a role can do is exactly what it can do.
- **No approval workflow.** A permitted administrator's change applies immediately; a second person is not required to approve it. Worth revisiting if access changes later need four eyes.
- **Bilingual, right-to-left first**, like every other screen in the platform: Arabic and English, with the Arabic layout treated as primary.
- **Both "admin" and "superadmin" are in scope as administrators of this module**, per the request; exactly what each may do relative to the other is the subject of an open clarification (FR-024).

## Dependencies

- Relies on the existing role and permission enforcement already applied across the product's screens and endpoints — this feature administers that system rather than replacing it.
- Relies on the existing change-log facility for accountability (FR-019 to FR-021).
- Relies on the existing user-role assignment screens, which must draw their list of assignable roles from this module's roles rather than a separate hardcoded copy (FR-018). Custom roles are invisible to users until this is done.
- Requires the deployment-time seeding of roles and permissions to be adjusted so that it re-establishes built-in roles without touching custom ones (FR-023) — including when it is invoked indirectly by the team-accounts seeder.
