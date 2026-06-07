# DETAILED PHASED IMPLEMENTATION ROADMAP
## Academic Management System (College ERP)

**Project Timeline:** 6-9 months | **Current Status:** Core admission pipeline + basic academic structure complete  
**Target Delivery:** Production-ready multi-role system serving 9 distinct roles

---

## PHASE 1 — Foundational Role & Permission Management
**Duration:** 2 weeks  
**Priority:** CRITICAL  
**Roles Impacted:** All (9 roles)  
**Prerequisites:** None  
**Blocks:** All other phases

### Context
The system has basic role-based access control (Spatie permissions) but needs formalized role hierarchies, program-level permissions, and granular feature access matrices.

### Features to Build

#### 1. Role Hierarchy & Permission Matrix
**Purpose:** Define clear role relationships, delegation capabilities, and feature access  
**Models Needed:**
- `Role` (extend Spatie) — add: `hierarchy_level`, `parent_roles`, `delegable_to`
- `RolePermissionMatrix` — role_id, permission_id, program_id (nullable), program_specific (boolean)
- `RoleFeatureAccess` — role_id, feature_code, access_level (view/create/edit/approve/delete)

**Controllers:**
- `Admin/RoleController@index` — list all roles with permission counts
- `Admin/RoleController@edit` — edit role details, manage parent roles
- `Admin/RoleController@permissions` — drag-drop permission assignment
- `Admin/RoleController@features` — feature access configuration

**Views:**
- `admin/role-management/index.blade.php` — role list with hierarchy visualization
- `admin/role-management/edit.blade.php` — role details + parent selection
- `admin/role-management/permissions.blade.php` — matrix view with checkboxes
- `admin/role-management/features.blade.php` — feature tree with access levels

**Routes:**
```php
Route::resource('admin/roles', Admin\RoleController::class);
Route::post('admin/roles/{role}/permissions', [Admin\RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
Route::post('admin/roles/{role}/features', [Admin\RoleController::class, 'updateFeatures'])->name('roles.features.update');
Route::post('admin/roles/{role}/delegate', [Admin\RoleController::class, 'delegate'])->name('roles.delegate');
```

**Workflow Dependencies:**
- Must complete before role-specific dashboards in Phase 2
- Enables program-scoped role assignments (Phase 3)

**Testing Strategy:**
- Create test roles with different hierarchies
- Verify permission inheritance
- Test feature access restrictions (attempt unauthorized actions)
- Validate delegation chains (A → B → C access)

**Acceptance Criteria:**
```
AC1: Admin can create custom roles with clear hierarchy
AC2: Permissions automatically cascade to child roles
AC3: Features marked as "program-specific" only apply when role assigned to program
AC4: Attempted access to denied feature shows permission error, not 500
AC5: Role deletion cascades safely (reassign users before deletion)
```

---

#### 2. User Role Assignment & Program Scoping
**Purpose:** Assign roles to users with optional program restrictions  
**Models Needed:**
- `UserRole` — user_id, role_id, program_id (nullable), assigned_by, assigned_at, active_until (nullable)
- Modify `User` — add scope helpers

**Controllers:**
- `Admin/UserRoleController@assign` — modal to assign role + program
- `Admin/UserRoleController@revoke` — remove role
- `Admin/UserRoleController@list` — per-user role assignment view

**Views:**
- `admin/users/role-assignments.blade.php` — table showing user → roles → programs
- Modal: `admin/users/assign-role-modal.blade.php`

**Routes:**
```php
Route::post('admin/users/{user}/roles', [Admin\UserRoleController::class, 'assign'])->name('users.roles.assign');
Route::delete('admin/users/{user}/roles/{role}', [Admin\UserRoleController::class, 'revoke'])->name('users.roles.revoke');
Route::get('admin/users/{user}/roles', [Admin\UserRoleController::class, 'show'])->name('users.roles.show');
```

**Testing Strategy:**
- Assign HOD role to user for specific program
- Verify HOD can only see students in that program
- Test expiring role (time-based access)
- Verify user with multiple roles sees all portals they can access

**Acceptance Criteria:**
```
AC1: User can have multiple roles (e.g., teacher + exam_cell)
AC2: Program-scoped role limits data visibility to that program only
AC3: Expired roles automatically disable access
AC4: Dashboard redirects based on primary role (configurable)
```

---

#### 3. Permission Audit Logging
**Purpose:** Track who assigned/changed/revoked roles and permissions  
**Models Needed:**
- `AuditLog` — actor_id, action, target_type, target_id, changes (JSON), created_at

**Controllers:**
- `Admin/AuditController@index` — searchable audit log
- `Admin/AuditController@show` — diff view for changes

**Views:**
- `admin/audit/index.blade.php` — table with filters: date, actor, action, target
- `admin/audit/show.blade.php` — before/after JSON diff

**Routes:**
```php
Route::get('admin/audit-log', [Admin\AuditController::class, 'index'])->name('audit.index');
Route::get('admin/audit-log/{log}', [Admin\AuditController::class, 'show'])->name('audit.show');
```

**Acceptance Criteria:**
```
AC1: Every role/permission change is logged with timestamp and actor
AC2: Audit log shows JSON diff of permission changes
AC3: Non-admin users cannot access audit log
```

---

### Database Models to Create/Extend
```php
// New migrations

// 2026_06_07_000000_refactor_role_permissions.php
Schema::create('role_permission_matrices', function (Blueprint $t) {
    $t->id();
    $t->foreignId('role_id')->constrained('roles');
    $t->foreignId('permission_id')->constrained('permissions');
    $t->unsignedBigInteger('program_id')->nullable(); // null = all programs
    $t->boolean('program_specific')->default(false);
    $t->timestamps();
});

// 2026_06_07_000001_create_role_feature_access.php
Schema::create('role_feature_access', function (Blueprint $t) {
    $t->id();
    $t->foreignId('role_id')->constrained('roles');
    $t->string('feature_code'); // 'exam.enter_marks', 'admission.approve', etc.
    $t->enum('access_level', ['view', 'create', 'edit', 'approve', 'delete']);
    $t->timestamps();
});

// 2026_06_07_000002_create_user_roles.php
Schema::create('user_roles', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained();
    $t->foreignId('role_id')->constrained('roles');
    $t->unsignedBigInteger('program_id')->nullable();
    $t->foreignId('assigned_by')->constrained('users');
    $t->date('active_until')->nullable();
    $t->timestamps();
});

// 2026_06_07_000003_create_audit_logs.php
Schema::create('audit_logs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('actor_id')->constrained('users');
    $t->string('action'); // 'role_assigned', 'permission_changed', etc.
    $t->string('target_type');
    $t->unsignedBigInteger('target_id');
    $t->json('changes')->nullable();
    $t->timestamps();
    $t->index(['created_at', 'actor_id']);
});
```

---

### Interdependencies
- **Blocks:** Phase 2 (role-specific dashboards), Phase 3 (program chair workflows)
- **Enabled By:** None
- **Milestone Gate:** All 9 roles defined with feature access matrices approved by stakeholders

---

### User Stories
```
As an Institute Director, I want to define role hierarchies so that 
permissions cascade logically (admin > dean > program_chair > faculty).

AC1: Create admin role with all permissions
AC2: Create dean role inheriting from admin but restricted permissions
AC3: Attempting access to denied feature shows clear error message

---

As an Admin, I want to assign the HOD role to a user for a specific program 
so that they only see data for that program.

AC1: Assign HOD role to User X for Program "PGDM"
AC2: User X logs in and only sees Program "PGDM" data
AC3: User X cannot access other programs even via URL manipulation

---

As a System Owner, I want to audit role changes so that I can track 
who granted access and when.

AC1: Every role assignment logged with actor_id and timestamp
AC2: Audit log searchable by date, actor, and target user
AC3: Can view before/after values of permission changes
```

---

---

## PHASE 2 — Role-Specific Dashboards & Navigation
**Duration:** 2 weeks  
**Priority:** CRITICAL  
**Roles Impacted:** All 9 roles  
**Prerequisites:** Phase 1  
**Blocks:** Specific role features (Phase 3-7)

### Context
System has basic dashboards for some roles (Dean, Program Chair, Exam Cell) but needs comprehensive, role-specific landing pages with KPIs, quick actions, and workflow entry points for all roles.

### Features to Build

#### 1. Institute Director / Super Admin Dashboard
**Purpose:** Executive overview of entire system  

**Models Needed:** None new (aggregates existing data)

**Controllers:**
- `Admin/DirectorDashboardController@index` — fetch aggregates
- `Admin/DirectorDashboardController@systemHealth` — performance metrics

**Views:**
- `admin/director-dashboard/index.blade.php` — executive KPI cards
- `admin/director-dashboard/quick-stats.blade.php` — component

**Key Metrics:**
- Total students enrolled (by program)
- Academic year progress (%)
- Total faculty FTE
- Revenue collected vs. target
- Pending approvals (by type)
- System health (database size, slow queries)
- Recent critical events

**Routes:**
```php
Route::get('admin/director-dashboard', [Admin\DirectorDashboardController::class, 'index'])->name('director.dashboard');
Route::get('admin/system-health', [Admin\DirectorDashboardController::class, 'systemHealth'])->name('system.health');
```

**Acceptance Criteria:**
```
AC1: Shows accurate student count across all programs
AC2: Revenue metrics calculated from fee payments + scholarship deductions
AC3: Pending approvals grouped by workflow type (offer letters, HOD approvals, etc.)
AC4: Dashboard caches data (refresh every 5 min) for performance
```

---

#### 2. Dean of Academic Affairs Dashboard
**Purpose:** Academic progress, program analytics, approval queue

**Expand existing:** `/dean/dashboard` with new metrics

