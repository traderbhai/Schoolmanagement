# Codex Project Context

## Project

- Repository root: `C:\Users\mohd.naved\Documents\SchoolManagement`
- Laravel app root: `C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt`
- Git remote: `https://github.com/traderbhai/Schoolmanagement.git`
- Main branch: `main`
- Local app URL: `http://127.0.0.1:8001`
- Vite dev URL: `http://127.0.0.1:5173`

## Current Product Phase

- Treat earlier commercial-readiness work as the completed v0.00 baseline.
- Current active development phase: v0.039 final Admission closure baseline before Academics/PMC.
- Previous v0.00 tasks are considered complete unless the user explicitly reopens a specific item.
- Do not use `COMMERCIAL_READINESS_AUDIT.md` as the active task source for new work unless the user explicitly asks for it.
- Do not update `COMMERCIAL_READINESS_AUDIT.md` during normal v0.03 development.
- For v0.03+, use the current worktree and this context file as the operational baseline, then inspect the relevant code before making changes.

## Runtime

The system XAMPP PHP is `8.2.12`, but this project requires newer PHP. Use the project setup PHP:

```powershell
C:\tmp\php-8.5.7\php.exe
```

Use this PHPRC when running the verified test command path:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
```

Composer should be run through that PHP binary:

```powershell
C:\tmp\php-8.5.7\php.exe C:\composer\composer.phar install
```

## Common Commands

Run these from `college-mgmt`.

```powershell
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\SpecificTest.php
C:\tmp\php-8.5.7\php.exe -d memory_limit=512M vendor\phpunit\phpunit\phpunit
npm run build
npm audit --audit-level=critical
C:\tmp\php-8.5.7\php.exe C:\composer\composer.phar audit
```

Start local servers:

```powershell
C:\tmp\php-8.5.7\php.exe artisan serve --host=127.0.0.1 --port=8001
npm run dev -- --host 127.0.0.1
```

## Code Map

- `routes/web.php`: main web routes and role portal grouping.
- `routes/api.php`: API routes.
- `app/Http/Controllers`: request handling by portal/domain.
- `app/Models`: Eloquent models.
- `app/Services`: business logic; prefer using/extending services over adding controller-heavy logic.
- `database/migrations`: schema.
- `database/seeders`: demo data, roles, permissions, access setup.
- `resources/views`: Blade UI.
- `resources/js` and `resources/css`: frontend entry points.
- `tests/Feature`: user-facing behavior tests.
- `tests/Unit`: service/model logic tests.

## Existing Context Docs

Use these before scanning broad code:

- `college-mgmt/CLAUDE.md`: concise developer rules, role map, SQLite pitfalls, route ordering.
- `college-mgmt/GUIDE.md`: fuller feature and workflow guide.
- `QUICK_REFERENCE.md`: quick repo-level operational reference.
- `IMPLEMENTATION_PATTERNS.md`: established implementation conventions.
- `TIMETABLE_ANALYSIS.md` and `TIMETABLE_IMPROVEMENTS_PLAN.md`: timetable-specific context.

## Role Portals

- Admin: `/admin/*`, `layouts.admin`
- Admission: `/admission/*`, `layouts.admin`
- Accounts: `/accounts/*`, `layouts.admin`
- Dean: `/dean/*`, `layouts.admin`
- HOD: `/hod/*`, `layouts.admin`
- Program Chair / PMC: `/program-chair/*`, `layouts.admin`
- Exam Cell: `/exam-cell/*`, `layouts.admin`
- Teacher: `/teacher/*`, `layouts.admin`
- Student: `/student/*`, `layouts.student`
- Parent: `/parent/*`, `layouts.parent`
- Applicant: `/applicant/*`, `layouts.applicant`
- Public: `/apply`, `/track`, `layouts.guest`
- Academic team: `/academic/*`, `layouts.admin`

## Demo Logins

All demo passwords are `password`.

- Admin: `admin@demo.edu`
- Admission Head: `head@college.com`
- Admission Officer: `officer@college.com`
- Accounts Officer: `accounts@college.com`
- Dean Academics: `dean@college.com`
- HOD: `hod@college.com`
- Program Chair: `chair@college.com`
- CMC: `cmc@college.com`
- Director: `director@college.com`
- Exam Cell: `exam@college.com`
- Teacher: `anjali@demo.edu`
- Student: `arjun.k@demo.edu`
- Parent: `parent@demo.edu`
- Applicant: `priya.sharma@applicant.demo`

## Development Workflow For Codex

For each requested change:

1. Identify the feature domain from the user request.
2. Search with `rg` for exact routes, controller names, model names, views, and tests.
3. Inspect only the vertical slice needed for that domain.
4. Prefer existing services, policies, request classes, view/layout patterns, and test style.
5. Make scoped edits.
6. Run targeted tests first.
7. Run broader tests/build when the change touches shared models, auth, routing, layouts, migrations, or services.

Avoid re-reading the whole repo unless the task is architectural or cross-cutting.
For full-suite verification, prefer the direct PHPUnit command with `-d memory_limit=512M`; plain `artisan test` can exhaust the local PHP memory limit.

## Laravel And SQLite Rules

- Static routes must be registered before wildcard routes with the same prefix.
- SQLite does not support `HAVING` without `GROUP BY`; filter in PHP after fetching when needed.
- Qualify column names in joins, for example `students.id`, when tables share column names.
- Do not use `whereType()` on `morphTo`; use `whereHasMorph()` and load nested relations after fetch.
- Do not rely on database-level enum enforcement in SQLite; validate enum-like values in Laravel.
- PDF Blade templates for `barryvdh/laravel-dompdf` must be standalone HTML and should not `@extends()` app layouts.

## Current Verified State

Last verified setup:

- Composer dependencies installed.
- npm dependencies installed.
- `.env` exists.
- SQLite database exists at `college-mgmt/database/database.sqlite`.
- Migrations completed, including Admission OS v0.039 additive tables.
- `npm run build` passed.
- `PHPRC=C:\tmp\php-8.5.7-codex-ini C:\tmp\php-8.5.7\php.exe artisan test` passed: 423 tests, 1707 assertions.
- Admission v0.039 regression gate passed: 117 tests, 658 assertions.
- `npm audit --audit-level=critical` passed with 0 vulnerabilities.
- Composer audit passed with no advisories.

## Admission OS Baseline

- v0.01 and v0.02 are treated as complete baselines.
- v0.03 implemented additive operations for command center, communication hub, telecaller call queue, pipeline boards, automation engine, deterministic lead scoring, applicant journey versions, partner/channel admissions, forecasting snapshots, data-quality flags, and admission approvals.
- v0.03 key files:
  - Migration: `college-mgmt/database/migrations/2026_06_13_900001_add_admission_os_v003_operations.php`
  - Demo seeder: `college-mgmt/database/seeders/AdmissionOperatingDemoSeeder.php`, called from `DemoDataSeeder`
  - Services: `AdmissionCommunicationService`, `AdmissionCallService`, `AdmissionPipelineService`, `AdmissionAutomationService`, `AdmissionLeadScoringService`, `AdmissionJourneyService`, `AdmissionPartnerService`, `AdmissionForecastingService`, `AdmissionDataQualityService`, `AdmissionApprovalService`, `AdmissionCommandCenterService`
  - Test: `college-mgmt/tests/Feature/AdmissionOsV003Test.php`
- New v0.03 route groups live under `/admission/command-center`, `/admission/communication`, `/admission/call-queue`, `/admission/pipeline`, `/admission/automations`, `/admission/lead-scoring`, `/admission/journeys`, `/admission/partners`, `/admission/data-quality`, `/admission/forecasting`, and `/admission/approvals`.
- Communication uses email/internal/mock SMS/mock WhatsApp abstractions by default. No live SMS/WhatsApp provider credentials are required.
- Forecasting is deterministic and explainable, not ML-based.
- PWA/offline sync is not implemented; v0.03 UI is responsive/PWA-ready direction only.
- Demo localhost should run with `APP_DEBUG=false`; branded `errors/404.blade.php` and `errors/500.blade.php` hide Laravel traces while preserving recovery copy covered by `ErrorPageTest`.
- After adding migrations or demo features, run `php artisan migrate --force` and `php artisan db:seed --class=DemoDataSeeder --force` on the local SQLite database so localhost pages use database-backed demo data instead of empty states.
- Preferred demo seed entry point is now `php artisan db:seed --class=MasterDemoSeeder --force`; it runs `DemoDataSeeder` and `RoleFeatureAccessSeeder`.

## Admission OS v0.031 Baseline

- v0.031 implemented daily operations, reminders/cadences, assessment panels, admission calendar, walk-ins, and manager reviews as additive modules over v0.03.
- v0.031 key files:
  - Migration: `college-mgmt/database/migrations/2026_06_13_910001_add_admission_os_v0031_operations.php`
  - Models: `AdmissionReminderSchedule`, `AdmissionCadenceRule`, `AdmissionAssessmentPanel`, `AdmissionAssessmentPanelMember`, `AdmissionAssessmentPanelAssignment`, `AdmissionWalkIn`, `AdmissionManagerReview`
  - Services: `AdmissionReminderService`, `AdmissionCadenceService`, `AdmissionAssessmentPanelService`, `AdmissionCalendarService`, `AdmissionWalkInService`, `AdmissionManagerReviewService`
  - Routes: `/admission/counsellor-workspace`, `/admission/manager-workspace`, `/admission/reminders`, `/admission/assessment-panels`, `/admission/assessment-operations`, `/admission/calendar`, `/admission/walk-ins`, `/admission/manager-reviews`
  - Views: `college-mgmt/resources/views/admission/v0031/*`
  - Test: `college-mgmt/tests/Feature/AdmissionOsV031Test.php`
- Demo data is in `AdmissionOperatingDemoSeeder` for reminder schedules, cadence rules, assessment panels/evaluators, panel assignments, applicant scores, walk-ins, and manager review queues.
- Existing selection sessions remain the legacy assessment backbone. New assessment variety is stored on `admission_assessment_panels.panel_type`; keep `selection_process_steps.type` within existing allowed values unless a future migration safely broadens that enum/check constraint.
- Browser smoke verification passed on localhost for counsellor workspace, manager workspace, reminders, assessment panels, assessment operations, admission calendar, walk-ins, manager reviews, and selection sessions. Mobile viewport checks passed for counsellor workspace and walk-ins with no horizontal overflow.

## Admission OS v0.032 Baseline

- v0.032 is a compact display/operations usability pass, not a new schema sprint.
- Dashboard KPI cards and funnel stages now link to their source lists/queues, for example submitted applicants, selected applicants, leads, document queue, payment queue, and enrollments.
- Admission leads list now supports search, status/source/program filters, page-size selection, server-side sort links, compact metrics, and paginated table results.
- Admission applicants list now supports page-size selection and server-side sort links while preserving existing search, program/status/date filters, export, bulk actions, completeness indicators, and pagination.
- Counsellor and manager workspaces now use compact cards/tables and link metric numbers or short-list headers to the relevant detailed queues/lists.
- New master demo seeder: `college-mgmt/database/seeders/MasterDemoSeeder.php`.
- Test: `college-mgmt/tests/Feature/AdmissionOsV032Test.php`.
- Browser smoke verification passed on localhost for dashboard, leads, applicants, counsellor workspace, and manager workspace. Mobile viewport checks passed for dashboard, leads, and applicants with no horizontal page overflow.

## Admission OS v0.033 Baseline

- v0.033 is a stabilization and data-density pass for v0.031 operational pages, focused on real database-backed lists, pagination, filtering, and hierarchy-safe mutations.
- Reminder, walk-in, and manager-review services now expose scoped query builders and access checks so controllers can paginate/filter and block cross-scope actions.
- Assessment panel and assessment operation pages now paginate operational tables and keep assignment/finalization/override actions permission-aware.
- Operational pages now have compact filters and pagination for reminders, walk-ins, assessment panels, assessment operations panel lists, and manager reviews.
- `AdmissionOperatingDemoSeeder` now creates v0.033 operational volume for reminders, walk-ins, manager reviews, and assessment panels so demo pages show seeded database rows instead of sparse/empty primary content.
- Test: `college-mgmt/tests/Feature/AdmissionOsV033Test.php`.
- Focused verification passed: `AdmissionOsV033Test` with 2 tests, 24 assertions.
- Admission regression gate passed: `AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 89 tests, 455 assertions.
- Full suite passed: 395 tests, 1504 assertions.
- Browser smoke verification passed on localhost after seeding `MasterDemoSeeder` for reminders, walk-ins, assessment panels, assessment operations, manager reviews, dashboard, leads, and applicants. Mobile viewport checks passed for reminders, walk-ins, and manager reviews with no horizontal page overflow.

## Admission OS v0.034 Baseline

- v0.034 is a real user-flow hardening pass for lead/applicant detail operations.
- Added `AdmissionNextActionService` to compute record-specific blockers, primary next action, quick commands, compact metrics, and recent operating activity for leads and applicants.
- Lead detail now shows a `Lead Action Center` with assignment/contact/conversion/reminder/call commands and recent communication/call/reminder activity.
- Applicant detail now shows an `Applicant Action Center` with document/payment/assessment/enrollment/reminder/call commands and recent operating activity.
- Added reusable action-center Blade partials under `college-mgmt/resources/views/admission/partials/`.
- Fixed applicant detail mobile header wrapping so the status/action row does not create horizontal overflow.
- Test: `college-mgmt/tests/Feature/AdmissionOsV034Test.php`.
- Focused verification passed: `AdmissionOsV034Test` with 2 tests, 20 assertions.
- Admission regression gate passed: `AdmissionOsV034Test|AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 91 tests, 475 assertions.
- Full suite passed: 397 tests, 1524 assertions.
- Browser smoke verification passed on localhost for lead and applicant detail action centers. Mobile viewport checks passed for both detail pages with no horizontal page overflow.

## Admission OS v0.036 Baseline

- v0.036 is an assessment-control and counsellor-operations sprint over the v0.034 baseline.
- Added assessment control room, rubric templates/criteria, evaluator draft/final scoring, lifecycle events, reschedules, assessment artifacts, aggregate score/variance detection, and panel readiness signals.
- Added counsellor operating desk, conversation timeline aggregation, counsellor playbooks/steps, structured counselling profiles, and quick call/reminder/communication commands.
- v0.036 key files:
  - Migration: `college-mgmt/database/migrations/2026_06_13_920001_add_admission_os_v0036_assessment_and_counsellor_ops.php`
  - Services: `AdmissionAssessmentControlRoomService`, `AdmissionRubricService`, `AdmissionEvaluatorScoringService`, `AdmissionCounsellorDeskService`, `AdmissionConversationTimelineService`, `AdmissionCounsellorPlaybookService`
  - Routes: `/admission/assessment-control-room`, `/admission/assessment-rubrics`, `/admission/evaluator-scoring`, `/admission/counsellor-desk`, `/admission/conversation-timeline/{subjectType}/{subjectId}`, `/admission/counsellor-playbooks`
  - Views: `college-mgmt/resources/views/admission/v0036/*`
  - Test: `college-mgmt/tests/Feature/AdmissionOsV036Test.php`
- `AdmissionOperatingDemoSeeder` now seeds v0.036 rubrics, rubric criteria, panel lifecycle states, evaluator scores, no-show/reschedule records, artifacts, counsellor playbooks, structured counselling profiles, and conversation timeline examples.
- Focused verification passed: `AdmissionOsV036Test` with 5 tests, 40 assertions.
- Admission regression gate passed: `AdmissionOsV036Test|AdmissionOsV034Test|AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 96 tests, 515 assertions.
- Full suite passed: 402 tests, 1564 assertions.
- Browser smoke verification passed on localhost for assessment control room, evaluator scoring, assessment rubrics, counsellor desk, counsellor playbooks, lead detail, applicant detail, and selection session detail. Mobile viewport checks passed for assessment control room, counsellor desk, and session detail with no horizontal page overflow.

## Admission OS v0.037 Baseline

- v0.037 is a completed production-hardening sprint focused on integration readiness, assessment scheduling/scoring depth, counsellor productivity, automation expansion, access audit visibility, exports, saved views, and UX navigation.
- Added sandbox/live-config-ready provider records for email/SMS/WhatsApp/dialer/video/signature, webhook event logs, provider delivery attempts, and communication delivery state tracking. Existing `mock_sms`/`mock_whatsapp` queued-provider aliases remain backward compatible; dispatch uses sandbox provider records.
- Added evaluator availability, assessment schedule conflict records, bulk evaluator assignment, blind scoring aliases, normalized assessment scores, counsellor target scorecards, script compliance logs, objection analytics, parent/guardian journeys, coaching notes, route access audit records, automation schedules/simulations/conflict logs, saved views, accessibility checklist, and v0.037 export logs.
- New services include `AdmissionIntegrationService`, `AdmissionProviderRegistry`, `AdmissionWebhookService`, `AdmissionBulkEvaluatorAssignmentService`, `AdmissionBlindScoringService`, `AdmissionAssessmentNormalizationService`, `AdmissionScriptComplianceService`, `AdmissionObjectionAnalyticsService`, `AdmissionParentJourneyService`, `AdmissionAutomationSchedulerService`, `AdmissionAutomationSimulationService`, `AdmissionSavedViewService`, `AdmissionAccessibilityAuditService`, and `AdmissionExportService`.
- New routes include `/admission/integrations`, `/admission/assessment-bulk-assignment`, `/admission/assessment-normalization`, `/admission/script-compliance`, `/admission/objection-analytics`, `/admission/parent-journeys`, `/admission/automation-simulation`, `/admission/saved-views`, `/admission/accessibility-audit`, `/admission/v037-exports/{type}`, plus webhook and action POST routes.
- New views: `college-mgmt/resources/views/admission/v0037/*`.
- `AdmissionOperatingDemoSeeder` now seeds all v0.037 completion data: provider configs, webhook/delivery records, conflict demo panel/assignments, blind aliases, normalized scores, script templates/compliance logs, objections, parent journeys, automation schedules/simulations, saved views, export logs, counsellor targets, coaching notes, and route access audit records.
- Focused verification passed: `AdmissionOsV037Test` with 9 tests, 75 assertions.
- Admission regression gate passed: `AdmissionOsV037Test|AdmissionOsV036Test|AdmissionOsV034Test|AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 105 tests, 590 assertions.
- Full suite passed: 411 tests, 1639 assertions.
- Browser smoke verification passed on localhost for integrations, assessment bulk assignment, assessment normalization, script compliance, objection analytics, parent journeys, automation simulation, saved views, accessibility audit, assessment control room, and counsellor desk. Mobile viewport checks passed for integrations, bulk assignment, normalization, counsellor desk, and automation simulation with no horizontal page overflow.

## Admission OS v0.038 Baseline

- v0.038 is a real-team admission operations sprint over the v0.037 baseline.
- Added calling-desk speed mode with next-call selection, call attempts, queue skips, script-compliance capture, retry reminders, and parent/objection context.
- Added assessment logistics for slots, slot assignments, reschedule requests, resources/resource bookings, evaluator invitations, GD group building, assessment submissions, and final selection committee decisions.
- Added offer/seat control for offer rounds, waitlist entries and promotions, seat holds/releases, seat movements, deferrals, and joining-kit tasks.
- Added communication safety for consent records/history, quiet-hour rules, template approval/versioning, bulk-send safety previews, and sensitive action audit events.
- Added vendor-ready integration health and retry queue records for MSG91-style SMS, Meta WhatsApp, Exotel-style dialer, Zoom/Meet-style video, and DocuSign/Leegality-style signature adapters, with sandbox providers as the local default.
- Added global Admission quick search across leads, applicants, offers, and selection sessions.
- v0.038 key files:
  - Migration: `college-mgmt/database/migrations/2026_06_14_100001_add_admission_os_v0038_real_team_ops.php`
  - Services: `AdmissionCallingDeskService`, `AdmissionCallQueueSelectorService`, `AdmissionCallAttemptService`, `AdmissionAssessmentSlotService`, `AdmissionAssessmentResourceService`, `AdmissionGdGroupService`, `AdmissionAssessmentSubmissionService`, `AdmissionSelectionCommitteeService`, `AdmissionOfferRoundService`, `AdmissionWaitlistService`, `AdmissionSeatControlService`, `AdmissionDeferralService`, `AdmissionJoiningKitService`, `AdmissionConsentService`, `AdmissionTemplateApprovalService`, `AdmissionCommunicationSafetyService`, `AdmissionVendorAdapterRegistry`, `AdmissionIntegrationHealthService`, `AdmissionQuickSearchService`
  - Routes: `/admission/calling-desk`, `/admission/assessment-scheduling`, `/admission/selection-committee`, `/admission/offer-seat-control`, `/admission/communication-safety`, `/admission/integration-health`, `/admission/quick-search`
  - Views: `college-mgmt/resources/views/admission/v0038/*`
  - Test: `college-mgmt/tests/Feature/AdmissionOsV038Test.php`
- `AdmissionOperatingDemoSeeder` now seeds v0.038 data for call queues/attempts, assessment slots/resources/GD groups/submissions, committee decisions, offer rounds, waitlists, seat holds, deferrals, joining-kit tasks, consents, template approvals, quiet hours, provider health checks, retry queue, saved views, and quick-search logs.
- Focused verification passed: `AdmissionOsV038Test` with 6 tests, 45 assertions.
- Admission regression gate passed: `AdmissionOsV038Test|AdmissionOsV037Test|AdmissionOsV036Test|AdmissionOsV034Test|AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 111 tests, 635 assertions.
- Full suite passed: 417 tests, 1684 assertions.
- Browser verification passed on localhost for calling desk, assessment scheduling, selection committee, offer/seat control, communication safety, integration health, and quick search. Mobile viewport checks passed for calling desk, assessment scheduling, offer/seat control, and communication safety with no document-level horizontal overflow. Browser action checks passed for calling-desk outcome creation and integration health refresh.

## Admission OS v0.039 Final Closure Baseline

- v0.039 is the final Admission OS closure sprint before moving to Academics and PMC.
- Added central admission policy checks and audit logs, sensitive action audit helpers, transition-event logging, safe communication wrapper, blocked/delayed communication queue, final export logs, high-volume seed profile records, and formal Admission-to-Academics/PMC handoff records.
- Communication Hub, bulk communication, reminders, and automations now route through `AdmissionSafeCommunicationService`; legacy templates with no approval history remain compatible, while templates in an approval workflow must be approved before sending.
- Applicant self-service now includes `/applicant/admission-operations` with own-scope assessment slot/reschedule, assessment submissions, consent preferences, waitlist/seat-hold status, joining-kit checklist, deferral status, and handoff status.
- Assessment scheduling now uses searchable selectors instead of raw applicant IDs, supports bulk slot assignment, check-in lifecycle updates, reschedule review, evaluator replacement, submission audit, and filtered export.
- Offer/seat closure now enforces seat-matrix availability on holds, audits seat releases and deferral approvals, and has `admission:run-final-schedulers` for expired offers/holds and waitlist replies.
- Handoff queue lives at `/admission/handoff` and tracks pending, blocked, ready, handed-off, and returned-for-correction records with document/fee/joining-kit summaries.
- v0.039 key files:
  - Migration: `college-mgmt/database/migrations/2026_06_14_110001_add_admission_os_v0039_final_closure.php`
  - Services: `AdmissionAccessPolicyService`, `AdmissionSensitiveAuditService`, `AdmissionSafeCommunicationService`, `AdmissionTransitionService`, `AdmissionHandoffService`, `AdmissionFinalExportService`, `AdmissionOfferSeatSchedulerService`
  - Routes: `/admission/handoff`, `/admission/v039-exports/{type}`, `/applicant/admission-operations`, plus v0.039 assessment scheduling action routes
  - Views: `college-mgmt/resources/views/admission/v0039/handoff.blade.php`, `college-mgmt/resources/views/applicant/admission-operations.blade.php`
  - Test: `college-mgmt/tests/Feature/AdmissionOsV039Test.php`
- `AdmissionOperatingDemoSeeder` now seeds v0.039 final scenarios for blocked communications, assessment-day lifecycle, evaluator replacement, reschedule approval, seat expiry, handoff records, sensitive audits, export logs, high-volume readiness profile, and saved views.
- Focused verification passed: `AdmissionOsV039Test` with 6 tests, 23 assertions.
- Admission regression gate passed: `AdmissionOsV039Test|AdmissionOsV038Test|AdmissionOsV037Test|AdmissionOsV036Test|AdmissionOsV034Test|AdmissionOsV033Test|AdmissionOsV032Test|AdmissionOsV031Test|AdmissionOsV003Test|AdmissionDepartmentOsTest|AdmissionFlowTest|ApplicantStatusGuidanceTest|OfferLetterTest|LaunchRouteSmokeTest|ErrorPageTest` with 117 tests, 658 assertions.
- Full suite passed: 423 tests, 1707 assertions.
- Browser verification passed on localhost for handoff, assessment scheduling, communication safety, route access audit, and applicant admission operations. Mobile viewport checks passed for applicant admission operations and admission handoff with no document-level horizontal overflow.

## Known Local Notes

- `localhost:8000` is used by another local process. Use `http://127.0.0.1:8001` for this app.
- `package.json` and `package-lock.json` were updated to use `concurrently` with patched `shell-quote`.
