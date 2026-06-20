# Project Completion Backlog

This file is the release-control checklist for completing the SchoolManagement app without turning the work into one endless goal.

Use `CODEX_PROJECT_CONTEXT.md` as project memory, but verify current code before marking anything complete here.

## Operating Rules

- Work one module or one workflow family at a time.
- Do not claim a module is complete from context alone. Completion needs current code evidence and test evidence.
- Do not create placeholder primary screens. Main pages must use database-backed data.
- Preserve existing routes and behavior unless hardening broken workflows.
- Use additive migrations only.
- Update demo seeders when visible workflows need data.
- Update `CODEX_PROJECT_CONTEXT.md` only after tests pass.
- Stop after each bounded slice with changed files, tests run, and remaining gaps.
- Full suite should run once at the end of a coherent slice, not after every exploratory read.

## Release-Ready Definition

A module can be marked `Release-ready` only when all of these are true:

- Core workflows are audited against current routes/controllers/models/views/tests.
- Critical and high-risk workflow integrity gaps are closed.
- Role, hierarchy, and scope rules are enforced on direct routes.
- Published, approved, closed, paid, enrolled, issued, frozen, archived, or otherwise finalized records cannot be silently rewritten.
- Student, parent, applicant, faculty, and partner-facing pages do not expose draft, internal, or out-of-scope data.
- Financial workflows cannot create duplicate, negative, stale, or cross-scope committed state.
- Approval, lock, audit, and history rules are present where real-world operations require them.
- Growing tables have search/filter/sort/pagination/export where needed.
- Dashboard numbers link to filtered source lists where practical.
- Demo seed data populates the module's main pages.
- Focused tests pass.
- Adjacent regression tests pass.
- Full `php artisan test` passes after the module slice.
- Browser smoke has been completed for the module's primary role pages when UI changed.

## Status Legend

- `Not started`: no current audit for this release cycle.
- `Audited`: current code inspected and gaps listed.
- `In progress`: fixes are being implemented.
- `Verification pending`: code/tests written but full verification not complete.
- `Release-ready`: meets the release-ready definition above.
- `Deferred`: intentionally postponed with a reason.

## Recommended Work Order

1. Security and access-control readiness.
2. Financial integrity readiness.
3. Official academic records readiness.
4. Admission final closure readiness.
5. PMC timetable/course-allocation readiness.
6. Academics Dean, CoE, IQAC, Program Leadership, and Course Delivery readiness.
7. Student, Teacher, Parent, and Applicant portal readiness.
8. Operations modules readiness: Library, Hostel, Transport, CMC, Assets, Notifications.
9. Demo/browser/final app readiness.

## Master Module Checklist

