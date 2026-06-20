# Frontend Completion Backlog

This file controls frontend-only readiness work. It is separate from backend workflow completion and should be updated after each frontend slice passes its focused tests.

## Status Legend

- `Not started`: not audited in the current frontend cycle.
- `Audited`: current UI/routes/layout inspected and gaps listed.
- `In progress`: fixes are being implemented.
- `Verification pending`: code/tests written but verification not complete.
- `Release-ready`: current slice passed focused checks and no high-risk UI break remains.

## Global Frontend Gates

| Gate | Status | Evidence |
| --- | --- | --- |
| Route/navigation manifest | Release-ready | `App\Support\FrontendNavigation` groups primary role/module routes and focused tests validate named routes. |
| Seeded demo role landing pages | Release-ready | `FrontendReadinessTest` opens manifest landing pages without debug traces. |
| Shared UI primitives | Release-ready | Blade components added under `resources/views/components/ui` for page headers, KPI strips, filters, tables, status badges, empty states, and timelines. |
| Compact design utilities | Release-ready | `public/css/app.css` includes compact operational UI classes and denser card/table spacing. |
| Frontend smoke scripts | Release-ready | `npm run frontend:build`, `npm run frontend:smoke`, and `npm run frontend:smoke:mobile` are registered. |
| Sidebar scroll/mobile shell | Release-ready | Existing sidebar scroll CSS remains covered; Admin, Admission, Dean, HOD, Director, PMC, CoE/Exam, IQAC, Program Leadership, Teacher, Student, Parent, Applicant, Accounts, and CMC role branches now use the shared manifest-driven sidebar renderer for desktop and mobile navigation where migrated. |

## Module UX Backlog

| Priority | Module / Surface | Status | Current UI Direction | Remaining Frontend Work |
| --- | --- | --- | --- | --- |
| P0 | Global shell/navigation | Release-ready | Foundation manifest and compact CSS are in place; `x-ui.manifest-sidebar` now renders Admin, Admission, Dean, HOD, Director, PMC, CoE/Exam, IQAC, Program Leadership, Teacher, Student, Parent, Applicant, Accounts, and CMC navigation from `App\Support\FrontendNavigation`. | Continue visual polish later, but no current high-risk hardcoded-role sidebar remains in the primary role shells covered by the manifest. |
| P0 | Admission OS | Release-ready | Command Center, Calling Desk, Counsellor Desk, Assessment Control Room, and Offer/Seat Control are covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page or source-link gap is known on the primary Admission daily surfaces. |
| P0 | Academics Dean OS | Release-ready | Dean OS remains preferred daily workspace; v0.08 operating surfaces now have real filters, sorting, pagination, export-current-view links, and focused beta-readiness coverage. | Continue visual polish later, but no current high-risk broken-page or false filter/sort claim is known on primary Dean OS surfaces. |
| P0 | PMC OS | Release-ready | PMC Command, v0.04 operating surfaces, and v0.041 timetable/course-allocation entry pages are covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page, false overdue link, or missing source-link gap is known on primary PMC surfaces. |
| P1 | CoE / Exam OS | Release-ready | CoE Operating dashboard and section source lists are compact, filterable, source-linked, and covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page, placeholder metric-link, or missing source-filter gap is known on primary CoE surfaces. |
| P1 | IQAC OS | Release-ready | IQAC Operating dashboard and quality source lists are compact, filterable, source-linked, and covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page, placeholder metric-link, or missing source-filter gap is known on primary IQAC surfaces. |
| P1 | Program Leadership OS | Release-ready | Program Leadership dashboard and program source lists are compact, filterable, source-linked, and covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page, placeholder metric-link, or missing source-filter gap is known on primary Program Leadership surfaces. |
| P1 | Course Delivery OS | Release-ready | Course Delivery dashboard and delivery source lists are compact, filterable, source-linked, and covered by focused beta-readiness tests. | Continue visual polish later, but no current high-risk broken-page, placeholder metric-link, or missing source-filter gap is known on primary Course Delivery surfaces. |
| P1 | Student / Teacher / Parent / Applicant portals | Release-ready | Student, Teacher, Parent, and Applicant primary portal pages now have focused beta-readiness coverage for seeded-role access, action links, debug-trace absence, product-neutral copy, and placeholder/broken-markup regressions. | Continue visual polish later, but no current high-risk broken-page, placeholder action, framework-trace, or missing daily-work link gap is known on primary portal surfaces. |
| P2 | Admin / Operations | Release-ready | Admin, Accounts, CMC, Library, Hostel, Transport, Assets, and fee collection pages now have focused beta-readiness coverage for seeded-role access, action links, debug-trace absence, product-neutral labels, mojibake prevention, and placeholder/broken-markup regressions. | Continue gradual visual polish later, but no current high-risk broken-page, placeholder action, framework-trace, or mojibake gap is known on primary Admin/Operations surfaces. |

