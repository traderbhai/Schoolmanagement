# Commercial Readiness Audit

Last updated: 2026-06-13

## Purpose

This document turns the broad launch goal into an executable product-quality program for the Schoolmanagement college ERP.

The goal is not only "more screens." The app must become:

- Easy to use for every role.
- Complete enough for real college operations.
- Reliable, secure, scalable, and maintainable.
- Ready for a commercial launch with clear verification gates.

## Evidence Snapshot

Current codebase evidence:

- Laravel app root: `college-mgmt`.
- Route surface: 840 registered routes.
- Route distribution:
  - `admin`: 292
  - `admission`: 134
  - `student`: 87
  - `program-chair`: 72
  - `academic`: 59
  - `cmc`: 33
  - `teacher`: 31
  - `exam-cell`: 20
  - `applicant`: 20
  - `hod`: 14
  - `accounts`: 10
  - `dean`: 8
  - `parent`: 6
  - `director`: 3
- Models: 128.
- Controller files: 169.
- Blade views: 480+.
- Feature tests: 51 files.
- Unit tests: 4 files.
- Last full test run: 349 tests passed, 1157 assertions.
- Build/audit status:
  - `npm run build`: passed.
  - `npm audit --audit-level=critical`: passed.
  - `composer audit`: passed.

## Current Strengths

- Broad role coverage already exists across admission, academics, accounts, placement, student, teacher, parent, exam cell, HOD, dean, program chair, director, and admin.
- Admission workflow is unusually complete: lead capture, applicant CRM, documents, payments, selection, merit list, offers, waitlist, enrollment, and reporting.
- Academic lifecycle is represented: programs, batches, terms, subjects, attendance, exams, results, promotions, OBE, transcripts, scholarships, timetable, materials, quizzes, assignments, mentorship, grievances, library, hostel, placements, alumni.
- The repo includes useful developer context and route/workflow documentation.
- Current automated tests pass, and dependency audits are clean.

## Major Risks Before Commercial Launch

### P0: UX Consistency And Navigation

The app has many role-specific portals, but the UI appears to be assembled over many modules. `layouts.admin.blade.php` is very large, and the project mixes Bootstrap-style views with Tailwind utility classes in some places.

Risk:

- Users may feel they are using different apps depending on their role or module.
- Navigation may be overwhelming, especially for admin/admission users with hundreds of routes.
- Shared UI behavior such as filters, tables, buttons, alerts, empty states, and forms may be inconsistent.
  - Status: started; the admission enrollment confirmation screen now uses explicit readiness states, actionable blocked-state guidance, missing mandatory document details, and a disabled submit control when enrollment gates are incomplete.
  - Status: started; shared admin, teacher, student, and parent topbars now fall back to the view title before defaulting to "Dashboard," so non-dashboard operational pages show accurate context.

Required outcomes:

- A single design system for all role portals.
- Consistent page header, action bar, filters, tables, pagination, empty states, form layout, validation, destructive actions, and success/error feedback.
- Role-specific dashboards that show today's work first, not just summary cards.
- Mobile and tablet usability for student/applicant/parent/teacher flows.
  - Status: started; browser QA verified the admission enrollment readiness screen at a 390px mobile viewport with no horizontal overflow and a usable enabled/disabled enrollment action.

### P0: Role Workflow Verification

Route count proves feature breadth, not workflow completeness. Each role needs tested end-to-end paths.

Required role workflow gates:

- Applicant: apply, complete application sections, upload documents, pay fees, track status, accept/decline offer.
  - Status: started; public application registration now preserves program batch, applicant name/email/phone, authenticates the applicant, increments window capacity, and can be tracked immediately with application number plus email.
  - Status: started; public `/apply` now lists only open, available application windows and shows deadline, batch, and remaining capacity before the applicant starts.
  - Status: started; public status tracking now gives status-specific next actions for draft, review, shortlisted, selected, rejected, withdrawn, and not-found states.
  - Status: started; applicant portal registration-fee submission now has an applicant-owned route and form instead of linking applicants to a staff-only admission route.
  - Status: started; authenticated applicant status now gives status-specific next actions for registration-fee completion, document review, pending fee verification, issued offers, and completed enrollment.
  - Status: started; submitted applicants without legacy registration-fee records now see review-stage guidance instead of a dead-end fee-submission CTA.
