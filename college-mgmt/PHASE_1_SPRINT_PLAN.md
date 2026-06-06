# Phase 1: Role & Permission Hierarchy — Sprint Plan

**Duration:** 2 weeks (10 working days)  
**Status:** Starting 2026-06-06  
**Goal:** Define 9 roles with program-scoped permissions, audit logging

---

## Sprint Breakdown

### Week 1: Data Model & Architecture (Days 1-5)

#### Day 1: Models & Migrations
- [ ] Create `RolePermissionMatrix` model
- [ ] Create `RoleFeatureAccess` model  
- [ ] Create `UserRole` model (replaces direct Spatie role_user)
- [ ] Create `AuditLog` model
- [ ] Write migrations for all 4 models
- [ ] Extend User model: program scoping, primary role

**Deliverable:** Database schema complete, models with relationships defined

#### Day 2: Controllers & Routes
- [ ] Create `Admin/RolePermissionController` — manage role hierarchy, inheritance
- [ ] Create `Admin/UserRoleController` — assign/revoke roles to users
- [ ] Create `Admin/AuditController` — view audit log
- [ ] Create `Admin/RoleFeatureAccessController` — manage feature access matrix
- [ ] Add routes for all CRUD operations

**Deliverable:** Route structure in place, basic CRUD endpoints

#### Day 3: Views & Forms (Part 1)
- [ ] `admin/roles/hierarchy.blade.php` — visualize role hierarchy tree
- [ ] `admin/roles/permissions.blade.php` — permission matrix per role
- [ ] `admin/roles/feature-access.blade.php` — feature access matrix
- [ ] Create forms for role creation/editing

**Deliverable:** Admin can view and manage role hierarchy

#### Day 4: User Role Assignment Views
- [ ] `admin/users/assign-role.blade.php` — form to assign role to user
- [ ] `admin/users/roles.blade.php` — table of all user-role assignments
- [ ] Add "Manage Roles" link to existing user admin views

**Deliverable:** Admin can assign/revoke roles, see active roles per user

#### Day 5: Audit Log Views & Reports
- [ ] `admin/audit-log/index.blade.php` — searchable audit table
- [ ] `admin/audit-log/show.blade.php` — detailed change view
- [ ] Add filters: date range, actor, action type, target

**Deliverable:** Audit log accessible and searchable

---

### Week 2: Middleware & Access Control (Days 6-10)

#### Day 6: Program Scoping
- [ ] Update `AuthMiddleware` to check program scoping
- [ ] Add `ProgramScope` trait to Controllers that need filtering
- [ ] Test: Program Chair for Program A cannot see Program B data
- [ ] Test: Admin can see all programs

**Deliverable:** Program-level access control working

#### Day 7: Feature Access Control
- [ ] Create `FeatureAccess` middleware/gate
- [ ] Define feature codes for all 9 roles:
  - `exam.enter_marks` → exam_cell, dean, admin
  - `admission.approve_offers` → dean, admission_head
  - `approval.dean_sign_off` → dean, admin
  - etc. (25+ features)
- [ ] Implement `@canAccess('exam.enter_marks')` blade directive

**Deliverable:** Feature-level access control enforced

#### Day 8: Role Hierarchy Inheritance
- [ ] Implement role inheritance logic (admin > dean > program_chair > faculty)
- [ ] Higher role inherits lower role permissions (optional override)
- [ ] Test permission cascading

**Deliverable:** Hierarchy working, inheritance respected

#### Day 9: Testing & Bug Fixes
- [ ] Unit tests: RolePermissionMatrix queries, program scoping
- [ ] Integration tests: User with role X can access feature Y
- [ ] Manual testing: All 9 roles in real scenarios
- [ ] Fix bugs discovered

**Deliverable:** Phase 1 passes all tests

#### Day 10: Documentation & Handoff
- [ ] Update CLAUDE.md with role definitions
- [ ] Update GUIDE.md with permission matrix table
- [ ] Create role-by-role access guide for admins
- [ ] Demo Phase 1 to stakeholders
- [ ] Commit & push to Phase 1 branch

**Deliverable:** Phase 1 complete, documented, ready for Phase 2

---

## Acceptance Criteria (Definition of Done)

### A. Role Hierarchy
- [ ] 9 roles defined: admin, dean, chair, hod, exam_cell, faculty, cmc, director, owner
- [ ] Hierarchy stored in `RolePermissionMatrix`
- [ ] Admin can view hierarchy tree in UI
- [ ] Inheritance logic: higher role ⊇ lower role permissions

### B. Program Scoping
- [ ] Role can be assigned globally or per-program
- [ ] Program Chair assigned to Program A cannot see Program B
- [ ] Program Chair dashboard filters by assigned program
- [ ] Admin can see all programs (no scoping)
- [ ] Attempting to access other program via URL returns 403

### C. Feature Access
- [ ] 25+ features defined (exam.enter_marks, admission.approve, etc.)
- [ ] Each role has feature matrix (view/create/edit/approve/delete levels)
- [ ] Blade directive `@canAccess('feature.code')` works
- [ ] Unauthorized feature access returns error message or 403