## Current Frontend Slice

Current shell/navigation adoption status:

- `resources/views/components/ui/manifest-sidebar.blade.php` renders grouped role navigation from `App\Support\FrontendNavigation`.
- `resources/views/layouts/applicant.blade.php` is the first manifest-driven adopter for both desktop sidebar and mobile offcanvas navigation.
- `resources/views/layouts/parent.blade.php` now also uses the manifest renderer for desktop sidebar and mobile offcanvas navigation.
- `resources/views/layouts/student.blade.php` now uses the manifest renderer for desktop sidebar and mobile offcanvas navigation, and the Student manifest was expanded to preserve the existing academics, finance, career, support, and settings workflows.
- `resources/views/layouts/teacher.blade.php` now uses the manifest renderer for desktop sidebar and mobile offcanvas navigation, and the Teacher manifest was expanded to preserve timetable, attendance, marks, materials, assignments, announcements, student, mentoring, feedback, leave, and profile workflows.
- The shared manifest sidebar now supports explicit active route patterns, preserving wildcard active behavior for Teacher subroutes without reintroducing the overly broad fallback that affected Applicant.
- The Accounts and CMC role branches inside `resources/views/layouts/admin.blade.php` now use the manifest renderer for both desktop and mobile sidebar sections. Their manifests preserve fee collections, admission payments, outstanding, reconciliation, reports, placement drives, companies, career events, placement stats, internships, alumni, and analytics links.
- The Dean Academics branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The Dean manifest preserves Dean OS, legacy dashboard, command center, governance, branch operations, student overview, curriculum, exams, approvals, reports, hostel/library, analytics, AICTE, and placement stats links.
- The Exam Cell / CoE branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The CoE manifest preserves CoE OS, legacy Exam Cell dashboard, CoE workspace, Academics Governance, exam readiness, marks/results, hall tickets, all exams, schedule exam, legacy results, transcripts, reports, hall-ticket admin, marks appeals, and anomaly log links.
- The Program Chair / PMC branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The PMC manifest preserves PMC Command, legacy dashboard, PMC workspace, PMC Operating, Program Leadership, Academics Governance, planning/readiness, curriculum, timetable/course allocation, student-success, faculty/workload, approvals, reports, and legacy chair links.
- The IQAC branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The IQAC manifest uses the real `iqac.head@college.com` demo user and preserves IQAC OS, IQAC Workspace, Academics Governance, OBE Readiness, OBE Framework, attainment, feedback quality, audit compliance, and reports links.
- The Program Leadership branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The Program Leadership manifest preserves Program Workspace, Program Leadership, portfolio, student success, course delivery, student monitoring, quality signals, reports, and legacy Program Reports links.
- The Admission branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The Admission manifest preserves Dashboard, Command Center, Workbench, Calling Desk, Counsellor Desk, Quick Search, applicants, document/payment queues, assessment scheduling, committee board, merit/offer links through the first active program, enrollment, seat control, handoff, sessions, leads, follow-up calendar, analytics, reports, bulk communication, consent/safety, integration health, and refunds. Existing generic Department Controls still provide hierarchy/governance links for allowed Admission users without duplicating them in the role menu.
- The HOD and Director branches inside `resources/views/layouts/admin.blade.php` now use the manifest renderer for both desktop and mobile sidebar sections. The HOD manifest preserves dashboard, faculty roster/workload, leave approvals, department performance, student grievances, and approvals. The Director manifest preserves dashboard, programs, reports, analytics, institutional KPI, and AICTE report links.
- The Admin branch inside `resources/views/layouts/admin.blade.php` now uses the manifest renderer for both desktop and mobile sidebar sections. The Admin manifest preserves setup, timetable, people, Admission, assessments, applicants, leads, communication, Academics, exams, finance, operations, placement, approvals, access-control/settings, and reports links while removing duplicated hardcoded Library/sidebar markup.
- The Teacher/Faculty branch inside `resources/views/layouts/admin.blade.php` now uses the same Teacher manifest renderer as the dedicated Teacher layout for both desktop and mobile sidebar sections, preserving Course Delivery pages that extend the shared admin shell.
- Applicant active-state matching was tightened to exact named routes after browser verification caught multiple active links on `/applicant/dashboard`.
- Browser verification on `localhost:8001/applicant/dashboard` confirmed desktop sidebar groups (`Command`, `Daily Work`, `Track`), only the Dashboard link active, no debug traces, no console logs, scrollable sidebar body, and mobile offcanvas open/scroll behavior. The Browser plugin in this environment did not support screenshot capture commands, so evidence is DOM/interaction/console based.
- Parent shell verification is covered by focused portal tests plus desktop/mobile smoke; direct Browser binding was unavailable after the Node REPL reset during this slice.
- Student shell verification is covered by focused portal tests plus desktop/mobile smoke; the shared component preserves conditional Official Transcript visibility through a manifest condition.
- Teacher shell verification is covered by focused portal tests plus desktop/mobile smoke, including a shared-admin-shell Course Delivery page.
- Accounts/CMC shell verification is covered by focused admin/frontend tests plus desktop/mobile smoke.
- Dean shell verification is covered by focused Dean/frontend tests plus desktop/mobile smoke.
- CoE/Exam shell verification is covered by focused CoE/frontend tests plus desktop/mobile smoke.
- PMC shell verification is covered by focused PMC/frontend tests plus desktop/mobile smoke.
- IQAC and Program Leadership shell verification is covered by focused IQAC/Program Leadership/frontend tests plus desktop/mobile smoke.
- Admission shell verification is covered by focused Admission/frontend tests plus desktop/mobile smoke.
- HOD and Director shell verification is covered by focused legacy academic shell tests plus desktop/mobile smoke.
- Verification: focused `PortalFrontendBetaReadinessTest` + `FrontendReadinessTest` passed 17 tests / 1310 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 95 tests / 2315 assertions; `npm run frontend:smoke:mobile` passed 24 tests / 1180 assertions; full `php artisan test` passed 1396 tests / 10712 assertions.

