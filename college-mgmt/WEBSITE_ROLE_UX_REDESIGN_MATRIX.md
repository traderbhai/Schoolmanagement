# Website Role UX Redesign Matrix

This file controls the next frontend phase: making the app easy to operate for real institute users. It is different from the previous safety/readiness files.

Previous UX work proved that many pages open, have guidance, avoid obvious placeholders, and pass focused tests. That does not prove the interface is easy, compact, logical, or naturally usable. This matrix tracks role-level redesign readiness.

## UX Completion Definition

A role is not UX-complete until a real user can do the role's daily work from the first screen without needing to understand the database model, route names, or internal module history.

Each role must satisfy:

- `Landing`: correct default page for the role.
- `Navigation`: grouped by daily work, not by technical modules.
- `Today`: first viewport shows the most important work now.
- `Workflow`: primary tasks are ordered in the same sequence users follow in real life.
- `Action`: main actions are visible, named plainly, and explain what happens next.
- `Data`: counts, tables, and detail panels use the same source and filters.
- `State`: draft, pending, blocked, approved, published, locked, overdue, and completed states are visually distinct.
- `Empty`: empty states explain whether data is missing because of setup, scope, filters, or no current work.
- `Density`: operational pages are compact enough for repeated daily use.
- `Mobile`: self-service and high-frequency staff pages work at phone width.
- `Evidence`: focused test plus browser or rendered-page evidence proves the workflow.

## Design Principles For This App

- This is an institute operations app, not a marketing website.
- Prefer compact command surfaces, dense tables, clear filters, and source-linked KPIs.
- Avoid decorative cards, oversized hero layouts, repeated empty panels, and long instruction blocks above work queues.
- Put the next practical action near the relevant record.
- Use role language: `Call next lead`, `Verify document`, `Approve timetable`, `Publish result`, `Assign mentor`.
- Hide or de-emphasize secondary setup links unless the user is in a governance role.
- A dashboard metric that looks clickable must open a matching filtered source list.

## Role Redesign Order

| Order | Role / Area | Primary daily job | Current UX risk | Redesign target | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Admission Applicant | Complete application, documents, fees, assessments, offer, and enrollment readiness. | Portal has many pages; user may not know the next required action. | One checklist-led dashboard with exact blocker, next action, and staff-owner context. | redesign_started |
| 2 | Admission Counsellor / Telecaller | Work leads/applicants, call, log outcome, schedule follow-up, escalate. | Too many admission tools can split daily work across menus. | One daily operating desk with next call, blocker queue, quick actions, and timeline. | redesign_started |
| 3 | Admission Manager / Head | Assign work, monitor team, unblock documents/payments/offers/seats. | Command/workbench/report surfaces can feel duplicated. | One supervisor command path: team workload, exceptions, approvals, drilldowns, reports. | redesign_started |
| 4 | Student | See today's classes, dues, results, documents, requests, notices. | Self-service pages may expose operational structure rather than student tasks. | Mobile-first student dashboard with today, blockers, official records, and requests. | redesign_started |
| 5 | Teacher | Teach classes, mark attendance, upload material, create assignments, enter marks, mentor. | Teaching actions are spread across several pages. | Teaching day workspace plus course-specific detail pages. | redesign_started |
| 6 | Parent | Monitor linked child attendance, results, fees, notices, hostel/transport. | Parent may see fragments without a clear concern/escalation path. | Child-first dashboard with alerts, financial/academic status, and contact/escalation route. | redesign_started |
| 7 | Dean Academics | Govern planning, risk, approvals, reviews, workload, induction. | Dean OS is broad and can become a collection of modules. | Dean command center organized by Plan, Govern, Deliver, Assess, Improve, Report. | redesign_started |
| 8 | PMC | Run course allocation, groups, faculty load, timetable, delivery, student success. | Timetable workflow has many prerequisite surfaces. | Wizard-like sequence: course basket -> groups -> faculty -> constraints -> generate -> approve. | redesign_started |
| 9 | CoE / Exam | Prepare exams, hall tickets, marks, results, transcripts, appeals. | Official-state boundaries must be visible and action order must be obvious. | Exam readiness cockpit with publish locks, blockers, and official output status. | redesign_started |
| 10 | IQAC | Track OBE, attainment, feedback closure, evidence, corrective actions. | Quality work can feel like reports instead of action ownership. | Quality command board with evidence gaps, owners, due dates, and closure actions. | redesign_started |
| 11 | Program Leadership | Monitor program risk, delivery, curriculum, interventions, reports. | Program scope and escalation path may be unclear. | Scoped program command page with risks, owners, interventions, and Dean/PMC escalation. | redesign_started |
| 12 | Course Delivery | Track teaching plans, sessions, materials, missed classes, remedials. | Delivery progress can be scattered between faculty and governance views. | Course delivery board that connects session evidence to interventions. | redesign_started |
| 13 | Accounts | Verify payments, outstanding dues, refunds, scholarships, reconciliation. | Financial queues need strong status language and matching totals. | Finance command desk with verification queue, reconciliation, blocked students, exports. | redesign_started |
| 14 | Admin / Director | Configure institute structure, roles, permissions, settings, audit. | Admin menu can become too large and setup order unclear. | Setup cockpit grouped by Institution, People, Academics, Finance, Security, Audit. | redesign_started |
| 15 | Operations | Run Library, Hostel, Transport, Assets, CMC, Notices, Notifications. | Operations modules may look like CRUD screens instead of operating queues. | Module command pages with active queue, lifecycle status, and history-preserving actions. | redesign_started |

