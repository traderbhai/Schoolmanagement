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

## Action Workflow And Release Preflight Recheck

Action-oriented workflow chunks were re-run after the dependency patch and full regression pass:

```text
Admission/payment/scholarship workflow chunk: 54 tests passed, 415 assertions
PMC timetable/canonical attendance/student-teacher workflow chunk: 59 tests passed, 568 assertions
Accounts fee/payment workflow chunk: 92 tests passed, 616 assertions
```

Release preflight checks:

```text
composer check-platform-reqs: passed on PHP 8.5.7
php artisan migrate:status: all migrations ran on the local database
php artisan route:list --json: 1298 registered routes
Critical routes present: academics.pmc.command, academics.pmc.official-timetable.index, admin.dashboard, teacher.timetable.index, student.timetable, accounts.dashboard, admission.dashboard
```

## Remaining Module Workflow Recheck

Additional module chunks that were not part of the previous action pass:

```text
Exam/CoE/published-result reporting boundary chunk: 60 tests passed, 713 assertions
CMC/placement workflow chunk: 38 tests passed, 308 assertions
Library/hostel/transport operations chunk: 84 tests passed, 831 assertions
```

Authenticated HTTP module sweep covered the registered routes for Exam Cell, CMC, admin library/hostel/transport/exam, and student exam/placement/library/hostel/transport. The corrected sweep passed 24 pages with HTTP 200 and no service-error/debug text:

```text
exam@college.com: /exam-cell/dashboard, /exam-cell/exams, /exam-cell/exams/create, /exam-cell/results, /exam-cell/hall-tickets
cmc@college.com: /cmc/dashboard, /cmc/companies, /cmc/drives, /cmc/placements, /cmc/events, /cmc/internships
admin@college.com: /admin/library/books, /admin/library/issues, /admin/hostel, /admin/hostel/allocations, /admin/hostel/fees, /admin/transport, /admin/exams
arjun.k@demo.edu: /student/exam-registration, /student/placements, /student/library, /student/hostel/outpass, /student/hostel/complaints, /student/transport
```

## Launch Guard And Timetable Lifecycle Recheck

Active code/view/doc scan for `TODO`, `FIXME`, `not implemented`, `coming soon`, `known limitation`, `manual repair`, and unexpected `Service Error` markers found no active blocker outside the intentional 500 error page.

Guard and launch-critical suites:

```text
Architecture/frontend/route/shared-approval guard chunk: 49 tests passed, 13299 assertions
Official timetable publish/freeze/revision/canonical constraint chunk: 76 tests passed, 436 assertions
Global access/reporting/canonical admin boundary chunk: 20 tests passed, 124 assertions
```

## Fresh Seed Idempotency Recheck

A disposable SQLite database was created at `database/codex_seed_idempotency.sqlite` to test whether the demo setup is safe to rerun. The first `migrate:fresh --seed --force` passed, but a second `db:seed --force` exposed a real blocker in `LegacyCollegeDemoSeeder`: legacy attendance rows used `firstOrCreate` with a plain date string, while the model cast inserted the date as `YYYY-MM-DD 00:00:00`. On SQLite this failed to match the existing row and hit the unique key on `student_id`, `timetable_entry_id`, and `date`.

Fix applied:

- Legacy attendance demo rows now check existing records with `whereDate('date', $date)` before creating attendance.

Verification after the fix:

```text
fresh migrate/seed followed by second db:seed: passed
users=62
attendances=106
official_items=3
missing_scope=0
missing_bridge_link=0
duplicate_emails=0
duplicate_pmc_items=0
duplicate_attendance_keys=0
Demo credentials + seeded official timetable + attendance focused regression: 22 tests passed, 100 assertions
double-seeded temporary server smoke on http://127.0.0.1:8013: 9 critical pages passed with HTTP 200 and no service-error/debug text
```

Post-fix broad launch gates were then rerun:

```text
php artisan test: 1800 tests passed, 29685 assertions
npm run test:production-readiness: 57 tests passed, 12464 assertions
npm run frontend:build: passed
npm run frontend:smoke: 137 tests passed, 4008 assertions
npm run frontend:smoke:mobile: 29 tests passed, 1473 assertions
fresh migrate/seed plus second db:seed idempotency recheck: passed
double-seed integrity recheck: users=62, attendances=106, official_items=3, missing_scope=0, missing_bridge_link=0, duplicate_emails=0, duplicate_attendance_keys=0
```

