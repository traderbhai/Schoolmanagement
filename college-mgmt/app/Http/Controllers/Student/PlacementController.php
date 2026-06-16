<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use App\Models\PlacementDrive;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    private const VISIBLE_DRIVE_STATUSES = ['upcoming', 'ongoing'];

    public function index()
    {
        $student = auth()->user()->student;
        $studentCgpa = $student ? (float) $student->calculateCGPA() : null;

        $myApplicationDriveIds = $student
            ? Placement::where('student_id', $student->id)->pluck('drive_id')->toArray()
            : [];

        $drives = PlacementDrive::with('company')
            ->whereIn('status', self::VISIBLE_DRIVE_STATUSES)
            ->orderBy('drive_date')
            ->get()
            ->map(function (PlacementDrive $drive) use ($student, $studentCgpa) {
                $drive->student_eligibility = $this->driveEligibility($student, $drive, $studentCgpa);
                return $drive;
            });

        $myApplications = $student
            ? Placement::with(['drive.company'])
                ->where('student_id', $student->id)
                ->latest()
                ->get()
            : collect();

        $placementPriority = $this->placementPriority($student, $drives, $myApplications);

        return view('student.placements', compact('drives', 'myApplications', 'myApplicationDriveIds', 'student', 'studentCgpa', 'placementPriority'));
    }

    public function myApplications()
    {
        $student = auth()->user()->student;

        $myApplications = $student
            ? Placement::with(['drive.company'])
                ->where('student_id', $student->id)
                ->latest()
                ->get()
            : collect();

        return view('student.my-applications', compact('myApplications', 'student'));
    }

    public function apply(Request $request, PlacementDrive $drive)
    {
        $student = auth()->user()->student;

        if (!$student) {
            return redirect()->route('student.placements')->with('error', 'Student profile not found.');
        }

        if (! in_array($drive->status, self::VISIBLE_DRIVE_STATUSES, true)) {
            return redirect()->route('student.placements')->with('error', 'This placement drive is not open for applications.');
        }

        if ($drive->last_apply_date && $drive->last_apply_date->lt(now()->startOfDay())) {
            return redirect()->route('student.placements')->with('error', 'The application deadline for this drive has passed.');
        }

        $eligibility = $this->driveEligibility($student, $drive, (float) $student->calculateCGPA());
        if (! $eligibility['eligible']) {
            return redirect()->route('student.placements')->with('error', $eligibility['reason']);
        }

        // Check if already applied
        $exists = Placement::where('drive_id', $drive->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($exists) {
            return redirect()->route('student.placements')->with('error', 'You have already applied to this drive.');
        }

        Placement::create([
            'drive_id'           => $drive->id,
            'student_id'         => $student->id,
            'application_status' => 'applied',
        ]);

        return redirect()->route('student.placements')->with('success', 'Application submitted successfully.');
    }

    private function driveEligibility($student, PlacementDrive $drive, ?float $studentCgpa = null): array
    {
        if (! $student) {
            return ['eligible' => false, 'reason' => 'Student profile not found.'];
        }

        if ($drive->min_cgpa !== null && $drive->min_cgpa !== '') {
            $requiredCgpa = (float) $drive->min_cgpa;
            $actualCgpa = $studentCgpa ?? (float) $student->calculateCGPA();
            if ($actualCgpa < $requiredCgpa) {
                return [
                    'eligible' => false,
                    'reason' => 'You do not meet the minimum CGPA requirement for this drive.',
                    'detail' => 'Required CGPA ' . number_format($requiredCgpa, 2) . '; your CGPA ' . number_format($actualCgpa, 2) . '.',
                ];
            }
        }

        return ['eligible' => true, 'reason' => null, 'detail' => null];
    }

    private function placementPriority($student, $drives, $myApplications): array
    {
        if (! $student) {
            return [
                'level' => 'warning',
                'title' => 'Complete your student profile',
                'body' => 'Your student profile is required before you can apply to placement drives.',
                'route' => route('student.dashboard'),
                'action' => 'Open Dashboard',
            ];
        }

        $selected = $myApplications->firstWhere('application_status', 'selected');
        if ($selected) {
            return [
                'level' => 'success',
                'title' => 'Placement offer selected',
                'body' => 'Review your selected placement details and wait for joining or offer-letter instructions from CMC.',
                'route' => route('student.placements.applications'),
                'action' => 'View Applications',
            ];
        }

        $inProgress = $myApplications->first(fn($application) => in_array($application->application_status, ['shortlisted', 'interview'], true));
        if ($inProgress) {
            return [
                'level' => 'warning',
                'title' => 'Placement process in progress',
                'body' => 'You have an active shortlist or interview stage. Track updates and prepare for the next CMC instruction.',
                'route' => route('student.placements.applications'),
                'action' => 'Track Status',
            ];
        }

        $availableDrives = $drives->filter(fn($drive) =>
            ! $myApplications->contains('drive_id', $drive->id)
            && (! $drive->last_apply_date || $drive->last_apply_date->isToday() || $drive->last_apply_date->isFuture())
        );

        $deadlineSoon = $availableDrives
            ->filter(fn($drive) => $drive->last_apply_date && $drive->last_apply_date->between(now()->startOfDay(), now()->addDays(3)->endOfDay()))
            ->sortBy('last_apply_date')
            ->first();

        if ($deadlineSoon) {
            return [
                'level' => 'danger',
                'title' => 'Placement deadline is near',
                'body' => "{$deadlineSoon->title} closes on {$deadlineSoon->last_apply_date->format('d M Y')}. Apply if you are eligible.",
                'route' => route('student.placements'),
                'action' => 'Review Drives',
            ];
        }

        if ($availableDrives->isNotEmpty()) {
            return [
                'level' => 'info',
                'title' => $availableDrives->count() . ' placement drive' . ($availableDrives->count() === 1 ? '' : 's') . ' available',
                'body' => 'Review eligibility, package, location, and deadline before applying.',
                'route' => route('student.placements'),
                'action' => 'Browse Drives',
            ];
        }

        if ($myApplications->isNotEmpty()) {
            return [
                'level' => 'none',
                'title' => 'No new drives to apply for',
                'body' => 'You have applied to the current opportunities. Track application status while CMC updates outcomes.',
                'route' => route('student.placements.applications'),
                'action' => 'View Applications',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No placement drives are open',
            'body' => 'Check back later for upcoming or ongoing drives published by CMC.',
            'route' => route('student.placements.applications'),
            'action' => 'My Applications',
        ];
    }
}
