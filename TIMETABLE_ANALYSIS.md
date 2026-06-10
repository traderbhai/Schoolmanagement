# Timetable System - Current Implementation Analysis

**Analysis Date:** 2026-06-10  
**Method:** Graphify + Code Analysis (0 tokens)  
**Status:** COMPLETE OVERVIEW

---

## Executive Summary

Your timetable system is **multi-level** with three separate implementations:

1. **Admin Timetable** — Full manual CRUD (create, read, update, delete)
2. **PMC (Program Chair) Timetable** — Advanced builder with conflict detection
3. **Student/Teacher Timetable** — Read-only views

The system uses:
- ✅ Conflict detection (teacher, room, course clashes)
- ✅ Versioning (draft → published states)
- ✅ Teacher availability tracking
- ✅ Substitution management
- ❌ **No automated scheduling engine** (all manual)
- ❌ **No soft constraint optimization** (spreading classes, load balancing)

---

## How It Currently Works

### Data Model

#### Core Tables

**`timetable_entries`** (Main table)
```
id
semester_id      → Semester (academic period)
course_id        → Course (e.g., BTech CSE)
subject_id       → Subject (e.g., Data Structures)
teacher_id       → Teacher (who teaches)
classroom_id     → Classroom (where)
timetable_slot_id → TimetableSlot (when - start/end time)
day_of_week      → 1-6 (Monday-Saturday)
batch_id         → Batch (which students)
timetable_version_id → TimetableVersion (draft/published)
is_active        → Boolean
status           → 'draft' or 'published'
timestamps
```

**Unique Constraints** (Hard Rules):
- `unique_room_slot` — One class per room per time slot
- `unique_teacher_slot` — One teacher can't teach two classes at once
- `unique_course_slot` — One course can't have two classes at once

**`timetable_slots`** (Time slots)
```
id
name             → e.g., "Slot 1", "10:00-11:00"
start_time       → 10:00
end_time         → 11:00
is_break         → Boolean (lunch break?)
sort_order       → Display order
is_active        → Boolean
```

**`timetable_versions`** (Version control)
```
id
program_id
term_id
batch_id
version_number
status          → 'draft', 'published', 'archived'
published_at
published_by     → Which user published
effective_from   → When it takes effect
notes
```

**`timetable_substitutions`** (Teacher absences)
```
id
original_entry_id  → Original timetable entry
substitute_teacher_id → Replacement teacher
date               → When the substitution happens
reason
status             → 'scheduled' or 'cancelled'
notified_at        → When students were told
```

**`teacher_availability`** (Not fully implemented)
```
id
teacher_id
term_id
day_of_week
timetable_slot_id
availability_type → 'available', 'unavailable', 'preferred'
notes
```

---

## Current Workflow & Procedure

### 1. **Admin Manual Entry** (Simplest)

**Route:** `/admin/timetable`  
**Controller:** `Admin\TimetableController`  
**Flow:**

1. **View Index** (`GET /admin/timetable`)
   - Shows all entries for a semester
   - Displays as weekly grid (day × time slot)
   - Can filter by semester and course
   - Uses `TimetableService::buildWeeklyGrid()`

2. **Create Entry** (`GET /admin/timetable/create`)
   - Form with dropdowns:
     - Semester
     - Course
     - Subject
     - Teacher
     - Classroom
     - Slot (time)
     - Day (Monday-Saturday)

3. **Store Entry** (`POST /admin/timetable`)
   - Validates all required fields
   - **Calls `TimetableService::checkConflicts()`**:
     - Checks if room is free at that time/day
     - Checks if teacher is free at that time/day
     - Checks if course already has class at that time/day
   - If conflicts found: Return to form with error
   - If OK: Create `TimetableEntry` record
   - Redirect to index

4. **Edit/Update** (`GET/PATCH /admin/timetable/{id}`)
   - Same form as create
   - Updates existing entry
   - Also checks conflicts (excluding itself)

5. **Delete** (`DELETE /admin/timetable/{id}`)
   - Soft delete via `is_active = false`

---

### 2. **PMC Advanced Builder** (What You Should Use)

**Route:** `/program-chair/timetable/builder`  
**Controller:** `Departmental\PmcTimetableController`  
**Status:** ✅ Fully implemented with advanced features

#### Step 1: Open Builder
```
GET /program-chair/timetable/builder
```

**Shows:**
- Program selector (dropdown)
- Term selector (latest 8 terms)
- Batch selector (optional, for batch-specific timetable)
- Weekly grid layout:
  ```
  Monday    Slot1  Slot2  Slot3  ...
  Tuesday   Slot1  Slot2  Slot3  ...
  ...
  Saturday  Slot1  Slot2  Slot3  ...
  ```

**Pre-loads:**
- All entries for this program-term-batch
- List of available subjects for this program-term
- List of available teachers
- List of classrooms
- **Teacher availability** (if set by teacher)
- Current version status (draft/published)

#### Step 2: Drag-and-Drop / Assign Slots
```
POST /program-chair/timetable/slot
```

