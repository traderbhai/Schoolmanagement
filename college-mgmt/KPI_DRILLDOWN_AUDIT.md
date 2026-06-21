# KPI Drilldown Audit

Release control file for the cross-department KPI drilldown consistency sprint.

Invariant: a visible clickable KPI must use the same source query and scope as the destination list. The destination must show a visible total and filter summary. Summary-only metrics must not look like drilldown links.

## Reference Baseline

Admission was fixed first and is the reference pattern:
- `AdmissionKpiDrilldownService` owns dashboard and drilldown queries.
- Dashboard cards link to exact filters.
- Destination lists show scoped total and active filter context.
- Verified previously with `AdmissionKpiDrilldownConsistencyTest`, Admission regressions, full suite, and browser checks.

## Academics Dean OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Overdue Approvals | `AcademicDeanAttentionService::queues()['overdue_dean_approvals']` | `academics.dean-os.attention(overdue_dean_approvals)` | Dean/admin/academic owner/director | ok | Queue route already matched count. |
| Open Actions | `AcademicDeanActionItem` active statuses | `academics.dean-os.reviews?status=open` | Dean/admin/academic owner/director | fixed | Reviews page now filters active actions and shows total/filter summary. |
| Critical Program Risks | `AcademicDeanRiskService::programRisks()` critical/high bands | `academics.dean-os.program-risk?band=critical_high` | Dean/admin/academic owner/director | fixed | Program risk page now accepts band/program filters and shows total/filter summary. |
| Handoff Blockers | Admission handoff blocker queue statuses | `academics.dean-os.handoff?status=blocking` | Dean/admin/academic owner/director | fixed | `blocking` maps to blocked, pending admission completion, and returned for correction. |
| Critical Attention | Aggregate of all critical/high queues | `academics.dean-os.attention(critical_attention)` | Dean/admin/academic owner/director | fixed | Aggregate queue is backed by the same Dean attention service count and lists critical/high items. |
| Reports: Program Risk / Actions / Handoff | Dean command report cards | Filtered Dean pages | Dean/admin/academic owner/director | fixed | Report routes now carry exact risk/action/handoff filters where applicable. |

Focused evidence: `AcademicsDeanKpiDrilldownConsistencyTest`.

## PMC OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Curriculum Gaps | `AcademicPmcOperatingService::dashboard()` | `curriculum-readiness?metric=curriculum_gaps` | PMC hierarchy plus Dean/admin oversight | fixed | Shared section service filters the same metric-keyed item set and shows total/filter summary. |
| Faculty Gaps | `AcademicPmcOperatingService::dashboard()` | `faculty-allocation?metric=faculty_gaps` | PMC hierarchy plus Dean/admin oversight | fixed | Missing faculty and overload items share the aggregate key. |
| Student Risk | `AcademicPmcOperatingService::dashboard()` | `student-monitoring?metric=student_risk` | PMC hierarchy plus Dean/admin oversight | fixed | Attendance and weak-performance rows share the aggregate key. |
| Scoped Programs | `AcademicPmcOperatingService::scopedPrograms()` | `academics.pmc.programs?metric=active_programs` | PMC hierarchy plus Dean/admin oversight | fixed | Added a scoped PMC program source list so the dashboard count opens matching program rows without leaking to global Admin Programs. |
| Timetable Hard Conflicts | `AcademicPmcTimetableV041Service::dashboard()` | `timetable-planner?severity=hard` | PMC hierarchy plus Dean/admin oversight | fixed | Dashboard now clones constraint queries and planner applies severity filter. |
| Timetable Soft Warnings | `AcademicPmcTimetableV041Service::dashboard()` | `timetable-planner?severity=soft` | PMC hierarchy plus Dean/admin oversight | fixed | Fixed previous query reuse bug that undercounted soft warnings. |
| Timetable Quality Score | Average quality score | `timetable-quality` | PMC hierarchy plus Dean/admin oversight | fixed | Score now opens the quality/generator source surface so PMC can inspect quality before publish/freeze. |

Focused evidence: `AcademicsPmcKpiDrilldownConsistencyTest`.

