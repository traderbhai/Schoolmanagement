# User Role UX And Workflow Audit

Purpose: role-by-role evidence log for using the SchoolManagement app as each real user type, recording ease of use, broken pages, confusing workflows, missing features, data mismatches, and follow-up development items.

Rule for this audit: a role is not marked complete until its visible navigation, daily dashboard, main workflows, drilldowns, create/edit actions where safe, lists, filters, exports, mobile/sidebar behavior, and error/debug behavior have been checked with current code/runtime evidence.

## Audit Method

For each user type:
1. Confirm seeded demo user and role/scope.
2. Open the role landing page.
3. Review sidebar grouping and whether the user can quickly identify daily work.
4. Open every visible sidebar route and primary workflow page.
5. For dashboards, click/check actionable KPI cards and verify the destination explains the same data.
6. Test representative safe actions: filters, pagination, exports, detail pages, modals, create forms without committing destructive state unless explicitly needed.
7. Record broken links, 403/404/500, debug traces, empty placeholder content, confusing labels, mismatched counts, missing filters, workflow gaps, and UX friction.
8. Record evidence: browser/manual check, PHPUnit/frontend smoke, focused test, or code inspection.

Status values:
- `pending`: not checked yet in this role audit.
- `in_progress`: inventory or partial checks done.
- `checked_no_blocker`: checked with no high-priority blocker found yet.
- `issues_found`: checked and actionable issues recorded.
- `needs_deeper_workflow_test`: pages open, but full create/edit/approval workflow still needs role simulation.

## User Type Inventory

### Global / Administration

| User Type | Demo User | Primary Landing | Manifest Routes | Status |
| --- | --- | --- | ---: | --- |
| Admin / Superuser | `admin@college.com` and `admin@demo.edu` | `admin.dashboard` | 106 | fixed_verified |
| Director | `director@college.com` | `director.dashboard` | 6 | fixed_verified |
| HOD / Department Head | `hod@college.com` | `hod.dashboard` | 7 | fixed_verified |

### Admission Department

| User Type | Demo User | Primary Landing | Status |
| --- | --- | --- | --- |
| Admission Head | `head@college.com` | `admission.dashboard` | fixed_verified |
| Admission Officer | `officer@college.com` | `admission.dashboard` | fixed_verified |
| Admission Manager | `admission.manager@college.com` | Admission workspace/manager scope | fixed_verified |
| Admission Counsellor | `counsellor@college.com` | Counsellor desk/workspace | fixed_verified |
| Admission Telecaller | `telecaller@college.com` | Calling desk / call queue | fixed_verified |
| Admission Partner / Channel Contact | `partner.citychannel@demo.edu` | Partner-facing admission scope | fixed_verified |

### Academics Department

| User Type | Demo User | Primary Landing | Manifest Routes | Status |
| --- | --- | --- | ---: | --- |
| Dean Academics | `dean@college.com` | `academics.dean-os.index` | 35 | in_progress |
| Academic Department Owner | `director@college.com` | Academics/Dean oversight | Director manifest | in_progress |
| PMC Head / Program Chair | `chair@college.com` | `academics.pmc.command` | 26 | in_progress |
| PMC Manager | `pmc.manager@college.com` | PMC scoped command/workbench | role-specific | in_progress |
| PMC Officer | `pmc.officer@college.com` | PMC operational queues | role-specific | in_progress |
| CoE / Exam Cell Head | `exam@college.com` | `academics.coe.index` | 16 | in_progress |
| Exam Manager | `exam.manager@college.com` | CoE scoped operations | role-specific | in_progress |
| Exam Officer | `exam.officer@college.com` | CoE operational queues | role-specific | in_progress |
| IQAC Head | `iqac.head@college.com` | `academics.iqac.index` | 9 | in_progress |
| IQAC Manager | `iqac.manager@college.com` | IQAC scoped quality ops | role-specific | in_progress |
| IQAC Officer | `iqac.officer@college.com` | IQAC operational queues | role-specific | in_progress |
| Program Director / Program Leader | `chair@college.com`, `pmc.manager@college.com`, `hod@college.com` | `academics.program-leadership.index` | 9 | in_progress |
| Semester Coordinator | `semester.coordinator@college.com` | Program/term scoped academic ops | role-specific | in_progress |
| Course Coordinator | `course.coordinator@college.com` | Course scoped academic ops | role-specific | in_progress |
| Faculty Mentor | `faculty.mentor@college.com` | Mentor / student-success scope | role-specific | in_progress |
| Course Delivery Oversight | `dean@college.com` | `academics.course-delivery.index` | 6 | in_progress |

### Teaching / Learning Portals

| User Type | Demo User | Primary Landing | Manifest Routes | Status |
| --- | --- | --- | ---: | --- |
| Teacher / Faculty | `anjali@demo.edu` | `teacher.dashboard` | 12 | issues_found |
| Student | `arjun.k@demo.edu` | `student.dashboard` | 36 | issues_found |
| Parent / Guardian | `parent@demo.edu` | `parent.dashboard` | 3 | in_progress |
| Applicant | `priya.sharma@applicant.demo` | `applicant.dashboard` | 6 | in_progress |

### Operations / Finance / Placement

| User Type | Demo User | Primary Landing | Manifest Routes | Status |
| --- | --- | --- | ---: | --- |
| Accounts Officer | `accounts@college.com` | `accounts.dashboard` | 6 | in_progress |
| CMC / Placement Officer | `cmc@college.com` | `cmc.dashboard` | 8 | in_progress |
| Library Operator / Admin | Admin role currently | `admin.library.index` | under Admin Operations | in_progress |
| Hostel Operator / Admin | Admin role currently | `admin.hostel.index` | under Admin Operations | in_progress |
| Transport Operator / Admin | Admin role currently | `admin.transport.index` | under Admin Operations | in_progress |
| Asset Manager / Admin | Admin role currently | `admin.assets.index` | under Admin Operations | in_progress |

## Cross-Role Findings So Far

### Finding UX-001: Role coverage is broad enough that the audit must be batched

Severity: Medium  
Status: tracking

There are at least 30 practical user types/sub-types when Academics hierarchy roles are counted separately. A single manual browser pass over every screen, action, and workflow is too broad for one unchecked run. The audit is therefore split by role batch:
- Batch A: Admin, Director, HOD.
- Batch B: Admission Head/Officer/Manager/Counsellor/Telecaller/Partner.
- Batch C: Dean Academics and Academic Department Owner.
- Batch D: PMC Head/Manager/Officer/Program Leader/Faculty Mentor.
- Batch E: CoE/Exam users and IQAC users.
- Batch F: Teacher, Student, Parent, Applicant.
- Batch G: Accounts, CMC, Library, Hostel, Transport, Assets.

### Finding UX-002: Manifest-driven navigation exists, but Admin role is extremely large

Severity: Medium  
Status: partially_fixed_verified

The Admin manifest has 106 visible routes across many groups. The grouped sidebar is structurally better than older hardcoded menus, but Admin is still cognitively heavy. During the Admin role pass, check whether common tasks are discoverable without scrolling through too many groups. Likely improvement: add an Admin “Today / Setup / Review / Exceptions” command surface that reduces dependency on raw sidebar scanning.

Evidence:
- `App\Support\FrontendNavigation::manifest()` reports `admin` with 106 manifest entries.

### Finding UX-003: KPI drilldown consistency has a recent verified baseline

Severity: Low  
Status: checked_no_blocker for the audited KPI surfaces

Recent KPI drilldown work fixed or documented summary-only behavior for Admission, Dean OS, PMC, CoE, IQAC, Program Leadership, Course Delivery, Accounts overdue demands, CMC summaries, and portal dashboards. This does not prove every workflow is usable, but it gives a reliable baseline for dashboard count-to-list behavior.

Evidence:
- `KPI_DRILLDOWN_AUDIT.md`
- Full suite baseline after that pass: `1418 tests / 11054 assertions`.

## Batch A: Admin / Director / HOD

Status: fixed_verified

Checklist:
- Admin dashboard: KPI cards, quick actions, charts, long sidebar scroll, global search.
- Governance: academic years, semesters, departments, subjects, classrooms, programs, batches.
- People: students, teachers, parents, student documents.
- Timetable: time slots, weekly timetable, teacher view.
- Finance/admin: fees, fee collection, fee report, accounts links.
- Operations: library, hostel, transport, assets.
- Access-control/settings: role hierarchy, permission matrix, feature matrix, role assignments, org hierarchy, department hierarchy, audit logs, system settings.
- Director dashboard and reports.
- HOD dashboard, faculty roster/workload, leave approvals, student grievances.

Browser/page-health sample completed for:
- `admin.dashboard`
- `admin.search`
- `admin.analytics`
- `admin.academic-years.index`
- `admin.students.index`
- `admin.fees.index`
- `admin.library.index`
- `admin.roles.permissions.index`
- `admin.users.roles.index`
- `admin.audit.index`
- `admin.settings`
- `director.dashboard`
- `hod.dashboard`

### Finding UX-A001: Admin/Director/HOD sampled pages load, but most lack semantic page headings

Severity: Medium  
Status: fixed_verified

The sampled Admin, Director, and HOD pages loaded on `localhost:8001` without console errors, Laravel/Whoops traces, missing main content, or placeholder `href="#"` links. However, the browser probe found no `h1`, `h2`, or `h3` headings on the sampled pages even though browser titles are present. This weakens page orientation for users, accessibility, and automated "page has meaningful content" checks.

Evidence:
- Browser sample checked 13 Admin/Director/HOD pages on `localhost:8001`.
- All sampled pages had `hasMain=true`.
- All sampled pages reported empty `headings`.
- Sampled titles included `Dashboard`, `Institution Analytics`, `Academic Years`, `Students`, `Fee Management`, `Library Management`, `Role Permissions`, `Role Assignments`, `Audit Log`, `Settings`, `Director Dashboard`, and `HOD Dashboard`.

Recommended fix:
- Add a shared page-header component to the Admin/Director/HOD layouts with a real `h1`, optional subtitle, breadcrumbs, and primary action.
- Update the page-health/frontend smoke tests to require at least one semantic heading in the main content area for operational pages.

Fix applied:
- The shared admin shell now renders the current page title as a compact semantic `h1` in the topbar while preserving the existing visual density.
- Browser check confirmed representative admin-shell pages now expose `h1` headings: `/admin/dashboard`, `/admission/reports`, `/accounts/dashboard`, `/admin/library`, and `/cmc/dashboard`.
- Focused frontend readiness tests passed after the change.

### Finding UX-A002: Admin sidebar is scrollable, but Admin still needs a command-first task surface

Severity: Medium  
Status: fixed_verified

The Admin desktop sidebar contains 106 links. Browser inspection confirmed the long desktop sidebar has an inner scroll container with `overflow-y: auto`, so the menu is technically reachable at the sampled 1280x720 viewport. The usability risk remains that Admin users must scan too many groups for common work.

Evidence:
- `.sidebar-desktop` contains 107 links including logout.
- Inner sidebar body `.mt-2.pb-4.flex-grow-1` has `clientHeight=652`, `scrollHeight=5109`, and `overflowY=auto`.

Recommended fix:
- Create or improve an Admin command surface grouping common tasks into Setup, People, Finance, Operations, Security, Exceptions, and Reports.
- Keep the sidebar as a secondary navigation fallback, not the primary way to perform daily Admin work.

Batch A closure verification:
- Representative Admin dashboard quick-action links are now checked for students, attendance, fee collection, notices, admissions, institutional KPI, AICTE reporting, and audit log routing.
- Safe setup entry pages are now checked for academic years, departments, programs, batches, user-role assignment, permission matrix, feature access matrix, and system settings/branding.
- Mobile/sidebar shell behavior is now checked for Admin, Director, and HOD dashboards, including the mobile sidebar target and accessible open-navigation label.
- Security/access-control screens are now checked for usable labels and no broken placeholder links on permission matrix, role assignments, feature access matrix, system settings, and audit log.

Verification:
- Focused Admin/Operations frontend readiness passed: 9 tests / 638 assertions.
- Adjacent security/access tests passed: `AdminRolePermissionAccessControlTest`, `AdminUserRoleIntegrityTest`, `AdminSystemConfigurationAccessControlTest`, `RoleRedirectTest` = 70 tests / 243 assertions.
- Batch 4 full-suite stage gate passed: 1485 tests / 12355 assertions.

## Batch B: Admission Users

Status: fixed_verified

Checklist:
- Admission Head: dashboard, command center, workbench, applicants, leads, documents, payments, assessments, offers/seats, handoff, reports, governance.
- Admission Officer: assigned applicant/lead/document/payment work.
- Admission Manager: team queue, delegation, SLA, reassignment.
- Counsellor: counsellor desk, conversation timeline, reminders, applicant blockers, parent/guardian interactions.
- Telecaller: calling desk, next-call flow, call attempts, disposition, retry schedule.
- Partner: lead submission, partner dashboard, status visibility.

Browser/page-health sample completed as `head@college.com` for:
- `admission.dashboard`
- `admission.command-center.index`
- `admission.workbench`
- `admission.calling-desk.index`
- `admission.counsellor-desk.index`
- `admission.applicants.index`
- `admission.leads.index`
- `admission.documents.queue`
- `admission.assessment-control-room.index`
- `admission.offer-rounds.index`
- `admission.handoff.index`
- `admission.reports.index`

### Finding UX-B001: Admission Head role login and primary pages are reachable

Severity: Low  
Status: checked_no_blocker for sampled page-load behavior

The normal UI login flow works for `head@college.com` and lands on `/admission/dashboard`. Sampled primary Admission pages loaded on `localhost:8001` without console errors, Laravel/Whoops traces, missing main content, placeholder `href="#"` links, or obvious empty primary content.