## Admission Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- Command Center KPI cards now link to applicants, attention queues, calling desk, and forecasting source pages.
- Counsellor Desk metrics now link to calling, applicant blockers, assessment control, and reminders instead of looping back to the same page.
- Calling Desk metrics now link to counsellor performance, callback reminders, and parent journeys.
- Calling Desk save/skip actions are split into stable separate forms instead of adjacent nested form markup.
- Assessment Control Room metric cards link to evaluator scoring, assessment scheduling, and sessions where relevant.
- `AdmissionFrontendBetaReadinessTest` covers primary Admission operating pages, source-linked metrics, missing debug traces, placeholder links, mojibake, and broken form markup.
- Verification: `AdmissionFrontendBetaReadinessTest` passed 3 tests / 64 assertions; adjacent Admission/frontend regression passed 32 tests / 783 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 57 tests / 775 assertions with Admission checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1356 tests / 9153 assertions.

## Academics Dean OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- Dean v0.08 generic operating surfaces now apply request-backed search, status, severity, program, owner, sorting, direction, and pagination through `AcademicDeanOperatingRecordService`.
- Faculty workload, faculty performance, mentoring, student success, interventions, curriculum governance, exam readiness, quality command, audit evidence, OBE plans, induction, and onboarding pages inherit the same filterable/sortable/exportable operating table.
- The operating surface UI now includes compact filter controls, sortable table headers, visible filter summaries, export-current-view links that preserve query filters, source-linked KPI cards, and operational empty states.
- `AcademicsDeanFrontendBetaReadinessTest` covers primary Dean OS pages, request-backed filters/sort/export URL behavior, debug-trace absence, placeholder links, mojibake, and broken form markup.
- Verification: `AcademicsDeanFrontendBetaReadinessTest` passed 3 tests / 67 assertions; Dean/frontend adjacent regression passed 26 tests / 722 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 60 tests / 842 assertions with Admission and Dean beta checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1359 tests / 9220 assertions.

