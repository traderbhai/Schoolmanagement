<?php
namespace App\Http\Controllers\Academic;
use App\Http\Controllers\Controller;
use App\Models\{Student, AcademicTranscript, Term, ExamResult, Exam, Enrollment, StudentSubjectEnrollment};
use App\Services\GradeService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class TranscriptController extends Controller {

    public function index(Request $request) {
        $query = Student::with(['user','program','batch'])->where('status','active');
        if ($request->filled('program_id')) $query->where('program_id',$request->program_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->whereHas('user', fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"))
                  ->orWhere('enrollment_number','like',"%$s%");
            });
        }
        $students = $query->paginate(25)->withQueryString();
        $programs = \App\Models\Program::where('is_active',true)->orderBy('name')->get();
        return view('academic.transcripts.index', compact('students','programs'));
    }

    public function show(Student $student) {
        $student->load(['user','program','batch']);
        ['semesterReports' => $semesterReports, 'cgpa' => $cgpa, 'totalCredits' => $totalCredits] = $this->buildTranscriptReport($student);
        $transcript = AcademicTranscript::where('student_id',$student->id)->latest()->first();
        return view('academic.transcripts.show', compact('student','semesterReports','cgpa','totalCredits','transcript'));
    }

    public function generatePdf(Student $student) {
        $student->load(['user','program','batch']);
        $transcript = AcademicTranscript::where('student_id', $student->id)
            ->where('status', 'issued')
            ->latest('issued_at')
            ->first();

        if ($transcript && $this->hasUsableSnapshot($transcript)) {
            ['semesterReports' => $semesterReports, 'cgpa' => $cgpa, 'totalCredits' => $totalCredits] = $this->reportFromSnapshot($transcript);
        } else {
            ['semesterReports' => $semesterReports, 'cgpa' => $cgpa, 'totalCredits' => $totalCredits] = $this->buildTranscriptReport($student);

            if ($this->hasPendingTranscriptRows($semesterReports)) {
                return redirect()
                    ->route('academic.transcripts.show', $student)
                    ->with('error', 'Official transcript cannot be issued until every enrolled published-exam subject has a recorded result.');
            }

            $snapshot = $this->snapshotReport($semesterReports, $cgpa, $totalCredits);

            if ($transcript) {
                $transcript->update(['semester_data' => $snapshot]);
            } else {
                $transcript = AcademicTranscript::create([
                    'student_id' => $student->id,
                    'cgpa' => $cgpa,
                    'total_credits_earned' => $totalCredits,
                    'status' => 'issued',
                    'issued_by' => auth()->id(),
                    'issued_at' => now(),
                    'semester_data' => $snapshot,
                ]);
            }
        }

        $issuedAt = $transcript?->issued_at ?? now();

        $pdf = Pdf::loadView('pdf.academic-transcript', compact('student','semesterReports','cgpa','totalCredits','issuedAt'))
            ->setPaper('a4','portrait');
        return $pdf->stream("transcript-{$student->enrollment_number}.pdf");
    }

    private function buildTranscriptReport(Student $student): array
    {
        $gradeService = new GradeService();
        $terms = $this->transcriptTerms($student);
        $enrollmentScope = $this->enrollmentScope($student);
        $semesterReports = [];
        $totalCredits = 0;
        $earnedPoints = 0.0;

        foreach ($terms as $term) {
            $exams = $this->eligibleExamsForTerm($student, $term, $enrollmentScope);
            $termTotalCredits = 0;
            $termEarnedCredits = 0;
            $termEarnedPoints = 0.0;
            $subjects = [];

            foreach ($exams as $exam) {
                $result = ExamResult::where('exam_id', $exam->id)->where('student_id', $student->id)->first();
                $credits = (int) ($exam->subject?->credits ?? 3);

                if (!$result) {
                    $subjects[] = ['subject' => $exam->subject, 'credits' => $credits, 'obtained' => null, 'total' => $exam->total_marks, 'pct' => null, 'grade' => null, 'status' => 'pending'];
                    continue;
                }

                $pct = $exam->total_marks > 0 ? ($result->marks_obtained / $exam->total_marks) * 100 : 0;
                $grade = $gradeService->getGrade($pct);
                $termTotalCredits += $credits;
                if ($grade['letter'] !== 'F') {
                    $termEarnedCredits += $credits;
                    $termEarnedPoints += $credits * $grade['points'];
                }
                $totalCredits += $credits;
                $earnedPoints += $credits * $grade['points'];
                $subjects[] = ['subject' => $exam->subject, 'credits' => $credits, 'obtained' => $result->marks_obtained, 'total' => $exam->total_marks, 'pct' => round($pct, 1), 'grade' => $grade, 'status' => $grade['letter'] === 'F' ? 'fail' : 'pass'];
            }

            $sgpa = $termTotalCredits > 0 ? round($termEarnedPoints / $termTotalCredits, 2) : 0;
            $semesterReports[] = ['term' => $term, 'subjects' => $subjects, 'sgpa' => $sgpa, 'earned_credits' => $termEarnedCredits, 'total_credits' => $termTotalCredits];
        }

        return [
            'semesterReports' => $semesterReports,
            'cgpa' => $totalCredits > 0 ? round($earnedPoints / $totalCredits, 2) : 0,
            'totalCredits' => $totalCredits,
        ];
    }

    private function transcriptTerms(Student $student): Collection
    {
        $canonicalTermIds = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereNotNull('term_id')
            ->pluck('term_id');

        $query = Term::query();
        if ($canonicalTermIds->isNotEmpty()) {
            $query->whereIn('id', $canonicalTermIds);
        } elseif ($student->batch_id) {
            $query->where('batch_id', $student->batch_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->orderBy('term_number')->get();
    }

    private function eligibleExamsForTerm(Student $student, Term $term, array $scope): Collection
    {
        $semesterIds = $this->semesterIdsForTerm($term);

        return Exam::where('program_id', $student->program_id)
            ->whereNotNull('published_at')
            ->whereIn('subject_id', $scope['subject_ids'])
            ->where(function ($query) use ($term, $semesterIds) {
                $query->where('term_id', $term->id);

                if ($semesterIds !== []) {
                    $query->orWhereIn('semester_id', $semesterIds);
                }
            })
            ->with('subject')
            ->orderBy('subject_id')
            ->orderBy('exam_date')
            ->get()
            ->unique('id')
            ->values();
    }

    private function enrollmentScope(Student $student): array
    {
        $canonical = StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->get(['subject_id', 'term_id']);
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
        ];
    }

    private function semesterIdsForTerm(Term $term): array
    {
        return \App\Models\Semester::where('number', $term->term_number)
            ->orWhere('name', $term->name)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function hasUsableSnapshot(AcademicTranscript $transcript): bool
    {
        return is_array($transcript->semester_data)
            && array_key_exists('semester_reports', $transcript->semester_data)
            && array_key_exists('cgpa', $transcript->semester_data)
            && array_key_exists('total_credits', $transcript->semester_data);
    }

    private function hasPendingTranscriptRows(array $semesterReports): bool
    {
        foreach ($semesterReports as $report) {
            foreach ($report['subjects'] ?? [] as $subject) {
                if (($subject['status'] ?? null) === 'pending') {
                    return true;
                }
            }
        }

        return false;
    }

    private function snapshotReport(array $semesterReports, float $cgpa, int $totalCredits): array
    {
        return [
            'version' => 1,
            'cgpa' => $cgpa,
            'total_credits' => $totalCredits,
            'semester_reports' => collect($semesterReports)->map(function (array $report) {
                return [
                    'term' => [
                        'id' => $report['term']->id,
                        'name' => $report['term']->name,
                        'term_number' => $report['term']->term_number,
                    ],
                    'sgpa' => $report['sgpa'],
                    'earned_credits' => $report['earned_credits'],
                    'total_credits' => $report['total_credits'],
                    'subjects' => collect($report['subjects'])->map(function (array $subject) {
                        return [
                            'subject' => [
                                'id' => $subject['subject']?->id,
                                'name' => $subject['subject']?->name,
                                'code' => $subject['subject']?->code,
                            ],
                            'credits' => $subject['credits'],
                            'obtained' => $subject['obtained'],
                            'total' => $subject['total'],
                            'pct' => $subject['pct'],
                            'grade' => $subject['grade'],
                            'status' => $subject['status'],
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function reportFromSnapshot(AcademicTranscript $transcript): array
    {
        $snapshot = $transcript->semester_data;

        return [
            'semesterReports' => $snapshot['semester_reports'] ?? [],
            'cgpa' => (float) ($snapshot['cgpa'] ?? $transcript->cgpa),
            'totalCredits' => (int) ($snapshot['total_credits'] ?? $transcript->total_credits_earned),
        ];
    }
}