## Execution Contract Per Role

For each role, do exactly this and then stop:

1. Inspect current route, controller, service, view, test, and seeder evidence for that role.
2. Open the main page in browser or render it through focused tests.
3. Record only real UX problems in this file.
4. Fix the highest-impact navigation, layout, and workflow-order issues.
5. Do not add fake screens or fake numbers.
6. Add or extend focused tests for the changed workflow.
7. Browser-check the role's landing page and top workflow page when tooling allows it.
8. Update this matrix, `FRONTEND_UX_REDESIGN_BACKLOG.md`, and `CODEX_PROJECT_CONTEXT.md` only after evidence passes.

## Slice Template

Use this template when starting a role:

```text
Role:
Real-world job:
Primary routes inspected:
Current first-screen problem:
Current navigation problem:
Current workflow-order problem:
Current data/drilldown problem:
Current mobile/density problem:
Fix selected:
Evidence required:
Status:
```

## First Slice To Start

Start with `Admission Applicant`, because the institute journey begins there and this role decides whether prospective students can progress without staff help.

Target result:

- Applicant lands on one checklist-first dashboard.
- The first viewport shows one next required action, blocker count, application stage, fee/document/assessment/offer state, and who owns the next step.
- Secondary pages remain available but are grouped under the checklist journey.
- All action cards open real source-backed pages.
- Final/submitted states show tracking guidance rather than dead-end buttons.

## Verified Role Slices

### Admission Applicant - Ownership Clarity

- `User`: Admission applicant.
- `Job`: understand the next required admission step and whether the applicant or Admission team owns it.
- `Routes inspected`: `applicant.dashboard`, `applicant.checklist`, `applicant.status`, applicant documents, fees, offer letters, admission operations.
- `Issue`: dashboard/checklist had source-backed blockers but did not clearly separate applicant-owned actions from Admission-team review steps.
- `Fix`: dashboard and checklist now show `Owner: Your action`, `Owner: Admission team`, or `Complete` beside the next action and each readiness row; dashboard explains the path order and replaces the batch dash fallback with `Batch not assigned yet`.
- `Evidence`: `AdmissionApplicantUxGuidanceTest` passed `8 tests / 105 assertions`; adjacent `AdmissionApplicantReadinessTest`, `ApplicantPortalActionEntryTest`, and `PortalFrontendBetaReadinessTest` passed `22 tests / 828 assertions`; static scan on edited applicant dashboard/checklist views found no weak placeholders or mojibake markers.
- `Status`: first applicant role-redesign slice verified. Continue with browser-level density/navigation review before marking the full Admission Applicant role UX-complete.

### Admission Counsellor / Telecaller - Queue Clarity

