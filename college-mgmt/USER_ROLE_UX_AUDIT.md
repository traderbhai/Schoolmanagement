# User Role UX And Workflow Audit

This is the short active UX audit. Full historical role-by-role findings are archived at `docs/archive/USER_ROLE_UX_AUDIT_FULL_ARCHIVE_2026-06-20.md`.

## Purpose

Track current release-blocking UX, navigation, ownership, action-entry, mobile, and broken-page issues by role family.

Do not append long sprint diaries here. Record only current status, blockers, and verification evidence.

## Status Legend

- `not_started`: not checked in current cycle.
- `in_progress`: active bounded UX slice.
- `fixed_verified`: focused and adjacent checks passed.
- `checked_no_blocker`: sampled checks found no release blocker.
- `future_polish`: useful improvement, not a current blocker.

## Current Verified Baseline

- Full PHP suite: `1490 tests / 12645 assertions` passed.
- Frontend build: passed.
- Desktop frontend smoke: `127 tests / 3729 assertions` passed.
- Mobile frontend smoke: `29 tests / 1380 assertions` passed.

## Role Family Matrix

| Role family | Current UX status | Evidence | Remaining non-blocking polish |
| --- | --- | --- | --- |
| Admin / Director / HOD | fixed_verified | Admin dashboard quick actions, setup/security entry pages, security labels, mobile shell, and direct route usability covered. Full suite passed at Batch 4 gate. | Admin information architecture can be further simplified later. |
| Admission Head/Admin | fixed_verified | Admission Head all-scope dashboard/drilldowns, visible Admission links, daily/governance/closure workflows, queue exports, document/payment queues, assessment/offer-seat protections, communication/automation protections covered. Latest focused role slice passed `AdmissionHeadReadinessTest` plus adjacent Admission frontend/KPI/v0.039 checks. | Richer sortable headers on some queues. |
| Admission Manager/Counsellor Lead/Officer/Telecaller | fixed_verified | Scoped KPI drilldowns, rendered nav, Manager/Counsellor Lead/Officer dashboards, Counsellor/Telecaller desks, visible links, scoped applicant/lead access, call outcomes, reminder/document actions, cadence-rule blocking, read-only offer-seat controls, and handoff visibility covered. Latest role slices passed `AdmissionManagerOfficerReadinessTest` and `AdmissionCounsellorTelecallerReadinessTest` plus adjacent Admission frontend/KPI/v0.038/v0.039 checks. | More live browser action-flow checks later. |
| Admission Partner | fixed_verified | Partner dashboard, submitted-lead list, and lead submission are database-backed and scoped. | Partner reporting depth later. |
| Dean Academics | fixed_verified | Dean OS KPI/mobile/sidebar checks, planning/action/approval/risk/export checks covered. | More browser-click checks if tooling permits. |
| PMC / Program Leadership | fixed_verified | PMC command, v0.041 surfaces, legacy at-risk list controls/export, scoped navigation, and KPI consistency covered. | Further visual polish and richer timetable browser interactions. |
| CoE / Exam | fixed_verified | CoE filtered source lists/export, scoped Exam Manager/Officer navigation, official-boundary checks covered. | Legacy page visual parity later. |
| IQAC | fixed_verified | IQAC filtered source lists/export, scoped IQAC Manager/Officer navigation, OBE/quality surfaces covered. | Legacy page visual parity later. |
| Course Delivery | fixed_verified | Source-backed filters/export, role-safe navigation, dashboard/link coverage, and course-delivery scope checks covered. | Additional faculty browser workflows later. |
| Teacher Portal | fixed_verified | Visible nav, mobile shell, safe action entries for attendance/materials/assignments/roster, profile/empty-state handling covered. | More positive create/submit browser flows later. |
| Student Portal | fixed_verified | Academic summary, action availability, personal list/empty-state signals, ownership checks, mobile shell covered. | Richer personal filters where history grows. |
| Parent Portal | fixed_verified | Linked child attendance/results/fees work; unlinked child details blocked; mobile shell covered. | More communication journey checks later. |
| Applicant Portal | fixed_verified | Checklist/documents/fees/status action entry, final-state locks, ownership, mobile shell, owned Admission Operations, Offer Letters, Notifications navigation, visible applicant links, seeded page reachability, and applicant-owned consent update audit covered. Latest role slice passed `AdmissionApplicantReadinessTest` plus adjacent portal/applicant/v0.039 checks. | More live browser applicant submission flows later. |
| Accounts | fixed_verified | Outstanding/reconciliation export/action surfaces, dashboard links, finance lifecycle/report tests covered. | Richer sorting/table density later. |
| CMC | fixed_verified | Drive/company/event create-edit guidance, exports, placement action tests, mobile/table checks covered. | More browser modal interactions later. |
| Library / Hostel / Transport / Assets | fixed_verified | Admin-backed pages load with headings, action-entry surfaces, confirmation guards, exports, mobile/table wrappers, lifecycle/access tests covered. | Dedicated operator roles; richer sorting/table density. |

## Batch Status

| Batch | Scope | Status | Latest evidence |
| --- | --- | --- | --- |
| A | Admin / Director / HOD | fixed_verified | Focused Admin/Operations frontend + adjacent security tests; full suite passed. |
| B | Admission users | fixed_verified | Focused Admission frontend/KPI/action tests + adjacent Admission regressions. |
| C | Dean Academics | fixed_verified | Dean frontend/v0.07/v0.08/KPI/action/export tests. |
| D | PMC / Program Management | fixed_verified | PMC frontend, v0.041, KPI, and scoped-navigation tests. |
| E | CoE / Exam and IQAC | fixed_verified | CoE/IQAC frontend and operating tests; scoped nav tests. |
| F | Teacher / Student / Parent / Applicant | fixed_verified | `PortalFrontendBetaReadinessTest`, ownership/scope/action-entry tests, mobile smoke. |
| G | Accounts / CMC / Operations | fixed_verified | `AdminOperationsFrontendBetaReadinessTest`, operations lifecycle/access tests, full suite. |

## Current Blockers

No known release-blocking UX issue is open in this active audit.

## Future Polish Backlog

- Dedicated operator roles for Library, Hostel, Transport, and Assets.
- Richer sortable headers and table-density improvements on some operations pages.
- More browser-level create/edit/modal interaction checks where PHPUnit and smoke tests already verify entry safety.
- Further Admin command-surface simplification.

## Update Rule

When a new UX issue is found:

1. Add it under `Current Blockers` with role, route, reproduction, severity, and expected behavior.
2. Keep the fix bounded to one role/module batch.
3. Move detailed investigation notes to `docs/archive/` if they grow beyond a short paragraph.
4. Mark `fixed_verified` only after focused and adjacent tests pass.
