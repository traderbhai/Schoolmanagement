# Development Guide

## Local Runtime

Use the bundled PHP runtime for local checks on this workspace:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
C:\tmp\php-8.5.7\php.exe artisan test
```

Run the local web app from `public`:

```powershell
cd C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt\public
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
C:\tmp\php-8.5.7\php.exe -S 127.0.0.1:8000 -t . ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

## Standard Checks

```powershell
npm run frontend:build
npm run frontend:smoke
npm run frontend:smoke:mobile
npm run test:timetable
npm run test:portal
npm run test:finance
npm run test:production-readiness
```

## Current Stabilization Evidence

Last verified locally during the structural stabilization pass:

```powershell
npm run frontend:build
npm run frontend:smoke
npm run frontend:smoke:mobile
npm run test:timetable
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\ArchitectureStabilizationTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\FeePaymentTest.php tests\Feature\AccountsDashboardGuidanceTest.php tests\Feature\AdminGlobalExportAccessControlTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AdmissionFrontendBetaReadinessTest.php tests\Feature\AdmissionDepartmentOsTest.php tests\Feature\PortalFrontendBetaReadinessTest.php tests\Feature\StudentTimetableWorkflowTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AdminOperationsFrontendBetaReadinessTest.php tests\Feature\AdminOperationsUxGuidanceTest.php tests\Feature\AdminDashboardCanonicalTimetableTest.php
```

The full `artisan test` command was also attempted with the same PHP runtime and hit the local 10-minute process timeout before returning a failing test. Use the chunked suites above for reliable local verification until the full suite runtime is reduced.

## Git Hygiene

- Do not commit `.env`, SQLite databases, runtime logs, local diagnostics, generated cache files, or personal tool context.
- Keep generated graph reports only when they are durable documentation; keep graph cache and manifest outputs ignored.
- Prefer focused commits by subsystem: routes, timetable, admission, portal, finance, or frontend.
- Preserve existing route names and URLs during structural refactors unless the change is explicitly planned as a breaking route migration.
