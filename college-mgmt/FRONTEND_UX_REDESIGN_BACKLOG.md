# Frontend UX Redesign Backlog

This is the active control file for making the app easier to use without reopening broad backend feature work.

## Objective

Make every major role workspace compact, professional, task-first, and safe to operate. A page is not considered usable just because it renders; it must tell the user what the page is for, what needs attention, what action is expected, and where the underlying data comes from.

## Current UX Phase

The earlier UX readiness slices proved that pages are safer, source-backed, and less placeholder-heavy. The next phase is role-level usability redesign. Use `WEBSITE_ROLE_UX_REDESIGN_MATRIX.md` as the control file for this phase so we do not confuse "page opens and tests pass" with "a real user can operate the app easily."

Start with Admission Applicant, then Admission Counsellor/Telecaller, then Admission Manager/Head. Continue role-by-role only after each role has a verified landing page, navigation, workflow order, source-linked data, compact layout, and focused evidence.

Latest role-redesign progress: Admission Applicant ownership clarity is verified. Dashboard and checklist now distinguish applicant-owned actions from Admission-team review steps, explain the admission path order, and use a readable batch fallback. Admission Counsellor/Telecaller queue clarity is verified: Counsellor Desk rows use readable missing-data labels and Calling Desk queue rows link directly to the exact lead/applicant source record. Admission Manager/Head supervisor drilldown clarity is verified: Command Center SLA opens the exact queue and Workbench KPI cards open workload, SLA, conversion, and reminder source pages. Student self-service ownership clarity is verified: Student dashboard now labels priority owner/source, uses readable missing-data states, and links the no-classes state to subject registration. Teacher teaching-priority clarity is verified: Teacher dashboard now labels priority owner/source, fixes blank-grid timetable empty-state behavior, and uses readable timetable missing-data labels. Parent child-monitoring clarity is verified: Parent dashboard now labels priority owner/source and uses readable attendance/result/fee/linked-child empty states. Dean Academics command clarity is verified: Dean OS priority and attention rows now expose owner, source, due-date context, and readable fallback labels. PMC command/timetable clarity is verified: PMC Command and Timetable OS now expose owner/source context and non-dead-end empty states for attention, approvals, generation, notifications, and constraints. CoE official-boundary clarity is verified: CoE OS source rows expose owner/source context and no-match lists explain published-result, eligibility, registration, and transcript-readiness boundaries. IQAC quality-evidence clarity is verified: IQAC OS source rows expose owner/source context and no-match lists explain evidence, action, and target-boundary checks. Program Leadership scoped-risk clarity is verified: Program OS source rows expose assigned owner/source context and no-match lists explain scope, source-record, evidence, progress, and escalation checks. Course Delivery faculty-evidence clarity is verified: Course Delivery OS source rows expose owner/source context and no-match lists explain faculty assignment, published timetable, attendance evidence, material updates, feedback signals, and mentor follow-up checks. Accounts finance-queue clarity is verified: Accounts pages expose finance owner/source context, source-list filters, and readable `Rs.` labels instead of mojibake currency/separator text. Admin / Director setup command clarity is verified: Admin dashboard now labels owner/source context, uses readable `Rs.` labels, and no longer throws a 500 from the Upcoming Exams fallback. Operations notice-board workflow clarity is verified: Notice Board now has source filters, workflow guidance, readable visibility states, and create/edit/detail finality guidance. Shared notification inbox clarity is verified: Notification Inbox now has owned-message filters, source labels, filtered empty states, and detail-page read/action guidance. Operations Assets metric-source clarity is verified: Asset KPI cards now link to matching register/status drilldowns or stock sections, with owner/source context in the first viewport. Evidence is recorded in `WEBSITE_ROLE_UX_REDESIGN_MATRIX.md` and `WEBSITE_UX_INVENTORY.md`.

## Execution Rule

Work one role/workflow slice at a time:

1. Inspect current routes, views, controllers, tests, and seeded data for the selected role.
2. Identify real UX blockers only: confusing page purpose, weak empty states, broken/mismatched links, unreadable fallbacks, missing filters, unusable tables, or unclear action sequence.
3. Patch narrowly with source-backed UI guidance and existing backend data.
4. Add focused tests for the exact page/workflow.
5. Run focused and nearest adjacent tests.
6. Update this file, `USER_ROLE_UX_AUDIT.md`, `FRONTEND_COMPLETION_BACKLOG.md`, and `CODEX_PROJECT_CONTEXT.md` only after evidence passes.

