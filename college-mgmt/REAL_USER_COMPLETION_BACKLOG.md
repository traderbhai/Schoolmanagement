# Real User Completion Backlog

This file controls the next phase after release-readiness: act like real users, find remaining practical friction, and close only verified gaps one bounded workflow at a time.

## Current Baseline

- Backend/release readiness is already green in `PROJECT_COMPLETION_BACKLOG.md`.
- Frontend/UX readiness is already green in `FRONTEND_COMPLETION_BACKLOG.md` and `USER_ROLE_UX_AUDIT.md`.
- Latest verified full suite: `1548 tests / 14152 assertions`.
- Latest frontend gates: `frontend:build` passed, desktop smoke `127 / 3733`, mobile smoke `29 / 1384`.

## Execution Rules

- Work one real-user journey at a time.
- Start each journey from a demo login and use the rendered website, not only code inspection.
- Record only concrete problems reproduced from current code/browser evidence.
- Fix critical/high usability or workflow gaps first.
- Do not add broad feature sets unless a real workflow is blocked without them.
- Keep backend changes narrow and additive.
- Preserve existing routes and verified tests.
- Run focused tests for each journey, adjacent regression after each slice, and full suite only at stage gates.
- Update this file and `CODEX_PROJECT_CONTEXT.md` only after verification passes.

## Real-User Journey Slices

| Priority | Slice | User goal | Current status | Exit evidence |
| --- | --- | --- | --- | --- |
| P0 | Admission applicant-to-enrollment journey | A prospective student can understand status, complete required actions, submit/track documents, fee, assessment, offer, and handoff without dead ends. | fixed_verified | Browser flow + applicant/admission focused tests pass. |
| P0 | Admission staff daily conversion journey | Counsellor/telecaller/manager can find next work, call/log/remind/update applicants/leads, and hand off issues without hunting through menus. | fixed_verified | Browser flow + Admission staff focused tests pass. |
| P0 | Admin setup journey | Admin can set up academic year, department, program, batch, term, users, roles, and permissions with clear sequence and no unsafe actions. | fixed_verified | Rendered admin setup/security focused tests pass; browser confirms non-admin direct-route protection has friendly 403. |
| P0 | Student daily portal journey | Student can see today classes, attendance, fees, timetable, results, materials, assignments, documents, and blockers without internal terminology. | fixed_verified | Rendered student portal focused and adjacent workflow tests pass. |
| P0 | Teacher daily teaching journey | Teacher can see assigned classes, take attendance, post material/assignment, view roster, and avoid draft/out-of-scope courses. | fixed_verified | Rendered teacher portal focused and adjacent workflow tests pass. |
| P1 | Parent monitoring journey | Parent can view linked child attendance, fees, results, notices, and contact guidance without seeing unrelated records. | fixed_verified | Rendered parent portal focused and adjacent workflow tests pass. |
| P1 | Dean academic governance journey | Dean can review risks, approvals, actions, planning, handoff, reports, and drill into source lists from one command flow. | fixed_verified | Focused Dean UX/KPI/v0.07/v0.08 tests and adjacent Academics regressions pass. |
| P1 | PMC timetable journey | PMC can move from course allocation to groups, faculty load, timetable generation, conflict resolution, publish/freeze, and student/faculty views. | fixed_verified | Focused PMC UX/KPI/timetable tests and adjacent PMC regressions pass. |
| P1 | CoE exam journey | CoE can progress exam readiness, marks/results, hall tickets, transcripts, appeals, and official-boundary checks. | fixed_verified | Focused CoE UX/frontend/KPI/v0.03 tests and adjacent Academics regressions pass. |
| P1 | IQAC quality journey | IQAC can track OBE/attainment/feedback/evidence/action closure from dashboard to source records. | fixed_verified | Focused IQAC UX/frontend/KPI/v0.04 tests and adjacent Academics regressions pass. |
| P1 | Accounts finance journey | Accounts can verify payments, reconcile outstanding, scholarships, refunds, reports, and exports without mismatched numbers. | fixed_verified | Focused accounts dashboard guidance and adjacent finance integrity tests pass; browser reached local app and confirmed non-accounts direct access shows friendly restricted page without debug traces. |
| P2 | Operations journey | Library, Hostel, Transport, Assets, CMC, Notifications have practical create/edit/list/action paths and clear empty states. | fixed_verified | Focused CMC/operations KPI tests and adjacent operations lifecycle/frontend checks pass. |

## Active Slice

### Slice: Admission applicant-to-enrollment journey

Scope:
- Applicant dashboard.
- Applicant checklist.
- Application form entry point.
- Documents.
- Fees/registration fee.
- Status tracker.
- Admission operations.
- Offer letters.
- Notifications/consent.
- Staff applicant detail only where applicant-facing state depends on staff workflow.

Real-user questions:
- What should the applicant do first?
- Does every shown blocker have a working action?
- Do counts/statuses match the destination page?
- Does submitted/final-state behavior avoid dead-end CTAs?
- Can the applicant see only their own data?
- Does the page explain whether the next step is applicant action or staff review?

Planned verification:
- Browser: login as applicant and open the main journey pages on desktop and mobile.
- Focused tests: applicant readiness, registration fee, portal ownership, applicant UX guidance.
- Adjacent tests: Admission applicant/front-end/v0.039 coverage.

Status: `fixed_verified`.

Verification:
- Browser desktop: applicant login reached `/applicant/dashboard`; dashboard, checklist, application, documents, fees, status, admission operations, offer letters, and notifications opened without debug traces or console errors.
- Browser mobile 390x844: applicant dashboard rendered without horizontal overflow; primary submitted-state registration-fee action now reads `Track Status`.
- Focused tests: `ApplicantRegistrationFeeTest`, `AdmissionApplicantReadinessTest`, `AdmissionApplicantUxGuidanceTest` passed `16 tests / 207 assertions`.
- Adjacent tests: `ApplicantStatusGuidanceTest`, `PortalFrontendBetaReadinessTest`, `PortalOwnershipBoundaryTest`, `AdmissionOsV039Test` passed `28 tests / 725 assertions`.

### Slice: Admission staff daily conversion journey

Scope:
- Admission Command Center.
- Manager Workspace.
- Workbench.
- Reminders.
- Document queue.
- Lead and applicant lists.
- Lower-role daily workflow evidence through scoped Admission staff tests.

Real-user questions:
- Can the manager/counsellor/telecaller find the next practical work item quickly?
- Do KPI cards open the matching source list?
- Do lead/applicant queue counts use the same source as their drilldown pages?
- Do staff pages explain whether the next action is assignment, call, reminder, document review, or escalation?
- Do scoped staff routes avoid debug traces and dead links?

Status: `fixed_verified`.

Verification:
- Browser desktop as Admission Manager opened Command Center, Manager Workspace, Workbench, Reminders, Document Queue, Leads, and Applicants without debug traces.
- Browser found the Command Center workload metric was lead workload but linked to applicants; this is fixed and verified in-browser: `Lead Workload` links to `/admission/leads`, with no stale applicant-workload hint.
- Focused staff tests: `AdmissionSupervisorUxGuidanceTest`, `AdmissionCounsellorTelecallerReadinessTest`, `AdmissionManagerOfficerReadinessTest`, and `AdmissionKpiDrilldownConsistencyTest` passed `16 tests / 404 assertions`.
- Adjacent Admission tests: `AdmissionFrontendBetaReadinessTest`, `AdmissionOsV039Test`, and `AdmissionHeadReadinessTest` passed `29 tests / 626 assertions`.

### Slice: Admin setup journey

Scope:
- Admin dashboard.
- Academic years.
- Departments.
- Programs.
- Batches.
- Terms/semesters.
- Role assignments.
- Role permissions.
- System settings.

Real-user questions:
- Does an admin know the correct setup order before creating records?
- Can the admin jump between setup steps without hunting through the long sidebar?
- Do setup pages avoid broken placeholder links and debug traces?
- Are admin-only routes still protected from non-admin direct URL access?

Status: `fixed_verified`.