- `User`: Admission counsellor and admission telecaller.
- `Job`: find the next candidate, understand why the record is in the queue, open the source record, log outcome, and continue follow-up.
- `Routes inspected`: `admission.counsellor-desk.index`, `admission.calling-desk.index`, `admission.calling-desk.outcome`, `admission.leads.show`, `admission.applicants.show`.
- `Issue`: daily desks explained the work sequence, but candidate rows still had weak missing-data fallbacks and the Calling Desk queue did not provide a direct source-record action per row.
- `Fix`: Counsellor Desk next-call/applicant/assessment rows now use readable `Phone not recorded`, `Program not assigned`, `Next action not set`, and scoped no-work states. Calling Desk active-call and queue rows now show readable phone/program context and each queue row includes `Open record` linking to the exact lead or applicant record.
- `Evidence`: `AdmissionCounsellorTelecallerUxGuidanceTest` passed `6 tests / 83 assertions`; adjacent `AdmissionCounsellorTelecallerReadinessTest`, `AdmissionFrontendBetaReadinessTest`, and `AdmissionOsV038Test` passed `35 tests / 657 assertions`; static scan on edited Counsellor/Calling Desk views found no weak placeholders or mojibake markers.
- `Status`: first Counsellor/Telecaller role-redesign slice verified. Continue with browser-level call-outcome flow and density review before marking the full role UX-complete.

### Admission Manager / Head - Supervisor Drilldown Clarity

- `User`: Admission manager and Admission head.
- `Job`: monitor team pressure, open the exact exception queue, rebalance work, and move from command summary to operational source pages.
- `Routes inspected`: `admission.command-center.index`, `admission.manager-workspace.index`, `admission.workbench`, `admission.attention.index`, `admission.reports.index`.
- `Issue`: supervisor pages explained the management loop, but some KPI cards were static and SLA/call-pressure entries did not always point to the exact source queue or show readable context.
- `Fix`: Command Center SLA Breaches now opens the SLA queue directly; Command Center call-pressure rows use readable phone/program fallbacks and a useful no-calls empty state. Workbench KPI summary cards now link to matching workload, SLA, conversion-report, and reminder source pages with visible hints.
- `Evidence`: `AdmissionSupervisorUxGuidanceTest` passed `7 tests / 120 assertions`; adjacent `AdmissionHeadReadinessTest`, `AdmissionManagerOfficerReadinessTest`, `AdmissionFrontendBetaReadinessTest`, `AdmissionKpiDrilldownConsistencyTest`, and `AdmissionOsV039Test` passed `46 tests / 1055 assertions`; static scan on edited Command Center/Workbench views found no weak placeholders or mojibake markers.
- `Status`: first Manager/Head role-redesign slice verified. Continue with browser-level supervisor assignment/rebalance flow and command-surface density review before marking the full role UX-complete.

### Student - Self-Service Ownership Clarity

- `User`: Student.
- `Job`: understand today's academic priority, whether the student or institute owns the next step, and where the source record lives.
- `Routes inspected`: `student.dashboard`, `student.attendance`, `student.timetable`, `student.subjects.index`, `student.assignments.index`, `student.results`, `student.fees`.
- `Issue`: the Student dashboard already explained daily sequence, but some important records still used dash fallbacks and the priority card did not explicitly distinguish student-owned action from institute-owned data sources.
- `Fix`: Student dashboard priority now shows `Owner` and `Source` badges for attendance, fee, assignment, timetable, and no-urgent-action states. Student profile and timetable rows now use readable missing-data labels such as `Batch not assigned yet`, `Term not published yet`, `Mentor not assigned yet`, `Faculty not assigned`, and `Room not assigned`. The no-classes state now links to subject registration so students know what to check if the timetable looks empty.
- `Evidence`: `StudentDashboardGuidanceTest` passed `7 tests / 43 assertions`; `PortalDashboardUxGuidanceTest` passed `3 tests / 51 assertions`; adjacent `PortalFrontendBetaReadinessTest`, `PortalKpiDrilldownConsistencyTest`, and `StudentCourseContentAccessTest` passed `40 tests / 843 assertions`; static scan on the edited Student dashboard found no weak placeholder, mojibake, or fake-link markers; `git diff --check` passed for the edited Student files.
- `Status`: first Student role-redesign slice verified. Continue with browser-level dashboard/mobile review and top self-service pages before marking the full Student role UX-complete.

### Teacher - Teaching Priority Ownership Clarity

