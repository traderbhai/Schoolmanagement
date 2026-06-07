# IMPLEMENTATION PATTERNS & CODE EXAMPLES
## Using the Existing Codebase as Foundation

This document shows how to use patterns already established in the codebase to implement each phase.

---

## Pattern 1: Role-Based Access Control (Phase 1)

### Existing Pattern in Codebase
The system already uses Spatie Permissions. Example from routes:

```php
// routes/web.php
Route::middleware(['auth', 'role:admin|dean_academics|program_chair|exam_cell|hod|accounts_officer'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('programs', Admin\ProgramController::class);
});

// Inline role check in controller
if ($user->hasRole('dean_academics')) {
    $query->whereIn('program_id', $user->programs()->pluck('id'));
}
```

### Phase 1 Implementation Pattern

**Step 1: Define Roles in Seeder**
```php
// database/seeders/RoleSeeder.php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder {
    public function run() {
        // Create roles with hierarchy
        $roles = [
            'admin' => 0,                    // Highest level
            'dean_academics' => 1,
            'program_chair' => 2,
            'hod' => 2,
            'exam_cell' => 3,
            'admission_head' => 1,
            'admission_officer' => 3,
            'accounts_officer' => 2,
            'teacher' => 4,
            'student' => 5,
            'applicant' => 5,
            'parent' => 5,
            'placement' => 3,
        ];

        foreach ($roles as $roleName => $hierarchyLevel) {
            Role::firstOrCreate(['name' => $roleName], ['hierarchy_level' => $hierarchyLevel]);
        }

        // Create permissions
        $permissions = [
            'view_programs', 'create_programs', 'edit_programs', 'delete_programs',
            'view_students', 'create_students', 'edit_students', 'delete_students',
            'view_exams', 'enter_exam_results', 'publish_results',
            'approve_offers', 'generate_offers',
            'collect_fees', 'verify_payments', 'reconcile_bank',
            // ... more permissions
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        Role::findByName('admin')->givePermissionTo(Permission::all());
        
        Role::findByName('dean_academics')->givePermissionTo([
            'view_programs', 'view_students', 'approve_offers'
        ]);

        Role::findByName('exam_cell')->givePermissionTo([
            'view_exams', 'enter_exam_results', 'publish_results'
        ]);
        
        // ... continue for other roles
    }
}
```

**Step 2: Create UserRole Model for Program Scoping**
```php
// app/Models/UserRole.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model {
    protected $fillable = ['user_id', 'role_id', 'program_id', 'assigned_by', 'active_until'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function role() {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    public function program() {
        return $this->belongsTo(Program::class);
    }

    public function isActive() {
        return is_null($this->active_until) || $this->active_until->isFuture();
    }
}

// Enhance User model
// app/Models/User.php
class User extends Model {
    // ... existing code ...

    public function userRoles() {
        return $this->hasMany(UserRole::class);
    }

    public function programs() {
        return $this->hasManyThrough(
            Program::class,
            UserRole::class,
            'user_id',
            'id',
            'id',
            'program_id'
        )->where('active_until', '>', now())->orWhereNull('active_until');
    }

    // Get roles scoped to specific program
    public function rolesForProgram(Program $program) {
        return $this->userRoles()
            ->where('program_id', $program->id)
            ->orWhere('program_id', null)
            ->with('role')
            ->get();
    }
}
```

