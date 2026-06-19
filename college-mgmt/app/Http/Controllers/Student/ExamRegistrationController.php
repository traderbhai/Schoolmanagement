<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Exam, ExamRegistration, Attendance, Enrollment, FeeDemand, Semester, Student, StudentSubjectEnrollment, Term};
use Illuminate\Support\Facades\Auth;

class ExamRegistrationController extends Controller {
    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $semester = Semester::current();
        $exams = $this->eligibleExamQuery($student)
            ->with(['subject','results' => fn($q) => $q->where('student_id',$student->id)])
            ->where('exam_date', '>', now())
            ->orderBy('exam_date')
            ->get();

        // Fee dues
        $hasDues = FeeDemand::where('student_id', $student->id)
            ->where('status','!=','fully_paid')->exists();

        // Attendance eligibility per subject
        $attendancePct = [];
        $eligibleSubjectIds = $this->examEligibilityScope($student)['subject_ids'];
        $attendances = Attendance::with('timetableEntry.subject')
            ->where('student_id', $student->id)
            ->whereHas('timetableEntry', function ($entryQuery) use ($eligibleSubjectIds) {
                $this->publishedTimetableScope($entryQuery);

                if ($eligibleSubjectIds !== []) {
                    $entryQuery->whereIn('subject_id', $eligibleSubjectIds);
                }
            })
            ->get()
            ->groupBy(fn($a) => $a->timetableEntry?->subject_id);
        foreach ($attendances as $sid => $recs) {
            if (!$sid) continue;
            $total   = $recs->count();
            $present = $recs->whereIn('status',['present','late'])->count();
            $attendancePct[$sid] = $total > 0 ? round(($present/$total)*100) : null;
        }

        $eligibilityIssues = collect();
        if ($hasDues) {
            $eligibilityIssues->push('Pending fee dues must be cleared before exam registration.');
        }
        foreach ($attendancePct as $subjectId => $pct) {
            if ($pct !== null && $pct < 75) {
                $subjectName = $exams->firstWhere('subject_id', (int) $subjectId)?->subject?->name ?? 'A subject';
                $eligibilityIssues->push($subjectName . ' attendance is below the 75% eligibility threshold.');
            }
        }

        $registrations = ExamRegistration::where('student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()->keyBy('exam_id');

        return view('student.exam-registration.index',
            compact('exams','registrations','hasDues','attendancePct','semester','eligibilityIssues'));
    }

    public function register(Exam $exam) {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        if ($student->status !== 'active') {
            return back()->with('error', 'Exam registration is available only for active students. Contact the Exam Cell for archived records.');
        }

        abort_unless((int) $exam->program_id === (int) $student->program_id, 403);
        abort_unless($this->studentCanAccessExam($student, $exam), 403);
        abort_if($exam->exam_date && $exam->exam_date->isPast(), 422, 'Registration is closed for this exam.');

        if ($exam->published_at) {
            return back()->with('error', 'Registration is closed because this exam result has already been published.');
        }

        $existingRegistration = ExamRegistration::where('student_id', $student->id)
            ->where('exam_id', $exam->id)
            ->first();

        if ($existingRegistration && in_array($existingRegistration->status, ['approved', 'rejected'], true)) {
            return back()->with('error', 'This exam registration has already been reviewed and cannot be changed.');
        }

        $hasDues = FeeDemand::where('student_id',$student->id)->where('status','!=','fully_paid')->exists();

        // Attendance check
        $recs = Attendance::where('student_id',$student->id)
            ->whereHas('timetableEntry', function ($query) use ($exam) {
                $this->publishedTimetableScope($query)
                    ->where('subject_id', $exam->subject_id);
            })
            ->get();
        $total   = $recs->count();
        $present = $recs->whereIn('status',['present','late'])->count();
        $attPct  = $total > 0 ? round(($present/$total)*100) : null;
        $attEligible = $attPct === null || $attPct >= 75;

        if ($hasDues) {
            return back()->with('error', 'Clear pending fee dues before exam registration.');
        }

        if (!$attEligible) {
            return back()->with('error', 'Attendance is below the 75% eligibility threshold for this subject.');
        }

        ExamRegistration::updateOrCreate(
            ['student_id'=>$student->id, 'exam_id'=>$exam->id],
            ['status'=>'pending', 'attendance_eligible'=>$attEligible, 'fee_cleared'=>!$hasDues]
        );

        return back()->with('success', 'Registered for ' . ($exam->name ?? $exam->subject->name ?? 'exam') . '. Exam Cell will verify eligibility.');
    }

    private function eligibleExamQuery(Student $student)
    {
        $eligibility = $this->examEligibilityScope($student);

        return Exam::query()
            ->where('program_id', $student->program_id)
            ->where(function ($query) use ($eligibility) {
                $query->whereNull('subject_id');

                if ($eligibility['subject_ids'] !== []) {
                    $query->orWhereIn('subject_id', $eligibility['subject_ids']);
                }
            })
            ->where(function ($query) use ($eligibility) {
                $query->where(fn($scope) => $scope->whereNull('term_id')->whereNull('semester_id'));

                if ($eligibility['term_ids'] !== []) {
                    $query->orWhereIn('term_id', $eligibility['term_ids']);
                }

                if ($eligibility['semester_ids'] !== []) {
                    $query->orWhereIn('semester_id', $eligibility['semester_ids']);
                }
            })
            ->whereNull('published_at');
    }

    private function studentCanAccessExam(Student $student, Exam $exam): bool
    {
        if (! $exam->subject_id) {
            return true;
        }

        $eligibility = $this->examEligibilityScope($student);
        if (! in_array((int) $exam->subject_id, $eligibility['subject_ids'], true)) {
            return false;
        }

        $hasExamPeriod = $exam->term_id || $exam->semester_id;
        if (! $hasExamPeriod) {
            return true;
        }

        return ($exam->term_id && in_array((int) $exam->term_id, $eligibility['term_ids'], true))
            || ($exam->semester_id && in_array((int) $exam->semester_id, $eligibility['semester_ids'], true));
    }

    private function examEligibilityScope(Student $student): array
    {
        $canonical = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->get(['subject_id', 'term_id']);
        $canonicalTermIds = $canonical->pluck('term_id')->filter()->map(fn($id) => (int) $id)->unique()->values();
        $canonicalTerms = $canonicalTermIds->isEmpty()
            ? collect()
            : Term::whereIn('id', $canonicalTermIds)->get(['id', 'term_number', 'name']);
        $mappedSemesterIds = $canonicalTerms->flatMap(function (Term $term) {
            return Semester::where('number', $term->term_number)
                ->orWhere('name', $term->name)
                ->pluck('id');
        });

        $legacy = Enrollment::where('student_id', $student->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->get(['subject_id', 'semester_id']);

        return [
            'subject_ids' => $canonical->pluck('subject_id')
                ->merge($legacy->pluck('subject_id'))
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'term_ids' => $canonicalTermIds->all(),
            'semester_ids' => $legacy->pluck('semester_id')
                ->merge($mappedSemesterIds)
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ];
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