Do not run a full app redesign in one step. Do not add placeholder screens.

## Global UX Standards

- Every role dashboard starts with the next action or highest priority queue.
- Every KPI card links to its filtered source list unless it is explicitly summary-only.
- Every growing table has search, filters, visible filter summary, pagination, and readable empty/no-match states.
- Every action-heavy page explains the safe sequence before destructive or final actions.
- Every empty state explains whether the issue is missing setup, role scope, filter mismatch, unpublished data, or no current work.
- Every official output uses readable missing-data labels, ASCII-safe separators, and no debug/placeholders.
- Every sidebar remains scrollable on desktop and mobile.
- Every primary page uses database-backed data or a clear operational empty state.

## Role Slice Order

| Slice | Role / Surface | Current focus | Status |
| --- | --- | --- | --- |
| UX-001 | Admission Applicant | Checklist, status, fees, documents, offers, operations | verified |
| UX-002 | Admission Counsellor / Telecaller | Daily desk, calling desk, reminders, lead/applicant context | verified |
| UX-003 | Admission Manager / Head | Command, workbench, queues, reports, governance | verified |
| UX-003A | Student | Dashboard priority, owner/source context, readable empty states | verified |
| UX-003B | Teacher | Dashboard priority, owner/source context, timetable empty state | verified |
| UX-003C | Parent | Dashboard priority, child monitoring owner/source context | verified |
| UX-003D | Dean Academics | Command priority owner/source context, attention row ownership | verified |
| UX-003E | PMC | Command and timetable owner/source context, non-dead-end blockers | verified |
| UX-003F | CoE / Exam | Official-boundary owner/source context and no-match guidance | verified |
| UX-003G | IQAC | Quality evidence owner/source context and no-match guidance | verified |
| UX-003H | Program Leadership | Scoped program owner/source context and no-match guidance | verified |
| UX-003I | Course Delivery | Faculty delivery owner/source context and no-match guidance | verified |
| UX-003J | Accounts | Finance queue owner/source context and readable money labels | verified |
| UX-003K | Admin / Director | Setup command owner/source context and dashboard fallback fix | verified |
| UX-003L | Operations / Notices | Notice Board workflow filters, visibility state, and finality guidance | verified |
| UX-003M | Shared Notification Inbox | Owned-message filters, source labels, and detail action guidance | verified |
| UX-004 | Dean Academics | Command, planning, reviews, risk, approvals, handoff | verified |
| UX-005 | PMC / Program Leadership | Command, timetable, allocation, source lists, substitutions | verified |
| UX-006 | CoE / IQAC / Course Delivery | Official records, quality, delivery workflows | verified |
| UX-007 | Student / Parent / Applicant portals | Self-service guidance and ownership-safe data | verified |
| UX-008 | Teacher portal | Teaching dashboard, roster, assignments, feedback, leave, timetable | verified |
| UX-009 | Admin / Accounts / CMC / Operations | Setup, finance, placement, library, hostel, transport, assets | verified |
| UX-010 | Global workflow discoverability | Whole-site screen acceptance checklist and high-traffic workflow-first entry strips | in_progress |

## Active Slice

### UX-008: Teacher Portal

Goal: make teacher daily workflows readable and actionable without changing backend scope rules.

Current verified coverage:

- Teacher dashboard sequence guidance.
- Teacher feedback, leave, materials, announcements, assignments, mentor detail, and roster empty states.
- Teacher timetable empty state and fallback labels now explain that only published assigned timetable rows are visible, and missing profile/subject/batch/room data uses readable labels.
- Teacher attendance marking now explains the mark-attendance sequence, missing published-class prerequisites, and student roster allocation blockers, with readable labels and ASCII-safe separators.
- Teacher exam/result entry now explains exam setup, roster review, absent/marks entry, and Exam Cell publication-lock boundaries, with readable official-record labels.
- Teacher assignments now explain the published-subject creation, student publication, active-roster submission tracking, follow-up, and grading sequence, with readable labels instead of dash/mojibake placeholders.
- Teacher materials now explain published-subject upload, file/link/description requirements, student visibility, and publish timing, with readable subject and attachment-state labels.
- Teacher announcements now explain published-subject posting, student course-feed visibility, and pinned-notice intent, with readable subject selector/list labels.
- Teacher student roster now explains published-timetable roster source, exposes search/clear/filter summary controls, handles no-semester/no-roster states, and avoids linking teachers into admin student detail pages.
- Teacher mentor index/detail now explain the mentor follow-up sequence, assigned-mentee source, messages, meetings, attendance and published-result review, and escalation path, with readable fallback labels instead of `N/A` or dash placeholders.
- Teacher Course Feedback now explains teaching-allocation source, anonymous aggregate interpretation, delivery-improvement next steps, and escalation path for recurring low feedback, with readable subject/code fallbacks.
- Teacher Leave create now explains leave type selection, date-overlap blocking, review context, pending-cancellation rules, reviewed-history behavior, and handover/coverage notes before submission.
- Teacher Profile now explains teacher-editable versus admin-owned fields, uses readable missing-data labels, and shows inactive-profile update lock guidance.