- `User`: Teacher.
- `Job`: understand the first teaching action for the day, whether the teacher or program office owns the next step, and whether timetable data is missing because it is unpublished or unassigned.
- `Routes inspected`: `teacher.dashboard`, `teacher.timetable.index`, `teacher.attendance.mark`, `teacher.assignments.index`, `teacher.materials.create`, `teacher.profile`.
- `Issue`: the Teacher dashboard explained the teaching sequence, but the priority card did not show owner/source context and the weekly timetable could render a blank table when the grid had day containers but no real entries.
- `Fix`: Teacher dashboard priority now shows `Owner` and `Source` badges for grading, attendance, active-assignment, and no-urgent-action states. Timetable cells now use readable `Subject not assigned`, `Course not assigned`, and `Room not assigned` labels. Empty timetable detection now checks for real schedule entries and shows a useful `No published timetable for your profile yet` state with a profile review link.
- `Evidence`: `TeacherDashboardGuidanceTest` passed `4 tests / 32 assertions`; adjacent `TeacherProfileMissingGracefulTest`, `TeacherScopeWorkflowTest`, `PortalDashboardUxGuidanceTest`, and `PortalFrontendBetaReadinessTest` passed `49 tests / 1025 assertions`; static scan on the edited Teacher dashboard found no weak placeholder, mojibake, old timetable-empty, or fake-link markers; `git diff --check` passed for the edited Teacher files.
- `Status`: first Teacher role-redesign slice verified. Continue with browser-level attendance/material/assignment flow and dashboard density review before marking the full Teacher role UX-complete.

### Parent - Child Monitoring Ownership Clarity

- `User`: Parent.
- `Job`: see which linked child needs attention, whether the parent, student/mentor, or Accounts owns the next step, and open the exact attendance, result, fee, or notice source page.
- `Routes inspected`: `parent.dashboard`, `parent.children`, `parent.children.attendance`, `parent.children.results`, `parent.children.fees`, `parent.notices`.
- `Issue`: the Parent dashboard explained the monitoring sequence, but the priority card did not show ownership/source context and child summary cards still used weak `N/A` labels for attendance, SGPA, and program context.
- `Fix`: Parent dashboard priority now shows `Owner` and `Source` badges for attendance, fee, and clear states. Child cards now use readable labels such as `No records`, `Not published`, `Program not assigned yet`, `Enrollment not issued yet`, and `No due date published`. The no-linked-child empty state now explains that administration must link the parent profile to student records before data can appear.
- `Evidence`: `ParentDashboardGuidanceTest` passed with `PortalDashboardUxGuidanceTest` as `5 tests / 67 assertions`; adjacent `PortalFrontendBetaReadinessTest`, `PortalOwnershipBoundaryTest`, and `PortalKpiDrilldownConsistencyTest` passed `19 tests / 710 assertions`; static scan on the edited Parent dashboard found no weak placeholder, mojibake, old generic empty-state, or fake-link markers; `git diff --check` passed for the edited Parent files.
- `Status`: first Parent role-redesign slice verified. Continue with browser-level child-detail pages and mobile review before marking the full Parent role UX-complete.

### Dean Academics - Command Ownership Clarity

- `User`: Dean Academics.
- `Job`: open the department command page, identify today's highest academic risk/approval/action, assign ownership, and drill into source records across PMC, CoE, IQAC, Program Leadership, Course Delivery, and Admission handoff.
- `Routes inspected`: `academics.dean-os.index`, `academics.dean-os.attention`, `academics.dean-os.program-risk`, `academics.dean-os.reviews`, `academics.dean-os.branch-health`, `academics.dean-os.handoff`.
- `Issue`: Dean OS already had source-linked KPIs and daily command sequence, but the top priority and attention rows did not consistently expose owner/source/due context, and fallback language used `Unassigned`.
- `Fix`: Dean dashboard priority now shows `Owner` and `Source` badges; attention items show owner, source, and due-date badges; program/action rows use readable fallbacks (`Program not assigned`, `Owner not assigned`) and title-cased statuses; empty attention/actions states explain the next Dean workflow.
- `Evidence`: `AcademicsDeanUxGuidanceTest`, `AcademicsDeanFrontendBetaReadinessTest`, and `AcademicsDeanKpiDrilldownConsistencyTest` passed `13 tests / 230 assertions`; adjacent `AcademicsDeanV007Test` and `AcademicsDeanV008Test` passed `19 tests / 118 assertions`; static scan on the edited Dean dashboard found no weak placeholder, mojibake, old `Unassigned`, or fake-link markers.
- `Status`: first Dean Academics role-redesign slice verified. Continue with browser-level planning/review/approval flows and dashboard density review before marking the full Dean role UX-complete.