**Step 3: Controller for Role Assignment**
```php
// app/Http/Controllers/Admin/UserRoleController.php
namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Program;
use App\Models\UserRole;
use Illuminate\Http\Request;

class UserRoleController extends Controller {
    public function assign(Request $request, User $user) {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'program_id' => 'nullable|exists:programs,id',
            'active_until' => 'nullable|date|after:today',
        ]);

        // Check if role-program combo already exists
        $existing = UserRole::where('user_id', $user->id)
            ->where('role_id', $validated['role_id'])
            ->where('program_id', $validated['program_id'] ?? null)
            ->first();

        if ($existing && $existing->isActive()) {
            return back()->with('error', 'User already has this role for this program');
        }

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $validated['role_id'],
            'program_id' => $validated['program_id'],
            'assigned_by' => auth()->id(),
            'active_until' => $validated['active_until'],
        ]);

        // Log the action (Phase 1 audit logging)
        ActivityLog::create([
            'actor_id' => auth()->id(),
            'action' => 'role_assigned',
            'target_type' => User::class,
            'target_id' => $user->id,
            'changes' => [
                'role_id' => $validated['role_id'],
                'program_id' => $validated['program_id'],
            ],
        ]);

        return back()->with('success', 'Role assigned successfully');
    }

    public function show(User $user) {
        $userRoles = $user->userRoles()
            ->with('role', 'program')
            ->paginate(20);

        return view('admin.users.role-assignments', [
            'user' => $user,
            'roles' => \Spatie\Permission\Models\Role::all(),
            'programs' => Program::where('is_active', true)->get(),
            'userRoles' => $userRoles,
        ]);
    }
}
```

**Step 4: Query Helper for Data Scoping**
```php
// app/Services/DataScopeService.php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class DataScopeService {
    /**
     * Scope query by user's program assignments
     */
    public static function scopeByUserPrograms(Builder $query, $user = null) {
        $user = $user ?? auth()->user();
        
        // Admin sees all
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Others see only their assigned programs
        $programIds = $user->programs()->pluck('id');
        
        if ($programIds->isEmpty()) {
            return $query->whereRaw('1 = 0'); // No programs = no access
        }

        return $query->whereIn('program_id', $programIds);
    }
}

// Usage in Controller:
class Student::class {
    public function index() {
        $students = Student::query()
            ->with('program', 'batch')
            ->scopeByUserPrograms() // Uses auth()->user() by default
            ->paginate(50);
    }
}
```

**Step 5: Migration**
```php
// database/migrations/2026_06_07_000002_create_user_roles.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('role_id')->constrained('roles');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('active_until')->nullable();
            $table->timestamps();
            
            // Prevent duplicate role assignments
            $table->unique(['user_id', 'role_id', 'program_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_roles');
    }
};
```

---

## Pattern 2: Dashboard with KPIs (Phase 2)

### Existing Pattern
Look at existing controller: `app/Http/Controllers/Departmental/DeanController.php`

```php
// Already exists — showing pattern for enhancement
public function dashboard() {
    $totalPrograms = Program::count();
    $totalStudents = Student::whereHas('batch.program', fn($q) => $q->whereIn('id', auth()->user()->programs()->pluck('id')))->count();
    
    $recentResults = ExamResult::with('exam', 'student')
        ->latest()
        ->limit(10)
        ->get();

    return view('departmental.dean.dashboard', [
        'totalPrograms' => $totalPrograms,
        'totalStudents' => $totalStudents,
        'recentResults' => $recentResults,
    ]);
}
```

### Phase 2 Enhancement Pattern