**Key Metrics:**
- Program-wise enrollment status
- Student performance heatmap (pass rate by program)
- Faculty utilization (teaching load vs. capacity)
- Exam schedule overview
- Pending approvals (offer letters, term promotions)
- At-risk students (attendance < 75%, avg marks < 40%)
- Scholarship disbursement status

**New Controllers:**
- `Departmental/DeanController@dashboard` — aggregate data
- `Departmental/DeanController@programAnalytics` — drill-down by program

**New Views:**
- `departmental/dean/dashboard.blade.php` — redesigned with charts
- `departmental/dean/program-analytics.blade.php` — heatmap

**Routes:**
```php
Route::get('dean/program-analytics/{program}', [Departmental\DeanController::class, 'programAnalytics'])->name('dean.program-analytics');
```

**Acceptance Criteria:**
```
AC1: At-risk students calculated correctly (attendance + exam average)
AC2: Faculty utilization shows actual teaching hours vs. capacity
AC3: Charts render with real-time data (cache 10 min)
AC4: Can drill down from program card to student list
```

---

#### 3. Program Chair / Coordinator Dashboard
**Purpose:** Curriculum management, program metrics, approvals

**Key Metrics:**
- Subject offerings this semester
- Total enrolled students in program
- Faculty assignments (subject-wise)
- Approval pending (curriculum changes, transfer requests)
- Placement stats (if applicable)
- Semester schedule (when next term starts)

**Views:**
- `departmental/program-chair/dashboard.blade.php`
- `departmental/program-chair/curriculum-overview.blade.php`

**Routes:**
```php
Route::get('program-chair/curriculum-overview/{program}', [Departmental\ProgramChairController::class, 'curriculumOverview'])->name('chair.curriculum-overview');
```

---

#### 4. HOD / Area Chair Dashboard
**Purpose:** Department oversight, faculty management, approvals

**Key Metrics:**
- Faculty in department
- Subject allocations
- Student performance (program-wise within dept)
- Pending approvals (leave, transfer, etc.)
- Department budget (if available)

**Views:**
- `departmental/hod/dashboard.blade.php`

---

#### 5. Examination Cell Dashboard
**Purpose:** Exam schedule, result entry, grade publication

**Key Metrics:**
- Exams scheduled (upcoming, in-progress, completed)
- Results pending entry (by subject)
- Results published (by term)
- Grade distribution (pass/fail counts)
- Answer sheet processing status

**Views:**
- `departmental/exam-cell/dashboard.blade.php`

---

#### 6. Accounts Officer Dashboard
**Purpose:** Financial overview, collections, outstanding

**Key Metrics:**
- Total fee collected (admission + academic)
- Outstanding amount (by program, by student)
- Payment verification queue
- Overdue fees (count + amount)
- Monthly collection trend
- Scholarship disbursement pending

**Expand:** `/accounts/dashboard` with new charts

**Acceptance Criteria:**
```
AC1: Outstanding calculated as (all fee demands) - (verified payments)
AC2: Overdue calculated as (payments past due date) with threshold (e.g., 30 days)
AC3: Monthly trend shows rolling 12-month collection chart
```

---

#### 7. Placement / CMC Dashboard
**Purpose:** Placement drives, company interaction, student registration

**Key Metrics:**
- Active placement drives (upcoming, ongoing, completed)
- Registered students for drives
- Companies registered
- Placement offers received
- Placement rate (by program, by batch)
- Top placed companies

**Models Needed:** (Assume exists — check if not)
- `PlacementDrive` ✓
- `Placement` ✓
- `Company` ✓

**Controllers:**
- `Placement/DashboardController@index`

**Views:**
- `placement/dashboard.blade.php`

**Routes:**
```php
Route::prefix('placement')->name('placement.')->middleware(['auth', 'role:placement|admin'])->group(function () {
    Route::get('dashboard', [Placement\DashboardController::class, 'index'])->name('dashboard');
    // ... more routes in Phase 5
});
```

---

#### 8. Faculty / Teacher Dashboard
**Purpose:** Class schedule, attendance, exam entry

**Expand existing:** `/teacher/dashboard`

**Key Metrics:**
- Today's schedule (class timing, subject, batch)
- My students (count by subject)
- Attendance completion % (for subjects I teach)
- Upcoming exams (my subjects)
- Pending exam result entry

**Acceptance Criteria:**
```
AC1: Schedule shows only today's classes
AC2: Attendance completion % is accurate (entries / total possible)
AC3: Pending exams sorted by date
```

---

#### 9. Student Portal Dashboard
**Improve existing:** `/student/dashboard`

**Key Metrics:**
- Enrollment number, program, batch
- Current semester / term
- Attendance % (overall, by subject)
- Fee status (paid, pending, overdue)
- Academic performance (latest exam marks, GPA)
- Notices/announcements (recent)
- Placement drive registrations

**Acceptance Criteria:**
```
AC1: Shows correct enrollment number
AC2: Fee status shows outstanding amount
AC3: Attendance color-coded (green >80%, yellow 60-80%, red <60%)
AC4: Latest exam result shows within 24h of publishing
```

---

#### 10. Admission Head Dashboard
**Improve existing:** `/admission/dashboard`

**Key Metrics:**
- Application funnel (leads → applications → selections → enrollments)
- Pending action items (documents to verify, payments to check, offers to generate)
- Application windows status
- Enrollment progress vs. seat plan
- Merit list status per program

**Acceptance Criteria:**
```
AC1: Funnel shows accurate counts at each stage
AC2: Pending items link directly to action page
AC3: Enrollment progress bar shows current / target seats
```

---

### Navigation & Layout
**Controllers:**
- `NavigationController@menu` — render role-specific sidebar

**Views:**
- `layouts/admin-sidebar.blade.php` — role-aware menu structure
- `components/role-badge.blade.php` — display current role

**Routes:**
```php
Route::get('navigation/menu', [NavigationController::class, 'menu'])->name('navigation.menu');
```

**Structure:**
```
Admin → Programs, Students, Teachers, Notices, Timetable, Attendance, Exams
Dean → Programs, Students, Academics, Attendance, Approvals, Analytics
Program Chair → Dashboard, Curriculum, Approvals
HOD → Approvals, Department View
Exam Cell → Dashboard, Exams, Results, Grade Publication
Accounts → Dashboard, Fee Collections, Outstanding, Admissions Payments, Reconciliation
Admission Head → Dashboard, Leads, Applicants, Documents, Payments, Sessions, Enrollment, Reports
Placement → Dashboard, Drives, Companies, Registrations, Placements
Faculty → Dashboard, Attendance, Students, Exams, Profile
Student → Dashboard, Attendance, Results, Fees, Subjects, Timetable, Notices
```

---

### Interdependencies
- **Blocks:** All role-specific features (Phases 3-7)
- **Enabled By:** Phase 1 (role hierarchy)
- **Milestone Gate:** All 9 role dashboards tested, performance < 1s load time

---

### User Stories
```
As a Dean of Academic Affairs, I want to see at-risk students (low attendance + low marks) 
on my dashboard so that I can intervene early.

AC1: "At-Risk Students" widget shows students with attendance < 75% AND avg marks < 40%
AC2: Can click student name to view profile
AC3: Data refreshes every 10 minutes

---

As a Program Chair, I want to see which subjects are taught in my program 
and by which faculty so that I can verify curriculum coverage.

AC1: Dashboard shows all subjects this semester
AC2: Each subject shows assigned faculty member
AC3: Can drill down to see student enrollment per subject
```

---

---

## PHASE 3 — Approval Workflows & Escalation
**Duration:** 2 weeks  
**Priority:** CRITICAL  
**Roles Impacted:** Dean, Program Chair, HOD, Admission Head  
**Prerequisites:** Phase 1, Phase 2  
**Blocks:** Phase 4 (offer letter workflow), Phase 5 (placement cycle)

### Features to Build

#### 1. Generalized Approval Workflow Engine
**Purpose:** Multi-step approvals (Dean → Program Chair → HOD, etc.) with escalation

**Models Needed:**
- Enhance `ApprovalWorkflow` — add: `escalation_days`, `escalation_to_role`, `escalation_reason`
- `ApprovalWorkflowStep` — workflow_id, step_number, approver_role, approval_required, deadline_days
- `ApprovalNote` — workflow_id, step_id, user_id, note, action_at

**Controllers:**
- `Departmental/ApprovalWorkflowController@index` — pending approvals for logged-in role
- `Departmental/ApprovalWorkflowController@approve` — approve with optional notes
- `Departmental/ApprovalWorkflowController@reject` — reject with reason
- `Departmental/ApprovalWorkflowController@escalate` — escalate to higher role
- `Admin/ApprovalWorkflowController@configure` — set up approval chains per object type

**Views:**
- `departmental/approvals/index.blade.php` — pending approvals table
- `departmental/approvals/show.blade.php` — detail view with history
- `admin/approval-workflows/configure.blade.php` — approval chain builder

**Routes:**
```php
Route::prefix('approvals')->name('approvals.')->middleware(['auth'])->group(function () {
    Route::get('/', [Departmental\ApprovalWorkflowController::class, 'index'])->name('index');
    Route::get('/{workflow}', [Departmental\ApprovalWorkflowController::class, 'show'])->name('show');
    Route::post('/{workflow}/approve', [Departmental\ApprovalWorkflowController::class, 'approve'])->name('approve');
    Route::post('/{workflow}/reject', [Departmental\ApprovalWorkflowController::class, 'reject'])->name('reject');
    Route::post('/{workflow}/escalate', [Departmental\ApprovalWorkflowController::class, 'escalate'])->name('escalate');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin/approval-workflows')->name('admin.approval-workflows.')->group(function () {
    Route::get('configure', [Admin\ApprovalWorkflowController::class, 'configure'])->name('configure');
    Route::post('configure', [Admin\ApprovalWorkflowController::class, 'storeConfiguration'])->name('configure.store');
});
```