### PMC - Command And Timetable Ownership Clarity

- `User`: PMC Head / Manager / Officer and Program Leadership users operating within PMC scope.
- `Job`: start from the PMC command page, identify planning/curriculum/faculty/timetable/student blockers, then follow the timetable sequence from course baskets to groups, faculty allocation, constraints, generation, approval, and freeze.
- `Routes inspected`: `academics.pmc.command`, `academics.pmc.attention.index`, `academics.pmc.approvals.index`, `academics.pmc.timetable-os.index`, `academics.pmc.timetable-generator.index`, `academics.pmc.timetable-planner.index`.
- `Issue`: PMC pages already explained the sequence, but the command entry did not clearly label ownership/source context, and attention/approval/timetable panels could look blank or terminal when there were no current rows.
- `Fix`: PMC Command now shows owner/source badges, attention rows label owner/source/due context, and attention/approval empty states explain the next operating path. PMC Timetable OS now labels owner/source context, gives non-dead-end guidance for missing generation runs/notifications/constraints, and exposes a clickable real-world sequence from student baskets to publish/freeze.
- `Evidence`: `AcademicsPmcUxGuidanceTest`, `AcademicsPmcFrontendBetaReadinessTest`, and `AcademicsPmcKpiDrilldownConsistencyTest` passed `18 tests / 511 assertions`; adjacent `AcademicsPmcV004Test` and `AcademicsPmcTimetableV041Test` passed `8 tests / 200 assertions`; static scan on the edited PMC command/timetable dashboard found no weak placeholder, mojibake, or fake-link markers; `git diff --check` passed for the edited PMC files.
- `Status`: PMC command/timetable sequencing slice verified. Latest targeted passes also fixed scoped group diagnostics for PMC managers and batch-specific generator boundaries. Continue with browser-level create/edit interactions and table-density review before marking the full PMC role UX-complete.

### CoE / Exam - Official Boundary Ownership Clarity

- `User`: CoE / Examination team, Exam Manager, and Exam Officer.
- `Job`: start from the CoE command page, identify exam readiness, marks/result, hall-ticket, transcript, appeal, or anomaly blockers, and confirm official/published boundaries before releasing documents.
- `Routes inspected`: `academics.coe.index`, `academics.coe.exam-readiness`, `academics.coe.marks-results`, `academics.coe.hall-ticket-readiness`, `academics.coe.transcripts`, `academics.coe.appeals-anomalies`, `academics.coe.reports`.
- `Issue`: CoE OS already had source-backed KPIs and filters, but dashboard cards and source rows did not show ownership/source context, and no-match source lists did not explain official-state prerequisites.
- `Fix`: CoE dashboard now labels owner/source context and section rows show owner/source badges. CoE source lists now label owner/source context per row and explain that empty filtered lists may mean no blockers, missing source workflow records, or unpublished/eligibility/registration/transcript-readiness boundaries.
- `Evidence`: `AcademicsCoeUxGuidanceTest`, `AcademicsCoeFrontendBetaReadinessTest`, and filtered `AcademicOperatingKpiDrilldownConsistencyTest` passed `9 tests / 288 assertions`; adjacent `AcademicsCoeV003Test` and `ExamCellDashboardGuidanceTest` passed `26 tests / 272 assertions`; static scan on edited CoE views found no weak placeholder, mojibake, or fake-link markers; `git diff --check` passed for edited CoE files.
- `Status`: first CoE / Exam role-redesign slice verified. Continue with browser-level legacy `/exam-cell/*` flows, marks publication, hall-ticket download, transcript issue, and appeal review before marking the full CoE role UX-complete.

### IQAC - Quality Evidence Ownership Clarity