Evidence:
- Browser login state after sign-in: `/admission/dashboard`, title `Admission Dashboard`.
- 12 sampled Admission pages had `hasMain=true`.
- Sampled pages included tables/forms/cards from database-backed screens.

### Finding UX-B002: Admission reports page lacks a meaningful page title and semantic heading

Severity: Medium  
Status: fixed_verified

Most sampled Admission workflow pages expose a visible heading such as `Admission Command Center`, `Admission Workbench`, `All Applicants`, `Leads & Enquiries`, `Document Verification Queue`, `Assessment Control Room`, `Offer And Seat Control`, or `Admission To Academics / PMC Handoff`. `/admission/reports` is weaker: the browser title falls back to `EduManage - Portal`, and the page-health probe found no `h1`, `h2`, or `h3` heading.

Evidence:
- Browser page-health probe on `http://localhost:8001/admission/reports`.
- `title=EduManage - Portal`.
- `headings=[]`.
- The page still had main content, cards, and tables, so this is a user-orientation/semantic issue rather than a hard 500/404.

Recommended fix:
- Add `@section('title', 'Admission Reports')`.
- Add the shared page-header component with `h1` = `Admission Reports`.
- Add filter summary/export/list headings if they are missing from report sections.

Fix applied:
- `/admission/reports` now sets `@section('title', 'Admission Reports')` and `@section('page-title', 'Admission Reports')`.
- The shared admin shell renders the page title as a compact semantic `h1`.
- Browser check confirmed title `Admission Reports`, `h1=Admission Reports`, and no debug/error text.

### Finding UX-B003: Lower Admission roles can open core desks, but many pages lack semantic headings

Severity: Medium  
Status: fixed_verified for shared-shell heading coverage

Authenticated probes for Admission Officer, Admission Manager, Admission Counsellor, and Admission Telecaller show that core daily pages load without Laravel/Whoops traces. However, many daily pages still have browser titles but no semantic `h1`/`h2`, including dashboard, applicant list, lead list, reminders, counsellor desk, manager workspace, calling desk, and assessment control pages.

Evidence:
- `officer@college.com`: dashboard, workbench, applicants, leads, documents queue, payment queue, assessment control returned 200.
- `admission.manager@college.com`: dashboard, manager workspace, command center, workbench, applicants, leads, reminders, reports returned 200.
- `counsellor@college.com`: dashboard, counsellor desk, counsellor workspace, calling desk, applicants, leads, reminders returned 200.
- `telecaller@college.com`: dashboard, calling desk, call queue, leads, reminders returned 200.

Recommended fix:
- Apply the same shared operational page-header component across Admission list/workspace pages.
- Ensure role-specific pages expose the current role/scope in the subtitle or filter summary.

Fix applied:
- Admission staff pages extending the admin shell now inherit the compact semantic topbar `h1`.
- Role-specific subtitles/scope summaries remain a separate deeper UX enhancement.

### Finding UX-B006: Counsellor/Reminder pages expose real actions but are form-dense

Severity: Medium  
Status: partially_fixed_verified

The counsellor workflow is not just placeholder content: the rendered pages expose concrete POST actions for sending reminders, completing reminders, pausing reminders, saving call outcomes, skipping records, saving cadences, and bulk applicant actions. The concern is usability and safety: `/admission/reminders` rendered 70 forms for the counsellor sample, and `/admission/counsellor-desk` rendered many individual `Send` forms. This can work technically, but it is dense and hard to operate confidently.

Evidence:
- `counsellor@college.com`:
  - `/admission/calling-desk` exposes `Save Call Outcome` and `Skip This Record` forms.
  - `/admission/reminders` exposes filter fields plus repeated `Send`, `Done`, `Pause`, and `Save Cadence` actions.
  - `/admission/applicants` exposes filters and `admission/applicants/bulk-action`.

Recommended fix:
- Group repeated reminder actions into a compact action menu or row quick-action pattern.
- Add confirmation/preview for bulk and communication-affecting actions.
- Add browser tests for one safe reminder action path in a seeded test database.

Fix applied:
- `/admission/reminders` now states that send, complete, and pause actions are audited.
- Reminder `Send`, `Done`, `Pause`, and cadence-rule creation forms now require browser confirmation before committing communication/status changes.
- The counsellor desk `Send` action for due reminders now uses the same communication-hub confirmation.
- Full action-menu consolidation remains future UX polish, but the high-risk accidental-submit gap is now covered.

Verification:
- Focused Admission frontend readiness passed: 8 tests / 111 assertions.
- Adjacent Admission v0.031/v0.033/v0.036 regressions passed: 10 tests / 111 assertions.
- Full `php artisan test` passed 1462 tests / 11500 assertions.

### Finding UX-B007: Admission list pages vary in export/sort support

Severity: Medium  
Status: partially_fixed_verified

Admission Applicants and Leads pages are comparatively mature: they have table views, search/filter fields, pagination, export links, and sort signals. Other queue/report pages are less consistent.

Evidence:
- `/admission/applicants`: filters `search, program_id, status, per_page, date_from, date_to`, pagination, export, sort signal.
- `/admission/leads`: filters `search, status, source, program_id, per_page`, pagination, export, sort signal.
- `/admission/documents/queue`: filters present and pagination present, but no export/sort signal.
- `/admission/payments/queue`: filters present and pagination present, but no table/export/sort signal in the probe.
- `/admission/reminders`: filters and pagination present, but no export/sort signal.
- `/admission/reports`: has export label, but weak title/heading and no filter fields in the probe.

Recommended fix:
- Standardize queue pages with search/filter summary, export current view, and sortable table columns where the data can grow.
- Keep report pages clearly titled and source-backed.

Fix applied:
- `/admission/payments/queue` now shows a visible `Export Current View` action and a filtered pending-payment total in the table header.
- Added `admission.payments.queue.export`, placed before the dynamic `payments/{program}` route to avoid route collision.
- The export reuses the same scoped, filtered pending-payment query as the visible queue, streams CSV, and writes an `admission_payment_queue_export` audit log.
- Filter query parameters such as payment mode, program, installment, and date range are preserved in the export URL.
- `/admission/documents/queue` now has matching `Export Current View` support, filtered visible totals, and query-preserving CSV export.
- Added `admission.documents.queue.export`, placed before parameterized document routes and backed by the same scoped pending-document query as the visible queue.
- Document-name filtering now matches both configured required-document names and uploaded original filenames.
- Richer sortable column headers remain future bounded polish.

Verification:
- Payment export slice: focused Admission frontend readiness passed 9 tests / 122 assertions; adjacent Admission KPI/payment/department regressions passed 57 tests / 389 assertions; full `php artisan test` passed 1463 tests / 11511 assertions.
- Document export slice: focused Admission frontend readiness passed 10 tests / 133 assertions; adjacent Admission KPI/payment/department/flow regressions passed 77 tests / 504 assertions; full `php artisan test` passed 1464 tests / 11522 assertions.

### Finding UX-B008: Lower-role calling-desk POST actions were not scoped to the visible queue

Severity: High  
Status: fixed_verified

The calling desk GET queue was scoped for lower Admission roles, but the outcome and skip POST routes accepted a raw `subject_type`/`subject_id` and loaded the lead/applicant directly. Before the fix, a telecaller/counsellor could potentially submit a call outcome or skip event for a record outside their visible calling scope through a direct POST, even if that record never appeared in their queue.

Evidence:
- `CallingDeskController::outcome` and `skip` resolved `Lead::findOrFail` / `Applicant::findOrFail` before persisting call attempts or skip rows.
- `AdmissionCallAttemptService::record` wrote `admission_call_attempts`, `admission_call_logs`, callback reminders, and subject `last_activity_at` without an access check.
- `AdmissionCallQueueSelectorService::eligibleRecords` did scope the visible queue, so GET visibility and POST mutation did not use the same rule.

Fix applied:
- Added `AdmissionCallQueueSelectorService::canAccess()` and reused the same Admission hierarchy/assignment visibility rule for direct POST actions.
- `admission.calling-desk.outcome` and `admission.call-attempts.skip` now return 403 for out-of-scope records before writing attempts, call logs, reminders, skips, or subject activity.
- Calling-desk dashboard metrics now scope attempts, contact rate, callback due, parent due, and objections for lower-role users instead of showing global operational totals.
- Added focused regression coverage proving `telecaller@college.com` cannot mutate another assigned lead by direct POST, while a legitimate assigned lead outcome still saves.

Verification:
- Focused Admission frontend readiness passed: 7 tests / 104 assertions.
- Adjacent Admission v0.038/v0.039 regressions passed: 15 tests / 111 assertions.
- Full `php artisan test` passed 1461 tests / 11493 assertions.

### Finding UX-B009: Offer and seat-control writes were exposed to lower Admission roles

Severity: High  
Status: fixed_verified

The Offer And Seat Control page is a high-risk leadership workflow: offer rounds, waitlist movement, seat holds, deferrals, and joining-kit readiness directly affect admission decisions and seat inventory. The page and write routes were under the broad Admission role group, and the controller did not apply Admission hierarchy visibility or approval authority before showing rows or accepting direct POSTs.

Evidence:
- `OfferSeatControlController::index` loaded selected applicants, offer rounds, waitlist rows, seat holds, deferrals, and joining-kit tasks without applying `DepartmentHierarchyService` visibility.
- `createRound`, `publishRound`, `addWaitlist`, `releaseSeat`, `requestDeferral`, `approveDeferral`, and `ensureJoiningKit` accepted direct requests from any role admitted by the broad Admission route group.
- The Blade view showed create/publish/release/waitlist/deferral controls without explaining when a lower-role user was read-only.

Fix applied:
- Offer/seat control list data is now scoped for non-leadership users through Admission hierarchy applicant visibility.
- High-risk write actions now require `DepartmentHierarchyService::canApproveAdmission()`.
- Direct write routes return 403 for lower roles such as Admission Officer.
- Applicant-specific write actions also verify the target applicant is inside the actor's visible Admission scope.
- Lower-role users see an explicit read-only guidance message and disabled controls instead of actionable buttons that fail later.
- Leadership users keep full controls, with confirmation prompts for publish, waitlist, release, deferral, and offer-round creation actions.

Verification:
- Focused Admission frontend readiness passed: 11 tests / 146 assertions.
- Adjacent Admission offer/seat/waitlist regressions passed: 41 tests / 206 assertions.
- Full `php artisan test` passed 1465 tests / 11535 assertions.

### Finding UX-B010: Assessment scheduling writes were exposed to lower Admission roles

Severity: High  
Status: fixed_verified

Assessment Scheduling is an operational control surface for slots, candidate assignment, evaluator response/replacement, check-in lifecycle, GD grouping, and assessment submissions. The page was available to the broad Admission role group and presented mutation forms to lower roles. The controller accepted direct POSTs without checking Admission approval authority or applicant visibility for selected applicants.

Evidence:
- `AssessmentSchedulingController::index` loaded applicants globally for selectors instead of applying Admission hierarchy visibility.
- `storeSlot`, `assignSlot`, `bulkAssignSlot`, `evaluatorResponse`, `replaceEvaluator`, `reviewReschedule`, `checkIn`, `buildGd`, and `submission` had no leadership/approval guard.
- Candidate assignment and submission routes loaded applicants by raw ID before mutation.
- The Blade page showed create/assign/bulk/check-in/GD/submission controls without explaining when a user was read-only.

Fix applied:
- Assessment Scheduling applicant selectors now use Admission hierarchy applicant visibility for non-leadership users.
- Scheduling writes now require `DepartmentHierarchyService::canApproveAdmission()`.
- Applicant-specific writes verify the target applicant is visible to the actor before mutation.
- Lower-role users see read-only guidance and disabled controls.
- Leadership users keep controls with confirmation prompts for slot creation, candidate assignment, bulk assignment, evaluator changes, GD group building, submission updates, and check-in lifecycle updates.

Verification:
- Focused Admission frontend readiness passed: 12 tests / 157 assertions.
- Adjacent Admission v0.036/v0.037/v0.038/v0.039 regressions passed: 29 tests / 234 assertions.
- Full `php artisan test` passed 1466 tests / 11546 assertions.

### Finding UX-B011: Communication and automation controls were exposed to lower Admission roles

Severity: High  
Status: fixed_verified

Communication and automation are high-impact Admission workflows because they can send messages, create templates, dispatch queued communication, and mutate leads/applicants through rule actions. The pages were accessible to the broad Admission role group and showed active configuration forms to lower roles. The controllers accepted raw subject IDs for send/run actions without applying Admission hierarchy visibility.

Evidence:
- `CommunicationController::index` loaded recent communication logs globally.
- `storeTemplate` and `dispatch` had no leadership/approval guard.
- `send` loaded a raw lead/applicant ID and queued communication without checking the actor's Admission scope.
- `AutomationController::index` loaded recent automation executions globally.
- `store` and `run` had no leadership/approval guard, and `run` accepted a raw lead/applicant ID before executing rule actions.
- The Communication Hub and Automation pages showed active template/rule/dispatch controls to lower roles.

Fix applied:
- Communication and automation recent logs/executions are now scoped for non-leadership users.
- Template management, queued-message dispatch, automation rule creation, and manual automation runs now require `DepartmentHierarchyService::canApproveAdmission()`.
- Direct communication send and automation run actions verify the target lead/applicant is inside the actor's Admission scope before mutation.
- Lower-role users see read-only guidance and disabled controls.
- Leadership users keep controls, with confirmation prompts before template save, message dispatch, and automation save.