**Step 1: Create Dashboard Service for Calculations**
```php
// app/Services/DashboardService.php
namespace App\Services;

use App\Models\Student;
use App\Models\Program;
use App\Models\ExamResult;
use App\Models\Attendance;
use Illuminate\Support\Facades\Cache;

class DashboardService {
    private $user;

    public function __construct($user = null) {
        $this->user = $user ?? auth()->user();
    }

    /**
     * Get Dean Dashboard KPIs (cached for 5 minutes)
     */
    public function getDeanKPIs() {
        $cacheKey = "dean.kpi.{$this->user->id}";
        
        return Cache::remember($cacheKey, 300, function() {
            return [
                'total_students' => $this->getTotalStudents(),
                'total_programs' => $this->getTotalPrograms(),
                'total_faculty' => $this->getTotalFaculty(),
                'overall_attendance_pct' => $this->getOverallAttendance(),
                'pass_rate_pct' => $this->getPassRate(),
                'at_risk_count' => $this->getAtRiskStudentCount(),
                'pending_approvals_count' => $this->getPendingApprovals(),
                'program_breakdown' => $this->getProgramBreakdown(),
            ];
        });
    }

    private function getTotalStudents() {
        return Student::query()
            ->whereHas('batch.program', fn($q) => 
                DataScopeService::scopeByUserPrograms($q, $this->user)
            )
            ->count();
    }

    private function getAtRiskStudentCount() {
        // Students with attendance < 75% AND avg marks < 40%
        return Student::query()
            ->where('attendance_percentage', '<', 75)
            ->whereHas('examResults', function($q) {
                $q->selectRaw('student_id, AVG(marks) as avg_marks')
                    ->havingRaw('AVG(marks) < 40')
                    ->groupBy('student_id');
            })
            ->whereHas('batch.program', fn($q) =>
                DataScopeService::scopeByUserPrograms($q, $this->user)
            )
            ->count();
    }

    private function getProgramBreakdown() {
        return Program::query()
            ->whereIn('id', $this->user->programs()->pluck('id'))
            ->with([
                'batches' => function($q) {
                    $q->select('id', 'program_id')
                        ->withCount('students');
                }
            ])
            ->get()
            ->map(fn($prog) => [
                'name' => $prog->name,
                'enrolled' => $prog->batches->sum('students_count'),
                'faculty_count' => $prog->teachers()->count(),
            ]);
    }

    private function getPendingApprovals() {
        return ApprovalWorkflow::where('approver_role', $this->user->roles()->first()?->name)
            ->where('status', 'pending')
            ->count();
    }

    // ... more helper methods
}
```

**Step 2: Enhanced Dashboard Controller**
```php
// app/Http/Controllers/Departmental/DeanController.php
namespace App\Http\Controllers\Departmental;

use App\Services\DashboardService;
use App\Models\Student;

class DeanController extends Controller {
    public function dashboard(DashboardService $service) {
        $kpis = $service->getDeanKPIs();

        return view('departmental.dean.dashboard', [
            'kpis' => $kpis,
            'at_risk_students' => $this->getAtRiskStudents(),
            'program_performance' => $this->getProgramPerformance(),
        ]);
    }

    private function getAtRiskStudents() {
        // Students with attendance < 75% AND avg marks < 40%
        $programIds = auth()->user()->programs()->pluck('id');

        return Student::query()
            ->where('attendance_percentage', '<', 75)
            ->whereHas('batch.program', fn($q) => $q->whereIn('id', $programIds))
            ->with('user', 'program', 'batch')
            ->limit(10)
            ->get();
    }

    private function getProgramPerformance() {
        // Pass rate by program (for heatmap)
        $programIds = auth()->user()->programs()->pluck('id');

        return Program::whereIn('id', $programIds)
            ->get()
            ->map(function($prog) {
                $results = ExamResult::whereHas('exam.subject', fn($q) => 
                    $q->where('program_id', $prog->id)
                )
                ->get();

                $passCount = $results->filter(fn($r) => $r->marks >= $r->exam->passing_marks)->count();
                
                return [
                    'program' => $prog->name,
                    'pass_rate' => $results->count() ? ($passCount / $results->count() * 100) : 0,
                    'total_results' => $results->count(),
                ];
            });
    }
}
```

