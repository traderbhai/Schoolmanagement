<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use Illuminate\Http\Request;

class PlacementDriveController extends Controller
{
    public function index(Request $request)
    {
        $drives = PlacementDrive::with('company')
            ->withCount('placements')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_id, fn($q, $v) => $q->where('company_id', $v))
            ->when($request->search, fn($q, $v) =>
                $q->where(fn($sq) =>
                    $sq->where('title', 'like', "%$v%")
                       ->orWhere('job_role', 'like', "%$v%")
                )
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $companies = Company::where('is_active', true)->get();

        $totalDrives     = PlacementDrive::count();
        $upcomingDrives  = PlacementDrive::where('status', 'upcoming')->count();
        $ongoingDrives   = PlacementDrive::where('status', 'ongoing')->count();
        $completedDrives = PlacementDrive::where('status', 'completed')->count();

        return view('admin.placement-drives.index', compact(
            'drives', 'companies', 'totalDrives', 'upcomingDrives', 'ongoingDrives', 'completedDrives'
        ));
    }

    public function create()
    {
        $companies = Company::where('is_active', true)->get();
        return view('admin.placement-drives.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id'     => 'required|exists:companies,id',
            'title'          => 'required|string|max:191',
            'job_role'       => 'required|string|max:191',
            'drive_date'     => 'nullable|date',
            'last_apply_date'=> 'nullable|date',
            'min_cgpa'       => 'nullable|numeric|min:0|max:10',
            'status'         => 'required|in:upcoming,ongoing,completed,cancelled',
            'vacancies'      => 'nullable|integer|min:1',
            'package'        => 'nullable|string|max:191',
            'location'       => 'nullable|string|max:191',
            'eligibility'    => 'nullable|string|max:500',
            'description'    => 'nullable|string',
        ]);

        PlacementDrive::create($request->all());

        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive created successfully.');
    }

    public function show(PlacementDrive $placementDrive)
    {
        $placementDrive->load(['company', 'placements.student.user', 'placements.student.course']);

        $appliedStudentIds = $placementDrive->placements->pluck('student_id')->toArray();
        $students = Student::with('user', 'course')
            ->where('status', 'active')
            ->whereNotIn('id', $appliedStudentIds)
            ->get();

        return view('admin.placement-drives.show', compact('placementDrive', 'students'));
    }

    public function edit(PlacementDrive $placementDrive)
    {
        $companies = Company::where('is_active', true)->get();
        return view('admin.placement-drives.edit', compact('placementDrive', 'companies'));
    }

    public function update(Request $request, PlacementDrive $placementDrive)
    {
        $request->validate([
            'company_id'     => 'required|exists:companies,id',
            'title'          => 'required|string|max:191',
            'job_role'       => 'required|string|max:191',
            'drive_date'     => 'nullable|date',
            'last_apply_date'=> 'nullable|date',
            'min_cgpa'       => 'nullable|numeric|min:0|max:10',
            'status'         => 'required|in:upcoming,ongoing,completed,cancelled',
            'vacancies'      => 'nullable|integer|min:1',
            'package'        => 'nullable|string|max:191',
            'location'       => 'nullable|string|max:191',
            'eligibility'    => 'nullable|string|max:500',
            'description'    => 'nullable|string',
        ]);

        $placementDrive->update($request->all());

        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive updated successfully.');
    }

    public function destroy(PlacementDrive $placementDrive)
    {
        $placementDrive->delete();
        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive deleted.');
    }

    public function apply(Request $request, PlacementDrive $drive)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        Placement::firstOrCreate(
            ['drive_id' => $drive->id, 'student_id' => $request->student_id],
            ['application_status' => 'applied']
        );

        return redirect()->route('admin.placement-drives.show', $drive)->with('success', 'Student added to drive.');
    }

    public function exportPlacements()
    {
        $placements = Placement::with('student.user', 'student.course', 'drive.company')
            ->get();

        $filename = 'placements_' . now()->format('Ymd_His') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($placements) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student','Enrollment','Course','Company','Drive','Role','Package','Status','Offer Letter','Joining Date']);
            foreach ($placements as $p) {
                fputcsv($file, [
                    $p->student->user->name ?? '',
                    $p->student->enrollment_number ?? '',
                    $p->student->course->name ?? '',
                    $p->drive->company->name ?? '',
                    $p->drive->title ?? '',
                    $p->drive->job_role ?? '',
                    $p->offered_package ?? $p->drive->package ?? '',
                    $p->application_status,
                    $p->offer_letter_number ?? '',
                    $p->joining_date?->format('d/m/Y') ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function updateApplication(Request $request, Placement $placement)
    {
        $request->validate([
            'application_status' => 'required|in:applied,shortlisted,interview,selected,rejected,withdrawn',
            'offered_package'    => 'nullable|numeric|min:0',
            'joining_date'       => 'nullable|date',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $placement->update($request->only('application_status', 'offered_package', 'joining_date', 'remarks'));

        return redirect()->route('admin.placement-drives.show', $placement->drive_id)->with('success', 'Application updated.');
    }
}