**Workflow Dependencies:**
- Applicant Offer Letter approval: `Admission Head → Dean → Program Chair`
- Term Promotion: `HOD → Dean`
- Leave Application: `HOD → Dean` (optional escalation to HR)

**Acceptance Criteria:**
```
AC1: Approval chains configurable per entity type
AC2: Escalation triggered automatically after X days
AC3: Approval history shows all steps with timestamps
AC4: Reject with reason blocks further processing
AC5: SMS/email notifications sent to next approver on workflow progression
```

---

#### 2. Approval Dashboard Component
**Purpose:** Unified approval queue for all roles

**Views:**
- Component: `components/approval-queue.blade.php`
- Widget showing: count, approval type, applicant/student name, days pending

**Routes:**
```php
Route::get('approvals/count', [Departmental\ApprovalWorkflowController::class, 'count'])->name('approvals.count');
```

---

#### 3. Escalation & SLA Tracking
**Purpose:** Auto-escalate overdue approvals, track SLA compliance

**Models Needed:**
- `ApprovalSLA` — workflow_type, approver_role, sla_days, escalate_to_role

**Controllers:**
- `Console/Command/EscalateOverdueApprovalsCommand` — scheduled job

**Commands:**
```bash
php artisan approvals:escalate-overdue
```

**Acceptance Criteria:**
```
AC1: Approval pending > SLA days automatically escalates
AC2: Escalation email sent to next role with urgency indicator
AC3: SLA dashboard shows % approvals met vs. breached
```

---

#### 4. Bulk Approval Actions
**Purpose:** Approve multiple applicants/documents in one action

**Controllers:**
- `Departmental/ApprovalWorkflowController@bulkApprove` — check multiple, approve all

**Views:**
- `departmental/approvals/bulk-actions.blade.php` — checkboxes + approve button

**Routes:**
```php
Route::post('approvals/bulk-approve', [Departmental\ApprovalWorkflowController::class, 'bulkApprove'])->name('approvals.bulk-approve');
```

**Acceptance Criteria:**
```
AC1: Select multiple workflows, approve all at once
AC2: Each approval logged separately with actor_id
AC3: Bulk action sends individual notifications
```

---

### Database Models
```php
// 2026_06_07_100000_enhance_approval_workflows.php
Schema::table('approval_workflows', function (Blueprint $t) {
    $t->integer('escalation_days')->nullable();
    $t->string('escalation_to_role')->nullable();
    $t->text('escalation_reason')->nullable();
    $t->timestamp('escalated_at')->nullable();
    $t->foreignId('escalated_by')->nullable()->constrained('users');
});

// 2026_06_07_100001_create_approval_workflow_steps.php
Schema::create('approval_workflow_steps', function (Blueprint $t) {
    $t->id();
    $t->foreignId('approval_workflow_id')->constrained('approval_workflows');
    $t->integer('step_number');
    $t->string('approver_role');
    $t->boolean('approval_required')->default(true);
    $t->integer('deadline_days')->nullable();
    $t->timestamp('completed_at')->nullable();
    $t->timestamps();
});

// 2026_06_07_100002_create_approval_notes.php
Schema::create('approval_notes', function (Blueprint $t) {
    $t->id();
    $t->foreignId('approval_workflow_id')->constrained();
    $t->foreignId('step_id')->constrained('approval_workflow_steps');
    $t->foreignId('user_id')->constrained();
    $t->text('note');
    $t->timestamps();
});

// 2026_06_07_100003_create_approval_slas.php
Schema::create('approval_slas', function (Blueprint $t) {
    $t->id();
    $t->string('workflow_type'); // 'offer_letter', 'term_promotion', etc.
    $t->string('approver_role');
    $t->integer('sla_days');
    $t->string('escalate_to_role');
    $t->timestamps();
});
```

---

### Interdependencies
- **Blocks:** Phase 4 (Offer Letter workflow), Phase 5 (Leave approvals)
- **Enabled By:** Phase 1, Phase 2
- **Milestone Gate:** Offer letter approval chain tested end-to-end with notification delivery

---

### User Stories
```
As a Dean of Academic Affairs, I want to see all pending offer letters awaiting my approval 
so that I can review and approve/reject applicants.

AC1: Approval queue shows applicant name, program, application date, days pending
AC2: Can approve with optional remarks
AC3: Approve triggers Program Chair workflow automatically
AC4: Email sent to Program Chair on approval

---

As a Program Chair, I want overdue approvals to escalate to the Dean automatically 
so that critical decisions don't get stuck.

AC1: Set SLA to 2 days for offer letter approvals
AC2: After 2 days, workflow escalates to dean_academics role
AC3: Escalation email sent with urgency flag
AC4: SLA dashboard shows % breached vs. met
```

---

---

## PHASE 4 — Offer Letter & Enrollment Workflow
**Duration:** 2 weeks  
**Priority:** CRITICAL  
**Roles Impacted:** Admission Head, Dean, Program Chair, Applicant (self-service)  
**Prerequisites:** Phase 1, Phase 2, Phase 3  
**Blocks:** Phase 6 (Academic onboarding)

### Features to Build

#### 1. Offer Letter Generation & Approval
**Purpose:** Auto-generate offer letters after merit list approval, route through approvals

**Expand existing:** `OfferLetterController` (already exists)

**Models:**
- `OfferLetter` ✓ (exists, may enhance)
- `ApprovalWorkflow` ✓ (use for workflow)

**Controllers:**
- `Admission/OfferLetterController@generate` — bulk generate for selected applicants
- `Admission/OfferLetterController@sendApprovalWorkflow` — route to Dean for approval
- `Admission/OfferLetterController@resend` — resend to applicant
- `Admission/OfferLetterController@withdraw` — withdraw offer (e.g., if applicant rejected)

**Views:**
- `admission/offer-letters/generate.blade.php` — select applicants, set deadline
- `admission/offer-letters/bulk-actions.blade.php` — resend/withdraw multiple
- `pdf/offer-letter.blade.php` — PDF template (already exists, verify)

**Routes:**
```php
Route::middleware(['auth', 'role:admission_head|admin'])->prefix('admission')->name('admission.')->group(function () {
    Route::post('offer-letters/generate', [Admission\OfferLetterController::class, 'generate'])->name('offer-letters.generate');
    Route::post('offer-letters/{offerLetter}/resend', [Admission\OfferLetterController::class, 'resend'])->name('offer-letters.resend');
    Route::post('offer-letters/{offerLetter}/withdraw', [Admission\OfferLetterController::class, 'withdraw'])->name('offer-letters.withdraw');
    Route::post('offer-letters/bulk-resend', [Admission\OfferLetterController::class, 'bulkResend'])->name('offer-letters.bulk-resend');
});
```

**Workflow:**
```
Merit List Finalized
  ↓
Admission Head generates Offer Letters → ApprovalWorkflow (status: pending_dean_approval)
  ↓
Dean reviews & approves → ApprovalWorkflow (status: pending_chair_approval)
  ↓
Program Chair approves → OfferLetter created + sent to applicant
  ↓
Applicant accepts/rejects via portal
```

**Acceptance Criteria:**
```
AC1: Offer letter PDF includes all required fields (program, fees, deadline)
AC2: Generation creates ApprovalWorkflow for Dean automatically
AC3: Approval chain enforces strict ordering (must approve in sequence)
AC4: PDF sent via email to applicant with acceptance deadline
AC5: Expired offers marked as expired automatically (after deadline)
```

---

#### 2. Applicant Offer Letter Portal (Self-Service)
**Purpose:** Applicants view, download, accept/reject offers (expand existing)

**Controllers:**
- `Applicant/OfferLetterController@index` ✓ (exists)
- `Applicant/OfferLetterController@show` ✓
- `Applicant/OfferLetterController@downloadPdf` ✓
- `Applicant/OfferLetterController@accept` ✓
- `Applicant/OfferLetterController@decline` ✓

**Verify existing implementation covers:**
- Download PDF
- Accept with e-signature / checkbox
- Decline with reason
- Email confirmation of acceptance
- Change status to "accepted" or "declined"

**Routes:** (should already exist)
```php
Route::prefix('applicant')->name('applicant.')->group(function () {
    Route::get('offer-letters', [Applicant\OfferLetterController::class, 'index'])->name('offer-letters.index');
    Route::get('offer-letters/{offerLetter}', [Applicant\OfferLetterController::class, 'show'])->name('offer-letters.show');
    Route::post('offer-letters/{offerLetter}/accept', [Applicant\OfferLetterController::class, 'accept'])->name('offer-letters.accept');
    Route::post('offer-letters/{offerLetter}/decline', [Applicant\OfferLetterController::class, 'decline'])->name('offer-letters.decline');
});
```

**Acceptance Criteria:**
```
AC1: Only applicants with active offers can accept/reject
AC2: Expired offers show "expired" badge, disable acceptance
AC3: Acceptance sends confirmation email + SMS
AC4: Applicant can view acceptance deadline clearly
```

---

#### 3. Enrollment Confirmation & Student Record Creation
**Purpose:** Confirm offer acceptance, create Student record with enrollment number

**Models:**
- `Student` ✓ (may enhance with `enrollment_confirmation_date`)

**Controllers:**
- `Admission/EnrollmentController@confirm` — mark applicant as enrolled, create Student
- `Admission/EnrollmentController@bulk` — bulk confirm

**Views:**
- `admission/enrollment/index.blade.php` — list applicants awaiting confirmation
- `admission/enrollment/confirm.blade.php` — detail view + confirm button

