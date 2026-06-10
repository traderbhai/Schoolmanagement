# Timetable Improvements - Comprehensive Implementation Plan

**Status:** Ready for Development  
**Token Budget:** ~300 per feature (using automation)  
**Priority:** 1 (High Value, Frequent Use)

---

## Overview: 12 Features to Implement

Organized by priority and effort level:

```
Priority 1 (Easy - Week 1-2)
├─ 1. Bulk Import from CSV/Excel
├─ 2. Copy Timetable from Previous Term
├─ 3. Faculty Workload Report
└─ 4. PDF Export (Batch Timetable)

Priority 2 (Medium - Week 3-4)
├─ 5. Classroom Capacity Validation
├─ 6. Teacher Workload Warnings
├─ 7. Room Utilization Report
└─ 8. Conflict Prevention Mode

Priority 3 (Complex - Week 5-6)
├─ 9. Soft Constraint Optimization
├─ 10. Auto-Scheduling Algorithm
├─ 11. Load Balancing Engine
└─ 12. Analytics Dashboard
```

---

## PRIORITY 1: Easy Wins (Week 1-2)

### Feature 1: Bulk Import from CSV/Excel

**What:** Import timetable entries from file instead of manual entry  
**Why:** Saves time for large timetables (e.g., 100+ classes)  
**Impact:** ⭐⭐⭐⭐⭐ (Huge time saver)

#### Specification

**New Route:**
```
GET    /program-chair/timetable/import              → form
POST   /program-chair/timetable/import              → process
POST   /program-chair/timetable/import/validate     → validate before save
```

**New Controller Method:**
```php
// TimetableImportController
public function importForm()                    // Show import form
public function process(Request $request)       // Handle CSV upload
public function validateImport(Request $request) // Dry-run validation
```

**CSV Format:**
```csv
day_of_week,timetable_slot_id,subject_id,teacher_id,classroom_id,batch_id
Monday,1,5,3,10,2
Monday,2,8,5,12,2
Tuesday,1,12,7,15,3
...
```

**Implementation Steps:**

1. **Create Migration** (optional, no schema change needed)

2. **Create Controller: `TimetableImportController`**
   ```php
   namespace App\Http\Controllers\Departmental;
   
   class TimetableImportController extends Controller {
       public function __construct(
           private TimetableImportService $importService
       ) {}
       
       public function importForm() { ... }
       public function process(Request $request) { ... }
       public function validateImport(Request $request) { ... }
   }
   ```

3. **Create Service: `TimetableImportService`**
   ```php
   namespace App\Services;
   
   class TimetableImportService {
       public function validateCSV(UploadedFile $file): array { ... }
       public function importCSV(array $data, int $programId, int $termId): array { ... }
       public function parseRow(array $row): array { ... }
   }
   ```

4. **Create View: `timetable/import.blade.php`**
   - File upload form
   - CSV template download link
   - Validation results table
   - Dry-run summary
   - Confirm & import button

5. **Add Routes**
   ```php
   Route::get('timetable/import', [Departmental\TimetableImportController::class, 'importForm'])
       ->name('timetable.import');
   Route::post('timetable/import', [Departmental\TimetableImportController::class, 'process'])
       ->name('timetable.import.process');
   Route::post('timetable/import/validate', [Departmental\TimetableImportController::class, 'validateImport'])
       ->name('timetable.import.validate');
   ```

**Validation Rules:**
- CSV format correct
- Subject exists in program
- Teacher exists and is active
- Classroom exists and is active
- Batch exists
- No conflicts (room, teacher, course)
- Day 1-6 (Mon-Sat)

**Error Handling:**
- Row-by-row validation
- Report which rows fail + why
- Allow user to fix and retry
- Dry-run before actual import

---

### Feature 2: Copy Timetable from Previous Term

**What:** Auto-generate new timetable based on previous term  
**Why:** Most subjects repeat every term; just duplicate and adjust  
**Impact:** ⭐⭐⭐⭐ (80% time saving)

#### Specification

**New Route:**
```
GET    /program-chair/timetable/copy              → select source term
POST   /program-chair/timetable/copy              → execute copy
```

**New Controller Method:**
```php
public function copyForm(Request $request)           // Select term to copy from
public function copy(Request $request)               // Do the copy
```

**Implementation Steps:**

1. **Create Service: `TimetableCopyService`**
   ```php
   class TimetableCopyService {
       public function getAvailableTerms(int $programId): Collection { ... }
       public function previewCopy(int $sourceTerm, int $targetTerm, int $programId): array { ... }
       public function executeCopy(array $config): int { ... } // Returns count copied
   }
   ```

