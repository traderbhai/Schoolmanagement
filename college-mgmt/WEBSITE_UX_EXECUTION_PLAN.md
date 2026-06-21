# Website UX Execution Plan

This file controls future frontend work across the whole app. It keeps the work fast and evidence-based by forcing one real-user workflow at a time.

## Objective

Make the website easy to operate for real institute users. Every major page should answer:

- What is this page for?
- What needs attention now?
- What can I do here?
- What happens after I act?
- Where do I go next?

## Whole-Site UX Method

Use this method for every department and role. A page is not considered fixed just because it opens with HTTP 200.

### Whole-Site Operating Model

The whole website must be improved through repeated real-user walkthroughs, not a single broad redesign pass.

For each walkthrough, act as one exact role and complete one real workflow:

1. Start from that role's landing page.
2. Read the first viewport as a real user would.
3. Follow the visible navigation and primary action, without using route knowledge.
4. Compare every dashboard number with the clicked destination list.
5. Try the safe create/edit/filter/export path for that workflow.
6. Record only reproduced friction:
   - unclear next action,
   - wrong or missing drilldown,
   - hidden workflow step,
   - weak empty state,
   - unreadable placeholder/fallback,
   - excessive whitespace or poor table density,
   - missing filter/pagination/export on growing lists,
   - broken or misleading sidebar link,
   - unsafe direct action without finality guidance.
7. Fix only that role/workflow slice.
8. Prove it with focused tests and one nearby regression set.
9. Stop with a report before moving to another role.

This is how the entire website gets completed without becoming an endless task.

### What Counts As A Real UX Fix

A UX fix must make the actual workflow easier or safer. It is not enough to restyle a card.

Valid fixes:

- Move a role to the correct daily-work landing page.
- Add owner, scope, source, blocker, and next-action context to the first viewport.
- Make KPI cards open matching filtered source lists.
- Replace weak labels such as `N/A`, `TBA`, dashes, or corrupted symbols with meaningful operational states.
- Add visible filter summaries, result totals, pagination, and export controls where data grows.
- Add action-sequence guidance before publish, freeze, approve, bulk-send, payment, or official-document actions.
- Add no-match states that explain whether the issue is active filters, missing setup, unpublished data, or no assigned scope.
- Reduce wasted space in operational screens so the user sees work queues earlier.
- Group sidebar links by user intent instead of database models.

Invalid fixes:

- Adding a decorative card without changing workflow clarity.
- Showing fake counts or hardcoded demo content.
- Creating a new placeholder page when the backend workflow is missing.
- Marking a page fixed because it returns HTTP 200.
- Running only a broad smoke test and assuming the workflow is usable.

### Whole-Site Coverage Order

Use this order when no more urgent user-reported issue exists:

1. Admission Applicant.
2. Admission Counsellor / Telecaller.
3. Admission Manager / Head.
4. Student.
5. Teacher.
6. Parent.
7. Dean Academics.
8. PMC.
9. CoE / Exam.
10. IQAC.
11. Program Leadership.
12. Course Delivery.
13. Accounts.
14. Admin / Director.
15. Operations: CMC, Library, Hostel, Transport, Assets, Notices, Notifications.

Within each role, prioritize:

1. landing page and sidebar,
2. dashboard KPI drilldowns,
3. daily queues/lists,
4. detail pages,
5. safe create/edit actions,
6. reports/exports,
7. mobile layout.

### 0. Whole-Website Execution Phases

Do not attempt to redesign the whole website in one uninterrupted run. Use these phases so the work stays fast and reviewable:

| Phase | Work | Output |
| --- | --- | --- |
| 1. Shell and navigation | Verify the common layout, sidebar scroll, mobile menu, active states, role landings, and grouped navigation labels. | Shared shell fixes, navigation tests, smoke evidence. |
| 2. Role dashboards | For each role, make the first screen answer today's priority, owned scope, blockers, and source-linked KPIs. | One role dashboard slice at a time with focused tests. |
| 3. Daily work pages | Improve the top workflow pages each role actually uses daily: queues, lists, details, forms, and approvals. | Workflow-first guidance, matching filters, safe action entry. |
| 4. Deep operations | Improve lower-frequency but high-risk pages: publish/freeze/approve/bulk-send/payment/official-document flows. | Confirmation, reason capture, finality guidance, audit visibility. |
| 5. Portal/mobile pass | Check Student, Parent, Applicant, and Teacher pages at phone width and remove internal terminology from self-service flows. | Mobile-safe dashboards and owned-data guidance. |
| 6. Final evidence pass | Run frontend build/smoke/mobile smoke and a focused full-suite gate only after grouped slices are complete. | Release evidence in backlog/context files. |

Each phase still runs one role/workflow slice at a time. The phase only decides the order of work.

### 1. Real-User Journey Map

For each role, define the daily job in plain language before touching UI:

| Role type | Required journey evidence |
| --- | --- |
| Applicant / Student / Parent | The page shows only owned data, next required action, current blocker, and who is responsible if the user cannot act. |
| Counsellor / Teacher / Officer | The page shows today's work, overdue work, assigned scope, safe action entry, and where the action appears after saving. |
| Manager / Dean / Head | The page shows team risk, approvals, exceptions, owner, due date, escalation, and filtered drilldown source. |
| Admin / Governance | The page shows setup order, dependency warnings, finality/lock impact, audit impact, and role/security boundaries. |

### 2. Screen Acceptance Checklist

Every redesigned or audited primary screen must pass these checks:

- `Purpose`: the first viewport clearly says what the page is for.
- `Priority`: the user can see what needs attention now.
- `Source`: counts and rows explain which data source and filters are being used.
- `Action`: primary actions are visible, role-appropriate, and not buried in unrelated menus.
- `Sequence`: multi-step workflows show the correct order and prerequisites.
- `State`: draft, pending, approved, rejected, published, locked, overdue, and blocked states are visually distinct.
- `Scope`: the page tells scoped users whether they are seeing all records, assigned records, or filtered records.
- `Empty`: empty states explain whether there is no data, missing setup, no assigned scope, unpublished data, or active filters.
- `Drilldown`: clickable KPIs land on lists whose filters and totals match the card.
- `Safety`: final/publish/delete/bulk/send/override actions show confirmation and reason capture where required.
- `Density`: operational pages are compact enough for repeated daily use.
- `Mobile`: top actions and navigation remain usable at phone width.

### 3. Page Triage Labels

Use these labels in `REAL_USER_COMPLETION_BACKLOG.md` and `FRONTEND_UX_REDESIGN_BACKLOG.md`:

| Label | Meaning | Fix expectation |
| --- | --- | --- |
| `workflow_blocker` | User cannot complete the real task or lands on a dead end. | Fix immediately in the active slice. |
| `misleading_data` | Count, status, scope, or official/draft state is wrong or unclear. | Fix source query and drilldown together. |
| `navigation_friction` | The right page exists but is hard to find or grouped incorrectly. | Fix manifest/sidebar grouping, labels, and landing links. |
| `action_unclear` | User can act but does not know what happens next. | Add sequence guidance, prerequisites, and result location. |
| `empty_state_weak` | Empty page looks broken or gives no next step. | Add operational empty state tied to setup/scope/filter. |
| `visual_density` | Page wastes space or hides data below the fold. | Tighten spacing, table rows, card layout, and filter bars. |
| `future_polish` | Useful but not blocking current real work. | Document only; do not widen the current slice. |

### 4. Shared UI Pattern Targets

Prefer improving existing Blade/Bootstrap patterns instead of introducing a new frontend framework.

- Role dashboard: compact priority strip, linked KPIs, "today's work", blocked records, and recent activity.
- Workbench/list: filter bar, visible filter summary, result count, pagination, row actions, export where relevant.
- Detail page: summary panel, lifecycle/status tracker, blockers, timeline, primary next actions.
- Form page: prerequisites, required fields first, inline validation, finality warning, confirmation for sensitive actions.
- Report page: filters, source explanation, export log/audit note, drilldown links.
- Empty state: reason, owner, next action, and setup/filter clear links.

### 5. Evidence Required Before Marking A Slice Fixed

Each slice needs current evidence:

- Files inspected for that role's route, controller, view, service, tests, and seed data.
- Focused test for the changed workflow.
- Adjacent regression test for the owning module.
- Static scan for broken placeholders when official or user-facing output is changed.
- Browser check when login/session/tooling allows it; otherwise PHPUnit rendering evidence plus a restricted-page browser check.
- Updated tracking file entry with route, issue, fix, and test output.

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

## Per-Screen Redesign Contract

Before changing a page, write down the contract in one or two lines in the active backlog:

- `User`: the exact role using the page.
- `Job`: the real-world task they are trying to finish.
- `Input`: the database-backed records the page depends on.
- `Decision`: what the user must decide or act on.
- `Next`: where the user should go after acting.
- `Proof`: test/browser evidence that the rendered page supports the job.

If a page cannot satisfy this contract with existing backend data, do not fake the UI. Record the backend gap and fix the minimum data/query/service issue needed for a truthful frontend.

## First-Viewport Rule

The first viewport of every primary workspace should contain:

1. Page title and role/scope context.
2. The most important current priority.
3. Three to six linked metrics or queue counts.
4. The first practical action or filter.
5. A clear explanation of why the current records are visible.

Avoid putting broad instructions, marketing-style cards, or low-priority reports above the daily work queue.

## Navigation Rule

The sidebar should answer "where do I work?" rather than "what models exist?".

Use grouped labels based on user intent:

- `Command`: dashboards and daily command centers.
- `Daily Work`: calls, queues, attendance, marks, approvals, operational actions.
- `People`: applicants, students, staff, parents, partners.
- `Academic Work`: courses, timetable, delivery, exams, OBE, curriculum.
- `Finance`: fees, payments, refunds, scholarships, reconciliation.
- `Reports`: analytics, exports, audit packs.
- `Governance`: hierarchy, permissions, policies, configuration.

Do not expose every secondary setup page as a top-level link when it belongs inside a workflow surface.

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

- `WEBSITE_UX_INVENTORY.md`: whole-site role workflow inventory. Start here when the user asks how to improve the entire website or says to continue UX work without naming a role.
- `WEBSITE_ROLE_UX_REDESIGN_MATRIX.md`: active role-level usability redesign matrix. Use this when the issue is that the app is hard to operate even though pages technically open.
- `REAL_USER_COMPLETION_BACKLOG.md`: active real-user issues and journey evidence.
- `FRONTEND_COMPLETION_BACKLOG.md`: frontend readiness status and global gates.
- `USER_ROLE_UX_AUDIT.md`: role family status.
- `KPI_DRILLDOWN_AUDIT.md`: cross-module metric-to-source consistency.
- `CODEX_PROJECT_CONTEXT.md`: short project memory after verified changes only.
