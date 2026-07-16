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

Post-fix release gates on commit `2d0a05f` also passed:

```text
npm run test:production-readiness: 57 tests passed, 12456 assertions
php artisan test tests\Feature: 1771 tests passed, 29576 assertions
npm run frontend:build: passed
npm run frontend:smoke: 135 tests passed, 3977 assertions
npm run frontend:smoke:mobile: 29 tests passed, 1473 assertions
```

Bounded role-navigation crawl on `http://127.0.0.1:8000` then checked visible same-site links for major seeded roles. Initial crawl found real broken user-facing paths:

- `/exam-cell/exams/create` rendered a 500 because the view referenced the non-existent route name `exam-cell.exams.index`.
- `/approvals/inbox` rendered a 500 because `ApprovalWorkflow::overdue()` was called as a query scope but only existed as an instance method.
- Admission document queues exposed preview/download links for seeded files that were not present in local storage, producing visible 404s.
- Accounts pages linked accounts officers into Admission-only payment/scholarship routes, producing avoidable 403s from visible finance actions.

Fixes applied:

- Exam Cell create/cancel links now use the existing `exam-cell.exams` route.
- `ApprovalWorkflow` now has a query `scopeOverdue()`.
- Admission document queue, applicant detail, and workbench show a `File missing` state instead of preview/download links when the local file is absent.
- Accounts now has an owned scholarship disbursement queue at `/accounts/scholarship-disbursements`; Accounts dashboard/payment links stay inside Accounts routes.

Final bounded crawl result after fixes:

```text
Major seeded role crawl: 663 checked pages/links, 0 broken 404/500/runtime-error pages.
```

Focused and adjacent verification after these crawl fixes:

```text
Admin/Accounts adjacent feature checks: 30 tests passed, 1223 assertions
Admission adjacent feature checks: 31 tests passed, 600 assertions
npm run test:finance: 57 tests passed, 438 assertions
npm run test:admission: 101 tests passed, 1093 assertions
```

Full release gates after the route snapshot was updated for the intentional Accounts scholarship route:

```text
Architecture route guard: passed with 1298 registered routes
php artisan test tests\Feature: 1773 tests passed, 29615 assertions
npm run frontend:build: passed
npm run frontend:smoke: 137 tests passed, 4008 assertions
npm run frontend:smoke:mobile: 29 tests passed, 1473 assertions
npm run test:production-readiness: 57 tests passed, 12464 assertions
```

Complete PHP suite and action-focused launch workflow pack were then run on commit `27c69d5`:

```text
php artisan test: 1800 tests passed, 29685 assertions
Admission lifecycle/payment/scholarship action pack: 56 tests passed, 431 assertions
PMC publish/timetable plus student-teacher attendance action pack: 34 tests passed, 343 assertions
Accounts fee/payment action pack: 81 tests passed, 529 assertions
```

Production-style Laravel cache and fresh-smoke gates on commit `edfd9b1`:

```text
php artisan config:cache: passed
php artisan route:cache: passed
php artisan view:cache: passed
php artisan optimize:clear: passed after cache verification
fresh isolated migrate:fresh --seed: passed
fresh seeded live smoke on http://127.0.0.1:8010: 16 critical pages passed with HTTP 200 and no service-error/debug text
```

Fresh seeded live smoke covered Exam Cell exam creation, unified approvals inbox, Admin dashboard, Accounts dashboard/admission payments/scholarship disbursements/reconciliation, Admission workbench/document queue/dashboard, PMC official timetable/command, Teacher attendance/timetable, and Student timetable/fees.

## Real-User Continuation Pass

Continuation pass on the same local server at `http://127.0.0.1:8000`:

```text
composer validate --strict: passed
composer audit: initially found 3 medium Guzzle advisories
composer update guzzlehttp/guzzle guzzlehttp/psr7 --with-dependencies: upgraded guzzlehttp/guzzle 7.11.0 -> 7.14.2, guzzlehttp/psr7 2.11.0 -> 2.12.5, guzzlehttp/promises 2.5.0 -> 2.5.1
composer audit after update: passed, no advisories
npm audit --audit-level=high: passed, 0 vulnerabilities
npm run frontend:build: passed
Focused PMC timetable regression: 18 tests passed, 155 assertions
npm run test:timetable: 126 tests passed, 1000 assertions
npm run test:production-readiness: 57 tests passed, 12464 assertions
npm run frontend:smoke: 137 tests passed, 4008 assertions
npm run frontend:smoke:mobile: 29 tests passed, 1473 assertions
npm run test:admission: 101 tests passed, 1093 assertions
npm run test:finance: 57 tests passed, 438 assertions
npm run test:portal: 51 tests passed, 439 assertions
```

Live authenticated real-user sweep covered 43 pages across Admin, PMC, Dean, Admission, Accounts, Exam Cell, Teacher, Student, Parent, and Admission Partner. All returned HTTP 200 with no service-error/debug text.

The smoke script initially checked `/approvals`, which is not a valid registered route. The real shared approval inbox is `/approvals/inbox`; it passed for Admin and Dean.

## Full Regression And Fresh Setup Recheck

After the Guzzle dependency patch, the broad backend and production-style gates were re-run:

```text
php artisan test: 1800 tests passed, 29685 assertions
php artisan config:cache: passed
php artisan route:cache: passed
php artisan view:cache: passed
php artisan optimize:clear: passed after cache verification
```

A disposable SQLite database was created at `database/codex_goal_fresh_smoke.sqlite`, then `migrate:fresh --seed --force` passed. Fresh seeded PMC canonical timetable integrity returned:

```text
official_items=3
missing_scope=0
missing_bridge_link=0
unscheduled_official=0
bridge_rows=3
parallel_slot_groups=1
```

A temporary fresh-data server on `http://127.0.0.1:8012` passed 12 authenticated critical page checks with HTTP 200 and no service-error/debug text:

```text
chair@college.com: /academics/pmc/official-timetable, /academics/pmc/command
admin@college.com: /admin/dashboard, /admin/timetable
anjali@demo.edu: /teacher/timetable, /teacher/attendance/mark
arjun.k@demo.edu: /student/timetable, /student/fees
accounts@college.com: /accounts/dashboard, /accounts/reconciliation
head@college.com: /admission/dashboard, /admission/documents/queue
```

## Final Blockers Fixed

- Restored faculty load review refresh by moving shared multi-slot/consecutive-slot calculations into `TimetableSlotMathService` and delegating from `PmcTimetableFacultyReadinessService`.
- Kept the student fee UI behavior that labels past-due pending hostel demands as `Overdue`, and aligned the stale regression expectation.
- Fixed the KPI component markup spacing issue that affected the admin operations KPI drilldown assertion.
- Fixed fresh PMC demo seed bridge integrity: published canonical sessions now create compatibility bridge rows during seeding, matching the reconciliation baseline and downstream attendance/reporting expectations.
- Fixed navigation-crawl blockers in Exam Cell, unified approvals, Admission document file actions, and Accounts finance links.
- Patched Composer dependency advisories in Guzzle packages and re-ran dependency, frontend, timetable, production-readiness, Admission, Finance, Portal, and live role smoke checks.

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