| Priority | Module / Workflow Family | Status | Current Evidence | Critical / High Gaps To Audit | Required Verification |
| --- | --- | --- | --- | --- | --- |
| P0 | Security, Roles, Policies, Direct Route Access | Release-ready | Security-admin routes for role assignments, global user roles, permission matrix, feature-access matrix, system settings, org hierarchy, department-hierarchy admin aliases, and audit logs are guarded and covered. Latest readiness slice added director-positive coverage and invalid configuration payload checks. | No known high-risk direct-route privilege gap remains in the audited security-admin surfaces. Continue to re-audit only if new security routes are added. | Focused readiness test `AdminSecurityAccessReadinessTest` passed 3 / 36; adjacent Goal 1 security tests passed 26 / 177; full suite passed 1320 / 8364. |
| P0 | Fees, Payments, Refunds, Scholarships, NOC | Release-ready | Goal 2 audited academic fee demands, student payment proofs, admin manual fee collection, Admission refunds, scholarships, hostel dues, student/parent/API/accounts balance surfaces, and NOC clearance tests. Manual fee collection reserves pending student proofs, processed Admission refund UTRs are trimmed/case-insensitive, student web fee balances match the API no-current-year fallback, student/applicant scholarship disbursement references cannot be blank after trimming, non-cash student payment proofs require transaction references at submission and verification, and the final refund/accounts/NOC audit found no remaining high-risk balance mismatch. | No known duplicate, negative, stale, over-collection, finality, or cross-surface balance gap remains in the audited financial-integrity surfaces. Re-audit only if new financial routes or correction workflows are added. | Final Goal 2 adjacent financial/NOC regression passed 174 / 1041; full suite passed 1327 / 8414. |
| P0 | Official Academic Records: Exams, Results, Grade Cards, Transcripts, Hall Tickets | Release-ready | Goal 3 audited official academic-record routes and tests for Exam Cell results/publication, hall tickets, student admit cards, student/API results, grade cards, academic transcripts, student transcript downloads, teacher/admin marks entry, marks appeals, and published-result reporting. Transcript issuance now refuses to create an official issued transcript while any enrolled published-exam subject still has a pending result; existing issued snapshots remain stable and student downloads require issued snapshots. | No known high-risk draft exposure, official-document issuance, wrong-roster, result-publication lock, marks-appeal correction, or direct-route scope gap remains in the audited official-record surfaces. Re-audit only if new official document or result-correction routes are added. | Focused `AcademicTranscriptCanonicalWorkflowTest` passed 4 / 22; adjacent official-records regression passed 90 / 691; full suite passed 1328 / 8418. |
| P0 | Admission OS Final Closure | Release-ready | Goal 4 audited applicant lifecycle, Admission payments, documents, assessments, offers, waitlist, seat holds, enrollment, applicant self-service operations, communication safety, and Admission-to-Academics handoff surfaces. Applicant assessment self-service now lists only open slots in the applicant's own program/batch scope and rejects direct reschedule POSTs to foreign-program or foreign-batch slots. Final-state applicant reschedule locks, own-assignment scope, handoff blockers, offer/seat, payment, and hierarchy-scope regressions are covered. | No known high-risk final-state, stale payment/offer/seat, hierarchy-scope, applicant self-service exposure, or handoff-contract gap remains in the audited Admission closure surfaces. Re-audit only if new Admission final-state or applicant self-service routes are added. | Focused `AdmissionOsV039Test` passed 8 / 35; adjacent Admission closure regression passed 156 / 1006; full suite passed 1329 / 8423. |
| P0 | PMC Timetable / Course Allocation | Release-ready | Goal 5 audited PMC course allocation/timetable lifecycle, version publish/freeze/rollback, generated-to-operational sync, delivery tracker repair, notification repair, and reconciliation checks. Publishing a new PMC timetable now retires superseded operational timetable entries, rollback refuses draft/non-published source versions, and reconciliation repair/scheduled delivery tracking is limited to currently published official versions instead of archived draft history. | No known high-risk course-basket consistency, group membership, faculty assignment lock, published/frozen timetable lifecycle, rollback, operational sync, notification audit, or reconciliation repair-safety gap remains in the audited PMC timetable/course-allocation surfaces. Re-audit only if new timetable publish/rollback/sync routes are added. | Focused `AcademicsPmcTimetableV043Test` passed 4 / 17; reconciliation `AcademicsPmcTimetableV092Test` passed 11 / 119; adjacent PMC timetable regression passed 99 / 880; full suite passed 1331 / 8429. |
| P1 | Academics Dean OS | Release-ready | Goal 6 audited Dean OS v0.07/v0.08 routes, controller write paths, planning service, approval cockpit, minutes/action services, policy audit, handoff, reports, and adjacent Academics branches. Dean approval decisions are now final once approved/rejected/returned/escalated/cancelled, rejected/returned/escalated decisions require a reason, published/closed/cancelled planning cycles cannot be silently downgraded or revised in place, and approved meeting minutes cannot be re-approved to create duplicate follow-up actions. | No known high-risk Dean approval cockpit, planning/calendar finality, action/minutes verification, induction handoff, risk snapshot, report export, or policy-audit gap remains in the audited Dean OS surfaces. Re-audit only if new Dean OS write routes or cross-branch governance actions are added. | Focused `AcademicsDeanV008Test` passed 9 / 65; Dean adjacent `AcademicsDeanV007Test|AcademicsDeanV008Test` passed 15 / 102; broader Academics adjacent regression passed 61 / 341; full suite passed 1334 / 8441. |
| P1 | CoE / Exam OS | Release-ready | Goal 7 audited CoE operating dashboards, Exam Cell setup, marks entry, result publication, hall-ticket readiness/download, transcript visibility, marks appeals/anomalies, and official result reporting. CoE failed-result metrics and source queues now use the same exam publication boundary as published-exam and transcript metrics, so draft/unpublished failed marks no longer appear as official CoE risk. Existing guards cover exam setup contracts, published-result locks, roster-bound marks entry, hall-ticket eligibility, appeal correction locks, and transcript issuance boundaries. | No known high-risk question-paper/readiness, marks-entry publication boundary, hall-ticket eligibility, revaluation/appeal lock, result SLA/reporting, direct-route scope, or draft-result exposure gap remains in the audited CoE/Exam OS surfaces. Re-audit only if new CoE result/report/correction routes are added. | Focused `AcademicsCoeV003Test` passed 7 / 33; adjacent CoE/exam regression passed 69 / 534; full suite passed 1335 / 8445. |
| P1 | IQAC OS | Release-ready | Goal 8 audited IQAC operating pages plus OBE/CO/PO/PSO/matrix/attainment/survey write routes. OBE write routes now enforce program/subject/term scope through `AcademicAccessPolicyService::canManageScope`, validate subject-program and term-program consistency, and reject cross-program matrix targets. Existing OBE evidence/history locks, IQAC source-backed dashboards, feedback-quality lists, and program-scoped IQAC operating services remain intact. | No known high-risk OBE direct-route scope, CO/PO/PSO evidence lock, attainment recalculation, survey write, source-backed reporting, or IQAC operating visibility gap remains in the audited IQAC surfaces. Re-audit only if new IQAC/OBE write routes or quality-evidence workflows are added. | Focused `AcademicsIqacV004Test` passed 6 / 31; adjacent IQAC/governance/Dean/Program Leadership regression passed 30 / 165; full suite passed 1336 / 8453. |
| P1 | Program Leadership OS | Release-ready | Goal 9 audited Program Leadership OS routes, controller access, service scope resolution, dashboard/source-list views, and adjacent Dean/PMC/IQAC/Course Delivery rollups. Program Leadership portfolio and quality program queries now filter scoped program records by `programs.id`, and term/subject-scoped academic users now derive program visibility from their assigned terms/subjects instead of seeing empty or misleading operating views. Existing student-success and course-delivery publication boundaries remain intact. | No known high-risk program-scope enforcement, source-backed portfolio, curriculum/timetable/student-risk visibility, mentor/course-coordinator scope, report link, or draft-data exposure gap remains in the audited Program Leadership surfaces. Re-audit only if new Program Leadership write routes, approval actions, or exports are added. | Focused `AcademicsProgramLeadershipV005Test` passed 9 / 34; adjacent Program Leadership/IQAC/Dean/PMC/Course Delivery regression passed 34 / 203; full suite passed 1337 / 8458. |
| P1 | Course Delivery OS | Release-ready | Goal 10 audited Course Delivery OS routes, controller access, service scope resolution, attendance interventions, session delivery, course engagement, mentor actions, teacher/student adjacent flows, and PMC/Program Leadership rollups. Faculty/subject-scoped course-delivery users now build visible student IDs from active subject enrollments and own mentees instead of broad derived program scope, preventing assigned-subject teachers from seeing same-program students outside their roster. Existing draft-timetable and draft-version publication boundaries remain intact. | No known high-risk faculty assigned-course scope, planned-vs-actual visibility, material/discussion engagement scope, attendance-intervention roster, mentor-action visibility, draft-data exposure, or source-backed delivery-progress gap remains in the audited Course Delivery surfaces. Re-audit only if new Course Delivery write routes, material publication workflows, or remedial actions are added. | Focused `AcademicsCourseDeliveryV006Test` passed 7 / 25; adjacent Course Delivery/Program Leadership/PMC/Teacher/Student regression passed 62 / 408; full suite passed 1338 / 8460. |
| P1 | Student Portal | Release-ready | Goal 11 audited student dashboard, course hub, course content, assignments/quizzes, materials, discussions, fees/payment proofs, timetable, results, documents, and adjacent teacher/course-delivery routes. Student Course Hub faculty names now use only active published timetable entries whose linked timetable version is published, preventing draft staffing from appearing in student-facing course cards. Existing active-student write locks, own-student scope, enrolled-subject checks, published-results boundaries, fee/NOC guards, and archived-student restrictions remain intact. | No known high-risk own-student scope, active/current data, archived-student write lock, draft/internal timetable exposure, course content enrollment, fee/payment proof, document/NOC, or published academic record visibility gap remains in the audited Student Portal surfaces. Re-audit only if new student-facing write routes, course-material publication workflows, or portal dashboards are added. | Focused `StudentCourseContentAccessTest` passed 21 / 124; adjacent Student/Teacher/Course Delivery regression passed 90 / 568; full suite passed 1339 / 8464. |
| P1 | Teacher Portal | Release-ready | Goal 12 audited teacher dashboard, attendance, exam result entry, student list, timetable, materials, assignments, announcements, feedback, leave, and mentor flows. Teacher learning-content and feedback subject scopes now require active published timetable entries with linked timetable versions also published, so draft timetable subjects or published rows under draft versions cannot be used to publish assignments/materials/announcements or expose feedback. Existing roster-bound attendance/results/grading, inactive-teacher write locks, and student-list publication boundaries remain intact. | No known high-risk taught-subject scope, roster boundary, draft/final content separation, assignment/material/announcement lock, feedback visibility, leave/substitution, or dashboard source-list gap remains in the audited Teacher Portal surfaces. Re-audit only if new teacher-facing write routes, substitution workflows, or content publication controls are added. | Focused `TeacherScopeWorkflowTest` passed 27 / 198; adjacent Teacher/Student/Course Delivery regression passed 71 / 460; full suite passed 1340 / 8476. |
| P1 | Parent Portal | Release-ready | Goal 13 audited parent dashboard, children, attendance, results, fees, notices, linked-child checks, demand/hostel balances, paid receipt history, published-result visibility, published-timetable attendance visibility, and notice audience/date filtering. Focused coverage now proves direct attendance/results/fees URLs for unlinked students are forbidden. Existing parent fee, hostel, academic, and notice boundaries remain intact. | No known high-risk linked-child scope, parent-facing publication boundary, fee/hostel balance, notice audience/date, or unrelated-student direct URL exposure gap remains in the audited Parent Portal surfaces. Re-audit only if new parent-facing write routes, communication flows, or portal detail pages are added. | Focused `ParentPortalGuidanceTest` passed 9 / 56; adjacent Parent/Student/Fees/Notice regression passed 95 / 586; full suite passed 1341 / 8479. |
| P2 | Admin Master Data / Academic Setup | Release-ready | Admin master-data routes for departments, courses, programs, batches, terms, subjects, academic years, semesters, classrooms, timetable slots, and timetable entries are audited and covered. Latest slice blocks direct creation of programs/courses/subjects under inactive departments, blocks moving existing programs/courses/subjects into inactive departments, and blocks deactivating departments while active academic children or active people remain attached. Existing guards cover lifecycle locks, duplicate identifiers, academic-year windows, deletion/history preservation, inactive program/batch/term usage, classroom/slot/timetable finality, and role access. | No known high-risk lifecycle, inactive setup usage, direct-route mutation, duplicate identifier, deletion/history, or finality gap remains in the audited Admin master-data surfaces. Re-audit only if new setup routes or correction workflows are added. | Focused `AdminAcademicMasterDataIntegrityTest` passed 28 / 184; adjacent admin/academic integrity regression passed 25 / 256; full suite passed 1399 / 10863. |
| P2 | Library | Release-ready | Goal 14 audited Library routes/controllers/tests alongside adjacent Operations modules. Existing guards cover library operation access, active borrower checks, reservation queue order, membership limits, return/fine finality, lost/returned issue locks, student reservation eligibility, and NOC-facing fine blockers. | No known high-risk borrower eligibility, reservation order, fine finality, issue return, access-control, or history-preservation gap remains in audited Library surfaces. Re-audit only if new circulation, fine, catalog deletion, or borrower self-service routes are added. | Adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P2 | Hostel | Release-ready | Goal 14 audited Hostel routes/controllers/tests alongside adjacent Operations modules. Existing guards cover hostel operation access, active-student allocation and transfer, room capacity/status controls, hostel fee generation/paid/waiver finality, outpass approval/return lifecycle, complaint closure locks, and student-facing hostel requests. | No known high-risk room/bed occupancy, inactive-student allocation, fee-demand finality, outpass status, complaint closure, access-control, or NOC blocker gap remains in audited Hostel surfaces. Re-audit only if new hostel correction, transfer, or student request routes are added. | Adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P2 | Transport | Release-ready | Goal 14 audited Transport routes/controllers/tests alongside adjacent Operations modules. Existing guards cover transport operation access, active route/stop/vehicle checks, vehicle capacity, assignment overlap, active-student assignment, assignment end-date finality, and student transport visibility. | No known high-risk route/stop/vehicle activation, capacity, assignment overlap, inactive-student assignment, end-date, access-control, or history-preservation gap remains in audited Transport surfaces. Re-audit only if new transport billing, transfer, or scheduling routes are added. | Adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P2 | CMC / Placement / Alumni | Release-ready | Goal 14 audited Placement/Career routes/controllers/tests alongside adjacent Operations modules. Existing guards cover placement drive lifecycle, company archival with history, student active-status application locks, career-event registration/cancellation eligibility, attendance finality, internship active-student checks, and student placement visibility. | No known high-risk drive finality, company archive, internship eligibility, career-event attendance, student visibility, placement status, access-control, or history-preservation gap remains in audited CMC/Placement surfaces. Re-audit only if new offer-letter, alumni, drive cancellation, or export routes are added. | Adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P2 | Assets / Inventory | Release-ready | Goal 14 audited Asset/Inventory routes/controllers/tests. Asset issue/return and stock movement paths already covered access, active item checks, future-date rejection, custody duplication, condition locks, and duplicate receive references. This pass added duplicate issue-reference protection so repeated direct POSTs cannot decrement stock twice for the same inventory issue reference. | No known high-risk active custody, future movement, duplicate receive/issue reference, stock underflow, return finality, condition lock, access-control, or history-preservation gap remains in audited Assets/Inventory surfaces. Re-audit only if new inventory adjustment, disposal, depreciation, or asset transfer routes are added. | Focused `AssetWorkflowTest|AdminAssetAccessControlTest` passed 22 / 164; adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P2 | Notifications / Notices / Bulk Mail | Release-ready | Goal 14 audited Notifications/Notices routes/controllers/tests alongside adjacent Operations modules. Existing guards cover notification ownership, soft-delete history, notice audience/date visibility, notice archival, faculty/admin notice access control, bulk-mail duplicate/audience safety, and queued send revalidation. | No known high-risk audience-scope, queued-send revalidation, duplicate-recipient, draft/future/expired notice, notification ownership, archival, access-control, or history-preservation gap remains in audited Notifications/Notices surfaces. Re-audit only if new communication channels or bulk-send routes are added. | Adjacent Operations regression passed 171 / 1275; full suite passed 1342 / 8485. |
| P3 | Demo Seed Data And Browser Readiness | Release-ready | Goal 15 audited demo seeder wiring, APP_DEBUG/demo error behavior, sidebar scroll CSS, applicant mobile sidebar markup, seeded demo primary role pages, route smoke coverage, and localhost browser rendering. The latest final live browser pass found and fixed an all-scope Course Delivery aggregation Service Error that also affected Dean OS branch health for the seeded Admin session. Seeded demo primary portal pages open without Laravel/Whoops traces in focused tests, admin-accessible Admission/Academics/PMC/CoE/IQAC/Program Leadership/Course Delivery/Accounts/CMC pages render on localhost, and desktop/mobile sidebars are scrollable. | No known high-risk demo-mode error trace, broken primary seeded page, long-sidebar usability, or missing local migration gap remains in the final readiness surface. Re-audit only when new primary role dashboards, seeders, or layout shells are added. | `npm run frontend:build` passed; `npm run frontend:smoke` passed 97 / 2455; `npm run frontend:smoke:mobile` passed 24 / 1180; browser smoke passed for Admin dashboard, Admission dashboard, Dean OS, PMC command, CoE, IQAC, Program Leadership, Course Delivery, Accounts, and CMC with no service-error/debug text or console errors; full suite passed 1401 / 10873. |

## Per-Module Audit Template

Copy this template into the module section when starting a bounded slice.

```markdown
### Module: <name>

Status: Not started | Audited | In progress | Verification pending | Release-ready | Deferred

Scope for this slice:
- <one module or workflow family only>

Current code inspected:
- Routes:
- Controllers:
- Models/services:
- Views:
- Tests:
- Seeders:

Implemented workflows:
- ...

Incomplete or unsafe workflows:
- ...

Critical/high gaps selected for this slice:
- ...

Changes made:
- ...

Tests run:
- Focused:
- Adjacent:
- Full suite:

Browser checks:
- ...

Remaining gaps:
- ...

Release status:
- ...
```

## Current Next Bounded Goal

All bounded completion goals and the Admin Master Data / Academic Setup follow-up row are currently marked `Release-ready` with current full-suite evidence. The next bounded goal should be created only when a new feature area or regression is identified from current code evidence.