**Step 3: Blade View with Bootstrap Cards**
```blade
{{-- resources/views/departmental/dean/dashboard.blade.php --}}
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Dean of Academic Affairs Dashboard</h2>

    {{-- KPI Cards Row --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Students</h6>
                    <h3>{{ $kpis['total_students'] }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Pass Rate</h6>
                    <h3>{{ $kpis['pass_rate_pct'] }}%</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">At Risk</h6>
                    <h3>{{ $kpis['at_risk_count'] }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Pending Approvals</h6>
                    <h3>{{ $kpis['pending_approvals_count'] }}</h3>
                    <a href="{{ route('dean.approvals') }}" class="text-white small">View</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Program Breakdown Table --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Program Overview</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Enrolled</th>
                                <th>Faculty</th>
                                <th>Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kpis['program_breakdown'] as $prog)
                            <tr>
                                <td>{{ $prog['name'] }}</td>
                                <td>{{ $prog['enrolled'] }}</td>
                                <td>{{ $prog['faculty_count'] }}</td>
                                <td>{{ $program_performance[$loop->index]['pass_rate'] ?? 'N/A' }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- At Risk Students Widget --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5>At-Risk Students (Low Attendance + Low Marks)</h5>
                    <a href="{{ route('dean.at-risk-students') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @if($at_risk_students->count() > 0)
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Attendance %</th>
                                    <th>Avg Marks</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($at_risk_students as $student)
                                <tr>
                                    <td>{{ $student->user->name }}</td>
                                    <td>{{ $student->program->name }}</td>
                                    <td>
                                        <span class="badge badge-warning">{{ $student->attendance_percentage }}%</span>
                                    </td>
                                    <td>{{ $student->avg_marks ?? 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('student.profile', $student->id) }}" class="btn btn-xs btn-info">Profile</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted">No at-risk students at this time.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

## Pattern 3: Approval Workflow (Phase 3)

### Existing ApprovalWorkflow Model
```php
// app/Models/ApprovalWorkflow.php (already exists, enhance it)
class ApprovalWorkflow extends Model {
    protected $fillable = [
        'approvable_type', 'approvable_id',
        'approver_role', 'status',
        'approved_at', 'approved_by',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function approvable() {
        return $this->morphTo();
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
```

### Phase 3 Enhancement: Add Workflow Steps

**Step 1: Create ApprovalWorkflowStep Model**
```php
// app/Models/ApprovalWorkflowStep.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model {
    protected $fillable = [
        'approval_workflow_id',
        'step_number',
        'approver_role',
        'approval_required',
        'deadline_days',
        'completed_at',
        'approved_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function workflow() {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOverdue() {
        if (is_null($this->deadline_days) || !is_null($this->completed_at)) {
            return false;
        }

        $dueDate = $this->workflow->created_at->addDays($this->deadline_days);
        return now()->isAfter($dueDate);
    }
}
```

**Step 2: Create ApprovalNote Model**
```php
// app/Models/ApprovalNote.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalNote extends Model {
    protected $fillable = ['approval_workflow_id', 'step_id', 'user_id', 'note'];

    public function workflow() {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function step() {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'step_id');
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
```

**Step 3: Approval Workflow Service**
```php
// app/Services/ApprovalWorkflowService.php
namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ApprovalNote;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowService {
    /**
     * Create workflow with steps for an entity
     */
    public function createWorkflow(
        Model $approvable,
        array $steps, // [{approver_role, deadline_days}, ...]
        string $reason = null
    ) {
        $workflow = ApprovalWorkflow::create([
            'approvable_type' => get_class($approvable),
            'approvable_id' => $approvable->id,
            'status' => 'pending',
            'reason' => $reason,
        ]);

        // Create steps
        foreach ($steps as $index => $step) {
            ApprovalWorkflowStep::create([
                'approval_workflow_id' => $workflow->id,
                'step_number' => $index + 1,
                'approver_role' => $step['approver_role'],
                'approval_required' => $step['approval_required'] ?? true,
                'deadline_days' => $step['deadline_days'] ?? null,
            ]);
        }

        return $workflow;
    }

    /**
     * Approve workflow at current step
     */
    public function approve(ApprovalWorkflow $workflow, string $note = null) {
        $currentStep = $workflow->steps()
            ->whereNull('completed_at')
            ->orderBy('step_number')
            ->first();

        if (!$currentStep) {
            throw new \Exception('No pending steps in workflow');
        }

        // Verify current user has the required role
        if (!auth()->user()->hasRole($currentStep->approver_role)) {
            throw new \Exception('User does not have permission to approve this step');
        }

        // Complete this step
        $currentStep->update([
            'completed_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        // Add note if provided
        if ($note) {
            ApprovalNote::create([
                'approval_workflow_id' => $workflow->id,
                'step_id' => $currentStep->id,
                'user_id' => auth()->id(),
                'note' => $note,
            ]);
        }

        // Check if all required steps are complete
        if ($this->isWorkflowComplete($workflow)) {
            $workflow->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            // Trigger post-approval action (e.g., create offer letter)
            $this->handleApprovalCompletion($workflow);
        } else {
            // Notify next approver
            $nextStep = $workflow->steps()
                ->whereNull('completed_at')
                ->orderBy('step_number')
                ->first();

            if ($nextStep) {
                $this->notifyApprover($workflow, $nextStep);
            }
        }

        return $workflow;
    }

    /**
     * Reject workflow (stops all further steps)
     */
    public function reject(ApprovalWorkflow $workflow, string $reason) {
        $currentStep = $workflow->steps()
            ->whereNull('completed_at')
            ->orderBy('step_number')
            ->first();

        $currentStep->update([
            'completed_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        ApprovalNote::create([
            'approval_workflow_id' => $workflow->id,
            'step_id' => $currentStep->id,
            'user_id' => auth()->id(),
            'note' => "REJECTED: {$reason}",
        ]);

        $workflow->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        // Notify original requester
        $this->notifyRejection($workflow);

        return $workflow;
    }

    /**
     * Escalate if overdue
     */
    public function escalate(ApprovalWorkflow $workflow) {
        $currentStep = $workflow->steps()
            ->whereNull('completed_at')
            ->orderBy('step_number')
            ->first();

        if (!$currentStep->isOverdue()) {
            throw new \Exception('Workflow is not overdue');
        }

        // Move to higher authority (configurable per workflow type)
        $escalateRole = $this->getEscalationRole($currentStep->approver_role);

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflow->id,
            'step_number' => $currentStep->step_number,
            'approver_role' => $escalateRole,
            'approval_required' => true,
            'deadline_days' => 1, // Urgent
        ]);

        // Notify escalated approver
        $this->notifyApproverUrgent($workflow, $escalateRole);

        return $workflow;
    }

    private function isWorkflowComplete(ApprovalWorkflow $workflow) {
        $requiredSteps = $workflow->steps()
            ->where('approval_required', true)
            ->count();

        $completedSteps = $workflow->steps()
            ->where('approval_required', true)
            ->whereNotNull('completed_at')
            ->count();

        return $requiredSteps === $completedSteps;
    }

    private function notifyApprover(ApprovalWorkflow $workflow, ApprovalWorkflowStep $nextStep) {
        $users = User::role($nextStep->approver_role)->get();
        
        foreach ($users as $user) {
            // Send email/SMS notification
            \Mail::queue(new \App\Mail\ApprovalPending($workflow, $nextStep, $user));
        }
    }

    private function handleApprovalCompletion(ApprovalWorkflow $workflow) {
        // Dispatch job based on workflow type
        match ($workflow->approvable_type) {
            \App\Models\Applicant::class => $this->createOfferLetter($workflow),
            \App\Models\TermPromotion::class => $this->promoteStudent($workflow),
            // ... other types
        };
    }

    // ... other helper methods
}
```

**Step 4: Controller for Approvals**
```php
// app/Http/Controllers/Departmental/ApprovalWorkflowController.php
namespace App\Http\Controllers\Departmental;

use App\Models\ApprovalWorkflow;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\Request;

class ApprovalWorkflowController extends Controller {
    public function __construct(private ApprovalWorkflowService $service) {}

    public function index() {
        $userRole = auth()->user()->roles()->first()?->name;

        $approvals = ApprovalWorkflow::whereHas('steps', function($q) use ($userRole) {
            $q->where('approver_role', $userRole)
                ->whereNull('completed_at');
        })
        ->with(['approvable', 'steps'])
        ->latest()
        ->paginate(20);

        return view('departmental.approvals.index', [
            'approvals' => $approvals,
            'pending_count' => $approvals->total(),
        ]);
    }

    public function show(ApprovalWorkflow $workflow) {
        $workflow->load(['steps', 'approvable', 'notes']);

        return view('departmental.approvals.show', [
            'workflow' => $workflow,
        ]);
    }

    public function approve(Request $request, ApprovalWorkflow $workflow) {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $this->service->approve($workflow, $validated['note'] ?? null);

        return back()->with('success', 'Approval submitted successfully');
    }

    public function reject(Request $request, ApprovalWorkflow $workflow) {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->service->reject($workflow, $validated['reason']);

        return back()->with('success', 'Approval rejected');
    }

    public function count() {
        $userRole = auth()->user()->roles()->first()?->name;

        $count = ApprovalWorkflow::whereHas('steps', function($q) use ($userRole) {
            $q->where('approver_role', $userRole)
                ->whereNull('completed_at');
        })->count();

        return response()->json(['count' => $count]);
    }
}
```

---

## Pattern 4: Query Scoping with SQLite Compliance (All Phases)

### Critical SQLite Rules
From CLAUDE.md:
1. No HAVING without GROUP BY
2. Qualify column names in JOINs
3. No whereType() on morphTo

### Correct Implementation

```php
// ❌ WRONG — SQLite will error on this
$results = ExamResult::where('marks', '>', 40)
    ->groupBy('student_id')
    ->having('marks', '>', 50) // Error: HAVING without GROUP BY in aggregate
    ->get();

// ✅ RIGHT — Filter after fetch
$results = ExamResult::where('marks', '>', 40)
    ->groupBy('student_id')
    ->get()
    ->filter(fn($r) => $r->marks > 50);

// ❌ WRONG — Unqualified column in JOIN
$students = Student::join('users', 'email', 'email')
    ->select('*')
    ->get();

// ✅ RIGHT — Fully qualified columns
$students = Student::join('users', 'students.user_id', '=', 'users.id')
    ->select('students.*', 'users.name')
    ->get();

// ❌ WRONG — whereType() on morphTo
$approvals = ApprovalWorkflow::whereMorphType('approvable', Applicant::class)->get();

// ✅ RIGHT — whereHasMorph()
$approvals = ApprovalWorkflow::whereHasMorph('approvable', [Applicant::class], function($q) {
    $q->where('program_id', $programId);
})->with('approvable')->get();

// Then load nested relations AFTER fetch
$approvals->each(function($approval) {
    if ($approval->approvable instanceof Applicant) {
        $approval->approvable->load('user', 'program', 'batch');
    }
});
```

---

## Pattern 5: File Upload & Storage (Phase 4, 5, 6)

### Existing Applicant Document Upload
```php
// app/Http/Controllers/Applicant/DocumentController.php (already exists)
public function store(Request $request, RequiredDocument $requiredDocument) {
    $validated = $request->validate([
        'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
    ]);

    $file = $validated['file'];
    $path = $file->store("applicants/{$this->applicant->id}", 'public');

    ApplicantDocument::create([
        'applicant_id' => $this->applicant->id,
        'required_document_id' => $requiredDocument->id,
        'file_path' => $path,
        'file_name' => $file->getClientOriginalName(),
        'uploaded_at' => now(),
    ]);

    return back()->with('success', 'Document uploaded');
}
```

### Storage Configuration
```php
// config/filesystems.php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL') . '/storage',
        'visibility' => 'public',
    ],
    'documents' => [
        'driver' => 'local',
        'root' => storage_path('app/documents'),
        'visibility' => 'private', // Not accessible via web
    ],
],
```

---

## Pattern 6: Email Notifications (All Phases)

### Existing Offer Letter Email
```php
// app/Mail/OfferLetterIssued.php (create if doesn't exist)
namespace App\Mail;

use App\Models\OfferLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OfferLetterIssued extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public OfferLetter $offerLetter) {}

    public function build() {
        return $this->subject("Offer Letter - {$this->offerLetter->program->name}")
            ->view('emails.admission.offer-letter')
            ->attach(storage_path("app/public/{$this->offerLetter->pdf_path}"), [
                'as' => "offer_letter_{$this->offerLetter->id}.pdf",
            ]);
    }
}