Verification:
- Focused Admission frontend readiness passed: 13 tests / 176 assertions.
- Adjacent Admission v0.003/v0.037/v0.039 regressions passed: 22 tests / 182 assertions.
- Full `php artisan test` passed 1467 tests / 11565 assertions.

### Finding UX-B004: Admission Officer can hit Handoff route but receives 403

Severity: Medium  
Status: fixed_verified

The shared Admission navigation previously rendered `Handoff` for Admission Officer even though the route correctly returns 403 under `AdmissionAccessPolicyService::can($user, 'read.handoff')`. The sidebar now uses the same policy-backed visibility condition, so officers no longer see a dead handoff link while Admission Head still sees and can open it.

Evidence:
- Authenticated as `officer@college.com`, rendered sidebar includes `Handoff` -> `/admission/handoff`.
- Requesting the visible Handoff link returned 403.
- After the fix, rendered navigation probe as `officer@college.com` showed 26 visible Admission links, no `Handoff`, and no failed links.
- Rendered navigation probe as `head@college.com` still showed `Handoff` -> `/admission/handoff` returning 200.
- `AdmissionFrontendBetaReadinessTest::test_admission_handoff_sidebar_link_matches_policy_visibility` covers Head visibility, Officer hidden-link behavior, and direct Officer 403.

Recommended fix:
- Keep Handoff hidden for roles that fail `read.handoff`.
- If officers later need handoff participation, add a scoped officer handoff queue and update the policy plus sidebar condition together.

### Finding UX-B005: Partner/channel user has no accessible partner-facing portal

Severity: High  
Status: fixed_verified

The seeded partner/channel user exists but has no Spatie role and no accessible partner-facing dashboard in the current route set. Staff-side partner routes exist under `/admission/partners`, but the partner user receives 403 there. Guessed partner dashboard/submission routes are 404.

Evidence:
- `partner.citychannel@demo.edu` exists with no role assigned.
- `artisan route:list` shows staff-side partner routes only: `admission.partners.index`, `admission.partners.store`, `admission.partners.approve`, `admission.partners.leads.store`.
- Authenticated as partner user:
  - `/admission/partners` returned 403.
  - `/admission/dashboard` returned 403.
  - `/applicant/dashboard` returned 403.
  - `/admission/partners/dashboard`, `/admission/partners/leads`, and `/admission/partners/submit-lead` returned 404.

Recommended fix:
- Either remove the partner user from advertised/demo user flows, or implement a real partner portal with dashboard, lead submission, status tracking, and scoped reports.
- Assign a partner/channel role and add frontend navigation/access tests for it.

Fix applied:
- Added `admission_partner` as the seeded partner role and redirect target.
- Added partner-facing `/admission/partners/dashboard` and `/admission/partners/leads` routes backed by the partner contact record, not placeholder data.
- Added a compact partner layout/sidebar using the frontend navigation manifest.
- Partner dashboard now shows database-backed lead totals, conversion, latest leads, and a scoped lead-submission form.
- Partner leads list now shows filtered source count, search/status filters, pagination, and only the current partner's leads.
- Direct access by an unrelated Admission Officer returns 403.

Verification:
- Rendered navigation probe as `partner.citychannel@demo.edu` opened Dashboard and Submitted Leads with status 200, semantic `h1`, and no debug/error text.
- Focused tests passed: `AdmissionFrontendBetaReadinessTest`, `RoleRedirectTest`, `FrontendReadinessTest` = 72 tests / 1189 assertions.
- Adjacent Admission regression passed: `AdmissionOsV003Test`, `AdmissionOsV039Test` = 13 tests / 99 assertions.
- Full `php artisan test` passed 1438 tests / 11161 assertions.

Batch B closure verification:
- Role-specific KPI/list consistency is now covered for Admission Manager, Counsellor, and Telecaller. The dashboard KPI source service is compared against the visible leads, applicants, document queue, and payment queue drilldowns for each seeded lower role.
- Lower-role rendered navigation is now inspected for Admission Manager, Counsellor, and Telecaller from their preferred workspace pages. Visible internal GET links are asserted not to return 403, 404, or 500.
- Safe action coverage now includes scoped calling-desk outcomes/skips, scoped reminder scheduling, scoped document verification, read-only/protected offer-seat controls, and handoff visibility/filter behavior.
- A real direct-route gap was closed: `admission.reminders.store` now authorizes the target lead/applicant through the same Admission hierarchy visibility used by dashboard/list queries before creating a reminder.

Verification:
- Focused Admission frontend readiness passed: 17 tests / 355 assertions.
- Admission KPI drilldown consistency passed: 2 tests / 47 assertions.
- Adjacent Admission regressions passed: `AdmissionOsV031Test`, `AdmissionOsV036Test`, `AdmissionOsV038Test`, `AdmissionOsV039Test` = 23 tests / 198 assertions.

## Batch C: Dean Academics

Status: in_progress

Checklist:
- Dean OS command page.
- Planning, reviews/actions, approval cockpit, risk, faculty workload, student success, curriculum, exam readiness, quality, induction/handoff, reports, calendar, policy audit.
- Cross-links into PMC/CoE/IQAC/Program/Course modules.
- Direct action creation/closure and export behavior.

Browser/page-health sample completed as `dean@college.com` for:
- legacy `/dean/dashboard`
- `academics.dean-os.index`
- `academics.dean-os.branch-health`
- `academics.dean-os.program-risk`
- `academics.dean-os.reviews`
- `academics.dean-os.handoff`
- `academics.dean-os.calendar`
- `academics.dean-os.reports`
- `academics.dean-os.planning`
- `academics.dean-os.approval-cockpit`
- `academics.dean-os.faculty-workload`
- `academics.dean-os.student-success`

### Finding UX-C001: Dean login lands on legacy dashboard instead of preferred Dean OS

Severity: High  
Status: fixed_verified

The Dean OS is documented and implemented as the preferred daily workspace, but logging in as `dean@college.com` lands on `/dean/dashboard`, the legacy Dean dashboard. The sidebar does include `Dean OS`, but the first screen after login is not the current operating system. This can cause Dean users to miss planning, approval cockpit, risk, handoff, workload, and student-success workflows that are now centralized under `/academics/dean-os`.

Evidence:
- Browser login as `dean@college.com` redirected to `http://localhost:8001/dean/dashboard`.
- Browser title after login: `Dean Academics — Dashboard`.
- `/academics/dean-os` loads correctly and has `h1=Dean Academics Command OS`.

Recommended fix:
- Update role default landing/redirect for `dean_academics` to `academics.dean-os.index`.
- Keep `/dean/dashboard` stable as a legacy-compatible page and add a prominent link/banner to Dean OS if not already sufficiently visible.
- Add/adjust a role landing test asserting Dean login resolves to the preferred Dean OS route.

Fix applied:
- `dean_academics` now redirects to `academics.dean-os.index` from `/` and `/dashboard`.
- The sidebar brand link now points to Dean OS.
- Rechecked locally as `dean@college.com`: `/` and `/dashboard` both resolved to `/academics/dean-os` with `h1=Dean Academics Command OS`.
- Focused tests: `RoleRedirectTest`, `DemoCredentialsTest`.

### Finding UX-C002: Dean OS sampled pages are page-health clean

Severity: Low  
Status: checked_no_blocker for sampled page-load behavior

The sampled Dean OS pages loaded without console errors, Laravel/Whoops traces, placeholder hash links, or missing main content. Unlike the sampled Admin pages, most Dean OS pages already expose meaningful semantic headings.

Evidence:
- 11 Dean OS pages sampled on `localhost:8001`.
- Sample headings included `Dean Academics Command OS`, `Dean Branch Health`, `Program Risk Heatmap`, `Dean Reviews And Actions`, `Admission To Academics Handoff`, `Dean Academic Calendar`, `Dean Reports`, `Dean Academic Planning Cycle OS`, `Unified Dean Approval Cockpit`, `Faculty Workload Governance`, and `Student Success Command`.

### Finding UX-C003: Director / Academic Department Owner can open major Academics oversight pages

Severity: Low  
Status: checked_no_blocker for sampled page-load behavior

`director@college.com` can open the major Academics oversight surfaces without debug traces or 403/500 errors. The legacy Director dashboard still lacks a semantic heading, consistent with other legacy pages.

Evidence:
- 200 responses for `/director/dashboard`, `/academics/dean-os`, `/academics/command-center`, `/academics/governance`, `/academics/pmc/command`, `/academics/coe`, `/academics/iqac`, `/academics/program-leadership`, and `/academics/course-delivery`.
- Newer Academics OS pages expose `h1` headings; `/director/dashboard` does not.

### Finding UX-C004: Dean rendered navigation includes Admin Library/Hostel links that return 403

Severity: High  
Status: fixed_verified

The Dean rendered sidebar includes `Library` and `Hostel` links pointing to Admin operation routes, but the Dean receives 403 on those links. Visible navigation should never send the role to a forbidden page.

Evidence:
- Authenticated as `dean@college.com`, rendered navigation from `/academics/dean-os` includes:
  - `Library` -> `/admin/library`, returned 403.
  - `Hostel` -> `/admin/hostel`, returned 403.
- Other Dean OS and Academics links in the same probe returned 200.

Recommended fix:
- Remove Admin operation links from Dean navigation unless Dean is intended to access those modules.
- If Dean should have oversight, add Dean-safe read-only Library/Hostel summary routes instead of linking to Admin pages.
- Add rendered-sidebar access tests for Dean.

Fix applied:
- Removed `admin.library.index` and `admin.hostel.index` from the Dean navigation manifest.
- Rechecked rendered Dean navigation locally: 35 links checked, all returned 200 without debug text.

### Finding UX-C005: Dean readiness blocker action creation was not idempotent

Severity: High  
Status: fixed_verified

The Dean planning page lets Dean users create action items directly from readiness blockers. Before this pass, repeated clicks or browser resubmits against the same readiness blocker could create duplicate Dean action items with the same `planning_readiness` source. That makes the action tracker noisy and can assign the same blocker multiple times.

Evidence:
- `AcademicDeanPlanningService::createActionFromBlocker()` previously called `AcademicDeanActionItem::create()` directly for every request.
- The UI exposed a one-click `Create Action` form for each blocker.

Fix applied:
- Readiness-blocker action creation now reuses the existing `planning_readiness` action for the same readiness item instead of creating duplicates.
- Dean planning create, planning approve, and blocker action buttons now require browser confirmation.
- Added `AcademicsDeanV008Test::test_readiness_blocker_action_creation_is_idempotent`.
- Verification: focused `AcademicsDeanV008Test` passed 10 tests / 69 assertions; adjacent Dean frontend/KPI/v0.07 tests passed 13 tests / 157 assertions; full `php artisan test` passed 1468 tests / 11569 assertions.

### Finding UX-C006: Dean action items could be closed without evidence or closure note

Severity: High  
Status: fixed_verified

Dean action tracking is meant to support governance and closure verification, but the review/action update route previously allowed a Dean action to be marked `done` with a blank closure note and no evidence record. That makes later review weak because the action appears closed without any explanation, proof, or exception trail.

Evidence:
- `AcademicDeanReviewService::updateAction()` previously set `closed_at` whenever status was `done`, without checking evidence or a meaningful closure note.
- The Dean review table allowed status updates with an optional closure-note field.

Fix applied:
- `AcademicDeanActionItem` now exposes an `evidence()` relation.
- `AcademicDeanReviewService::updateAction()` now rejects `done` transitions unless the action already has evidence or the submitted closure note is nonblank.
- Dean review/action forms now include browser confirmations, and the closure-note placeholder explains that it is required when closing without evidence.
- Added `AcademicsDeanV007Test::test_dean_action_cannot_be_closed_without_evidence_or_closure_note`.
- Verification: focused `AcademicsDeanV007Test` passed 7 tests / 42 assertions; adjacent Dean frontend/KPI/v0.08 tests passed 17 tests / 189 assertions; full `php artisan test` passed 1469 tests / 11574 assertions.

### Finding UX-C007: Dean risk snapshot capture could duplicate same-day snapshots

Severity: Medium  
Status: fixed_verified

The Dean risk page has a `Capture Snapshot` action for trend/history reporting. Before this pass, repeated captures on the same day created another snapshot row for the same program/date, making risk history noisier and potentially misleading trend charts and review packs.

Evidence:
- `AcademicDeanRiskSnapshotService::capture()` previously created a new snapshot row for every program on every request.
- Focused regression initially reproduced duplicate same-day rows before the service was hardened.

Fix applied:
- Snapshot capture now updates the existing snapshot for the same program and date using a `whereDate` lookup, otherwise creates one row.
- Risk snapshot and mitigation buttons now require browser confirmation.
- Added `AcademicsDeanV008Test::test_risk_snapshot_capture_is_idempotent_for_program_and_date`.
- Verification: focused `AcademicsDeanV008Test` passed 11 tests / 72 assertions; adjacent Dean frontend/KPI/v0.07 tests passed 14 tests / 162 assertions; full `php artisan test` passed 1470 tests / 11577 assertions.

### Finding UX-C008: Dean saved views existed in backend but were not usable on operating surfaces

Severity: Medium  
Status: fixed_verified

Dean saved views had a table, service, route, and report-page badge display, but the high-frequency Dean operating-list pages did not expose a practical create/apply workflow. This meant a Dean could not save the current faculty workload, student success, curriculum, exam-readiness, quality, or induction filters directly from the page being used.

Evidence:
- `AcademicDeanSavedViewService` and `academics.dean-os.saved-views.store` existed.
- `resources/views/academics/dean-os/reports.blade.php` only displayed saved views as badges.
- `resources/views/academics/dean-os/v008/operating-surface.blade.php` had filters/export but no saved-view create/apply controls.

