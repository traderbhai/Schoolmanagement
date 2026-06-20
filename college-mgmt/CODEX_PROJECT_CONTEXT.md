# SchoolManagement Codex Project Context

This is the short active project memory. Detailed historical sprint notes are archived at `docs/archive/CODEX_PROJECT_CONTEXT_FULL_ARCHIVE_2026-06-20.md`.

## Working Rules

- Verify current code before relying on this file.
- Work one bounded goal at a time.
- Do not reopen the whole app unless the user explicitly asks for a global audit.
- Patch only verified critical/high gaps for the active goal.
- Use additive migrations only.
- Keep existing routes stable unless adding compatible redirects/links.
- Main pages must be database-backed or show useful operational empty states.
- Update demo seeders when new visible workflows need data.
- Update this file only after focused/adjacent verification passes.
- Run full `php artisan test` only at planned stage gates or after shared auth/layout/schema changes.

## Runtime

- App path: `C:\Users\mohd.naved\Documents\SchoolManagement\college-mgmt`.
- Local demo server: `http://localhost:8001`.
- PHP test runtime: `C:\tmp\php-8.5.7\php.exe`.
- PHPRC: `C:\tmp\php-8.5.7-codex-ini`.
- Test command:

```powershell
$env:PHPRC='C:\tmp\php-8.5.7-codex-ini'; C:\tmp\php-8.5.7\php.exe artisan test
```

## Release Control Files

Use these files instead of rereading long history:

- `PROJECT_COMPLETION_BACKLOG.md`: master release checklist by module/workflow family.
- `USER_ROLE_UX_AUDIT.md`: frontend/UX role-readiness checklist and fixed/future-polish status.
- `FRONTEND_COMPLETION_BACKLOG.md`: frontend safety and redesign readiness plan.
- `KPI_DRILLDOWN_AUDIT.md`: cross-department KPI/card-to-list consistency audit.
- `ADMISSION_KPI_DRILLDOWN_AUDIT.md`: Admission-specific KPI drilldown audit.
- `RELEASE_CONTROL.md`: current execution protocol and next-goal template.

## Current Verified Baseline

- Latest full PHP suite: `1490 tests / 12645 assertions` passed.
- Latest frontend build: `npm run frontend:build` passed.
- Latest desktop frontend smoke: `npm run frontend:smoke` passed `127 tests / 3729 assertions`.
- Latest mobile frontend smoke: `npm run frontend:smoke:mobile` passed `29 tests / 1380 assertions`.
- Fast UX closure Batches 1-6 are marked fixed/verified in `USER_ROLE_UX_AUDIT.md` for sampled release-blocking scope.
- Admission Head bounded role slice: `AdmissionHeadReadinessTest` passed `4 tests / 239 assertions`; adjacent Admission frontend/KPI/v0.039 checks passed `27 tests / 437 assertions`.
- Admission Manager/Counsellor Lead/Officer bounded role slice: `AdmissionManagerOfficerReadinessTest` passed `5 tests / 199 assertions`; adjacent Admission frontend/KPI/v0.039 checks passed `27 tests / 437 assertions`.
- Admission Counsellor/Telecaller bounded role slice: `AdmissionCounsellorTelecallerReadinessTest` passed `5 tests / 113 assertions`; adjacent Admission frontend/KPI/v0.038/v0.039 checks passed `34 tests / 513 assertions`.
- Admission Applicant Portal bounded role slice: applicant navigation now exposes owned Admission Operations, Offer Letters, and Notifications self-service routes; consent updates from applicant self-service are recorded with `applicant_portal` source. `AdmissionApplicantReadinessTest` passed `4 tests / 124 assertions`; adjacent portal/applicant/v0.039 checks passed `40 tests / 779 assertions`.

## Release-Ready Module Status

Current release-control status is in `PROJECT_COMPLETION_BACKLOG.md`. As of the latest verified baseline, the bounded completion goals there are marked `Release-ready` unless a new regression/feature scope is opened.

Release-ready families include:

- Security, roles, policies, direct route access.
- Fees, payments, refunds, scholarships, NOC.
- Official academic records: exams, results, grade cards, transcripts, hall tickets.
- Admission OS final closure.
- PMC timetable/course allocation.
- Academics Dean OS.
- CoE / Exam OS.
- IQAC OS.
- Program Leadership OS.
- Course Delivery OS.
- Student Portal.
- Teacher Portal.
- Parent Portal.
- Admin master data / academic setup.
- Library.
- Hostel.
- Transport.
- CMC / Placement / Alumni.
- Assets / Inventory.
- Notifications / Notices / Bulk Mail.
- Demo seed data and browser readiness.

## Demo Users

Common demo users:

- Admin: `admin@college.com` / `password`; `admin@demo.edu` is also used in tests.
- Director: `director@college.com` / `password`.
- Dean Academics: `dean@college.com` / `password`.
- PMC Head / Program Chair: `chair@college.com` / `password`.
- PMC Manager: `pmc.manager@college.com` / `password`.
- CoE / Exam Cell: `exam@college.com` / `password`.
- IQAC Head: `iqac.head@college.com` / `password`.
- Program Leader / HOD: `hod@college.com` / `password`.
- Faculty mentor: `faculty.mentor@college.com` / `password`.
- Teacher: `anjali@demo.edu` / `password`.
- Student: `arjun.k@demo.edu` / `password`.
- Parent: `parent@demo.edu` / `password`.
- Applicant: `priya.sharma@applicant.demo` / `password`.
- Accounts: `accounts@college.com` / `password`.
- CMC: `cmc@college.com` / `password`.

## How To Start Future Work

For every new request:

1. Identify the active bounded goal and relevant release-control file.
2. Inspect current routes/controllers/models/views/tests for only that scope.
3. List implemented workflows and verified gaps.
4. Fix only critical/high gaps selected for the slice.
5. Add or update focused tests.
6. Run focused tests, then adjacent regression.
7. Run full suite only at planned stage gate.
8. Update `PROJECT_COMPLETION_BACKLOG.md` or the relevant audit file.
9. Update this context only after tests pass.
10. Stop and report changed files, tests, and remaining gaps.

## Future-Polish Items, Not Current Blockers

- Dedicated non-admin operator roles for Library, Hostel, Transport, and Assets.
- Richer sortable headers/table-density improvements on some operations pages.
- More browser-level create/edit interactions where PHPUnit/frontend smoke already covers page/action-entry safety.

## Archive Rule

Do not append long sprint diaries here. If detailed historical notes are needed, add them to `docs/archive/` and keep this file under roughly 150 lines.