- Admission Head/Officer: manage leads, verify documents, verify payments, score sessions, generate offers, enroll students.
- Admin: manage academic setup, people records, student services, document requests, admissions, fees, reports, and operational controls.
  - Status: started; admin now has a student document request queue with status/type/program/student filters, request KPIs, approve/reject actions, ready-file upload, staff notes, and secure staff downloads.
  - Status: started; admin document request approvals, rejections, and ready uploads now notify students through in-app notifications and queued email.
  - Status: started; admin now has a student scholarship application queue with status/program/student filters, review KPIs, shortlist, approve, reject, and disbursement actions.
  - Status: started; admin library circulation now has regression-covered dashboard/catalog/detail pages, copy-based issue flow, return flow, membership-limit enforcement, and corrected route contracts.
  - Status: started; admin hostel allocation now supports vacating and reallocating the same room/bed despite SQLite's non-partial unique constraint, and blocks invalid bed numbers or maintenance-room allocation.
  - Status: started; admin hostel complaints now receive student-submitted room-linked complaints from the student portal, keeping warden follow-up in the existing queue.
  - Status: started; admin hostel fee demands can now be generated monthly from active allocations, skip duplicates and zero-fee rooms, and move pending demands to paid or waived states.
  - Status: started; admin hostel allocation now supports room transfers with target room/bed validation, maintenance-room blocking, occupied-bed blocking, source allocation closure, and target room status updates.
  - Status: started; admin transport management now supports route, stop, vehicle, and student transport assignment setup with duplicate active-assignment and vehicle-capacity guards.
  - Status: started; admin asset management now supports categories, asset register entries with vendor/purchase/location details, assignment to users, assignment returns, and status/value KPIs.
  - Status: started; admin consumable inventory now supports stock item setup, reorder levels, receiving stock, issuing stock, movement history, and over-issue prevention.
- Student: view dashboard, timetable, attendance, fees, results, materials, assignments, documents, grievances, placements, scholarships.
  - Status: started; student dashboard now surfaces a "Today's Priority" action for low attendance, fee dues, upcoming assignments, today's timetable, or a clear no-urgent-action state.
  - Status: started; student dashboard fee outstanding now uses active fee demands and includes penalties instead of falling back from a non-existent demand amount column.
  - Status: started; student placement pages now surface a "Placement Priority" action for near deadlines, in-progress applications, selected offers, available drives, missing profile setup, or clear no-drive states.
  - Status: started; students can no longer apply by direct POST to completed/cancelled or expired placement drives, and placement application tracking now explains each status next step.
  - Status: started; student internship pages now surface an "Internship Priority" summary and show ongoing/completed internship details, supervisor contacts, stipend, feedback, and ratings with clean status labels.
  - Status: started; student alumni network now surfaces verified same-program network guidance, verified total counts, and clean alumni profile cards with employer, location, feedback, and LinkedIn access.
  - Status: started; student grievances now surface a "Grievance Priority" next action for escalated, open, resolved, or no-active-grievance states, and students can add follow-up comments or close resolved issues cleanly.
  - Status: started; student document requests now surface a "Document Priority" action for ready, rejected, in-progress, or no-active-request states.
  - Status: started; student document requests now require a purpose, block duplicate open requests for the same document type, and expose ownership-checked downloads for admin-fulfilled ready files.
  - Status: started; students now receive document request status updates with direct portal links when staff approve, reject, or upload ready documents.
  - Status: started; student scholarship UX now includes a required reason field, clean scheme details, program eligibility enforcement, seat availability checks, and application tracking through review, approval, rejection, and disbursement.
  - Status: started; student library overdue day calculations now use positive overdue days, so borrowed-book status and fines are understandable instead of showing negative values.
  - Status: started; student hostel outpass requests now block duplicate open requests so students cannot create overlapping pending/approved permissions.
  - Status: started; students with active hostel allocations can now submit and track hostel complaints with category, priority, room context, status, and resolution notes.
  - Status: started; students now see hostel fee demands and pending hostel dues alongside academic fee status.
  - Status: started; students now have a transport page showing active route, pickup/drop stop, vehicle, driver contact, fee, notes, and assignment history.
