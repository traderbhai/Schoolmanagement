# Website UX Inventory

This file is the working inventory for whole-website UX improvement. It translates the broad frontend goal into bounded, role-based walkthroughs.

Use this file with:

- `WEBSITE_UX_EXECUTION_PLAN.md` for method and screen acceptance rules.
- `WEBSITE_ROLE_UX_REDESIGN_MATRIX.md` for verified role-slice history.
- `REAL_USER_COMPLETION_BACKLOG.md` for real-user journey evidence.
- `USER_ROLE_UX_AUDIT.md` for release-blocking UX status.
- `CODEX_PROJECT_CONTEXT.md` for short project memory.

## Current UX Baseline

Evidence inspected from current control files shows:

- The backend and role-readiness work is broadly release-ready.
- Major roles have at least one verified UX guidance slice.
- Frontend build, desktop smoke, mobile smoke, and full PHP suite are recorded as passing in project context.
- Many pages now have owner/source context, readable empty states, and source-linked KPIs.
- Remaining work is not broad backend feature creation. The main risk is practical usability: whether a real user can complete each daily workflow without route knowledge, confusion, hidden actions, mismatched counts, or excessive screen hunting.

## Whole-Site UX Rule

Do not redesign by module count. Redesign by real user journey.

For every role/workflow:

1. Start from the role landing page.
2. Use only visible navigation and visible actions.
3. Check first viewport clarity.
4. Click dashboard metrics and verify the destination list matches.
5. Try the safe action path.
6. Fix only reproduced friction.
7. Add focused tests.
8. Browser-check when possible.
9. Update this inventory and context after verification.

## UX Completion States

| State | Meaning |
| --- | --- |
| `release_safe` | Existing tests/smoke evidence prove the page is not obviously broken and has basic guidance. |
| `walkthrough_needed` | Needs a real-user browser walkthrough from landing page through the daily flow. |
| `workflow_gap_found` | A reproduced usability or workflow issue exists and needs a bounded fix. |
| `fixed_verified` | Focused tests plus adjacent regression/browser evidence passed for the slice. |
| `future_polish` | Useful improvement, but not blocking v0.01 usability. |

## Shared UX Contracts

Every important page should show:

- `Purpose`: what this page is for.
- `Scope`: whose data this is.
- `Source`: where counts/rows come from.
- `Priority`: what needs attention now.
- `Action`: what the user can do next.
- `Sequence`: where this step sits in the workflow.
- `State`: draft/pending/blocked/approved/published/locked/overdue/complete.
- `Drilldown`: metric links land on matching filtered lists.
- `Empty`: no-match states explain setup, scope, filters, or no work.
- `Safety`: publish/freeze/approve/bulk/send/payment/finality actions explain impact.

## Role Workflow Inventory

### 1. Admission Applicant

- `Primary job`: complete application, documents, fees, assessment, offer, and enrollment readiness.
- `Landing`: applicant dashboard/checklist.
- `Critical pages`: dashboard, checklist, application form, documents, fees, status, admission operations, offer letters, notifications.
- `Current state`: `release_safe`.
- `Next UX pass`: browser walkthrough of full applicant submission/update path, including mobile.
- `Likely remaining polish`: clearer inline progress on long application sections; richer guidance after final submitted/selected/enrolled states.
- `Evidence available`: applicant UX/readiness/portal ownership tests and browser checks are recorded in context/backlogs.

### 2. Admission Counsellor / Telecaller

- `Primary job`: call next lead/applicant, log outcome, schedule follow-up, send reminder, escalate blockers.
- `Landing`: counsellor desk or calling desk.
- `Critical pages`: counsellor desk, calling desk, lead detail, applicant detail, reminders, call queue, conversation timeline.
- `Current state`: `release_safe`.
- `Next UX pass`: live call outcome workflow from queue to saved follow-up.
- `Likely remaining polish`: tighter one-screen calling mode, keyboard-friendly call disposition, quicker parent/guardian contact action.

### 3. Admission Manager / Head

- `Primary job`: supervise team workload, assignments, SLA breaches, documents, payments, assessments, offers, and seat movement.
- `Landing`: admission command center / manager workspace.
- `Critical pages`: command center, manager workspace, workbench, attention queues, reports, governance, offer/seat control.
- `Current state`: `release_safe`.
- `Next UX pass`: assignment/reassignment and exception drilldown walkthrough.
- `Likely remaining polish`: stronger team workload visualization and fewer duplicate management entry points.

### 4. Student Portal