// Usage in service:
\Mail::queue(new OfferLetterIssued($offerLetter));
```

### SMS Notification (Phase 3, 4, 6)
```php
// app/Services/SMSService.php
namespace App\Services;

class SMSService {
    public function sendApprovalNotification($user, $workflowType) {
        $message = "New {$workflowType} awaiting your approval. Please login to portal.";
        
        \Twilio::message($user->phone, $message);
    }

    public function sendFeeReminder($student, $outstanding) {
        $message = "Fee reminder: Outstanding amount Rs. {$outstanding}. Please pay by due date.";
        
        \Twilio::message($student->user->phone, $message);
    }
}
```

---

## Pattern 7: PDF Generation (Phase 4, 5, 8)

### Existing Offer Letter PDF
```php
// resources/views/pdf/offer-letter.blade.php
// IMPORTANT: No @extends() — must be standalone HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .content { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Offer Letter</h2>
    </div>

    <div class="content">
        <p>Dear {{ $applicant->user->name }},</p>
        
        <p>
            Congratulations! We are pleased to offer you admission to 
            {{ $offer->program->name }} program.
        </p>

        <table style="width: 100%; margin: 20px 0;">
            <tr>
                <td><strong>Program:</strong></td>
                <td>{{ $offer->program->name }}</td>
            </tr>
            <tr>
                <td><strong>Batch:</strong></td>
                <td>{{ $offer->batch->name }}</td>
            </tr>
            <tr>
                <td><strong>Acceptance Deadline:</strong></td>
                <td>{{ $offer->acceptance_deadline->format('d-m-Y') }}</td>
            </tr>
        </table>

        <p>Please accept or decline this offer by logging into the applicant portal.</p>
    </div>