**Routes:**
```php
Route::middleware(['auth', 'role:admission_head|admin'])->prefix('admission')->name('admission.')->group(function () {
    Route::get('enrollment', [Admission\EnrollmentController::class, 'index'])->name('enrollment.index');
    Route::post('enrollment/{applicant}/confirm', [Admission\EnrollmentController::class, 'confirm'])->name('enrollment.confirm');
    Route::post('enrollment/bulk-confirm', [Admission\EnrollmentController::class, 'bulkConfirm'])->name('enrollment.bulk-confirm');
});
```

**Logic:**
```php
// On confirmation:
$student = Student::create([
    'user_id' => $applicant->user_id,
    'program_id' => $applicant->program_id,
    'batch_id' => $applicant->batch_id,
    'enrollment_number' => EnrollmentService::generateNumber($applicant),
    'status' => 'active',
    'enrolled_at' => now(),
]);

$applicant->update(['status' => 'enrolled']);
```

**Acceptance Criteria:**
```
AC1: Enrollment number auto-generated with format ENR-YYYY-PROGRAMCODE-#####
AC2: Student record created with correct batch assignment
AC3: User assigned 'student' role automatically
AC4: Applicant status changed to 'enrolled'
AC5: Welcome email sent with enrollment number + fee structure
```

---

#### 4. Enrollment Verification & Compliance
**Purpose:** Verify against seat matrix, ensure compliance with AICTE guidelines

**Models:**
- `SeatMatrix` ✓ (exists)

**Controllers:**
- `Admission/SeatMatrixController@verify` — check occupancy

**Views:**
- `admission/seat-matrix/compliance.blade.php` — occupancy report

**Routes:**
```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('admin/seat-matrix/{program}/occupancy', [Admin\SeatMatrixController::class, 'occupancy'])->name('seat-matrix.occupancy');
});
```

**Acceptance Criteria:**
```
AC1: Enrollment count matches SeatMatrix by program
AC2: Category-wise allocation (SC/ST/OBC/General) tracked
AC3: Compliance report shows % occupancy vs. AICTE guidelines
AC4: Warn if category-wise allocation breached
```

---

#### 5. Enrollment Fee Management
**Purpose:** Charge enrollment/seat fee, process payment before student activated

**Models:**
- `AdmissionPayment` ✓
- `FeeDemand` ✓

**Controllers:**
- Expand `Applicant/PaymentController` — add enrollment fee payment
- `Admission/EnrollmentController` — wait for fee confirmation before final enrollment

**Views:**
- `applicant/fees/enrollment-fee.blade.php` — enrollment fee payment prompt

**Routes:**
```php
Route::middleware(['auth', 'role:applicant'])->prefix('applicant')->name('applicant.')->group(function () {
    Route::get('enrollment-fee', [Applicant\PaymentController::class, 'enrollmentFee'])->name('enrollment-fee.show');
    Route::post('enrollment-fee/pay', [Applicant\PaymentController::class, 'payEnrollmentFee'])->name('enrollment-fee.pay');
});
```

**Workflow:**
```
Applicant accepts offer
  ↓
Enrollment fee demand created (configurable amount or %)
  ↓
Applicant navigates to /applicant/enrollment-fee, pays via payment gateway
  ↓
Payment verified by Accounts Officer
  ↓
Admission Head confirms enrollment (Student record created)
```

**Acceptance Criteria:**
```
AC1: Enrollment fee amount configurable per program
AC2: Applicant can pay via online gateway or request manual entry
AC3: Enrollment blocked if fee not verified
AC4: Fee payment linked to applicant in dashboard
```

---

### Database Models
```php
// 2026_06_07_110000_enhance_offer_letters.php
Schema::table('offer_letters', function (Blueprint $t) {
    $t->timestamp('declined_at')->nullable();
    $t->string('decline_reason')->nullable();
    $t->timestamp('acceptance_deadline')->nullable();
    $t->boolean('is_expired')->default(false);
});

// 2026_06_07_110001_enhance_students.php
Schema::table('students', function (Blueprint $t) {
    $t->timestamp('enrollment_confirmation_date')->nullable();
    $t->enum('enrollment_status', ['pending_confirmation', 'confirmed', 'active', 'suspended', 'graduated'])->default('pending_confirmation');
});
```

---

### Interdependencies
- **Blocks:** Phase 6 (Academic onboarding), Phase 5 (Fee management)
- **Enabled By:** Phase 1, Phase 2, Phase 3
- **Milestone Gate:** End-to-end workflow tested: Merit list → Offer Gen → Approval → Applicant Accept → Enrollment → Fee Charge

---

### User Stories
```
As an Admission Head, I want to generate offer letters in bulk for all selected applicants 
so that I don't have to create them one by one.

AC1: Select applicants from merit list
AC2: Click "Generate Offers" → creates 50+ offers
AC3: Routes all to Dean for approval workflow
AC4: Email sent to each applicant with PDF attachment

---

As a Program Chair, I want to see offer letters awaiting my approval 
and approve/reject with remarks so that decisions are documented.

AC1: /program-chair/approvals shows pending offer letters
AC2: Can click offer to review applicant details
AC3: Approve with optional remarks → changes to "final_approved"
AC4: Reject with reason → email sent to Admission Head

---

As an Applicant, I want to accept my offer letter so that I can proceed 
to pay enrollment fees and become a student.

AC1: Navigate to /applicant/offer-letters
AC2: Download PDF to review
AC3: Click "Accept Offer" → confirm acceptance
AC4: Receive confirmation email with next steps (enrollment fee payment)
```

---

---

## PHASE 5 — Academic Lifecycle Management
**Duration:** 3 weeks  
**Priority:** HIGH  
**Roles Impacted:** Dean, HOD, Program Chair, Faculty, Student, Exam Cell  
**Prerequisites:** Phase 1, Phase 2, Phase 4  
**Blocks:** Phase 8 (Analytics & Reporting)

### Features to Build

#### 1. Term / Semester Management & Promotion
**Purpose:** Define academic calendar, promote students to next term/semester

**Models:**
- `Term` ✓ (exists)
- `Semester` ✓
- `TermPromotion` ✓
- `AcademicCalendar` ✓

**Controllers:**
- `Academic/TermPromotionController@index` — term promotion queue
- `Academic/TermPromotionController@promote` — bulk promote students
- Existing: `Academic/TermPromotionController` (verify coverage)

**Views:**
- `academic/term-promotions/index.blade.php` — students awaiting promotion
- `academic/term-promotions/bulk-promote.blade.php` — select students, promote

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|hod|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('term-promotions', [Academic\TermPromotionController::class, 'index'])->name('term-promotions.index');
    Route::post('term-promotions/bulk-promote', [Academic\TermPromotionController::class, 'bulkPromote'])->name('term-promotions.bulk-promote');
    Route::get('academic-calendars', [Academic\AcademicCalendarController::class, 'index'])->name('academic-calendars.index');
});
```

**Promotion Rules:**
- Student with (exam results submitted + attendance ≥ 75% OR exemption) can be promoted
- Promotion workflow: HOD approves → Dean approves → status changes to next term
- Graduation check: if final semester, mark as graduated

**Acceptance Criteria:**
```
AC1: Bulk promotion respects eligibility rules (attendance, exam results)
AC2: HOD approval required before promotion finalized
AC3: Dean approval required for final sign-off
AC4: Graduation date set if last semester
AC5: Notification email sent to promoted students
```

---

#### 2. Enrollment & Subject Registration
**Purpose:** Ensure students enroll in correct program/batch each term, select elective subjects

**Models:**
- `Enrollment` ✓ (may enhance for subject-level tracking)
- `SubjectRegistration` (new) — student_id, subject_id, term_id, registered_at

**Controllers:**
- `Student/SubjectController@index` — view available subjects
- `Student/SubjectController@register` — register for elective
- `Student/SubjectController@deregister` — drop subject
- `Academic/EnrollmentController` — admin override enrollment

**Views:**
- `student/subjects/index.blade.php` — available subjects + registration form
- `student/subjects/my-subjects.blade.php` — registered subjects

**Routes:**
```php
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('subjects', [Student\SubjectController::class, 'index'])->name('subjects.index');
    Route::post('subjects/{subject}/register', [Student\SubjectController::class, 'register'])->name('subjects.register');
    Route::delete('subjects/{subject}', [Student\SubjectController::class, 'deregister'])->name('subjects.deregister');
});
```

**Validation:**
- Credit hour limit (e.g., max 18 credits per term)
- Prerequisite subjects (if defined)
- Core vs. elective allocation

**Acceptance Criteria:**
```
AC1: Student can only register for subjects in current term
AC2: Credit hour limit enforced (reject if exceeds)
AC3: Prerequisites checked before allowing registration
AC4: Can deregister up to deadline (configurable)
AC5: Registration locked after deadline
```

---

#### 3. Attendance Management (Existing, Enhance)
**Purpose:** Mark attendance, track per-subject and overall, alert low attendance

**Expand existing:** Teacher marks attendance, system calculates overall %

**Models:**
- `Attendance` ✓
- Add: `attendance_alert` (boolean) on Student if < threshold

**Controllers:**
- `Teacher/AttendanceController@mark` ✓ — already exists
- `Student/AttendanceController@view` ✓
- `Academic/AttendanceController@report` — overall attendance report

**Views:**
- `teacher/attendance/mark.blade.php` ✓ (exists)
- `student/attendance/index.blade.php` ✓
- `academic/attendance/report.blade.php` — program-wise attendance heatmap

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|hod|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('attendance-report', [Academic\AttendanceController::class, 'report'])->name('attendance-report');
    Route::get('attendance-report/export', [Academic\AttendanceController::class, 'export'])->name('attendance-report.export');
});
```