**Request:**
```json
{
  "program_id": 1,
  "term_id": 5,
  "batch_id": 10,
  "day_of_week": 1,          // Monday
  "timetable_slot_id": 3,    // 10:00-11:00
  "subject_id": 42,          // Data Structures
  "teacher_id": 7,           // Dr. Sharma
  "classroom_id": 15         // Room 101
}
```

**What happens:**
1. **Validate** all fields
2. **Call `TimetableConflictService::check()`**:
   - Teacher already teaching at this time?
   - Room already booked?
   - Batch already has class at this time?
   - Return conflicts as JSON if found
3. **Delete** existing entry for this slot (if any)
4. **Create** new entry if subject is provided
5. **Return** JSON response

**UI Response:**
- ✅ Success → Grid updates instantly
- ❌ Conflict → Shows error message (teacher/room/batch clash)

#### Step 3: Check Conflicts (Full Audit)
```
POST /program-chair/timetable/check-conflict
```

Runs `TimetableConflictService::auditTerm()` — checks entire term for any conflicts.

#### Step 4: Publish Timetable
```
POST /program-chair/timetable/publish
```

**What happens:**
1. Validates program, term, batch
2. **Runs full conflict audit** (must pass)
3. **Creates TimetableVersion** record:
   - Status: 'published'
   - published_at: now
   - published_by: current user
   - effective_from: specified date (or today)
4. **Archives previous version** (if existed):
   - Sets status to 'archived'
5. **Makes visible** to:
   - Students (via `/student/timetable`)
   - Teachers (via `/teacher/timetable`)
4. Notifies users (planned, not fully implemented)

#### Step 5: Manage Teacher Availability (Optional)
```
GET/POST /program-chair/timetable/availability
```

Teachers can mark:
- Unavailable (blocked) times → Can't teach then
- Preferred times → Would like to teach then
- Normal availability

**PMC sees this** when building timetable (color-coded in UI).

#### Step 6: Handle Substitutions
```
GET/POST /program-chair/timetable/substitutions
```

When a teacher is absent:
1. PMC marks date + reason
2. Assigns substitute teacher
3. System updates timetable temporarily
4. Notifies students + classes

---

### 3. **Student/Teacher Read-Only View**

**Student Route:** `/student/timetable`  
**Teacher Route:** `/teacher/timetable`  
**Both use:** `TimetableService::buildWeeklyGrid()`

- Shows only published entries
- Filters by their batch (student) or teacher_id (teacher)
- Weekly grid display
- Can view all semesters

---

## Conflict Detection System

**Service:** `App\Services\TimetableConflictService`

### Hard Constraints (Enforced)

```php
// Can't double-book a room
if ($entry->classroom_id == $data['classroom_id']) {
    conflicts[] = "Room conflict: Room 101 already booked"
}

// Teacher can't teach two classes at once
if ($entry->teacher_id == $data['teacher_id']) {
    conflicts[] = "Teacher conflict: Dr. Sharma already teaching"
}

// Course can't have two classes at same time
if ($entry->course_id == $data['course_id']) {
    conflicts[] = "Course conflict: BTech CSE already has class"
}
```

### Soft Constraints (Not Enforced, Just Warnings)

❌ **Currently NOT implemented:**
- Teacher workload check (> 18 hours/week = warning)
- Room utilization (< 20% utilization = underused)
- Classroom size check (capacity < batch size)
- Subject spread (spread throughout week?)
- Lab sessions grouped (back-to-back labs?)

---

## What's Missing (Improvement Opportunities)

### 1. **Automated Scheduling Engine**
Currently: 100% manual
Should have: Algorithm to auto-generate base schedule

### 2. **Soft Constraint Optimization**
Currently: Only hard constraints
Should have:
- Load balancing (even teacher workload)
- Room utilization optimization
- Classroom size matching
- Subject spread across week

### 3. **Conflict Prevention**
Currently: Conflicts checked after assignment
Should have:
- Real-time conflict preview
- "Can assign here?" check before UI action
- Conflict avoidance suggestions

### 4. **Advanced Features**
Missing:
- Batch-wise timetable (same subjects split across sections)
- Lab vs lecture room assignment rules
- Online vs offline class support
- Faculty preference integration
- Student preference consideration

### 5. **Bulk Operations**
Currently: One slot at a time
Should have:
- Bulk import from file
- Copy timetable (from previous term)
- Template-based scheduling

### 6. **Analytics & Reports**
Missing:
- Faculty workload report (hours/week per teacher)
- Room utilization report
- Conflict reports
- Timetable PDF export per batch
- Teacher-wise schedule export

---

## Current Database Schema

### Relationships

```
TimetableEntry
├─ belongsTo(Semester)
├─ belongsTo(Course)
├─ belongsTo(Program)
├─ belongsTo(Term)
├─ belongsTo(Batch)
├─ belongsTo(Subject)
├─ belongsTo(Teacher)
├─ belongsTo(Classroom)
├─ belongsTo(TimetableSlot) — as 'slot'
├─ hasMany(Attendance)
├─ hasMany(TimetableSubstitution)
└─ belongsTo(TimetableVersion)

TimetableSlot
└─ hasMany(TimetableEntry)

TimetableVersion
└─ hasMany(TimetableEntry)

TimetableSubstitution
├─ belongsTo(TimetableEntry)
└─ belongsTo(Teacher) — substitute teacher

TeacherAvailability
├─ belongsTo(Teacher)
├─ belongsTo(Term)
└─ belongsTo(TimetableSlot)
```

