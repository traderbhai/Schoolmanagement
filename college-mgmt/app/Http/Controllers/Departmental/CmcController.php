<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{PlacementDrive, Placement, Student, Program, Company, CareerEvent, CareerEventRegistration};
use App\Services\PlacementLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CmcController extends Controller
{
    private const ACTIVE_DRIVE_STATUSES = ['upcoming', 'ongoing'];
    private const OPEN_APPLICATION_STATUSES = ['applied', 'shortlisted', 'interview'];

    public function __construct(private PlacementLifecycleService $lifecycle) {}

    public function dashboard()
    {
        $activeDrives    = PlacementDrive::whereIn('status', self::ACTIVE_DRIVE_STATUSES)->count();
        $totalPlacements = Placement::where('application_status', 'selected')->count();
        $totalStudents   = Student::count();
        $recentDrives    = PlacementDrive::with('company')->latest()->take(5)->get();
        $programs        = Program::where('is_active', true)->orderBy('name')->get();
        $upcomingEvents  = CareerEvent::where('event_date', '>=', now())->where('is_published', true)->orderBy('event_date')->take(5)->get();
        $activeCompanies = Company::where('is_active', true)->count();
        $openApplications = Placement::whereIn('application_status', self::OPEN_APPLICATION_STATUSES)->count();
        $careerEventsThisMonth = CareerEvent::where('is_published', true)
            ->whereBetween('event_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
        $placementRate = $totalStudents > 0 ? round(($totalPlacements / $totalStudents) * 100, 0) : 0;

        $cmcPriority = $this->cmcPriority(
            $activeCompanies,
            $activeDrives,
            $openApplications,
            $totalStudents,
            $placementRate,
            $careerEventsThisMonth
        );

        return view('departmental.cmc.dashboard', compact(
            'activeDrives', 'totalPlacements', 'totalStudents',
            'recentDrives', 'programs', 'upcomingEvents', 'placementRate', 'cmcPriority'
        ));
    }

    private function cmcPriority(int $activeCompanies, int $activeDrives, int $openApplications, int $totalStudents, int $placementRate, int $careerEventsThisMonth): array
    {
        if ($activeCompanies === 0) {
            return [
                'level' => 'warning',
                'title' => 'Build the recruiter pipeline',
                'body' => 'No active companies are available for drives. Add recruiter records before scheduling placement activity.',
                'route' => route('cmc.companies.create'),
                'action' => 'Add Company',
            ];
        }

        if ($openApplications > 0) {
            return [
                'level' => 'warning',
                'title' => "Review {$openApplications} open placement application" . ($openApplications === 1 ? '' : 's'),
                'body' => 'Applications in applied, shortlisted, or interview stages need status updates so students and leadership see current outcomes.',
                'route' => route('cmc.drives'),
                'action' => 'Review Applications',
            ];
        }

        if ($activeDrives === 0) {
            return [
                'level' => 'danger',
                'title' => 'Schedule an active placement drive',
                'body' => 'There are no upcoming or ongoing drives visible to students. Create a drive to keep opportunities moving.',
                'route' => route('cmc.drives.create'),
                'action' => 'New Drive',
            ];
        }

        if ($totalStudents > 0 && $placementRate < 40) {
            return [
                'level' => 'warning',
                'title' => "Placement rate is {$placementRate}%",
                'body' => 'Review program-wise placement performance and target recruiter outreach for low-coverage cohorts.',
                'route' => route('cmc.analytics'),
                'action' => 'Open Analytics',
            ];
        }

        if ($careerEventsThisMonth === 0) {
            return [
                'level' => 'info',
                'title' => 'Plan this month\'s career engagement',
                'body' => 'No published career events are scheduled this month. Add preparation sessions before recruitment peaks.',
                'route' => route('cmc.events.create'),
                'action' => 'New Event',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'Placement operations are current',
            'body' => 'Use analytics to review program outcomes, recruiter coverage, and event participation.',
            'route' => route('cmc.analytics'),
            'action' => 'Open Analytics',
        ];
    }

    public function drives(Request $request)
    {
        $query = PlacementDrive::with(['placements', 'company'])->latest();

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('company_id')) $query->where('company_id', $request->company_id);

        $drives    = $query->paginate(20)->withQueryString();
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('departmental.cmc.drives', compact('drives', 'companies'));
    }

    public function createDrive()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        return view('departmental.cmc.create-drive', compact('companies', 'programs'));
    }

    public function storeDrive(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'company_id'      => 'required|exists:companies,id',
            'job_role'        => 'required|string|max:255',
            'package'         => 'nullable|string|max:100',
            'min_cgpa'        => 'nullable|numeric|min:0|max:10',
            'eligibility'     => 'nullable|string|max:1000',
            'drive_date'      => 'required|date',
            'last_apply_date' => 'nullable|date',
            'location'        => 'nullable|string|max:255',
            'vacancies'       => 'nullable|integer|min:1',
            'description'     => 'nullable|string|max:2000',
            'status'          => 'required|in:upcoming,ongoing,completed,cancelled',
        ]);

        if (($data['last_apply_date'] ?? null) && ($data['drive_date'] ?? null) && $data['last_apply_date'] > $data['drive_date']) {
            return back()->withErrors(['last_apply_date' => 'Application deadline cannot be after the placement drive date.'])->withInput();
        }

        PlacementDrive::create($data);
        return redirect()->route('cmc.drives')->with('success', 'Placement drive created.');
    }

    public function editDrive(PlacementDrive $drive)
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        return view('departmental.cmc.edit-drive', compact('drive', 'companies', 'programs'));
    }

    public function updateDrive(Request $request, PlacementDrive $drive)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'company_id'      => 'required|exists:companies,id',
            'job_role'        => 'required|string|max:255',
            'package'         => 'nullable|string|max:100',
            'min_cgpa'        => 'nullable|numeric|min:0|max:10',
            'eligibility'     => 'nullable|string|max:1000',
            'drive_date'      => 'required|date',
            'last_apply_date' => 'nullable|date',
            'location'        => 'nullable|string|max:255',
            'vacancies'       => 'nullable|integer|min:1',
            'description'     => 'nullable|string|max:2000',
            'status'          => 'required|in:upcoming,ongoing,completed,cancelled',
        ]);

        if ($message = $this->lifecycle->validateDriveUpdate($drive, $data)) {
            return back()->withErrors(['placement_drive' => $message])->withInput();
        }

        $drive->update($data);
        return redirect()->route('cmc.drives')->with('success', 'Drive updated.');
    }

    public function destroyDrive(PlacementDrive $drive)
    {
        if ($message = $this->lifecycle->validateDriveDelete($drive)) {
            return back()->with('error', $message);
        }

        $drive->delete();
        return back()->with('success', 'Drive deleted.');
    }

    public function driveApplications(PlacementDrive $drive)
    {
        $drive->load(['company']);
        $applications = Placement::where('drive_id', $drive->id)
            ->with(['student.user'])->latest()->get();

        return view('departmental.cmc.drive-applications', compact('drive', 'applications'));
    }

    public function updateApplicationStatus(Request $request, Placement $placement)
    {
        $request->validate([
            'application_status' => 'required|in:applied,shortlisted,interview,selected,rejected,withdrawn',
            'offered_package'    => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $data = $request->only(['application_status', 'offered_package', 'remarks']);

        if ($message = $this->lifecycle->validateApplicationUpdate($placement, $data)) {
            return back()->withErrors(['application_status' => $message])->withInput();
        }

        $placement->update($data);
        return back()->with('success', 'Application status updated.');
    }

    public function placements()
    {
        $placements = Placement::with(['student.user', 'drive.company'])
            ->where('application_status', 'selected')->latest()->paginate(30);
        return view('departmental.cmc.placements', compact('placements'));
    }

    public function analytics()
    {
        $byProgram = Placement::join('students', 'placements.student_id', '=', 'students.id')
            ->join('programs', 'students.program_id', '=', 'programs.id')
            ->where('placements.application_status', 'selected')
            ->selectRaw('programs.name as program_name, COUNT(*) as placed_count')
            ->groupBy('programs.id', 'programs.name')
            ->orderByDesc('placed_count')->get();

        $driveStats = PlacementDrive::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status');

        return view('departmental.cmc.analytics', compact('byProgram', 'driveStats'));
    }

    public function companies(Request $request)
    {
        $query = Company::withCount('drives');
        if ($request->filled('search')) $query->where('name', 'like', '%' . $request->search . '%');
        $companies = $query->orderBy('name')->paginate(25)->withQueryString();
        return view('departmental.cmc.companies', compact('companies'));
    }

    public function createCompany()
    {
        return view('departmental.cmc.create-company');
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'industry'        => 'nullable|string|max:100',
            'website'         => 'nullable|url|max:255',
            'contact_person'  => 'nullable|string|max:255',
            'contact_email'   => 'nullable|email|max:255',
            'contact_phone'   => 'nullable|string|max:20',
            'description'     => 'nullable|string|max:1000',
        ]);
        $data['is_active'] = true;
        Company::create($data);
        return redirect()->route('cmc.companies')->with('success', 'Company added.');
    }

    public function editCompany(Company $company)
    {
        return view('departmental.cmc.edit-company', compact('company'));
    }

    public function updateCompany(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'industry'       => 'nullable|string|max:100',
            'website'        => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_email'  => 'nullable|email|max:255',
            'contact_phone'  => 'nullable|string|max:20',
            'description'    => 'nullable|string|max:1000',
            'is_active'      => 'boolean',
        ]);

        if ($company->hasOperationalHistory() && $data['name'] !== $company->name) {
            return back()
                ->withErrors(['name' => 'Company name cannot be changed after placement or internship history exists.'])
                ->withInput();
        }

        $data['is_active'] = $request->boolean('is_active');
        if ($company->is_active && ! $data['is_active'] && $company->hasActivePlacementDrives()) {
            return back()
                ->withErrors(['is_active' => 'Company cannot be deactivated while upcoming or ongoing placement drives exist.'])
                ->withInput();
        }

        $company->update($data);
        return redirect()->route('cmc.companies')->with('success', 'Company updated.');
    }

    public function events(Request $request)
    {
        $query = CareerEvent::with('organizer')->withCount('registrations')->latest('event_date');
        if ($request->filled('type') && array_key_exists($request->type, CareerEvent::TYPE_LABELS)) $query->where('event_type', $request->type);
        $events = $query->paginate(25)->withQueryString();
        return view('departmental.cmc.events', compact('events'));
    }

    public function createEvent()
    {
        return view('departmental.cmc.create-event');
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'event_type'            => ['required', Rule::in(array_keys(CareerEvent::TYPE_LABELS))],
            'event_date'            => 'required|date|after_or_equal:today',
            'venue'                 => 'nullable|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'seats'                 => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before_or_equal:event_date',
            'is_published'          => 'boolean',
        ]);
        $data['organizer_id'] = auth()->id();
        $data['is_published'] = $request->boolean('is_published');
        CareerEvent::create($data);
        return redirect()->route('cmc.events')->with('success', 'Event created.');
    }

    public function editEvent(CareerEvent $event)
    {
        return view('departmental.cmc.edit-event', compact('event'));
    }

    public function updateEvent(Request $request, CareerEvent $event)
    {
        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'event_type'            => ['required', Rule::in(array_keys(CareerEvent::TYPE_LABELS))],
            'event_date'            => 'required|date',
            'venue'                 => 'nullable|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'seats'                 => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date|before_or_equal:event_date',
            'is_published'          => 'boolean',
        ]);

        $registeredCount = $event->registrations()->count();
        if ($registeredCount > 0) {
            $message = $this->registeredEventContractChangeMessage($event, $data);
            if ($message) {
                return back()
                    ->withErrors(['career_event' => $message])
                    ->withInput();
            }
        }

        if (($data['seats'] ?? null) !== null && (int) $data['seats'] < $registeredCount) {
            return back()
                ->withErrors(['seats' => "Seats cannot be lower than the current registration count ({$registeredCount})."])
                ->withInput();
        }

        $data['is_published'] = $request->boolean('is_published');
        $event->update($data);
        return redirect()->route('cmc.events')->with('success', 'Event updated.');
    }

    public function destroyEvent(CareerEvent $event)
    {
        if ($event->registrations()->exists()) {
            return back()->with('error', 'Cannot delete a career event after students have registered. Unpublish it instead to preserve registration and attendance history.');
        }

        $event->delete();
        return back()->with('success', 'Event deleted.');
    }

    public function eventRegistrations(CareerEvent $event)
    {
        $event->load('organizer');
        $registrations = CareerEventRegistration::where('career_event_id', $event->id)
            ->with(['student.user'])->latest()->get();
        return view('departmental.cmc.event-registrations', compact('event', 'registrations'));
    }

    public function updateEventAttendance(Request $request, CareerEvent $event, CareerEventRegistration $registration)
    {
        abort_unless((int) $registration->career_event_id === (int) $event->id, 404);

        $data = $request->validate([
            'attended' => 'required|boolean',
        ]);

        if ($registration->attended && ! (bool) $data['attended']) {
            return back()->with('error', 'Attended event records are locked. Use an audited correction workflow for attendance reversals.');
        }

        $registration->update([
            'attended' => (bool) $data['attended'],
        ]);

        return back()->with('success', 'Event attendance updated.');
    }

    private function registeredEventContractChangeMessage(CareerEvent $event, array $data): ?string
    {
        $date = $event->event_date?->toDateString();
        $deadline = $event->registration_deadline?->toDateString();

        $newDate = $data['event_date'] ?? null;
        $newDeadline = $data['registration_deadline'] ?? null;

        if (($data['event_type'] ?? null) !== $event->event_type) {
            return 'Event type cannot be changed after students have registered.';
        }

        if ($newDate !== $date) {
            return 'Event date cannot be changed after students have registered.';
        }

        if (($newDeadline ?: null) !== $deadline) {
            return 'Registration deadline cannot be changed after students have registered.';
        }

        return null;
    }
}