**Attendance Thresholds:**
- Warn if < 75% overall
- Alert Dean if < 65% (at-risk)
- Restrict exam entry if < 50% (may need exemption)

**Acceptance Criteria:**
```
AC1: Attendance % calculated correctly (present / total classes)
AC2: Subject-wise attendance shown separately
AC3: Low attendance alert triggers SMS/email to student + parent
AC4: Exam Cell can review attendance before exam eligibility
AC5: Report exportable as CSV for analysis
```

---

#### 4. Exam Management & Result Entry
**Purpose:** Create exams, mark results, publish grades

**Expand existing:** `Exam`, `ExamResult` models and controllers

**Models:**
- `Exam` ✓
- `ExamResult` ✓
- `AssessmentComponent` ✓ (for rubric-based grading)

**Controllers:**
- `Exam/ExamController@create` — define exam
- `Exam/ExamController@schedule` — set date/time/subject
- `Exam/ExamController@enterResults` — bulk result entry
- `Exam/ExamController@publish` — publish grades to students
- `Teacher/ExamController@enterMarks` — teacher enters marks
- `Exam/GradeController@calculate` — auto-calculate grades (A, B, C, etc.)

**Views:**
- `admin/exams/create.blade.php` ✓
- `admin/exams/enter-results.blade.php` ✓
- `exam-cell/exams/results-entry.blade.php` — bulk entry interface
- `exam-cell/exams/publish.blade.php` — publish confirmation

**Routes:**
```php
Route::middleware(['auth', 'role:exam_cell|dean_academics|admin'])->prefix('exam-cell')->name('exam-cell.')->group(function () {
    Route::get('exams/{exam}/enter-results', [ExamCell\ExamController::class, 'enterResults'])->name('exams.enter-results');
    Route::post('exams/{exam}/enter-results', [ExamCell\ExamController::class, 'storeResults'])->name('exams.store-results');
    Route::post('exams/{exam}/publish', [ExamCell\ExamController::class, 'publish'])->name('exams.publish');
    Route::get('exams/{exam}/grade-sheet', [ExamCell\ExamController::class, 'gradeSheet'])->name('exams.grade-sheet');
});
```

**Grading Scale:**
```
90-100: A+ (Grade Point 10)
80-89:  A  (Grade Point 9)
70-79:  B+ (Grade Point 8)
60-69:  B  (Grade Point 7)
50-59:  C  (Grade Point 6)
40-49:  D  (Grade Point 5)
< 40:   F  (Grade Point 0) — Failed
```

**Acceptance Criteria:**
```
AC1: Exam result entry supports bulk CSV upload or manual entry
AC2: Grades auto-calculated from marks based on grading scale
AC3: GPA calculated (avg of all subject grades)
AC4: Results published only after Dean approval (verification step)
AC5: Student cannot see results until published
AC6: Email sent to students once results published
```

---

#### 5. Academic Performance Analytics
**Purpose:** Track student performance, identify at-risk students, program-level analytics

**Controllers:**
- `Academic/AnalyticsController@topPerformers` — top 10 by GPA
- `Academic/AnalyticsController@atRiskStudents` — < 40% average
- `Academic/AnalyticsController@passRates` — by program
- `Academic/AnalyticsController@studentProgression` — term-wise progress

**Views:**
- `academic/analytics/index.blade.php` — dashboard with charts
- `academic/analytics/top-performers.blade.php` — detail list
- `academic/analytics/at-risk.blade.php` — detail list with intervention options

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('analytics', [Academic\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/top-performers', [Academic\AnalyticsController::class, 'topPerformers'])->name('analytics.top-performers');
    Route::get('analytics/at-risk', [Academic\AnalyticsController::class, 'atRiskStudents'])->name('analytics.at-risk');
    Route::get('analytics/pass-rates', [Academic\AnalyticsController::class, 'passRates'])->name('analytics.pass-rates');
});
```

**Acceptance Criteria:**
```
AC1: At-risk students identified by attendance < 75% OR avg marks < 40%
AC2: Charts show trend over 3 semesters
AC3: Pass rates by subject and program
AC4: Can drill down to individual student for intervention
```

---

#### 6. Transcript & Certificate Generation
**Purpose:** Generate academic transcripts, provisional certificates

**Controllers:**
- `Student/TranscriptController@view` — student views transcript
- `Student/TranscriptController@download` — PDF download
- `Admin/TranscriptController@issue` — admin issues official transcript
- `Admin/CertificateController@generate` — generate certificate

**Views:**
- `student/transcript.blade.php` — view transcript
- `pdf/transcript.blade.php` — PDF template
- `pdf/certificate.blade.php` — certificate template

**Routes:**
```php
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('transcript', [Student\TranscriptController::class, 'view'])->name('transcript.view');
    Route::get('transcript/download', [Student\TranscriptController::class, 'download'])->name('transcript.download');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('transcripts', [Admin\TranscriptController::class, 'index'])->name('transcripts.index');
    Route::post('transcripts/{student}/issue', [Admin\TranscriptController::class, 'issue'])->name('transcripts.issue');
    Route::get('certificates', [Admin\CertificateController::class, 'index'])->name('certificates.index');
    Route::post('certificates/{student}/generate', [Admin\CertificateController::class, 'generate'])->name('certificates.generate');
});
```

**Acceptance Criteria:**
```
AC1: Transcript shows all subjects, marks, grades, GPA
AC2: Only issued transcripts show official seal
AC3: Certificate generated after graduation status
AC4: Student can download provisional transcript anytime
AC5: Official transcript requests trackable (for compliance)
```

---

### Database Models
```php
// 2026_06_07_200000_create_subject_registrations.php
Schema::create('subject_registrations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('student_id')->constrained();
    $t->foreignId('subject_id')->constrained();
    $t->foreignId('term_id')->constrained();
    $t->integer('credit_hours');
    $t->timestamp('registered_at');
    $t->timestamp('deregistered_at')->nullable();
    $t->timestamps();
    $t->unique(['student_id', 'subject_id', 'term_id']);
});

// 2026_06_07_200001_enhance_exam_results.php
Schema::table('exam_results', function (Blueprint $t) {
    $t->string('grade')->nullable(); // A+, A, B+, B, C, D, F
    $t->decimal('grade_point', 3, 1)->nullable();
    $t->boolean('passed')->default(false);
    $t->timestamp('published_at')->nullable();
    $t->timestamp('published_by')->nullable();
});

// 2026_06_07_200002_enhance_students.php
Schema::table('students', function (Blueprint $t) {
    $t->decimal('gpa', 3, 2)->nullable();
    $t->decimal('attendance_percentage', 5, 2)->default(0);
    $t->boolean('attendance_alert')->default(false);
    $t->enum('academic_status', ['active', 'at_risk', 'suspended', 'graduated'])->default('active');
});
```

---

### Interdependencies
- **Blocks:** Phase 8 (Reporting), Phase 9 (Placement)
- **Enabled By:** Phase 1-4
- **Milestone Gate:** Complete academic year cycle: enrollment → attendance → exams → results → promotion

---

### User Stories
```
As a Faculty Member, I want to mark attendance for my classes 
so that the system tracks student participation accurately.

AC1: Navigate to /teacher/attendance/mark
AC2: Select batch, subject, date
AC3: Mark each student present/absent/late
AC4: Submit → attendance recorded
AC5: Attendance % updated in real-time on student dashboard

---

As a Dean, I want to see at-risk students (low attendance + low marks) 
so that I can intervene with counseling or remedial classes.

AC1: /academic/analytics/at-risk shows students with attendance < 75% AND avg < 40%
AC2: Can click student name to view profile + contact parent
AC3: Can flag for counselor follow-up
AC4: List exportable for action tracking

---

As an Exam Cell Officer, I want to enter exam results in bulk 
so that I don't have to create each result individually.

AC1: /exam-cell/exams/{exam}/enter-results allows CSV upload
AC2: Grades auto-calculated from marks
AC3: Bulk entry validates data before saving
AC4: Can publish results after verification
```

---

## PHASE 6 — Fee & Financial Management
**Duration:** 2 weeks  
**Priority:** HIGH  
**Roles Impacted:** Accounts Officer, Admission Head, Student, Parent  
**Prerequisites:** Phase 1, Phase 4, Phase 5  
**Blocks:** Phase 9 (Reporting)

### Features to Build

#### 1. Fee Structure & Demand Management
**Purpose:** Define semester/program-wise fees, generate demands, track collections

**Expand existing:** `FeeStructure`, `FeeDemand` models

**Controllers:**
- Existing: `Admin/FeeStructureController` (verify coverage)
- `Academic/FeeDemandController@generate` — auto-generate demands per batch/term
- `Academic/FeeDemandController@index` — view all demands
- `Academic/FeeDemandController@remind` — send payment reminders

**Views:**
- `academic/fee-demands/index.blade.php` — all demands
- `academic/fee-demands/generate.blade.php` — generate for batch/term

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|accounts_officer|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('fee-demands', [Academic\FeeDemandController::class, 'index'])->name('fee-demands.index');
    Route::post('fee-demands/generate', [Academic\FeeDemandController::class, 'generate'])->name('fee-demands.generate');
    Route::post('fee-demands/bulk-remind', [Academic\FeeDemandController::class, 'bulkRemind'])->name('fee-demands.bulk-remind');
});
```

**Fee Components:**
```
Tuition Fee (per term)
Lab Fee (if applicable)
Hostel Fee (if applicable)
Transport Fee (if applicable)
Library Fee (annual)
Exam Fee (per exam)
Activity Fee (annual)
```

**Acceptance Criteria:**
```
AC1: Fee structure defined per program/batch/term
AC2: Fee demands generated automatically on term start
AC3: Each demand linked to student + due date
AC4: Payment reminder SMS/email sent 1 week before due
AC5: Overdue calculation (if past due date + not fully paid)
```