---

## Key Code Locations

```
Models:
  app/Models/TimetableEntry.php         (Main model, 30 lines)
  app/Models/TimetableSlot.php          (Time slots, 17 lines)
  app/Models/TimetableVersion.php       (Version control)
  app/Models/TimetableSubstitution.php  (Substitutions)
  app/Models/TeacherAvailability.php    (Not fully used)

Services:
  app/Services/TimetableService.php              (Basic: grid, load, util.)
  app/Services/TimetableConflictService.php      (Conflict detection)

Controllers:
  app/Http/Controllers/Admin/TimetableController.php
    → Manual CRUD (create, store, edit, update, delete)
  
  app/Http/Controllers/Departmental/PmcTimetableController.php
    → Advanced builder (13.4 KB, 400+ lines)
    → builder() — UI grid
    → saveSlot() — AJAX save
    → publish() — Make live
    → substitutions() — Handle absences
    → teacherAvailability() — Preference management

  app/Http/Controllers/Student/TimetableController.php
    → Read-only view
  
  app/Http/Controllers/Teacher/TimetableController.php
    → Read-only view

Views:
  resources/views/departmental/program-chair/timetable.blade.php
  resources/views/student/timetable.blade.php
  resources/views/teacher/timetable.blade.php
  resources/views/pdf/timetable.blade.php

Migrations:
  2026_06_02_172738_create_timetable_entries_table.php
  2026_06_02_172738_create_timetable_slots_table.php
  2026_06_07_800000_add_batch_id_to_timetable_entries.php
  2026_06_07_800001_create_timetable_versions_table.php
  2026_06_07_800003_create_timetable_substitutions_table.php

Routes:
  /admin/timetable/                         (CRUD)
  /admin/timetable-slots/                   (Manage time slots)
  /program-chair/timetable/builder          (Advanced builder)
  /program-chair/timetable/slot             (AJAX save)
  /program-chair/timetable/publish          (Publish)
  /program-chair/timetable/check-conflict   (Audit)
  /program-chair/timetable/substitutions    (Absences)
  /program-chair/timetable/availability     (Preferences)
  /student/timetable                        (View)
  /teacher/timetable                        (View)
```

---

## Procedure Summary

### Creating a Timetable

**Step 1: Setup** (One-time)
- Admin creates TimetableSlots (time periods)
  - E.g., 8:00-9:00, 9:00-10:00, etc.
- ProgramSubject entries already exist (curriculum)
- Teachers and Classrooms already set up

**Step 2: Build** (For each program-term-batch)
```
Option A (Simple): Use Admin interface
  1. Go to /admin/timetable/create
  2. Fill form: semester, course, subject, teacher, room, slot, day
  3. Click Save
  4. Repeat for all classes (tedious!)

Option B (Better): Use PMC Builder
  1. Go to /program-chair/timetable/builder
  2. Select program, term, batch
  3. For each day-slot:
     - Click grid cell
     - Assign subject → teacher → room
     - System checks conflicts
     - Click Save (AJAX)
  4. Repeat until all slots filled
```

**Step 3: Validate**
```
POST /program-chair/timetable/check-conflict
→ Full audit of entire term
→ Reports any conflicts
```

**Step 4: Publish**
```
POST /program-chair/timetable/publish
→ Validates once more
→ Creates TimetableVersion record
→ Makes visible to students + teachers
→ Archives previous version
```

**Step 5: Maintain**
```
- Handle absences: POST /program-chair/timetable/substitutions
- Update preferences: POST /program-chair/timetable/availability
- View reports: (Not implemented yet)
```

---

## Improvement Roadmap

**Priority 1 (Easy wins):**
- [ ] Bulk import from CSV/Excel
- [ ] Copy timetable from previous term
- [ ] Faculty workload report
- [ ] PDF export per batch

**Priority 2 (Medium effort):**
- [ ] Classroom capacity validation
- [ ] Teacher workload suggestions
- [ ] Soft constraint warnings
- [ ] Conflict prevention mode

**Priority 3 (Complex):**
- [ ] Auto-scheduling algorithm
- [ ] Optimization engine
- [ ] AI conflict resolution
- [ ] Student preference integration

---

## Next Steps

**What would you like to improve?**

1. **Automated Scheduling** — Build scheduling engine
2. **Conflict Prevention** — Real-time conflict avoidance
3. **Bulk Operations** — Import/copy features
4. **Analytics** — Workload & utilization reports
5. **Advanced Rules** — Lab grouping, classroom size, etc.

Let me know which direction and I'll provide detailed implementation plan!

---

**Generated using:** Graphify (0 tokens) + Code Analysis  
**Status:** Ready for development