## PMC OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- PMC Command KPI cards now clearly show source-link affordance and the overdue-actions metric now links to a real `due=overdue` filter instead of an unsupported `status=overdue` query.
- `AcademicPmcV004Service` now applies `due=overdue` filtering to operating records, matching the UI and command metric behavior.
- PMC v0.04 operating surfaces now use constrained status and due filters, query-preserving export links, source-linked summary cards, and an operational empty state.
- `AcademicsPmcFrontendBetaReadinessTest` covers primary PMC command/v0.04/v0.041 surfaces, source-linked command metrics, request-backed filtering/export URL behavior, debug-trace absence, placeholder links, mojibake, and broken form-markup checks.
- Verification: `AcademicsPmcFrontendBetaReadinessTest` passed 3 tests / 90 assertions; adjacent PMC/frontend regression passed 23 tests / 860 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 63 tests / 932 assertions with Admission, Dean, and PMC beta checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1362 tests / 9310 assertions.

## CoE / Exam OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- CoE section routes now pass request filters into `AcademicCoeOperatingService`, so source-list search and status filters are backed by the service instead of static table text.
- CoE source-list pages now include compact search/status controls, visible filter summaries, export-current-view links, accessible table captions, and operational empty states.
- CoE section metric cards no longer use `href="#source-list"` placeholder anchors and now expose a visible source-link affordance.
- `AcademicsCoeFrontendBetaReadinessTest` covers primary CoE operating pages, request-backed hall-ticket source filtering, debug-trace absence, placeholder links, mojibake, and broken form-markup checks.
- Verification: `AcademicsCoeFrontendBetaReadinessTest` passed 3 tests / 53 assertions; adjacent CoE/frontend regression passed 18 tests / 639 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 66 tests / 985 assertions with Admission, Dean, PMC, and CoE beta checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1365 tests / 9363 assertions.

## IQAC OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- IQAC section routes now pass request filters into `AcademicIqacOperatingService`, so source-list search and status filters are service-backed.
- IQAC source-list pages now include compact search/status controls, visible filter summaries, export-current-view links, accessible table captions, and operational empty states.
- IQAC section metric cards no longer use `href="#source-list"` placeholder anchors and now expose a visible source-link affordance.
- `AcademicsIqacFrontendBetaReadinessTest` covers primary IQAC operating pages, request-backed attainment source filtering, debug-trace absence, placeholder links, mojibake, and broken form-markup checks.
- Verification: `AcademicsIqacFrontendBetaReadinessTest` passed 3 tests / 48 assertions; adjacent IQAC/frontend regression passed 17 tests / 632 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 69 tests / 1033 assertions with Admission, Dean, PMC, CoE, and IQAC beta checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1368 tests / 9411 assertions.

## Program Leadership OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- Program Leadership section routes now pass request filters into `AcademicProgramLeadershipService`, so source-list search and status filters are service-backed.
- Program Leadership source-list pages now include compact search/status controls, visible filter summaries, export-current-view links, accessible table captions, and operational empty states.
- Program Leadership section metric cards no longer use `href="#source-list"` placeholder anchors and now expose a visible source-link affordance.
- `AcademicsProgramLeadershipFrontendBetaReadinessTest` covers primary Program Leadership pages, request-backed student-success filtering, debug-trace absence, placeholder links, mojibake, and broken form-markup checks.
- The batch factory now generates deterministic unique default batch names alongside unique codes, preventing a full-suite flake where two application-window batches could share the same display name.
- Verification: `AcademicsProgramLeadershipFrontendBetaReadinessTest` passed 3 tests / 48 assertions; adjacent Program Leadership/frontend regression passed 20 tests / 635 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 72 tests / 1081 assertions with Admission, Dean, PMC, CoE, IQAC, and Program Leadership beta checks included; `npm run frontend:smoke:mobile` passed 10 tests / 560 assertions; full `php artisan test` passed 1371 tests / 9459 assertions.