- Teacher: dashboard, timetable, attendance marking, materials, assignments, grading, mentoring, leave requests.
  - Status: started; teacher dashboard now surfaces a "Today's Teaching Priority" action for pending grading, attendance marking, active assignments, or a clear no-urgent-action state.
  - Status: started; teacher dashboard class counts now use the controller's filtered today-classes data instead of recomputing from the raw timetable grid.
- Program Chair: curriculum, timetable builder, approvals, at-risk students, faculty workload, reports.
  - Status: started; program chair dashboard now surfaces a single "Program Chair Priority" action for missing program assignment, approvals, leave/condonation requests, grievances, at-risk students, timetable publication, curriculum readiness, or reports.
  - Status: started; program chair dashboard and approvals now scope applicant approval counts/lists/actions to the chair's assigned programs instead of showing global program-chair approvals.
  - Status: started; program chair dashboard no longer uses the missing examResults relation when exams exist.
- Exam Cell: exam creation, marks entry, publishing, hall tickets, appeals, anomaly logs.
  - Status: started; exam cell dashboard now surfaces a single "Exam Cell Priority" action for open anomalies, pending marks appeals, completed exams needing result entry, upcoming hall-ticket preparation, or first exam scheduling.
  - Status: started; exam cell dashboard "Schedule Exam" actions now route through the exam-cell workflow instead of the admin exam route.
- Accounts: fee collection, outstanding, reconciliation, demand letters, exports.
  - Status: started; accounts dashboard now uses fee demands as the source of truth for billed, outstanding, overdue, and penalty KPIs instead of legacy fee-structure totals.
  - Status: started; accounts dashboard now surfaces a "Finance Priority" action for admission payment verification, overdue demand follow-up, scholarship disbursement, outstanding balances, or reconciliation.
  - Status: started; accounts outstanding page and CSV export now use active fee demands plus penalties instead of legacy fee-structure totals, and show open/overdue demand counts.
  - Status: started; accounts reports now use fee-demand totals for billed, outstanding, penalties, collection percentage, and batch/program summaries instead of legacy fee structures.
- CMC/Placement: companies, drives, applications, placements, internships, alumni, analytics.
  - Status: started; CMC dashboard now surfaces a single "CMC Priority" action for recruiter pipeline setup, open applications, drive scheduling, low placement rate, career-event planning, or analytics review.
  - Status: started; CMC drive creation, editing, dashboards, analytics, and Director placement-drive counts now use the real drive lifecycle (`upcoming`, `ongoing`, `completed`, `cancelled`) instead of legacy `open`/`active` states.
  - Status: started; CMC application review now supports the real placement application statuses, including `interview`, and validates offered packages as numeric values.
  - Status: started; placement statistics now use selected placement applications, offered packages, and drive-linked companies instead of missing placement columns.
  - Status: started; CMC internship management now surfaces an "Internship Priority" action for overdue planned completions, ongoing internship monitoring, or new internship registration.
  - Status: started; internship completion now rejects invalid end dates and prevents already-completed records from being completed again.
  - Status: started; CMC alumni management now surfaces an "Alumni Priority" action for unverified profiles, graduated students missing alumni records, initial network setup, or routine review.
  - Status: started; alumni profiles now capture country cleanly, expose verification KPIs, avoid duplicate verification churn, and show salary/location outcomes with clean formatting.
- Parent: children, attendance, fees, results, notices.
  - Status: started; parent dashboard now surfaces a "Parent Priority" next action for low attendance, overdue fee demands, open fee balances, or a clear no-urgent-action state.
  - Status: started; parent dashboard and fee detail page now use active fee demands plus penalties for balances instead of legacy fee-structure totals.
  - Status: started; parent fee page now shows demand-level due dates, penalties, open totals, overdue status, and payment history in one workflow.
