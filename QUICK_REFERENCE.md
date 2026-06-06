# PHASED IMPLEMENTATION QUICK REFERENCE CARD

## The 8 Phases (6-9 months)

### PHASE 1: Role & Permission Management (Weeks 1-2)
**What:** Define 9 roles with feature-level access control  
**Key Deliverables:** Role hierarchy, permission matrix, program scoping, audit logging  
**Models:** UserRole, RolePermissionMatrix, RoleFeatureAccess, AuditLog  
**Success Gate:** Role matrix approved by stakeholders  

### PHASE 2: Role-Specific Dashboards (Weeks 3-4)
**What:** 9 custom landing pages with KPIs for each role  
**Key Metrics:** Students, revenue, placements, pending approvals, pass rates, fees  
**Service:** DashboardService for cached KPI calculations  
**Success Gate:** All 9 dashboards reviewed + performance < 2 sec  

### PHASE 3: Approval Workflows & Escalation (Weeks 5-6)
**What:** Multi-step approvals (Offer Letter: Admin→Dean→Chair), auto-escalation  
**Models:** ApprovalWorkflowStep, ApprovalNote, ApprovalSLA  
**Logic:** Pending approvals queued per role, escalate if overdue  
**Success Gate:** Offer letter approval chain tested end-to-end  

### PHASE 4: Offer Letter & Enrollment (Weeks 7-8)
**What:** Merit list → Offers → Approval chain → Applicant acceptance → Student creation  
**Workflow:** Admin generates offers → Dean approves → Chair approves → OfferLetter issued  
**Key Features:** Bulk offer generation, applicant portal, enrollment number auto-generation  
**Success Gate:** Complete workflow tested (merit list to enrolled student)  

### PHASE 5: Academic Lifecycle (Weeks 9-11)
**What:** Subject registration, attendance, exams, grades, promotion, graduation  
**Key Features:** Attendance tracking, exam result entry (bulk CSV), GPA calculation, term promotion  
**Grading:** A+ (90-100, 10 pts), A (80-89, 9 pts), ..., F (<40, 0 pts)  
**Success Gate:** Full academic year cycle tested  

### PHASE 6: Fee Management (Weeks 12-13)
**What:** Fee structures, demand generation, payment collection, reconciliation, outstanding tracking  
**Key Reports:** Collection trend, outstanding aging, program-wise %, overdue  
**Alerts:** Payment reminder (1 week before due), overdue notification  
**Success Gate:** Fee lifecycle tested (demand → collection → reconciliation)  

### PHASE 7: Placement & Career Services (Weeks 14-15)
**What:** Drive scheduling, student registration, offer tracking, placement statistics  
**Key Metric:** Placement Rate = (students with accepted offers) / (eligible students) * 100  
**Optional:** Pre-placement training, internship tracking  
**Success Gate:** Drive → registration → interview → offer acceptance tested  

### PHASE 8: Reporting & Analytics (Weeks 16-17)
**What:** Admission funnel, academic performance, financial dashboard, AICTE compliance  
**Reports:** Conversion rates, pass rates by program, revenue vs. target, placement %, faculty ratio  
**Performance:** Heavy queries cached (5-30 min), load tests with 10k+ records  
**Success Gate:** AICTE compliance report validated  

---

## The 9 Roles

| Role | Dashboard KPIs | Key Actions |
|------|---|---|
| **Director** | Students, Revenue, Placements, Pending Approvals | View system health, drill-down |
| **Dean** | Programs, Pass Rates, At-Risk Students, Faculty Utilization | Approve offers, flag at-risk, review analytics |
| **Program Chair** | Subject Offerings, Faculty Assignments, Pending Approvals | Approve curriculum changes, manage approvals |
| **HOD** | Department Overview, Pending Approvals | Manage department approvals |
| **Exam Cell** | Exams Pending, Results Published, Grade Distribution | Enter results, publish grades |
| **Accounts Officer** | Revenue, Outstanding, Overdue, Collections Trend | Verify payments, reconcile bank, send reminders |
| **Admission Head** | Funnel, Pending Documents, Pending Payments, Enrollment Progress | Approve applicants, generate offers, confirm enrollments |
| **Faculty** | Today's Classes, My Students, Attendance %, Exams Pending | Mark attendance, enter exam results |
| **Student** | Enrollment #, Attendance %, Fee Status, Exam Results, Notices | Register subjects, pay fees, view timetable |

---

## Database: New + Enhanced Tables

**10 New Tables:**
- user_roles (program-scoped role assignment)
- role_permission_matrices
- role_feature_access
- approval_workflow_steps
- approval_notes
- approval_slas
- subject_registrations
- pre_placement_trainings
- student_training_enrollments
- (+ 1 more for Phase 8 audit/compliance)

**20+ Enhanced Tables:**
- users (add program scoping)
- students (add GPA, attendance %, status)
- exam_results (add grade, grade_point, published_at)
- offer_letters (add fields for deadline, decline_reason, expired)
- approval_workflows (add escalation fields)
- (+ many others per phase)

---

## Route Patterns (from existing codebase)

```php
// Static routes BEFORE parameterized (critical!)
Route::get('leads/import', ...);      // FIRST
Route::get('leads/{lead}', ...);      // SECOND

// Namespaced with middleware
Route::middleware(['auth', 'role:dean_academics'])->prefix('dean')->name('dean.')->group(function () {
    Route::get('dashboard', [...]);
});

// Resource routes (full CRUD)
Route::resource('programs', ProgramController::class);
// → /programs, /programs/create, /programs/{id}, /programs/{id}/edit, etc.

// Polymorphic approval workflow pattern
ApprovalWorkflow::whereHasMorph('approvable', [Applicant::class], fn($q) => ...)->with('approvable')->get();
```

