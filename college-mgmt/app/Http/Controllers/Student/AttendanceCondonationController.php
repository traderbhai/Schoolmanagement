<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{AttendanceCondonation, Subject, Attendance, Enrollment, Student, StudentSubjectEnrollment};
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceCondonationController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $condonations = AttendanceCondonation::with('subject')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')->paginate(15);
        $canRequestCondonation = $student->status === 'active';

        return view('student.condonation.index', compact('condonations', 'canRequestCondonation'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.condonation.index')
                ->with('error', 'Attendance condonation requests are available only for active students. Contact the academic office for archived records.');
        }

        $lowSubjects = $this->eligibleLowAttendanceSubjects($student)->values()->all();

        return view('student.condonation.create', compact('lowSubjects'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.condonation.index')
                ->with('error', 'Attendance condonation requests are available only for active students. Contact the academic office for archived records.');
        }

        $data = $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'reason'     => 'required|string|max:1000',
        ]);

        $eligible = $this->eligibleLowAttendanceSubjects($student)
            ->first(fn ($row) => (int) $row['subject']->id === (int) $data['subject_id']);

        if (! $eligible) {
            return back()
                ->withInput()
                ->withErrors(['subject_id' => 'Condonation can be requested only for an enrolled subject with attendance below 75%.']);
        }

        $openRequestExists = AttendanceCondonation::where('student_id', $student->id)
            ->where('subject_id', $data['subject_id'])
            ->where(function ($query) use ($eligible) {
                $eligible['term_id']
                    ? $query->where('term_id', $eligible['term_id'])
                    : $query->whereNull('term_id');
            })
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($openRequestExists) {
            return back()
                ->withInput()
                ->withErrors(['subject_id' => 'You already have an open condonation request for this subject.']);
        }

        AttendanceCondonation::create([
            'student_id'          => $student->id,
            'subject_id'          => $data['subject_id'],
            'term_id'             => $eligible['term_id'],
            'reason'              => $data['reason'],
            'sessions_requested'  => max(1, (int) $eligible['deficit']),
            'status'              => 'pending',
        ]);

        return redirect()->route('student.condonation.index')
            ->with('success', 'Condonation request submitted.');
    }

    private function eligibleLowAttendanceSubjects(Student $student): Collection
    {
        $termBySubject = $this->activeEnrollmentTermsBySubject($student);
        if ($termBySubject->isEmpty()) {
            return collect();
        }

        return Attendance::with('timetableEntry.subject')
            ->where('student_id', $student->id)
            ->whereHas('timetableEntry', function ($query) use ($termBySubject) {
                $this->publishedTimetableScope($query)
                    ->whereIn('subject_id', $termBySubject->keys());
            })
            ->get()
            ->groupBy(fn ($attendance) => $attendance->timetableEntry?->subject_id)
            ->map(function ($records, $subjectId) use ($termBySubject) {
                $total = $records->count();
                if ($total === 0) {
                    return null;
                }

                $present = $records->whereIn('status', ['present', 'late'])->count();
                $pct = round(($present / $total) * 100);
                if ($pct >= 75) {
                    return null;
                }

                $subject = $records->first()->timetableEntry?->subject;
                if (! $subject) {
                    return null;
                }

                return [
                    'subject' => $subject,
                    'term_id' => $termBySubject->get((int) $subjectId),
                    'pct' => $pct,
                    'deficit' => $total - $present,
                ];
            })
            ->filter()
            ->values();
    }

    private function activeEnrollmentTermsBySubject(Student $student): Collection
    {
        $canonical = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->orderByRaw('CASE WHEN term_id = ? THEN 0 ELSE 1 END', [$student->current_term_id ?? 0])
            ->get(['subject_id', 'term_id']);

        $terms = collect();
        foreach ($canonical as $enrollment) {
            $terms->put((int) $enrollment->subject_id, $enrollment->term_id);
        }

        Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->get(['subject_id', 'term_id'])
            ->each(function (Enrollment $enrollment) use ($terms) {
                if (! $terms->has((int) $enrollment->subject_id)) {
                    $terms->put((int) $enrollment->subject_id, $enrollment->term_id);
                }
            });

        return $terms;
    }

    private function publishedTimetableScope($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($versionQuery) {
                $versionQuery->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            });
    }
}