- `User`: IQAC Head / Manager / Officer.
- `Job`: start from the IQAC command page, identify OBE, attainment, feedback, audit-evidence, or corrective-action gaps, assign ownership, and verify evidence/action closure before quality review is considered complete.
- `Routes inspected`: `academics.iqac.index`, `academics.iqac.obe-readiness`, `academics.iqac.attainment-monitoring`, `academics.iqac.feedback-quality`, `academics.iqac.audit-compliance`, `academics.iqac.reports`.
- `Issue`: IQAC OS already had source-backed KPIs and filters, but dashboard cards and source rows did not show quality owner/source context, and no-match source lists did not explain evidence/action/threshold boundaries.
- `Fix`: IQAC dashboard now labels quality owner/source context and section rows show owner/source badges. IQAC source lists now label owner/source context per row and explain that empty filtered lists may mean no quality gaps, missing OBE/attainment/feedback/audit/corrective-action records, or unresolved evidence/action/target boundaries.
- `Evidence`: `AcademicsIqacUxGuidanceTest`, `AcademicsIqacFrontendBetaReadinessTest`, and filtered `AcademicOperatingKpiDrilldownConsistencyTest` passed `9 tests / 273 assertions`; adjacent `AcademicsIqacV004Test` passed `6 tests / 32 assertions`; static scan on edited IQAC views found no weak placeholder, mojibake, or fake-link markers; `git diff --check` passed for edited IQAC files.
- `Status`: first IQAC role-redesign slice verified. Continue with browser-level evidence upload/closure, OBE/attainment drilldowns, feedback closure actions, and corrective-action review before marking the full IQAC role UX-complete.

### Program Leadership - Scoped Risk Ownership Clarity

- `User`: Program Director / Program Leader / Program Chair working within assigned program scope.
- `Job`: open the Program Leadership OS, understand assigned program scope, review delivery gaps, triage student risk, check quality signals, and escalate unresolved issues through Chair/PMC/Dean workflows.
- `Routes inspected`: `academics.program-leadership.index`, `academics.program-leadership.portfolio`, `academics.program-leadership.course-delivery`, `academics.program-leadership.student-success`, `academics.program-leadership.quality-signals`, `academics.program-leadership.reports`.
- `Issue`: Program Leadership OS already had source-backed KPIs and filters, but dashboard rows and source lists did not clearly show assigned owner/source context, and no-match lists did not explain scope, source-record, evidence, or escalation boundaries.
- `Fix`: Program Leadership dashboard now labels assigned program owner/source context and section rows show owner/source badges. Program source lists now show owner/source context per row and explain that empty filtered lists may mean no matching risks, missing portfolio/delivery/student/quality/escalation records, or unresolved scope/evidence/progress/escalation checks.
- `Evidence`: `AcademicsProgramLeadershipUxGuidanceTest`, `AcademicsProgramLeadershipFrontendBetaReadinessTest`, and filtered `AcademicOperatingKpiDrilldownConsistencyTest` passed `8 tests / 177 assertions`; adjacent `AcademicsProgramLeadershipV005Test` passed `9 tests / 34 assertions`; static scan on edited Program Leadership views found no weak placeholder, mojibake, or fake-link markers; `git diff --check` passed for edited Program Leadership files.
- `Status`: first Program Leadership role-redesign slice verified. Continue with browser-level program-risk drilldowns, intervention actions, course-delivery evidence, and Chair/PMC/Dean escalation flows before marking full Program Leadership UX-complete.

### Course Delivery - Faculty Delivery Evidence Clarity

- `User`: Faculty, Course Coordinator, Faculty Mentor, and academic leaders reviewing teaching delivery.
- `Job`: open Course Delivery OS, confirm assigned course load, review published sessions, resolve attendance risks, update engagement/material gaps, and close mentor actions with source evidence.
- `Routes inspected`: `academics.course-delivery.index`, `academics.course-delivery.course-load`, `academics.course-delivery.session-delivery`, `academics.course-delivery.attendance-interventions`, `academics.course-delivery.course-engagement`, `academics.course-delivery.mentor-actions`, `academics.course-delivery.reports`.
- `Issue`: Course Delivery OS already had workflow guidance, filters, KPI links, and source lists, but dashboard rows and source lists did not clearly show owner/source context, and no-match lists did not explain timetable, attendance, engagement, feedback, or mentor-record prerequisites.
- `Fix`: Course Delivery dashboard now labels owner/source context and section rows show owner/source badges. Course Delivery source lists now label owner/source context per row and explain that empty filtered lists may mean no delivery exceptions, missing source records, or unresolved faculty assignment, published timetable, attendance evidence, material update, feedback, or mentor follow-up checks.
- `Evidence`: `AcademicsCourseDeliveryUxGuidanceTest` passed `3 tests / 113 assertions`; adjacent `AcademicsCourseDeliveryFrontendBetaReadinessTest` with filtered `AcademicOperatingKpiDrilldownConsistencyTest` passed `5 tests / 74 assertions`; adjacent `AcademicsCourseDeliveryV006Test` passed `7 tests / 25 assertions`; static scan and `git diff --check` on edited Course Delivery files passed.
- `Status`: first Course Delivery role-redesign slice verified. Continue with browser-level session update, material follow-up, attendance intervention, mentor closure, and teacher/student visibility checks before marking full Course Delivery UX-complete.