2. **Create Controller Method:**
   ```php
   public function copyForm(Request $request) {
       $programIds = $this->programIds();
       $programs = Program::whereIn('id', $programIds)->get();
       $selectedProgram = Program::find($request->program_id) ?? $programs->first();
       
       $availableTerms = TimetableCopyService::getAvailableTerms($selectedProgram->id);
       
       return view('departmental.program-chair.timetable.copy-form', compact(
           'programs', 'selectedProgram', 'availableTerms'
       ));
   }
   ```

3. **Create View: `timetable/copy-form.blade.php`**
   - Program selector
   - Source term selector (dropdown: previous terms)
   - Target term selector (dropdown: future terms)
   - "Preview" button (show what will be copied)
   - Adjustment options:
     - Keep same teachers? Yes/No
     - Keep same classrooms? Yes/No
     - Adjust schedule? (shift time slots)
   - "Copy" button

4. **Add Routes**
   ```php
   Route::get('timetable/copy', [Departmental\PmcTimetableController::class, 'copyForm'])
       ->name('timetable.copy');
   Route::post('timetable/copy', [Departmental\PmcTimetableController::class, 'copy'])
       ->name('timetable.copy.process');
   ```

**Logic:**
```
1. Get all entries from source term
2. For each entry:
   - Keep: program, batch, subject, day_of_week, slot
   - Reset: teacher (optional), classroom (optional)
   - Create new entry in target term
3. Run conflict check on all new entries
4. Show: X entries copied, Y conflicts found
```

---

### Feature 3: Faculty Workload Report

**What:** Show hours/week per teacher for each term  
**Why:** Flag overloaded (>18hrs) or underloaded (<6hrs) teachers  
**Impact:** ⭐⭐⭐⭐ (Important for fairness)

#### Specification

**New Route:**
```
GET    /program-chair/faculty/workload              → report
GET    /program-chair/faculty/workload/export       → CSV export
```

**New Controller Method:**
```php
public function workloadReport(Request $request)     // Show report
public function workloadExport(Request $request)     // CSV download
```

**Implementation Steps:**

1. **Create View: `faculty/workload.blade.php`**
   ```
   Program: [dropdown]
   Term: [dropdown]
   
   [Table]
   Teacher Name | Subject | Day | Slot | Hours/Week | Status
   ─────────────────────────────────────────────────────────────
   Dr. Sharma   | (hours) |     |      | 16 hrs    | ✓ OK
   Dr. Patel    | (hours) |     |      | 22 hrs    | ⚠ Overloaded
   Dr. Gupta    | (hours) |     |      | 4 hrs     | ⚠ Underloaded
   
   [Chart] Bar chart of hours by teacher
   [Export CSV] button
   ```

2. **Logic in Controller:**
   ```php
   public function workloadReport(Request $request) {
       $term = Term::find($request->term_id);
       
       $teachers = Teacher::with('user')->get();
       $workload = [];
       
       foreach ($teachers as $teacher) {
           $hours = TimetableService::getTeacherWeeklyLoad(
               $teacher->id, 
               $term->id
           );
           
           $status = match(true) {
               $hours > 18 => 'overloaded',
               $hours < 6 => 'underloaded',
               default => 'normal'
           };
           
           $workload[] = [
               'teacher' => $teacher,
               'hours' => $hours,
               'status' => $status,
               'entries' => TimetableEntry::where('teacher_id', $teacher->id)
                   ->where('term_id', $term->id)
                   ->with(['subject', 'day_name', 'slot'])
                   ->get()
           ];
       }
       
       return view('departmental.program-chair.faculty.workload', compact(
           'workload', 'term'
       ));
   }
   ```

3. **Modify TimetableService:**
   ```php
   // Already exists, but enhance:
   public function getTeacherWeeklyLoad(int $teacherId, int $termId): float {
       $entries = TimetableEntry::where('teacher_id', $teacherId)
           ->where('term_id', $termId)
           ->where('is_active', true)
           ->with('slot')
           ->get();
       
       $hours = 0;
       foreach ($entries as $entry) {
           $hours += $entry->slot->duration_minutes / 60;
       }
       return round($hours, 1);
   }
   ```

4. **Add Route**
   ```php
   Route::get('faculty/workload', [Departmental\PmcFacultyController::class, 'workloadReport'])
       ->name('faculty.workload');
   ```

