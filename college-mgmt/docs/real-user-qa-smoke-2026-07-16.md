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

## Public Applicant Apply Recheck

Manual public applicant testing caught two launch blockers:

```text
The local demo had no open application_windows row, so /apply could render but a real public applicant could not register for any program.
DemoAdmissionConfigSeeder now creates an idempotent open PGDM application window tied to the active PGDM batch with dynamic open/close dates.
Applicant personal-section saves were dropping entrance_exam_type and related direct metadata because Applicant::$fillable did not include all category/admission metadata columns.
Applicant::$fillable now includes category_certificate_verified, pwd_percentage, domicile_state, is_state_quota, entrance_exam_type, and entrance_exam_year.
```

Manual public flow:

```text
DemoAdmissionConfigSeeder rerun on local database: open PGDM window id=1, batch_id=1, capacity_limit=500, current_applications=0
POST /apply/1 created qa-public-20260716221509@example.test, applicant id=7, status=draft, then all four application sections were saved and final POST /applicant/application/submit moved status to submitted.
POST /apply/1 created qa-public-meta-20260716221812@example.test, applicant id=8, then draft personal-section save persisted category=general, domicile_state=Delhi, entrance_exam_type=cat, entrance_exam_score=91, entrance_exam_roll_number=QA123, entrance_exam_year=2025.
```

Focused verification:

```text
LaunchRouteSmokeTest, ApplicationWindowTest, AdmissionApplicantReadinessTest: 50 tests passed, 299 assertions
Temporary fresh SQLite migrate:fresh --seed: passed
Fresh SQLite public apply readiness: open_application_windows=1, program=PGDM, capacity_limit=500
```

## Applicant Follow-Up Workflow Recheck

Manual applicant-side follow-up submissions were exercised against the running local app after public application creation:

```text
qa-public-meta-20260716221812@example.test: POST /applicant/registration-fee saved registration_fee_receipt=QA-REG-20260716222426 and registration_fee_amount=10000 while the application was still draft.
qa-public-meta-20260716221812@example.test: POST /applicant/documents/4 uploaded Graduation Marksheet (all semesters) as applicant document id=29, status=pending, version=1, path=applicant-documents/8/graduation-marksheet-all-semesters_v1_1784220866.pdf.
sneha.patel@applicant.demo: POST /applicant/fees/2 submitted First Semester Fee proof as admission payment id=3, amount=140000, status=pending, transaction_reference=QA-FEE-20260716222457, proof=payment_proofs/saCKAZuOYABahKTqek0EIi2ih7FNcALB12Yfe9gU.pdf.
```

Observed behavior:

```text
Draft applicants can save registration-fee details and upload required documents.
Submitted applicants can still manage documents until a final selected/rejected/withdrawn/enrolled state.
Admission-fee proof remains correctly gated to shortlisted/selected applicants.
No blocker was found in this pass.
```

## Applicant Admission Operations Recheck

Manual shortlisted-applicant operations were exercised against the running local app:

```text
sneha.patel@applicant.demo: GET /applicant/admission-operations loaded successfully with existing assessment slot assignment id=3.
sneha.patel@applicant.demo: POST /applicant/admission-operations/consent saved admission_consent_records id=5, channel=whatsapp, status=opt_out, reason=QA smoke preference.
sneha.patel@applicant.demo: POST /applicant/admission-operations/reschedule saved admission_assessment_reschedule_requests id=2, slot_assignment_id=3, status=pending, reason=QA smoke reschedule request.
```

Observed behavior:

```text
Shortlisted applicants can view admission operations, update communication consent, and request assessment rescheduling.
The reschedule path correctly validates ownership of the slot assignment before creating the request.
No blocker was found in this pass.
```

## Applicant Offer Letter Recheck

Manual applicant offer-letter actions were exercised against disposable local offers:

```text
Initial live check found a real browser-form bug: applicant offer accept/decline forms posted successfully, but the controller returned application/json instead of redirecting back to an HTML page.
Applicant OfferLetterController now returns JSON only for requests that expect JSON; normal portal form posts redirect back with flash success/error messages.
qa-public-20260716221509@example.test: GET /applicant/offer-letters loaded issued offer id=3, POST /applicant/offer-letters/3/accept returned HTML at /applicant/offer-letters with the success message instead of raw JSON.
rahul.verma@applicant.demo: GET /applicant/offer-letters loaded issued offer id=4, POST /applicant/offer-letters/4/decline returned HTML at /applicant/offer-letters with the success message instead of raw JSON.
qa-public-20260716221509@example.test: GET /applicant/offer-letters/3/pdf returned application/pdf, 5734 bytes, header=%PDF-1.7.
Offer id=3 persisted status=accepted and applicant status=selected.
Offer id=4 persisted status=declined, declined_reason=QA smoke decline after redirect fix, and applicant status=rejected.
```

Focused verification:

```text
OfferLetterTest: 23 tests passed, 122 assertions
The regression now covers browser form redirects and preserves JSON responses for JSON callers.
```

## Admission Enrollment And Handoff Recheck

Manual admission enrollment was exercised against selected local applicants with verified prerequisite records:

```text
Initial live check enrolled qa-public-20260716221509@example.test and created student id=23, confirmation id=1, and handoff id=5, but exposed a lifecycle bug: the linked applicant kept status=selected even after the user role changed from applicant to student.
EnrollmentService now marks the applicant status as enrolled inside the same transaction that creates the student and enrollment confirmation.
meenakshi.rao@applicant.demo: GET /admission/enrollment/5/create loaded successfully for officer@college.com.
meenakshi.rao@applicant.demo: POST /admission/enrollment/5 created enrollment confirmation id=2, enrollment_number=ENR-2026-PGDM-00005, roll=QA-ENR2-223722, student id=24.
Applicant id=5 persisted status=enrolled and user role=student.
Admission handoff id=6 persisted status=ready_for_academics, student_id=24.
GET /admission/enrollment/confirmation/2/letter returned application/pdf, 1272562 bytes, header=%PDF-1.7.
```

Focused verification:

```text
AdmissionFlowTest: 22 tests passed, 181 assertions
The regression now proves completed enrollment updates applicant status to enrolled.
```

## Converted Student Portal Recheck

Manual post-enrollment student checks were exercised with `meenakshi.rao@applicant.demo`, whose applicant record was enrolled into student id=24:

```text
Login redirected to /student/dashboard with role=student, applicant status=enrolled, student status=active.
GET /student/dashboard, /student/profile, /student/timetable, /student/pmc-timetable, /student/courses, /student/attendance, /student/fees, /student/documents, /student/notifications, and /student/academic-summary all returned clean HTML without SERVICE ERROR/Whoops output.
POST /student/documents created document request id=2, type=bonafide, status=pending, purpose=QA enrollment smoke document 224139.
POST /student/grievances created grievance id=2, category=academic, status=open, program_id=1, title=QA enrolled student grievance 224139.
POST /student/notifications with Laravel method spoofing saved notification preference id=4, email_application_updates=1, email_notices=1.
```

Operational repair:

```text
Added admission:repair-enrollment-status with dry-run default and explicit --apply mode.
Local dry run found one pre-fix completed enrollment mismatch for qa-public-20260716221509@example.test.
Local apply repaired 1 applicant status record; remaining completed-enrollment/applicant-status mismatches=0.
```

Focused verification:

```text
AdmissionFlowTest: 23 tests passed, 190 assertions
Student dashboard/document/grievance/notification/timetable/attendance suite: 52 tests passed, 373 assertions
```

## Admission To Academics Handoff Recheck

Manual Admission and Dean handoff checks were exercised against the handoff records created by enrollment:

```text
head@college.com: GET /admission/handoff?status=ready_for_academics loaded ready handoff records, including Meenakshi's handoff.
head@college.com: POST /admission/handoff/5/refresh returned to the ready-for-academics queue successfully.
head@college.com: POST /admission/handoff/6/mark-handed-off returned success and persisted handoff id=6 as status=handed_off, handed_off_by=16, student_id=24.
admission_sensitive_audit_events has an admission_handoff_completed audit row for actor_user_id=16.
dean@college.com: GET /academics/dean-os/handoff returned clean HTML with Admission To Academics Handoff and no SERVICE ERROR/Whoops output.
```

Focused verification:

```text
Admission handoff filtered tests: 3 tests passed, 22 assertions
Academics Dean handoff filtered test: 1 test passed, 9 assertions
```

## Converted Student Fees And Accounts Recheck

Manual fee-demand, payment-proof, Accounts verification, and receipt checks were exercised against enrolled student id=24:

```text
Created fee demand id=1 for meenakshi.rao@applicant.demo, student id=24, term_id=1, final_amount=25000, status=pending.
meenakshi.rao@applicant.demo: GET /student/fees returned clean HTML and showed the 25000 demand.
meenakshi.rao@applicant.demo: GET /student/fee-payment/create returned clean HTML.
meenakshi.rao@applicant.demo: POST /student/fee-payment submitted proof request id=1, demand_id=1, amount=25000, transaction_ref=QA-STU-FEE-20260716225223, proof_path=fee-proofs/24/8CtvdzbgbHoLq8oVPwSROpT8eIliPpjxD4zivVMm.pdf.
accounts@college.com: GET /admin/fee-payment-requests?status=pending returned clean HTML and showed the submitted reference.
accounts@college.com: GET /admin/fee-payment-requests/1/proof downloaded the uploaded proof, 68 bytes, application/octet-stream.
accounts@college.com: PATCH /admin/fee-payment-requests/1/verify verified the proof.
Fee payment request id=1 persisted status=verified, verified_by=43.
Fee demand id=1 persisted status=fully_paid, final_amount=0, penalty_amount=0.
Fee payment id=17 persisted status=paid, amount=25000, receipt_number=RCP-6A59137D57125.
meenakshi.rao@applicant.demo: GET /student/reports/fee-receipt/17 returned application/pdf, 885999 bytes, header=%PDF-1.7.
```

Focused verification:

```text
FeePaymentTest and AccountsDashboardGuidanceTest filtered fee/payment/receipt/accounts checks: 27 tests passed, 194 assertions
```

## Converted Student Placement And CMC Recheck

Manual placement drive, student application, CMC status update, and export checks were exercised against enrolled student id=24:

```text
Existing demo placement drives were status-visible but had 2025 application deadlines, so a real student could not apply in the 2026 local test window.
cmc@college.com: GET /cmc/drives/create loaded successfully.
cmc@college.com: POST /cmc/drives created placement drive id=3, title=QA Converted Student Drive 225631, status=upcoming, min_cgpa=0, last_apply_date=2026-08-01.
meenakshi.rao@applicant.demo: GET /student/placements returned clean HTML and showed the QA drive.
meenakshi.rao@applicant.demo: POST /student/placements/3/apply created placement application id=4, status=applied.
meenakshi.rao@applicant.demo: GET /student/placements/my-applications returned clean HTML and showed the QA drive.
cmc@college.com: GET /cmc/drives/3/applications showed the converted student's application.
cmc@college.com: PATCH /cmc/placements/4/status updated the application to shortlisted.
cmc@college.com: GET /cmc/drives/3/applications/export returned CSV containing ENR-2026-PGDM-00005.
cmc@college.com: PATCH /cmc/placements/4/status updated the application to selected with offered_package=650000.
cmc@college.com: GET /cmc/placements returned clean HTML showing the student, drive title, and INR 650,000 package.
cmc@college.com: GET /cmc/placements/export returned CSV containing ENR-2026-PGDM-00005, QA Converted Student Drive 225631, and 650000.
```

Fix applied:

```text
Cleaned CMC placed-students view to remove mojibake fallback characters, show both drive title and company, and format offered package as INR amount.
```

Focused verification:

```text
StudentPlacementGuidanceTest, PlacementLifecycleIntegrityTest, and CmcDashboardGuidanceTest filtered placement checks: 33 tests passed, 257 assertions
```

## Exam Registration, Review, And Hall-Ticket Recheck

Manual Exam Cell and student exam-registration checks were exercised against the running local server:

```text
Initial finding: demo academic years, semesters, and terms were stale for 2026 local testing. Exam Cell could open /exam-cell/exams/create, but POST /exam-cell/exams rejected a realistic 2026 exam with "Exam date must fall within the selected semester window."
Fix applied: DemoDataSeeder now creates a rolling current academic session; LegacyCollegeDemoSeeder no longer marks the legacy engineering semester current; demo:repair-academic-calendar repairs existing local databases.
Ran demo:repair-academic-calendar locally, producing academic session 2026-27 with Semester I from 2026-07-01 to 2026-11-30 and PGDM term 1 current.
exam@college.com: POST /exam-cell/exams created exam id=17, title=QA Live Exam Registration 230803, subject_id=18, term_id=1, semester_id=4, exam_date=2026-08-10.
arjun.k@demo.edu: GET /student/exam-registration returned clean HTML and showed the QA exam.
arjun.k@demo.edu: POST /student/exam-registration/17/register created exam registration id=3, status=pending, attendance_eligible=true, fee_cleared=true.
exam@college.com: GET /exam-cell/hall-tickets?exam_id=17 showed the pending registration under Registration Review.
exam@college.com: PATCH /exam-cell/registrations/3 approved the registration with remarks.
exam@college.com: GET /exam-cell/hall-tickets?exam_id=17 showed the registration as approved and hall-ticket ready.
exam@college.com: GET /exam-cell/hall-tickets/17/11/download returned application/pdf.
arjun.k@demo.edu: GET /student/admit-cards showed the QA exam and download action.
arjun.k@demo.edu: GET /student/admit-cards/17/download returned application/pdf.
```

Fix applied:

```text
Added Exam Cell registration review from the hall-ticket screen so submitted student exam registrations can be approved or rejected before PDF generation.
Cleaned hall-ticket page mojibake and preserved the approved-only PDF gate.
```

Focused verification:

```text
StudentExamRegistrationWorkflowTest and ExamCellDashboardGuidanceTest: 28 tests passed, 287 assertions
```

## Career Event Registration And CMC Attendance Recheck

Manual CMC career-event and student registration checks were exercised against the running local server:

```text
cmc@college.com: POST /cmc/events created published event id=2, title=QA Career Event 231449, event_date=2026-07-16, seats=2.
arjun.k@demo.edu: GET /student/career-events returned clean HTML and showed the QA event.
arjun.k@demo.edu: POST /student/career-events/2/register registered the student for the event.
cmc@college.com: GET /cmc/events/2/registrations showed Arjun Kapoor with registered status.
cmc@college.com: PATCH /cmc/events/2/registrations/{registration}/attendance marked the student attended.
cmc@college.com: GET /cmc/events/2/registrations/export returned CSV containing Arjun Kapoor and attended=Yes.
cmc@college.com: GET /cmc/events returned clean HTML with the QA event and no mojibake fallback text.
```

Fix applied:

```text
Cleaned CMC career event list fallbacks so venue and open-seat values render as readable text without mojibake.
```

Focused verification:

```text
StudentCareerEventWorkflowTest and CmcDashboardGuidanceTest filtered career-event checks: 24 tests passed, 141 assertions
```

## Internship Lifecycle Recheck

Manual CMC internship and student visibility checks were exercised against the running local server:

```text
cmc@college.com: GET /cmc/internships/create loaded successfully.
cmc@college.com: POST /cmc/internships created internship id=2, role=QA Internship Analyst 231845, student_id=11, company=InfoSys Ltd, status=ongoing.
cmc@college.com: GET /cmc/internships showed the QA internship.
cmc@college.com: GET /cmc/internships/2 showed the role and supervisor details.
arjun.k@demo.edu: GET /student/internships showed the QA internship and "Internship currently in progress" priority.
cmc@college.com: POST /cmc/internships/2/complete marked the internship completed with feedback and rating=5.
arjun.k@demo.edu: GET /student/internships showed "Internship record completed", QA completion feedback, and rating 5/5.
```

Focused verification:

```text
InternshipWorkflowGuidanceTest: 8 tests passed, 58 assertions
```

## Alumni Network Recheck

Manual CMC alumni profile and student alumni-network checks were exercised against the running local server:

```text
cmc@college.com: GET /cmc/alumni/create loaded successfully.
cmc@college.com: POST /cmc/alumni created alumni profile id=1 for Sneha Reddy, employer=QA Alumni Employer, role=Product Manager.
cmc@college.com: GET /cmc/alumni showed Sneha Reddy and QA Alumni Employer.
arjun.k@demo.edu: GET /student/alumni did not show the unverified QA alumni profile.
cmc@college.com: POST /cmc/alumni/1/verify marked the profile verified.
arjun.k@demo.edu: GET /student/alumni showed Sneha Reddy, QA Alumni Employer, and Product Manager.
```

Focused verification:

```text
AlumniWorkflowGuidanceTest: 4 tests passed, 32 assertions
```

## Transport Assignment Recheck

