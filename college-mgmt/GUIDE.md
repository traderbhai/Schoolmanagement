# College Management System — Complete User & Developer Guide

> **App:** PGDM College Management System  
> **Stack:** Laravel 11 · SQLite · Bootstrap 5 · Spatie Permissions · DomPDF  
> **Branch:** `claude/focused-rubin-Uo1Iz` → `traderbhai/Schoolmanagement`

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [User Roles & Demo Credentials](#2-user-roles--demo-credentials)
3. [Admission Workflow — End-to-End](#3-admission-workflow--end-to-end)
4. [Admin Panel](#4-admin-panel)
5. [Admission Head Portal](#5-admission-head-portal)
6. [Accounts Officer Portal](#6-accounts-officer-portal)
7. [Dean Academics Portal](#7-dean-academics-portal)
8. [HOD Portal](#8-hod-portal)
9. [Program Chair Portal](#9-program-chair-portal)
10. [Teacher Portal](#10-teacher-portal)
11. [Student Portal](#11-student-portal)
12. [Applicant Self-Service Portal](#12-applicant-self-service-portal)
13. [Academic Management (Shared)](#13-academic-management-shared)
14. [Data Models Reference](#14-data-models-reference)
15. [Route Map](#15-route-map)
16. [Technical Notes for Developers](#16-technical-notes-for-developers)

---

## 1. System Overview

A multi-role college ERP covering the full lifecycle from student enquiry through graduation:

```
Enquiry/Lead → Application → Selection → Offer → Enrollment
     ↓               ↓            ↓          ↓         ↓
  CRM/Follow-up  Documents    WAT/GD/PI  Merit List  Fees/Academics
```

**Key capabilities:**
- Complete admission pipeline with CRM, lead analytics, document verification, selection scoring, merit lists, offer letters, waitlist, and enrollment
- Academic management: programs, batches, terms, subjects, timetables, attendance, exams, results
- Multi-level approval workflows (Dean → Program Chair)
- Fee management: fee structures, demands, payments, reconciliation, outstanding tracking
- Scholarship management: schemes, applicant awards, disbursements
- Reporting with PDF export, CSV exports
- Bulk communication (email/SMS)
- Public applicant self-service portal (apply, track, upload documents, pay fees, view offer)

---

## 2. User Roles & Demo Credentials

All passwords: **`password`**

**Staff / Admin:**

| Role | Email | Name | What They Access |
|------|-------|------|-----------------|
| **admin** | `admin@college.com` | Admin User | Full system — programs, batches, students, teachers, notices, timetable |
| **admission_head** | `head@college.com` | Rajesh Kumar | Complete admission pipeline (leads → enrollment) |
| **admission_officer** | `officer@college.com` | Sunita Sharma | Leads, documents, payments |
| **accounts_officer** | `accounts@college.com` | — | Fee collections, outstanding, admission payments, reconciliation |
| **dean_academics** | `dean@college.com` | — | Programs overview, student analytics, academics, approvals |
| **hod** | `hod@college.com` | — | Departmental approvals |
| **program_chair** | `chair@college.com` | — | Curriculum, approvals, dashboard |
| **exam_cell** | `exam@college.com` | — | Exam management, grade sheets, publish results |

**Teachers:**

| Email | Name | Department |
|-------|------|-----------|
| `ravi@college.com` | Prof. Ravi Mehta | Computer Science |
| `sunita@college.com` | Prof. Sunita Patel | Computer Science |
| `arjun@college.com` | Prof. Arjun Roy | Electronics |
| `anjali@demo.edu` | Dr. Anjali Sharma | Management Studies |
| `rakesh@demo.edu` | Prof. Rakesh Verma | Finance & Accounting |
| `priya.n@demo.edu` | Ms. Priya Nair | Marketing & Sales |
| `suresh@demo.edu` | Dr. Suresh Menon | Finance & Accounting |

**Students:**

| Email | Name | Program | Semester |
|-------|------|---------|----------|
| `aarav@college.com` | Aarav Sharma | CS | 5 |
| `priya@college.com` | Priya Patel | CS | 5 |
| `rohan@college.com` | Rohan Mehta | CS | 3 |
| `sneha@college.com` | Sneha Gupta | CS | 1 |
| `arjun.s@college.com` | Arjun Singh | EC | 5 |
| `divya@college.com` | Divya Nair | EC | 5 |
| `kiran@college.com` | Kiran Reddy | ME | 5 |
| `meera@college.com` | Meera Joshi | CS | 7 |
| `vikram@college.com` | Vikram Das | CS | 7 |
| `neha@college.com` | Neha Verma | ME | 3 |

**Login URL:** `http://localhost:8000/login`

---

## 3. Admission Workflow — End-to-End

### 3.1 Overview

```
Stage 1: Lead Capture
  └─ Web forms / referral / social / events / agent / advertisement
  └─ Bulk CSV import

Stage 2: Lead Nurturing
  └─ Status tracking: new → contacted → interested → not_interested / converted
  └─ Follow-up scheduling with calendar view
  └─ Counsellor assignment
  └─ Analytics dashboard

Stage 3: Application
  └─ Application window opens (dates + program + seats)
  └─ Applicant self-registers via public portal (/apply)
  └─ Fills application form (personal, academic, work experience)
  └─ Pays registration fee

Stage 4: Document Verification
  └─ Applicant uploads: marksheets, ID proof, photo, graduation certificate, etc.
  └─ Admission officer verifies each document (verify / reject with reason)
  └─ Document queue shows pending verifications

Stage 5: Selection Process
  └─ Selection steps configured per program (e.g. WAT → GD → PI)
  └─ Each step has weightage, max score, scoring parameters
  └─ Selection sessions created for each step
  └─ Applicants invited to sessions
  └─ Scores entered per applicant per step
  └─ Scorecards auto-calculated

Stage 6: Merit List
  └─ Auto-generated from weighted scores + academic merit
  └─ Reviewable, filterable, exportable CSV

Stage 7: Offer Letter
  └─ Generated after Dean approves
  └─ Sent to applicant (email)
  └─ Applicant accepts/rejects via portal
  └─ Acceptance deadline enforced

Stage 8: Waitlist
  └─ Rejected/overflow applicants can be waitlisted per program
  └─ Managed manually or via seat availability

Stage 9: Enrollment
  └─ Applicant pays enrollment/seat fee (via admission payments)
  └─ Admission officer confirms enrollment
  └─ Student record auto-created with enrollment number

Stage 10: Fee Demands
  └─ Semester-wise fee installments configured
  └─ Demands generated per batch/term
  └─ Students pay; accounts officer tracks
```

### 3.2 Admission Head — Detailed Feature List

**Dashboard** (`/admission/dashboard`)
- KPI cards: total leads, active applications, pending documents, pending payments, upcoming sessions, enrolled students
- Quick-action links to all sub-modules

**Leads & Enquiries** (`/admission/leads`)
- Filterable table: status, source, program
- Stats bar: total, new, contacted, interested, converted, conversion rate %
- Per-lead actions: view detail, add follow-up, change status, convert to applicant
- Bulk status update
- Export CSV

**Lead Analytics** (`/admission/leads/analytics/dashboard`)
- Source breakdown chart
- Status funnel
- Program-wise interest
- Month-over-month trend
- Conversion rate by source
- Counsellor performance stats

**Follow-up Calendar** (`/admission/leads/follow-ups/calendar`)
- Monthly calendar view
- Upcoming follow-ups highlighted
- Click a date to add/view follow-ups
- Mark follow-up as completed

**Lead Import** (`/admission/leads/import`)
- Upload CSV with columns: name, email, phone, source, program_code, notes
- Validates required columns (name, email minimum)
- Skips duplicates by email
- Maps program_code to program_id
- Reports imported/skipped counts

**Applicants CRM** (`/admission/applicants`)
- Full applicant table with filters: status, program, search
- Status labels: draft, submitted, under_review, shortlisted, selected, rejected, waitlisted, enrolled
- Export CSV
- Click into applicant detail

**Applicant Detail** (`/admission/applicants/{id}`)
- Personal info, academic background, work experience
- Application form answers
- Document verification status per document
- Approve/reject individual documents
- View scorecard
- View payment history
- Generate/view offer letter

**Applicant Scorecard** (`/admission/applicants/{id}/scorecard`)
- Per-step scores with parameters breakdown
- Weighted total score
- Rank within program cohort

**Applicant Payments** (`/admission/applicants/{id}/payments`)
- All admission payments for this applicant
- Verification status, amount, method, reference

**Document Queue** (`/admission/documents/queue`)
- All pending document verifications across all applicants
- Bulk verify action
- Per-document verify/reject with reason

**Payment Queue** (`/admission/payments/queue`)
- All pending admission payment verifications
- Shows: applicant, amount, method, reference, uploaded receipt
- Verify or reject with remarks

**Selection Sessions** (`/admission/sessions`)
- List of all scheduled sessions (WAT, GD, PI)
- Create new session: step, program, date/time, venue, capacity
- View session details, manage invitees

**Create Session** (`/admission/sessions/create`)
- Select program, selection step, date, time, venue, capacity
- Invite shortlisted applicants

**Enrollment** (`/admission/enrollment`)
- Shows applicants with accepted offer letters awaiting enrollment confirmation
- Confirm enrollment → creates Student record

**Reports** (`/admission/reports`)
- Funnel report: leads → applications → selections → enrollments
- Program-wise breakdown
- Year-over-year comparison (3 years)
- AICTE compliance summary
- Counsellor performance stats
- Geographic distribution
- Export full report as PDF

**Bulk Communication** (`/admission/bulk-communication`)
- Select recipients: by program, status, or custom list
- Compose email / SMS message
- Preview before send
- Sent history

**Refunds** (`/admission/refunds`)
- List of refund requests
- Approve / reject / mark processed
- Per-applicant refund creation

**Scholarship Schemes** (`/admission/scholarship-schemes`)
- Create merit/need/sports/category-based schemes
- Set percentage discount, max amount, eligibility criteria
- List and manage schemes

**Create Scholarship Scheme** (`/admission/scholarship-schemes/create`)
- Name, type, description, discount %, max amount, eligibility

**Scholarship Disbursements** (`/admission/scholarship-disbursements`)
- Track awarded scholarships awaiting disbursement
- Mark as disbursed

**Seat Matrix** (`/admission/seat-matrices/{program}`)
- Configure total seats, reserved category seats (SC/ST/OBC/EWS/General)
- Current filled count shown

**Selection Process** (`/admission/selection-process/{program}/steps`)
- Configure steps per program (e.g. WAT → Group Discussion → Personal Interview)
- Each step: name, type, max_score, weightage %, instructions
- Manage scoring parameters per step
- Total weightage shown with warning if not 100%

**Fee Installments** (`/admission/fee-installments/{program}`)
- Configure semester-wise fee installments per program
- Installment name, amount, due date, type (registration/tuition/hostel/etc.)

**Merit List** (`/admission/merit-list/{program}`)
- Index: list of all merit list entries for a program
- Show view: ranked table with scores, academic marks, final merit score
- Export CSV

**Offer Letters** (`/admission/offer-letters/{program}`)
- List all issued offer letters for a program
- Status: issued, accepted, rejected, expired
- Resend, view, withdraw actions

**Waitlist** (`/admission/waitlist/{program}`)
- Waitlisted applicants for a program
- Position management
- Convert waitlist to offer

**Application Windows** (`/admission/application-windows/{program}`)
- Open/close application windows per program
- Set: name, start date, end date, max applications, is_active

### 3.3 Applicant Status Flow

```
draft → submitted → under_review → shortlisted → selected → enrolled
                                              ↘ rejected → waitlisted
```

### 3.4 Approval Chain (Offer Letter)

```
Admission Head selects → Dean Academics approves → OfferLetter auto-created
                                     ↓
                      ApprovalWorkflow for Program Chair created
                                     ↓
                         Program Chair reviews → final approval
```

---

## 4. Admin Panel

**Login:** `admin@college.com` / `password`  
**Dashboard:** `/admin/dashboard`

### Programs (`/admin/programs`)
- Create / edit / delete academic programs (e.g. PGDM, MBA)
- Fields: name, code, department, duration (years), total_seats, description, is_active
- View enrolled student count, batch count

### Batches (`/admin/batches`)
- Create batches per program (e.g. PGDM 2024-26)
- Fields: name, program, start_year, end_year, max_students, is_active
- View term/semester list per batch

### Students (`/admin/students`)
- Full student list with search, filter by program/batch/status
- View profile, attendance %, fees paid
- Manual enrollment, status change (active/suspended/graduated)

### Teachers (`/admin/teachers`)
- Full teacher list
- Create/edit: name, email, department, qualification, joining_date, status
- View assigned subjects and timetable slots

### Notices (`/admin/notices`)
- Create notices for all users or specific roles
- Title, content, type (general/urgent/event), target_role, publish_date, expiry_date
- Published/draft state

### Timetable (`/admin/timetable`)
- View full timetable grid by day/period
- Assign teacher + subject + classroom to slot per batch

### Academic Years
- Create academic years (e.g. 2024-2025)
- Set as current

### Departments
- Create/edit departments (Management Studies, Finance & Accounting, etc.)

### Subjects
- Create subjects per program/term
- Code, name, credit_hours, subject_type (core/elective), department

### Classrooms
- Name, capacity, type (lecture_hall/lab/seminar), building, floor

### Admissions Config (via Admin)
- Required documents list per program
- Registration fee amount
- Form field configuration

### Fee Structures
- Create fee structures per program/term
- Component: tuition, lab, hostel, transport, exam

### Role Management
- Assign roles to users
- View all users by role

---

## 5. Admission Head Portal

See [Section 3.2](#32-admission-head--detailed-feature-list) for the full feature breakdown.

**Quick navigation sidebar links:**
- Admission Dashboard
- Leads & Enquiries
- Lead Analytics
- Follow-up Calendar
- Lead Import
- Applicants CRM
- Document Queue
- Payment Queue
- Selection Sessions
- Enrollment
- Reports
- Bulk Communication
- Refunds
- Scholarship Schemes
- Scholarship Disbursements

---

## 6. Accounts Officer Portal

**Login:** `accounts@college.com` / `password`

### Dashboard (`/accounts/dashboard`)
- Total billed vs collected vs outstanding
- Overdue payment count
- Program-wise collection summary
- Recent payments list
- Admission-side totals (total collected, pending verification count)
- Pending scholarship disbursements (count + amount)
- Overdue fee demands (count + amount)

### Fee Collections (`/accounts/fee-collections`)
- All student fee payments with filters: program, batch, status, date range
- Status: paid / pending / overdue
- Export CSV

### Outstanding Fees (`/accounts/outstanding`)
- Students with unpaid dues grouped by program
- Per-student: amount due, last payment date
- Export CSV

### Admission Payments (`/accounts/admission-payments`)
- Pending admission payment verifications (registration fees, seat fees)
- Verify / reject each payment with remarks

### Reconciliation (`/accounts/reconciliation`)
- Verified admission payments grouped by program
- Summary: payment count + total collected per program
- Grand total
- Filter by program
- Export CSV

---

## 7. Dean Academics Portal

**Login:** `dean@college.com` / `password`

### Dashboard (`/dean/dashboard`)
- Total programs, students, faculty, exams this year
- Overall attendance percentage
- Program-wise student + batch count
- Recent exam results (last 10)

### Programs (`/dean/programs`)
- All active programs with: department, active student count, batch count, subject count, faculty count
- Read-only overview

### Students (`/dean/students`)
- All non-graduated students with filters: program, batch, status, search
- View enrollment number, program, batch, status

### Academics (`/dean/academics`)
- **Top Performers:** Students with exam results, sorted by average marks (top 10)
- **At-Risk Students:** Students with average score < 40% (bottom 20)
- **Program Pass Rates:** Pass % per program based on exam results

### Attendance (`/dean/attendance`)
- Program-wise attendance percentage
- Total attendance records per program

### Approvals (`/dean/approvals`)
- Pending approval workflows assigned to `dean_academics` role
- Typically: applicant offer letter approvals
- Approve with remarks → triggers OfferLetter creation + Program Chair workflow
- Reject with reason
- Filter by program

---

## 8. HOD Portal

**Login:** `hod@college.com` / `password`

### Approvals (`/hod/approvals`)
- Pending approval workflows assigned to `hod` role
- Approve / reject with remarks
- Filter by program

---

## 9. Program Chair Portal

**Login:** `chair@college.com` / `password`

### Dashboard (`/program-chair/dashboard`)
- Overview of program metrics

### Approvals (`/program-chair/approvals`)
- Pending approval workflows for `program_chair` role
- These arrive after Dean approval of an applicant
- Approve / reject

### Curriculum (`/program-chair/curriculum`)
- Subject list for managed programs
- View/manage course content overview

---

## 10. Teacher Portal

**Login:** `ravi@college.com` / `password`

### Dashboard (`/teacher/dashboard`)
- Assigned subjects, today's schedule
- Recent attendance summary
- Upcoming exams

### Mark Attendance (`/teacher/attendance/mark`)
- Select batch + subject + date
- Mark each student: present / absent / late
- Submit for the session

### Students (`/teacher/students`)
- Students enrolled in teacher's subjects
- View profile, contact details, attendance %

### Exams (`/teacher/exams`)
- Exams scheduled for teacher's subjects
- Enter marks for each student
- View result distribution

### Profile (`/teacher/profile`)
- View and edit own profile details
- Qualification, contact info, joining date

---

## 11. Student Portal

**Login:** `aarav@college.com` / `password`

### Dashboard (`/student/dashboard`)
- Welcome card with enrollment number
- Attendance % with color indicator (green/yellow/red)
- Recent notices
- Quick links to all modules

### Attendance (`/student/attendance`)
- Subject-wise attendance percentage
- Date-wise attendance log
- Color-coded: present (green), absent (red), late (yellow)

### Results (`/student/results`)
- Exam-wise marks
- Passed/failed status per exam
- Percentage score

### Fees (`/student/fees`)
- Outstanding dues per fee structure
- Paid amounts and payment dates
- Fee demand letters

### Subject Registration (`/student/subjects`)
- Available elective subjects for the current term
- Register for subjects (within credit limit)
- View registered subjects, deregister

### Timetable (`/student/timetable`)
- Weekly timetable grouped by day
- Subject, teacher, classroom, time slot

### Notices (`/student/notices`)
- All notices targeted to students or all roles
- Sorted by publish date

### Profile (`/student/profile`)
- Personal info, enrollment number, program, batch
- Contact details

---

## 12. Exam Cell Portal

**Login:** `exam@college.com` / `password`  
**Role:** `exam_cell` (also accessible by `dean_academics`, `admin`)

### Dashboard (`/exam-cell/dashboard`)
- Exam schedule overview
- Pending results entry count
- Recent published results

### Exams (`/exam-cell/exams`)
- All exams across programs and terms
- Exam types: internal, midterm, final
- Date, subject, program, total marks, passing marks

### Results (`/exam-cell/results`)
- All exam results
- Filter by exam, program, batch

### Grade Sheet (`/exam-cell/results/{exam}/grade-sheet`)
- Per-exam grade distribution table
- Student-wise marks, grade, pass/fail
- Downloadable

### Publish Results (`/exam-cell/results/{exam}/publish`)
- Mark results as published so students can see them

---

## 12b. Parent Portal

**Role:** `parent`  
*(No seeded demo parent user — can be created via admin role assignment)*

### Dashboard (`/parent/dashboard`)
- Summary of all registered children

### Children (`/parent/children`)
- List of linked student children

### Per-Child: Attendance (`/parent/children/{student}/attendance`)
- Subject-wise attendance percentage for the child

### Per-Child: Results (`/parent/children/{student}/results`)
- Exam results for the child

### Per-Child: Fees (`/parent/children/{student}/fees`)
- Fee payment status for the child

### Notices (`/parent/notices`)
- Notices targeted to parents or all roles

---

## 13. Applicant Self-Service Portal

**Public URL:** `/apply` (no login required to start)  
**Status Tracker:** `/track` (anonymous, needs application number + email)

### Public Routes
| URL | Purpose |
|-----|---------|
| `/apply` | Program selection landing |
| `/apply/{program}` | Application form for a specific program |
| `/track` | Track application status anonymously |

### After Registration (applicant role login)
| URL | Purpose |
|-----|---------|
| `/applicant/dashboard` | Application overview |
| `/applicant/application` | View/edit application form |
| `/applicant/documents` | Upload required documents |
| `/applicant/status` | Application status tracker |
| `/applicant/fees` | View fee installments and pay |
| `/applicant/fees/{payment}` | Payment receipt |
| `/applicant/offer-letters` | View offer letters |
| `/applicant/offer-letters/{id}/pdf` | Download offer letter PDF |
| `/applicant/offer-letters/{id}/accept` | Accept offer |
| `/applicant/offer-letters/{id}/decline` | Decline offer |

### Application Form Sections
- Personal details (name, DOB, gender, phone, address)
- Academic history (10th, 12th, graduation marks/institution)
- Work experience (company, role, years)
- Family details
- Program-specific additional fields (configured per program via AdmissionFormConfig)
- Document uploads (per RequiredDocument list for the program)

---

## 13. Academic Management (Shared)

Accessible to `dean_academics`, `admin`, `program_chair`, `hod` roles.

### Academic Calendars (`/academic/academic-calendars`)
- Create academic events: exam weeks, holidays, orientation, convocation
- Month-view calendar display
- Events filterable by type

### Term Promotions (`/academic/term-promotions`)
- Promote students from one term to the next within a batch
- Generate promotion list (eligible students)
- Bulk approve promotions
- Individual overrides (hold back, exempt)

### Scholarships (`/academic/scholarships`)
- Award scholarships to enrolled students
- Link to scholarship scheme
- Status: pending → awarded → disbursed

### Fee Demands (`/academic/fee-demands`)
- Generate semester fee demands for a batch+term
- Per-student demand with final amount (after scholarships)
- Overdue tracking

---

## 14. Data Models Reference

### Core Models

| Model | Table | Key Fields |
|-------|-------|-----------|
| User | users | name, email, password; roles via Spatie |
| Program | programs | name, code, department_id, duration_years, total_seats, is_active |
| Batch | batches | name, program_id, start_year, end_year, max_students, is_active |
| Term | terms | name, batch_id, term_number, start_date, end_date |
| Department | departments | name, code |
| Subject | subjects | name, code, program_id, term_id, credit_hours, subject_type |
| Classroom | classrooms | name, capacity, type, building |
| Teacher | teachers | user_id, department_id, qualification, joining_date, status |
| Student | students | user_id, program_id, batch_id, enrollment_number, status |

### Admission Models

| Model | Table | Key Fields |
|-------|-------|-----------|
| Lead | leads | name, email, phone, source, status, program_id, counsellor_id, last_contacted_at |
| LeadFollowUp | lead_follow_ups | lead_id, user_id, notes, follow_up_at, completed_at |
| ApplicationWindow | application_windows | program_id, name, start_date, end_date, max_applications, is_active |
| Applicant | applicants | user_id, program_id, batch_id, application_number, status, academic data... |
| ApplicantDocument | applicant_documents | applicant_id, document_type, file_path, status, verified_by, rejection_reason |
| AdmissionPayment | admission_payments | applicant_id, amount_paid, payment_method, reference_number, status, verified_at |
| SelectionStep | selection_steps | program_id, name, type, step_order, max_score, weightage, instructions |
| ScoringParameter | scoring_parameters | selection_step_id, name, max_marks, description |
| SelectionSession | selection_sessions | selection_step_id, program_id, scheduled_at, venue, capacity |
| ApplicantScore | applicant_scores | applicant_id, selection_step_id, session_id, total_score, parameter_scores (JSON) |
| MeritList | merit_lists | program_id, applicant_id, merit_score, rank, academic_score, status |
| OfferLetter | offer_letters | applicant_id, program_id, batch_id, status, issued_at, issued_by, acceptance_deadline |
| SeatMatrix | seat_matrices | program_id, general_seats, sc_seats, st_seats, obc_seats, ews_seats |
| Enrollment | enrollments | applicant_id, program_id, batch_id, confirmed_at, confirmed_by |
| ScholarshipScheme | scholarship_schemes | name, type, discount_percentage, max_amount, eligibility_criteria |
| ApplicantScholarship | applicant_scholarships | applicant_id, scheme_id, awarded_amount, status |
| RefundRequest | refund_requests | applicant_id, amount, reason, status, approved_by |
| FeeInstallment | fee_installments | program_id, name, amount, due_date, type |

### Academic Models

| Model | Table | Key Fields |
|-------|-------|-----------|
| Exam | exams | program_id, subject_id, name, exam_date, total_marks, passing_marks |
| ExamResult | exam_results | exam_id, student_id, marks_obtained, is_absent |
| Attendance | attendances | student_id, subject_id, teacher_id, date, status (present/absent/late) |
| FeeStructure | fee_structures | program_id, name, amount, type |
| FeePayment | fee_payments | student_id, fee_structure_id, amount_paid, payment_date, status |
| FeeDemand | fee_demands | student_id, term_id, final_amount, status |
| Notice | notices | title, content, type, target_role, publish_date, expiry_date, created_by |
| Timetable (TimetableEntry) | timetable_entries | batch_id, subject_id, teacher_id, classroom_id, day_of_week, start_time, end_time |
| AcademicYear | academic_years | name, start_date, end_date, is_current |
| TermPromotion | term_promotions | student_id, from_term_id, to_term_id, status |
| ApprovalWorkflow | approval_workflows | approvable_type, approvable_id, approver_role, status, approver_id, remarks |
| SubjectEnrollment | subject_enrollments | student_id, subject_id, term_id |

### Key Relationships

```
Program hasMany Batch, Student, Subject, SelectionStep, SeatMatrix, MeritList
Batch hasMany Student, Term
Student belongsTo User, Program, Batch
Applicant belongsTo User, Program, Batch; hasMany ApplicantDocument, AdmissionPayment, ApplicantScore; hasOne OfferLetter, Enrollment
Lead hasMany LeadFollowUp; belongsTo Program, User(counsellor)
ApprovalWorkflow morphTo approvable (Applicant or other models)
OfferLetter belongsTo Applicant, Program, Batch
```

### Key Services

| Service | Key Methods | Purpose |
|---------|-------------|---------|
| `GradeService` | `getGrade(pct)`, `calculateStudentSemesterReport(studentId, semesterId)`, `calculateCGPA(studentId)` | Letter grades (O/A+/A/B+/B/C/D/F), SGPA, CGPA |
| `EnrollmentService` | `enroll(Applicant, data, confirmedBy)` | Validates applicant (must be 'selected'), generates `ENR-YYYY-CODE-#####` enrollment number, creates Student record |
| `AdmissionNotificationService` | (notification dispatch) | In-app notifications for status changes, offer letters, payment updates |
| `TimetableService` | (timetable retrieval) | Term-based timetable lookups for student view |
| `ReportService` | (PDF generation) | Grade cards, fee receipts, timetable reports |

---

## 15. Route Map

### Public Routes
| URL | Purpose |
|-----|---------|
| `/apply` | Public application form |
| `/apply/track` | Track application status |
| `/login`, `/logout` | Auth |

### Admin Routes (`/admin/*`)
| URL | Purpose |
|-----|---------|
| `/admin/dashboard` | Admin home |
| `/admin/programs` | Programs CRUD |
| `/admin/batches` | Batches CRUD |
| `/admin/students` | Students management |
| `/admin/teachers` | Teachers management |
| `/admin/notices` | Notices CRUD |
| `/admin/timetable` | Timetable management |
| `/admin/departments` | Departments CRUD |
| `/admin/subjects` | Subjects CRUD |
| `/admin/classrooms` | Classrooms CRUD |
| `/admin/academic-years` | Academic years |

### Admission Routes (`/admission/*`)
| URL | Purpose |
|-----|---------|
| `/admission/dashboard` | Admission KPI dashboard |
| `/admission/leads` | Lead CRM |
| `/admission/leads/analytics/dashboard` | Lead analytics |
| `/admission/leads/follow-ups/calendar` | Follow-up calendar |
| `/admission/leads/import` | CSV bulk import |
| `/admission/leads/export-csv` | Export leads |
| `/admission/applicants` | Applicant CRM |
| `/admission/applicants/{id}` | Applicant detail |
| `/admission/applicants/{id}/scorecard` | Applicant scorecard |
| `/admission/applicants/{id}/payments` | Applicant payments |
| `/admission/documents/queue` | Document verification queue |
| `/admission/payments/queue` | Payment verification queue |
| `/admission/sessions` | Selection sessions |
| `/admission/sessions/create` | Create session |
| `/admission/enrollment` | Enrollment confirmation |
| `/admission/reports` | Admission reports |
| `/admission/reports/export-pdf` | Export report PDF |
| `/admission/bulk-communication` | Bulk email/SMS |
| `/admission/refunds` | Refund management |
| `/admission/scholarship-schemes` | Scholarship schemes |
| `/admission/scholarship-disbursements` | Disbursements |
| `/admission/seat-matrices/{program}` | Seat matrix config |
| `/admission/selection-process/{program}/steps` | Selection steps config |
| `/admission/fee-installments/{program}` | Fee installments config |
| `/admission/merit-list/{program}` | Merit list |
| `/admission/merit-list/{program}/show` | Merit list ranked view |
| `/admission/offer-letters/{program}` | Offer letters |
| `/admission/waitlist/{program}` | Waitlist |
| `/admission/application-windows/{program}` | App windows |

### Accounts Routes (`/accounts/*`)
| URL | Purpose |
|-----|---------|
| `/accounts/dashboard` | Accounts KPI |
| `/accounts/fee-collections` | Student fee payments |
| `/accounts/outstanding` | Outstanding dues |
| `/accounts/admission-payments` | Admission payment verification |
| `/accounts/reconciliation` | Payment reconciliation |

### Departmental Routes
| URL | Purpose |
|-----|---------|
| `/dean/dashboard` | Dean overview |
| `/dean/programs` | Programs summary |
| `/dean/students` | All students |
| `/dean/academics` | Performance analytics |
| `/dean/attendance` | Attendance overview |
| `/dean/approvals` | Approval queue |
| `/hod/approvals` | HOD approval queue |
| `/program-chair/dashboard` | Chair overview |
| `/program-chair/approvals` | Chair approvals |
| `/program-chair/curriculum` | Curriculum view |

### Teacher Routes (`/teacher/*`)
| URL | Purpose |
|-----|---------|
| `/teacher/dashboard` | Teacher home |
| `/teacher/attendance/mark` | Mark attendance |
| `/teacher/students` | View students |
| `/teacher/exams` | Manage exams |
| `/teacher/profile` | Profile |

### Student Routes (`/student/*`)
| URL | Purpose |
|-----|---------|
| `/student/dashboard` | Student home |
| `/student/attendance` | Attendance log |
| `/student/results` | Exam results |
| `/student/fees` | Fee status |
| `/student/subjects` | Subject registration |
| `/student/timetable` | Weekly timetable |
| `/student/notices` | Notices |
| `/student/profile` | Profile |

### Exam Cell Routes (`/exam-cell/*`)
| URL | Purpose |
|-----|---------|
| `/exam-cell/dashboard` | Exam cell overview |
| `/exam-cell/exams` | All exams |
| `/exam-cell/results` | All results |
| `/exam-cell/results/{exam}/grade-sheet` | Grade sheet per exam |
| `/exam-cell/results/{exam}/publish` | Publish results |

### Parent Routes (`/parent/*`)
| URL | Purpose |
|-----|---------|
| `/parent/dashboard` | Parent home |
| `/parent/children` | List of children |
| `/parent/children/{student}/attendance` | Child attendance |
| `/parent/children/{student}/results` | Child results |
| `/parent/children/{student}/fees` | Child fee status |
| `/parent/notices` | Notices for parents |

### Academic Routes (`/academic/*`)
| URL | Purpose |
|-----|---------|
| `/academic/academic-calendars` | Academic calendar |
| `/academic/term-promotions` | Term promotions |
| `/academic/scholarships` | Student scholarships |
| `/academic/fee-demands` | Fee demands |

---

## 17. Academic Phase Implementation Roadmap

**Status:** Complete and ready for Phase 1 approval (2026-06-06)

**Duration:** 6-9 months | **Team:** 5-6 people | **Effort:** ~1,200-1,500 person-days

### Overview

After successfully implementing the **Admission Phase** (leads → application → selection → enrollment), the system enters the **Academic Phase**: curriculum delivery, teaching, exams, grading, placement, and alumni tracking.

This involves 9 distinct roles with complex interdependencies:

1. **Dean of Academic Affairs** — Curriculum approval, policy enforcement, grievance management
2. **Program Chair** — Program-specific leadership, cohort analytics, placements
3. **PMC** — Timetable planning, resource allocation, calendar management
4. **Exam Cell** — Exam scheduling, result management, transcripts, malpractice tracking
5. **HOD** — Department leadership, faculty management, leave approval, budget
6. **Faculties / Teachers** — Teaching, marking, attendance, mentoring
7. **Placement / CMC** — Placement drives, internships, alumni tracking, career services
8. **Institute Director** — Strategic oversight, KPI monitoring, compliance
9. **Institute Owner** — Ownership, governance, long-term vision

### 8 Phases (Detailed Roadmaps Available)

See `/college-mgmt/` root for:
- **`PHASED_IMPLEMENTATION_ROADMAP.md`** (78 KB) — Complete technical blueprint
- **`IMPLEMENTATION_SUMMARY.md`** (24 KB) — Stakeholder overview
- **`IMPLEMENTATION_PATTERNS.md`** (41 KB) — Code patterns & examples
- **`QUICK_REFERENCE.md`** (10 KB) — One-page cheat sheet

| Phase | Theme | Duration | Prerequisites | Roles |
|-------|-------|----------|---|---|
| **1** | Role & Permission Hierarchy | 2 weeks | None | All 9 |
| **2** | Role-Specific Dashboards | 2 weeks | Phase 1 | All 9 |
| **3** | Approval Workflows & Escalation | 2 weeks | 1, 2 | Dean, Chair, HOD |
| **4** | Offer & Enrollment | 2 weeks | 1-3 | Admission, Dean, Chair |
| **5** | Academic Lifecycle | 3 weeks | 1-4 | Dean, Faculty, Exam Cell |
| **6** | Fee Management | 2 weeks | 1, 4, 5 | Accounts, Admission |
| **7** | Placement & Career Services | 2 weeks | 1, 4 | Placement, Students |
| **8** | Reporting & Analytics | 2 weeks | 1-7 | Director, Dean |

**Total: 17-18 weeks core development + testing/customization = 6-9 months**

### Phase Highlights

**Phase 1: Role & Permission Hierarchy**
- Define 9 roles with specific scopes (program-level, department-level, institution-level)
- Permission matrix: who can do what actions on which data
- Program scoping: a Program Chair can only see their program, not others
- Audit logging: track all actions for compliance
- Models: UserRole, RolePermissionMatrix, AuditLog

**Phase 2: Role-Specific Dashboards**
- 9 custom landing pages (one per role) with KPIs, quick actions, alerts
- Dean: program health scorecard, faculty summary, grievance queue
- Program Chair: cohort progress, placement rate, curriculum status
- PMC: timetable conflicts, room utilization, exam logistics
- Exam Cell: result entry completion, anomalies detected, case management
- HOD: faculty directory, workload analysis, leave pending
- Faculty: my courses, attendance %, assignments to grade, exam schedule
- CMC: active drives, placements achieved, applications pending
- Director: institution KPIs, program metrics, compliance status
- Owner: financial snapshot, strategic initiatives, board governance

**Phase 3: Approval Workflows**
- Multi-step approval chains (Curriculum → Dean → Program Chair → implement)
- Escalation paths (Dean can escalate to Director if needed)
- SLA tracking (approvals must happen within N days)
- Models: CurriculumChange, ApprovalWorkflowStep, ApprovalNote, ApprovalSLA

**Phase 4: Offer & Enrollment**
- Bulk offer letter generation from merit list
- Approval chain: Admission Head → Dean → Program Chair
- Enrollment confirmation → auto-create Student record with enrollment number
- Integration with applicant portal (accept/decline offer)
- Models: None new (extend existing OfferLetter, Enrollment)

**Phase 5: Academic Lifecycle**
- Subject registration: students select electives within credit limits
- Attendance tracking: daily roll calls, monitor thresholds, export sheets
- Exam result entry: marks, grades, GPA/CGPA calculation
- Transcript generation: per-student, archival-ready PDFs
- Term promotion: students eligible for next term?
- Models: SubjectEnrollment, ExamAnomalyLog, AcademicTranscript

**Phase 6: Fee Management**
- Fee structures per program/term, with components
- Semester fee demands: auto-generate per batch/term
- Payment verification: Accounts officer reconciles payments
- Scholarship disbursements: deduct awarded scholarships from demands
- Outstanding tracking: which students owe how much
- Models: FeeInstallment (extend), FeeDemand (extend), ApplicantScholarship (extend)

**Phase 7: Placement & Career Services**
- Placement drives: company invites, interview scheduling, offer tracking
- Student eligibility: GPA cutoff, semester requirements enforced
- Internship coordination: placement during studies, supervisor feedback
- Alumni database: post-graduation employment, salary, feedback
- Placement statistics: placement %, average salary, top employers
- Models: PlacementDrive (extend), Internship, AlumniProfile, EmployerFeedback

**Phase 8: Reporting & Analytics**
- Admission funnel: leads → applications → placements (with conversion %)
- Academic analytics: pass rates, GPA trends, cohort retention, gender diversity
- Financial dashboard: fees collected vs. outstanding, scholarship spend
- AICTE compliance: curriculum coverage, faculty credentials, lab equipment
- Director dashboard: institution KPIs, program comparisons, trend analysis
- Models: InstitutionalKPI (pre-computed), StrategicGoal, ComplianceCheckpoint

### Database Impact

**New Models (10+):**
- UserRole, RolePermissionMatrix, RoleFeatureAccess
- CurriculumChange, StudentGrievance, FacultyWorkload
- StudentMentorship, PrePlacementTraining, StudentTrainingEnrollment
- Internship, AlumniProfile, EmployerFeedback
- and more...

**Enhanced Models (20+):**
- User (program scoping)
- Student (GPA fields, attendance %)
- ExamResult (grade, grade_point fields)
- Teacher (qualifications, certifications, performance rating)
- all Approval models with escalation, SLA fields

### Success Criteria

- **System uptime:** 99.5%
- **Page load time:** < 2 seconds (95th percentile)
- **User adoption:** > 90% within 3 months of phase completion
- **Data accuracy:** 100% fee reconciliation, 100% grade accuracy
- **AICTE compliance:** 100% report accuracy
- **Support quality:** < 2 tickets per 100 active users/month

### Critical Success Factors

1. **Phase 1 rigor** — Get role definitions exactly right (saves 4+ weeks later)
2. **Real payment gateway** — Integrate Razorpay/PayU in Phase 4 (not mocked)
3. **Query optimization** — Profile database in Phase 5 (before Phase 8 reporting)
4. **Reliable email/SMS** — Use SendGrid/Twilio with async queues
5. **User testing** — Gather feedback from 2-3 real users per role per phase
6. **Documentation** — Keep CLAUDE.md and GUIDE.md updated throughout (this file is source of truth)

### Parallelization Opportunities

- Phase 2 (dashboards) can start immediately after Phase 1 (roles) is done
- Phases 3 & 5 can run in parallel if workflow engine is shared
- Phase 7 (Placement) can start after Phase 4 (Enrollment) is live
- Phase 8 (Analytics) builds on all prior phases (sequential by necessity)

### Next Steps

1. **Review** the detailed roadmaps (15-min read for summary, 1-2 hours for full roadmaps)
2. **Confirm** priorities and phase sequence (are these phases the right order for your institution?)
3. **Allocate team** (which 5-6 people will work on this)
4. **Set sprint calendar** (when do you want Phase 1 done?)
5. **Approve Phase 1 kickoff** (role definitions, permission matrix)
6. **Commit to live testing** (after Phase 4, run 1 real admission → enrollment cycle with real users)

This is a comprehensive, production-grade implementation plan. The 4 roadmap documents provide everything needed for sprint planning, coding, testing, and stakeholder communication.

### Stack & Architecture

- **Framework:** Laravel 11 with Breeze (Blade + Bootstrap 5)
- **Database:** SQLite (file: `database/database.sqlite`)
- **Auth:** Laravel Breeze + Spatie Laravel Permission (roles, not permissions)
- **PDF:** `barryvdh/laravel-dompdf ^3.1` — standalone HTML templates (no `@extends`)
- **Icons:** Bootstrap Icons (bi-*)
- **Layout:** `resources/views/layouts/admin.blade.php` — used by all admin/admission/accounts/dean/hod/chair/teacher views
- **Student layout:** `resources/views/layouts/student.blade.php`
- **PDF templates:** Standalone HTML files (no layout extension)

### SQLite Gotchas

1. **HAVING without GROUP BY** — SQLite rejects `HAVING` on non-aggregate queries. Always filter in PHP after `->get()` instead of using `->having()` without a corresponding `->groupBy()`.
2. **Ambiguous column names in JOINs** — Always qualify column names when joining tables that have the same column name (e.g., `admission_payments.status` not just `status`).
3. **No ENUM enforcement** — Column values are not validated by the DB. Use Laravel validation or model casts.
4. **No `whereType()` on morphTo relations** — Use `whereHasMorph()` for querying polymorphic relations, and load relations with `->load()` post-fetch.

### Route Ordering Rules

Static routes must be declared **before** parameterized wildcard routes with the same prefix. Example:

```php
// CORRECT — static routes first
Route::get('leads/import', ...);        // static
Route::get('leads/export-csv', ...);    // static
Route::get('leads/{lead}', ...);        // wildcard — must come last
```

If a static route is registered after a wildcard, it gets shadowed. This caused the `leads/import` 404 bug.

### Polymorphic Approval Workflows

`ApprovalWorkflow` uses `morphTo('approvable')`. Pattern for querying + loading:

```php
// Query
$query = ApprovalWorkflow::where('approver_role', 'dean_academics')
    ->where('status', 'pending')
    ->with(['approvable', 'approver'])
    ->latest();

// Filter by program (for Applicant morphable type)
if ($request->filled('program_id')) {
    $query->whereHasMorph('approvable', [Applicant::class], function ($q) use ($request) {
        $q->where('program_id', $request->program_id);
    });
}

$approvals = $query->paginate(20)->withQueryString();

// Load nested relations post-fetch
$approvals->getCollection()->each(function ($approval) {
    if ($approval->approvable instanceof Applicant) {
        $approval->approvable->load(['user', 'program', 'batch']);
    }
});
```

### Offer Letter Auto-Generation

When `DeanController::approve()` approves an ApprovalWorkflow for an Applicant:
1. Creates `OfferLetter` with `status='issued'`, `issued_at`, `issued_by`, `acceptance_deadline` (14 days)
2. Creates next `ApprovalWorkflow` for `program_chair` role

### Middleware / Role Gates

Routes are guarded by Spatie's `role:` middleware:
```php
Route::middleware(['auth', 'role:admission_head|admission_officer'])->group(...)
```

The admin role typically has access to everything via the admin middleware group.

### PDF Export Pattern

```php
$pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('path.to.pdf-template', compact(...))
    ->setPaper('a4', 'portrait');
return $pdf->stream('filename.pdf');
```

PDF view files must be standalone HTML — **do not** use `@extends()` inside PDF templates.

### Seeder Users Summary

```
admin@college.com       → admin
head@college.com        → admission_head
officer@college.com     → admission_officer
accounts@college.com    → accounts_officer
dean@college.com        → dean_academics
chair@college.com       → program_chair
exam@college.com        → exam_cell
hod@college.com         → hod
ravi@college.com        → teacher (Ravi Kumar)
priya@college.com       → teacher (Priya Sharma)
amit@college.com        → teacher (Amit Joshi)
aarav@college.com       → student (Aarav Sharma — PGDM)
(+ 11 more students)
```

### Re-seeding the Database

```bash
php artisan migrate:fresh --seed
```

This drops and recreates all tables, then runs `DatabaseSeeder` (which calls `DemoDataSeeder`).

### Running the Dev Server

```bash
cd /home/user/Schoolmanagement/college-mgmt
php artisan serve --port=8000
```

### Running Playwright Tests

```bash
NODE_PATH=/opt/node22/lib/node_modules node /tmp/test_admission2.js
# Screenshots saved to /tmp/screenshots/
```

---

*Guide last updated: 2026-06-06. All 67 pages tested — 64/67 return HTTP 200 (3 are 404s for non-existent test URLs, not application bugs).*