- `Primary job`: see today classes, attendance, dues, results, documents, assignments, materials, feedback, requests, and blockers.
- `Landing`: student dashboard.
- `Critical pages`: dashboard, timetable, attendance, results, fees, courses, assignments, documents, feedback, hostel/transport, library.
- `Current state`: `release_safe`.
- `Next UX pass`: mobile-first daily student journey from dashboard to a required action.
- `Likely remaining polish`: table-density controls for long history pages; more consistent "who owns this next" wording.

### 5. Teacher Portal

- `Primary job`: teach classes, mark attendance, upload material, create assignments, enter results, manage mentees.
- `Landing`: teacher dashboard.
- `Critical pages`: dashboard, timetable, attendance marking, assignments, submissions, materials, announcements, exams/results, roster, mentor.
- `Current state`: `release_safe`.
- `Next UX pass`: positive browser create/update flow for attendance, assignment, and material.
- `Likely remaining polish`: faster teaching-day layout with fewer clicks from timetable to attendance/material.

### 6. Parent Portal

- `Primary job`: monitor linked child attendance, results, fees, notices, hostel/transport, and contact guidance.
- `Landing`: parent dashboard.
- `Critical pages`: dashboard, child list, attendance, results, fees, notices.
- `Current state`: `release_safe`.
- `Next UX pass`: linked-child drilldown path with multiple children.
- `Likely remaining polish`: clearer parent escalation/contact path for repeated risk.

### 7. Dean Academics

- `Primary job`: govern Academics through planning, risk, approvals, actions, workload, student success, induction, and reports.
- `Landing`: Dean OS.
- `Critical pages`: command, attention, planning, reviews/actions, approval cockpit, risk, faculty workload, student success, induction, analytics.
- `Current state`: `release_safe`.
- `Next UX pass`: Dean review meeting -> action -> approval -> closure walkthrough.
- `Likely remaining polish`: reduce module sprawl by emphasizing Plan/Govern/Deliver/Assess/Improve/Report tabs.

### 8. PMC

- `Primary job`: run course allocation, sections/groups, faculty load, timetable generation, conflicts, publish/freeze, delivery, student success.
- `Landing`: PMC command / timetable OS.
- `Critical pages`: command, planning, curriculum, course allocation, groups, faculty allocation, timetable planner, generator, conflicts, approvals, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: deeper browser-level interaction pass for course basket, group edit, faculty assignment, generator, and freeze actions.
- `Latest PMC slice`: timetable build sequence badges are now route-backed links, and section/group launch diagnostics respect PMC manager scope instead of leaking unrelated program blockers. Remaining polish is richer browser-level interaction coverage.

### 9. CoE / Exam

- `Primary job`: prepare exams, marks, results, hall tickets, transcripts, appeals, and official publication boundaries.
- `Landing`: CoE OS / exam dashboard.
- `Critical pages`: dashboard, readiness, marks/results, hall tickets, transcripts, appeals, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: marks/result queue -> publish boundary -> official document route.
- `Likely remaining polish`: make official/draft/locked state visually stronger.

### 10. IQAC

- `Primary job`: manage OBE, attainment, feedback closure, audit evidence, corrective actions, and quality reports.
- `Landing`: IQAC OS.
- `Critical pages`: dashboard, OBE readiness, attainment, feedback quality, audit compliance, corrective actions, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: evidence gap -> owner/action -> closure evidence workflow.
- `Likely remaining polish`: better evidence upload/review interaction density.

### 11. Program Leadership

- `Primary job`: monitor assigned program portfolio, delivery gaps, student success, quality signals, and escalations.
- `Landing`: Program Leadership OS.
- `Critical pages`: dashboard, portfolio, course delivery, student success, quality signals, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: program risk -> intervention -> escalation path.
- `Likely remaining polish`: stronger scoped-program selector and comparison view.

### 12. Course Delivery

- `Primary job`: track teaching plans, sessions, materials, missed/rescheduled classes, attendance interventions, and mentor actions.
- `Landing`: Course Delivery OS.
- `Critical pages`: dashboard, course load, session delivery, attendance interventions, engagement/material gaps, mentor actions, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: session update -> material follow-up -> attendance intervention -> closure.
- `Likely remaining polish`: more compact faculty-facing course board.

### 13. Accounts

- `Primary job`: verify payments, reconcile outstanding balances, refunds, scholarships, reports, and exports.
- `Landing`: accounts dashboard.
- `Critical pages`: dashboard, collections, payment verification, outstanding, reconciliation, refunds, reports.
- `Current state`: `release_safe`.
- `Next UX pass`: payment verification -> reconciliation -> outstanding source list walkthrough.
- `Likely remaining polish`: richer sorting and quick filters for finance queues.

### 14. Admin / Director / HOD