## CoE / Exam OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Upcoming Exams | `AcademicCoeOperatingService::examReadiness()` | `exam-readiness?metric=upcoming_exams` | CoE scope plus Dean/admin oversight | fixed | Items carry `metric_keys`; section filter applies same key. |
| Marks Pending | `AcademicCoeOperatingService::marksResults()` | `marks-results?metric=marks_pending` | CoE scope plus Dean/admin oversight | fixed | Count/list now match pending-marks items only. |
| Hall Ticket Blocks | `AcademicCoeOperatingService::hallTicketReadiness()` | `hall-ticket-readiness?metric=blocked_registrations` | CoE scope plus Dean/admin oversight | fixed | Drilldown uses exact blocked registration item set. |
| Appeals/Anomalies | `AcademicCoeOperatingService::appealsAnomalies()` | `appeals-anomalies?metric=appeals_anomalies` | CoE scope plus Dean/admin oversight | fixed | Appeal and anomaly rows share aggregate metric key. |
| Section metric cards | Section service metrics | Same section with `metric=<key>` | CoE scope plus Dean/admin oversight | fixed | Filter preserves metric through search/status form; reset now clears search/status while keeping the active metric queue. |

Focused evidence: `AcademicOperatingKpiDrilldownConsistencyTest`.

## IQAC OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| OBE Gaps | `AcademicIqacOperatingService::obeReadiness()` | `obe-readiness?metric=obe_gaps` | IQAC scope plus Dean/admin oversight | fixed | PO and CO gaps share aggregate key. |
| Mapping Gaps | `AcademicIqacOperatingService::obeReadiness()` | `obe-readiness?metric=mapping_gaps` | IQAC scope plus Dean/admin oversight | fixed | Maps only COs without mapping. |
| Target Misses | `AcademicIqacOperatingService::attainmentMonitoring()` | `attainment-monitoring?metric=target_misses` | IQAC scope plus Dean/admin oversight | fixed | CO and PO misses share aggregate key. |
| Feedback Gaps | `AcademicIqacOperatingService::feedbackQuality()` | `feedback-quality?metric=feedback_gaps` | IQAC scope plus Dean/admin oversight | fixed | Matches subjects missing feedback only. |
| Audit/Compliance metric cards | `AcademicIqacOperatingService::auditCompliance()` | `audit-compliance?metric=<key>` | IQAC scope plus Dean/admin oversight | fixed | Quality-review and scope-change keys no longer overmatch generic audit activity; reset now clears search/status while keeping the active metric queue. |

Focused evidence: `AcademicOperatingKpiDrilldownConsistencyTest`.

## Program Leadership OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Programs | `AcademicProgramLeadershipService::programPortfolio()` | `portfolio?metric=active_programs` | Assigned program scope plus Dean/admin oversight | fixed | Program rows carry active-program metric key. |
| Active Students | `AcademicProgramLeadershipService::studentSuccess()` | `student-success?metric=active_students` | Assigned program scope plus Dean/admin oversight | fixed | Active-student rows materialize only on the filtered source list, keeping default student-success previews focused on risk/weak-performance queues. |
| Delivery Gaps | `AcademicProgramLeadershipService::courseDelivery()` | `course-delivery?metric=delivery_gaps` | Assigned program scope plus Dean/admin oversight | fixed | Faculty gaps and draft timetable entries share aggregate key. |
| Student Risk | `AcademicProgramLeadershipService::studentSuccess()` | `student-success?metric=student_risk` | Assigned program scope plus Dean/admin oversight | fixed | Attendance risk and weak performance rows share aggregate key. |

Focused evidence: `AcademicOperatingKpiDrilldownConsistencyTest`.

## Course Delivery OS

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Assigned Courses | `AcademicCourseDeliveryService::courseLoad()` | `course-load?metric=assigned_subjects` | Assigned teacher/course scope plus Dean/admin oversight | fixed | Exact faculty assignment rows only. |
| Today Sessions | `AcademicCourseDeliveryService::sessionDelivery()` | `session-delivery?metric=today_sessions` | Assigned teacher/course scope plus Dean/admin oversight | fixed | Only today published session rows. |
| Attendance Risk | `AcademicCourseDeliveryService::attendanceInterventions()` | `attendance-interventions?metric=attendance_risk_students` | Assigned teacher/course scope plus Dean/admin oversight | fixed | Only grouped at-risk student rows. |
| Mentor Actions | `AcademicCourseDeliveryService::mentorActions()` | `mentor-actions?metric=open_mentor_actions` | Assigned mentor/student scope plus Dean/admin oversight | fixed | Only open mentor meeting/action rows. |

Focused evidence: `AcademicOperatingKpiDrilldownConsistencyTest`.