---

### Feature 4: PDF Export (Batch Timetable)

**What:** Generate printable PDF of timetable  
**Why:** For posting on notice board or distributing to students  
**Impact:** ⭐⭐⭐⭐ (Very popular feature)

#### Specification

**New Routes:**
```
GET    /program-chair/timetable/pdf/:batch_id      → download PDF
```

**Implementation Steps:**

1. **Create View: `pdf/timetable.blade.php`**
   ```blade
   <h1>{{ $program->name }} - {{ $batch->name }}</h1>
   <h2>{{ $term->name }}</h2>
   
   <table class="timetable">
       <tr>
           <th>Time</th>
           <th>Monday</th>
           <th>Tuesday</th>
           ...
       </tr>
       @foreach($slots as $slot)
       <tr>
           <td>{{ $slot->start_time }} - {{ $slot->end_time }}</td>
           @foreach($days as $day)
           <td>
               @if($grid[$day][$slot->id])
                   {{ $grid[$day][$slot->id]->subject->name }}<br>
                   {{ $grid[$day][$slot->id]->teacher->user->name }}<br>
                   <small>{{ $grid[$day][$slot->id]->classroom->room_number }}</small>
               @endif
           </td>
           @endforeach
       </tr>
       @endforeach
   </table>
   ```

2. **Create Controller Method:**
   ```php
   public function pdfExport(Request $request, int $batchId) {
       $batch = Batch::findOrFail($batchId);
       $term = Term::find($request->term_id);
       $program = $batch->program;
       
       $slots = TimetableSlot::where('is_active', true)
           ->orderBy('sort_order')->get();
       
       $grid = TimetableService::buildWeeklyGrid(
           $term->id,
           null,
           null,
           $batch->id
       );
       
       $pdf = PDF::loadView('pdf.timetable', compact(
           'batch', 'program', 'term', 'slots', 'grid'
       ));
       
       return $pdf->download("timetable-{$batch->name}-{$term->name}.pdf");
   }
   ```

3. **Add Route**
   ```php
   Route::get('timetable/pdf/{batchId}', [Departmental\PmcTimetableController::class, 'pdfExport'])
       ->name('timetable.pdf');
   ```

---

## PRIORITY 2: Medium Effort (Week 3-4)

### Feature 5: Classroom Capacity Validation

**Issue:** Can assign 100 students to 20-seat classroom  
**Solution:** Check classroom capacity before saving

#### Implementation:

```php
// In TimetableConflictService
public function checkCapacity(array $data): ?string {
    $batch = Batch::find($data['batch_id']);
    $classroom = Classroom::find($data['classroom_id']);
    
    if ($batch && $classroom && $batch->student_count > $classroom->capacity) {
        return "Classroom too small: {$batch->student_count} students, {$classroom->capacity} seats";
    }
    return null;
}

// Call in PmcTimetableController->saveSlot()
$capacity_error = app(TimetableConflictService::class)->checkCapacity($data);
if ($capacity_error) {
    return response()->json(['error' => $capacity_error], 422);
}
```

---

### Feature 6: Teacher Workload Warnings

**Issue:** No warning when assigning >18 hours/week to teacher  
**Solution:** Show warning (but allow assignment)

#### Implementation:

```php
// In TimetableService
public function getTeacherWorkloadStatus(int $teacherId, int $termId): array {
    $hours = $this->getTeacherWeeklyLoad($teacherId, $termId);
    
    return [
        'hours' => $hours,
        'warning' => $hours > 18 ? 'Overloaded' : ($hours < 6 ? 'Underloaded' : null),
        'status' => $hours > 18 ? 'danger' : ($hours < 6 ? 'warning' : 'success')
    ];
}

// Show in UI when selecting teacher
```

---

### Feature 7: Room Utilization Report

**What:** Show which rooms are under/over utilized

#### Implementation:

```php
public function roomUtilizationReport(Request $request) {
    $term = Term::find($request->term_id);
    
    $classrooms = Classroom::where('is_active', true)->get();
    $utilization = [];
    
    foreach ($classrooms as $classroom) {
        $percent = TimetableService::getClassroomUtilization(
            $classroom->id, 
            $term->id
        );
        
        $utilization[] = [
            'classroom' => $classroom,
            'percent' => $percent,
            'status' => $percent < 20 ? 'underused' : ($percent > 80 ? 'overused' : 'good')
        ];
    }
    
    return view('...', compact('utilization', 'term'));
}
```