---

#### 2. Payment Collection & Reconciliation
**Purpose:** Record payments, verify, reconcile with bank

**Expand existing:** `FeePayment`, `AdmissionPayment` models

**Controllers:**
- `Accounts/PaymentController@record` — record manual payment (check, transfer)
- `Accounts/PaymentController@verify` — mark payment verified
- `Accounts/PaymentController@reconcile` — bank reconciliation

**Views:**
- `departmental/accounts/payments/record.blade.php` — manual payment entry
- `departmental/accounts/payments/reconciliation.blade.php` — bank matching

**Routes:**
```php
Route::middleware(['auth', 'role:accounts_officer|admin'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::post('payments/record', [Accounts\PaymentController::class, 'record'])->name('payments.record');
    Route::post('payments/{payment}/verify', [Accounts\PaymentController::class, 'verify'])->name('payments.verify');
    Route::get('reconciliation', [Accounts\ReconciliationController::class, 'index'])->name('reconciliation.index');
    Route::post('reconciliation/match', [Accounts\ReconciliationController::class, 'match'])->name('reconciliation.match');
});
```

**Payment Methods:**
- Online (Razorpay, PayU integration)
- Check (manual entry)
- Bank transfer (manual entry + reconciliation)
- Cash (manual entry)

**Acceptance Criteria:**
```
AC1: Online payments auto-verified
AC2: Manual payments require Accounts Officer verification
AC3: Bank reconciliation matches payment transactions to fee demands
AC4: Outstanding amount = sum(demands) - sum(verified payments)
AC5: Overdue email sent if payment past due + unpaid
```

---

#### 3. Student Fee Dashboard
**Purpose:** Students track their fee status

**Expand existing:** `/student/fees`

**Views:**
- `student/fees/index.blade.php` — outstanding vs. paid summary
- `student/fees/details.blade.php` — per-demand breakdown

**Routes:** (should exist)
```php
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('fees', [Student\FeeController::class, 'index'])->name('fees.index');
    Route::get('fees/{demand}', [Student\FeeController::class, 'show'])->name('fees.show');
});
```

**Acceptance Criteria:**
```
AC1: Shows all fee demands (paid + outstanding)
AC2: Clearly displays due date and overdue status
AC3: Can download fee demand letter (PDF)
AC4: Pay button visible for outstanding fees
```

---

#### 4. Scholarship Disbursement
**Purpose:** Track and disburse scholarships to eligible students

**Expand existing:** `Scholarship`, `ApplicantScholarship`, `ScholarshipScheme` models

**Controllers:**
- `Academic/ScholarshipController@disburse` — disburse to eligible students
- `Academic/ScholarshipController@track` — track disbursement status

**Views:**
- `academic/scholarships/disburse.blade.php` — select scholarships, disburse
- `academic/scholarships/tracking.blade.php` — disbursement status

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|accounts_officer|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::post('scholarships/disburse', [Academic\ScholarshipController::class, 'disburse'])->name('scholarships.disburse');
    Route::get('scholarships/tracking', [Academic\ScholarshipController::class, 'tracking'])->name('scholarships.tracking');
});
```

**Disbursement Logic:**
```
For each eligible student with scholarship:
  Fee Demand - Scholarship Amount = Amount Due
  Disburse amount to student account (or reduce fee demand)
```

**Acceptance Criteria:**
```
AC1: Scholarships identified per student from applicant records
AC2: Disbursement reduces fee demand or adds student credit
AC3: Disbursal tracked with date and amount
AC4: Student fee dashboard shows scholarship deduction
```

---

#### 5. Outstanding & Collection Reports
**Purpose:** Track which students owe fees, generate collection reports

**Controllers:**
- `Accounts/ReportController@outstanding` — students with unpaid fees
- `Accounts/ReportController@collection` — monthly/term collection trend

**Views:**
- `departmental/accounts/reports/outstanding.blade.php` — aging report
- `departmental/accounts/reports/collection.blade.php` — trend chart

**Routes:**
```php
Route::middleware(['auth', 'role:accounts_officer|admin'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('reports/outstanding', [Accounts\ReportController::class, 'outstanding'])->name('reports.outstanding');
    Route::get('reports/collection', [Accounts\ReportController::class, 'collection'])->name('reports.collection');
    Route::post('reports/outstanding/export', [Accounts\ReportController::class, 'exportOutstanding'])->name('reports.outstanding.export');
});
```

**Acceptance Criteria:**
```
AC1: Outstanding report shows student name, amount due, days overdue
AC2: Sortable by amount, days, program
AC3: Can select students to send reminder email/SMS
AC4: Collection report shows monthly trend with target vs. actual
AC5: Exportable as CSV/Excel
```

---

### Database Models
```php
// Enhance existing migrations as needed
// 2026_06_07_300000_enhance_fee_structures.php
Schema::table('fee_structures', function (Blueprint $t) {
    if (!Schema::hasColumn('fee_structures', 'components')) {
        $t->json('components')->nullable(); // Array of fee components
    }
});

// 2026_06_07_300001_enhance_fee_demands.php
Schema::table('fee_demands', function (Blueprint $t) {
    if (!Schema::hasColumn('fee_demands', 'due_date')) {
        $t->date('due_date');
    }
    if (!Schema::hasColumn('fee_demands', 'overdue_since')) {
        $t->date('overdue_since')->nullable();
    }
});
```

---

### Interdependencies
- **Blocks:** Phase 9 (Reporting)
- **Enabled By:** Phase 1-5
- **Milestone Gate:** Complete fee lifecycle: demand → collection → reconciliation, with accurate outstanding tracking

---

### User Stories
```
As an Accounts Officer, I want to generate fee demands for all students 
at the start of each term so that students know what they owe.

AC1: Select program, batch, term → click "Generate Demands"
AC2: Demands created for each student with due date
AC3: Email sent to each student + parent with demand details
AC4: Student can view demand on dashboard

---

As a Student, I want to see my outstanding fees and due dates 
so that I can plan payments.

AC1: Navigate to /student/fees
AC2: See summary: Total Due, Paid, Outstanding
AC3: See each demand with due date and payment status
AC4: Can pay online via button

---

As an Accounts Officer, I want to track which students are overdue 
so that I can follow up for collections.

AC1: /accounts/reports/outstanding shows all unpaid students
AC2: Sorted by days overdue (oldest first)
AC3: Can bulk select and send reminder SMS/email
AC4: Export to CSV for collection team
```

---

## PHASE 7 — Placement & Career Services
**Duration:** 2 weeks  
**Priority:** HIGH  
**Roles Impacted:** Placement Officer, Students, Faculty Coordinators  
**Prerequisites:** Phase 1, Phase 4  
**Blocks:** Phase 9 (Reporting)

### Features to Build

#### 1. Placement Drive Management
**Purpose:** Schedule and manage placement drives

**Expand existing:** `PlacementDrive`, `Company` models

**Controllers:**
- `Placement/DriveController@create` — schedule new drive
- `Placement/DriveController@inviteCompanies` — add companies
- `Placement/DriveController@inviteStudents` — send invitations
- `Placement/DriveController@manage` — day-of management

**Views:**
- `placement/drives/create.blade.php` — drive scheduling
- `placement/drives/{drive}/manage.blade.php` — drive dashboard
- `placement/drives/{drive}/interview-schedule.blade.php` — interview slots

**Routes:**
```php
Route::middleware(['auth', 'role:placement|admin'])->prefix('placement')->name('placement.')->group(function () {
    Route::resource('drives', Placement\DriveController::class);
    Route::post('drives/{drive}/invite-companies', [Placement\DriveController::class, 'inviteCompanies'])->name('drives.invite-companies');
    Route::post('drives/{drive}/invite-students', [Placement\DriveController::class, 'inviteStudents'])->name('drives.invite-students');
    Route::get('drives/{drive}/manage', [Placement\DriveController::class, 'manage'])->name('drives.manage');
});
```

**Drive Fields:**
```
name, company_id, date, time, location, batch_id
roles (array), package (salary), selection_steps (array)
status (upcoming, in_progress, completed, cancelled)
```

**Acceptance Criteria:**
```
AC1: Create drive with company, date, location, package info
AC2: Invite eligible students (based on batch/graduation criteria)
AC3: Students receive invitation email
AC4: Drive details visible on student portal
AC5: Can update drive status day-of
```

---

#### 2. Student Placement Portal
**Purpose:** Students register for drives, view placements

**Expand existing:** Student placement views

**Controllers:**
- `Student/PlacementController@drives` — available drives
- `Student/PlacementController@register` — register for drive
- `Student/PlacementController@placements` — my placements

**Views:**
- `student/placement/drives.blade.php` — filterable drive list
- `student/placement/my-placements.blade.php` — offers received

**Routes:**
```php
Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('placement/drives', [Student\PlacementController::class, 'drives'])->name('placement.drives');
    Route::post('placement/drives/{drive}/register', [Student\PlacementController::class, 'register'])->name('placement.register');
    Route::get('placement/placements', [Student\PlacementController::class, 'placements'])->name('placement.placements');
});
```

**Acceptance Criteria:**
```
AC1: Student sees available drives with company, package, date
AC2: Can register for drive (if eligible)
AC3: View registered drives
AC4: Placements show offers received with company name, package, offer letter
AC5: Can download offer letter PDF
```

---

#### 3. Offer Management & Acceptance
**Purpose:** Track placement offers from companies

**Models:**
- `Placement` (Offer) — student_id, company_id, role, package, status (offered, accepted, rejected)

**Controllers:**
- `Placement/OfferController@accept` — student accepts offer
- `Placement/OfferController@reject` — student rejects offer
- `Placement/OfferController@track` — view offer status

**Views:**
- `placement/offers/show.blade.php` — offer details
- Modal: offer acceptance confirmation

**Routes:**
```php
Route::middleware(['auth'])->prefix('placement')->name('placement.')->group(function () {
    Route::get('offers/{offer}', [Placement\OfferController::class, 'show'])->name('offers.show');
    Route::post('offers/{offer}/accept', [Placement\OfferController::class, 'accept'])->name('offers.accept');
    Route::post('offers/{offer}/reject', [Placement\OfferController::class, 'reject'])->name('offers.reject');
});
```

**Acceptance Criteria:**
```
AC1: Placement officer can create offers in system after negotiation
AC2: Student receives offer email
AC3: Student can accept/reject on portal
AC4: Acceptance recorded with date
AC5: Placement marked as "placed" once accepted
```

---

#### 4. Placement Statistics & Reporting
**Purpose:** Track placement rates and outcomes

**Controllers:**
- `Placement/ReportController@statistics` — overall placement stats
- `Placement/ReportController@byProgram` — program-wise breakdown
- `Placement/ReportController@byCompany` — company-wise breakdown

**Views:**
- `placement/reports/statistics.blade.php` — dashboard charts
- `placement/reports/by-program.blade.php` — detail by program
- `placement/reports/by-company.blade.php` — detail by company

**Routes:**
```php
Route::middleware(['auth', 'role:placement|admin'])->prefix('placement')->name('placement.')->group(function () {
    Route::get('reports/statistics', [Placement\ReportController::class, 'statistics'])->name('reports.statistics');
    Route::get('reports/by-program', [Placement\ReportController::class, 'byProgram'])->name('reports.by-program');
    Route::get('reports/by-company', [Placement\ReportController::class, 'byCompany'])->name('reports.by-company');
});
```

**Metrics:**
```
Total Registered (eligible students)
Total Offers Received
Placement Rate (%)
Average Package
Highest Package
Lowest Package
Program-wise placement rate
Company-wise placement stats
```

**Acceptance Criteria:**
```
AC1: Placement rate = (students with accepted offers) / (eligible students) * 100
AC2: Stats accurate and updated in real-time
AC3: Can filter by batch/program/year
AC4: Charts show trend over years
AC5: Exportable as PDF/CSV for AICTE compliance
```

---

#### 5. Pre-Placement Training (Optional)
**Purpose:** Track training courses before placement season

**Models:**
- `PrePlacementTraining` — name, description, start_date, end_date
- `StudentTrainingEnrollment` — student_id, training_id, status, completion_date

**Controllers:**
- `Placement/TrainingController@index` — list training programs
- `Placement/TrainingController@enroll` — student enrolls
- `Placement/TrainingController@complete` — mark complete

**Views:**
- `placement/training/index.blade.php`
- `student/placement/training.blade.php`

**Routes:**
```php
Route::middleware(['auth', 'role:placement|admin'])->prefix('placement')->name('placement.')->group(function () {
    Route::resource('training', Placement\TrainingController::class);
});

