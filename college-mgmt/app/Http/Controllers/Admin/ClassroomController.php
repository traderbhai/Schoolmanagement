<?php
namespace App\Http\Controllers\Admin;
use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\TimetableService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassroomController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $this->authorizeAcademicScheduling();

        $classrooms = Classroom::paginate(20);
        return view('admin.classrooms.index', compact('classrooms'));
    }
    public function create() {
        $this->authorizeAcademicScheduling();

        return view('admin.classrooms.create');
    }
    public function store(Request $request) {
        $this->authorizeAcademicScheduling();

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'room_number'   => 'required|string|max:20|unique:classrooms',
            'capacity'      => 'required|integer|min:1',
            'type'          => 'required|in:lecture,lab,seminar,auditorium',
            'building'      => 'nullable|string|max:100',
            'floor'         => 'nullable|string|max:20',
            'has_projector' => 'boolean',
            'has_lab'       => 'boolean',
        ]);
        Classroom::create($data);
        return redirect()->route('admin.classrooms.index')->with('success', 'Classroom added.');
    }
    public function show(Classroom $classroom) {
        $this->authorizeAcademicScheduling();

        $currentSemester = \App\Models\Semester::current();
        $utilization = $currentSemester ? $this->service->getClassroomUtilization($classroom->id, $currentSemester->id) : 0;
        return view('admin.classrooms.show', compact('classroom','utilization'));
    }
    public function edit(Classroom $classroom) {
        $this->authorizeAcademicScheduling();

        return view('admin.classrooms.edit', compact('classroom'));
    }
    public function update(Request $request, Classroom $classroom) {
        $this->authorizeAcademicScheduling();

        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'room_number'   => 'required|string|max:20|unique:classrooms,room_number,'.$classroom->id,
            'capacity'      => 'required|integer|min:1',
            'type'          => 'required|in:lecture,lab,seminar,auditorium',
            'building'      => 'nullable|string|max:100',
            'floor'         => 'nullable|string|max:20',
            'has_projector' => 'boolean',
            'has_lab'       => 'boolean',
            'is_active'     => 'boolean',
        ]);

        if ($this->hasOperationalDependencies($classroom) && $this->changesStructuralFields($classroom, $data)) {
            throw ValidationException::withMessages([
                'classroom' => 'Room number, type, lab/projector capability, and building details cannot be changed after timetable or exam records are linked.',
            ]);
        }

        $minimumCapacity = $this->minimumRequiredCapacity($classroom);
        if ($minimumCapacity !== null && (int) $data['capacity'] < $minimumCapacity) {
            throw ValidationException::withMessages([
                'capacity' => "Classroom capacity cannot be reduced below {$minimumCapacity}, because active timetable batches depend on this room.",
            ]);
        }

        if ($this->deactivatesRoomWithActiveSchedules($classroom, $data)) {
            throw ValidationException::withMessages([
                'is_active' => 'Classrooms with active timetable entries or upcoming exams cannot be deactivated.',
            ]);
        }

        $classroom->update($data);
        return redirect()->route('admin.classrooms.index')->with('success', 'Updated.');
    }
    public function destroy(Classroom $classroom) {
        $this->authorizeAcademicScheduling();

        if ($this->hasOperationalDependencies($classroom)) {
            return redirect()->route('admin.classrooms.index')
                ->with('error', 'Classrooms with timetable or exam history cannot be deleted because room allocation history depends on them.');
        }

        $classroom->delete();
        return redirect()->route('admin.classrooms.index')->with('success', 'Deleted.');
    }

    private function authorizeAcademicScheduling(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicScheduling(auth()->user()), 403);
    }

    private function hasOperationalDependencies(Classroom $classroom): bool
    {
        return $classroom->timetableEntries()->exists()
            || $classroom->exams()->exists();
    }

    private function changesStructuralFields(Classroom $classroom, array $data): bool
    {
        return (string) $classroom->room_number !== (string) $data['room_number']
            || (string) $classroom->type !== (string) $data['type']
            || (string) ($classroom->building ?? '') !== (string) ($data['building'] ?? '')
            || (string) ($classroom->floor ?? '') !== (string) ($data['floor'] ?? '')
            || (bool) $classroom->has_projector !== (bool) ($data['has_projector'] ?? false)
            || (bool) $classroom->has_lab !== (bool) ($data['has_lab'] ?? false);
    }

    private function minimumRequiredCapacity(Classroom $classroom): ?int
    {
        $maximumBatchSize = $classroom->timetableEntries()
            ->where('is_active', true)
            ->with('batch')
            ->get()
            ->map(function ($entry) {
                if (! $entry->batch) {
                    return null;
                }

                return max(
                    (int) $entry->batch->students()->where('status', 'active')->count(),
                    (int) $entry->batch->intake_capacity
                );
            })
            ->filter(fn ($size) => $size !== null)
            ->max();

        return $maximumBatchSize ? (int) $maximumBatchSize : null;
    }

    private function deactivatesRoomWithActiveSchedules(Classroom $classroom, array $data): bool
    {
        return array_key_exists('is_active', $data)
            && ! (bool) $data['is_active']
            && (bool) $classroom->is_active
            && (
                $classroom->timetableEntries()->where('is_active', true)->exists()
                || $classroom->exams()->whereDate('exam_date', '>=', now()->toDateString())->exists()
            );
    }
}
