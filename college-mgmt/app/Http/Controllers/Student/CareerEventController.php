<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{CareerEvent, CareerEventRegistration};
use Illuminate\Support\Facades\Auth;

class CareerEventController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $canManageCareerEventRegistrations = $student->status === 'active';

        $upcoming = CareerEvent::where('is_published', true)
            ->where('event_date', '>=', today())
            ->withCount('registrations')
            ->orderBy('event_date')->get();

        $past = CareerEvent::where('is_published', true)
            ->where('event_date', '<', today())
            ->orderByDesc('event_date')->limit(10)->get();

        $myRegistrations = CareerEventRegistration::where('student_id', $student->id)
            ->pluck('career_event_id')->toArray();

        return view('student.career-events.index', compact('upcoming','past','myRegistrations', 'canManageCareerEventRegistrations'));
    }

    public function register(CareerEvent $event) {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        abort_unless($student->status === 'active', 422, 'Career event registration is available only for active students.');
        abort_unless($event->isOpen(), 422, 'Registration is closed for this event.');

        CareerEventRegistration::firstOrCreate([
            'career_event_id' => $event->id,
            'student_id'      => $student->id,
        ]);

        return back()->with('success', 'Registered for "' . $event->title . '".');
    }

    public function cancel(CareerEvent $event) {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        abort_unless($student->status === 'active', 422, 'Career event cancellation is available only for active students.');
        abort_if($event->event_date && $event->event_date->isPast() && ! $event->event_date->isToday(), 422, 'Past event registrations cannot be cancelled.');
        abort_if($event->registration_deadline && $event->registration_deadline->isPast() && ! $event->registration_deadline->isToday(), 422, 'Cancellation is closed for this event.');

        $deleted = CareerEventRegistration::where('career_event_id', $event->id)
            ->where('student_id', $student->id)
            ->where('attended', false)
            ->delete();

        abort_unless($deleted > 0, 422, 'No cancellable registration found for this event.');

        return back()->with('success', 'Registration cancelled for "' . $event->title . '".');
    }
}
