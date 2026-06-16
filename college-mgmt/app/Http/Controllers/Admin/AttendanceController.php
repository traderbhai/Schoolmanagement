<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Attendance, TimetableEntry, Student, Semester, Course, Term};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request) {
        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $courses   = Course::where('is_active', true)->orderBy('name')->get();
        $students  = Student::with('user')->orderBy('id')->get();
        return view('admin.attendance.index', compact('semesters', 'courses', 'students'));
    }

    public function entriesJson(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'course_id'   => 'required|exists:courses,id',
            'date'        => 'required|date',
        ]);

        $dayOfWeek = (int) date('N', strtotime($request->date)); // 1=Mon, 7=Sun

        $entries = \App\Models\TimetableEntry::with(['subject', 'slot', 'classroom'])
            ->where('semester_id', $request->semester_id)
            ->where('course_id', $request->course_id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->map(fn($e) => [
                'id'    => $e->id,
                'label' => $e->subject->name . ' — ' . $e->slot->name . ' (' . $e->slot->start_time . '-' . $e->slot->end_time . ') · ' . $e->classroom->room_number,
            ]);

        return response()->json($entries);
    }

    public function mark(Request $request) {
        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date',
        ]);

        $entry = TimetableEntry::with(['course','subject'])->findOrFail($request->timetable_entry_id);
        $students = $this->eligibleStudentsForEntry($entry)->with(['user',
            'attendances' => fn($q) => $q->where('timetable_entry_id', $entry->id)->where('date', $request->date)
        ])->orderBy('roll_number')->get();

        return view('admin.attendance.mark', compact('entry','students','request'));
    }

    public function store(Request $request) {
        $request->validate([
            'timetable_entry_id' => 'required|exists:timetable_entries,id',
            'date'               => 'required|date',
            'attendance'         => 'required|array',
        ]);

        $entry = TimetableEntry::findOrFail($request->timetable_entry_id);
        $allowedStudentIds = $this->eligibleStudentsForEntry($entry)->pluck('id')->map(fn($id) => (string) $id)->all();
        $submittedStudentIds = array_map('strval', array_keys($request->attendance));
        abort_unless(empty(array_diff($submittedStudentIds, $allowedStudentIds)), 403, 'Attendance includes students outside this class roster.');

        foreach ($request->attendance as $studentId => $status) {
            Attendance::updateOrCreate(
                ['student_id' => $studentId, 'timetable_entry_id' => $request->timetable_entry_id, 'date' => $request->date],
                ['status' => $status]
            );
        }

        return redirect()->route('admin.attendance.index')->with('success', 'Attendance marked.');
    }

    public function report(Request $request) {
        $report = null;
        if ($request->student_id && $request->semester_id) {
            $termIds = $this->termIdsForSemester((int) $request->semester_id);
            $report = Attendance::with(['timetableEntry.subject'])
                ->where('student_id', $request->student_id)
                ->whereHas('timetableEntry', function ($q) use ($request, $termIds) {
                    $q->where('semester_id', $request->semester_id);

                    if ($termIds !== []) {
                        $q->orWhereIn('term_id', $termIds);
                    }
                })
                ->get()
                ->groupBy('timetableEntry.subject.name');
        }
        $students = Student::with('user')->where('status','active')->get();
        $semesters = Semester::with('academicYear')->get();
        return view('admin.attendance.report', compact('report','students','semesters','request'));
    }

    public function export(Request $r)
    {
        $query = Attendance::with(['student.user', 'timetableEntry.subject'])
            ->when($r->course_id, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('course_id', $r->course_id)))
            ->when($r->semester_id, function ($q) use ($r) {
                $termIds = $this->termIdsForSemester((int) $r->semester_id);
                $q->whereHas('timetableEntry', function ($sq) use ($r, $termIds) {
                    $sq->where('semester_id', $r->semester_id);

                    if ($termIds !== []) {
                        $sq->orWhereIn('term_id', $termIds);
                    }
                });
            })
            ->when($r->date_from, fn($q) => $q->whereDate('date', '>=', $r->date_from))
            ->when($r->date_to, fn($q) => $q->whereDate('date', '<=', $r->date_to))
            ->orderBy('date', 'desc');

        $records = $query->get();

        $filename = 'attendance_' . now()->format('Ymd_His') . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student Name','Enrollment No','Subject','Date','Status']);
            foreach ($records as $a) {
                fputcsv($file, [
                    $a->student->user->name ?? '',
                    $a->student->enrollment_number ?? '',
                    $a->timetableEntry->subject->name ?? '',
                    $a->date instanceof \Carbon\Carbon ? $a->date->format('d/m/Y') : $a->date,
                    $a->status,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function pdfReport(Request $r)
    {
        $report = null;
        $student = null;
        $semester = null;

        if ($r->student_id && $r->semester_id) {
            $student = Student::with('user', 'course', 'department')->findOrFail($r->student_id);
            $semester = Semester::with('academicYear')->findOrFail($r->semester_id);
            $termIds = $this->termIdsForSemester((int) $r->semester_id);

            $report = Attendance::with(['timetableEntry.subject'])
                ->where('student_id', $r->student_id)
                ->whereHas('timetableEntry', function ($q) use ($r, $termIds) {
                    $q->where('semester_id', $r->semester_id);

                    if ($termIds !== []) {
                        $q->orWhereIn('term_id', $termIds);
                    }
                })
                ->get()
                ->groupBy('timetableEntry.subject.name');
        }

        $pdf = Pdf::loadView('admin.attendance.pdf-report', compact('report', 'student', 'semester', 'r'));
        return $pdf->setPaper('a4')->stream('attendance-report.pdf');
    }

    private function eligibleStudentsForEntry(TimetableEntry $entry)
    {
        $termIds = $this->termIdsForEntry($entry);

        return Student::where(function ($query) use ($entry, $termIds) {
            $query->whereHas('enrollments', function ($q) use ($entry) {
                $q->where('semester_id', $entry->semester_id)
                    ->where('subject_id', $entry->subject_id)
                    ->whereIn('status', ['active', 'enrolled']);
            })->orWhereHas('subjectEnrollments', function ($q) use ($entry, $termIds) {
                $q->where('subject_id', $entry->subject_id)
                    ->where('status', 'active');

                $termIds === []
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('term_id', $termIds);
            });
        });
    }

    private function termIdsForEntry(TimetableEntry $entry): array
    {
        if ($entry->term_id) {
            return [(int) $entry->term_id];
        }

        if (! $entry->semester_id) {
            return [];
        }

        $semester = $entry->semester ?: Semester::find($entry->semester_id);
        if (! $semester) {
            return [];
        }

        return Term::query()
            ->when($entry->program_id, fn($q) => $q->where('program_id', $entry->program_id))
            ->when($entry->batch_id, fn($q) => $q->where('batch_id', $entry->batch_id))
            ->where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function termIdsForSemester(int $semesterId): array
    {
        $semester = Semester::find($semesterId);
        if (! $semester) {
            return [];
        }

        return Term::where(fn($q) => $q->where('term_number', $semester->number)->orWhere('name', $semester->name))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }
}