- `Primary job`: configure institution setup, academic structure, users, roles, permissions, settings, and audit.
- `Landing`: admin dashboard / director dashboard / HOD dashboard.
- `Critical pages`: dashboard, academic year, departments, programs, batches, terms, users, roles, permissions, settings, audit.
- `Current state`: `release_safe`.
- `Next UX pass`: first-time setup sequence and direct route protection walkthrough.
- `Likely remaining polish`: reduce cognitive load in the Admin menu and create a clearer setup cockpit.

### 15. Operations

- `Primary job`: run Library, Hostel, Transport, Assets, CMC/Placement, Notices, Notifications.
- `Landing`: module dashboards/lists.
- `Critical pages`: library issue/return, hostel room/outpass/complaint, transport routes/vehicles/assignments, asset register/custody/movement, CMC drives/applications, notices, notification inbox.
- `Current state`: `release_safe`.
- `Next UX pass`: finish module-by-module rendered workflow checks, starting with Assets if no higher-priority user issue exists.
- `Likely remaining polish`: dedicated operator role pages, richer table sorting, and more browser checks for modal/action flows.

## Immediate Execution Queue

Use this queue when the user says "proceed" without naming a role:

1. `Operations - Assets`: asset register, stock/custody/movement, assign/return guidance.
2. `Operations - Library`: issue/return/reservation/fine browser workflow.
3. `Operations - Hostel`: room allocation/outpass/complaint browser workflow.
4. `Operations - Transport`: route/vehicle/student assignment browser workflow.
5. `Teacher - positive create/update`: attendance, material, assignment.
6. `Admission - positive staff action`: call outcome, reminder, document action.
7. `PMC - timetable prerequisite wizard`: course allocation to publish/freeze.
8. `Dean - review/action workflow`: meeting to closure.
9. `Applicant - mobile full journey`: checklist to fee/document/status.
10. `Final shell pass`: sidebar scroll, dashboard links, table density, no placeholder primary content.

## Evidence Template

For each completed slice, append a short note:

```text
### YYYY-MM-DD - {Role / Workflow}

- Routes inspected:
- Issue reproduced:
- Fix:
- Tests:
- Browser evidence:
- Status:
```

## Open Current Slice

### 2026-06-21 - Operations / Assets

- `Routes inspected`: `admin.assets.index`, `admin.assets.export`, `admin.assets.assign`, `admin.assets.assignments.return`, stock receive/issue/export routes.
- `Issue reproduced`: Asset page had source-backed lists and exports, but top KPI cards looked like dashboard drilldowns while not linking to matching register/status or stock sections.
- `Fix`: top metric cards now link to the asset register/status drilldowns or consumable stock section; first viewport now labels Admin/Director owner and source data as asset register, custody assignments, and inventory movements.
- `Tests`: `AssetWorkflowTest` passed `22 tests / 152 assertions`; adjacent `AdminOperationsFrontendBetaReadinessTest` passed `11 tests / 808 assertions`; `git diff --check` passed for the changed files.
- `Browser evidence`: current browser session is a PMC user; `/admin/assets` showed the friendly restricted page with no debug text and no console warnings/errors. Positive Admin render is covered by the focused feature test.
- `Status`: `fixed_verified`.

### PMC Timetable Real-World Sequence Slice

- `Date`: 2026-06-22.
- `Scope`: PMC Timetable OS dashboard and launch-control group diagnostics.
- `Fix`: the top timetable build sequence now links directly to the real source workflow pages: student baskets, groups, section/group faculty allocation, locked slots, generator, and publish/freeze. Group launch diagnostics now respect PMC academic scope so a manager does not see unrelated program blockers in the readiness count.
- `Tests`: `AcademicsPmcTimetableV041Test` passed `6 tests / 161 assertions`; adjacent `AcademicsPmcFrontendBetaReadinessTest` passed `8 tests / 345 assertions`; `git diff --check` passed for the changed files.
- `Status`: `fixed_verified` for this bounded PMC real-world workflow slice.
### PMC Timetable Batch-Scoped Generator Slice

- `Date`: 2026-06-22.
- `Scope`: PMC timetable generator group selection.
- `Fix`: batch-specific timetable generation now filters course groups by `batch_id` and applies the actor's PMC academic scope before creating generation items, preventing another batch's section/group from entering a selected batch timetable run.
- `Tests`: `AcademicsPmcTimetableV041Test` passed `7 tests / 164 assertions`; adjacent `AcademicsPmcFrontendBetaReadinessTest` passed `8 tests / 345 assertions`; syntax checks passed for the touched service/test files.
- `Status`: `fixed_verified` for this bounded generator-boundary slice.