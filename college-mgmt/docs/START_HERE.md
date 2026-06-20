# Start Here

Use this index to avoid rereading long historical files.

## First File To Read

Read `RELEASE_CONTROL.md` first for any future development task.

Then read only the file relevant to the active goal:

| Task type | Read this |
| --- | --- |
| Current runtime, users, latest baseline | `CODEX_PROJECT_CONTEXT.md` |
| Backend workflow readiness | `PROJECT_COMPLETION_BACKLOG.md` |
| Frontend/UX role issue closure | `USER_ROLE_UX_AUDIT.md` |
| Frontend shell/design safety | `FRONTEND_COMPLETION_BACKLOG.md` |
| KPI/card/list mismatches | `KPI_DRILLDOWN_AUDIT.md` |
| Admission KPI details | `ADMISSION_KPI_DRILLDOWN_AUDIT.md` |
| Historical detail | `docs/archive/` |

## Do Not Start With Archives

Archive files are for investigation only. Do not load them by default.

Use an archive only when:

- A current release-control file points to a historical detail.
- A regression appears related to an old sprint.
- The user explicitly asks for historical reasoning.

## Current Fast Workflow

1. Define one bounded goal.
2. Inspect only relevant code.
3. Patch only verified high-impact gaps.
4. Run focused tests.
5. Run adjacent tests.
6. Run full suite only at stage gates.
7. Update release-control files.
8. Stop and report.

## Current Verified Baseline

- Full PHP suite: `1490 tests / 12645 assertions` passed.
- Frontend build: passed.
- Desktop frontend smoke: `127 tests / 3729 assertions` passed.
- Mobile frontend smoke: `29 tests / 1380 assertions` passed.