Manual admin transport setup, assignment, student visibility, export, and end-assignment checks were exercised against the running local server:

```text
admin@demo.edu: POST /admin/transport/routes created route QA Route 232215.
admin@demo.edu: POST /admin/transport/stops created stop QA Stop.
admin@demo.edu: POST /admin/transport/vehicles created vehicle QA-232215.
admin@demo.edu: POST /admin/transport/assignments assigned Vikram Singh to the QA route, stop, and vehicle.
admin@demo.edu: GET /admin/transport?assignment_search=Vikram showed Vikram Singh, QA Route 232215, and QA Stop.
admin@demo.edu: GET /admin/transport/assignments/export?assignment_search=Vikram returned CSV containing Vikram Singh and QA Route 232215.
vikram.s@demo.edu: GET /student/transport showed the active assignment and notes.
admin@demo.edu: POST /admin/transport/assignments/{assignment}/end ended the assignment.
vikram.s@demo.edu: GET /student/transport showed no active assignment and retained the QA route in transport history.
```

Focused verification:

```text
TransportWorkflowTest: 18 tests passed, 119 assertions
```

## Library Circulation And Reservation Recheck

Manual admin library circulation, student library visibility, reservation, return, and fulfillment checks were exercised against the running local server:

```text
admin@demo.edu: POST /admin/library/books created book QA Library Book 232339 with one copy.
admin@demo.edu: POST /admin/library/issue issued the copy to Pooja Mehta with due date 2026-07-30.
pooja.m@demo.edu: GET /student/library showed QA Library Book 232339 under currently borrowed with Issued status.
vikram.s@demo.edu: GET /student/library showed the unavailable QA title with a Reserve action.
vikram.s@demo.edu: POST /student/library/reservations created a pending reservation for the QA title.
admin@demo.edu: GET /admin/library/reservations?search=QAISBN232339 showed Vikram Singh's reservation.
admin@demo.edu: POST /admin/library/issues/{issue}/return returned Pooja's copy.
pooja.m@demo.edu: GET /student/library showed no current borrowing and retained the QA title in borrowing history.
admin@demo.edu: POST /admin/library/reservations/{reservation}/fulfill fulfilled Vikram's reservation and issued the copy.
vikram.s@demo.edu: GET /student/library showed QA Library Book 232339 under currently borrowed with Issued status.
```

Focused verification:

```text
LibraryCirculationWorkflowTest and AdminLibraryAccessControlTest: 32 tests passed, 332 assertions
```

## CMC, Portal, Admin, Library, Transport Regression Chunk

After the placement, career-event, internship, alumni, transport, and library live checks, the adjacent focused regression chunk passed:

```text
CmcDashboardGuidanceTest
PlacementLifecycleIntegrityTest
StudentCareerEventWorkflowTest
InternshipWorkflowGuidanceTest
AlumniWorkflowGuidanceTest
TransportWorkflowTest
LibraryCirculationWorkflowTest
AdminLibraryAccessControlTest
PortalFrontendBetaReadinessTest
AdminOperationsFrontendBetaReadinessTest

Result: 140 tests passed, 2436 assertions
```

## Hostel Allocation, Fees, Outpass, And Complaint Recheck

Manual admin hostel setup and student hostel checks were exercised against the running local server:

```text
admin@demo.edu: POST /admin/hostel/blocks created QA Hostel Block 232919.
admin@demo.edu: POST /admin/hostel/blocks/{block}/rooms created room QA-232919.
admin@demo.edu: POST /admin/hostel/allocations allocated Neha Patel to the QA room.
admin@demo.edu: GET /admin/hostel/allocations?search=Neha showed Neha Patel and the QA block.
admin@demo.edu: POST /admin/hostel/fees/generate created a pending July 2026 hostel fee demand for Neha.
neha.p@demo.edu: POST /student/hostel/outpass submitted a future outpass request.
neha.p@demo.edu: POST /student/hostel/complaints submitted a maintenance complaint.
admin@demo.edu: GET /admin/hostel/outpasses?status=pending showed Neha's outpass.
admin@demo.edu: POST /admin/hostel/outpasses/{outpass}/approve approved the outpass.
admin@demo.edu: GET /admin/hostel/complaints?status=open&priority=high showed the complaint.
admin@demo.edu: PUT /admin/hostel/complaints/{complaint} resolved the complaint with notes.
neha.p@demo.edu: GET /student/hostel/outpass showed the approved status.
neha.p@demo.edu: GET /student/hostel/complaints showed the resolved complaint.
```