Route::middleware(['auth', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('placement/training', [Student\PlacementController::class, 'training'])->name('placement.training');
});
```

---

### Database Models
```php
// 2026_06_07_400000_enhance_placements.php
Schema::table('placements', function (Blueprint $t) {
    if (!Schema::hasColumn('placements', 'offer_status')) {
        $t->enum('offer_status', ['offered', 'accepted', 'rejected', 'expired'])->default('offered');
    }
    if (!Schema::hasColumn('placements', 'accepted_at')) {
        $t->timestamp('accepted_at')->nullable();
    }
});

// 2026_06_07_400001_create_placement_drives.php (if not exists)
// Check if table exists before creating

// 2026_06_07_400002_create_pre_placement_trainings.php
Schema::create('pre_placement_trainings', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->text('description')->nullable();
    $t->date('start_date');
    $t->date('end_date');
    $t->foreignId('created_by')->constrained('users');
    $t->timestamps();
});

// 2026_06_07_400003_create_student_training_enrollments.php
Schema::create('student_training_enrollments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('student_id')->constrained();
    $t->foreignId('training_id')->constrained('pre_placement_trainings');
    $t->enum('status', ['enrolled', 'in_progress', 'completed', 'dropped'])->default('enrolled');
    $t->timestamp('completed_at')->nullable();
    $t->timestamps();
});
```

---

### Interdependencies
- **Blocks:** Phase 9 (Reporting), AICTE compliance reports
- **Enabled By:** Phase 1-6
- **Milestone Gate:** Complete placement cycle: drive → registration → interview → offer → acceptance

---

### User Stories
```
As a Placement Officer, I want to schedule a placement drive 
with a company and invite eligible students.

AC1: Create drive with company name, date, location, roles, package
AC2: Select eligible batches → auto-invite eligible students
AC3: Students receive email invitation
AC4: Drive status managed: upcoming → in_progress → completed

---

As a Student, I want to register for placement drives and track offers 
so that I can secure a job before graduation.

AC1: Navigate to /student/placement/drives
AC2: See available drives (company, package, date, roles)
AC3: Click "Register" for interested drives
AC4: View registered drives and their status
AC5: Receive offer email → click link to view/accept
AC6: /student/placement/placements shows all accepted offers

---

As a Placement Coordinator, I want to see what % of students 
are placed so that I can report to AICTE.

AC1: /placement/reports/statistics shows placement rate
AC2: Filter by batch/program/year
AC3: Shows: total eligible, total registered, total offered, total placed
AC4: Can drill down to student list for each category
AC5: PDF report exportable for compliance
```

---

## PHASE 8 — Advanced Reporting & Analytics
**Duration:** 2 weeks  
**Priority:** MEDIUM  
**Roles Impacted:** Director, Dean, Admission Head, Accounts, Institute Owner  
**Prerequisites:** Phase 1-7  
**Blocks:** None (independent)

### Features to Build

#### 1. Admission Funnel Analytics
**Purpose:** Track conversion from lead to enrollment with visualizations

**Expand existing:** `Admission/ReportController`

**Controllers:**
- `Admission/ReportController@funnel` — conversion visualization
- `Admission/ReportController@source` — source breakdown
- `Admission/ReportController@conversion` — conversion rates by source/program

**Views:**
- `admission/reports/funnel.blade.php` — funnel chart + table
- `admission/reports/source-analysis.blade.php` — source breakdown
- `admission/reports/cohort-comparison.blade.php` — year-over-year

**Routes:**
```php
Route::middleware(['auth', 'role:admission_head|dean_academics|admin'])->prefix('admission')->name('admission.')->group(function () {
    Route::get('reports/funnel', [Admission\ReportController::class, 'funnel'])->name('reports.funnel');
    Route::get('reports/source-analysis', [Admission\ReportController::class, 'sourceAnalysis'])->name('reports.source-analysis');
    Route::get('reports/cohort-comparison', [Admission\ReportController::class, 'cohortComparison'])->name('reports.cohort-comparison');
});
```

**Metrics:**
```
Stage 1: Leads Generated
Stage 2: Applications Submitted
Stage 3: Documents Verified
Stage 4: Selected (After Selection Process)
Stage 5: Offers Issued
Stage 6: Offers Accepted
Stage 7: Enrollment Confirmed
Stage 8: Fee Paid (Active)