- Director/Dean/HOD: dashboards, approvals, reporting, escalations, program/department oversight.
  - Status: started; Director dashboard now surfaces a single "Director Priority" action for overdue academic approvals, pending academic approvals, low attendance, low enrollment, placement-drive gaps, hierarchy setup, or executive reports.
  - Status: started; Director portal summaries now render clean fee collection values with `Rs.` formatting instead of corrupted currency/dash characters.
  - Status: started; Dean dashboard now surfaces a single "Dean Priority" action for overdue approvals, pending approvals, grievances, academic risk, program setup, or academic review.
  - Status: started; Dean program health now aggregates exam results through exams instead of relying on a missing Program examResults relation.
  - Status: started; Dean approval actions now reject non-Dean/non-pending/non-applicant approvals and avoid duplicate program-chair pending approvals after offer generation.
  - Status: started; HOD dashboard now surfaces a single "HOD Priority" action for missing department profile, department approvals, leave requests, low attendance, setup gaps, or performance review.
  - Status: started; HOD dashboard, approval lists, approval actions, leave lists, and leave review actions now scope HOD users to their teacher department instead of falling back to global data.
  - Status: started; HOD exam and attendance analytics now use existing relationships (`results`, `timetableEntry.subject`) instead of missing relations/columns.

### P0: Production Configuration

Current local setup uses SQLite. That is acceptable for local development but not a commercial deployment target for a serious ERP.

Required outcomes:

- Production database target selected and tested, preferably MySQL/PostgreSQL.
- Environment templates for local, staging, and production.
  - Status: started; `.env.production.example` now documents production-safe defaults for app mode, debugging, MySQL, Redis sessions/cache/queues, S3 storage, SMTP mail, and secrets.
- Queue worker strategy for mail, notifications, reports, and long-running jobs.
  - Status: started; `PRODUCTION_READINESS_CHECKLIST.md` now documents the supervised queue worker command, restart process, failure monitoring, and Redis production guidance.
- Scheduler/cron strategy for reminders, penalties, escalations, overdue actions, and cleanup.
  - Status: started; production cron setup and the six registered operational schedule commands are documented and regression-tested.
- Storage strategy for uploaded documents, receipts, photos, PDFs, and backups.
  - Status: started; production guidance now distinguishes private object storage from public assets and requires object storage backup/versioning.
- Backup and restore process documented and tested.
  - Status: partially documented; the runbook requires backups before high-risk operations and restore verification, but an automated restore drill is still needed.

### P0: Security And Compliance

College ERP data includes sensitive student, parent, applicant, academic, financial, and document data.

Required outcomes:

- Role and permission matrix verified route-by-route.
- Program/department data scoping verified for staff roles.
- File upload validation and private storage controls verified.
- Audit logs for important actions: admissions, fee verification, result publication, role changes, document decisions, approvals.
- CSRF, auth, authorization, rate limiting, password reset, session lifetime, and login throttling verified.
  - Status: started; login throttling and successful-login throttle reset are now covered by regression tests.
- Browser security headers verified for web, redirects, and API JSON responses.
- Generic Breeze self-registration is disabled; unauthenticated users are directed to the admissions `/apply` workflow so bare accounts are not created.
- PII handling rules for exports, logs, email, and PDFs.
  - Status: started; admin grievance management now uses the real grievance schema fields (`title`, `resolution_notes`) and valid status lifecycle (`open`, `under_review`, `escalated`, `resolved`, `closed`) instead of stale aliases.
  - Status: started; student document downloads now enforce owner access, ready status, output path presence, and private-disk file existence before serving files.
  - Status: started; admin document request downloads now enforce file presence on the private disk before serving fulfilled student documents.
  - Status: started; private student document downloads now stream with explicit generic content type to avoid expensive MIME probing and memory exhaustion under constrained PHP limits.
  - Status: started; library circulation now keeps `books.available_copies`, `book_copies.is_available`, and active issue records synchronized when issuing and returning copies.
  - Status: started; hostel outpass approval, rejection, and return actions are state-guarded so staff cannot return pending requests or reject already-approved requests.
  - Status: started; hostel fee demand status transitions are guarded so paid or waived demands cannot be changed through pending-only actions.

### P1: Test Coverage Depth

349 passing tests is useful, but still small for an 840-route ERP.

Required outcomes:

- Smoke tests for all role dashboards and top-level routes.
- End-to-end feature tests for each critical workflow.
  - Status: started; admission-to-enrollment now has HTTP-level regression coverage for applicant payment submission, payment verification, mandatory document verification, enrollment confirmation, student creation, role transition, queued enrollment email, and activity logs.
  - Status: started; public application registration through status tracking now has regression coverage.
  - Status: started; public status tracker next-action messaging now has regression coverage.
  - Status: started; applicant registration-fee submission and staff-side fee recording now have regression coverage.
  - Status: started; applicant portal status guidance now has regression coverage for draft, shortlisted pending-payment, selected issued-offer, and enrolled states.
  - Status: started; submitted-applicant dashboard fee guidance now has regression coverage.
- Authorization tests for cross-role access denial.
- Form validation tests for high-risk operations.
- Regression tests for known SQLite/Laravel route ordering pitfalls.
- Factory stability for unique schema fields.
  - Status: started; `ProgramFactory` now generates deterministic unique program codes/abbreviations so high-volume feature tests do not intermittently violate the `programs.code` unique index.

### P1: Performance And Scalability

The app needs performance checks with realistic data volume.

Required outcomes:

- Seeded load profile: at least 10k students/applicants, multi-year fees, attendance, results, notices, payments.
- Query profiling for dashboards, reports, search, attendance, results, and fee pages.
- Pagination enforced on large lists.
- Index review for foreign keys, status filters, date filters, and search-heavy columns.
- Cached KPI services for dashboards and reports where appropriate.

### P1: Commercial UX Details

High-quality apps are won in details, especially for operational workflows.

Required outcomes:

- Empty states with next action.
- Confirmation modals for destructive or irreversible actions.
- Clear pending/rejected/approved statuses with reasons and timestamps.
- Critical blocked workflows explain the next action instead of allowing confusing partial submissions.
  - Status: started; admission enrollment now explains selected status, payment, mandatory document, and duplicate-enrollment gates before student creation.
  - Status: started; public status tracker now explains the applicant's next action rather than only showing a passive status badge.
  - Status: started; applicant dashboard registration-fee CTA now routes to an applicant-owned form and no longer points to a staff-only admission page.
  - Status: started; applicant status and fee screens now avoid corrupted visible currency/dash characters and show actionable status guidance.
  - Status: started; browser QA caught and fixed submitted applicants seeing an unusable registration-fee CTA after submission.
  - Status: started; student dashboard now gives a single priority next action and keeps fee/attendance/assignment links visible from the first dashboard viewport.
  - Status: started; teacher dashboard now gives a single priority next action for grading, attendance, assignments, materials, or timetable review.
  - Status: started; accounts dashboard now gives a single priority next action for verification, overdue dues, scholarships, outstanding follow-up, or reconciliation.
  - Status: started; accounts outstanding follow-up now shows demand-based balances, oldest due date, open demand count, overdue count, and last payment date.
  - Status: started; accounts reports now include demand-based billed, collected, outstanding, and collection percentage views for both programs and batches.
  - Status: started; parent portal now gives a single priority next action and demand-based fee balance guidance from the first dashboard viewport.
  - Status: started; exam cell dashboard now gives a single priority next action for anomalies, appeals, marks entry, hall tickets, or scheduling.
  - Status: started; program chair dashboard now gives a single priority next action and prevents unscoped program-chair approval access.
  - Status: started; HOD dashboard now gives a single priority next action and prevents unscoped department approval/leave access.
  - Status: started; Dean dashboard now gives a single priority next action and guards final approval actions.
  - Status: started; Director dashboard now gives a single executive priority next action and keeps configured portal summaries readable.
  - Status: started; CMC dashboard now gives a single placement-office priority next action, and placement stats show selected placements, package averages, active drives, and top recruiters from valid schema relationships.
  - Status: started; student placement UX now highlights deadlines, eligibility, application availability, and next-step guidance from the first placement page.
  - Status: started; internship staff/student UX now shows priorities, clean currency/duration/status labels, completion validation errors, and feedback/rating outcomes.
  - Status: started; alumni staff/student UX now shows priorities, verification coverage, clean salary/location labels, same-program defaults, and verified-only student network results.
  - Status: started; grievance staff/student UX now shows priority cards, active/urgent/overdue/resolved counts, clean status badges, schema-correct resolution notes, and clearer student close/acknowledge behavior.
  - Status: started; student document UX now has clean status labels, required purpose feedback, duplicate-request guidance, and real download links instead of placeholder actions.
  - Status: started; admin student document UX now gives staff a single queue for approval, rejection notes, fulfillment upload, and ready-file download instead of leaving student requests as passive records.
  - Status: started; student document status changes now trigger both portal notifications and email, reducing manual status-checking.
  - Status: started; scholarship schemes now count both applicant awards and enrolled-student approved/disbursed applications against available seats, preventing over-awards.
  - Status: started; student scholarships now have an end-to-end enrolled-student workflow from application reason to staff review, approval, notification, and disbursement.
  - Status: started; library issue/return UX now uses the actual admin route names and controller contract, and fine messages use stable `Rs.` formatting.
  - Status: started; hostel bed lifecycle now preserves operational continuity by reactivating vacated bed records for new students instead of failing on stale unique bed rows.
  - Status: started; student hostel support now has a closed loop for maintenance, hygiene, food, security, ragging, and other complaints instead of only outpass requests.
  - Status: started; hostel fee UX now gives admins a generate/filter/action queue and gives students readable hostel dues with stable `Rs.` formatting.
  - Status: started; hostel allocation UX now lets staff transfer students between rooms from the active allocation queue instead of using manual vacate/reallocate workarounds.
  - Status: started; transport UX now gives admin staff one operational setup/assignment screen and gives students self-service route, pickup, vehicle, and contact details.
  - Status: started; asset UX now gives operations staff one register for asset setup, filtering, assignment, active handovers, and returns instead of tracking equipment outside the system.
  - Status: started; consumable stock UX now shows low-stock status, stock movements, receive/issue actions, and clear quantity guardrails from the asset register.