## Portal Documents Assets Recheck

Additional portal and operations chunks:

```text
Applicant/parent portal workflow chunk: 46 tests passed, 437 assertions
Student document/transcript/admit-card/API workflow chunk: 47 tests passed, 320 assertions
Assets/inventory/admin operations chunk: 42 tests passed, 1333 assertions
Student results + portal ownership/frontend chunk: 27 tests passed, 808 assertions
```

Authenticated HTTP sweep covered 20 pages with HTTP 200 and no service-error/debug text:

```text
priya.sharma@applicant.demo: /applicant/dashboard, /applicant/checklist, /applicant/application, /applicant/documents, /applicant/fees, /applicant/status, /applicant/registration-fee, /applicant/notifications
parent@demo.edu: /parent/dashboard, /parent/children, /parent/notices
arjun.k@demo.edu: /student/documents, /student/documents/request, /student/admit-cards, /student/results, /student/transcript/download
admin@college.com: /admin/assets, /admin/document-requests, /admin/applicants, /admin/parents
```

## Public Auth Notification Recheck

Public access, auth/profile, notification, and status workflow chunks:

```text
Application/public/applicant notification/status chunk: 72 tests passed, 436 assertions
Notification/shared inbox/profile/promotion chunk: 21 tests passed, 143 assertions
Auth/profile/role redirect chunk: 94 tests passed, 290 assertions
```

Public and authenticated HTTP sweep covered 15 pages with HTTP 200 and no service-error/debug text:

```text
public: /, /apply, /login, /forgot-password
admin@college.com: /dashboard, /notifications, /profile
priya.sharma@applicant.demo: /applicant/status, /applicant/notifications, /notifications
arjun.k@demo.edu: /student/promotion-status, /student/notifications, /notifications
parent@demo.edu: /parent/dashboard, /notifications
```

## Leadership Academic OS Recheck

Leadership and academic operating-system workflow chunks:

```text
Leadership dashboard guidance + Dean KPI chunk: 38 tests passed, 230 assertions
Dean OS chunk: 31 tests passed, 326 assertions
Program Leadership + Course Delivery chunk: 32 tests passed, 399 assertions
IQAC/CoE/PMC frontend guidance chunk: 41 tests passed, 1056 assertions
```

Authenticated HTTP sweep covered 24 pages with HTTP 200 and no service-error/debug text:

```text
dean@college.com: /dean/dashboard, /academics/dean-os, /academics/dean-os/attention/critical_attention, /academics/dean-os/exam-readiness, /academics/course-delivery
director@college.com: /director/dashboard
hod@college.com: /hod/dashboard, /academics/program-leadership
chair@college.com: /program-chair/dashboard, /academics/pmc/command, /academics/pmc/official-timetable, /academics/program-leadership, /academics/course-delivery
exam@college.com: /exam-cell/dashboard, /academics/coe, /academics/coe/exam-readiness, /academics/coe/marks-results, /academics/coe/hall-ticket-readiness
iqac.head@college.com: /academics/iqac, /academics/iqac/obe-readiness, /academics/iqac/attainment-monitoring, /academics/iqac/feedback-quality, /academics/iqac/audit-compliance, /academics/iqac/reports
```

## Admission Lifecycle Recheck

Representative admission lifecycle chunk:

```text
Admission applicant readiness/UX, flow, document verification, payment verification, merit decision, seat matrix, waitlist promotion, refund, and reporting scope: 92 tests passed, 889 assertions
```

Authenticated HTTP sweep covered 31 pages with HTTP 200 and no service-error/debug text:

```text
head@college.com: /admission/dashboard, /admission/command-center, /admission/workbench, /admission/applicants, /admission/applicants/6, /admission/documents/queue, /admission/payments/queue, /admission/merit-list/1, /admission/offer-seat-control, /admission/reports, /admission/call-queue, /admission/counsellor-performance
officer@college.com: /admission/dashboard, /admission/applicants, /admission/documents/queue, /admission/payments/queue, /admission/reports
counsellor@college.com: /admission/counsellor-desk, /admission/counsellor-workspace, /admission/counsellor-playbooks, /admission/applicants
telecaller@college.com: /admission/calling-desk, /admission/call-queue, /admission/leads
priya.sharma@applicant.demo: /applicant/dashboard, /applicant/admission-operations, /applicant/checklist, /applicant/application, /applicant/documents, /applicant/fees, /applicant/status
```