### Accounts - Finance Queue Ownership Clarity

- `User`: Accounts Officer, Director/Admin oversight users, and finance staff reviewing collections.
- `Job`: open the Accounts dashboard, verify Admission payments, review outstanding fee demands, reconcile verified receipts, process scholarship disbursement queues, and export current finance views.
- `Routes inspected`: `accounts.dashboard`, `accounts.fee-collections`, `accounts.admission-payments`, `accounts.outstanding`, `accounts.reconciliation`, `accounts.reports`.
- `Issue`: Accounts safety and totals were already test-covered, but daily finance queues still had old mojibake currency/separator text, weak missing-data labels, and less explicit owner/source context for finance records.
- `Fix`: Accounts dashboard now labels Accounts owner/source context and makes Admission collected/overdue demand cards open source lists. Fee Collections, Admission Payment Verification, Reconciliation, and Outstanding pages now explain the finance workflow, show visible filter/source context, use owner/source row badges, replace mojibake currency/separators with `Rs.` text, and provide actionable no-match states.
- `Evidence`: `AccountsDashboardGuidanceTest` passed `13 tests / 122 assertions`; adjacent `AdminOperationsFrontendBetaReadinessTest --filter=accounts` passed `1 test / 39 assertions`; adjacent `FeePaymentTest` and `FeeDemandTest` passed `68 tests / 407 assertions`; static scan and `git diff --check` on edited Accounts files passed. Browser reached `/accounts/dashboard` as the current non-Accounts session and showed a restricted page without debug text or console errors; positive Accounts rendering is covered by role-auth feature tests.
- `Status`: first Accounts role-redesign slice verified. Continue with browser-level verification as an Accounts user, refund/scholarship action-flow checks, and richer sorting/table-density review before marking full Accounts UX-complete.

### Admin / Director - Setup Command Clarity

- `User`: Admin and Director oversight users.
- `Job`: open the Admin dashboard, understand institute setup health, check source-backed KPIs, and jump into people, academics, finance, governance, reports, audit, and security setup pages.
- `Routes inspected`: `admin.dashboard` plus Admin setup/security/report links covered by `AdminOperationsFrontendBetaReadinessTest` and adjacent access-control tests.
- `Issue`: Admin dashboard already had useful quick links, but the first screen did not explicitly label Admin/Director ownership and source context. A previous readable-label cleanup also introduced a broken fallback expression on Upcoming Exams, causing a 500 for the dashboard.
- `Fix`: Admin dashboard now labels `Owner: Admin / Director` and the source data used by institute KPIs. The broken Upcoming Exam subject fallback is fixed to show `Subject not assigned`, and dashboard money/date/chart labels use readable ASCII-safe `Rs.` and hyphen text.
- `Evidence`: focused `AdminOperationsFrontendBetaReadinessTest --filter=admin` passed `11 tests / 808 assertions`; adjacent `FrontendReadinessTest`, `AdminSystemConfigurationAccessControlTest`, `AdminRolePermissionAccessControlTest`, `AdminUserRoleIntegrityTest`, and `RoleRedirectTest` passed `79 tests / 1179 assertions`; static Admin dashboard scan found no `N/A`, mojibake, rupee/entity, `TBA`, dead `href="#"`, or broken exam-subject fallback; browser verified `http://localhost:8001/admin/dashboard` with owner/source labels visible, no debug text, and zero console warnings/errors; `git diff --check` passed for the edited Admin dashboard/test/control files.
- `Status`: first Admin / Director role-redesign slice verified. Continue with deeper setup-page form-entry and Admin information-architecture simplification later.

### Operations - Notice Board Communication Workflow