## Course Delivery OS Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- Course Delivery section routes now pass request filters into `AcademicCourseDeliveryService`, so source-list search and status filters are service-backed.
- Course Delivery source-list pages now include compact search/status controls, visible filter summaries, export-current-view links, accessible table captions, and operational empty states.
- Course Delivery section metric cards no longer use `href="#source-list"` placeholder anchors and now expose a visible source-link affordance.
- Course Delivery dashboard item aggregation now uses plain collections before merging mapped item arrays, preventing all-scope Admin/Dean users from hitting a live Service Error when Course Delivery is rolled into Dean OS branch health.
- `AcademicsCourseDeliveryFrontendBetaReadinessTest` covers primary Course Delivery pages, request-backed attendance-intervention filtering, debug-trace absence, placeholder links, mojibake, and broken form-markup checks.
- Verification: latest focused Dean/Course Delivery frontend regression passed 9 tests / 162 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 97 tests / 2455 assertions with all current frontend beta checks included; `npm run frontend:smoke:mobile` passed 24 tests / 1180 assertions; full `php artisan test` passed 1401 tests / 10873 assertions; live localhost browser recheck passed for Admin dashboard, Admission dashboard, Dean OS, PMC command, CoE, IQAC, Program Leadership, Course Delivery, Accounts, and CMC with no service-error/debug text or console errors.

## Student / Teacher / Parent / Applicant Portal Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- `PortalFrontendBetaReadinessTest` now opens the seeded Student, Teacher, Parent, and Applicant primary portal routes without debug traces, including the student resume builder.
- Portal dashboard tests assert real daily-work links for student timetable/attendance/results/fees, teacher timetable/attendance/assignments, parent children/notices, and applicant application/checklist/documents/fees.
- Primary portal view files are checked for placeholder action links, JavaScript placeholder links, broken adjacent form markup, mojibake, framework-brand labels, and debug-trace text.
- Student resume builder copy now uses product-neutral skill examples and ASCII action/fallback characters.
- The portal beta test is included in `npm run frontend:smoke`.
- Verification: focused `PortalFrontendBetaReadinessTest` passed 8 tests / 466 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 95 tests / 2386 assertions with all current frontend beta checks included; `npm run frontend:smoke:mobile` passed 24 tests / 1180 assertions; full `php artisan test` passed 1396 tests / 10783 assertions.

## Admin / Operations Beta-Readiness Slice

Status: `Release-ready`

Evidence:

- Admin frontend navigation manifest now includes compact grouped entry points for Command, Governance, Timetable, Students/Applicants, Admission, Assessments, Leads, Communication, Academics, Exams, Finance, Operations, Placement, Approvals, Settings, and Reports.
- User-facing admin settings, role-permission, and API documentation copy now use product-neutral framework wording instead of framework-brand labels.
- Hostel Allocations no longer 500s from an inline Blade `@json` expression; room selector data is prepared as a simple PHP value before JSON encoding.
- Admin Fee Collection no longer renders mojibake currency/separator text and is included in seeded open-page coverage.
- `AdminOperationsFrontendBetaReadinessTest` opens seeded Admin, Accounts, and CMC primary Admin/Operations routes without debug traces.
- Admin/Operations dashboard tests assert real workflow links for admin actions, library circulation, hostel workflows, accounts queues/reports, and CMC drives/events/analytics.
- Primary Admin/Operations view files, including API documentation and fee collection, are checked for placeholder action links, JavaScript placeholder links, broken adjacent form markup, mojibake, debug-trace text, and visible framework trace labels.
- The Admin/Operations beta test is included in `npm run frontend:smoke`.
- Verification: focused `AdminOperationsFrontendBetaReadinessTest` passed 5 tests / 408 assertions; `npm run frontend:build` passed; `npm run frontend:smoke` passed 95 tests / 2445 assertions with all current frontend beta checks included; `npm run frontend:smoke:mobile` passed 24 tests / 1180 assertions; full `php artisan test` passed 1396 tests / 10842 assertions.
