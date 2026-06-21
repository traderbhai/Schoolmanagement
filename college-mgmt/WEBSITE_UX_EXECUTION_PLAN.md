# Website UX Execution Plan

This file controls future frontend work across the whole app. It keeps the work fast and evidence-based by forcing one real-user workflow at a time.

## Objective

Make the website easy to operate for real institute users. Every major page should answer:

- What is this page for?
- What needs attention now?
- What can I do here?
- What happens after I act?
- Where do I go next?

## Non-Negotiables

- Do not redesign the whole app in one pass.
- Do not add placeholder screens.
- Do not mark a role or module complete without current tests or browser evidence.
- Keep existing routes stable.
- Use database-backed content or useful operational empty states.
- Dashboard metrics must link to matching source lists where practical.
- Tables that can grow need filters, visible filter context, pagination, and export where the workflow needs it.
- Run focused tests first; run full suite only at stage gates.

## Execution Loop

For each role workflow:

1. Login/open as the role or inspect the role route tests when browser login is blocked.
2. Open dashboard, sidebar, and the top workflow pages.
3. Record only reproduced issues.
4. Fix critical/high UX blockers first.
5. Add or update focused tests.
6. Run focused tests and adjacent regression.
7. Browser-check the changed page when possible.
8. Update `REAL_USER_COMPLETION_BACKLOG.md`, `FRONTEND_COMPLETION_BACKLOG.md`, `USER_ROLE_UX_AUDIT.md`, and `CODEX_PROJECT_CONTEXT.md` only after verification passes.
9. Stop and report.

## Role Workflow Order

| Order | Role / workflow | Primary goal |
| --- | --- | --- |
| 1 | Admission applicant | Prospective student can complete application, documents, fees, assessments, offers, and status tracking without dead ends. |
| 2 | Admission counsellor / telecaller | Staff can find next lead/applicant, call, log outcome, schedule reminder, and escalate quickly. |
| 3 | Admission manager / head | Supervisors can manage assignment, queues, assessment, offers, seats, reports, and governance. |
| 4 | Student | Student can see today's academic/fee/document actions and understand blockers. |
| 5 | Teacher | Teacher can run daily teaching, attendance, materials, assignments, marks, and mentoring. |
| 6 | Parent | Parent can monitor linked children without seeing internal/draft/unrelated data. |
| 7 | Dean Academics | Dean can govern planning, risks, approvals, reviews, workload, student success, and handoff. |
| 8 | PMC | PMC can operate course allocation, groups, faculty load, timetable, delivery, student success, and approvals. |
| 9 | CoE / Exam | Exam team can run readiness, marks, results, hall tickets, transcripts, and appeals with official-state boundaries. |
| 10 | IQAC | IQAC can run OBE, attainment, feedback, evidence, corrective actions, and reports. |
| 11 | Program Leadership | Program owners can see scoped risks, interventions, delivery, curriculum, and quality signals. |
| 12 | Course Delivery | Faculty/program delivery teams can track sessions, materials, missed classes, remedials, and progress. |
| 13 | Accounts | Accounts can verify payments, outstanding dues, refunds, scholarships, reconciliation, and reports. |
| 14 | Admin / Director | Admin can set up institute structure, roles, permissions, settings, and audit/security safely. |
| 15 | Operations | Library, Hostel, Transport, Assets, CMC, Notices, and Notifications have practical action paths and clear empty states. |

## Issue Severity

| Severity | Meaning | Examples |
| --- | --- | --- |
| P0 | Blocks core work or exposes wrong data. | Wrong scoped count, protected route leak, broken form, 500 page, official/draft data mix. |
| P1 | User can proceed but likely gets stuck or misled. | Static KPI that should drill down, empty table without next action, unclear approval/finality state. |
| P2 | Polishing gap that does not block work. | Dense table sort improvement, secondary browser flow, label refinement. |

## Stage Gates

Run full verification only after a meaningful group of slices:

- `npm run frontend:build`
- `npm run frontend:smoke`
- `npm run frontend:smoke:mobile`
- full PHP suite:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'; C:\tmp\php-8.5.7\php.exe artisan test
```

## Current Control Files

- `REAL_USER_COMPLETION_BACKLOG.md`: active real-user issues and journey evidence.
- `FRONTEND_COMPLETION_BACKLOG.md`: frontend readiness status and global gates.
- `USER_ROLE_UX_AUDIT.md`: role family status.
- `KPI_DRILLDOWN_AUDIT.md`: cross-module metric-to-source consistency.
- `CODEX_PROJECT_CONTEXT.md`: short project memory after verified changes only.
