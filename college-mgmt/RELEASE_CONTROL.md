# Release Control

This file controls how future development should proceed without turning the app into one endless task.

## Active Rule

Work one bounded release slice at a time.

Do not use `CODEX_PROJECT_CONTEXT.md` as a full history log. It is only short working memory. Detailed history is archived under `docs/archive/`.

## Standard Slice Protocol

For every new goal:

1. Name the bounded goal.
2. Read only the relevant release-control file:
   - `PROJECT_COMPLETION_BACKLOG.md` for backend/workflow readiness.
   - `USER_ROLE_UX_AUDIT.md` for role/frontend UX issues.
   - `KPI_DRILLDOWN_AUDIT.md` for KPI/card/list consistency.
   - `FRONTEND_COMPLETION_BACKLOG.md` for frontend shell/design readiness.
3. Inspect current code for only the active scope.
4. List real gaps from current code evidence.
5. Select critical/high gaps only.
6. Patch narrowly.
7. Add or update focused tests.
8. Run focused tests.
9. Run adjacent regression tests.
10. Run full `php artisan test` only at a stage gate or after shared auth/layout/schema changes.
11. Update the relevant release-control file.
12. Update `CODEX_PROJECT_CONTEXT.md` only after tests pass.
13. Stop and report.

## Test Commands

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'; C:\tmp\php-8.5.7\php.exe artisan test
```

Frontend gates:

```powershell
npm run frontend:build
npm run frontend:smoke
npm run frontend:smoke:mobile
```

## Stage Gate Rules

Run full `php artisan test` when:

- A batch closes.
- Shared authorization, role, policy, layout, route, migration, seeder, or model behavior changes.
- Multiple focused fixes have accumulated.
- The user asks whether a slice is complete.

Avoid full suite after:

- Documentation-only updates.
- A single isolated view/test expectation change.
- Exploratory audits with no code change.

## Completion Labels

Use these labels consistently:

- `not_started`: no current audit.
- `audited`: code inspected and gaps listed.
- `in_progress`: fix is underway.
- `verification_pending`: code is patched but tests are not complete.
- `fixed_verified`: focused and adjacent verification passed.
- `release_ready`: exit criteria and stage gate passed.
- `future_polish`: useful improvement, not a blocker.
- `deferred`: intentionally postponed with a reason.

## Bounded Goal Template

```markdown
## Goal: <module/workflow>

Scope:
- <specific routes/workflows>

Do not touch:
- <out-of-scope modules>

Exit criteria:
- <observable behavior>
- <focused tests>
- <adjacent regression>
- <full suite if required>

Current code inspected:
- Routes:
- Controllers:
- Services/models:
- Views:
- Tests:

Verified gaps:
- P0/P1:
- Lower priority/future polish:

Changes made:
- 

Verification:
- Focused:
- Adjacent:
- Full suite:
- Frontend/browser:

Release-control updates:
- 

Remaining:
- 
```

## Current Baseline

- Full PHP suite: `1490 tests / 12645 assertions` passed.
- Frontend build: passed.
- Desktop smoke: `127 tests / 3729 assertions` passed.
- Mobile smoke: `29 tests / 1380 assertions` passed.

## Current Next Step

No active bounded goal is open. Start the next goal only after the user identifies a module/workflow or a concrete regression.