Focused verification:

```text
HostelWorkflowGuidanceTest, HostelFeeWorkflowTest, and AdminHostelAccessControlTest: 39 tests passed, 387 assertions
```

## Grievance, Leave, And Condonation Recheck

Manual student, admin, and program-office lifecycle checks were exercised against the running local server:

```text
aarav@college.com: POST /student/grievances submitted QA live grievance 233416.
aarav@college.com: POST /student/leave submitted QA live leave 233416.
aarav@college.com: POST /student/condonation submitted QA live condonation 233416 for an eligible low-attendance subject.
admin@college.com: POST /admin/grievances/3 with _method=PATCH resolved and assigned the grievance.
admin@college.com: POST /admin/leaves/8/approve with _method=PATCH approved the student leave.
admin@college.com: POST /program-chair/students/condonations/1/approve approved the condonation through the program-office review route.
aarav@college.com: POST /student/grievances/3/close closed the resolved grievance.
aarav@college.com: GET /student/grievances/3 showed Closed status and the resolution notes.
aarav@college.com: GET /student/leave showed the approved leave and original reason.
aarav@college.com: GET /student/condonation showed the approved condonation and original reason.
```

Focused verification:

```text
GrievanceWorkflowGuidanceTest and StudentLeaveWorkflowTest: 32 tests passed, 237 assertions
```

Seed-data note:

```text
arjun.k@demo.edu had low attendance in subject 19, but no active enrollment for that subject, so the condonation form correctly rejected the request.
The valid seeded condonation path used aarav@college.com, whose low-attendance subject and active enrollment align.
```

## Coursework, Mentoring, Feedback, Resume, And Scholarship Recheck

Manual teacher, student, program-office, admission, and admin checks were exercised against the running local server:

```text
admin@college.com: POST /program-chair/students/mentors/assign assigned student 11 to active teacher 5.
pmc.faculty@college.com: POST /teacher/materials created QA fixed material 234501 for subject 18.
pmc.faculty@college.com: POST /teacher/assignments created QA fixed assignment 234501 for subject 18.
arjun.k@demo.edu: GET /student/courses/18/materials showed QA fixed material 234501.
arjun.k@demo.edu: POST /student/assignments/3/submit submitted QA fixed assignment answer 234501.
pmc.faculty@college.com: POST /teacher/assignments/submissions/1/grade graded the submission with 22/25 and QA feedback.
arjun.k@demo.edu: GET /student/assignments/3 showed Graded status and QA fixed grade feedback 234501.
arjun.k@demo.edu: POST /student/mentor/message sent QA fixed mentor message 234501.
arjun.k@demo.edu: POST /student/mentor/meeting scheduled QA fixed mentor meeting 234501.
pmc.faculty@college.com: POST /teacher/mentor/11/message sent QA fixed mentor reply 234501.
arjun.k@demo.edu: GET /student/mentor showed both student and mentor messages.
arjun.k@demo.edu: POST /student/feedback/22 submitted QA fixed course feedback 234501.
arjun.k@demo.edu: POST /student/resume saved QA fixed resume headline 234501 with skills and project details.
admin@college.com: POST /admission/scholarship-schemes created QA live scholarship scheme 234659.
arjun.k@demo.edu: POST /student/scholarships/1/apply submitted the student scholarship application.
admin@college.com: POST /admin/student-scholarships/1/shortlist with _method=PATCH shortlisted the application.
admin@college.com: POST /admin/student-scholarships/1/approve with _method=PATCH approved Rs. 12,000.
admin@college.com: POST /admin/student-scholarships/1/disburse with _method=PATCH disbursed with ref QA-UTR-234659.
```

Bug found and fixed:

```text
Teacher-created assignments and study materials were using the globally latest term instead of the official teaching term for the selected subject.
That caused enrolled students to receive 403 on a newly created assignment when their enrollment term did not match the global latest term.
The fix adds officialTeachingTermIdForSubject() to UsesOfficialTeachingSubjects and uses it in Teacher\AssignmentController and Teacher\MaterialController.
Regression added: TeacherScopeWorkflowTest::test_teacher_assignment_uses_official_teaching_term_for_student_visibility.
```