## Teacher Student Timetable Attendance Recheck

Teacher/student timetable, attendance, course, result, and ownership workflow chunk:

```text
Student/teacher canonical attendance, student timetable, course content, academic summary, results, teacher dashboard/student list/scope/profile, and portal ownership: 100 tests passed, 782 assertions
```

Authenticated HTTP sweep covered 29 valid pages with HTTP 200 and no service-error/debug text:

```text
ravi@college.com: /teacher/dashboard, /teacher/timetable, /teacher/pmc-timetable, /teacher/pmc-availability, /teacher/attendance/mark, /teacher/students, /teacher/materials, /teacher/assignments, /teacher/exams, /teacher/mentor, /teacher/profile
aarav@college.com: /student/dashboard, /student/timetable, /student/pmc-timetable, /student/attendance, /student/courses, /student/courses/2, /student/courses/2/materials, /student/courses/2/announcements, /student/academic-summary, /student/results, /student/exam-registration, /student/fees, /student/documents, /student/profile, /student/mentor, /student/pmc-course-basket, /student/pmc-elective-choices
```

The attempted `/student/courses/24` page returned HTTP 403 for `aarav@college.com`; this was the correct ownership guard because that subject was not linked from Aarav's course hub. Valid linked course pages used subject `2`.

## Accounts Fees Recheck

Accounts, fee demand, payment, hostel fee, admission receipt, and payment-verification workflow chunk:

```text
Accounts dashboard guidance, fee demand/payment, hostel fee workflow, admission fee receipt branding, and admission payment verification: 110 tests passed, 728 assertions
```

Authenticated HTTP sweep covered 10 pages with HTTP 200 and no service-error/debug text:

```text
accounts@college.com: /accounts/dashboard, /accounts/fee-collections, /accounts/outstanding, /accounts/reconciliation, /accounts/reports, /accounts/admission-payments, /accounts/scholarship-disbursements
aarav@college.com: /student/fees, /student/fee-payment, /student/fee-payment/create
```

## CMC Placement Recheck

CMC, placement, career event, resume, internship, and alumni workflow chunk:

```text
CMC dashboard guidance, placement lifecycle, student placement guidance, student career events, student resume, internship guidance, and alumni guidance: 78 tests passed, 570 assertions
```

Authenticated HTTP sweep covered 21 pages with HTTP 200 and no service-error/debug text:

```text
cmc@college.com: /cmc/dashboard, /cmc/companies, /cmc/companies/create, /cmc/companies/1/edit, /cmc/drives, /cmc/drives/create, /cmc/drives/1/applications, /cmc/placements, /cmc/placement-stats, /cmc/internships, /cmc/internships/1, /cmc/alumni, /cmc/events, /cmc/events/create, /cmc/analytics
aarav@college.com: /student/placements, /student/placements/my-applications, /student/resume, /student/internships, /student/career-events, /student/alumni
```

## Campus Admin Operations Recheck

Admin operations, campus services, access control, and student service workflow chunk:

```text
Admin operations frontend/KPI/UX, admin record lifecycle, asset workflow/access, hostel guidance/access, library circulation/access, transport workflow/access, official report/global export access, and student document requests: 164 tests passed, 2437 assertions
```

Authenticated HTTP sweep covered 28 pages with HTTP 200 and no service-error/debug text:

```text
admin@college.com: /admin/dashboard, /admin/assets, /admin/library, /admin/library/books, /admin/library/issues, /admin/library/memberships, /admin/library/reservations, /admin/library/fines, /admin/transport, /admin/document-requests, /admin/students, /admin/teachers, /admin/attendance, /admin/attendance/report, /admin/enrollments, /admin/exams, /admin/fees, /admin/fee-payment-requests, /admin/hostel/fees, /admin/audit-log
aarav@college.com: /student/documents, /student/documents/request, /student/grievances, /student/grievances/create, /student/hostel/complaints, /student/hostel/outpass, /student/library, /student/transport
```

## Aggregate Readiness Recheck

Current committed state was rechecked with the broad readiness gates:

```text
npm run test:production-readiness: 57 tests passed, 12464 assertions
npm audit --audit-level=high: 0 vulnerabilities
PHPRC=C:\tmp\php-8.5.7-codex-ini C:\tmp\php-8.5.7\php.exe C:\composer\composer.phar audit: no security vulnerability advisories found
npm run frontend:build: passed
npm run frontend:smoke: 137 tests passed, 4008 assertions
npm run frontend:smoke:mobile: 29 tests passed, 1473 assertions
PHPRC=C:\tmp\php-8.5.7-codex-ini C:\tmp\php-8.5.7\php.exe artisan test: 1800 tests passed, 29685 assertions
```

## Manual Form Submission Recheck

Low-risk form submissions were exercised against the running local app with seeded demo users. This pass caught and fixed a real seeded-data blocker:

```text
Initial student grievance POST as aarav@college.com failed with HTTP 500 because legacy demo students had no canonical program_id, while student_grievances.program_id is required.
LegacyCollegeDemoSeeder now creates canonical B.Tech programs, batches, and current terms for legacy courses, and backfills legacy subjects, timetable rows, and students with program/batch/term scope.
Regression added: seeded aarav@college.com can submit a student grievance with non-null program_id.
```

Post-fix manual submissions:

```text
aarav@college.com: POST /student/grievances created open grievance id=1 with program_id=3
aarav@college.com: POST /student/documents created pending migration document request id=1
aarav@college.com: /student/fee-payment/create had no outstanding demand option, so fee proof submission was skipped rather than fabricating a payment state
cmc@college.com: POST /cmc/companies created active company id=3
cmc@college.com: POST /cmc/events created published workshop event id=1
head@college.com: POST /admission/applicants/6/notes created admission team note id=1
```

Focused verification:

```text
LegacyCollegeDemoSeeder rerun on local database: passed
Legacy demo scope integrity after rerun: legacy_students_missing_scope=0, legacy_timetable_missing_scope=0, legacy_subjects_missing_scope=0
Temporary fresh SQLite migrate:fresh --seed: passed
Fresh SQLite legacy scope integrity: legacy_students_missing_scope=0, legacy_timetable_missing_scope=0, legacy_subjects_missing_scope=0, aarav_program_id=1
GrievanceWorkflowGuidanceTest, DemoCredentialsTest, PortalFrontendBetaReadinessTest, StudentDashboardGuidanceTest: 51 tests passed, 912 assertions
```

## Teacher Operations Manual Recheck

Low-risk teacher operational submissions were exercised against the running local app:

```text
ravi@college.com: POST /teacher/pmc-availability created submitted availability request id=3 with term_id=7, max/day=4, max/week=18, max consecutive=3
ravi@college.com: POST /teacher/attendance/store for timetable_entry_id=1 on 2026-07-13 saved 5 present attendance rows, all marked_by=2
```

Focused verification:

```text
StudentTeacherAttendanceCanonicalWorkflowTest, AttendanceWorkflowTest, AttendanceFeatureTest, TeacherScopeWorkflowTest, TeacherDashboardGuidanceTest, TeacherStudentListTest, TeacherProfileMissingGracefulTest: 62 tests passed, 515 assertions
```

## Final Blockers Fixed

- Restored faculty load review refresh by moving shared multi-slot/consecutive-slot calculations into `TimetableSlotMathService` and delegating from `PmcTimetableFacultyReadinessService`.
- Kept the student fee UI behavior that labels past-due pending hostel demands as `Overdue`, and aligned the stale regression expectation.
- Fixed the KPI component markup spacing issue that affected the admin operations KPI drilldown assertion.
- Fixed fresh PMC demo seed bridge integrity: published canonical sessions now create compatibility bridge rows during seeding, matching the reconciliation baseline and downstream attendance/reporting expectations.
- Fixed navigation-crawl blockers in Exam Cell, unified approvals, Admission document file actions, and Accounts finance links.
- Patched Composer dependency advisories in Guzzle packages and re-ran dependency, frontend, timetable, production-readiness, Admission, Finance, Portal, and live role smoke checks.
- Fixed legacy demo attendance seeding so rerunning `db:seed` on an existing demo database does not create duplicate attendance keys or crash.
- Fixed legacy demo canonical scope seeding so legacy student services can create program-scoped records such as grievances without database exceptions.

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