Fix applied:
- Dean operating surfaces now receive saved views for the current surface.
- Each operating surface shows existing saved views as filter-apply buttons.
- Each operating surface includes a compact `Save current filters` form with default-view support and confirmation.
- Focused coverage proves a Dean can save a filtered faculty-workload view and then reopen the surface with an apply link for the saved filters.
- Verification: focused `AcademicsDeanFrontendBetaReadinessTest` passed 5 tests / 110 assertions; adjacent Dean v0.07/v0.08/KPI tests passed 20 tests / 130 assertions; full `php artisan test` passed 1470 tests / 11583 assertions.

### Finding UX-C009: Dean approval cockpit did not expose request-evidence/escalation decisions and showed active forms on finalized rows

Severity: High  
Status: fixed_verified

The Dean approval cockpit is the central page for academic approval decisions. Before this pass, pending rows exposed approve/return/reject only, so the implemented `requested_evidence` and `escalated` states were not discoverable in the UI. Finalized rows still showed active decision forms even though the backend rejected rewrites, which made completed approvals look editable and sent users into avoidable 422 errors.

Evidence:
- `AcademicDeanApprovalCockpitService::decide()` supported `requested_evidence` and `escalated`.
- `resources/views/academics/dean-os/v008/approvals.blade.php` showed only Approve, Return, and Reject.
- The same view rendered the decision form for all rows, including finalized statuses.

Fix applied:
- The approval cockpit now shows `Request evidence` and `Escalate` decisions for non-final rows.
- Evidence requests now require a nonblank decision reason, matching the real workflow need to state what evidence is required.
- Finalized approval rows now display a locked final-decision message and reason instead of another decision form.
- Decision buttons now require browser confirmation.
- Verification: focused Dean v0.08/frontend tests passed 18 tests / 191 assertions; adjacent Dean v0.07/KPI tests passed 9 tests / 58 assertions; full `php artisan test` passed 1472 tests / 11592 assertions.

### Finding UX-C010: Dean operating exports ignored the visible filtered list

Severity: High  
Status: fixed_verified

Dean operating pages such as Faculty Workload and Student Success expose filtered lists and an `Export Current View` button. Before this pass, the export URL preserved the filters, but `AcademicDeanExportService` ignored those filters for operating-record exports and logged/exported the unfiltered latest rows. That could produce CSVs that did not match what the Dean was reviewing on screen.

Evidence:
- `resources/views/academics/dean-os/v008/operating-surface.blade.php` passed query filters into the export route.
- `AcademicDeanExportService::operatingRows()` previously selected by `record_type`, then `latest()->limit(500)`, without applying search/status/severity/program/owner/sort filters.

Fix applied:
- Dean operating exports now apply the same search, status, severity, program, owner, sort, and direction filters used by the visible operating table.
- Export audit `row_count` now reflects the filtered export rows.
- Focused coverage verifies a filtered faculty-workload export includes only the matching critical open row, excludes the closed nonmatching row, and logs the active filters with row count `1`.
- Verification: focused `AcademicsDeanFrontendBetaReadinessTest` passed 6 tests / 124 assertions; adjacent Dean v0.07/v0.08/KPI tests passed 21 tests / 134 assertions; full `php artisan test` passed 1472 tests / 11601 assertions.

### Finding UX-C011: Dean KPI/mobile sidebar rendered checks are verified by route tests; browser localhost was unavailable

Severity: Low  
Status: checked_no_blocker with test-backed evidence

Attempted rendered browser verification for Dean OS KPI clicks and mobile/sidebar behavior, but the in-app Browser rejected direct `localhost:8001` navigation under its URL policy. I did not bypass that policy with another browser surface. Instead, this slice added focused Laravel-rendered page evidence for the same contracts.

Evidence:
- Dean dashboard includes concrete links for:
  - Overdue Approvals -> `academics.dean-os.attention(overdue_dean_approvals)`.
  - Open Actions -> `academics.dean-os.reviews?status=open`.
  - Critical Program Risks -> `academics.dean-os.program-risk?band=critical_high`.
  - Handoff Blockers -> `academics.dean-os.handoff?status=blocking`.
- Critical Attention is visibly `Summary only`, not a fake drilldown.
- The Dean shared shell includes the mobile offcanvas sidebar, hamburger target, accessible open-menu label, and scrollable desktop/mobile sidebar CSS.
- Verification: focused `AcademicsDeanFrontendBetaReadinessTest` passed 7 tests / 140 assertions; adjacent Dean/production readiness tests passed 28 tests / 181 assertions; full `php artisan test` passed 1473 tests / 11617 assertions.

Remaining Batch C work:
- Continue any remaining Dean risk-setting edge checks if needed.
- Browser-click/mobile visual checks remain desirable if local browser access is later available.

## Batch D: PMC / Program Management

Status: fixed_verified

Checklist:
- PMC command/workbench.
- Academic planning/readiness.
- Curriculum governance.
- Course allocation, student baskets, sections/groups.
- Faculty allocation/load.
- Timetable generator/planner/conflicts/quality/freeze/rollback.
- Student success and mentor governance.
- Reviews, decisions, actions, approvals, automation, reports.

Browser/page-health sample completed as `chair@college.com` for:
- legacy `/program-chair/dashboard`
- `academics.pmc.command`
- `academics.pmc.workbench`
- `academics.pmc.planning`
- `academics.pmc.curriculum-governance`
- `academics.pmc.faculty-allocation`
- `academics.pmc.timetable-governance`
- `academics.pmc.course-allocation`
- `academics.pmc.course-groups`
- `academics.pmc.timetable-generator`
- `academics.pmc.timetable-planner`
- `academics.pmc.student-success`
- `academics.pmc.approvals`
- `academics.pmc.analytics`

### Finding UX-D001: PMC login lands on legacy Program Chair dashboard instead of PMC Command OS

Severity: High  
Status: fixed_verified

Logging in as `chair@college.com` lands on `/program-chair/dashboard`, while the current daily PMC workspace is `/academics/pmc/command`. This repeats the Dean pattern: legacy dashboards remain stable, but users are not taken directly to the newer operating-system surface where planning, course allocation, timetable governance, approvals, analytics, and attention queues are centralized.

Evidence:
- Browser login as `chair@college.com` redirected to `http://localhost:8001/program-chair/dashboard`.
- Browser title after login: `PMC Dashboard`.
- `/academics/pmc/command` loads correctly with `h1=PMC Command OS`.

Recommended fix:
- Update role default landing/redirect for `program_chair` / PMC Head to `academics.pmc.command`.
- Keep `/program-chair/dashboard` as a legacy-compatible route with a prominent handoff to PMC Command OS.
- Add/adjust a role landing test asserting PMC Head login resolves to the preferred PMC command route.

Fix applied:
- `program_chair` now redirects to `academics.pmc.command` from `/` and `/dashboard`.
- The sidebar brand link now points to PMC Command.
- Rechecked locally as `chair@college.com`: `/` and `/dashboard` both resolved to `/academics/pmc/command` with `h1=PMC Command OS`.
- Focused tests: `RoleRedirectTest`, `DemoCredentialsTest`.

### Finding UX-D002: PMC OS sampled pages are page-health clean

Severity: Low  
Status: checked_no_blocker for sampled page-load behavior

The sampled PMC OS pages loaded without console errors, Laravel/Whoops traces, placeholder hash links, missing main content, or empty primary content. The newer PMC pages consistently expose meaningful `h1` headings and tables/forms/cards suitable for operational work.

Evidence:
- 13 PMC OS pages sampled on `localhost:8001`.
- Sample headings included `PMC Command OS`, `PMC Workbench`, `PMC Academic Planning Cycle`, `PMC Curriculum Governance`, `Faculty Allocation`, `PMC Timetable Governance`, `PMC Course Allocation`, `PMC Section And Group Builder`, `PMC Constraint-Based Timetable Generator`, `PMC Timetable Planning Board`, `PMC Student Success Command`, `PMC Approval Cockpit`, and `PMC Analytics And Reports`.

### Finding UX-D003: Scoped PMC and Program Leadership users can open core OS pages

Severity: Low  
Status: checked_no_blocker for sampled page-load behavior

Authenticated probes for PMC Manager, PMC Officer, Semester Coordinator, Course Coordinator, and Faculty Mentor returned 200 for the sampled current OS pages. These pages generally expose semantic headings and contain database-backed cards/tables/forms.

Evidence:
- `pmc.manager@college.com`: PMC command, workbench, planning, course allocation, course groups, timetable generator, student success, and Program Leadership returned 200.
- `pmc.officer@college.com`: PMC command, workbench, planning, course allocation, timetable planner, and approvals returned 200.
- `semester.coordinator@college.com`: Program Leadership, portfolio, student success, course delivery, and reports returned 200.
- `course.coordinator@college.com`: Program Leadership and Course Delivery OS returned 200.
- `faculty.mentor@college.com`: Program Leadership, PMC Student Success, and Course Delivery OS returned 200.

### Finding UX-D004: Course Delivery actual manifest routes are healthy; guessed older route names are absent

Severity: Low  
Status: checked_no_blocker

The current Course Delivery manifest uses `/academics/course-delivery/session-delivery`, `/attendance-interventions`, `/mentor-actions`, `/course-engagement`, and `/reports`. Those routes return 200 for `course.coordinator@college.com`. Older guessed URLs such as `/academics/course-delivery/progress` and `/session-logs` returned 404, but they are not visible manifest routes and should not be treated as user-facing breakages.

Evidence:
- `course.coordinator@college.com` received 200 for all six current Course Delivery manifest routes.

### Finding UX-D005: PMC Head rendered navigation is clean in sampled pass

Severity: Low  
Status: checked_no_blocker for rendered sidebar links

Rendered navigation from `/academics/pmc/command` as `chair@college.com` had 33 links and no 403/404/500 failures in the sampled pass.

Evidence:
- Checked links included PMC OS, planning, curriculum governance, course allocation, section builder, timetable planner, student success, faculty allocation, approval cockpit, analytics, policy audit, Program Leadership, legacy Program Chair links, and Department Governance.

### Finding UX-D006: PMC list-heavy pages lack search/export/sort consistency

Severity: Medium  
Status: needs_deeper_workflow_test

PMC OS pages load and show data, but several high-volume workflow pages have filters/pagination without search, export, or sort affordances. This matters for real PMC work because course allocation, groups, timetable planning, approvals, and analytics can grow quickly.

Evidence:
- `/academics/pmc/course-allocation`, `/course-groups`, and `/timetable-planner` have filter fields and pagination, but no search/export/sort signal in the probe.
- `/academics/pmc/approvals` and `/academics/pmc/analytics` show pagination but no search/export/sort signal.
- `/program-chair/students/at-risk` had filters and pagination, but no export/sort signal.

Recommended fix:
- Add search, export current view, and sortable columns to PMC allocation/group/timetable/approval/student-risk tables.
- Show visible filter summaries on each page.

Fix applied:
- `/academics/pmc/approvals` now exposes search, status, overdue, sort, order, reset, and `Export` controls.
- `/academics/pmc/analytics` now exposes search, band, sort, order, reset, and `Export` controls.
- `AcademicPmcV004Service` now exports `approvals` from `AcademicPmcApproval` and `analytics` from `AcademicPmcAnalyticsSnapshot` instead of falling back to generic PMC operating records.
- Export logs now record the filtered approval/analytics row count for those current-view exports.
- `/academics/pmc/course-allocation`, `/course-groups`, and `/timetable-planner` now expose search, sort/order, and `Export Current View` controls on the shared v0.041 surface filter bar.
- `AcademicPmcTimetableV041Service` now exports filtered current-view CSV rows for course allocation, section/group builder, and timetable planner surfaces, with scoped source queries and export log row counts.
- The v0.041 status filter is hardened so student course allocations use `basket_status`/`approval_status` instead of assuming a nonexistent `status` column.
- Legacy `/program-chair/students/at-risk` now uses a shared filtered source for page totals and CSV export, applies program/batch/risk/search filters, exposes visible filter summary, sortable columns, pagination totals, and `Export Current View`.
- Rendered scoped-link checks now cover PMC Manager, PMC Officer, HOD/Program Leadership, Course Coordinator, and Faculty Mentor. Visible links that previously led to forbidden legacy Program Chair/Teacher routes were hidden or retargeted to scoped PMC/Program/Course Delivery OS pages.
- Safe v0.041 workflow coverage remains in `AcademicsPmcTimetableV041Test`: course allocation -> group creation -> section/group faculty assignment -> generator -> quality score/change request/substitution/notification, plus blocked invalid change-request status and access-policy checks.
- Focused tests: `AcademicsPmcFrontendBetaReadinessTest`.
- Verification: focused PMC frontend test passed `8 tests / 340 assertions`; adjacent PMC regressions passed `AcademicsPmcTimetableV041Test` (`4 tests / 150 assertions`), `AcademicsPmcV004Test` (`4 tests / 50 assertions`), and `AcademicsPmcKpiDrilldownConsistencyTest` (`5 tests / 31 assertions`).

Remaining Batch D work:
- No known critical/high Batch D blocker remains from the current fast UX closure checklist.
- Browser-click/mobile visual checks remain desirable if local browser tooling is later used, but the current PHPUnit evidence covers data-backed legacy at-risk controls, safe v0.041 workflow checks, scoped navigation reachability, exports, and KPI drilldown consistency.

## Batch E: CoE / Exam And IQAC

Status: fixed_verified

Checklist:
- CoE dashboard, readiness, exam setup, marks/results, hall tickets, transcripts, appeals/anomalies, reports.
- IQAC dashboard, OBE readiness, CO/PO/PSO, attainment, feedback closure, audit evidence, corrective action plans, reports.