</body>
</html>

// Usage in controller:
public function downloadPdf(OfferLetter $offerLetter) {
    $pdf = \PDF::loadView('pdf.offer-letter', [
        'offer' => $offerLetter,
        'applicant' => $offerLetter->applicant,
    ]);

    return $pdf->download("offer_letter_{$offerLetter->id}.pdf");
}
```

---

## Pattern 8: Caching for Performance (Phase 2, 8)

### Dashboard KPI Caching (Phase 2)
```php
// app/Services/DashboardService.php
public function getDeanKPIs() {
    $cacheKey = "dean.kpi." . auth()->id();
    $ttl = 300; // 5 minutes

    return Cache::remember($cacheKey, $ttl, function() {
        return [
            'total_students' => $this->expensiveQuery1(),
            'pass_rate' => $this->expensiveQuery2(),
            // ...
        ];
    });
}

// Invalidate cache when data changes
public function studentEnrolled($student) {
    Cache::forget("dean.kpi." . $student->batch->program->dean->user_id);
}
```

### Query Optimization (Phase 8)
```php
// ❌ SLOW — N+1 queries
$students = Student::all();
foreach ($students as $student) {
    echo $student->program->name; // Extra query per student
}

// ✅ FAST — Eager loaded
$students = Student::with('program', 'batch', 'user')->get();
foreach ($students as $student) {
    echo $student->program->name; // No extra queries
}