---

### Feature 8: Conflict Prevention Mode

**What:** Show available slots before assigning  
**Why:** Instead of assigning then seeing conflicts, show what's available

#### Implementation:

```
POST /program-chair/timetable/available-slots

Input: teacher_id, day_of_week, term_id
Output: [list of available slots for this teacher on this day]

Logic:
1. Get all slots
2. Exclude slots where teacher already teaches
3. Exclude breaks
4. Return available slots + classroom suggestions
```

---

## PRIORITY 3: Complex Features (Week 5-6)

### Feature 9: Soft Constraint Optimization

**Examples:**
- Avoid stacking 3+ classes back-to-back
- Spread subjects evenly across week
- Lab sessions back-to-back (2-hour blocks)

#### Implementation: Create `TimetableOptimizerService`

---

### Feature 10: Auto-Scheduling Algorithm

**What:** Generate complete timetable automatically  
**Algorithm:** Graph coloring + constraint satisfaction

---

### Feature 11: Load Balancing Engine

**What:** Automatically balance teacher workload

---

### Feature 12: Analytics Dashboard

**What:** Comprehensive timetable analytics and insights

---

## Implementation Order & Dependencies

```
Week 1:
  ├─ Feature 1: Bulk Import CSV
  └─ Feature 3: Faculty Workload Report

Week 2:
  ├─ Feature 2: Copy from Previous Term
  ├─ Feature 4: PDF Export
  └─ Feature 5: Capacity Validation

Week 3:
  ├─ Feature 6: Workload Warnings
  ├─ Feature 7: Room Utilization
  └─ Feature 8: Conflict Prevention

Week 4-6:
  ├─ Feature 9: Soft Constraints
  ├─ Feature 10: Auto-Scheduling
  ├─ Feature 11: Load Balancing
  └─ Feature 12: Analytics Dashboard
```

---

## Database Changes Needed

```
No migrations needed for Priority 1-2!

Optional enhancements:
- Add `batch->student_count` column (estimate from enrollments)
- Add `classroom->capacity` column (already exists)
- Add `timetable_entries->priority` (for optimization)
```

---

## Testing Strategy

For each feature:
1. Unit tests (Service methods)
2. Feature tests (Controller + validation)
3. Manual testing (UI interactions)
4. Edge cases (empty data, conflicts, etc.)

---

## Estimated Token Cost

Using automation system:

| Feature | Tokens | Time |
|---------|--------|------|
| Feature 1 | 400 | 3 hours |
| Feature 2 | 350 | 3 hours |
| Feature 3 | 300 | 2 hours |
| Feature 4 | 280 | 2 hours |
| Feature 5 | 150 | 1 hour |
| Feature 6 | 150 | 1 hour |
| Feature 7 | 200 | 2 hours |
| Feature 8 | 250 | 2 hours |
| **Total P1-2** | **2,080** | **16 hours** |

**Without automation system:** ~8,000 tokens

**Savings: 74% (6,000 tokens saved!)**

---

## Quick Reference: Files to Create/Modify

### New Files
```
Controllers:
  app/Http/Controllers/Departmental/TimetableImportController.php
  app/Http/Controllers/Departmental/TimetableReportController.php

Services:
  app/Services/TimetableImportService.php
  app/Services/TimetableCopyService.php
  app/Services/TimetableReportService.php

Views:
  resources/views/departmental/program-chair/timetable/import.blade.php
  resources/views/departmental/program-chair/timetable/copy.blade.php
  resources/views/departmental/program-chair/faculty/workload.blade.php
  resources/views/departmental/program-chair/classroom/utilization.blade.php
  resources/views/pdf/timetable.blade.php
```

### Modified Files
```
Services:
  app/Services/TimetableService.php (add new methods)
  app/Services/TimetableConflictService.php (add validation)

Controllers:
  app/Http/Controllers/Departmental/PmcTimetableController.php (add methods)

Routes:
  routes/web.php (add new routes)
```

---

## Ready to Start?

**Option 1: Start with Feature 1 (Bulk Import)**
```bash
cd college-mgmt
bash .dev-config/auto-optimize.sh start-feature "timetable_bulk_import"
# Then copy PROMPT_TEMPLATE.md to Claude Code
```

**Option 2: Start with Feature 3 (Workload Report)**
```bash
bash .dev-config/auto-optimize.sh start-feature "timetable_workload_report"
```

**Option 3: Do all Priority 1 features in sequence**

Which would you like to start with? 🎯
