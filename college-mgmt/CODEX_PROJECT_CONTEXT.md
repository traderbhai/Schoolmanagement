# SchoolManagement Codex Project Context

## Runtime
- Laravel/PHP app in `C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt`.
- Local demo server usually runs on `http://localhost:8001`.
- Test runtime: `C:\tmp\php-8.5.7\php.exe` with `PHPRC=C:\tmp\php-8.5.7-codex-ini`.
- Run tests with: `$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'; C:\tmp\php-8.5.7\php.exe artisan test`.

## Demo Users
- Admin: `admin@college.com` / `password`.
- Dean Academics: `dean@college.com` / `password`.
- PMC Head / Program Chair: `chair@college.com` / `password`.
- PMC Manager: `pmc.manager@college.com` / `password`.
- CoE: `exam@college.com` / `password`.
- IQAC Head: `iqac.head@college.com` / `password`.
- Program Leader / HOD: `hod@college.com` / `password`.
- Faculty mentor: `faculty.mentor@college.com` / `password`.

## Completed Department Baselines
- Admission OS completed through v0.039, including hierarchy, workflows, communication safety, assessments, offer/seat control, and Admission-to-Academics handoff.
- Academics OS v0.01 created flexible Academics hierarchy for Dean Office, PMC, CoE/Examination, IQAC, and Program Leadership.
- Academics OS v0.011 added Academics command center and attention workspaces.
- PMC OS v0.02 added initial PMC dashboard: curriculum readiness, faculty allocation, timetable readiness, student monitoring, and basic reports.
- CoE OS v0.03, IQAC OS v0.04, Program Leadership OS v0.05, Course Delivery OS v0.06 are present as operating slices.
- Dean Academics OS v0.07 added Dean command dashboard, attention queues, branch health, program risk, reviews/actions, handoff, calendar, reports, and exports.
- Dean Academics OS v0.08 added planning cycles, advanced reviews/actions, risk governance, approval cockpit, faculty workload, student success, curriculum, exam readiness, quality, induction, analytics, calendar, saved views, and policy audit.

## Current Sprint
- Academics PMC OS v0.03 is being implemented.
- Scope: PMC command desk, workbench, curriculum governance, faculty workload, timetable control, student success, reviews/actions, saved views, reports/exports, seeder data, and tests.
- Existing v0.02 PMC routes remain stable.

## Development Rules
- Keep schema additive and SQLite-test compatible.
- Keep all existing routes stable unless adding compatible links.
- Extend `AcademicsOperatingDemoSeeder` for every new Academics/PMC surface.
- Use compact, professional, data-dense UI.
- Growing tables should have pagination and export/current-view links.
- Dashboard metrics should link to filtered source lists or relevant workbench pages.