Evidence:

- `StudentTimetableWorkflowTest`: `4 tests / 31 assertions` passed.
- `TeacherDashboardGuidanceTest`: `4 tests / 20 assertions` passed.
- `TeacherProfileMissingGracefulTest`: `3 tests / 30 assertions` passed.
- `AttendanceWorkflowTest`: `4 tests / 13 assertions` passed.
- `ExamResultTest`: `7 tests / 23 assertions` passed.
- `TeacherScopeWorkflowTest`: `30 tests / 264 assertions` passed.
- `TeacherStudentListTest`: `3 tests / 26 assertions` passed.
- `StudentMentorWorkflowTest`: `3 tests / 26 assertions` passed.
- `StudentCourseFeedbackWorkflowTest`: `9 tests / 66 assertions` passed.
- `StudentLeaveWorkflowTest`: `17 tests / 131 assertions` passed.
- `InstitutionalProfileUpdateIntegrityTest`: `3 tests / 35 assertions` passed.
- `PortalFrontendBetaReadinessTest`: `13 tests / 667 assertions` passed.
- `PortalDashboardUxGuidanceTest`: `3 tests / 49 assertions` passed.
- `LayoutProfileNavigationTest`: `3 tests / 21 assertions` passed.
- Static teacher timetable, attendance, exam, assignment, materials, announcement, roster, mentor, feedback, leave, and profile scans found no `N/A`, mojibake markers, dash/bullet entities, em dash, or rupee symbol.
- `git diff --check` passed for the Teacher timetable, attendance, exam, assignment, materials, announcement, roster, mentor, feedback, leave, and profile slices.
- Browser opened `/teacher/assignments` in the current non-teacher session and verified a friendly restricted page with no debug text or console errors; positive teacher rendering is covered by PHPUnit.

Remaining non-blocking polish:

- More browser-level positive create/edit checks for attendance, materials, assignments, and timetable drilldowns.
- Richer table-density and saved-view controls if teacher history pages grow.

## Active Slice

### UX-010: Global Workflow Discoverability

Audit high-traffic pages for the page-level answer to:

- What should I do first?
- What changed since yesterday?
- Which records need my attention?
- Which action is safe here?
- Where will this action be reflected?

Current verified coverage:

- `WEBSITE_UX_EXECUTION_PLAN.md` now defines a stricter whole-site UX method, screen acceptance checklist, issue labels, shared UI pattern targets, and evidence requirements.
- `WEBSITE_UX_EXECUTION_PLAN.md` now also defines whole-website execution phases, a per-screen redesign contract, first-viewport rules, and intent-based navigation rules so future frontend work can cover the full app without becoming an endless broad goal.
- Portal navigation now better matches real user intent: Teacher Leave moved into Daily Work, Teacher Feedback moved into Reports, and Parent sidebar now links directly to the first linked child's Attendance, Results, and Fees pages when a child is available.
- Admission sidebar navigation now separates staff communication actions, finance/refunds, management reports, and integration governance instead of grouping Bulk Communication, Consent/Safety, Refunds, Reports, and Integration Health under one Reports section.
- Admin sidebar navigation now separates Teachers, Students, and Parents into a People group while keeping Student Documents, Applications, and Admissions under Students / Applicants.
- Director and HOD sidebar navigation now keeps academic delivery, approvals, and reports in separate intent-based groups instead of mixing report/approval work into delivery/student sections.
- Student sidebar navigation now keeps Academic Summary and Promotion Status under Track, leaving Settings for profile and notification preferences only.
- Accounts sidebar navigation now separates reports from daily finance queues, keeping collections, payments, outstanding balances, and reconciliation under Finance.
- Admin sidebar navigation now places Fee Report under Reports instead of mixing it into daily Finance setup/collection links.
- Admin sidebar navigation now places Integration Health with technical settings/security controls instead of report cards.
- CMC sidebar navigation now places Placement Stats under Reports instead of daily placement operations.
- Admin sidebar navigation now keeps Command to Dashboard and Global Search, moving Analytics and Institutional KPI under Reports.
- Admin sidebar navigation now also places Placement Stats under Reports instead of Placement operations.
- Admin and Admission sidebar navigation now place Lead Analytics under Reports instead of daily Lead operations.
- Admin and PMC sidebar navigation now place Leave Approvals under Approvals instead of academic delivery/student operational groups.
- PMC, CoE, and IQAC sidebar navigation now place Academics Governance under Governance instead of daily Command entry points.
- Admin sidebar navigation now places Academics Governance under Governance instead of Academics / Delivery.
- Dean sidebar navigation now places Approval Cockpit under Approvals instead of Governance.
- Admin Grade Reports now uses result-specific empty-state guidance and readable official-record fallback labels instead of attendance wording, mojibake placeholders, or dash-only missing marks/grade/points.
- CoE official Hall Ticket PDF now uses readable missing-data labels and ASCII-safe separators instead of `N/A`, mojibake, or dash-only placeholders.
- Student Admit Cards now uses clear exam-readiness labels for missing time, venue, subject, and max marks instead of `TBA`, `General`, or `Not set`.
- Student email notifications now use readable missing-data labels and ASCII-safe financial/footer text instead of `N/A`, corrupted currency symbols, or HTML dash entities.
- Student mail subject lines now use ASCII-safe separators and `Rs.` financial text instead of mojibake, rupee symbols, or Unicode dashes.
- Legacy shared emails for exam results, fee receipts, notices, and welcome messages now use readable fallbacks and `Rs.` financial labels instead of brittle direct output or currency entities.
- Shared admin shell JavaScript comments now use ASCII-safe section labels.
- Admission Dashboard now starts with a compact daily operating order that links to Calling Desk, Document Queue, Payment Queue, Assessment Control Room, and Offer/Seat Control.
- Admission Offer/Seat Control empty states now explain source workflow and next action links for offer rounds, waitlist, seat holds, joining-kit blockers, and deferrals.
- Admission Document Queue uses ASCII-safe applicant/program separators.
- Admission Lead Detail now uses ASCII-safe title/phone/counsellor labels and explains empty notes, follow-up, assignment, communication, and call-history sections with next staff actions.
- Admission Lead Queue now explains the lead-processing workflow, uses readable phone/program fallbacks, has actionable no-match guidance, and avoids escaped shell title text.
- Admission Follow-up Calendar now explains the monthly callback review workflow, uses ASCII-safe title/header/comment/fallback labels, provides actionable empty-month guidance, and uses readable counsellor/notes fallbacks.
- Admission Reminder Queue now explains the reminder/cadence operating workflow, shows active filter context, links reminders to lead/applicant detail records, and provides actionable no-match guidance.
- Admission Communication Hub now replaces terse template/message empty states with source-workflow guidance and links to Bulk Communication, Communication Safety, and Reminder Queue.
- Admission Workbench now links attention cards to filtered queue keys and explains empty lead, enrollment, document, payment, assessment, and offer-risk panels with source workflow next steps.
- Admission Attention Queues now honor selected queue drilldowns, show displayed total and active source filters, hide unrelated queue sections when opened from a KPI card, and explain queue-specific no-match next steps.
- Admission Bulk Communication now shows no-preview guidance, active audience filter summaries, recipient program/status/email context, and Communication Safety links before staff compose/send an audience message.

Evidence:

- `AdmissionFrontendBetaReadinessTest`: `22 tests / 427 assertions` passed.
- `AdmissionKpiDrilldownConsistencyTest`: `2 tests / 47 assertions` passed.
- `AdmissionAssessmentOfferSeatUxGuidanceTest`: `5 tests / 78 assertions` passed.
- `AdmissionFrontendBetaReadinessTest`: `22 tests / 428 assertions` passed after the Offer/Seat and Document Queue slice.
- `AdmissionDetailTimelineUxGuidanceTest`: `5 tests / 87 assertions` passed after the Lead Detail slice.
- `AdmissionFrontendBetaReadinessTest`: `22 tests / 428 assertions` passed after the Lead Detail slice.
- `LeadTest` plus `AdmissionFrontendBetaReadinessTest`: `38 tests / 530 assertions` passed after the Lead Queue slice.
- `LeadTest`: `18 tests / 131 assertions` passed after the Follow-up Calendar slice.
- `AdmissionFrontendBetaReadinessTest`: `22 tests / 428 assertions` passed after the Follow-up Calendar slice.
- `AdmissionFrontendBetaReadinessTest`: `23 tests / 457 assertions` passed after the Reminder Queue slice.
- `AdmissionCounsellorTelecallerUxGuidanceTest`: `4 tests / 59 assertions` passed after the Reminder Queue slice.
- `AdmissionOsV033Test`: `2 tests / 36 assertions` passed after the Reminder Queue slice.
- `AdmissionCommunicationAutomationReportsUxGuidanceTest`: `5 tests / 98 assertions` passed after the Communication Hub slice.
- `AdmissionFrontendBetaReadinessTest`: `23 tests / 457 assertions` passed after the Communication Hub slice.
- `AdmissionSupervisorUxGuidanceTest`: `6 tests / 95 assertions` passed after the Workbench slice.
- `AdmissionFrontendBetaReadinessTest`: `23 tests / 457 assertions` passed after the Workbench slice.
- `AdmissionSupervisorUxGuidanceTest`: `7 tests / 113 assertions` passed after the Attention Queue drilldown slice.
- `AdmissionFrontendBetaReadinessTest`: `23 tests / 457 assertions` passed after the Attention Queue drilldown slice.
- `AdmissionCommunicationAutomationReportsUxGuidanceTest`: `5 tests / 117 assertions` passed after the Bulk Communication preview slice.
- `AdmissionFrontendBetaReadinessTest`: `23 tests / 457 assertions` passed after the Bulk Communication preview slice.
- Shared navigation slice: `PortalFrontendBetaReadinessTest` passed `13 tests / 676 assertions`; `FrontendReadinessTest` passed `11 tests / 947 assertions`.
- Admission sidebar grouping slice: `AdmissionFrontendBetaReadinessTest` passed `23 tests / 470 assertions`; `FrontendReadinessTest` passed `11 tests / 950 assertions`.
- Admin sidebar grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 747 assertions`; `FrontendReadinessTest` passed `11 tests / 951 assertions`.
- Director/HOD sidebar grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 757 assertions`; `FrontendReadinessTest` passed `11 tests / 951 assertions`; static manifest check found no empty navigation groups.
- Student sidebar grouping slice: `PortalFrontendBetaReadinessTest` passed `13 tests / 680 assertions`; `FrontendReadinessTest` passed `11 tests / 952 assertions`; static manifest check found no empty navigation groups.
- Accounts sidebar grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 759 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Admin fee-report grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 764 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Admin integration-health grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 766 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- CMC placement-stats grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 768 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Admin command/report grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 771 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Admin placement-stats grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 774 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Lead Analytics report grouping slice: `AdmissionFrontendBetaReadinessTest` passed `23 tests / 473 assertions`; `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 776 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Leave Approvals grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 778 assertions`; `AcademicsPmcFrontendBetaReadinessTest` passed `8 tests / 343 assertions`; `FrontendReadinessTest` passed `11 tests / 953 assertions`; static manifest check found no empty navigation groups.
- Academics branch governance grouping slice: `AcademicsPmcFrontendBetaReadinessTest` passed `8 tests / 345 assertions`; `AcademicsCoeFrontendBetaReadinessTest` passed `5 tests / 159 assertions`; `AcademicsIqacFrontendBetaReadinessTest` passed `5 tests / 159 assertions`; `FrontendReadinessTest` passed `11 tests / 955 assertions`; static manifest check found no empty navigation groups.
- Admin academics-governance grouping slice: `AdminOperationsFrontendBetaReadinessTest` passed `10 tests / 780 assertions`; `FrontendReadinessTest` passed `11 tests / 955 assertions`; static manifest check found no empty navigation groups.
- Dean approval-cockpit grouping slice: `AcademicsDeanFrontendBetaReadinessTest` passed `7 tests / 143 assertions`; `FrontendReadinessTest` passed `11 tests / 955 assertions`; static manifest check found no empty navigation groups.
- Admin Grade Reports official-record slice: `AdminOperationsFrontendBetaReadinessTest` passed `11 tests / 799 assertions`; `FrontendReadinessTest` passed `11 tests / 955 assertions`; `AdminExamAccessControlTest` passed `3 tests / 43 assertions`; static scan of `resources/views/admin/results/index.blade.php` found no old attendance empty-state text, mojibake select labels, or corrupted dash placeholders.
- CoE Hall Ticket PDF official-output slice: `ExamCellDashboardGuidanceTest` passed `19 tests / 238 assertions`; adjacent `AcademicsCoeFrontendBetaReadinessTest` passed `5 tests / 159 assertions`; static scan of `resources/views/departmental/exam-cell/hall-ticket-pdf.blade.php` found no `N/A`, mojibake markers, HTML dash/bullet entities, or Unicode dash placeholders.
- Student Admit Cards self-service slice: `StudentAdmitCardWorkflowTest` passed `7 tests / 58 assertions`; adjacent `PortalFrontendBetaReadinessTest` passed `13 tests / 680 assertions`; static scan of `resources/views/student/admit-cards.blade.php` found no `TBA`, `Not set`, `General`, mojibake markers, HTML dash/bullet entities, or Unicode dash placeholders.
- Student email notification slice: `StudentEmailUxTest` passed `1 test / 17 assertions`; adjacent `PortalFrontendBetaReadinessTest` passed `13 tests / 680 assertions`; adjacent `FeePaymentTest` passed `44 tests / 316 assertions`; static scan of the changed email layout/student templates found no `N/A`, mojibake markers, rupee symbol, `TBA`, `Not set`, or HTML dash/bullet entities.
- Student mail subject slice: `StudentEmailUxTest` passed `2 tests / 29 assertions`; adjacent `PortalFrontendBetaReadinessTest` passed `13 tests / 680 assertions`; adjacent `FeePaymentTest` passed `44 tests / 316 assertions`; static scan of related `app/Mail` classes and student email templates found no `N/A`, mojibake markers, rupee symbol, or Unicode dash placeholders.
- Legacy shared email view slice: `StudentEmailUxTest` passed `3 tests / 50 assertions`; adjacent `NoticeVisibilityIntegrityTest` passed `8 tests / 50 assertions`; adjacent `FeePaymentTest` passed `44 tests / 316 assertions`; static scan of shared email views found no `N/A`, mojibake markers, rupee/currency entity, `TBA`, `Not set`, or HTML dash/bullet entities.
- Browser rendered `/admission/dashboard` with Communication, Finance, Reports, and Governance sidebar groups plus Bulk Communication, Consent & Safety, Refunds, Admission Reports, and Integration Health links; no debug text or console errors.
- Browser rendered `/admission/offer-seat-control` with title `Offer And Seat Control`, workflow/empty-state guidance visible, no debug text, and no console errors.
- Browser rendered `/admission/leads?search=no-matching-lead-token` with title `Leads and Enquiries`, no escaped title text, visible lead workflow and empty-state guidance, no debug text, and no console errors.
- Browser rendered `/admission/leads/follow-ups/calendar?month=2026-06` with title `Follow-up Calendar - June 2026`, visible workflow guidance, no mojibake/debug text, and no console errors.
- Browser rendered `/admission/reminders?reason=no_matching_reason_for_browser` with workflow guidance, active filter context, no-match next steps, no mojibake/debug text, and no console errors.
- Browser rendered `/admission/communication` with communication sequence guidance, seeded template/message sections, no mojibake/debug text, and no console errors.
- Browser rendered `/admission/workbench?priority=low&program_id=999999` with empty-panel workflow guidance, queue-filter links, no mojibake/debug text, and no console errors.
- Browser rendered `/admission/attention?queue=unassigned_hot_leads&priority=low&program_id=999999` with selected queue context, `0 item(s)`, active filters, queue-specific empty guidance, no unrelated queue sections, no debug text, and no console errors.
- Browser rendered `/admission/bulk-communication?filter_status=submitted` with workflow guidance, audience filter summary, recipient program/status/email context, Communication Safety link, no debug text, and no console errors.
- Static scan of changed user-facing Blade files found no new placeholder/mojibake markers; only intentional negative test assertions matched.
- `git diff --check` passed for changed files.

Next target:

- Apply the same workflow-first entry strip to the next high-traffic daily-use surface after inspecting current code. Prefer Admission staff sub-workflows or Teacher dashboard only if a real issue is reproduced.