- Bulk actions with preview and undo/rollback where possible.
- Export buttons explain filters included.
- Form autosave or draft behavior for long applicant/admin forms.
- Accessible labels, keyboard focus, color contrast, and mobile-responsive controls.
  - Status: started; admission enrollment readiness was browser-checked for desktop and mobile width with no horizontal overflow, no console errors, correct contextual heading, and readable gate status.
- Human-readable error pages and permission-denied pages.
  - Status: started; custom 403, 404, and 500 recovery pages now have tests.
- Demo onboarding credentials match documentation across the advertised role list.
- Product branding is consistent in environment defaults and shared layout title fallbacks.
  - Status: started; `APP_NAME`, config fallback, and layout title fallbacks now use `EduManage` and have regression coverage.

### P2: Product Completeness Gaps To Validate

The following real-world ERP needs must be explicitly verified or added:

- Multi-campus/multi-institute support.
- Procurement approvals.
- HR/payroll for faculty and staff.
- Library barcode/RFID workflow.
- LMS-grade content delivery and online exams/proctoring.
- Student ID card generation.
- Biometric attendance integration.
- Payment gateway integration and webhook reconciliation.
- SMS/WhatsApp/email provider integration.
- Accreditation/compliance reporting beyond AICTE where relevant.
- Data import/export migration tools.
- Public website/CMS integration for admissions pages.

Some of these may be out of current launch scope, but they should be consciously accepted, deferred, or built.

## First Implementation Sprints

### Sprint 1: UX Foundation And Navigation

Goal: make the app feel like one commercial product before adding more modules.

Deliverables:

- Audit current layouts visually across admin, student, teacher, applicant, parent.
- Define shared UI tokens and component conventions.
- Refactor large layout concerns into smaller partials/components.
- Standardize page headers, breadcrumbs, action bars, table/filter patterns, alert/empty states.
- Add a global "My Work Today" pattern to dashboards.

Verification:

- Browser screenshots for representative pages in desktop and mobile widths.
  - Status: started; browser screenshots captured for applicant fees, admission enrollment blocked state, admission enrollment ready state, and admission enrollment mobile ready state.
  - Status: started; in-app browser QA verified applicant status, dashboard, documents, and fees at mobile width; screenshot capture timed out in the Browser runtime, so evidence for this pass is DOM, URL, console, and overflow checks.
