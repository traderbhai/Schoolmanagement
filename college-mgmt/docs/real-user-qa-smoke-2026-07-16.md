# Real User QA Smoke - 2026-07-16

## Scope

Time-boxed real-user smoke pass focused on major role journeys and critical timetable/development readiness. This pass intentionally avoids repeatedly testing the same page after it is green.

## Browser Limitation

The in-app browser connector failed to attach because of a runtime `process` property conflict. Local testing continued through the running Laravel server, live HTTP sessions with real login forms, and the existing role/frontend feature suites.

## Live Server Checks

Local server: `http://127.0.0.1:8000`

Verified with real form login and session cookies using seeded users:

| User | Path | Result |
| --- | --- | --- |
| `chair@college.com` | `/academics/pmc/command` | 200, no service error |
| `chair@college.com` | `/academics/pmc/official-timetable` | 200, no service error |
| `chair@college.com` | `/academics/pmc/timetable-generator` | 200, no service error |
| `chair@college.com` | `/academics/pmc/course-groups` | 200, no service error |
| `admin@college.com` | `/admin/dashboard` | 200, no service error |
| `dean@college.com` | `/academics/dean-os` | 200, no service error after fix |
| `head@college.com` | `/admission/dashboard` | 200, no service error |
| `anjali@demo.edu` | `/teacher/dashboard` | 200, no service error |
| `arjun.k@demo.edu` | `/student/dashboard` | 200, no service error |
| `accounts@college.com` | `/accounts/dashboard` | 200, no service error |

## Issue Found And Fixed

### Dean OS live 500

Live login as `dean@college.com` produced a 500 on `/academics/dean-os`.

Root cause:

`AcademicPmcOperatingService::canonicalTeacherConflicts()` and `legacyTeacherConflicts()` returned Eloquent collections containing arrays. Merging those arrays through an Eloquent collection triggered `Call to a member function getKey() on array` when real seeded conflict data existed.

Fix:

Both helper methods now return plain support collections of arrays before merge.

## Automated User Surface Evidence

Passed:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\FrontendReadinessTest.php tests\Feature\RoleDashboardTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AcademicsPmcFrontendBetaReadinessTest.php tests\Feature\AcademicsPmcUxGuidanceTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AdmissionFrontendBetaReadinessTest.php tests\Feature\PortalFrontendBetaReadinessTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AcademicsDeanFrontendBetaReadinessTest.php tests\Feature\AcademicsProgramLeadershipFrontendBetaReadinessTest.php tests\Feature\AcademicsCourseDeliveryFrontendBetaReadinessTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AcademicsCoeFrontendBetaReadinessTest.php tests\Feature\AcademicsIqacFrontendBetaReadinessTest.php tests\Feature\LegacyAcademicShellFrontendReadinessTest.php
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\AdminOperationsFrontendBetaReadinessTest.php tests\Feature\AdminOperationsUxGuidanceTest.php tests\Feature\AccountsDashboardGuidanceTest.php
npm run test:portal
npm run test:finance
npm run frontend:smoke:mobile
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature\ArchitectureStabilizationTest.php
npm run test:timetable
npm run frontend:build
C:\tmp\php-8.5.7\php.exe artisan test tests\Feature
```

Full feature suite result after the final fixes:

```text
1770 tests passed, 29566 assertions
```

Continuation verification on the same committed baseline:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'
C:\tmp\php-8.5.7\php.exe artisan test tests\Unit
npm run frontend:build
npm run frontend:smoke
npm run frontend:smoke:mobile
```

Results:

```text
Unit suite: 27 tests passed, 70 assertions
Frontend build: passed
Desktop frontend smoke: 135 tests passed, 3977 assertions
Mobile frontend smoke: 29 tests passed, 1473 assertions
```

Additional live HTTP login sweep on `http://127.0.0.1:8000` passed for these seeded-role pages with HTTP 200 and no service-error/debug failure text:

| User | Pages |
| --- | --- |
| `admin@college.com` | `/admin/dashboard`, `/admin/students`, `/admin/teachers`, `/admin/notices` |
| `chair@college.com` | `/academics/pmc/command`, `/academics/pmc/official-timetable`, `/academics/pmc/timetable-generator`, `/academics/pmc/course-groups`, `/academics/pmc/timetable-quality` |
| `dean@college.com` | `/academics/dean-os`, `/academics/dean-os/attention/critical_attention`, `/academics/course-delivery` |
| `head@college.com` | `/admission/dashboard`, `/admission/command-center`, `/admission/workbench`, `/admission/documents/queue` |
| `accounts@college.com` | `/accounts/dashboard`, `/accounts/fee-collections`, `/accounts/outstanding`, `/accounts/reconciliation` |
| `anjali@demo.edu` | `/teacher/dashboard`, `/teacher/timetable`, `/teacher/attendance/mark`, `/teacher/materials`, `/teacher/assignments` |
| `arjun.k@demo.edu` | `/student/dashboard`, `/student/timetable`, `/student/attendance`, `/student/fees`, `/student/courses` |

Fresh setup verification was also run against an isolated SQLite database, not the normal local `database.sqlite`:

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE='C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt\database\codex_fresh_smoke.sqlite'
C:\tmp\php-8.5.7\php.exe artisan migrate:fresh --seed --force
```

Result: fresh migrate and seed passed. The seeded PMC official timetable integrity check returned:

```text
official_items=3
missing_scope=0
missing_bridge_link=0
unscheduled_official=0
bridge_rows=3
parallel_slot_groups=1
```

Fresh seeded smoke on temporary server `http://127.0.0.1:8010` passed for:

| User | Pages |
| --- | --- |
| `chair@college.com` | `/academics/pmc/official-timetable`, `/academics/pmc/command`, `/academics/pmc/data-reconciliation` |
| `anjali@demo.edu` | `/teacher/timetable`, `/teacher/attendance/mark` |
| `arjun.k@demo.edu` | `/student/timetable`, `/student/attendance` |
| `admin@college.com` | `/admin/attendance`, `/admin/timetable` |

After fixing the fresh seed bridge gap, `npm run test:timetable` passed `126 tests / 1000 assertions`.

## Final Blockers Fixed

- Restored faculty load review refresh by moving shared multi-slot/consecutive-slot calculations into `TimetableSlotMathService` and delegating from `PmcTimetableFacultyReadinessService`.
- Kept the student fee UI behavior that labels past-due pending hostel demands as `Overdue`, and aligned the stale regression expectation.
- Fixed the KPI component markup spacing issue that affected the admin operations KPI drilldown assertion.
- Fixed fresh PMC demo seed bridge integrity: published canonical sessions now create compatibility bridge rows during seeding, matching the reconciliation baseline and downstream attendance/reporting expectations.

## Feedback

- The main seeded role dashboards and PMC timetable journeys are testable locally now.
- The app should keep live HTTP/session checks in the regular smoke routine because they caught a real seeded-data issue that isolated feature tests missed.
- Do not continue broad structural refactoring before the next product task unless a tested user flow exposes a blocker.

## Next Recommended Testing

When time allows, test one module at a time with real click/form submissions:

1. Admission application lifecycle.
2. Student timetable and attendance views.
3. Teacher timetable, attendance marking, and substitutions.
4. PMC publish/freeze flow on a disposable demo run.
5. Accounts fee/payment workflow.