## Admin / Operations / Accounts / CMC

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Admin dashboard primary metrics | Admin dashboard controller stats | Students, teachers, departments, programs, attendance, fees, notices, and exams source routes | Admin/director | fixed | Primary institute-health cards now open the matching admin source pages instead of remaining static summary cards. |
| Accounts primary finance KPIs | Accounts dashboard demand/payment queries | `accounts.reports`, `accounts.fee-collections?status=paid`, `accounts.outstanding`, `accounts.outstanding?mode=overdue_demands` | Accounts/admin/director | fixed | Total billed, collected, outstanding, and overdue cards now open their source finance surfaces instead of remaining static. |
| Accounts Pending Admission Verification | `AdmissionPayment::status=pending` | `accounts.admission-payments` | Accounts/admin/director | ok | Destination list already filters pending payments. |
| Accounts Overdue Demands | overdue or pending past-due fee demands | `accounts.outstanding?mode=overdue_demands` | Accounts/admin/director | fixed | Added exact demand-level mode with total/filter summary; prior link went to broad academic fee demands. |
| CMC primary KPI cards | CMC dashboard controller | `cmc.drives?status=active`, `cmc.placements`, `cmc.analytics` | CMC/dean/program/admin | fixed | Active drives now has an exact active source-list/export mode; placement totals and rate open selected placement or analytics surfaces. |
| CMC priority action | CMC priority service branch | Contextual create/list route | CMC/dean/program/admin | ok | This is an action shortcut, not a count drilldown. |

Focused evidence: `AdminOperationsKpiDrilldownConsistencyTest`.

## Portals

| Metric / Surface | Displayed Count Source | Drilldown Destination | Scope Rules | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| Student pending assignments due next 7 days | Student dashboard enrolled published assignment query | `student.assignments.index?filter=pending_next_7` | Own active student record only | fixed | Added matching filter mode and visible filtered source count. |
| Student attendance / fee priority links | Student dashboard owned queries | Student attendance/fees pages | Own active student record only | ok | Action links are owned portal pages, not broad admin lists. |
| Teacher dashboard KPI cards | Teacher dashboard assigned published timetable/query counts | Teacher timetable and attendance marking routes | Own teacher record/assigned classes | fixed | Weekly Load and My Classes Today open the timetable; Mark Attendance opens attendance marking as a primary card action. |
| Parent child cards | Parent dashboard linked children | Child-specific attendance/results/fees routes | Linked children only | ok | Links carry the child route parameter and remain scoped. |
| Applicant status/checklist cards | Applicant dashboard applicant record | Applicant-owned application/doc/fee routes | Own applicant record only | ok | Dashboard uses applicant-owned routes and no false metric anchors. |

Focused evidence: `PortalKpiDrilldownConsistencyTest`.

## Current Verification

- Focused tests: `AcademicOperatingKpiDrilldownConsistencyTest`, `AcademicsDeanKpiDrilldownConsistencyTest` passed 6 tests / 80 assertions.
- Adjacent frontend readiness: Dean, CoE, IQAC, Program Leadership, and Course Delivery frontend beta tests passed 21 tests / 342 assertions.
- Adjacent workflow regressions: Dean v0.07/v0.08, CoE v0.03, IQAC v0.04, Program Leadership v0.05, and Course Delivery v0.06 passed 44 tests / 225 assertions.
- Full suite: `php artisan test` passed 1409 tests / 11000 assertions.
- Browser verification on `localhost:8001`: Dean actions/risk/handoff plus CoE marks, IQAC OBE, Program delivery, and Course attendance drilldowns rendered expected filter summaries/totals with no debug/error text.
- Additional focused tests after continuing the sprint: `AcademicsPmcKpiDrilldownConsistencyTest` passed 5 tests / 31 assertions; `AdminOperationsKpiDrilldownConsistencyTest` passed 2 tests / 12 assertions; `PortalKpiDrilldownConsistencyTest` passed 2 tests / 11 assertions.
- Program Leadership active-student follow-up: `AcademicOperatingKpiDrilldownConsistencyTest`, `AcademicsProgramLeadershipUxGuidanceTest`, and `AcademicsProgramLeadershipV005Test` passed 15 tests / 173 assertions.
- Admin dashboard KPI follow-up: `AdminOperationsUxGuidanceTest` and `AdminOperationsKpiDrilldownConsistencyTest` passed 7 tests / 284 assertions; adjacent admin frontend/redirect checks passed 76 tests / 1839 assertions.
- PMC scoped-program follow-up: `AcademicsPmcKpiDrilldownConsistencyTest`, `AcademicsPmcUxGuidanceTest`, and `AcademicsPmcTimetableV041Test` passed 13 tests / 303 assertions.