Authenticated route/page-health sample completed for:
- CoE Head `exam@college.com`: 13 manifest routes.
- IQAC Head `iqac.head@college.com`: 8 manifest routes.

### Finding UX-E001: CoE OS routes load, but legacy Exam Cell pages lack semantic headings

Severity: Medium  
Status: fixed_verified

The newer CoE OS pages are healthy and expose semantic headings, but several legacy Exam Cell routes visible in the CoE navigation load without `h1`/`h2` headings. This creates the same orientation/accessibility inconsistency seen in Admin legacy pages.

Evidence:
- `exam@college.com` authenticated route probes returned 200 for visible CoE/Exam routes.
- Newer routes with headings: `/academics/coe`, `/academics/workspaces/coe`, `/academics/coe/exam-readiness`, `/academics/coe/marks-results`, `/academics/coe/hall-ticket-readiness`, `/academics/coe/transcripts`, `/academics/coe/reports`.
- Legacy routes without headings: `/exam-cell/dashboard`, `/exam-cell/exams`, `/exam-cell/results`, `/exam-cell/hall-tickets`, `/exam-cell/marks-appeals`, `/exam-cell/anomalies`.

Recommended fix:
- Add the shared page-header component to legacy Exam Cell pages.
- Consider redirecting or cross-linking legacy Exam Cell daily entry points toward the newer CoE OS where appropriate.

Fix applied:
- Added semantic `h1` headings to legacy Exam Cell dashboard, exams, results, hall tickets, marks appeals, grade sheet, and exam anomaly pages while preserving the legacy routes.
- Rechecked the rendered `/exam-cell/dashboard` HTML directly and confirmed it contains `<h1 class="h4 mb-1">Exam Cell Dashboard</h1>`.
- Focused tests: `AcademicsCoeFrontendBetaReadinessTest` and `ExamCellDashboardGuidanceTest`.

### Finding UX-E002: IQAC navigation exposes OBE Framework link that returns 403 for IQAC Head

Severity: High  
Status: fixed_verified

The IQAC manifest includes `OBE Framework` pointing to `/academic/obe/course-outcomes`, but authenticated access as `iqac.head@college.com` returns 403. A visible sidebar/workflow link should either be accessible to that role or hidden/replaced with an IQAC-scoped OBE route.

Evidence:
- `iqac.head@college.com` authenticated probe:
  - `/academics/iqac` returned 200.
  - `/academics/workspaces/iqac` returned 200.
  - `/academics/iqac/obe-readiness` returned 200.
  - `/academic/obe/course-outcomes` returned 403.
- The route is listed in `App\Support\FrontendNavigation::manifest()` under role `iqac`.
- Rendered IQAC sidebar includes `OBE Framework` -> `/academic/obe/course-outcomes`, which returned 403.

Recommended fix:
- Either grant IQAC Head read access to `academic.obe.co.index` or change the visible IQAC navigation to an IQAC-safe OBE framework page.
- Add a frontend navigation access test asserting every visible IQAC route opens for the seeded IQAC Head user.

Fix applied:
- IQAC navigation and dashboard OBE Framework links now point to `academics.iqac.obe-readiness`.
- IQAC source/action rows now point to IQAC operating pages instead of inaccessible legacy OBE/PMC routes.
- Rechecked rendered IQAC Head navigation locally: 9 links checked, all returned 200 without debug text.
- Focused tests: `AcademicsIqacFrontendBetaReadinessTest`.

### Finding UX-E003: IQAC Manager and Officer also receive 403 on visible OBE Framework route

Severity: High  
Status: fixed_verified

The same IQAC OBE Framework access mismatch appears for scoped IQAC Manager and Officer accounts.

Evidence:
- `iqac.manager@college.com` received 200 for IQAC OS, workspace, OBE readiness, attainment, feedback quality, audit compliance, and reports.
- `iqac.manager@college.com` received 403 for `/academic/obe/course-outcomes`.
- `iqac.officer@college.com` received 200 for the same IQAC OS routes and 403 for `/academic/obe/course-outcomes`.
- The IQAC layout condition includes IQAC manager/officer roles and renders the same IQAC manifest.

Recommended fix:
- Apply the same fix as UX-E002 to all IQAC roles, not just IQAC Head.

Fix applied:
- IQAC Manager and IQAC Officer now see `OBE Framework` linked to `/academics/iqac/obe-readiness`.
- Rechecked rendered IQAC Manager navigation: 9 links checked, all returned 200 without debug text.
- Rechecked rendered IQAC Officer navigation: 8 links checked, all returned 200 without debug text.

### Finding UX-E004: Exam Manager/Officer can open CoE OS pages but receive 403 on legacy Exam Cell routes

Severity: Medium  
Status: fixed_verified

Exam Manager and Exam Officer can access the newer CoE OS operating pages, but direct access to legacy `/exam-cell/exams` and `/exam-cell/results` returned 403. This may be correct if those legacy routes are reserved for the old `exam_cell` role, but it must be checked against actual visible sidebar/navigation for Exam Manager/Officer. If visible, it is a route-navigation mismatch.

Evidence:
- `exam.manager@college.com`: CoE OS, workspace, exam readiness, marks/results, hall-ticket readiness, and reports returned 200; `/exam-cell/exams` and `/exam-cell/results` returned 403.
- `exam.officer@college.com`: same pattern.

Recommended fix:
- Verify actual sidebar visibility for Exam Manager/Officer.
- If legacy Exam Cell links are visible, hide them or grant correct scoped access.
- Prefer newer CoE OS routes as the daily workflow and keep legacy routes role-compatible only where intentional.

Fix applied:
- Scoped Exam Manager/Officer sidebars now expose the newer CoE OS routes instead of legacy `/exam-cell/*` daily links.
- Legacy Exam Cell routes remain available for the legacy `exam_cell` role and existing compatibility tests.
- Rechecked rendered Exam Manager navigation from `/academics/coe`: 9 links checked, all returned 200 without debug text.
- Focused tests: `AcademicsCoeFrontendBetaReadinessTest` and `RoleRedirectTest`.

### Finding UX-E005: Some scoped Academics roles have brand links pointing to Admin dashboard

Severity: High  
Status: fixed_verified

Rendered navigation for IQAC Head and Exam Manager includes an `EduManage Portal` brand/home link pointing to `/admin/dashboard`, which returns 403 for those roles. This is a clear navigation bug: the brand/home link should point to that user's role landing page, not Admin.

Evidence:
- `iqac.head@college.com`: rendered link `EduManage Portal` -> `/admin/dashboard`; request returned 403.
- `exam.manager@college.com`: rendered link `EduManage Portal` -> `/admin/dashboard`; request returned 403.

Recommended fix:
- Update the shared admin/role layout brand route fallback for scoped Academics roles.
- Add rendered-sidebar tests that every visible brand/home link opens for the current role.

Fix applied:
- Shared layout and role landing map now route Dean, PMC, IQAC, Exam Manager/Officer, Program Leadership, and Course Delivery scoped roles to their operating-system landing pages instead of `/admin/dashboard`.
- Rechecked rendered IQAC Manager/Officer and Exam Manager navigation; brand/home links returned 200 without debug text.
- Focused tests: `RoleRedirectTest`, `DemoCredentialsTest`, `AcademicsIqacFrontendBetaReadinessTest`, and `AcademicsCoeFrontendBetaReadinessTest`.

### Finding UX-E006: CoE/IQAC current OS list pages are stronger than legacy Exam Cell pages

Severity: Low  
Status: checked_no_blocker for sampled current OS list controls

Current CoE and IQAC OS pages generally include search/status filters, pagination, and `Export current view` links. Legacy Exam Cell pages are less consistent.

Evidence:
- CoE current OS pages `/academics/coe/exam-readiness`, `/marks-results`, `/hall-ticket-readiness`, and `/reports` expose `search,status`, pagination, and export.
- IQAC pages `/obe-readiness`, `/attainment-monitoring`, `/feedback-quality`, `/audit-compliance`, and `/reports` expose `search,status`, pagination, and export.
- Legacy `/exam-cell/exams` has `program_id,status` filters and pagination but no export/sort signal.
- Legacy `/exam-cell/results` has pagination but no filter/export/sort signal in the probe.

Recommended fix:
- Prefer linking users to the newer CoE OS pages for daily work.
- Add list-control parity only to legacy pages that remain user-facing.

Remaining Batch E work:
- No known critical/high Batch E blocker remains from the current fast UX closure checklist.
- CoE and IQAC current OS filtered source lists now have verified CSV `Export current view` behavior from the same filtered section data.
- Rendered scoped navigation checks now cover Exam Manager, Exam Officer, IQAC Manager, and IQAC Officer with no visible 403/404/500 links in the focused tests.
- Verification: `AcademicsCoeFrontendBetaReadinessTest` passed `5 tests / 153 assertions`; `AcademicsIqacFrontendBetaReadinessTest` passed `5 tests / 152 assertions`; adjacent `AcademicsCoeV003Test`, `AcademicsIqacV004Test`, and `AcademicOperatingKpiDrilldownConsistencyTest` passed `17 tests / 130 assertions`; full `php artisan test` passed `1479 tests / 12033 assertions`.

## Batch F: Portals

Status: fixed_verified for sampled portal role/navigation/action-entry/ownership/mobile checks

Checklist:
- Student: dashboard, attendance, timetable, fees, assignments, courses, results, documents/NOC, feedback, discussions, placement, mobile usability.
- Teacher: dashboard, timetable, attendance, assignments, materials, marks, students, mentor, feedback, leave.
- Parent: dashboard, child attendance/results/fees, notices, linked-child restrictions.
- Applicant: dashboard, checklist, application, documents, fees, status, assessment/offer/seat visibility where present.

Authenticated route/page-health sample completed for:
- Teacher `anjali@demo.edu`: 12 manifest routes.
- Student `arjun.k@demo.edu`: 26 high-traffic manifest routes.
- Parent `parent@demo.edu`: 3 manifest routes.
- Applicant `priya.sharma@applicant.demo`: 6 manifest routes.

Batch 5 closure verification:
- Visible dashboard/sidebar links were checked for seeded Teacher, Student, Parent, and Applicant users; sampled internal links returned no 403/404/500.
- Safe action entry pages were checked for Teacher attendance/material/assignment/roster, Student fee payment/document/grievance/leave, Applicant checklist/documents/fees/status, and Parent child attendance/results/fees.
- Student personal pages now have verified operational list or empty-state signals for fees, payment submissions, document requests, feedback, and grievances.
- Applicant mobile shell now has the same mobile toggle/accessibility contract as the other portals.
- Focused tests passed: `PortalFrontendBetaReadinessTest` = 12 tests / 650 assertions; adjacent ownership/scope/action-entry tests = 113 tests / 730 assertions; `npm run frontend:smoke:mobile` = 29 tests / 1380 assertions.

### Finding UX-F001: Teacher sidebar exposed routes that returned 403 for the seeded teacher

Severity: High  
Status: fixed_verified

Several routes visible in the Teacher manifest are not accessible to the seeded teacher user. Visible navigation should not take a user to 403 unless the link is intentionally disabled with an explanation.

Evidence:
- Authenticated as `anjali@demo.edu`, rendered sidebar includes:
  - `My Timetable` -> `/teacher/timetable`, returned 403.
  - `My Mentees` -> `/teacher/mentor`, returned 403.
  - `My Feedback` -> `/teacher/feedback`, returned 403.

Recommended fix:
- Align teacher navigation visibility with teacher permissions/assignments.
- If a teacher can have no timetable/mentor/feedback scope, show an operational empty state instead of a 403.
- Add a seeded Teacher navigation access test covering every visible teacher route.

Fix applied:
- Teacher timetable, mentor, and feedback GET pages now render operational empty states when the user has the Teacher role but no linked Teacher profile.
- Rechecked `anjali@demo.edu` rendered Teacher sidebar after the fix: all 12 visible links returned 200 and no debug text.
- Focused tests: `TeacherProfileMissingGracefulTest`, `PortalFrontendBetaReadinessTest`.

### Finding UX-F002: Teacher Leave and Profile pages returned 500 for the seeded teacher

Severity: Critical  
Status: fixed_verified

The Teacher portal has two visible routes that produce 500 errors for `anjali@demo.edu`.

Evidence:
- `/teacher/leaves` returned 500.
- `/teacher/profile` returned 500.
- Laravel log:
  - `Teacher/LeaveController.php:20`: `Call to a member function leaveApplications() on null`.
  - `Teacher/ProfileController.php:13`: `Call to a member function load() on null`.

Likely cause:
- The seeded teacher user does not have the expected linked teacher/faculty profile record, or the controllers do not handle a missing profile defensively.

Recommended fix:
- Ensure demo seeder creates the linked teacher profile for every visible Teacher portal demo user.
- Add defensive empty-state handling when the profile relation is missing.
- Add tests for teacher leave/profile routes using `anjali@demo.edu`.

Fix applied:
- Teacher Leave and Profile GET pages now render a clear "Teacher profile not linked" operational state instead of crashing.
- Leave submission remains blocked unless an active Teacher profile exists.
- `DemoDataSeeder` now uses unique demo teacher employee IDs and links demo teachers by `user_id` to avoid collisions with base `TCH001` records.
- Rechecked `/teacher/leaves` and `/teacher/profile` locally as `anjali@demo.edu`; both returned 200 without debug text.
- Focused tests: `TeacherProfileMissingGracefulTest`, `PortalFrontendBetaReadinessTest`.

### Finding UX-F003: Student Academic Summary returned 500

Severity: Critical  
Status: fixed_verified

The Student portal has a visible route that returns 500 for the seeded student.

