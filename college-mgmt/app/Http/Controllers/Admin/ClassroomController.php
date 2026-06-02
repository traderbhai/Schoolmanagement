<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\TimetableService;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $classrooms = Classroom::paginate(20);
        return view('admin.classrooms.index', compact('classrooms'));
    }
    public function create() { return view('admin.classrooms.create'); }
    public function store(Request $request) {
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
        $currentSemester = \App\Models\Semester::current();
        $utilization = $currentSemester ? $this->service->getClassroomUtilization($classroom->id, $currentSemester->id) : 0;
        return view('admin.classrooms.show', compact('classroom','utilization'));
    }
    public function edit(Classroom $classroom) { return view('admin.classrooms.edit', compact('classroom')); }
    public function update(Request $request, Classroom $classroom) {
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
        $classroom->update($data);
        return redirect()->route('admin.classrooms.index')->with('success', 'Updated.');
    }
    public function destroy(Classroom $classroom) {
        $classroom->delete();
        return redirect()->route('admin.classrooms.index')->with('success', 'Deleted.');
    }
}