Verification:
- Added a shared `Admin setup sequence` strip to dashboard and setup pages, linking the ordered flow: academic year, departments, programs, batches, terms, users/roles, permissions.
- Focused setup/security tests: `AdminOperationsUxGuidanceTest`, `AdminAcademicStructureAccessControlTest`, `AdminRolePermissionAccessControlTest`, `AdminUserRoleIntegrityTest`, and `AdminSystemConfigurationAccessControlTest` passed `20 tests / 394 assertions`.
- Adjacent admin frontend/readiness tests: `AdminOperationsFrontendBetaReadinessTest`, `AdminOperationsKpiDrilldownConsistencyTest`, `FrontendReadinessTest`, and `RoleRedirectTest` passed `78 tests / 1851 assertions`.
- Browser session as a non-admin staff user opened admin setup URLs and received the friendly 403 page without Laravel/debug/service-error text, confirming protected direct-route behavior.

### Slice: Student daily portal journey

Scope:
- Student dashboard.
- Attendance.
- Fees.
- Results.
- Timetable.
- Courses/materials/assignments.
- Documents.
- Feedback and exam registration workflow checks.

Real-user questions:
- Can the student see the next action for today without internal terminology?
- Do dashboard metrics open the matching source page?
- Do student pages show only the logged-in student's own records?
- Are official/draft academic boundaries preserved for results, timetable, documents, and course content?

Status: `fixed_verified`.

Verification:
- Student dashboard KPI cards now act as source drilldowns: Attendance opens attendance details, SGPA/CGPA open results, and Fee Outstanding opens fee details.
- Focused student/portal tests: `PortalDashboardUxGuidanceTest`, `PortalFrontendBetaReadinessTest`, `PortalOwnershipBoundaryTest`, `StudentDashboardGuidanceTest`, `StudentTeacherAttendanceCanonicalWorkflowTest`, `StudentResultsWorkflowTest`, and `StudentTimetableWorkflowTest` passed `40 tests / 864 assertions`.
- Adjacent student workflow tests: `StudentCourseContentAccessTest`, `StudentCourseFeedbackWorkflowTest`, `StudentDocumentRequestGuidanceTest`, and `StudentExamRegistrationWorkflowTest` passed `45 tests / 250 assertions`.

### Slice: Teacher daily teaching journey

Scope:
- Teacher dashboard.
- Timetable.
- Attendance marking.
- Assignments and submissions.
- Materials.
- Exams/results entry.
- Student roster.
- Mentor/feedback/profile-missing boundaries.

Real-user questions:
- Can the teacher start the day from timetable/class readiness?
- Do dashboard metrics open the matching teaching workflow?
- Can the teacher reach attendance, assignments, materials, marks, and mentees without hunting?
- Are teacher writes scoped to assigned subjects, rosters, and active teaching context?

Status: `fixed_verified`.

Verification:
- Teacher dashboard KPI cards now act as source drilldowns: Weekly Load and My Classes Today open the timetable; Mark Attendance opens attendance marking.
- Focused teacher/portal tests: `PortalDashboardUxGuidanceTest`, `PortalFrontendBetaReadinessTest`, `TeacherProfileMissingGracefulTest`, `TeacherScopeWorkflowTest`, `TeacherStudentListTest`, `StudentTeacherAttendanceCanonicalWorkflowTest`, and `StudentTimetableWorkflowTest` passed `59 tests / 1031 assertions`.
- Adjacent teacher workflow tests: `StudentCourseFeedbackWorkflowTest`, `StudentLeaveWorkflowTest`, `AttendanceWorkflowTest`, and `ExamResultTest` passed `30 tests / 151 assertions`.

### Slice: Parent monitoring journey

Scope:
- Parent dashboard.
- Linked child attendance.
- Linked child results.
- Linked child fees.
- Notices.
- Child ownership boundaries.

Real-user questions:
- Can the parent identify the child priority and open the exact source record?
- Do attendance, result, and fee metrics drill into the linked child's records?
- Does the parent see only linked children and never unrelated students?
- Do parent-visible records preserve student-facing academic and financial boundaries?

Status: `fixed_verified`.

Verification:
- Parent dashboard child KPI cards now act as source drilldowns: Attendance opens the linked child's attendance, SGPA opens results, and Fee Balance opens fees.
- Focused parent/portal tests: `PortalDashboardUxGuidanceTest`, `ParentPortalGuidanceTest`, `PortalFrontendBetaReadinessTest`, `PortalOwnershipBoundaryTest`, `PortalKpiDrilldownConsistencyTest`, and `NoticeVisibilityIntegrityTest` passed `38 tests / 838 assertions`.
- Adjacent attendance/results/fee checks: `StudentResultsWorkflowTest`, `StudentTeacherAttendanceCanonicalWorkflowTest`, `FeeDemandTest`, and `FeePaymentTest` passed `80 tests / 497 assertions`.

### Slice: Dean academic governance journey

Scope:
- Dean Command OS dashboard.
- Dean attention queues.
- Branch health, program risk, reviews/actions, handoff, planning, approvals, and analytics entry points.

Real-user questions:
- Can the Dean open every high-priority dashboard number as a source-backed list?
- Does the dashboard identify the daily governance order without requiring branch-by-branch hunting?
- Do critical risks, approvals, actions, and handoff blockers lead to filtered evidence?
- Are Dean OS routes still protected and compatible with broader Academics navigation?

Status: `fixed_verified`.

Verification:
- Dean dashboard `Critical Attention` now opens the aggregate `/academics/dean-os/attention/critical_attention` queue instead of being a summary-only card.
- The aggregate queue is service-backed by all critical/high Dean attention items, so its record count matches the dashboard KPI source.
- Focused Dean tests: `AcademicsDeanUxGuidanceTest`, `AcademicsDeanKpiDrilldownConsistencyTest`, `AcademicsDeanFrontendBetaReadinessTest`, `AcademicsDeanV007Test`, and `AcademicsDeanV008Test` passed `31 tests / 334 assertions`.
- Adjacent Academics regression tests: `AcademicsOsV011Test` and `AcademicsOsV001Test` passed `17 tests / 85 assertions`.

### Slice: PMC timetable journey

Scope:
- PMC Timetable OS dashboard.
- Course allocation, student baskets, sections/groups, faculty assignment, locked slots, generator, planner, versions/freeze, substitutions, and reports entry points.

Real-user questions:
- Can PMC follow the real timetable sequence from student course baskets to published timetable?
- Do the top operational metrics open the source page that explains the number or score?
- Can hard conflicts, soft warnings, and quality score be inspected before publish/freeze?
- Are PMC timetable pages still compatible with PMC Head and Dean oversight workflows?

Status: `fixed_verified`.

Verification:
- PMC Timetable OS `Quality Score` now opens the quality source surface instead of being a summary-only card.
- The quality destination is the existing `academics.pmc.timetable-quality.index` surface, which exposes the generator/quality workflow and visible filter summary.
- Focused PMC tests: `AcademicsPmcUxGuidanceTest`, `AcademicsPmcKpiDrilldownConsistencyTest`, `AcademicsPmcFrontendBetaReadinessTest`, and `AcademicsPmcTimetableV041Test` passed `21 tests / 635 assertions`.
- Adjacent PMC regression tests: `AcademicsPmcV004Test`, `AcademicsPmcV003Test`, and `AcademicsPmcV002Test` passed `14 tests / 103 assertions`.

### Slice: CoE exam journey

Scope:
- CoE Operating System dashboard.
- Exam readiness, marks/results, hall-ticket readiness, transcripts, appeals/anomalies, and reports source lists.

Real-user questions:
- Can CoE staff start from readiness and move through marks/results, hall tickets, transcripts, and appeals without losing queue context?
- Do dashboard KPIs open scoped source lists that preserve their active queue filter?
- Can staff clear search/status filters on a KPI queue without accidentally broadening to the whole section?
- Are official/published boundaries still preserved for marks, transcripts, and hall-ticket queues?

Status: `fixed_verified`.

Verification:
- CoE source-list `Reset` now preserves the active KPI queue metric and is labeled `Reset queue`; search/status are cleared without collapsing the drilldown to all records.
- Focused CoE tests: `AcademicsCoeUxGuidanceTest`, `AcademicsCoeFrontendBetaReadinessTest`, `AcademicsCoeV003Test`, and `AcademicOperatingKpiDrilldownConsistencyTest` passed `18 tests / 338 assertions`.
- Adjacent Academics regression tests: `AcademicsDeanV008Test` and `AcademicsOsV011Test` passed `23 tests / 119 assertions`.

### Slice: IQAC quality journey

Scope:
- IQAC Operating System dashboard.
- OBE readiness, attainment monitoring, feedback quality, audit/compliance, and reports source lists.

