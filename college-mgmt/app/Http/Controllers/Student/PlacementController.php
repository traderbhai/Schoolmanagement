<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use App\Models\PlacementDrive;
use Illuminate\Http\Request;

class PlacementController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;

        $myApplicationDriveIds = $student
            ? Placement::where('student_id', $student->id)->pluck('drive_id')->toArray()
            : [];

        $drives = PlacementDrive::with('company')
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->orderBy('drive_date')
            ->get();

        $myApplications = $student
            ? Placement::with(['drive.company'])
                ->where('student_id', $student->id)
                ->latest()
                ->get()
            : collect();

        return view('student.placements', compact('drives', 'myApplications', 'myApplicationDriveIds', 'student'));
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
}
