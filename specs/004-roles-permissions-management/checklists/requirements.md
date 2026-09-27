# Specification Quality Checklist: Roles & Permissions Management

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-23
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

**All items pass as of iteration 2. Spec is ready for `/speckit-plan`.**

Iteration 1 raised three clarifications; all three were answered and folded in:

- **FR-022 — scope → assign-only.** The permission catalogue is code-defined and read-only in the
  module. This removed the "inert permission" edge case entirely and closes SC-006.
- **FR-023 — source of truth → seeder stays authoritative for the nine built-in roles.** Only
  custom roles created through the module have administrator-managed permissions.
- **FR-024 — privilege boundary → no escalation.** An administrator may not grant a permission they
  do not hold, and may not modify `superadmin`.

**The FR-023 answer forced a restructure, not just an edit.** With built-in roles read-only,
permission editing no longer applies to any role currently in use, so there is nothing to edit until
a custom role exists. Consequences applied:

- **US2 and US3 swapped.** "Create a role" is now P1 (the only path to changing what's possible);
  "Adjust a custom role" is now P2 and depends on it. The original ordering had a P1 story that
  couldn't be demonstrated without a P2 one.
- Permission-editing requirements (FR-005) and role rename/delete (FR-013–FR-015) scoped to custom
  roles; built-ins protected from renaming and deletion too, since a rename would break code that
  looks them up by name and cause seeding to recreate them.
- Edge cases reworked: dropped "a permission nothing checks" (impossible under FR-022); added
  "administrator tries to edit a built-in role", which will be the *most common* interaction, since
  the built-ins are the roles everyone currently holds.
- Added SC-009 (built-in edit attempts always refused with an explanation) and split SC-007 so
  built-in reset-on-deploy reads as intended behaviour rather than a violation.

Final shape: 24 functional requirements, 9 measurable success criteria, 4 prioritised user stories
(two P1, two P2).

**Scope note for planning**: the roles list is currently duplicated between a server-side seeder
constant and a hardcoded list in the admin interface. FR-018 requires collapsing that to one source,
so the user-assignment screens get touched even though this feature is not about assigning users to
roles — custom roles are invisible to users until that is done.