Real-user questions:
- Can IQAC staff move from dashboard quality signals to owner/evidence/action source records?
- Do OBE, mapping, attainment, and feedback KPI queues preserve their active metric while staff filter records?
- Can staff clear search/status filters without broadening the KPI queue unexpectedly?
- Are scoped IQAC manager/officer links and direct-route boundaries still intact?

Status: `fixed_verified`.

Verification:
- IQAC source-list `Reset` now preserves the active KPI queue metric and is labeled `Reset queue`; search/status are cleared without collapsing the drilldown to all records.
- Focused IQAC tests: `AcademicsIqacUxGuidanceTest`, `AcademicsIqacFrontendBetaReadinessTest`, `AcademicsIqacV004Test`, and `AcademicOperatingKpiDrilldownConsistencyTest` passed `17 tests / 323 assertions`.
- Adjacent Academics regression tests: `AcademicsDeanV008Test` and `AcademicsOsV011Test` passed `23 tests / 119 assertions`.

### Slice: Accounts finance journey

Scope:
- Accounts dashboard.
- Payment verification entry.
- Outstanding and overdue demand queues.
- Fee collections.
- Reconciliation.
- Scholarships, reports, and exports where surfaced from the Accounts dashboard.

Real-user questions:
- Can accounts staff start from the dashboard and open the exact finance queue behind each urgent metric?
- Do outstanding and overdue demand actions land on the correct source list instead of a broad unrelated page?
- Do payment verification, collections, reconciliation, and exports remain covered by existing finance integrity tests?
- Does a non-accounts session avoid debug traces when directly opening Accounts pages?

Status: `fixed_verified`.

Verification:
- Accounts dashboard primary KPI cards now open source surfaces: demand report, paid collections, outstanding list, and overdue demand queue.
- Finance Priority now routes overdue follow-up to `/accounts/outstanding?mode=overdue_demands` instead of the broad outstanding summary.
- Focused Accounts UX test passed `11 tests / 77 assertions`.
- Adjacent finance integrity tests passed `116 tests / 662 assertions` across fee demands, fee payments, scholarships, admission payment verification, admission refunds, and hostel fee workflows.
- Browser plugin reached `/accounts/dashboard` while logged in as a non-accounts user and confirmed the app shows a friendly restricted page without Laravel/Whoops/service-error text or console errors. Switching to the seeded accounts login was blocked by the existing browser session's non-clickable logout form, so source-link rendering was verified through focused response tests.

### Slice: Operations journey

Scope:
- CMC dashboard and placement source lists.
- Library, Hostel, Transport, Assets, and Notices adjacent workflow checks.
- Existing operations create/edit/list/action paths and clear empty-state coverage.

Real-user questions:
- Can operations staff open practical source lists from dashboard cards instead of seeing static numbers?
- Does CMC active-drive count land on a list that includes exactly upcoming and ongoing drives?
- Do filtered operations lists and exports preserve the same source rows?
- Do adjacent operations workflows remain stable after navigation changes?

Status: `fixed_verified`.

Verification:
- CMC dashboard KPI cards now open source surfaces: active drives, selected placements, and placement analytics.
- Added `status=active` support for CMC drives so the Active Drives KPI and export match upcoming plus ongoing drives, excluding completed/cancelled records.
- Focused CMC/operations KPI tests passed `12 tests / 116 assertions`.
- Adjacent operations frontend/lifecycle tests passed `111 tests / 1657 assertions` across Admin Operations readiness, UX guidance, Library circulation, Hostel workflow, Transport workflow, Asset workflow, and Notice visibility.

## Issue Log