- `User`: Admin / Director users publishing institute notices and operational communication.
- `Job`: create official notices, choose the correct audience, review draft/scheduled/active/expired notices, understand when student emails are queued, and archive outdated notices without destroying communication history.
- `Routes inspected`: `admin.notices.index`, `admin.notices.create`, `admin.notices.edit`, `admin.notices.show`, notice visibility tests, shared notification/notice rules.
- `Issue`: Notice Board was still a plain CRUD table. It had no visible workflow sequence, no filters/search/source summary, a corrupted expiry fallback, weak no-match guidance, and create/edit/detail pages did not explain audience visibility, student-email dispatch, or published-notice lock rules.
- `Fix`: Notice Board now has owner/source context, search/audience/status filters, visible result total/filter summary, readable audience/status/expiry labels, source row context, view/edit/archive actions, and a no-match state with Clear Filters/Create Notice actions. Create/edit/detail pages now explain publish sequence, portal visibility, current student email queue behavior, and published communication lock rules.
- `Evidence`: focused `NoticeVisibilityIntegrityTest` passed `10 tests / 82 assertions`; adjacent `AdminOperationsFrontendBetaReadinessTest --filter=admin` passed `11 tests / 808 assertions`; static notice-template scan found no `N/A`, mojibake, rupee/entity, `TBA`, dead `href="#"`, or HTML dash/bullet placeholders. Browser reached `/admin/notices?search=no-matching-notice-title` in the current non-admin session and showed the friendly restricted page with no debug text or console errors; positive Admin rendering is covered by role-auth feature tests.
- `Status`: first Operations communication slice verified. Continue with dedicated operation-command UX for remaining Library, Hostel, Transport, Assets, CMC, and Notification Inbox flows as separate bounded slices.

### Operations - Shared Notification Inbox Workflow

- `User`: all authenticated users receiving role-specific workflow alerts.
- `Job`: review account-owned messages, filter unread/read/type/search results, open the relevant source workflow, and mark messages as read without seeing another user's notifications.
- `Routes inspected`: `notifications.index`, `notifications.show`, `notifications.mark-all-read`, notification ownership tests, portal shell rendering tests.
- `Issue`: Shared Notification Inbox already had an empty state and shell selection, but it had no search/status/type filters, no active filter summary, and less explicit account-owner/source context for users trying to understand why a message appeared or where to act.
- `Fix`: Notification Inbox now uses account-owned search/status/type filters with pagination query preservation, visible matching totals, owner/source context, per-row source labels, a useful filtered no-match state, and detail-page guidance explaining source workflow, action links, and automatic read-state update.
- `Evidence`: focused `SharedNotificationInboxUxTest` plus `NotificationTest` passed `13 tests / 66 assertions`; adjacent `PortalFrontendBetaReadinessTest` passed `13 tests / 680 assertions`; static notification-template scan found no `N/A`, mojibake, rupee/entity, `TBA`, dead `href="#"`, or HTML dash/bullet placeholders. Browser verified `/notifications?search=no-matching-message` with the real inbox, owner label, filtered empty state, no debug text, and zero console warnings/errors.
- `Status`: shared Notification Inbox communication slice verified. Continue with remaining Operations command flows as separate slices.

### Operations - Assets Metric Source Clarity

- `User`: Admin / Director operations users managing institute assets, custody, consumable stock, and movement history.
- `Job`: review asset availability, identify assigned or maintenance assets, review consumable stock and low-stock signals, assign/return assets, receive/issue stock, and export the current source view.
- `Routes inspected`: `admin.assets.index`, asset register export, assignment export, stock item export, movement export, assign/return, receive/issue stock routes.
- `Issue`: Asset Register already had lifecycle-safe forms, filters, exports, and empty-state guidance, but the top metric cards looked like dashboard KPIs without acting as drilldowns to the underlying asset register/status or stock sections.
- `Fix`: Asset metric cards now link to all assets, available, assigned, maintenance, and stock source sections. The first viewport now labels `Owner: Admin / Director operations` and `Source: Asset register, custody assignments, inventory movements`.
- `Evidence`: focused `AssetWorkflowTest` passed `22 tests / 152 assertions`; adjacent `AdminOperationsFrontendBetaReadinessTest` passed `11 tests / 808 assertions`; `git diff --check` passed for changed files. Browser check in the current PMC session confirmed `/admin/assets` shows a friendly restricted page with no debug text or console warnings/errors; positive Admin rendering is covered by the role-auth feature test.
- `Status`: Assets metric-source slice verified. Continue with Library, Hostel, Transport, or richer browser modal/action checks as separate bounded slices.
