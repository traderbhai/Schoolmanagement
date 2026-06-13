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
- Current active development phase: v0.03.
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
- Migrations completed, including Admission OS v0.031 additive tables.
- `npm run build` passed.
- `PHPRC=C:\tmp\php-8.5.7-codex-ini C:\tmp\php-8.5.7\php.exe artisan test` passed: 393 tests, 1480 assertions.
- Admission v0.032 regression gate passed: 87 tests, 431 assertions.
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

## Known Local Notes

- `localhost:8000` is used by another local process. Use `http://127.0.0.1:8001` for this app.
- `package.json` and `package-lock.json` were updated to use `concurrently` with patched `shell-quote`.