| ID | Slice | Route / flow | Severity | Evidence | Status |
| --- | --- | --- | --- | --- | --- |
| RUC-001 | Admission applicant-to-enrollment | Applicant dashboard registration-fee CTA after submission | High | Full suite found submitted applicant still saw a dead-end `Submit Fee Details` action. Browser mobile then found the top card still used generic `Continue` after state-aware text. | fixed_verified; submitted applicant now sees `Track Status`; focused and adjacent tests pass. |
| RUC-002 | Admission applicant-to-enrollment | Applicant journey pages | Medium | Browser opened dashboard, checklist, application, documents, fees, status, admission operations, offer letters, and notifications. No debug traces or console errors reproduced. | checked_no_blocker. |
| RUC-003 | Admission staff daily conversion | Admission Command Center workload KPI | High | Browser as Admission Manager showed `Workload 2` linked to applicants, while scoped applicants list had 0 records and scoped leads list had 2 records. | fixed_verified; card is now `Lead Workload`, links to `/admission/leads`, and focused/adjacent tests pass. |
| RUC-004 | Admin setup journey | Admin setup pages | High | Setup pages for academic year, department, program, batch, term, users/roles, permissions, and settings were isolated CRUD pages, so admins had to infer setup order from the sidebar. | fixed_verified; shared setup sequence with direct route links is present and focused/adjacent tests pass. |
| RUC-005 | Student daily portal | Student dashboard KPI cards | High | Dashboard showed Attendance, SGPA, CGPA, and Fee Outstanding as static cards even though students naturally tap metrics to see source details. | fixed_verified; KPI cards now link to attendance, results, and fees; focused and adjacent tests pass. |
| RUC-006 | Teacher daily teaching | Teacher dashboard KPI cards | High | Dashboard showed Weekly Load and My Classes Today as static cards, while Mark Attendance only had a small nested link. | fixed_verified; KPI cards now link to timetable and attendance workflows; focused and adjacent tests pass. |
| RUC-007 | Parent monitoring | Parent dashboard child KPI cards | High | Child Attendance, SGPA, and Fee Balance metrics were static, forcing parents to use separate small buttons instead of tapping the metric needing attention. | fixed_verified; child KPI cards now link to attendance, results, and fees; focused and adjacent tests pass. |
| RUC-008 | Dean academic governance | Dean dashboard Critical Attention KPI | High | Dashboard showed a critical attention count as a summary-only card, so the Dean could not drill into the exact aggregate critical/high source list from the daily command flow. | fixed_verified; `Critical Attention` now opens `/academics/dean-os/attention/critical_attention`, which is backed by the same Dean attention service count; focused and adjacent tests pass. |
| RUC-009 | PMC timetable | PMC Timetable OS Quality Score KPI | High | Dashboard showed timetable `Quality Score` as a summary-only score even though PMC needs to inspect quality before generation review, publish, and freeze decisions. | fixed_verified; `Quality Score` now opens `/academics/pmc/timetable-quality`, backed by the existing quality/generator source surface; focused and adjacent tests pass. |
| RUC-010 | CoE exam | CoE KPI source-list reset | High | CoE KPI drilldowns preserved metric filters during search/apply, but the Reset button dropped the metric and broadened the queue to the full section. | fixed_verified; Reset now preserves the KPI metric as `Reset queue`, while clearing search/status; focused and adjacent tests pass. |
| RUC-011 | IQAC quality | IQAC KPI source-list reset | High | IQAC KPI drilldowns preserved metric filters during search/apply, but the Reset button dropped the metric and broadened the quality queue to the full section. | fixed_verified; Reset now preserves the KPI metric as `Reset queue`, while clearing search/status; focused and adjacent tests pass. |
| RUC-012 | Accounts finance | Accounts dashboard primary KPI cards | High | Total Billed, Collected, Outstanding, and Overdue Accounts were shown as operational cards but did not consistently open the source finance lists/accounts surfaces. The overdue priority shortcut also opened the broad outstanding summary instead of the overdue-demand queue. | fixed_verified; primary finance KPIs now link to source surfaces and overdue priority opens the overdue-demand drilldown; focused and adjacent tests pass. |
| RUC-013 | Operations / CMC | CMC dashboard primary KPI cards | High | Active Drives, Total Placed, Total Students, and Placement Rate were operational KPI cards but did not open source CMC surfaces. Active Drives also needed an exact active filter because active means upcoming plus ongoing. | fixed_verified; CMC KPI cards now open source surfaces, and `status=active` drives filter/export matches upcoming plus ongoing drives; focused and adjacent tests pass. |
| RUC-014 | Program Leadership | Active Students KPI | High | Program Leadership dashboard still treated Active Students as a summary-only count, so program owners could not open the scoped student list behind the metric. | fixed_verified; Active Students now opens `student-success?metric=active_students`, while default student-success previews remain risk-focused and published-boundary tests pass. |
| RUC-015 | Admin setup / institute command | Admin dashboard primary KPI cards | High | Admin dashboard institute-health cards were static, forcing admins to use the sidebar instead of drilling into the exact students, teachers, departments, programs, attendance, fees, notices, and exams source pages. | fixed_verified; the eight primary KPI cards now open matching admin source pages; focused and adjacent admin tests pass. Browser direct admin access is still blocked for the current non-admin session with a friendly restricted page and clean console. |
| RUC-016 | PMC operating dashboard | Scoped Programs KPI | High | The legacy PMC dashboard still showed Scoped Programs as summary-only, so PMC users could not inspect the exact programs in their scope from the KPI. | fixed_verified; added `academics.pmc.programs?metric=active_programs` backed by the PMC scope service and generic source-list controls; focused and adjacent PMC tests pass. |
| RUC-017 | Student daily portal | Student timetable empty state and fallback labels | Medium | The student timetable empty state only said no entries were found and pushed students to registration, even though the real blocker can also be unpublished PMC timetable or missing section/group allocation. The view also contained corrupted dash fallback text for missing fields. | fixed_verified; empty state now explains subject-basket, PMC publication, and section/group allocation paths; fallback labels are ASCII-safe; focused and adjacent portal tests pass; browser verified seeded timetable page has no debug text, console errors, or mojibake dash. |
| RUC-018 | Student daily portal | Student leave history and empty-state guidance | Medium | The leave page showed a one-line `No leave applications yet.` empty state and used corrupted dash text when a pending leave had no reviewer remarks, leaving students unsure who reviews the request or what pending means. | fixed_verified; empty state now explains when to apply and reviewer ownership, pending rows show `No reviewer remarks yet`, and focused/adjacent portal tests plus browser verification pass. |
| RUC-019 | Student daily portal | Student library self-service guidance | Medium | The library self-service page used one-line empty states and corrupted currency/dash fallbacks, so students could not tell when books, reservations, history, fines, and NOC blockers would appear. | fixed_verified; borrowed/reservation/catalog/history empty states now explain library-counter and reservation workflow, fines use `Rs.`, fallback labels are readable, and focused/adjacent tests plus browser verification pass. |
| RUC-020 | Parent monitoring journey | Parent fee empty-state guidance | Medium | Parent fee page showed terse `No fee demands recorded yet.` and `No payments recorded yet.` rows, leaving parents unsure whether accounts had not raised dues or whether payment proof was pending verification. | fixed_verified; fee demand and paid-history empty states now explain accounts-raised dues, due/penalty/open-balance fields, and verified-receipt visibility; focused/adjacent parent tests pass; browser direct access from non-parent session shows friendly 403 without debug traces. |
| RUC-021 | Parent monitoring journey | Parent attendance/result empty-state guidance | Medium | Parent attendance and result pages used terse empty rows, so parents could not tell whether records were missing, draft, unpublished, or outside the child's enrolled subjects. | fixed_verified; attendance/results empty states now explain published timetable, faculty marking, official exam publication, and draft/out-of-scope boundaries; focused/adjacent parent tests pass; browser direct access from non-parent session shows friendly 403 without debug traces. |
| RUC-022 | Teacher daily teaching journey | Teacher course feedback guidance | Medium | Teacher feedback used vague no-subject/no-response empty states and previously exposed corrupted separator text, so faculty could not tell whether timetable assignment, profile linkage, or student submissions were missing. | fixed_verified; feedback page now explains published timetable assignment, teacher-profile linkage, anonymous aggregate boundaries, and uses ASCII-safe separators; focused/adjacent teacher tests pass; browser verified teacher dashboard -> navigation -> My Feedback with clean console. |
| RUC-023 | Teacher daily teaching journey | Teacher leave empty-state and review guidance | Medium | Teacher leave history showed a terse empty state and corrupted remarks fallback, so teachers could not tell who reviews leave, when pending leave can be cancelled, or why submission may be disabled. | fixed_verified; leave page now explains leave types, academic-admin review, pending cancellation, active-profile eligibility, and uses `No reviewer remarks yet`; focused/adjacent leave tests pass; browser verified teacher dashboard -> Leave with clean console. |
| RUC-024 | Student daily portal | Student admit-card eligibility guidance | Medium | Admit Cards used a vague `No Upcoming Exams` empty state and corrupted max-marks fallback, so students could not tell whether the issue was CoE scheduling, exam registration approval, attendance/fee clearance, or result publication. | fixed_verified; admit-card page now explains CoE scheduling, approved registration, attendance/fee clearance, unpublished exam boundary, and Exam Cell follow-up; focused/adjacent student exam tests pass; browser direct access from a non-student session showed friendly 403 without debug traces, while Browser login switching was blocked by the in-app virtual clipboard issue. |
| RUC-025 | Student daily portal | Student promotion-status guidance | Medium | Promotion Status used a vague no-record alert and corrupted criteria/history fallbacks, so students could not tell that Academics/PMC generates promotion records after term-end results, attendance, and clearances are finalized. | fixed_verified; promotion page now explains term-end review ownership, academic office follow-up, pending review state, and readable criteria/history fallbacks; focused/adjacent student academic tests pass; browser unauthenticated access redirects cleanly to login without debug traces, while Browser login switching remains blocked by the in-app virtual clipboard issue. |
| RUC-026 | Student daily portal | Student placement empty-state and application tracking guidance | Medium | Placement page empty states only said no drives/applications existed, and application rows used dash fallbacks, so students could not tell that CMC publishes drives after company/deadline/eligibility confirmation or that CMC updates application outcomes. | fixed_verified; placement empty states now explain CMC drive publication and application tracking workflow, and application package fallback is readable; focused/adjacent placement tests pass; browser unauthenticated access redirects cleanly to login without debug traces. |
| RUC-027 | Student career readiness | Career Events and Resume Builder guidance/save wiring | High | Career Events had a terse empty state, and Resume Builder rendered project/experience/certification form inputs that were not persisted because the controller only read hidden JSON payloads. | fixed_verified; career events now explain CMC publication timing, resume empty sections explain placement-readiness next steps, visible structured resume fields persist, focused career/resume tests passed `3 / 22`, adjacent career/placement/portal tests passed `50 / 904`, and browser verified Career Events plus Resume Add Project with clean console. |
| RUC-028 | Student finance support | Scholarship tracking and scheme-publication guidance | Medium | Student Scholarships showed no application tracker when there were no applications and used a terse `No active scholarships at this time.` placeholder, so students could not tell who publishes schemes or where submitted review/disbursement status appears. | fixed_verified; My Applications now shows a tracking empty state, Available Scholarships explains office-published scheme availability and eligibility data prerequisites, focused scholarship test passed `1 / 9`, adjacent scholarship/career/portal tests passed `60 / 979`, and browser verified `/student/scholarships` with clean console. |
| RUC-029 | Student career network | Internship and Alumni Network empty-state guidance | Medium | Internships and alumni network pages used terse empty states, so students could not tell that CMC creates internship records after confirmation or verifies alumni profiles before they appear. | fixed_verified; internship/alumni empty states now explain CMC ownership, confirmed-record fields, verified alumni source, and filter clearing/all-program next steps; focused checks passed `2 / 13`, adjacent career/portal checks passed `34 / 822`, and browser verified `/student/internships` plus `/student/alumni` with clean console. |
| RUC-030 | Student communication settings | Notification Settings page | Medium | Student notification preferences used Tailwind-style utility classes inside the Bootstrap student shell and the sidebar label did not distinguish settings from the notification inbox. | fixed_verified; page now uses compact Bootstrap cards, explains email-vs-inbox behavior, links to Inbox and Notice Board, sidebar label is `Notification Settings`, focused checks passed `2 / 19`, adjacent portal/notification checks passed `27 / 750`, and browser verified render plus save interaction with clean console. |
| RUC-031 | Applicant communication settings | Notification Settings page | Medium | Applicant notification preferences used Tailwind-style utility classes inside the Bootstrap applicant shell and did not clearly explain email settings versus official portal status visibility. | fixed_verified; page now uses compact Bootstrap cards, explains email-vs-portal behavior, links to Status Tracker and Checklist, sidebar label is `Notification Settings`, focused checks passed `2 / 19`, adjacent applicant/frontend checks passed `22 / 848`, and browser verified render plus save interaction with clean console. |
| RUC-032 | Shared portal communication | Shared `/notifications` inbox and detail pages | High | The shared notification inbox always used the admin shell, so student/teacher/parent/applicant users could land in an admin-looking page from the bell; portal layouts also lacked a CSRF meta tag for the Mark All Read action. | fixed_verified; inbox/detail now select the role-specific shell, portal layouts include CSRF meta, inbox explains message purpose and action links, focused checks passed `3 / 30`, adjacent portal/notification checks passed `29 / 750`, and browser verified applicant inbox Mark All Read to `0 unread` with clean console. |
| RUC-033 | Admission staff pipeline | `/admission/pipeline` empty stages and direct board type | Medium | Admission Pipeline showed only `No records.` for empty stages and accepted arbitrary `object_type` query values, so staff had no explanation for empty columns and direct URLs could create/use invalid board types. | fixed_verified; page now explains scope, board limit, stage meaning, and source-list next step; invalid object types return 404 before board creation; focused pipeline check passed `1 / 8`, adjacent `AdmissionFrontendBetaReadinessTest` passed `19 / 366`, v0.03 regression passed `5 / 64`, and browser verified empty-stage guidance with clean console. |
| RUC-034 | Academics command center | `/academics/attention/{queue}` empty queue and direct queue key | Medium | Academics Command queue pages showed only `No records currently match this queue.` for empty queues, and typo queue keys could render an unknown queue page instead of a clear not-found result. | fixed_verified; valid empty queues now explain scope/no-unresolved-item meaning and next steps, invalid keys return 404, focused check passed `1 / 7`, adjacent `AcademicsOsV011Test` passed `12 / 50`, Dean frontend readiness passed `7 / 141`; browser confirmed unauthorized role gets friendly 403/no debug text, while positive Dean browser login was blocked by the known Browser virtual clipboard issue. |
| RUC-035 | Dean Academics governance | `/academics/dean-os/attention/{queue}` empty attention queue | Medium | Dean attention queues used only `No records in this queue.`, which did not explain whether there was no issue, no access, or missing source data. | fixed_verified; empty Dean attention queues now explain there are no unresolved items for the selected queue and direct the Dean back to dashboard/other queues/action creation; focused Dean KPI checks passed `3 / 26`, adjacent Dean frontend/v0.07 checks passed `14 / 183`; browser confirmed unauthenticated access redirects cleanly to login with no debug text, while positive Dean browser login remains blocked by the Browser virtual clipboard issue. |
| RUC-036 | PMC operating source lists | `/academics/pmc/*` shared source-list empty state | Medium | PMC section pages showed only `No records match the current PMC scope.`, so users could not tell whether filters, assigned scope, or missing source workflow data caused the empty list. | fixed_verified; shared PMC source lists now explain current scope/filter no-match meaning and next steps; focused empty-state check passed `1 / 7`, PMC KPI/frontend checks passed `14 / 391`, PMC v0.04 regression passed `4 / 50`; browser confirmed unauthenticated access redirects cleanly to login with no debug text. |
| RUC-037 | Program Chair timetable | `/program-chair/timetable/substitutions` empty substitution history | Medium | Legacy substitution management showed only `No records yet.`, so Program Chair/PMC users could not tell when records appear or where to review timetable sessions before recording coverage changes. | fixed_verified; empty substitution history now explains replacements, cancellations, reschedules, scoped timetable ownership, and links back to the timetable builder; focused legacy timetable test passed `13 / 48`, adjacent PMC frontend/timetable/KPI checks passed `18 / 541`; browser confirmed unauthenticated access redirects cleanly to login with no debug text or console errors. |
| RUC-038 | CoE / official academic records | `/academic/transcripts` filtered candidate empty state | Medium | Academic Transcripts showed only `No students found.`, so CoE/Admin users could not tell whether filters, inactive students, missing program/batch linkage, or unpublished/incomplete results were blocking transcript issuance. | fixed_verified; filtered transcript lists now show visible filter context, explain active-student and published-result prerequisites, and link to clear filters/student records; focused transcript workflow test passed `5 / 30`, adjacent CoE frontend/UX/v0.03 checks passed `14 / 274`; browser confirmed unauthenticated access redirects cleanly to login with no debug text or console errors. |
| RUC-039 | Applicant admission operations | `/applicant/admission-operations` missing assessment/seat guidance | Medium | Applicant self-service showed terse `No assessment slot assigned yet.` and `No seat or waitlist record yet.`, so applicants could not tell whether to wait for staff scheduling, review checklist/status, or check offers. | fixed_verified; missing assessment and seat/waitlist states now explain staff-published assessment slots, reschedule timing, selection/offer-round timing, and link to checklist/status/offers; focused applicant UX test passed `4 / 51`, adjacent Admission applicant/frontend/v0.039 checks passed `31 / 525`; browser confirmed unauthenticated access redirects cleanly to login with no debug text or console errors. |
| RUC-040 | Applicant fee self-service | `/applicant/fees` missing installment guidance | Medium | Applicant fee page showed only `No fee installments configured for your program yet.`, so applicants could not tell whether the blocker was admission stage, batch fee rules, accounts setup, or registration-fee proof. | fixed_verified; empty installment state now explains Admissions/Accounts fee milestone publication, stage/batch/fee-rule readiness, registration-fee/status next steps, and links to Status, Registration Fee, and Checklist; focused applicant action-entry test passed `4 / 18`, adjacent applicant/readiness/frontend checks passed `21 / 842`; browser confirmed unauthenticated access redirects cleanly to login with no debug text or console errors. |
| RUC-041 | Applicant offer self-service | `/applicant/offer-letters` missing offer-round guidance | Medium | Applicant offer page showed only `You will see your offer letters here once the admission team has issued them.`, so selected/waitlisted applicants could not tell that offer rounds, waitlist movement, seat holds, fee readiness, and deadlines control when offers appear. | fixed_verified; empty offer state now explains selection/waitlist movement, offer-round publication, seat-hold deadline creation, fee/checklist readiness, and links to Status, Checklist, Admission Operations, and Fees; focused applicant UX test passed `5 / 65`, adjacent offer/applicant/frontend checks passed `47 / 593`; browser confirmed unauthenticated access redirects cleanly to login with no debug text or console errors. |
| RUC-042 | Applicant offer detail | `/applicant/offer-letters/{offer}` response labels | Medium | Offer detail used symbol-prefixed response, warning, accepted, and declined labels that rendered as mojibake in PowerShell/browser evidence and made the critical accept/decline flow less clear. | fixed_verified; offer response labels now use plain text (`Accept Offer`, `Decline Offer`, `Important`, `Offer Accepted`, `Offer Declined`) and applicant offer views are ASCII-only; focused/adjacent offer/applicant checks passed `53 / 672`; browser confirmed unauthenticated offer route redirects cleanly to login with no debug text or console errors. |
| RUC-043 | Applicant document self-service | `/applicant/documents` no published requirements | Medium | A program with no active required-document rules could show `0/0 Uploaded` and an otherwise blank document area, leaving applicants unsure whether documents were complete, missing, or not configured. | fixed_verified; documents page now explains when Admission staff have not published the program checklist, what will appear after configuration, and links to Checklist, Status, and Dashboard; focused applicant UX test passed `6 / 76`, adjacent document/applicant/frontend checks passed `26 / 855`; browser confirmed unauthenticated documents route redirects cleanly to login with no debug text or console errors. |
| RUC-044 | Applicant application form | `/applicant/application` dynamic select placeholder | Low | Dynamic application section dropdowns used a symbol placeholder that rendered as mojibake in shell/browser evidence, making the first application form look unpolished and less readable. | fixed_verified; application section selects now use ASCII-safe `Select an option`; focused applicant UX test passed `7 / 83`, adjacent applicant/readiness/status/frontend checks passed `21 / 809`; browser confirmed unauthenticated application route redirects cleanly to login with no debug text or console errors. |
| RUC-045 | Applicant portal separators | `/applicant/dashboard`, `/applicant/checklist`, `/applicant/admission-operations` header/detail separators | Low | Applicant-facing headings and detail rows still used middle-dot separators that could render as `Â·`, making otherwise corrected pages look broken in some shells/browser evidence. | fixed_verified; Applicant dashboard, checklist, and Admission Operations now use ASCII hyphen separators, and Applicant UX tests assert the corrupted separator byte patterns stay absent; focused Applicant UX passed `7 / 90`, adjacent applicant/readiness/status/frontend checks passed `21 / 809`; browser confirmed affected routes redirect cleanly when unauthenticated with no debug text, mojibake, or console errors. |
| RUC-046 | Applicant fee self-service | `/applicant/fees` unpublished installment due date | Low | Fee installment cards showed `Due: N/A` when Admission/Accounts had not published a due date, which is vague in a financial workflow and leaves applicants unsure whether data is missing or not applicable. | fixed_verified; fee cards now show `Due date not published`, payment detail fallback uses `Installment not linked`, and focused fee/action tests passed `5 / 24`; adjacent applicant/payment/frontend checks passed `35 / 903`; browser confirmed unauthenticated fees route redirects cleanly with no debug text, mojibake, or console errors. |
| RUC-047 | Admission counsellor workspace | `/admission/counsellor-workspace` empty queues and vague fallbacks | Medium | Counsellor Workspace used blank tables/lists when assigned leads, applicants, reminders, or attention queues were empty, and used `N/A` for missing program data, leaving counsellors unsure whether work was clear, unassigned, or broken. | fixed_verified; workspace now explains empty assigned lead/applicant/reminder/attention queues, uses `Program not assigned` and `Next action not set` fallbacks, and focused counsellor/telecaller UX passed `3 / 44`; adjacent Admission frontend/counsellor/v0.031 checks passed `27 / 521`; browser confirmed unauthenticated counsellor workspace redirects cleanly with no debug text, mojibake, or console errors. |
| RUC-048 | Admission manager workspace | `/admission/manager-workspace` empty operational queues | Medium | Manager Workspace used blank table/list/card sections when team KPI rows, unassigned/stale leads, reminder stats, or pending reviews were empty, leaving managers unsure whether the queue was clear, out of scope, or broken. | fixed_verified; manager workspace now explains empty team scope, clear lead queues, missing reminder activity, and clear manager-review queues; focused supervisor UX passed `5 / 67`; adjacent Admission frontend/manager/v0.031 checks passed `27 / 609`; browser verified seeded manager login, Manager Workspace render, and Reminder Queue drilldown without debug text or console errors. |
| RUC-049 | Admission officer queues | `/admission/documents/queue`, `/admission/payments/queue` empty states and fallback labels | Medium | Officer document/payment queues used terse empty states and visible corrupted/fallback labels such as rupee/mojibake symbols and `N/A`, making it unclear whether queues were clear, filtered, out of scope, or broken. | fixed_verified; document and payment queues now explain pending-only scoped behavior, provide Clear Filters/Open Applicants actions, and use ASCII-safe `Rs.` plus meaningful missing-data labels; focused manager/officer readiness passed `6 / 219`; adjacent Admission frontend/KPI/v0.039 checks passed `29 / 448`; browser verified both queue routes with clean console, no debug text, and no mojibake. |
| RUC-050 | Admission telecaller calling desk | `/admission/calling-desk` empty next-call queue | Medium | Calling Desk explained the active-call empty state but the right-side Next Call Queue rendered a blank table when no records were eligible, leaving telecallers unsure whether callbacks, retries, hot leads, or parent follow-ups were clear. | fixed_verified; Next Call Queue now explains clear scoped callbacks/retries/hot leads/parent follow-ups and links to callback reminders/leads; Recent Objections empty state now explains when trends appear; focused telecaller UX passed `4 / 59`; adjacent Admission frontend/telecaller/v0.038 checks passed `31 / 550`; browser verified Calling Desk render and Review Objections drilldown with clean console and no mojibake. |
| RUC-051 | Admission applicant staff list/detail | `/admission/applicants`, `/admission/applicants/{applicant}` fallback labels and empty list state | Medium | Staff applicant list/detail pages still exposed vague `N/A`, symbol separators, rupee/mojibake labels, and a one-line empty applicant list, leaving staff unsure whether data was missing, filtered, or outside their Admission scope. | fixed_verified; applicant list now explains scope/filter empty state with Clear Filters and Open Leads actions, list rows use readable missing user/contact/follow-up labels, applicant detail uses ASCII-safe status, counselling, scholarship, document, and staff-user fallbacks; focused/detail/frontend tests passed `31 / 674`; browser verified applicant list render with scope guidance, no `N/A`, no mojibake, no debug text, and clean console. |
| RUC-052 | Admission payment overview | `/admission/payments/{program}` scoped totals and empty state | High | Program payment overview used scoped rows but unscoped summary/breakdown totals, so lower-role staff could see totals that did not match visible records. The page also used `N/A`, rupee/mojibake labels, and a dead `No payments found.` empty state. | fixed_verified; payment overview summary, breakdown, and rows now share the same Admission visibility scope; empty payment lists explain role scope/program/filter prerequisites and link to Clear Filters, Applicants, and Pending Queue; payment views use ASCII-safe `Rs.` and meaningful missing-data labels. Focused/adjacent Admission checks passed `46 / 739`; browser verified `/admission/payments/1` and filtered empty state with clean console, no `N/A`, no mojibake, and working Clear Filters interaction. |
| RUC-053 | Admission refund workflow | `/admission/refunds`, `/admission/refunds/create`, `/admission/refunds/{refund}/show` refund list/create/detail guidance | Medium | Refund list/create/detail pages still exposed mojibake currency/separators, vague dash fallbacks, and a one-line empty refund list, making it unclear whether records were missing, filtered, out of scope, or blocked by missing verified payments. | fixed_verified; refund list now explains role-scope/status-filter/refundable-payment prerequisites and links to Clear Filters, Applicants, and Payment Queue; create/detail pages use ASCII-safe `Rs.` labels, readable payment/applicant/bank fallbacks, and clean approval/process labels. Focused refund + adjacent Admission frontend checks passed `29 / 471`; browser verified `/admission/refunds?status=processed` and Clear Filters with clean console, no `N/A`, no mojibake, and no old empty state. |
| RUC-054 | Admission scholarships | `/admission/scholarship-schemes`, `/admission/scholarship-schemes/create`, `/admission/scholarship-schemes/{scheme}/edit`, `/admission/scholarship-disbursements` scholarship setup/disbursement UX | Medium | Scholarship setup and disbursement pages still exposed corrupted currency/separator text, vague unlimited/missing fallbacks, and a weak all-clear queue message that did not explain role scope, program filters, or how awards enter the disbursement queue. | fixed_verified; scheme list/forms and disbursement queue now use ASCII-safe `Rs.` labels, readable unlimited/missing-data fallbacks, operational empty-state guidance, filter context, and next-step links to Applicants/Schemes. Focused scholarship workflow passed `16 / 139`; adjacent Admission frontend readiness passed `19 / 366`; browser verified `/admission/scholarship-disbursements?program_id=1` and scoped Clear Filters with clean console, no `N/A`, no mojibake, and visible scoped queue guidance. |
| RUC-055 | Admission waitlist | `/admission/waitlist/{program}` waitlist capacity/filter/list UX | Medium | Waitlist Management still showed mojibake in the title and seat placeholders, `N/A` applicant fallbacks, and a terse empty state that did not explain batch filters, merit-list decision source data, or missing seat matrix setup. | fixed_verified; waitlist now uses ASCII-safe titles/placeholders, visible program/batch filter context, readable applicant/application/batch fallbacks, operational empty-state guidance, and next-step links to Merit List and Seat Matrix. Focused waitlist integrity/UX passed `8 / 50`; adjacent Admission offer-seat UX plus frontend readiness passed `23 / 423`; browser verified `/admission/waitlist/1?batch_id=1` and Clear Filter with clean console, no `N/A`, no mojibake, and source-list guidance. |
| RUC-056 | Admission dashboard activity panels | `/admission/dashboard` follow-up and recent-interaction UX | Medium | The main Admission dashboard still used `N/A` fallbacks in follow-up/recent-interaction panels and a terse `No follow-ups due` empty state, leaving staff unsure whether the queue was clear, unassigned, or missing counselling activity setup. | fixed_verified; dashboard now explains clear 7-day follow-up queue prerequisites, links to Applicants, and uses readable applicant/application/staff/note fallbacks. Focused Admission frontend readiness passed `20 / 377`; KPI drilldown consistency passed `2 / 47`; browser verified `/admission/dashboard` and the Open Applicants drilldown with clean console, no `N/A`, no mojibake, no debug text, visible filter summary, and operational source-list guidance. |
| RUC-057 | Admission selection session detail | `/admission/sessions/{session}` assessment session detail, panel readiness, candidate assignment guidance | Medium | Selection Session detail still used symbol/corrupted time and panel placeholders, `N/A` applicant/program fallbacks, and a terse `No candidates assigned yet.` message that did not explain why call letters, attendance, scoring, and check-in were unavailable. | fixed_verified; session detail now uses ASCII-safe time separators, readable applicant/program/panel/coordinator fallbacks, evaluator/capacity readiness labels, and operational no-candidate guidance with links to shortlisted applicants and Assessment Operations. Focused Admission frontend readiness passed `21 / 399`; adjacent assessment/offer-seat UX passed `4 / 57`; browser verified `/admission/sessions/3` and Assessment Operations drilldown with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-058 | Admission scoring pages | `/admission/sessions/{session}/scores`, `/admission/applicants/{applicant}/scorecard` scoring readiness and scorecard UX | Medium | Score sheet and applicant scorecard still exposed corrupted title separators, vague `N/A` venue/program/applicant fallbacks, terse empty-score messages, and unclear prerequisites for why score entry or scorecards were unavailable. | fixed_verified; score sheet now explains candidate assignment and Present attendance prerequisites, links back to session attendance, and uses readable venue/program/applicant placeholders. Applicant scorecard now explains session assignment, present attendance, and score-sheet scoring prerequisites with links to Selection Sessions and Assessment Operations. Focused scoring UX passed `1 / 20`; adjacent Admission frontend readiness passed `22 / 419`; Browser verified `/admission/sessions/3/scores` with clean console, no `N/A`, no mojibake, and source navigation. Browser direct scorecard access for the current scoped session correctly returned the friendly 403 without debug text; positive scorecard rendering is covered by focused PHPUnit. |
| RUC-059 | Admission bulk communication | `/admission/bulk-communication` filter, preview, and no-match recipient UX | Medium | Bulk Communication still showed corrupted filter placeholders and message placeholder text, recipient fallback symbols, and a terse no-match preview state that did not explain whether staff should clear filters or adjust audience scope before composing. | fixed_verified; filter placeholders now use readable `Any Status`, `Any Program`, and `Any Batch`; message placeholder and recipient fallbacks are ASCII-safe; no-match preview explains the active status/program/batch filters and provides a Clear Filters action. Focused bulk communication UX passed `1 / 35`; adjacent Admission communication/frontend checks passed `6 / 116`; Browser verified base and no-match preview states with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-060 | Admission staff registration fee | `/admission/applicants/{applicant}/registration-fee` staff payment recording UX | Medium | Staff-side registration-fee recording still used corrupted title/header separators, vague applicant/program fallbacks, rupee-symbol rendering risk, and a corrupted payment-method placeholder, making a financial workflow look broken and less clear. | fixed_verified; staff page now uses readable applicant/program/application fallbacks, `Rs.` amount labels, `Select Method`, and a staff recording sequence explaining identity confirmation, payment-reference verification, proof upload, duplicate-reference blocking, and applicant continuation after recording. Focused staff fee UX passed `1 / 14`; full registration-fee workflow file passed `10 / 59`; Browser direct access for the current scoped session returned the friendly 403 without debug text, `N/A`, or mojibake, while authorized positive rendering is covered by PHPUnit. |
| RUC-061 | Admission fee installments setup | `/admission/fee-installments/{program}` admission fee milestone configuration UX | Medium | Fee Installments still showed corrupted title/currency/due-date placeholders and a terse `No installments configured yet.` empty state, leaving staff unclear that these milestones control applicant admission fee visibility and become locked after payment history exists. | fixed_verified; page now explains the admission fee setup sequence, uses `Rs.` amount labels, shows `Due date not published` for missing due dates, provides Clear Batch Filter when scoped, and explains the empty setup state plus applicant portal impact. Focused fee-installment UX passed `1 / 22`; adjacent fee-installment configuration subset passed `4 / 48`; Browser verified `/admission/fee-installments/1` with clean console, no `N/A`, no mojibake, and readable financial labels. |
| RUC-062 | Admission seat matrix setup | `/admission/seat-matrices/{program}` offer, waitlist, and enrollment seat-control setup UX | Medium | Seat Matrix still showed corrupted title/subtitle separators and a terse empty state, leaving staff unclear that this setup controls offer rounds, waitlist promotions, manual seat holds, and enrollment capacity. | fixed_verified; page now explains the seat-control setup sequence, commitment-lock risk, and empty setup impact before offer/waitlist/enrollment workflows. Focused seat-matrix UX passed `1 / 20`; adjacent seat-matrix integrity passed `6 / 47`; Browser verified `/admission/seat-matrices/1` with clean console, no `N/A`, no mojibake, and readable setup guidance. |
| RUC-063 | Admission merit list setup and decisions | `/admission/merit-list/{program}`, `/admission/merit-list/{program}/show` selection, waitlist, offer, and seat-control source UX | High | Merit List pages still had corrupted title/list separators, vague `No merit list generated yet` and `No entries found` states, `N/A`/dash fallbacks, non-linked summary cards, and a hidden 500 in the bulk offer generation form because the required program route parameter was missing. | fixed_verified; setup now explains the merit-list control sequence and regeneration lock risk, KPI cards link to filtered list views, list pages show visible filter/row summary and actionable no-match guidance, fallbacks are readable, and bulk offer generation action includes the program parameter. Focused merit-list UX passed `1 / 38`; adjacent merit decision integrity passed `7 / 70`; Browser verified `/admission/merit-list/1` and `/admission/merit-list/1/show?decision=selected` with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-064 | Admission enrollment confirmation | `/admission/enrollment`, `/admission/enrollment/{applicant}/create`, `/admission/enrollment/confirmation/{confirmation}` final applicant-to-student conversion UX | High | Enrollment pages still used corrupted separators/dash fallbacks and a terse `No enrollments found` state, leaving staff unclear that enrollment requires selected status, verified payment, mandatory documents, roll number assignment, student profile creation, and Academics handoff review. | fixed_verified; enrollment list/create/detail pages now explain the enrollment-to-student sequence, readiness blockers, student-profile creation, and Academics handoff context; empty lists direct staff to selected applicants; readable fallbacks replace `N/A`/mojibake. Focused enrollment flow passed `1 / 67`; adjacent enrollment tests passed `8 / 115`; Browser verified `/admission/enrollment` with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-065 | Admission enrollment letter | `/admission/enrollment/confirmation/{confirmation}/letter` official enrollment PDF output | Medium | Enrollment Confirmation Letter template still had corrupted title separators, bullet/currency mojibake risk, and dash fallbacks in official student-facing output. | fixed_verified; letter template now uses ASCII-safe title, letterhead separators, `Rs.` payment labels, and readable missing-link fallbacks. Focused completed-letter test passed `1 / 10`; adjacent enrollment tests passed `8 / 122`; static template scan found no `N/A`, mojibake, dash placeholder, or corrupted rupee/bullet patterns. |
| RUC-066 | Admission selection process setup | `/admission/selection-process/{program}/steps`, `/admission/selection-process/steps/{step}/parameters` assessment/scoring setup UX | Medium | Selection Process setup pages still showed corrupted title separators, terse empty states, and dash placeholders, leaving staff unclear that steps and parameters drive sessions, scoring, scorecards, merit lists, and selection decisions. | fixed_verified; steps now explain the assessment-step, scoring-parameter, session, score-entry, and merit-list setup sequence plus post-scoring lock risk; parameters now explain rubric usage, score-total readiness, and evaluator-scoring prerequisites; empty states and fallbacks are readable. Focused UX test passed `1 / 33`; adjacent selection/scoring checks passed `8 / 119`; Browser verified steps and scoring-parameter pages with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-067 | Admission email notifications | Applicant/staff Admission mail subjects and templates for application, selection, session, payment, offer, enrollment, and follow-up notifications | Medium | Admission email subjects and bodies still used `N/A`, em-dash/mojibake-prone separators, rupee/check symbols, and terse missing-data labels, making applicant/staff notifications look broken outside the web UI. | fixed_verified; Admission mail subjects now use ASCII-safe separators and readable pending labels; applicant/staff email templates use clear program/application/payment/date fallbacks and `Rs.` financial labels. Focused email rendering test passed `1 / 120`; adjacent Admission communication/frontend checks passed `26 / 491`; static scan of Admission mail classes/templates is clean except negative assertions in the test. |
| RUC-068 | Admission reports management view | `/admission/reports` conversion, compliance, counsellor, and geography reporting UX | Medium | Admission Reports had weak empty states and silently treated missing seat-matrix intake as `1`, so management could misread missing source setup as real reporting data. | fixed_verified; report panels now explain required source data for lead sources, programs, categories, counsellor assignments, and geography; missing seat matrix now displays `Seat intake not configured` while calculations use a safe denominator. Focused report/communication UX passed `5 / 85`; adjacent reporting/frontend checks passed `24 / 433`; Browser verified `/admission/reports` with clean console, no `N/A`, no mojibake, and no debug text. |
| RUC-069 | Admission productivity analytics | `/admission/counsellor-performance`, `/admission/objection-analytics`, `/admission/parent-journeys` staff productivity pages | Medium | v0.037 productivity pages were reachable but still felt like technical record screens: objection and parent journey tables had no empty-state guidance, and counsellor performance did not explain the manager review sequence or target/coaching prerequisites. | fixed_verified; productivity pages now explain the manager workflow, objection analysis workflow, and parent decision-maker follow-up sequence; empty target, coaching, objection, and parent-journey states now tell staff what source data is missing and where to act next. Focused empty-state test passed `1 / 31`; full v0.037 Admission test passed `10 / 114`; adjacent Admission frontend/counsellor checks passed `26 / 478`; Browser verified `/admission/objection-analytics` with clean console, visible guidance, no `N/A`, no mojibake, and no debug text. |
| RUC-070 | Admission integrations and normalization | `/admission/integrations`, `/admission/assessment-normalization` production-readiness pages | Medium | Integration and normalization pages were technically reachable but still read like technical tables: integrations had weak webhook/retry empty states, and assessment normalization had no empty-state guidance when panels or evaluator scores were not ready. | fixed_verified; integrations now explains provider readiness, sandbox testing, webhook receipts, and retry review; normalization now explains chair review, outlier review, evaluator-score prerequisites, and links back to the assessment control room when no normalized scores exist. Focused empty-state test passed `1 / 23`; full v0.037 Admission test passed `11 / 137`; adjacent Admission frontend/assessment checks passed `26 / 476`; Browser verified `/admission/integrations` with clean console, visible guidance, no `N/A`, no mojibake, and no debug text. |
| RUC-071 | Admission governance review pages | `/admission/script-compliance`, `/admission/automation-simulation`, `/admission/route-access-audit`, `/admission/accessibility-audit` leadership quality-control pages | Medium | Admission governance pages were still too technical: Script Compliance had mojibake and blank table behavior, Automation Simulation did not explain safe simulation/run sequencing or empty rules/conflicts, and audit pages had little guidance for release reviewers. | fixed_verified; governance pages now explain script review, automation simulation safety, route-security review, and accessibility audit workflows; empty script/template/log, automation/simulation/conflict, and checklist states now describe source-data prerequisites and next actions. Focused governance UX passed `1 / 42`; full v0.037 Admission test passed `12 / 179`; adjacent Admission frontend/communication checks passed `27 / 504`; Browser verified `/admission/script-compliance` with clean console, visible guidance, no `N/A`, no mojibake, and no debug text. |
| RUC-072 | Admission saved views | `/admission/saved-views` saved filter workflow | Medium | Saved Views stored structured filters but the page did not clearly explain how users should create, reuse, and open saved views from daily work surfaces; empty surface states were weak and source links were not obvious. | fixed_verified; Saved Views now explains the create/apply workflow, shows selected surface context and record count, links to source work surfaces, and explains how to create the first reusable view when a surface has none. Focused saved-view test passed `1 / 27`; full v0.037 Admission test passed `12 / 193`; adjacent Admission frontend/supervisor checks passed `27 / 486`; Browser DOM/console verified `/admission/saved-views?surface=counsellor_desk` with visible guidance, source link, no `N/A`, no mojibake, and no debug text. |
| RUC-073 | Admission walk-in desk | `/admission/walk-ins` campus visit capture, filtering, sorting, and conversion report | Medium | Walk-in Desk still had static table headers, `N/A` program fallbacks, blank filtered table/report states, and a conversion report built outside the current viewer/filter scope, so Admission managers could see unclear or mismatched walk-in conversion data. | fixed_verified; Walk-in Desk now explains the visit-to-lead workflow, shows visible filter/sort summary, adds sortable visitor/visit/status headers, uses readable missing-data labels, shows actionable no-match table/report states, and scopes the conversion report through the same Admission visibility and program/search filters. Focused operational-table test passed `1 / 29`; full v0.033 Admission test passed `2 / 36`; adjacent Admission frontend readiness passed `22 / 419`; Browser DOM/console verified `/admission/walk-ins?search=no-matching-walk-in-visitor&sort=visitor_name&direction=asc` with visible workflow guidance, no `N/A`, no mojibake, no debug text, and clean console. |
| RUC-074 | Admission application PDF | `/admission/applicants/{applicant}/application-pdf` official printable application output | Medium | The official printable application template still emitted `N/A`, mojibake-prone bullets/dashes, and a dash placeholder for missing application fields, making submitted application PDFs look unfinished when optional program, batch, exam, document, or dynamic form data was unavailable. | fixed_verified; Application PDF output now uses readable missing-data labels, ASCII-safe header separators, and `Not provided` for empty dynamic fields. Focused template-render test passed `1 / 16`; adjacent application PDF route tests passed `3 / 22`; static template scan found no `N/A`, mojibake markers, `&bull;`, `&mdash;`, or em dash placeholders. |
| RUC-075 | Admission fee receipt PDF | `/admission/payments/{payment}/receipt` official Admission payment receipt output | Medium | Admission Fee Receipt still emitted `N/A` in applicant/payment fields and read legacy non-existent payment properties, so verified payment receipts could show blank/missing method/reference data even when the model stored `payment_mode` and `transaction_reference`. The Blade helper also redeclared `amountInWords()` on repeated renders in the same PHP process. | fixed_verified; Receipt output now uses actual payment fields, readable missing-data labels, and a guarded amount-in-words helper. Focused receipt-label test passed `1 / 21`; full receipt branding test passed `3 / 29`; adjacent Admission receipt route check passed `1 / 3`; static template scan found no `N/A`, mojibake markers, old bullet/dash placeholders, or old `payment_method`/`reference_number` references. |
| RUC-076 | Admission merit official outputs | `/admission/merit-list/{program}/export`, `/admission/merit-list/{program}/category-report` merit PDF and category seat report | Medium | Merit List PDF and Category-Wise Report still used mojibake separators, `N/A`, and dash placeholders, making official selection/ranking exports and seat-fill reports look unfinished when applicant identity, application number, step scores, academic scores, or seat-matrix data was missing. | fixed_verified; Merit PDF now uses ASCII-safe titles, readable applicant/application/score fallbacks, and Category Report explains missing seats/fill rate as seat-matrix setup dependency. Focused merit output test passed `1 / 20`; full merit decision integrity test passed `8 / 90`; adjacent assessment/offer/seat UX checks passed `4 / 57`; static scan found no `N/A`, mojibake markers, `&bull;`, `&mdash;`, or em dash placeholders in the two merit output templates. |
| RUC-077 | Admission offer-letter staff pages | `/admission/offer-letters/{program}`, `/admission/offer-letters/{offer}` offer list/detail UX | Medium | Staff offer-letter pages still used a mojibake-prone title separator, a terse `No offer letters found.` empty state, and `N/A` for missing issuing staff, leaving staff unclear how offer records are generated from merit-list and seat-readiness workflows. | fixed_verified; Offer list now uses ASCII-safe title text and explains the merit-list/seat-readiness prerequisites when no offers match, while offer detail uses readable issuer/applicant/date fallbacks. Focused offer index/detail checks passed `2 / 25`; full OfferLetterTest passed `22 / 116`; static scan found no `N/A`, mojibake markers, old bullet/dash placeholders, or old `No offer letters found.` text in the offer index/detail views. |
| RUC-078 | Admission call-letter PDF | `/admission/applicants/{applicant}/call-letter` official assessment call-letter output | Medium | Call Letter PDF still used decorative box-drawing comments and dash entity placeholders for missing program, batch, session time, venue, and reporting time, making official assessment notices look unfinished when scheduling data was not yet announced. | fixed_verified; Call Letter template now uses ASCII-safe comments/separators and readable missing schedule labels such as `Program not assigned`, `Time not announced`, and `Venue not announced`. Focused call-letter scope/render checks passed `2 / 13`; full `AdmissionFlowTest` passed `22 / 180`; adjacent v0.031 call-letter regression passed `1 / 4`; static scan found no `N/A`, mojibake markers, `&mdash;`, `&ndash;`, `&bull;`, em dash, or box-drawing placeholders in the template. |