- No overlapping text, broken nav, inaccessible controls, or inconsistent actions.
- `npm run build`.
- Focused route smoke tests.

### Sprint 2: Role Workflow Smoke Tests

Goal: stop regressions across the 840-route surface.

Deliverables:

- Route inventory tests grouped by role.
- Dashboard access tests for every demo role. Status: started; all major dashboards now have smoke coverage.
- Denial tests for role-specific portals. Status: started; unrelated-role denial checks now cover representative portal boundaries.
- Public apply/track smoke tests. Status: started; public entry pages, active/closed/future/full application windows, successful public registration, batch assignment, applicant profile capture, actionable empty state, and immediate status tracking now have coverage.
- Primary dashboard redirects. Status: started; login, `/`, and `/dashboard` now share one resolver and route every major role to its correct portal, including admission head/officer.

Verification:

- `php artisan test` passes.
- Each role has at least one tested happy-path dashboard.
- Public application and tracking entry points remain accessible.
  - Status: started; status tracking now verifies newly registered public applicants by application number and registered email.
  - Status: started; status tracking now provides recovery actions when no matching application is found.
- Guest users are redirected from protected portals.
- Authenticated users land in the right role dashboard from common entry points.

### Sprint 3: Admission-To-Enrollment Hardening

Goal: make the most commercially important workflow reliable end to end.

Deliverables:

- Applicant apply flow UX pass.
  - Status: started; public application registration now shows intake deadline, batch, and remaining capacity before account creation.
  - Status: started; public apply landing now hides unavailable programs and shows an actionable empty state with status tracking when no intake is open.
  - Status: started; registration-fee details can now be submitted from the applicant portal before final application submission.
- Admission officer queues UX pass.
- Document/payment verification audit trail.
  - Status: started; payment verification and enrollment actions are now asserted through activity log coverage in the admission-to-enrollment path.
- Offer acceptance/decline edge cases.
- Enrollment creation and fee demand verification.
  - Status: started; enrollment now enforces selected status, verified admission payment, and verified mandatory documents in the service layer before creating the student and confirmation record.
  - Status: started; the enrollment UI now mirrors these gates and prevents confirmation until all required checks pass.

Verification:

- End-to-end admission test from application to enrolled student.
  - Status: started; current coverage includes public registration-to-tracking and shortlisted-applicant-to-enrollment paths. A single full public application-form-to-enrollment browser test is still needed.
- Browser check of applicant and admission officer pages.
  - Status: started; browser QA completed for applicant payment and admission enrollment screens, including blocked and ready states. Broader applicant/admission officer workflow screenshots remain needed.

### Sprint 4: Production Readiness

Goal: convert local/demo app assumptions into deployable operations.

Deliverables:

- Production environment checklist.
- Queue/scheduler setup.
- Backup/restore plan.
- Storage and upload policy.
- Error logging and monitoring plan.
- Security checklist and permission matrix audit.

Verification:

- Deployment dry run on staging-like config.
- Dependency audits clean.
- Critical workflows tested after production config changes.

## Design Brief Needed Before UI Redesign

Before implementing a visual redesign across the app, the Product Design workflow needs these decisions:

- Visual direction: keep current Bootstrap-admin style and polish it, or move toward a more modern SaaS/ERP dashboard style.
- Reference source: provide a Figma, screenshot, URL, or named product style to match; otherwise we should create visual options first.
- Interactivity target: full working controls and states for production, or faster static exploration.

Recommended brief:

- Product: commercial college ERP for daily staff/student operations.
- Visual direction: modern, dense, calm SaaS/ERP interface; not a marketing-heavy style.
- Interactivity: full production interactivity for changed screens.
- First visual slice: admin dashboard, student dashboard, applicant application flow, and teacher dashboard.

## Immediate Next Action

Do not attempt to redesign all 480+ views at once. Start with Sprint 1:

1. Capture screenshots of representative role dashboards.
2. Identify concrete UX defects.
3. Define shared UI components.
4. Implement a first pass on the shared layouts and one dashboard per portal.
5. Verify in browser and tests.