---

## Critical Code Patterns

### Phase 1: Role Scoping
```php
// Scope query to user's programs
$students = Student::whereHas('batch.program', fn($q) => 
    DataScopeService::scopeByUserPrograms($q, auth()->user())
)->get();
```

### Phase 2: Dashboard Caching
```php
$kpis = Cache::remember("dean.kpi." . auth()->id(), 300, function() {
    return ['students' => count(), 'revenue' => sum(), ...];
});
```

### Phase 3: Approval Workflow
```php
ApprovalWorkflowService::createWorkflow($applicant, [
    ['approver_role' => 'dean_academics', 'deadline_days' => 2],
    ['approver_role' => 'program_chair', 'deadline_days' => 1],
]);
```

### Phase 5: Grade Calculation
```php
$grade = match(true) {
    $marks >= 90 => 'A+',
    $marks >= 80 => 'A',
    $marks >= 70 => 'B+',
    // ...
    default => 'F'
};
```

### Phase 6: Outstanding Calculation
```php
$outstanding = FeeDemand::where('student_id', $id)->sum('amount')
             - FeePayment::where('student_id', $id)->sum('verified_amount');
```

### Phase 8: SQLite-Safe Aggregation
```php
// ❌ WRONG: HAVING without GROUP BY
$results->groupBy('student_id')->having(...)->get();

// ✅ RIGHT: Filter after fetch
$results->groupBy('student_id')->get()->filter(fn($r) => ...);
```

---

## Timeline Parallelization

**Can Run in Parallel:**
- Phase 2 + Phase 3 (weeks 3-6)
- Phase 6 + Phase 7 (weeks 12-15)

**Must Be Sequential:**
- Phase 1 → all others (foundation)
- Phase 4 → Phase 5 (enrollment enables academic)
- Phase 5 → Phase 6 (enrollment enables fee demands)
- Phases 1-7 → Phase 8 (reporting needs all data)

**Optimized 6-Month Schedule:**
```
Weeks 1-2:   Phase 1
Weeks 3-4:   Phase 2
Weeks 3-6:   Phase 3 (parallel)
Weeks 7-8:   Phase 4
Weeks 9-11:  Phase 5
Weeks 11-13: Phase 6 (parallel)
Weeks 13-15: Phase 7 (parallel)
Weeks 16-17: Phase 8
Weeks 18+:   Testing, fixes, go-live
```

---

## Success Metrics (Target by Go-Live)

| Metric | Target |
|--------|--------|
| System Uptime | 99.5% |
| Page Load Time (95th %ile) | < 2 sec |
| User Adoption (staff) | > 90% in 3 months |
| Data Accuracy (fee reconciliation) | 100% |
| AICTE Compliance | 100% |
| Support Tickets per 100 users/month | < 2 |

---

## Critical Success Factors

1. **Phase 1 Rigor** — Spend 2 weeks getting role definitions exact. Saves 4+ weeks later.
2. **Real Payment Gateway** — Integrate actual Razorpay/PayU sandbox in Phase 4 (not mocked).
3. **Query Optimization** — Profile and optimize by Phase 5 (before Phase 8 reporting).
4. **Reliable Email/SMS** — Use SendGrid, Twilio, etc. (queue jobs asynchronously).
5. **User Testing** — Gather feedback from 2-3 real users per role, per phase.
6. **Documentation** — Keep CLAUDE.md and GUIDE.md updated throughout.

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Role definitions change | Get Phase 1 formal approval; enforce change control |
| Payment gateway fails | Test sandbox extensively; implement retry logic |
| Reports too slow | Profile queries in Phase 5; add indexes; cache |
| Email unreliable | Use professional provider; implement retry; track in DB |
| Database too large | Archive old admission/result records; index strategically |

---

## Team & Budget

**Recommended Team:**
- 1 Architect/Tech Lead
- 2 Backend Developers (Laravel)
- 1 Frontend Developer (Blade/Bootstrap)
- 1 QA Engineer
- 1 DevOps/DBA

**Effort Estimate:**
- 6 months @ 5 people = ~1,200-1,500 person-days
- Typical cost range: 20-50 Lakhs INR

---

## Documentation to Read First

1. **IMPLEMENTATION_SUMMARY.md** (30 min) — High-level overview for stakeholders
2. **PHASED_IMPLEMENTATION_ROADMAP.md Phase 1** (1 hour) — Detailed first phase specs
3. **IMPLEMENTATION_PATTERNS.md** (30 min) — Code patterns and examples

---

## Go-Live Checklist

- [ ] All 8 phases complete and tested
- [ ] 100% reconciliation of fees + enrollments
- [ ] 99.5% uptime achieved in staging for 1 week
- [ ] All critical roles trained (2 sessions each)
- [ ] Fallback processes documented for outage scenarios
- [ ] Data backup + recovery tested
- [ ] Support team trained
- [ ] Stakeholder sign-off on Phase 8 reports

---

**For complete details, see:**
- PHASED_IMPLEMENTATION_ROADMAP.md (4,000 lines)
- IMPLEMENTATION_SUMMARY.md (1,500 lines)
- IMPLEMENTATION_PATTERNS.md (2,000 lines)
- CLAUDE.md (developer context, SQLite rules, patterns)
- GUIDE.md (complete user & feature guide)

**Stack:** Laravel 11, SQLite, Bootstrap 5, Spatie Permissions, DomPDF