Conversion % = (count at stage X / count at stage 1) * 100
Drop-off = (count at stage X - count at stage X+1)
```

**Acceptance Criteria:**
```
AC1: Funnel shows all stages with drop-off visualization
AC2: Filterable by program, batch, source, date range
AC3: Source breakdown shows which channels convert best
AC4: Cohort comparison shows year-over-year trends
AC5: Exportable as PDF/CSV with charts
```

---

#### 2. Academic Performance Reports
**Purpose:** Student performance analytics for institutional improvement

**Controllers:**
- `Academic/ReportController@performanceByProgram` — program-wise pass rates
- `Academic/ReportController@studentTrends` — individual student progression
- `Academic/ReportController@subjectAnalysis` — subject-wise performance
- `Academic/ReportController@facultyEffectiveness` — faculty impact on grades

**Views:**
- `academic/reports/program-performance.blade.php` — heatmap by program/term
- `academic/reports/subject-analysis.blade.php` — subject-wise stats
- `academic/reports/faculty-effectiveness.blade.php` — faculty metrics

**Routes:**
```php
Route::middleware(['auth', 'role:dean_academics|admin'])->prefix('academic')->name('academic.')->group(function () {
    Route::get('reports/program-performance', [Academic\ReportController::class, 'performanceByProgram'])->name('reports.program-performance');
    Route::get('reports/subject-analysis', [Academic\ReportController::class, 'subjectAnalysis'])->name('reports.subject-analysis');
    Route::get('reports/faculty-effectiveness', [Academic\ReportController::class, 'facultyEffectiveness'])->name('reports.faculty-effectiveness');
});
```

**Metrics:**
```
Pass Rate = (students with grade ≠ F) / total enrolled * 100
GPA Average = sum(all grades) / count(grades)
Subject Difficulty = 1 - (pass rate for subject)
Faculty Effectiveness = avg(student marks) in subject taught by faculty
```

**Acceptance Criteria:**
```
AC1: Program performance shows pass rate by term/year
AC2: Can identify weak subjects (low pass rates)
AC3: Faculty effectiveness identifies high/low performing teachers
AC4: Student progression shows term-wise GPA trend
AC5: Charts interactive (drill down to student list)
```

---

#### 3. Financial Dashboard & Reporting
**Purpose:** Revenue, collections, outstanding tracking

**Expand existing:** `Accounts/ReportController`

**Controllers:**
- `Accounts/ReportController@revenue` — revenue collected vs. target
- `Accounts/ReportController@outstanding` — aging report
- `Accounts/ReportController@collection-trend` — monthly trend
- `Accounts/ReportController@expense` — scholarship disbursements (if tracking)

**Views:**
- `departmental/accounts/reports/revenue.blade.php` — KPI cards + chart
- `departmental/accounts/reports/outstanding.blade.php` — aging analysis
- `departmental/accounts/reports/collection-trend.blade.php` — monthly chart

**Routes:**
```php
Route::middleware(['auth', 'role:accounts_officer|dean_academics|admin'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('reports/revenue', [Accounts\ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/outstanding-aging', [Accounts\ReportController::class, 'outstandingAging'])->name('reports.outstanding-aging');
    Route::get('reports/collection-trend', [Accounts\ReportController::class, 'collectionTrend'])->name('reports.collection-trend');
});
```

**Metrics:**
```
Total Billed = sum(all fee demands)
Total Collected = sum(verified payments)
Outstanding = Billed - Collected
Collection Rate = (Collected / Billed) * 100
Overdue = payments past due date (grouped by days: 0-30, 31-60, >60)
```

**Acceptance Criteria:**
```
AC1: Revenue dashboard shows KPI cards (billed, collected, outstanding)
AC2: Outstanding aging shows distribution by days overdue
AC3: Monthly trend chart shows 12-month collection history
AC4: Filterable by program, batch, date range
AC5: Exportable as CSV/PDF with detailed breakdowns
```

---

#### 4. Compliance & Regulatory Reports
**Purpose:** Generate AICTE, statutory compliance reports

**Controllers:**
- `Admin/ComplianceController@aicteReport` — AICTE statutory return
- `Admin/ComplianceController@placementCompliance` — placement % for AICTE
- `Admin/ComplianceController@enrollmentCompliance` — seats vs. enrolled

**Views:**
- `admin/compliance/aicte-report.blade.php` — statutory form
- `admin/compliance/placement-compliance.blade.php`
- `admin/compliance/enrollment-compliance.blade.php`

**Routes:**
```php
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('compliance/aicte-report', [Admin\ComplianceController::class, 'aicteReport'])->name('compliance.aicte-report');
    Route::get('compliance/placement', [Admin\ComplianceController::class, 'placementCompliance'])->name('compliance.placement');
    Route::get('compliance/enrollment', [Admin\ComplianceController::class, 'enrollmentCompliance'])->name('compliance.enrollment');
    Route::post('compliance/export', [Admin\ComplianceController::class, 'export'])->name('compliance.export');
});
```

**AICTE Metrics:**
```
Total Enrolled (by program)
Total Faculty (with qualifications)
Faculty Student Ratio
Placement Rate (%)
Pass Rate (%)
Average Package (if applicable)
```

**Acceptance Criteria:**
```
AC1: AICTE report shows all required metrics
AC2: Placement rate calculated as specified
AC3: Faculty count includes qualifications (PhD, etc.)
AC4: Report exportable in AICTE-approved format
AC5: Historical reports stored for audit trail
```

---

#### 5. Dashboards with Real-Time Aggregation
**Purpose:** Executive dashboards with live KPIs

**Controllers:**
- `Admin/ExecutiveDashboardController@index` — real-time KPIs
- `Admin/ExecutiveDashboardController@snapshot` — system snapshot

**Views:**
- `admin/executive-dashboard/index.blade.php` — live cards + charts
- Component: `components/kpi-card.blade.php` — reusable KPI display

**Routes:**
```php
Route::middleware(['auth', 'role:admin|dean_academics'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('executive-dashboard', [Admin\ExecutiveDashboardController::class, 'index'])->name('executive-dashboard');
    Route::get('dashboard-snapshot', [Admin\ExecutiveDashboardController::class, 'snapshot'])->name('dashboard-snapshot');
});
```

**Key Metrics:**
```
Students (active, graduated, dropouts)
Revenue (collected, target, variance %)
Placements (placed, unplaced, %)
Applications (pending, approved, rejected)
Approvals (pending, overdue count)
Fees (outstanding, overdue, collection %)
```

**Caching Strategy:**
- Cache aggregation queries for 5 min
- Real-time for pending approvals
- Use queue for heavy reports (generate in background)

**Acceptance Criteria:**
```
AC1: Dashboard loads < 2 seconds with caching
AC2: All KPIs accurate and up-to-date
AC3: Can drill down to detail reports
AC4: Charts interactive (hover for data)
AC5: Mobile-responsive layout
```

---

### Interdependencies
- **Blocks:** None
- **Enabled By:** Phase 1-7
- **Milestone Gate:** All critical reports tested, AICTE compliance validated

---

### User Stories
```
As an Institute Director, I want to see live KPIs on my dashboard 
(students, revenue, placements) so that I know system health at a glance.

AC1: Dashboard shows: Total Students, Revenue Collected, Placement Rate, Pending Approvals
AC2: Each metric links to detail report
AC3: Dashboard loads in < 2 seconds
AC4: Metrics refresh every 5 minutes

---

As a Dean of Academics, I want to analyze which subjects have low pass rates 
so that I can improve curriculum and teaching.

AC1: /academic/reports/subject-analysis shows all subjects with pass rates
AC2: Can sort by pass rate (lowest first)
AC3: Drill down to student list for weak subjects
AC4: Identify at-risk students in weak subjects
```

---

## PHASE 9 — Mobile App & API Layer (Optional, 3+ months)
**Duration:** 4 weeks  
**Priority:** MEDIUM  
**Roles Impacted:** All (via mobile)  
**Prerequisites:** Phase 1-8

### Features (High-Level Overview)
- REST API for all role portals
- Mobile app (iOS/Android) with key features
- Push notifications
- Offline capability for critical workflows

### Skip this phase for MVP. Complete web portals first, then add mobile as expansion.

---

---

# PHASE TIMELINE SUMMARY

```
Week 1-2:   PHASE 1 — Role & Permission Management
Week 3-4:   PHASE 2 — Role-Specific Dashboards
Week 5-6:   PHASE 3 — Approval Workflows & Escalation
Week 7-8:   PHASE 4 — Offer Letter & Enrollment
Week 9-11:  PHASE 5 — Academic Lifecycle Management
Week 12-13: PHASE 6 — Fee & Financial Management
Week 14-15: PHASE 7 — Placement & Career Services
Week 16-17: PHASE 8 — Reporting & Analytics
Week 18+:   Polish, testing, deployment, Phase 9 (optional mobile)

TOTAL: 4.25 months (17-18 weeks) for core system
EXTENDED: 6-9 months with mobile + extensive testing + customizations
```

---

# PARALLELIZATION OPPORTUNITIES

**Can run in parallel (independent):**
- Phase 2 (dashboards) + Phase 3 (approvals) — once Phase 1 complete
- Phase 6 (Finance) + Phase 7 (Placement) — independent workflows (once Phase 1-4 done)

**Must be sequential:**
- Phase 1 → Phase 2, Phase 3, Phase 4, Phase 5
- Phase 4 → Phase 5 (academic onboarding depends on enrollment)
- Phase 5 → Phase 6 (fee demands tied to enrollment)
- Phases 1-7 → Phase 8 (reporting depends on all data)

**Optimized schedule (6 months):**
```
Weeks 1-2:   Phase 1
Weeks 3-4:   Phase 2 (parallel: Phase 3 starts)
Weeks 3-6:   Phase 3 (parallel: Phase 2 continues)
Weeks 5-8:   Phase 4 (parallel: Phase 6 starts for design)
Weeks 9-11:  Phase 5 (parallel: Phase 7 starts for design)
Weeks 11-13: Phase 6 (parallel: Phase 7 continues)
Weeks 13-15: Phase 7
Weeks 16-17: Phase 8
Weeks 18+:   Testing, bug fixes, go-live
```

---

# STAKEHOLDER APPROVAL GATES

| Phase | Gate Criteria |
|-------|--------------|
| 1 | Role definitions approved, feature matrix finalized |
| 2 | All 9 dashboards reviewed + approved |
| 3 | Approval workflows tested end-to-end |
| 4 | Offer letter workflow + enrollment tested with real payment |
| 5 | Academic year cycle tested (enrollment → exams → promotion) |
| 6 | Fee generation, collection, outstanding tracking validated |
| 7 | Placement drive + offer workflow tested with students |
| 8 | All reports tested + AICTE compliance verified |

---

# RISK MITIGATION

**High-Risk Items:**
1. **Payment Gateway Integration** (Phase 4, 6) — Use sandbox first, test extensively
2. **PDF Generation at Scale** (Phase 4, 8) — Use queue jobs, cache generated PDFs
3. **Database Performance** (Phase 8) — Test reports with 10k+ students
4. **Email/SMS Delivery** (All phases) — Use reliable provider (SendGrid, Twillio), queue jobs

**Testing Strategy Per Phase:**
- Unit tests for business logic (services)
- Integration tests for workflows
- E2E tests for user-facing features
- Load testing for reports (Phase 8)

---

# SUCCESS METRICS

1. **System Uptime:** 99.5% availability
2. **Page Load Time:** < 2 sec (95th percentile)
3. **User Adoption:** 90%+ of staff using system within 3 months
4. **Data Accuracy:** 100% reconciliation of fees, enrollments, exams
5. **Compliance:** 100% AICTE compliance in reports
6. **Support Tickets:** < 2 per 100 active users per month (after stabilization)

---

# BUDGET & RESOURCE ALLOCATION

**Team Composition (assumed):**
- 1 Architect/Tech Lead
- 2 Backend Developers (Laravel)
- 1 Frontend Developer (Blade/Bootstrap)
- 1 QA Engineer
- 1 DevOps/DBA

**Timeline: 6 months @ cost TBD**

---

END OF ROADMAP