// For aggregations, use selectRaw
$results = ExamResult::selectRaw('student_id, AVG(marks) as avg_marks')
    ->groupBy('student_id')
    ->get();
```

---

## Pattern 9: Validation (All Phases)

### Form Request Validation (Reusable)
```php
// app/Http/Requests/ApproveOfferLetterRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOfferLetterRequest extends FormRequest {
    public function authorize() {
        return auth()->user()->hasRole('dean_academics');
    }

    public function rules() {
        return [
            'offer_id' => 'required|exists:offer_letters,id',
            'remarks' => 'nullable|string|max:500',
            'acceptance_deadline' => 'nullable|date|after:today',
        ];
    }

    public function messages() {
        return [
            'acceptance_deadline.after' => 'Deadline must be in the future',
        ];
    }
}

// Usage in controller:
public function approve(ApproveOfferLetterRequest $request) {
    // $request->validated() is safe and clean
    $service->approveOffer($request->validated());
}
```

---

## Pattern 10: Testing (All Phases)

### Existing Test Structure
```php
// tests/Feature/AdmissionTest.php
use Tests\TestCase;
use App\Models\Applicant;
use App\Models\User;

class AdmissionTest extends TestCase {
    public function test_applicant_can_submit_application() {
        $applicant = Applicant::factory()->create(['status' => 'draft']);
        
        $response = $this->actingAs($applicant->user)
            ->post(route('applicant.application.submit'), [
                'section' => 'personal',
                'data' => ['phone' => '1234567890'],
            ]);

        $response->assertRedirect();
        $this->assertTrue($applicant->refresh()->status === 'submitted');
    }