Focused verification:

```text
TeacherScopeWorkflowTest official-term regression: 1 test passed, 8 assertions.
TeacherScopeWorkflowTest, StudentCourseContentAccessTest, StudentMentorWorkflowTest, StudentCourseFeedbackWorkflowTest, and StudentResumeWorkflowTest: 74 tests passed, 577 assertions.
ScholarshipTest, StudentScholarshipWorkflowGuidanceTest, and AdmissionApplicantScholarshipWorkflowTest: 50 tests passed, 320 assertions.
```

Quiz note:

```text
The previous quiz gap was closed by adding teacher quiz authoring routes, controller, views, and navigation.
pmc.faculty@college.com: POST /teacher/quizzes created QA live quiz 000406 for subject 18.
arjun.k@demo.edu: GET /student/quizzes showed QA live quiz 000406.
arjun.k@demo.edu: POST /student/quizzes/1/start opened the quiz attempt.
arjun.k@demo.edu: POST /student/quizzes/1/submit submitted the correct option.
arjun.k@demo.edu: GET /student/quizzes/1/result showed QA live quiz 000406 with score 1 / 1.
Focused verification: TeacherScopeWorkflowTest and StudentCourseContentAccessTest passed with 60 tests and 469 assertions.
```

## Accounts Fee Demand And Payment Proof Recheck

Manual academic, student, admin, and accounts checks were exercised against the running local server:

```text
admin@college.com: GET /academic/fee-demands/create showed active student labels with enrollment number and name after the selector fix.
admin@college.com: POST /academic/fee-demands created a Semester II fee demand for arjun.k@demo.edu.
arjun.k@demo.edu: GET /student/fees showed the new academic fee balance.
arjun.k@demo.edu: POST /student/fee-payment submitted general payment proof QA-FEE-20260717001610.
admin@college.com: PATCH /admin/fee-payment-requests/2/verify verified the proof and created receipt RCP-6A5926F453C84.
Database check: the general verified proof reduced Arjun Kapoor's open demand to fully_paid.
admin@college.com: POST /academic/fee-demands created a Semester III fee demand for arjun.k@demo.edu.
arjun.k@demo.edu: POST /student/fee-payment submitted linked demand proof QA-FEE-LINK-20260717001806.
admin@college.com: PATCH /admin/fee-payment-requests/3/verify verified the linked proof.
Database check: linked request 3 created payment 19 and left fee demand 3 partially_paid with INR 6,000 remaining.
arjun.k@demo.edu: GET /student/fees showed Partial status and INR 6,000 balance.
arjun.k@demo.edu: GET /student/fee-payment showed the verified linked proof and transaction reference.
accounts@college.com: GET /accounts/dashboard, /accounts/fee-collections, and /accounts/outstanding rendered successfully.
```

Bug found and fixed:

```text
The academic fee-demand create form selected only student id/name but rendered enrollment_number, so the student dropdown could show blank labels.
The controller now loads user name, enrollment number, and status; the Blade option renders "enrollment - student name" with inactive status where relevant.
Regression added: FeeDemandTest::test_create_fee_demand_student_selector_shows_enrollment_and_name.
```

Focused verification:

```text
FeeDemandTest selector regression: 1 test passed, 3 assertions.
FeePaymentTest, FeeDemandTest, and AccountsDashboardGuidanceTest: 82 tests passed, 532 assertions.
```

## Teacher And Student Timetable Attendance Recheck

Manual teacher and student checks were exercised against the running local server:

```text
pmc.faculty@college.com: GET /teacher/pmc-timetable showed official PMC sessions for PMC Faculty Allocation Demo, Decision Analytics Lab, and PGDM Core Section A.
arjun.k@demo.edu: GET /student/timetable and /student/pmc-timetable showed official PMC sessions for PMC Faculty Allocation Demo, Growth Analytics, Decision Analytics Lab, and PGDM Core Section A.
Before fix: pmc.faculty@college.com GET /teacher/timetable did not show official PMC sessions because the page selected the globally latest future term.
Before fix: pmc.faculty@college.com GET /teacher/attendance/mark?date=2026-07-14 showed no class for the Tuesday official PMC session because the bridge semester id differed from the current semester row.
After fix: /teacher/timetable showed PMC Faculty Allocation Demo and Decision Analytics Lab.
After fix: /teacher/attendance/mark?date=2026-07-14 showed the official PMC class.
After selecting bridge entry 31, the attendance roster showed Arjun Kapoor.
pmc.faculty@college.com: POST /teacher/attendance/store marked Arjun Kapoor present for 2026-07-14.
Database check: attendance row 116 was created with timetable_entry_id=31 and pmc_generation_item_id=5.
arjun.k@demo.edu: GET /student/attendance showed PMC Faculty Allocation Demo and the updated attendance percentage.
```