Evidence:
- Authenticated as `arjun.k@demo.edu`.
- `/student/academic-summary` returned 500.
- Rendered Student sidebar includes `Academic Summary` -> `/student/academic-summary`, which returned 500.
- Laravel log: `Student/AcademicSummaryController.php:12`: `Call to undefined relationship [user] on model [App\Models\User]`.

Recommended fix:
- Correct `AcademicSummaryController` to use the actual student/profile relationship instead of loading `user` on `User`.
- Add a route smoke/feature test for `/student/academic-summary` as the seeded student.

Fix applied:
- `AcademicSummaryController` now eager-loads `mentor` instead of invalid `mentor.user`; `mentor` already resolves to `App\Models\User`.
- Rechecked `/student/academic-summary` locally as `arjun.k@demo.edu`; it returned 200 without debug text.
- Focused tests: `StudentAcademicSummaryWorkflowTest`, `PortalFrontendBetaReadinessTest`.

### Finding UX-F004: Portal pages generally load but lack semantic page headings

Severity: Medium  
Status: fixed_verified for shared-shell heading coverage

Most sampled Teacher, Student, Parent, and Applicant portal routes return 200 and render database-backed content, but many do not expose an `h1`/`h2` heading. For end users, the portals should be especially clear and mobile-friendly.

Evidence:
- Teacher pages such as dashboard, exams, materials, assignments, announcements, and students returned 200 but no `h1`/`h2`.
- Student pages such as dashboard, attendance, results, admit cards, subjects, calendar, leave, exam registration, appeals, courses, assignments, fees, notices, library, grievances, mentor, feedback, documents, transport, promotion, and profile returned 200 but mostly no `h1`.
- Parent dashboard/children/notices returned 200 but no `h1`/`h2`.
- Applicant dashboard/application/documents/fees/status returned 200 but no heading; applicant checklist has `h1=Admission Checklist`.

Recommended fix:
- Add shared portal page headers with concise task-oriented titles and next-action subtitles.
- Add page-health tests requiring a semantic heading for portal pages.

Fix applied:
- Student, Teacher, Parent, and Applicant layouts now render their topbar page title as a compact semantic `h1`.
- Mobile/layout frontend smoke passed after the change.

### Finding UX-F005: Student action pages often do not expose an obvious action form on the landing page

Severity: Medium  
Status: partial_fixed_verified

Several Student portal pages are named like operational workflows but the rendered landing page exposes no form beyond logout. The action may exist behind a link/modal/create route, but from the first page it is not obvious in the form inventory. This needs interactive checking for entry-point clarity.

Evidence:
- `arjun.k@demo.edu`:
  - `/student/subjects` exposes repeated `Add` forms for subject registration.
  - `/student/leave`, `/student/fee-payment`, `/student/grievances`, `/student/feedback`, and `/student/documents` rendered only the logout form in the form inventory.

Recommended fix:
- Ensure each action page has an obvious primary action: `Apply Leave`, `Submit Payment Proof`, `Raise Grievance`, `Give Feedback`, `Request Document`.
- If action windows are closed, show the closed reason and next available date instead of a passive list.

Fix applied:
- Student Fee Payment create now opens a real explanatory create page instead of silently returning to the index when no eligible payment proof can be submitted.
- Student Course Feedback create now opens the subject feedback page with an explicit locked-state reason when the student is inactive or has already submitted feedback.
- Student Leave and Document Request create pages now also render locked explanatory forms when an inactive student opens them directly.
- Fee payment and feedback forms remain visible for orientation, but inputs and submit buttons are disabled while the reason is shown.

Verification:
- Focused Student portal tests passed: `FeePaymentTest`, `StudentCourseFeedbackWorkflowTest`, `PortalFrontendBetaReadinessTest` = 59 tests / 826 assertions.
- Focused safe-action tests passed: `StudentLeaveWorkflowTest`, `StudentDocumentRequestGuidanceTest`, `StudentCourseContentAccessTest`, `PortalFrontendBetaReadinessTest` = 52 tests / 726 assertions.
- Rendered probes as `arjun.k@demo.edu` opened `/student/dashboard`, `/student/fee-payment/create`, and `/student/feedback/1` with no failed sidebar links and no debug/error text.
- Full `php artisan test` passed 1439 tests / 11178 assertions.

### Finding UX-F007: Student create routes are mixed: some work, some silently redirect to index

Severity: Medium  
Status: fixed_verified

Direct route checks show that some Student action workflows do have create pages, but others redirect back to the index without an obvious reason in the HTTP-level probe. This may be valid when there is no payable demand or no feedback-eligible subject, but the user should see that explanation.

Evidence:
- `arjun.k@demo.edu`:
  - `/student/leave/create` returned 200 with title `Apply for Leave`.
  - `/student/grievances/create` returned 200 with title `Submit Grievance`.
  - `/student/documents/request` returned 200 with title `Request a Document`.
  - `/student/fee-payment/create` redirected/rendered `/student/fee-payment` index.
  - `/student/feedback/1` redirected/rendered `/student/feedback` index.

Recommended fix:
- On Student Fee Payment, show why payment submission is unavailable if no eligible demand exists.
- On Student Feedback, show why a subject feedback form is unavailable if the subject is not eligible, not assigned, already submitted, or outside the window.
- Make create buttons visible only when actionable, otherwise show disabled reason text.

Fix applied:
- `/student/fee-payment/create` now returns 200 with a visible unavailable-state explanation when the student is inactive or has no outstanding academic fee demand.
- `/student/feedback/{subject}` now returns 200 with a visible unavailable-state explanation when feedback is unavailable because the student is inactive or has already submitted for that subject.
- Store routes still enforce the underlying business rules, so the explanatory GET pages do not weaken write protection.

Verification:
- Focused Student portal tests passed: `FeePaymentTest`, `StudentCourseFeedbackWorkflowTest`, `PortalFrontendBetaReadinessTest` = 59 tests / 826 assertions.
- Full `php artisan test` passed 1439 tests / 11178 assertions.

### Finding UX-F009: Student action entry points exist as links, but availability rules need clearer explanations

Severity: Medium  
Status: fixed_verified

The Student portal does expose action links for important workflows, so the action system is not purely missing. The UX issue is that some actions lead to a useful create screen, while others return to the index without an explicit reason at the route level. A student should not have to infer whether there is no payable demand, no feedback window, no eligible assignment, or an access issue.

Evidence:
- `arjun.k@demo.edu` link probe:
  - Dashboard includes `Need Help?` -> `/student/grievances/create`.
  - Dashboard includes `Apply for Leave` -> `/student/leave/create`.
  - Leave page includes `Apply for Leave`.
  - Grievances page includes `Submit New Grievance` and `Submit Grievance`.
  - Documents page includes `Request Document`.
  - Feedback page includes multiple `Give Feedback` links with subject IDs.
- Direct route probe:
  - Leave, grievance, and document request create pages open.
  - Fee payment create and invalid feedback subject route redirect to their index pages.

Recommended fix:
- Add visible unavailable-state messages on Fee Payment and Feedback pages.
- Ensure dashboard cards link to the exact actionable route when available and to an explanatory list/empty state when not available.

Fix applied:
- Fee Payment and Course Feedback availability rules now have route-level explanations on the action pages instead of silently redirecting to their index pages.
- Leave and Document Request availability rules now also render route-level explanations on the action pages for inactive students.
- Applicant fee proof entry now shows a final-state-specific closed reason when the applicant is enrolled, rejected, or withdrawn, while selected applicants still see the payment submission action.
- Applicant document upload entry remains visibly locked in final states and hides upload/remove actions.
- Disabled forms preserve context and tell the student why the action is unavailable.

Verification:
- Focused Student portal tests passed: `FeePaymentTest`, `StudentCourseFeedbackWorkflowTest`, `PortalFrontendBetaReadinessTest` = 59 tests / 826 assertions.
- Focused safe-action tests passed: `StudentLeaveWorkflowTest`, `StudentDocumentRequestGuidanceTest`, `StudentCourseContentAccessTest`, `PortalFrontendBetaReadinessTest` = 52 tests / 726 assertions.
- Focused applicant action-entry tests passed: `ApplicantPortalActionEntryTest`, `AdmissionDocumentVerificationIntegrityTest`, `AdmissionPaymentVerificationIntegrityTest`, `PortalFrontendBetaReadinessTest` = 34 tests / 633 assertions.
- Rendered probes as `arjun.k@demo.edu` opened dashboard, fee-payment create, and feedback create pages with no failed sidebar links and no debug/error text.
- Full `php artisan test` passed 1445 tests / 11202 assertions.
- Batch 5 focused portal pass verified Student fee payment, document request, grievance, and leave create routes remain reachable and guided: `PortalFrontendBetaReadinessTest`, `StudentLeaveWorkflowTest`, and `StudentDocumentRequestGuidanceTest` passed.

### Finding UX-F006: Teacher pages are mostly filters/read-only in the sampled action inventory

Severity: Medium  
Status: fixed_verified for sampled action-entry pages

Teacher pages such as exams, materials, assignments, and students loaded, but the sampled index pages expose mostly filters/search and not the main create/update actions. This may be because create actions are links rather than forms, or because the seeded teacher has no assigned scope. It needs deeper browser/link testing after fixing the current Teacher 403/500 blockers.

Evidence:
- `anjali@demo.edu`: `/teacher/exams`, `/teacher/materials`, `/teacher/assignments`, and `/teacher/students` form inventory contained search/filter forms plus logout/delete shell forms, but no obvious create/update workflow form on the sampled index pages.

Recommended fix:
- After profile/scope issues are fixed, verify teacher actions as a properly linked teacher: mark attendance, enter marks, upload material, create assignment, review students, mentor actions.
- Make primary action buttons visible even when no assigned scope exists, with a disabled explanation if the action cannot be used.

Fix applied:
- Teacher attendance marking, material upload, assignment creation, and student roster entry pages are now covered by focused portal safe-action tests.
- These pages render semantic/guided entry surfaces and avoid 403/404/500/debug output for the seeded teacher.

Verification:
- Batch 5 focused tests passed: `PortalFrontendBetaReadinessTest` = 12 tests / 650 assertions.
- Adjacent Teacher scope tests passed: `TeacherProfileMissingGracefulTest` and `TeacherScopeWorkflowTest` as part of the 67-test portal ownership/scope/action-entry run.

### Finding UX-F008: Teacher create/action routes redirect or fail for the seeded teacher

Severity: High  
Status: fixed_verified

Direct Teacher action route probes showed that the seeded teacher could not meaningfully enter several core workflows. The profile-related 500 class has been fixed for visible Teacher pages, but create/action workflow usability still needs a dedicated safe-submit pass.

Evidence:
- `anjali@demo.edu`:
  - `/teacher/materials/create` rendered `/teacher/materials` index.
  - `/teacher/assignments/create` rendered `/teacher/assignments` index.
  - `/teacher/attendance/mark` rendered `/teacher/dashboard`.
  - `/teacher/leaves/create` returned 500.

Likely cause:
- The seeded teacher does not have the required linked teacher/faculty profile or assigned teaching scope for these workflows.

Recommended fix:
- Keep the fixed teacher demo profile/assignment seed data stable.
- Add empty-state explanations when a teacher has no assigned classes/subjects.
- Add tests for Teacher create/action routes using the seeded teacher.

Fix applied:
- Teacher profile, timetable, mentor, feedback, and leave index routes now render 200 with operational empty states when a linked active Teacher profile/scope is missing.
- Focused tests: `TeacherProfileMissingGracefulTest`.
- Teacher attendance marking, material upload, and assignment creation GET routes now render 200 with explanatory locked states when a linked active Teacher profile or published teaching assignment is missing.
- Material and assignment create pages keep the form visible for orientation, but disable the fields and submit buttons while showing the exact reason.
- POST/delete/grade routes remain guarded for inactive or unscoped teachers.

Verification:
- Focused Teacher/portal tests passed: `TeacherProfileMissingGracefulTest`, `PortalFrontendBetaReadinessTest`, `TeacherScopeWorkflowTest` = 38 tests / 694 assertions.
- Rendered probes as `anjali@demo.edu` opened `/teacher/dashboard`, `/teacher/materials/create`, `/teacher/assignments/create`, and `/teacher/attendance/mark` with no failed links, no debug/error text, and semantic headings.
- Full `php artisan test` passed 1439 tests / 11178 assertions.

### Finding UX-F010: Teacher dashboard exposes action links that currently fail or redirect

Severity: High  
Status: fixed_verified

The Teacher dashboard and sidebar expose action links such as `Mark Attendance`, `Upload Material`, `View Timetable`, `My Feedback`, and `View Profile`. The visible 403/500 blockers have been fixed, but some create/action links may still redirect to index/dashboard when no eligible teaching scope exists. That remaining behavior needs explanatory empty-state checks.

Evidence:
- `anjali@demo.edu` link probe:
  - Dashboard includes `Mark Attendance` -> `/teacher/attendance/mark`.
  - Dashboard includes `Upload Material` -> `/teacher/materials/create`.
  - Dashboard includes `View Timetable` -> `/teacher/timetable`.
  - Dashboard includes `View Profile` -> `/teacher/profile`.
- Direct probes:
  - `/teacher/attendance/mark` renders `/teacher/dashboard`.
  - `/teacher/materials/create` renders `/teacher/materials`.
  - `/teacher/timetable` returns 403.
  - `/teacher/profile` returns 500.

Recommended fix:
- Add explicit unavailable-state messages for action routes that redirect because the teacher has no eligible class/subject scope.
- Add a seeded positive-scope teacher workflow test for attendance, materials, assignments, and profile visibility.

