# Codex Project Context

## Project

- Repository root: `C:\Users\mohd.naved\Documents\SchoolManagement`
- Laravel app root: `C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt`
- Git remote: `https://github.com/traderbhai/Schoolmanagement.git`
- Main branch: `main`
- Local app URL: `http://127.0.0.1:8001`
- Vite dev URL: `http://127.0.0.1:5173`

## Runtime

The system XAMPP PHP is `8.2.12`, but this project requires newer PHP. Use the project setup PHP:

```powershell
C:\tmp\php-8.5.7\php.exe
```

Composer should be run through that PHP binary:

```powershell
C:\tmp\php-8.5.7\php.exe C:\composer\composer.phar install
```

## Common Commands

Run these from `college-mgmt`.

```powershell
C:\tmp\php-8.5.7\php.exe artisan test
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
- Applicant: `/applicant/*`, `layouts.admin`
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
- Migrations completed.
- `npm run build` passed.
- `C:\tmp\php-8.5.7\php.exe artisan test` passed: 349 tests, 1157 assertions.
- `npm audit --audit-level=critical` passed with 0 vulnerabilities.
- Composer audit passed with no advisories.

## Known Local Notes

- `localhost:8000` is used by another local process. Use `http://127.0.0.1:8001` for this app.
- `package.json` and `package-lock.json` were updated to use `concurrently` with patched `shell-quote`.
