<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\StudyMaterial;
use App\Models\SubjectAnnouncement;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CourseHubController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        return $student;
    }

    private function ensureEnrolled($student, Subject $subject): void
    {
        abort_unless(
            $this->isEnrolledInSubject($student, $subject),
            403,
            'You are not enrolled in this subject.'
        );
    }

    public function index()
    {
        $student = $this->getStudent();
        $subjects = $this->enrolledSubjects($student);

        return view('student.courses.index', compact('subjects'));
    }

    public function show(Subject $subject)
    {
        $student = $this->getStudent();
        $this->ensureEnrolled($student, $subject);

        $materials = StudyMaterial::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('type');

        $announcements = SubjectAnnouncement::where('subject_id', $subject->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(5)
            ->with('poster')
            ->get();

        $assignments = Assignment::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->with(['submissions' => fn($q) => $q->where('student_id', $student->id)])
            ->orderBy('due_at')
            ->get();

        $quizzes = Quiz::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->with(['attempts' => fn($q) => $q->where('student_id', $student->id)])
            ->get();

        return view('student.courses.show', compact('subject', 'materials', 'announcements', 'assignments', 'quizzes'));
    }

    private function enrolledSubjects(Student $student): Collection
    {
        $subjectIds = $this->enrolledSubjectIds($student);

        if ($subjectIds->isEmpty()) {
            return collect();
        }

        $canonicalFacultyBySubject = $this->canonicalFacultyBySubject($student, $subjectIds);
        $canonicalSubjectIds = $canonicalFacultyBySubject->keys();

        $facultyBySubject = TimetableEntry::with('teacher.user')
            ->whereIn('subject_id', $subjectIds)
            ->whereNotIn('subject_id', $canonicalSubjectIds)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('timetable_version_id')
                    ->orWhereHas('version', fn ($version) => $version->where('status', 'published'));
            })
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($entries) => $entries
                ->map(fn (TimetableEntry $entry) => $entry->teacher?->user?->name)
                ->filter()
                ->unique()
                ->values()
                ->all()
            );

        return Subject::whereIn('id', $subjectIds)
            ->orderBy('name')
            ->get()
            ->map(function (Subject $subject) use ($facultyBySubject, $canonicalFacultyBySubject) {
                $subject->faculty_names = $canonicalFacultyBySubject->get($subject->id, $facultyBySubject->get($subject->id, []));
                return $subject;
            });
    }

    private function canonicalFacultyBySubject(Student $student, Collection $subjectIds): Collection
    {
        $courseGroupIds = AcademicPmcCourseGroupMember::where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('courseGroup', fn (Builder $group) => $group->whereIn('subject_id', $subjectIds))
            ->pluck('course_group_id')
            ->unique()
            ->values();

        if ($courseGroupIds->isEmpty()) {
            return collect();
        }

        return AcademicPmcTimetableGenerationItem::with(['teacher.user', 'subject', 'courseGroup.subject', 'timetableVersion'])
            ->whereIn('course_group_id', $courseGroupIds)
            ->whereIn('status', ['scheduled', 'published', 'locked'])
            ->where('official_status', 'published')
            ->whereNotNull('timetable_version_id')
            ->whereNotNull('teacher_id')
            ->whereHas('timetableVersion', fn (Builder $version) => $version->where('status', 'published'))
            ->get()
            ->groupBy(fn (AcademicPmcTimetableGenerationItem $item) => $item->subject_id ?: $item->courseGroup?->subject_id)
            ->map(fn (Collection $items) => $items
                ->map(fn (AcademicPmcTimetableGenerationItem $item) => $item->teacher?->user?->name)
                ->filter()
                ->unique()
                ->values()
                ->all()
            );
    }

    private function enrolledSubjectIds(Student $student): Collection
    {
        return StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('subject_id')
            ->merge(
                Enrollment::where('student_id', $student->id)
                    ->whereIn('status', ['active', 'enrolled'])
                    ->pluck('subject_id')
            )
            ->filter()
            ->unique()
            ->values();
    }

    private function isEnrolledInSubject(Student $student, Subject $subject): bool
    {
        return StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->exists()
            || Enrollment::where('student_id', $student->id)
                ->where('subject_id', $subject->id)
                ->whereIn('status', ['active', 'enrolled'])
                ->exists();
    }
}