    public function test_unauthorized_role_cannot_approve_offer() {
        $user = User::factory()->create();
        $user->assignRole('teacher'); // Not dean

        $response = $this->actingAs($user)
            ->post(route('approvals.approve', $workflow));

        $response->assertForbidden();
    }
}
```

---

## Quick Reference: Route Patterns

```php
// Pattern from existing routes/web.php

// Admin resources (full CRUD)
Route::resource('programs', Admin\ProgramController::class);
// Creates: /admin/programs, /admin/programs/{program}, /admin/programs/create, etc.

// Namespaced routes with middleware
Route::middleware(['auth', 'role:dean_academics'])->prefix('dean')->name('dean.')->group(function () {
    Route::get('dashboard', [DeanController::class, 'dashboard'])->name('dashboard');
    Route::get('approvals', [DeanController::class, 'approvals'])->name('approvals');
});

// Static routes BEFORE parameterized (important!)
Route::get('leads/import', ...);      // FIRST
Route::get('leads/{lead}', ...);      // SECOND

// Polymorphic routes
Route::post('approvals/{workflow}/approve', ...)->name('approvals.approve');
```

---

## Summary: Implementation Checklist Per Phase

### Phase 1 — Role Management
- [ ] Create UserRole model + migration
- [ ] Create RolePermissionMatrix model + migration
- [ ] Enhance User model with program scoping
- [ ] Create Admin/RoleController
- [ ] Create Admin/UserRoleController
- [ ] Seed demo roles and permissions
- [ ] Write tests for permission checks

### Phase 2 — Dashboards
- [ ] Create DashboardService for KPI calculations
- [ ] Enhance all 9 role controllers with dashboard methods
- [ ] Create 9 dashboard Blade views with KPI cards
- [ ] Add caching to dashboard service (5 min TTL)
- [ ] Test performance (< 1 sec load time)

### Phase 3 — Approvals
- [ ] Create ApprovalWorkflowStep model
- [ ] Create ApprovalNote model
- [ ] Create ApprovalWorkflowService
- [ ] Create Departmental/ApprovalWorkflowController
- [ ] Create approval queue view
- [ ] Create console command for escalation
- [ ] Test end-to-end approval chain

### Phase 4 — Offers & Enrollment
- [ ] Implement bulk offer generation
- [ ] Route offers through ApprovalWorkflow
- [ ] Expand Applicant/OfferLetterController
- [ ] Create enrollment confirmation logic
- [ ] Generate enrollment numbers
- [ ] Create Student record on enrollment
- [ ] Charge enrollment fee
- [ ] Test complete workflow (merit list → enrollment)

### Phase 5 — Academic Lifecycle
- [ ] Create SubjectRegistration model
- [ ] Implement subject registration
- [ ] Expand attendance marking (already mostly done)
- [ ] Implement exam result entry (bulk + individual)
- [ ] Implement grade calculation
- [ ] Implement term promotion workflow
- [ ] Implement transcript generation
- [ ] Test full academic cycle

### Phase 6 — Fee Management
- [ ] Enhance FeeStructure model
- [ ] Implement fee demand generation
- [ ] Implement payment recording + verification
- [ ] Implement bank reconciliation
- [ ] Create student fee portal
- [ ] Create accounts officer reports
- [ ] Test complete fee lifecycle

### Phase 7 — Placement
- [ ] Expand PlacementDrive model
- [ ] Implement drive management
- [ ] Implement student registration
- [ ] Implement offer tracking
- [ ] Create placement statistics
- [ ] Test placement workflow

### Phase 8 — Reporting
- [ ] Create admission funnel report
- [ ] Create academic performance reports
- [ ] Create financial dashboard
- [ ] Create AICTE compliance report
- [ ] Create executive KPI dashboard
- [ ] Implement report caching
- [ ] Test performance with 10k+ records

---

**End of Implementation Patterns**

See `PHASED_IMPLEMENTATION_ROADMAP.md` for full feature specifications.