Fix applied:
- Dashboard/sidebar-visible Teacher profile/timetable/mentor/feedback/leave pages no longer return 403/500 for the seeded teacher.
- Focused tests and rendered navigation probes verify visible Teacher links return 200.
- Dashboard action targets for attendance, material upload, and assignment creation now open explanatory locked pages instead of redirecting to dashboard/index when no eligible linked profile or teaching scope exists.

### Finding UX-F011: Sampled portal direct-access boundaries block obvious cross-role routes

Severity: Low  
Status: fixed_verified for sampled direct-route and record-ownership checks

Basic direct-route probes show that Student, Applicant, and Parent users are blocked from obvious staff/other-portal routes. This is only a sampled check, not a full record-level ownership audit.

Evidence:
- `arjun.k@demo.edu`:
  - `/admin/students/1` returned 403.
  - `/admission/applicants/2` returned 403.
  - `/student/profile` returned 200.
- `priya.sharma@applicant.demo`:
  - `/admission/applicants/1` and `/admission/applicants/2` returned 403.
  - `/applicant/application` and `/applicant/status` returned 200.
- `parent@demo.edu`:
  - `/student/profile` returned 403.
  - `/admin/students/11` returned 403.
  - `/student/dashboard` returned 403.
  - `/parent/children` returned 200.

Remaining checks:
- Broader record-level ownership tests for less common portal record types as new workflows are added.
- Browser-level checks for whether unauthorized links are visible, not only whether direct URLs are blocked.

Fix applied:
- Added focused portal ownership regression coverage for parent child detail URLs and applicant self-service record URLs.
- Parent users can open linked child detail routes, but unlinked child attendance/results/fees routes return 403.
- Applicant users cannot open another applicant's payment detail, delete another applicant's document, open another applicant's offer, or accept/decline another applicant's offer.
- Student users cannot open, comment on, or close another student's grievance; assignment ownership remains covered by `StudentCourseContentAccessTest`.

Verification:
- Focused ownership/adjacent tests passed: `PortalOwnershipBoundaryTest`, `ParentPortalGuidanceTest`, `AdmissionDocumentVerificationIntegrityTest`, `AdmissionPaymentVerificationIntegrityTest`, `OfferLetterTest` = 55 tests / 297 assertions.
- Final focused ownership pass: `PortalOwnershipBoundaryTest`, `GrievanceWorkflowGuidanceTest`, `StudentCourseContentAccessTest` = 39 tests / 244 assertions.
- Full `php artisan test` passed 1446 tests / 11207 assertions.

### Finding UX-F012: Student portal personal lists mostly omit filters/pagination/export

Severity: Low  
Status: checked_no_blocker; future polish for richer personal filters

Student pages are personal-scope pages, so full staff-style table tooling is not always required. Still, some personal lists can grow over time and should expose basic search/filter or archive grouping.

Evidence:
- `/student/assignments`, `/student/fees`, `/student/library`, `/student/grievances`, `/student/notices`, `/student/feedback`, and `/student/documents` did not show search/pagination/export/sort signals in the list capability probe.
- Several pages are card-based rather than table-based, which may be acceptable if the item count stays small.

Recommended fix:
- For student-facing history pages, add compact filters such as current/archived, status, term, and date.
- Do not add heavy staff-style grids unless the personal data can realistically grow.

Mobile verification:
- `npm run frontend:smoke:mobile` passed 25 tests / 1212 assertions after the portal action-entry updates.
- Batch 5 verified the main personal history pages expose either an operational list or a clear empty state for the seeded student. Full staff-style export/filter grids remain optional polish for personal portals, not a release blocker.
- Latest mobile smoke passed: `npm run frontend:smoke:mobile` = 29 tests / 1380 assertions.

## Batch G: Accounts / CMC / Operations

Status: fixed_verified for sampled Accounts, CMC, Library, Hostel, Transport, and Assets UX/action-entry checks

Checklist:
- Accounts dashboard, fee collections, admission payments, outstanding, reconciliation, reports/exports.
- CMC dashboard, drives, companies, career events, placement stats, internships, alumni, analytics.
- Library: books, issues/returns, fines, reports.
- Hostel: rooms, allocations, fees, occupancy.
- Transport: routes, vehicles, assignments, fees.
- Assets: register, assignments, maintenance, lifecycle.

Authenticated route/page-health sample completed for:
- Accounts Officer `accounts@college.com`: 6 manifest routes.
- CMC / Placement Officer `cmc@college.com`: 8 manifest routes.

### Finding UX-G001: Accounts routes load, but most lack semantic page headings

Severity: Medium  
Status: fixed_verified

All sampled Accounts routes returned 200 and no Laravel/Whoops traces, but most pages do not expose semantic `h1`/`h2` headings. The finance workflows are sensitive enough that each page should clearly orient the user and show the current scope/filter context.

Evidence:
- `accounts@college.com` authenticated probes returned 200 for `/accounts/dashboard`, `/accounts/fee-collections`, `/accounts/admission-payments`, `/accounts/outstanding`, `/accounts/reconciliation`, and `/accounts/reports`.
- Only `/accounts/reconciliation` exposed an `h2` (`Admission Fee Reconciliation`); the other sampled Accounts pages returned no semantic heading.

Recommended fix:
- Add shared page headers to Accounts pages.
- Ensure each page shows visible filter/scope summaries and clear export/action buttons where relevant.

Fix applied:
- Accounts pages inherit the shared admin-shell semantic topbar `h1`.
- Browser check confirmed `/accounts/dashboard` renders `h1=Accounts - Dashboard` without debug/error text.

### Finding UX-G002: CMC routes load, but most lack semantic page headings

Severity: Medium  
Status: fixed_verified

All sampled CMC/Placement routes returned 200 with no debug traces, but most pages lack semantic headings. Placement workflows have many list/report pages, so page titles and in-page headings should be consistent.

Evidence:
- `cmc@college.com` authenticated probes returned 200 for `/cmc/dashboard`, `/cmc/drives`, `/cmc/companies`, `/cmc/events`, `/cmc/placement-stats`, `/cmc/internships`, `/cmc/alumni`, and `/cmc/analytics`.
- Only `/cmc/internships` exposed an `h2`; the rest returned no `h1`/`h2`.

Recommended fix:
- Add shared page headers to CMC pages.
- Add role smoke tests requiring meaningful headings and no empty primary content.

Fix applied:
- CMC pages inherit the shared admin-shell semantic topbar `h1`.
- Browser check confirmed `/cmc/dashboard` renders `h1=CMC Dashboard` without debug/error text.

### Finding UX-G003: Admin-backed operations pages load but lack semantic page headings

Severity: Medium  
Status: fixed_verified

Library, Hostel, Transport, and Assets pages are reachable as Admin and show operational tables/forms/cards, but all sampled pages lack semantic `h1`/`h2` headings. Since these are day-to-day operations screens, users need clearer page context, filter scope, and primary actions.

Evidence:
- `admin@college.com` authenticated probes returned 200 for:
  - `/admin/library`, `/admin/library/books`, `/admin/library/issues`, `/admin/library/memberships`, `/admin/library/reservations`, `/admin/library/fines`
  - `/admin/hostel`, `/admin/hostel/allocations`, `/admin/hostel/complaints`, `/admin/hostel/fees`, `/admin/hostel/outpasses`
  - `/admin/transport`
  - `/admin/assets`
- All sampled operation pages returned no `h1`/`h2`.

Recommended fix:
- Add shared operation page headers and visible filter summaries.
- Split future operator roles from Admin if Library/Hostel/Transport/Assets are expected to have their own staff users.

Fix applied:
- Admin Operations pages inherit the shared admin-shell semantic topbar `h1`.
- Browser check confirmed `/admin/library` renders `h1=Library Management` without debug/error text.

### Finding UX-G004: Operations pages are action-rich but need role split and safety review

Severity: Medium  
Status: partial_fixed_verified for existing lifecycle/access tests and sensitive confirmation guards; role split remains future work

Admin-backed operations pages expose many real write actions directly on the index pages. This is useful for speed, but it needs a safety/access pass before handing these modules to real operators.

Evidence:
- `/admin/hostel` exposes `Create Block`.
- `/admin/transport` exposes `Save Route`, `Add Stop`, `Add Vehicle`, and `Assign`.
- `/admin/assets` exposes `Create` stock item, `Save Category`, `Add Asset`, and filters.
- `/admin/library` is more list/search oriented, while deeper library list pages cover books/issues/memberships/reservations/fines.

Recommended fix:
- Add role-specific operator users for Library, Hostel, Transport, and Assets if these are real departments.
- Add confirmation/audit around destructive lifecycle actions: vacate, waive, return, issue, receive, transfer, end assignment.
- Add form-level validation and smoke tests for each operation's primary create form.

Verification:
- Focused operations lifecycle/access suite passed: `LibraryCirculationWorkflowTest`, `HostelWorkflowGuidanceTest`, `HostelFeeWorkflowTest`, `TransportWorkflowTest`, `AssetWorkflowTest`, `AdminLibraryAccessControlTest`, `AdminHostelAccessControlTest`, `AdminTransportAccessControlTest`, `AdminAssetAccessControlTest`, plus CMC lifecycle coverage = 126 tests / 1040 assertions.
- Existing tests cover key lifecycle locks and access restrictions for library circulation/reservations/fines, hostel allocation/fees/outpasses/complaints, transport assignments/end dates, asset stock/assignment returns, and CMC placement lifecycle.
- Sensitive operations lifecycle forms now have confirmation guards for asset assignment/return/stock receive/stock issue, transport assignment end, hostel fee paid/waive, hostel outpass approve/return, library reservation fulfil/cancel, and library fine paid actions.
- Focused operations frontend/lifecycle tests passed after the confirmation-guard slice: 115 tests / 1428 assertions.
- Full `php artisan test` passed 1458 tests / 11394 assertions.

Remaining:
- Role-specific operator roles for Library, Hostel, Transport, and Assets are not implemented in this frontend UX slice; current operations remain Admin-backed with existing access-control tests.
- Batch 6 focused frontend pass verified the primary Admin-backed action-entry surfaces for Library books/issues/reservations/fines, Hostel blocks/allocations/fees/outpasses, Transport route/assignment, Assets stock/category/asset assignment, Accounts outstanding/reconciliation, and CMC drive/company/event creation. These checks complement the existing backend lifecycle/access tests and confirmation guards.

### Finding UX-G005: Accounts and CMC sampled pages are mostly read-only/filter surfaces

Severity: Medium  
Status: fixed_verified for sampled Accounts/CMC action entry and export surfaces

Accounts and CMC pages load and show data, but sampled forms are mostly filters/search/logout. That may be correct for dashboard/list pages, but key actions should be easy to find from the first screen.

Evidence:
- Accounts:
  - `/accounts/fee-collections` exposes filters.
  - `/accounts/reconciliation` exposes a program filter.
  - dashboard, admission payments, outstanding, and reports expose no obvious action form beyond shell forms.
- CMC:
  - `/cmc/companies` exposes search.
  - `/cmc/events` exposes type filter.
  - dashboard, drives, placement stats expose no obvious create/update form in the sampled page.

Recommended fix:
- Confirm whether action entry points are links/buttons to create/detail pages.
- Add prominent primary actions where relevant: verify payment, reconcile item, export report, create drive, add company, schedule event.

Verification:
- CMC dashboard and list views already expose route-backed action links: `New Drive`, `New Event`, `Add Company`, drive applications, edit actions, and analytics links.
- Accounts dashboard exposes priority actions for payment verification, outstanding review, scholarship disbursement, reconciliation, and reports; Outstanding/Reconciliation now expose export-current-view actions.
- Focused CMC/operations tests passed 126 tests / 1040 assertions; Accounts/Admin Operations frontend tests passed 17 tests / 486 assertions.
- CMC drive/company/event create and edit forms now explain public workflow impact and require submit confirmation before recruiter, drive, event, or publication changes are saved.
- Focused CMC action-entry tests passed after the guidance slice: 38 tests / 638 assertions.
- Full `php artisan test` passed 1459 tests / 11412 assertions.
- Batch 6 focused frontend pass rechecked Accounts Outstanding/Reconciliation export/action surfaces and CMC Drive/Company/Event create pages. Focused `AdminOperationsFrontendBetaReadinessTest` passed 10 tests / 744 assertions, adjacent operations regression passed 130 tests / 1170 assertions, and full `php artisan test` passed 1490 tests / 12645 assertions.

### Finding UX-G006: Accounts and CMC rendered navigation is clean in sampled pass

Severity: Low  
Status: checked_no_blocker for rendered sidebar links

Rendered navigation for Accounts and CMC did not expose 403/404/500 links in the sampled pass. The remaining issues for these roles are primarily page headings, workflow discoverability, and deeper action testing.

Evidence:
- `accounts@college.com`: 8 rendered navigation links checked; all returned 200.
- `cmc@college.com`: 10 rendered navigation links checked; all returned 200.

### Finding UX-G007: Staff operation pages usually have pagination, but export/sort coverage is inconsistent

Severity: Medium  
Status: fixed_verified for sampled export/current-view and action-entry coverage; sorting remains future table-density polish

Accounts, Library, Hostel, Transport, Assets, and CMC pages mostly have pagination and some filters/search, but sorting remains inconsistent.

Evidence:
- Accounts:
  - `/accounts/fee-collections` has filters, pagination, and `Export CSV`.
  - `/accounts/reports` has `Collections CSV` and `Outstanding CSV`.
  - `/accounts/admission-payments`, `/outstanding`, and `/reconciliation` lack export/sort signal in the probe.
- Admin Operations:
  - Library/Hostel/Transport/Assets pages generally have search/filter and pagination.
  - Library, Hostel, Transport, and Assets now expose export-current-view controls for their primary operational lists.