### D. Audit Logging
- [ ] Every role assignment logged with actor_id, timestamp
- [ ] Every role revocation logged
- [ ] Every permission change logged
- [ ] Audit log shows before/after JSON diff
- [ ] Only admin/owner can view audit log
- [ ] Audit log searchable by date, actor, action

### E. User Interface
- [ ] Admin can view role hierarchy tree
- [ ] Admin can assign role to user (pick user, pick role, pick program if applicable)
- [ ] Admin can revoke role from user
- [ ] Admin can view audit log with search filters
- [ ] All UIs responsive on mobile/tablet

### F. Permissions & Access
- [ ] Non-admin users cannot access role management pages
- [ ] Non-admin users cannot view audit log
- [ ] Middleware checks both role + feature access before allowing request
- [ ] Program Chair cannot access role management even for their program

---

## Key Implementation Notes

### SQLite Considerations
- AuditLog.changes stored as JSON — use `json_extract()` if needed in queries
- Program scoping: add `WHERE user_roles.program_id = ? OR user_roles.program_id IS NULL` for global roles
- RolePermissionMatrix might be frequently queried — consider caching with `redis` or app cache

### Code Patterns
Use existing patterns from codebase:
- Models: follow `Student`, `Teacher`, `Applicant` structure
- Controllers: follow `Admin/ProgramController`, `Admission/ApplicantController`
- Views: follow `layouts/admin.blade.php` sidebar navigation
- Routes: follow `/admin/*` prefix pattern

### Testing Strategy
1. **Unit Tests:** Role hierarchy logic, permission matrix queries
2. **Integration Tests:** User with role X can access controller Y
3. **Acceptance Tests:** 9 roles assigned, verified access to real pages
4. **Manual Testing:** Real user logs in as dean, verify program-scoped data

---

## Blockers & Risks

### Risk 1: Spatie Permission conflicts
- Current system uses Spatie roles directly
- Phase 1 introduces `UserRole` table, which duplicates Spatie's `model_has_roles`
- **Mitigation:** Slowly migrate away from Spatie (Phase 1 keeps both, Phase 2 removes Spatie)

### Risk 2: Program scoping not enforced everywhere
- If even one query forgets to filter by program, Program Chair sees all data
- **Mitigation:** Create `ProgramScope` trait that all controllers use; test all queries

### Risk 3: Performance: Role hierarchy + feature access checks on every request
- Could slow down page loads
- **Mitigation:** Cache role/feature matrix in app cache, refresh every 5 min

### Risk 4: Role inheritance bugs
- If inheritance logic wrong, admin doesn't have faculty permissions
- **Mitigation:** Unit test inheritance thoroughly, manual verification

---

## Milestone Gate (Before Phase 2)

✅ All 9 roles defined and accessible  
✅ Program scoping enforced (Program Chair sees only assigned program)  
✅ Feature access control working (@canAccess blade directive)  
✅ Audit log tracking all role changes  
✅ 2-3 users tested in each role (real user feedback gathered)  
✅ CLAUDE.md & GUIDE.md updated with role definitions  

**Go/No-Go Decision:** After Day 10 testing, stakeholders sign off before Phase 2 starts

---

## Files to Create/Modify

**New Files:**
- `app/Models/RolePermissionMatrix.php`
- `app/Models/RoleFeatureAccess.php`
- `app/Models/UserRole.php`
- `app/Models/AuditLog.php`
- `app/Http/Controllers/Admin/RolePermissionController.php`
- `app/Http/Controllers/Admin/UserRoleController.php`
- `app/Http/Controllers/Admin/AuditController.php`
- `app/Http/Controllers/Admin/RoleFeatureAccessController.php`
- `app/Http/Middleware/ProgramScope.php`
- `app/Http/Middleware/FeatureAccess.php`
- `database/migrations/2026_06_06_000000_create_role_permission_matrices.php`
- `database/migrations/2026_06_06_000001_create_role_feature_access.php`
- `database/migrations/2026_06_06_000002_create_user_roles.php`
- `database/migrations/2026_06_06_000003_create_audit_logs.php`
- `resources/views/admin/roles/*.blade.php` (5 files)
- `resources/views/admin/users/assign-role.blade.php`
- `resources/views/admin/audit-log/*.blade.php` (2 files)
- `tests/Unit/RoleHierarchyTest.php`
- `tests/Integration/ProgramScopeTest.php`

**Modified Files:**
- `app/Models/User.php` (add program_scoping, primary_role)
- `routes/web.php` (add admin role management routes)
- `database/seeders/DemoDataSeeder.php` (seed 9 roles with permissions)
- `CLAUDE.md` (add role definitions)
- `GUIDE.md` (add role matrix table)

---

## Success Metrics

| Metric | Target | Actual |
|--------|--------|--------|
| Phase 1 completion | Day 10 | — |
| Code coverage | >80% | — |
| All 9 roles defined | ✅ | — |
| Program scoping tests passing | 100% | — |
| Audit log tests passing | 100% | — |
| Stakeholder sign-off | ✅ | — |
| Time to Phase 2 start | Day 11 | — |

---

*Update this document daily with progress. Commit to `claude/focused-rubin-Uo1Iz` branch.*
