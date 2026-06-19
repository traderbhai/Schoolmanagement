<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use App\Services\PlacementLifecycleService;
use Illuminate\Http\Request;

class PlacementDriveController extends Controller
{
    public function __construct(private PlacementLifecycleService $lifecycle) {}

    public function index(Request $request)
    {
        $this->authorizePlacementOperations($request);

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
        $this->authorizePlacementOperations(request());

        $companies = Company::where('is_active', true)->get();
        return view('admin.placement-drives.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $this->authorizePlacementOperations($request);

        $data = $request->validate([
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

        if ($message = $this->lifecycle->validateDriveCreate($data)) {
            return back()->withErrors(['placement_drive' => $message])->withInput();
        }

        PlacementDrive::create($data);

        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive created successfully.');
    }

    public function show(PlacementDrive $placementDrive)
    {
        $this->authorizePlacementOperations(request());

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
        $this->authorizePlacementOperations(request());

        $companies = Company::where('is_active', true)->get();
        return view('admin.placement-drives.edit', compact('placementDrive', 'companies'));
    }

    public function update(Request $request, PlacementDrive $placementDrive)
    {
        $this->authorizePlacementOperations($request);

        $data = $request->validate([
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

        if ($message = $this->lifecycle->validateDriveUpdate($placementDrive, $data)) {
            return back()->withErrors(['placement_drive' => $message])->withInput();
        }

        $placementDrive->update($data);

        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive updated successfully.');
    }

    public function destroy(PlacementDrive $placementDrive)
    {
        $this->authorizePlacementOperations(request());

        if ($message = $this->lifecycle->validateDriveDelete($placementDrive)) {
            return back()->with('error', $message);
        }

        $placementDrive->delete();
        return redirect()->route('admin.placement-drives.index')->with('success', 'Placement drive deleted.');
    }

    public function apply(Request $request, PlacementDrive $drive)
    {
        $this->authorizePlacementOperations($request);

        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        $student = Student::findOrFail($request->student_id);
        if ($message = $this->lifecycle->validateApplicationCreate($drive, $student)) {
            return back()->with('error', $message);
        }

        Placement::create(
            ['application_status' => 'applied']
            + ['drive_id' => $drive->id, 'student_id' => $student->id]
        );

        return redirect()->route('admin.placement-drives.show', $drive)->with('success', 'Student added to drive.');
    }

    public function exportPlacements(Request $request)
    {
        abort_unless($request->user() && AccessControl::canExportPlacementData($request->user()), 403);

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
        $this->authorizePlacementOperations($request);

        $request->validate([
            'application_status' => 'required|in:applied,shortlisted,interview,selected,rejected,withdrawn',
            'offered_package'    => 'nullable|numeric|min:0',
            'joining_date'       => 'nullable|date',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $data = $request->only('application_status', 'offered_package', 'joining_date', 'remarks');

        if ($message = $this->lifecycle->validateApplicationUpdate($placement, $data)) {
            return back()->withErrors(['application_status' => $message])->withInput();
        }

        $placement->update($data);

        return redirect()->route('admin.placement-drives.show', $placement->drive_id)->with('success', 'Application updated.');
    }

    private function authorizePlacementOperations(Request $request): void
    {
        abort_unless($request->user() && AccessControl::canManagePlacementOperations($request->user()), 403);
    }
}
