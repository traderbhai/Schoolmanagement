<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{PlacementDrive, Placement, Student, Program, Company, CareerEvent, CareerEventRegistration};
use Illuminate\Http\Request;

class CmcController extends Controller
{
    public function dashboard()
    {
        $activeDrives    = PlacementDrive::whereIn('status', ['open', 'active'])->count();
        $totalPlacements = Placement::where('status', 'selected')->count();
        $totalStudents   = Student::count();
        $recentDrives    = PlacementDrive::with('company')->latest()->take(5)->get();
        $programs        = Program::where('is_active', true)->orderBy('name')->get();
        $upcomingEvents  = CareerEvent::where('event_date', '>=', now())->where('is_published', true)->orderBy('event_date')->take(5)->get();

        return view('departmental.cmc.dashboard', compact(
            'activeDrives', 'totalPlacements', 'totalStudents',
            'recentDrives', 'programs', 'upcomingEvents'
        ));
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
            'status'          => 'required|in:draft,open,active,closed,cancelled',
        ]);

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
            'status'          => 'required|in:draft,open,active,closed,cancelled',
        ]);

        $drive->update($data);
        return redirect()->route('cmc.drives')->with('success', 'Drive updated.');
    }

    public function destroyDrive(PlacementDrive $drive)
    {
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
            'application_status' => 'required|in:applied,shortlisted,interviewed,selected,rejected',
            'offered_package'    => 'nullable|string|max:100',
            'remarks'            => 'nullable|string|max:500',
        ]);

        $placement->update($request->only(['application_status', 'offered_package', 'remarks']));
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
        $company->update($data);
        return redirect()->route('cmc.companies')->with('success', 'Company updated.');
    }

    public function events(Request $request)
    {
        $query = CareerEvent::with('organizer')->latest('event_date');
        if ($request->filled('type')) $query->where('event_type', $request->type);
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
            'event_type'            => 'required|string|max:100',
            'event_date'            => 'required|date',
            'venue'                 => 'nullable|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'seats'                 => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date',
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
            'event_type'            => 'required|string|max:100',
            'event_date'            => 'required|date',
            'venue'                 => 'nullable|string|max:255',
            'description'           => 'nullable|string|max:2000',
            'seats'                 => 'nullable|integer|min:1',
            'registration_deadline' => 'nullable|date',
            'is_published'          => 'boolean',
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $event->update($data);
        return redirect()->route('cmc.events')->with('success', 'Event updated.');
    }

    public function destroyEvent(CareerEvent $event)
    {
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
}
