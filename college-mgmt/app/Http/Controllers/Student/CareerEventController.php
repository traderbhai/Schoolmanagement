<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{CareerEvent, CareerEventRegistration};
use Illuminate\Support\Facades\Auth;

class CareerEventController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $upcoming = CareerEvent::where('is_published', true)
            ->where('event_date', '>=', today())
            ->withCount('registrations')
            ->orderBy('event_date')->get();

        $past = CareerEvent::where('is_published', true)
            ->where('event_date', '<', today())
            ->orderByDesc('event_date')->limit(10)->get();

        $myRegistrations = CareerEventRegistration::where('student_id', $student->id)
            ->pluck('career_event_id')->toArray();

        return view('student.career-events.index', compact('upcoming','past','myRegistrations'));
    }

    public function register(CareerEvent $event) {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        abort_unless($event->isOpen(), 422, 'Registration is closed for this event.');

        CareerEventRegistration::firstOrCreate([
            'career_event_id' => $event->id,
            'student_id'      => $student->id,
        ]);

        return back()->with('success', 'Registered for "' . $event->title . '".');
    }
}