Bug found and fixed:

```text
Teacher timetable used Term::latest(start_date), so future terms could hide a teacher's current official sessions.
Teacher timetable now derives the current term from the teacher's published canonical/legacy timetable assignments, then falls back to active current terms.
Teacher attendance filtered only the exact current semester id, while the bridge service may create operational entries under a same-number semester row.
Teacher attendance now accepts compatible semester rows with the same id, number, or name, so official PMC bridge entries remain markable.
Regression added: TeacherTimetablePortalTest.
```

Focused verification:

```text
TeacherTimetablePortalTest: 2 tests passed, 12 assertions.
Teacher timetable/attendance/dashboard filtered chunk: 19 tests passed, 138 assertions.
Student attendance/timetable adjacent filtered chunk: 17 tests passed, 108 assertions.
```

## Program Chair Substitution Recheck

Manual Program Chair and teacher checks were exercised against the running local server:

```text
chair@college.com: GET /program-chair/timetable/substitutions rendered the substitution page and listed PMC Faculty Allocation Demo.
chair@college.com: POST /program-chair/timetable/substitutions recorded canonical substitution QA substitution live 20260717003155 for pmc:5 with substitute teacher 6.
Database check: academic_pmc_substitution_recommendations row 8 was created with pmc_generation_item_id=5, original_teacher_id=5, substitute_teacher_id=6, status=recorded, score=100.
Database check: no timetable_substitutions legacy row was created for pmc_generation_item_id=5.
pmc.faculty@college.com: GET /teacher/timetable showed "Substituted by Prof. Vikram Shah" and the QA reason.
pmc.adjunct@college.com: GET /teacher/timetable showed "Covering for Prof. Aditi Sen" and the QA reason.
```

Focused verification:

```text
ProgramChairLegacyTimetableIntegrityTest, StudentTimetableWorkflowTest, AcademicsPmcTimetableV054Test, and AcademicsPmcTimetableV083Test substitution filter: 8 tests passed, 49 assertions.
```

## Frontend Build And Smoke Recheck

Final frontend checks after the live user workflow pass:

```text
npm run frontend:build: passed.
npm run frontend:smoke: first attempt exceeded the 3-minute command timeout; rerun with a longer timeout passed with 138 tests and 4025 assertions.
npm run frontend:smoke after Accounts fee-demand selector fix: passed with 138 tests and 4027 assertions.
npm run frontend:smoke:mobile: passed with 29 tests and 1473 assertions.
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
- Fixed public applicant demo readiness by seeding an open application window and allowing applicant category/entrance metadata to persist from application form saves.
- Fixed applicant offer-letter accept/decline form handling so real portal users return to HTML pages with flash messages instead of raw JSON, while JSON callers remain supported.
- Fixed enrollment lifecycle status so completed admission enrollment marks the applicant as enrolled while creating the student, confirmation, and Academics handoff.
- Added an explicit admission enrollment status repair command for existing databases with completed confirmations whose applicants were left non-enrolled before the lifecycle fix.
- Cleaned CMC placed-students output so selected placement records show readable drive, company, and package details without mojibake.
- Repaired rolling demo academic calendar data and added Exam Cell registration review so future exams, student registrations, approvals, hall tickets, and student admit-card downloads work in local real-user testing.
- Verified CMC career-event creation, student registration, attendance marking, export, and cleaned career-event list mojibake.

## Feedback

- The main seeded role dashboards and PMC timetable journeys are testable locally now.
- The app should keep live HTTP/session checks in the regular smoke routine because they caught a real seeded-data issue that isolated feature tests missed.
- Do not continue broad structural refactoring before the next product task unless a tested user flow exposes a blocker.

## Next Recommended Testing

When time allows, test one module at a time with real click/form submissions:

1. Admission application lifecycle.
2. PMC publish/freeze flow on a disposable demo run.
