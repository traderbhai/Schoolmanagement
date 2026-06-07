<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcademicCalendarController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $startOfMonth = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $events = AcademicEvent::where('is_public', true)
            ->where(function ($q) use ($student) {
                $q->whereNull('program_id')->orWhere('program_id', $student->program_id);
            })
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth]);
            })
            ->orderBy('start_date')
            ->get();

        $eventsByDate = [];
        foreach ($events as $event) {
            $key = $event->start_date->format('Y-m-d');
            $eventsByDate[$key][] = $event;
        }

        return view('student.calendar.index', compact('events', 'eventsByDate', 'month', 'year', 'startOfMonth'));
    }
}