- CMC:
  - Pages generally have pagination and some filters.

Fix applied:
- Accounts Outstanding now exposes `Export Current View` from the page header.
- Accounts overdue-demand drilldown export now respects `mode=overdue_demands`, exports the same overdue demand source list, and writes an export audit log with the active filter.
- Accounts Reconciliation now exposes `Export Current View` and preserves the current `program_id` filter when exporting verified admission payments.

Verification:
- Focused Accounts/Admin Operations tests passed: `AccountsDashboardGuidanceTest`, `AdminOperationsFrontendBetaReadinessTest`, `AdminOperationsKpiDrilldownConsistencyTest` = 17 tests / 486 assertions.
- Full `php artisan test` passed 1448 tests / 11225 assertions.

Remaining:
- Sorting remains inconsistent on some operation tables and should be handled in a later table-density pass.

Recommended fix:
- Add export current view for finance/operations/placement lists where reports are expected.
- Add sortable columns to table-heavy operational lists.
- Keep filter summaries visible so exported data is explainable.

Remaining Batch G work:
- No release-blocking Batch G UX gaps remain in the sampled readiness scope. Dedicated operator roles and richer sortable headers remain future bounded enhancements, not current blockers.

Mobile/table-overflow pass:
- Batch G mobile smoke now checks Accounts, CMC, and Admin-backed Operations pages for mobile sidebar markup, accessible open-menu controls, debug-trace absence, and responsive table wrappers when a page renders a table.
- Fixed missing table overflow wrappers on Hostel Allocations, CMC Companies, CMC Events, and CMC Drive Applications.
- Verification: `npm run frontend:smoke:mobile` passed 26 tests / 1287 assertions; focused operations/mobile-adjacent tests passed 42 tests / 772 assertions; full `php artisan test` passed 1460 tests / 11487 assertions.

## Workflow Test Matrix

Legend:
- `done`: checked with current evidence.
- `sampled`: representative routes/users checked, but not exhaustive.
- `partial`: useful evidence exists, but the workflow is not proven complete.
- `missing`: not yet verified.
- `blocked`: route/page currently blocks verification because it errors or denies visible links.

| Role family | Page load | Rendered nav | Forms/actions inventory | List controls | Safe submit | Export download | Mobile layout | Ownership/scope | Current status |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---|
| Admin / Director / HOD | done | sampled | sampled | sampled | sampled | sampled | sampled | partial | Admin dashboard quick actions, setup/security entry pages, and Admin/Director/HOD mobile shell are now covered by focused tests; broader admin information architecture remains future polish. |
| Admission Head/Admin | done | sampled | sampled | sampled | missing | partial | missing | partial | Core OS loads; KPI drilldowns, representative exports, and shared-shell heading coverage are verified. |
| Admission Officer/Manager/Counsellor/Telecaller | done | sampled | sampled | sampled | missing | partial | sampled | partial | Lower-role KPI drilldowns, rendered nav, calling, reminders, document verification, offer/seat read-only guards, and handoff visibility are now covered by focused tests. |
| Admission Partner | done | sampled | sampled | partial | missing | missing | sampled | partial | Partner portal dashboard, submitted-lead list, and lead submission are database-backed and scoped to the linked partner. |
| Dean Academics | done | sampled | sampled | sampled | missing | partial | sampled | partial | Dean OS is now the role landing; rendered Dean navigation no longer exposes the sampled admin-only links. |
| PMC / Program Leadership | done | sampled | sampled | partial | missing | partial | sampled | partial | PMC Command is now the role landing; approval/analytics current-view exports are verified; deeper timetable/course-allocation actions still need verification. |
| CoE / Exam | done | sampled | sampled | partial | missing | missing | sampled | partial | CoE scoped roles use OS routes; legacy Exam Cell routes remain compatible and now have semantic headings. |
| IQAC | done | sampled | sampled | partial | missing | missing | sampled | partial | IQAC OBE links now use IQAC-scoped routes for Head/Manager/Officer. |
| Course Delivery | done | sampled | sampled | partial | missing | missing | missing | partial | Actual Course Delivery routes load; older guessed routes were absent. |
| Teacher Portal | done | done | sampled | sampled | missing | partial | verified | fixed | Visible nav, mobile shell, and safe action entries for attendance/materials/assignments/roster are verified. |
| Student Portal | done | sampled | sampled | sampled | missing | partial | verified | fixed | Academic Summary, action availability, personal list/empty-state signals, and ownership checks are verified for sampled workflows. |
| Parent Portal | done | sampled | sampled | sampled | missing | missing | verified | fixed | Linked child attendance/results/fees work, unlinked child detail routes are blocked, and mobile shell is verified. |
| Applicant Portal | done | sampled | sampled | sampled | missing | missing | verified | fixed | Checklist/documents/fees/status action entry, final-state locks, self-service ownership, and mobile shell are verified. |
| Accounts | done | sampled | verified | sampled | sampled | verified | sampled | sampled | Outstanding/reconciliation export/action surfaces, dashboard links, and finance lifecycle/report tests are verified. |
| CMC | done | sampled | verified | sampled | sampled | verified | sampled | sampled | Drive/company/event create/edit guidance, export-current-view lists, and placement action tests are verified. |
| Library / Hostel / Transport / Assets | done | sampled | verified | sampled | verified | verified | sampled | sampled | Admin-backed operations pages load with headings, primary action-entry surfaces, confirmation guards, exports, mobile/table wrappers, and lifecycle/access tests. |

### Finding UX-H001: Representative export endpoints work, but export coverage is incomplete

Severity: Medium  
Status: sampled_working_exports_plus_missing_coverage

Representative export links were requested through authenticated sessions and returned real downloadable responses. This proves the export mechanism works in several mature areas. The remaining gap is coverage: many operational list pages do not expose an export link, and export behavior has not been checked for every module.

Verified working export responses:
- Admission:
  - `/admission/applicants/export-csv` returned `200`, `text/csv`, attachment `applicants-export-2026-06-20.csv`.
  - `/admission/leads/export-csv` returned `200`, `text/csv`, attachment `leads-export-2026-06-20.csv`.
  - `/admission/reports/export-pdf` returned `200`, `application/pdf`, inline filename `admission-report-2026-06.pdf`.
- Dean OS:
  - `/academics/dean-os/export/branch_health` returned `200`, `text/csv`.
  - `/academics/dean-os/export/program_risk` returned `200`, `text/csv`.
  - `/academics/dean-os/export/approval_sla` returned `200`, `text/csv`.
  - `/academics/dean-os/export/handoff_readiness` returned `200`, `text/csv`.
- Accounts:
  - `/accounts/export-fee-collections` returned `200`, `text/csv`, attachment `fee-collections-20260620.csv`.
- CMC:
  - Placement Drives, Companies, Career Events, Selected Placements, Drive Applications, and Event Registrations now expose `Export Current View` links.
  - Focused tests verify filtered CMC exports use the same source queries as the list pages, exclude nonmatching rows, and write CMC export activity logs.
- Library:
  - Books, Issues, Reservations, Memberships, and Fines now expose `Export Current View` links.
  - Focused tests verify filtered Library exports use the same source queries as the list pages, exclude nonmatching rows where filters apply, and write Library export activity logs.
- Hostel:
  - Allocations, Fee Demands, Outpasses, and Complaints now expose `Export Current View` links.
  - Focused tests verify filtered Hostel exports use the same source queries as the list pages, exclude nonmatching rows where filters apply, and write Hostel export activity logs.
- Transport:
  - Routes/Stops, Vehicles, and Active Assignments now expose `Export Current View` links.
  - Active Assignments support a source-backed search filter, visible filtered total, and query-preserving export.
  - Focused tests verify Transport exports use source-backed queries, exclude nonmatching assignment rows where filters apply, and write Transport export activity logs.
- Assets:
  - Asset Register, Active Assignments, Consumable Stock, and Stock Movements now expose export-current-view/current-list export links.
  - Asset Register exports preserve current `search` and `status` filters and show a visible filtered total.
  - Focused tests verify Assets exports use source-backed queries, exclude nonmatching asset rows where filters apply, block unauthorized export direct URLs, and write Assets export activity logs.

Missing or incomplete export coverage from list probes:
- Admission document/payment/reminder queues do not consistently expose export controls.
- PMC, CoE, IQAC, and some Admin Operations pages show inconsistent sort signal.
- Admin-backed Assets export coverage is now verified; richer sorting remains future table-density work.
- Student/Teacher/Parent/Applicant personal pages generally should not need broad exports, but downloadable official documents still need workflow-specific verification.

Recommended fix:
- Keep existing working export endpoints.
- Add export current view only to operational/reporting lists where users reasonably need it.
- Add focused tests that verify content type, file disposition, scoped filters, and row counts for each module's important exports.

## Next Verification Slices

These are the bounded slices that should follow this audit. Each slice should patch only defects proven by current evidence, then add tests before moving on.

1. Portal blockers:
   - Completed: fixed `/teacher/leaves`, `/teacher/profile`, and `/student/academic-summary` 500s.
   - Completed: fixed Teacher visible links that returned 403 by rendering missing-profile empty states.
   - Completed: added focused tests for Teacher missing-profile handling and Student academic summary mentor loading.
2. Scoped navigation blockers:
   - Completed: fixed IQAC OBE Framework visible link/access.
   - Completed: fixed scoped Academics brand/home link that pointed to `/admin/dashboard`.
   - Completed: fixed Dean visible `/admin/library` and `/admin/hostel` links.
   - Completed: Admission Officer sidebar no longer exposes Handoff while Head retains access.
3. Role landing:
   - Completed: Dean login routes to Dean OS.
   - Completed: PMC Head/Program Chair login routes to PMC Command OS.
   - Completed: added/updated login redirect tests by role.
4. Partner portal decision:
   - Completed: built partner landing, submitted-leads list, scoped lead submission, role redirect, and direct-route access test.
5. Safe workflow submissions:
   - Use test database only.
   - Verify one representative create/update/close workflow per major role family.
   - Add rollback-safe feature tests for each.
6. Export and table controls:
   - Verify real file responses for representative export links.
   - Add missing export/sort/filter summaries to high-volume operational lists.
   - Completed for primary CMC lists: drives, companies, events, selected placements, drive applications, and event registrations.
   - Completed for primary Library lists: books, issues, reservations, memberships, and fines.
   - Completed for primary Hostel lists: allocations, fee demands, outpasses, and complaints.
   - Completed for primary Transport lists: routes/stops, vehicles, and active assignments.
   - Completed for primary Assets lists: asset register, active assignments, consumable stock, and stock movements.
7. Browser/mobile pass:
   - Check desktop and mobile navigation for major roles.
   - Verify sidebar scroll, active state, table overflow, and modal/dropdown interactions.

## High-Priority Fix Backlog From Current Audit

This backlog is based only on current audit evidence. It should be converted into implementation slices after the role audit is completed.

1. Fix remaining visible-route permission mismatches:
   - Completed: IQAC OBE Framework links now use IQAC-scoped OBE readiness for IQAC Head/Manager/Officer.
   - Completed: scoped Academics brand/home links now route to role-specific OS landing pages instead of `/admin/dashboard`.
   - Completed: Dean navigation no longer exposes `/admin/library` and `/admin/hostel`.
   - Completed: CoE/Exam Manager/Officer visible navigation now uses CoE OS routes instead of inaccessible legacy `/exam-cell/*` daily routes.
   - Completed: Admission Officer visibility for `/admission/handoff` is policy-backed and hidden.
2. Fix role landing mismatches:
   - Completed: Dean login now lands on Dean OS.
   - Completed: PMC Head/Program Chair login now lands on PMC Command OS.
3. Complete or remove partner/channel demo workflow:
   - Completed: `partner.citychannel@demo.edu` now has a scoped partner-facing portal.
4. Add shared semantic page headers:
   - Admin/Director/HOD legacy pages.
   - Admission reports and many Admission role-specific pages.
   - Completed for legacy Exam Cell pages.
   - Teacher, Student, Parent, Applicant pages.
   - Accounts, CMC, Library, Hostel, Transport, Assets pages.
5. Continue deeper workflow testing:
   - Role-specific create/edit/filter/export/action flows.
   - Mobile/browser sidebar checks.
   - Ownership-boundary checks for Student, Parent, Applicant, Teacher, and scoped Academics roles.

## Coverage Status

Completed evidence types:
- User type inventory.
- Seeded demo account confirmation.
- Authenticated page-load checks for all listed user families.
- Rendered sidebar link checks for high-risk roles and representative portal/ops roles.
- Form/action surface inventory for Admission, Teacher, Student, Accounts, CMC, and Admin Operations samples.
- List capability inventory for representative Admission, Dean, PMC, CoE, IQAC, Student, Accounts, CMC, and Admin Operations pages.
- Sampled direct-route boundary checks for Student, Applicant, and Parent.
- Representative export download verification for Admission, Dean OS, and Accounts.
- Frontend build, desktop smoke, mobile/layout smoke, and full PHPUnit suite after the latest fixes.

Still required before this audit can be called exhaustive:
- Wider browser-level mobile checks beyond the smoke/layout gate.
- Actual safe submissions in a test database for create/edit workflows.
- Export download verification for modules not covered by the representative sample.
- Modal/dropdown interaction verification.
- Wider record-level ownership checks.
- Rendered sidebar verification for every scoped sub-role, not only representative samples.
- End-to-end workflow checks for Admission counselling, PMC timetable, CoE result lifecycle, IQAC evidence/action closure, Teacher attendance/material/assignment flows, Student payment/document/feedback flows, and Operations lifecycle actions.
