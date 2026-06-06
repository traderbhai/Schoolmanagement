# College Management System — Developer Context

> **Full guide:** `GUIDE.md` at repo root — read it for complete feature list, workflows, and route maps.

---

## Stack

- **Laravel 13.8** · PHP 8.3+ · SQLite · Bootstrap 5 · Spatie Permissions
- **PDF:** `barryvdh/laravel-dompdf ^3.1` — PDF templates must be standalone HTML, NEVER `@extends()`
- **Layouts:** `layouts/admin` (all staff portals), `layouts/student` (student), `layouts/guest` (public)
- **Branch:** `claude/focused-rubin-Uo1Iz` → `traderbhai/Schoolmanagement`

---

## Roles & Demo Logins (all passwords: `password`)

| Role | Email |
|------|-------|
| admin | admin@college.com |
| admission_head | head@college.com |
| admission_officer | officer@college.com |
| accounts_officer | accounts@college.com |
| dean_academics | dean@college.com |
| hod | hod@college.com |
| program_chair | chair@college.com |
| exam_cell | exam@college.com |
| teacher | ravi@college.com |
| student | aarav@college.com |

---

## Critical SQLite Rules (DO NOT FORGET)

1. **No `HAVING` without `GROUP BY`** — SQLite rejects it. Filter with PHP after `->get()` instead.
2. **Qualify column names in JOINs** — always `table.column` when joining tables with same column name.
3. **No `whereType()` on morphTo** — use `whereHasMorph()` + post-fetch `->load()`.
4. **No ENUM enforcement** — validate in Laravel only.

---

## Route Ordering Rule

Static routes MUST come before wildcard `{param}` routes sharing the same prefix:
```php
Route::get('leads/import', ...);      // FIRST — static
Route::get('leads/export-csv', ...);  // FIRST — static
Route::get('leads/{lead}', ...);      // LAST  — wildcard
```
Registering a static route after a wildcard causes the static route to be shadowed (404).

---

## Polymorphic ApprovalWorkflow Pattern

```php
$query = ApprovalWorkflow::where('approver_role', 'dean_academics')
    ->where('status', 'pending')
    ->with(['approvable', 'approver'])
    ->latest();

// Filter by program (polymorphic)
if ($request->filled('program_id')) {
    $query->whereHasMorph('approvable', [Applicant::class], fn($q) =>
        $q->where('program_id', $request->program_id)
    );
}

$approvals = $query->paginate(20)->withQueryString();

// Load nested relations AFTER fetch (not inside with() closure)
$approvals->getCollection()->each(function ($approval) {
    if ($approval->approvable instanceof Applicant) {
        $approval->approvable->load(['user', 'program', 'batch']);
    }
});
```

---

## Offer Letter Fields (do not use wrong fields)

```php
OfferLetter::create([
    'applicant_id'        => $applicant->id,
    'program_id'          => $applicant->program_id,
    'batch_id'            => $applicant->batch_id,
    'status'              => 'issued',      // NOT 'generated'
    'issued_at'           => now(),         // NOT 'generated_at'
    'issued_by'           => auth()->id(),
    'acceptance_deadline' => now()->addDays(14)->toDateString(),
]);
```

---

## Admission Status Flow

```
draft → submitted → under_review → shortlisted → selected → enrolled
                                              ↘ rejected → waitlisted
```

## Approval Chain (Offer Letters)

```
Admission Head selects applicant
→ Dean Academics approves → OfferLetter created + Program Chair workflow queued
→ Program Chair approves → final
```

---

## Enrollment Number Format

`EnrollmentService::enroll()` auto-generates: **`ENR-YYYY-PROGRAMCODE-#####`**

---

## URL Prefixes by Role

| Portal | Prefix | Layout used |
|--------|--------|-------------|
| Admin | /admin/* | layouts.admin |
| Admission | /admission/* | layouts.admin |
| Accounts | /accounts/* | layouts.admin |
| Dean | /dean/* | layouts.admin |
| HOD | /hod/* | layouts.admin |
| Program Chair | /program-chair/* | layouts.admin |
| Exam Cell | /exam-cell/* | layouts.admin |
| Teacher | /teacher/* | layouts.admin |
| Student | /student/* | layouts.student |
| Parent | /parent/* | layouts.admin |
| Applicant (self-service) | /applicant/* | layouts.admin |
| Public | /apply, /track | layouts.guest |
| Academic team | /academic/* | layouts.admin |

---

## Dev Commands

```bash
php artisan migrate:fresh --seed          # Full reseed
php artisan serve --port=8000             # Dev server
php artisan route:clear                   # Clear route cache
php artisan route:list --name=<prefix>    # List routes

# Playwright tests (screenshots to /tmp/screenshots/)
NODE_PATH=/opt/node22/lib/node_modules node /tmp/test_admission2.js
```

---

## Current Test Status (last run 2026-06-06)

**67 pages tested — 64 HTTP 200 | 0 auth failures | 0 server errors**

Known 404 (not bugs — test URL wrong):
- `/admin/academic-calendar` — correct URL is `/academic/academic-calendars`

---

## Key File Locations

```
routes/web.php                                    — all 497 routes
app/Http/Controllers/Admission/                   — 26 admission controllers
app/Http/Controllers/Departmental/                — Dean, Chair, HOD, ExamCell, Accounts
app/Services/                                     — Grade, Enrollment, Report, Timetable, AdmissionNotification
resources/views/layouts/admin.blade.php           — main staff layout (sidebar)
resources/views/layouts/student.blade.php         — student layout
database/seeders/DemoDataSeeder.php               — all demo data
GUIDE.md                                          — full user & developer guide
```

---

## Academic Phase Implementation Plan

**Status:** Plan created (2026-06-06), awaiting Phase 1 approval

**Timeline:** 6-9 months, 8 phases, ~1,200-1,500 person-days

**Detailed Roadmaps:** (in /college-mgmt/ root)
- `PHASED_IMPLEMENTATION_ROADMAP.md` — Technical blueprint (all features, models, routes)
- `IMPLEMENTATION_SUMMARY.md` — Executive summary for stakeholders
- `IMPLEMENTATION_PATTERNS.md` — Code examples & patterns
- `QUICK_REFERENCE.md` — One-page cheat sheet

**8 Phases:**

| Phase | Theme | Duration | Key Deliverables |
|-------|-------|----------|---|
| 1 | Role & Permission Hierarchy | 2w | Role scoping, permission matrix, audit logging |
| 2 | Role-Specific Dashboards | 2w | 9 dashboards (Dean, Chair, PMC, Exam, HOD, Faculty, CMC, Director, Owner) |
| 3 | Approval Workflows | 2w | Multi-step chains, escalation, SLA tracking |
| 4 | Offer & Enrollment | 2w | Bulk offers, enrollment numbers, portal updates |
| 5 | Academic Lifecycle | 3w | Exams, grades, GPA, promotion, transcripts |
| 6 | Fee Management | 2w | Demands, payments, reconciliation, scholarships |
| 7 | Placement & Career | 2w | Drives, internships, alumni, placement stats |
| 8 | Reporting & Analytics | 2w | AICTE compliance, institutional KPIs, director dashboards |

**New Models (~30):** UserRole, RolePermissionMatrix, CurriculumChange, StudentGrievance, FacultyWorkload, StudentMentorship, PlacementDrive (extended), AlumniProfile, + more

**Key Roles Impacted:** Dean, Program Chair, PMC, Exam Cell, HOD, Faculty, CMC/Placement, Director, Owner

**Next Step:** Phase 1 approval → start role & permission hierarchy implementation
