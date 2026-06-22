<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseFeedback;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\MentorMeeting;
use App\Models\MentorMessage;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherScopeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $course = Course::factory()->create();
        $semester = Semester::factory()->create(['is_current' => true]);
        $teacher = Teacher::factory()->create(['status' => 'active']);
        $teacher->user->assignRole('teacher');
        $assignedSubject = Subject::factory()->create(['program_id' => $program->id]);
        $otherSubject = Subject::factory()->create(['program_id' => $program->id]);
        $slot = TimetableSlot::factory()->create();
        $classroom = Classroom::factory()->create();

        $entry = TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'subject_id' => $assignedSubject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
        ]);

        $enrolled = Student::factory()->create(['program_id' => $program->id, 'course_id' => $course->id]);
        $outsider = Student::factory()->create(['program_id' => $program->id, 'course_id' => $course->id]);
        Enrollment::create([
            'student_id' => $enrolled->id,
            'semester_id' => $semester->id,
            'subject_id' => $assignedSubject->id,
            'status' => 'active',
        ]);

        return compact('teacher', 'assignedSubject', 'otherSubject', 'semester', 'entry', 'enrolled', 'outsider', 'program');
    }

    public function test_teacher_cannot_create_learning_content_for_unassigned_subject(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.store'), [
                'subject_id' => $fixture['otherSubject']->id,
                'title' => 'Wrong Subject Assignment',
                'description' => 'Should not be created.',
                'max_marks' => 10,
                'due_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.materials.store'), [
                'subject_id' => $fixture['otherSubject']->id,
                'title' => 'Wrong Subject Notes',
                'type' => 'notes',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.announcements.store'), [
                'subject_id' => $fixture['otherSubject']->id,
                'title' => 'Wrong Subject Announcement',
                'body' => 'Should not be posted.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignments', ['title' => 'Wrong Subject Assignment']);
        $this->assertDatabaseMissing('study_materials', ['title' => 'Wrong Subject Notes']);
        $this->assertDatabaseMissing('subject_announcements', ['title' => 'Wrong Subject Announcement']);
    }

    public function test_teacher_cannot_publish_learning_content_for_draft_timetable_subjects(): void
    {
        $fixture = $this->fixture();
        $draftSubject = Subject::factory()->create(['program_id' => $fixture['program']->id, 'name' => 'Draft Teacher Subject']);
        $draftVersionSubject = Subject::factory()->create(['program_id' => $fixture['program']->id, 'name' => 'Draft Version Teacher Subject']);
        $term = Term::factory()->create(['program_id' => $fixture['program']->id]);
        $course = Course::factory()->create();
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $fixture['teacher']->user_id,
        ]);

        TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => $fixture['teacher']->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'draft',
        ]);
        TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $course->id,
            'term_id' => $term->id,
            'subject_id' => $draftVersionSubject->id,
            'teacher_id' => $fixture['teacher']->id,
            'day_of_week' => now()->dayOfWeekIso,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        CourseFeedback::create([
            'student_id' => $fixture['enrolled']->id,
            'subject_id' => $draftSubject->id,
            'term_id' => null,
            'teaching_rating' => 1,
            'content_rating' => 1,
            'overall_rating' => 1,
            'comments' => 'Draft subject feedback should not show.',
        ]);

        foreach ([$draftSubject, $draftVersionSubject] as $subject) {
            $this->actingAs($fixture['teacher']->user)
                ->post(route('teacher.assignments.store'), [
                    'subject_id' => $subject->id,
                    'title' => 'Draft Subject Assignment',
                    'description' => 'Should not publish before timetable is official.',
                    'max_marks' => 10,
                    'due_at' => now()->addWeek()->toDateTimeString(),
                ])
                ->assertForbidden();

            $this->actingAs($fixture['teacher']->user)
                ->post(route('teacher.materials.store'), [
                    'subject_id' => $subject->id,
                    'title' => 'Draft Subject Material',
                    'type' => 'notes',
                    'description' => 'Should not publish before timetable is official.',
                ])
                ->assertForbidden();

            $this->actingAs($fixture['teacher']->user)
                ->post(route('teacher.announcements.store'), [
                    'subject_id' => $subject->id,
                    'title' => 'Draft Subject Announcement',
                    'body' => 'Should not publish before timetable is official.',
                ])
                ->assertForbidden();
        }

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.feedback.index'))
            ->assertOk()
            ->assertDontSee('Draft Teacher Subject')
            ->assertDontSee('Draft subject feedback should not show.');

        $this->assertDatabaseMissing('assignments', ['title' => 'Draft Subject Assignment']);
        $this->assertDatabaseMissing('study_materials', ['title' => 'Draft Subject Material']);
        $this->assertDatabaseMissing('subject_announcements', ['title' => 'Draft Subject Announcement']);
    }

    public function test_teacher_content_and_exam_scope_use_canonical_pmc_official_subjects(): void
    {
        $fixture = $this->fixture();
        $batch = Batch::factory()->create(['program_id' => $fixture['program']->id]);
        $term = Term::factory()->create([
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'start_date' => now(),
        ]);
        $canonicalSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'name' => 'Canonical Teacher Content Subject',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Teacher Content Group',
            'group_type' => 'core_section',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $canonicalSubject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $version = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $fixture['teacher']->user_id,
            'published_by' => $fixture['teacher']->user_id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Teacher Content Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $fixture['teacher']->user_id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $canonicalSubject->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $fixture['teacher']->user_id,
        ]);
        $draftCanonicalSubject = Subject::factory()->create([
            'program_id' => $fixture['program']->id,
            'name' => 'Draft Canonical Teacher Content Subject',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $fixture['teacher']->user_id,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $draftVersion->id,
            'course_group_id' => $group->id,
            'program_id' => $fixture['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $draftCanonicalSubject->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'day_of_week' => now()->dayOfWeekIso,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
        ]);
        $canonicalStudent = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['enrolled']->course_id,
            'roll_number' => 'CAN-CONTENT-001',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $canonicalStudent->id,
            'subject_id' => $canonicalSubject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        CourseFeedback::create([
            'student_id' => $canonicalStudent->id,
            'subject_id' => $canonicalSubject->id,
            'term_id' => $term->id,
            'teaching_rating' => 5,
            'content_rating' => 5,
            'overall_rating' => 5,
            'comments' => 'Canonical official feedback visible.',
        ]);
        CourseFeedback::create([
            'student_id' => $fixture['enrolled']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'term_id' => $term->id,
            'teaching_rating' => 1,
            'content_rating' => 1,
            'overall_rating' => 1,
            'comments' => 'Legacy flattened feedback should be hidden.',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.store'), [
                'subject_id' => $canonicalSubject->id,
                'title' => 'Canonical Official Assignment',
                'description' => 'Created from canonical PMC session.',
                'max_marks' => 10,
                'due_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertRedirect(route('teacher.assignments.index'));

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.materials.store'), [
                'subject_id' => $canonicalSubject->id,
                'title' => 'Canonical Official Material',
                'type' => 'notes',
                'description' => 'Canonical official notes.',
            ])
            ->assertRedirect(route('teacher.materials.index'));

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.announcements.store'), [
                'subject_id' => $canonicalSubject->id,
                'title' => 'Canonical Official Announcement',
                'body' => 'Canonical official announcement body.',
            ])
            ->assertRedirect();

        foreach ([$fixture['assignedSubject'], $draftCanonicalSubject] as $blockedSubject) {
            $this->actingAs($fixture['teacher']->user)
                ->post(route('teacher.assignments.store'), [
                    'subject_id' => $blockedSubject->id,
                    'title' => 'Blocked Non Official Assignment',
                    'max_marks' => 10,
                    'due_at' => now()->addWeek()->toDateTimeString(),
                ])
                ->assertForbidden();
        }

        $canonicalExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $canonicalSubject->id,
            'exam_date' => now()->subDay()->toDateString(),
            'total_marks' => 100,
        ]);
        $legacyExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'name' => 'Legacy Flattened Teacher Exam',
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.feedback.index'))
            ->assertOk()
            ->assertSee('Canonical Teacher Content Subject')
            ->assertSee('Canonical official feedback visible.')
            ->assertDontSee('Legacy flattened feedback should be hidden.')
            ->assertDontSee('Draft Canonical Teacher Content Subject');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.exams.index'))
            ->assertOk()
            ->assertSee($canonicalExam->name)
            ->assertDontSee('Legacy Flattened Teacher Exam');

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $canonicalExam), [
                'results' => [$canonicalStudent->id => ['marks_obtained' => 88]],
            ])
            ->assertRedirect(route('teacher.exams.index'));

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $legacyExam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 70]],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('assignments', [
            'subject_id' => $canonicalSubject->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Canonical Official Assignment',
        ]);
        $this->assertDatabaseHas('study_materials', [
            'subject_id' => $canonicalSubject->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Canonical Official Material',
        ]);
        $this->assertDatabaseHas('subject_announcements', [
            'subject_id' => $canonicalSubject->id,
            'posted_by' => $fixture['teacher']->user_id,
            'title' => 'Canonical Official Announcement',
        ]);
        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $canonicalExam->id,
            'student_id' => $canonicalStudent->id,
            'marks_obtained' => 88,
        ]);
        $this->assertDatabaseMissing('assignments', ['title' => 'Blocked Non Official Assignment']);
        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $legacyExam->id,
            'student_id' => $fixture['enrolled']->id,
        ]);
    }

    public function test_teacher_cannot_save_results_for_same_subject_outside_timetable_program_scope(): void
    {
        $fixture = $this->fixture();

        $otherProgram = Program::factory()->create();
        $wrongExam = Exam::factory()->create([
            'program_id' => $otherProgram->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->subDay(),
            'total_marks' => 100,
            'passing_marks' => 40,
            'published_at' => null,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $wrongExam), [
                'results' => [
                    $fixture['enrolled']->id => [
                        'marks_obtained' => 82,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $wrongExam->id,
            'student_id' => $fixture['enrolled']->id,
        ]);
    }

    public function test_teacher_material_upload_accepts_database_backed_material_types(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.materials.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Valid Lecture Notes',
                'type' => 'notes',
                'description' => 'Database enum compatible material type.',
            ])
            ->assertRedirect(route('teacher.materials.index'));

        $this->assertDatabaseHas('study_materials', [
            'subject_id' => $fixture['assignedSubject']->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Valid Lecture Notes',
            'type' => 'notes',
        ]);
    }

    public function test_teacher_cannot_publish_empty_title_only_study_material(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.materials.create'))
            ->post(route('teacher.materials.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Empty Visible Resource',
                'type' => 'notes',
            ])
            ->assertRedirect(route('teacher.materials.create'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('study_materials', [
            'subject_id' => $fixture['assignedSubject']->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Empty Visible Resource',
        ]);
    }

    public function test_teacher_cannot_create_assignment_with_blank_title_or_past_due_date(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.assignments.create'))
            ->post(route('teacher.assignments.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => '   ',
                'description' => 'Blank title should not become student-visible.',
                'max_marks' => 20,
                'due_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertRedirect(route('teacher.assignments.create'))
            ->assertSessionHasErrors('title');

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.assignments.create'))
            ->post(route('teacher.assignments.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Already Expired Assignment',
                'description' => 'Past due assignments should not be published as new work.',
                'max_marks' => 20,
                'due_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertRedirect(route('teacher.assignments.create'))
            ->assertSessionHasErrors('due_at');

        $this->assertDatabaseMissing('assignments', [
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => '',
        ]);
        $this->assertDatabaseMissing('assignments', [
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Already Expired Assignment',
        ]);
    }

    public function test_teacher_cannot_publish_blank_subject_announcement(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.announcements.index'))
            ->post(route('teacher.announcements.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => '   ',
                'body' => " \n\t ",
            ])
            ->assertRedirect(route('teacher.announcements.index'))
            ->assertSessionHasErrors(['title', 'body']);

        $this->assertDatabaseMissing('subject_announcements', [
            'subject_id' => $fixture['assignedSubject']->id,
            'posted_by' => $fixture['teacher']->user_id,
        ]);
    }

    public function test_inactive_teacher_cannot_mutate_course_content_or_grades(): void
    {
        $fixture = $this->fixture();
        $fixture['teacher']->update(['status' => 'inactive']);

        $material = StudyMaterial::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Historical inactive material',
            'type' => 'notes',
            'is_published' => true,
        ]);
        $announcement = SubjectAnnouncement::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'posted_by' => $fixture['teacher']->user_id,
            'title' => 'Historical inactive announcement',
            'body' => 'Visible history.',
        ]);
        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Historical inactive assignment',
            'description' => 'Historical assignment visible to the inactive teacher.',
            'max_marks' => 20,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Submitted work',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.materials.index'))
            ->assertOk()
            ->assertSee('Historical inactive material')
            ->assertSee('Active teachers only')
            ->assertDontSee('Upload Material');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.index'))
            ->assertOk()
            ->assertSee('Historical inactive assignment')
            ->assertSee('Active teachers only')
            ->assertDontSee('Create Assignment');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.announcements.index'))
            ->assertOk()
            ->assertSee('Historical inactive announcement')
            ->assertSee('teacher profile is not active')
            ->assertDontSee('Post Announcement');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.submissions', $assignment))
            ->assertOk()
            ->assertSee('Grading is locked because this teacher profile is not active.');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.materials.create'))
            ->assertOk()
            ->assertSee('Only active teachers can upload study materials.')
            ->assertSee('disabled', false);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.create'))
            ->assertOk()
            ->assertSee('Only active teachers can create assignments.')
            ->assertSee('disabled', false);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.materials.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Inactive teacher material',
                'type' => 'notes',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Inactive teacher assignment',
                'max_marks' => 10,
                'due_at' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.announcements.store'), [
                'subject_id' => $fixture['assignedSubject']->id,
                'title' => 'Inactive teacher announcement',
                'body' => 'Should not be posted.',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->delete(route('teacher.announcements.destroy', $announcement))
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.grade', $submission), [
                'marks_obtained' => 18,
                'feedback' => 'Should not grade.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('study_materials', ['title' => 'Inactive teacher material']);
        $this->assertDatabaseMissing('assignments', ['title' => 'Inactive teacher assignment']);
        $this->assertDatabaseMissing('subject_announcements', ['title' => 'Inactive teacher announcement']);
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'status' => 'submitted',
            'marks_obtained' => null,
        ]);
        $this->assertDatabaseHas('study_materials', ['id' => $material->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('subject_announcements', ['id' => $announcement->id, 'deleted_at' => null]);
    }

    public function test_teacher_material_and_announcement_delete_archives_content_history(): void
    {
        $fixture = $this->fixture();

        $material = StudyMaterial::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Published Content History',
            'type' => 'notes',
            'file_path' => 'materials/published-history.pdf',
            'is_published' => true,
        ]);

        $announcement = SubjectAnnouncement::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'posted_by' => $fixture['teacher']->user_id,
            'title' => 'Archived Announcement History',
            'body' => 'This announcement should be archived, not destroyed.',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertRedirect()
            ->assertSessionHas('success', 'Material archived. Teaching history and file reference were preserved.');

        $this->actingAs($fixture['teacher']->user)
            ->delete(route('teacher.announcements.destroy', $announcement))
            ->assertRedirect()
            ->assertSessionHas('success', 'Announcement archived. Teaching history was preserved.');

        $this->assertNull(StudyMaterial::find($material->id));
        $archivedMaterial = StudyMaterial::withTrashed()->find($material->id);
        $this->assertNotNull($archivedMaterial);
        $this->assertNotNull($archivedMaterial->deleted_at);
        $this->assertSame('materials/published-history.pdf', $archivedMaterial->file_path);

        $this->assertNull(SubjectAnnouncement::find($announcement->id));
        $archivedAnnouncement = SubjectAnnouncement::withTrashed()->find($announcement->id);
        $this->assertNotNull($archivedAnnouncement);
        $this->assertNotNull($archivedAnnouncement->deleted_at);
        $this->assertSame('Archived Announcement History', $archivedAnnouncement->title);
    }

    public function test_teacher_material_and_announcement_empty_states_explain_next_actions(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.materials.index'))
            ->assertOk()
            ->assertSeeText('Material workflow:')
            ->assertSeeText('Published materials become visible to students in their course content area')
            ->assertSeeText('No study materials match this view yet')
            ->assertSeeText('Upload notes, slides, readings, or links for your assigned subject')
            ->assertSeeText('Upload material')
            ->assertDontSeeText('No materials found.')
            ->assertDontSeeText('Upload the first one.');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.materials.create'))
            ->assertOk()
            ->assertSeeText('Upload sequence:')
            ->assertSeeText('Select published teaching subject')
            ->assertDontSee('—', false);

        StudyMaterial::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'uploaded_by' => $fixture['teacher']->user_id,
            'title' => 'Legacy Material Without File',
            'type' => 'notes',
            'is_published' => false,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.materials.index'))
            ->assertOk()
            ->assertSeeText('Legacy Material Without File')
            ->assertSeeText('File or link not attached')
            ->assertDontSee('â', false);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.announcements.index'))
            ->assertOk()
            ->assertSeeText('Announcement workflow:')
            ->assertSeeText('Students see posted announcements in their course feed')
            ->assertSeeText('Select published teaching subject')
            ->assertSeeText('No subject announcements are posted yet')
            ->assertSeeText('Use the form above to publish the first notice for an assigned subject')
            ->assertDontSeeText('No announcements posted yet.');

        SubjectAnnouncement::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'posted_by' => $fixture['teacher']->user_id,
            'title' => 'Legacy Announcement Subject Fallback',
            'body' => 'Announcement fallback labels should remain readable.',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.announcements.index'))
            ->assertOk()
            ->assertSeeText('Legacy Announcement Subject Fallback')
            ->assertDontSee('â', false)
            ->assertDontSee('—', false);
    }

    public function test_teacher_cannot_grade_another_teachers_assignment_submission(): void
    {
        $fixture = $this->fixture();
        $otherTeacher = Teacher::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $otherTeacher->user_id,
            'title' => 'Other Teacher Assignment',
            'description' => 'Owned by another teacher.',
            'max_marks' => 10,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.grade', $submission), [
                'marks_obtained' => 9,
                'feedback' => 'Unauthorized grade.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignment_submissions', [
            'id' => $submission->id,
            'marks_obtained' => 9,
        ]);
    }

    public function test_teacher_assignment_empty_states_explain_next_actions(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.index'))
            ->assertOk()
            ->assertSeeText('Assignment workflow:')
            ->assertSeeText('create work only for subjects assigned to you in a published timetable')
            ->assertSeeText('No assignments are published or drafted for this view yet')
            ->assertSeeText('Create the first assignment for your assigned subject')
            ->assertSeeText('Create assignment')
            ->assertDontSeeText('No assignments yet.')
            ->assertDontSeeText('Create one.');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.create'))
            ->assertOk()
            ->assertSeeText('Create assignment sequence:')
            ->assertSeeText('Select published teaching subject')
            ->assertDontSee('—', false);

        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'No Submission Guidance Assignment',
            'description' => 'The submission list should explain the next teacher action.',
            'max_marks' => 10,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.submissions', $assignment))
            ->assertOk()
            ->assertSeeText('Submission workflow:')
            ->assertSeeText('this page uses the active roster for the assignment subject')
            ->assertSeeText('No students have submitted this assignment yet')
            ->assertSeeText('Use the Not Yet Submitted list below to follow up with students')
            ->assertDontSeeText('No submissions yet.');
    }

    public function test_teacher_assignment_submissions_roster_uses_canonical_enrollments(): void
    {
        $fixture = $this->fixture();
        $canonicalOnly = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['entry']->course_id,
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $canonicalOnly->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'status' => 'active',
        ]);

        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Canonical Roster Assignment',
            'description' => 'Canonical students should appear.',
            'max_marks' => 10,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Legacy enrolled submitted.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.submissions', $assignment))
            ->assertOk()
            ->assertSee($canonicalOnly->user->name)
            ->assertDontSee($fixture['outsider']->user->name);
    }

    public function test_teacher_assignment_index_submission_count_uses_roster_and_subject_filter(): void
    {
        $fixture = $this->fixture();
        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Roster Count Assignment',
            'description' => 'Index count should match submission source list.',
            'max_marks' => 10,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);
        $filteredOutAssignment = Assignment::create([
            'subject_id' => $fixture['otherSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Filtered Out Assignment',
            'description' => 'Teacher created but is not currently teaching this subject.',
            'max_marks' => 10,
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Valid enrolled submission.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['outsider']->id,
            'answer_text' => 'Rogue submission outside roster.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $filteredOutAssignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Filtered subject submission.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.index', ['subject_id' => $fixture['assignedSubject']->id]))
            ->assertOk()
            ->assertSee('Roster Count Assignment')
            ->assertSee('1 submitted')
            ->assertDontSee('2 submitted')
            ->assertDontSee('Filtered Out Assignment');
    }

    public function test_teacher_cannot_grade_assignment_submission_for_student_outside_roster_or_above_max_marks(): void
    {
        $fixture = $this->fixture();
        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Roster Grade Assignment',
            'description' => 'Only roster submissions can be graded.',
            'max_marks' => 10,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);
        $rogueSubmission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['outsider']->id,
            'answer_text' => 'Rogue submission.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        $validSubmission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Valid submission.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.grade', $rogueSubmission), [
                'marks_obtained' => 8,
                'feedback' => 'Should be blocked.',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.submissions', $assignment))
            ->assertOk()
            ->assertSee($fixture['enrolled']->user->name)
            ->assertDontSee('Rogue submission.')
            ->assertDontSee($fixture['outsider']->user->name);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.assignments.grade', $validSubmission), [
                'marks_obtained' => 11,
                'feedback' => 'Too high.',
            ])
            ->assertSessionHasErrors('marks_obtained');

        $this->assertDatabaseMissing('assignment_submissions', [
            'id' => $rogueSubmission->id,
            'marks_obtained' => 8,
        ]);
        $this->assertDatabaseMissing('assignment_submissions', [
            'id' => $validSubmission->id,
            'marks_obtained' => 11,
        ]);
    }

    public function test_teacher_cannot_regrade_already_graded_assignment_submission(): void
    {
        $fixture = $this->fixture();
        $assignment = Assignment::create([
            'subject_id' => $fixture['assignedSubject']->id,
            'created_by' => $fixture['teacher']->user_id,
            'title' => 'Final Graded Assignment',
            'description' => 'Already graded submissions are locked.',
            'max_marks' => 10,
            'due_at' => now()->addWeek(),
            'is_published' => true,
        ]);
        $gradedAt = now()->subDay();
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $fixture['enrolled']->id,
            'answer_text' => 'Original answer.',
            'submitted_at' => now()->subDays(2),
            'marks_obtained' => 8,
            'feedback' => 'Original feedback.',
            'graded_by' => $fixture['teacher']->user_id,
            'graded_at' => $gradedAt,
            'status' => 'graded',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.assignments.submissions', $assignment))
            ->post(route('teacher.assignments.grade', $submission), [
                'marks_obtained' => 4,
                'feedback' => 'Silent downgrade.',
            ])
            ->assertRedirect(route('teacher.assignments.submissions', $assignment))
            ->assertSessionHas('error', 'This submission is already graded and cannot be changed through the standard grading route.');

        $submission->refresh();
        $this->assertSame('graded', $submission->status);
        $this->assertEquals(8.0, (float) $submission->marks_obtained);
        $this->assertSame('Original feedback.', $submission->feedback);
        $this->assertSame($fixture['teacher']->user_id, $submission->graded_by);
        $this->assertSame($gradedAt->toDateTimeString(), $submission->graded_at->toDateTimeString());
    }

    public function test_teacher_cannot_mark_attendance_or_results_for_students_outside_class_roster(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [
                    $fixture['enrolled']->id => 'present',
                    $fixture['outsider']->id => 'present',
                ],
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [
                    $fixture['enrolled']->id => ['marks_obtained' => 80],
                    $fixture['outsider']->id => ['marks_obtained' => 75],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', ['student_id' => $fixture['outsider']->id]);
        $this->assertDatabaseMissing('exam_results', ['student_id' => $fixture['outsider']->id]);
    }

    public function test_inactive_teacher_cannot_mark_attendance_or_save_exam_results(): void
    {
        $fixture = $this->fixture();
        $fixture['teacher']->update(['status' => 'inactive']);
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.attendance.mark', [
                'date' => now()->toDateString(),
                'entry_id' => $fixture['entry']->id,
            ]))
            ->assertOk()
            ->assertSee('Attendance marking is locked because this teacher profile is not active.');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.exams.results', $exam))
            ->assertOk()
            ->assertSee('Result entry is locked because this teacher profile is not active.');

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 80]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);
        $this->assertDatabaseMissing('exam_results', [
            'student_id' => $fixture['enrolled']->id,
            'exam_id' => $exam->id,
        ]);
    }

    public function test_inactive_teacher_can_view_mentee_history_but_cannot_send_messages_or_schedule_meetings(): void
    {
        $fixture = $this->fixture();
        $fixture['teacher']->update(['status' => 'inactive']);
        $fixture['enrolled']->update(['mentor_id' => $fixture['teacher']->user_id]);

        MentorMessage::create([
            'student_id' => $fixture['enrolled']->id,
            'sender_id' => $fixture['enrolled']->user_id,
            'message' => 'Historical mentor message',
        ]);
        MentorMeeting::create([
            'student_id' => $fixture['enrolled']->id,
            'mentor_id' => $fixture['teacher']->user_id,
            'meeting_date' => now()->addWeek()->toDateString(),
            'topic' => 'Historical mentor meeting',
            'status' => 'scheduled',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.index'))
            ->assertOk()
            ->assertSeeText('Use this desk to review assigned mentees')
            ->assertSeeText('1. Review risk')
            ->assertSeeText('4. Escalate blockers')
            ->assertSee('Messaging and meeting scheduling are locked because this teacher profile is not active.')
            ->assertSee('Historical mentor meeting');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.mentee', $fixture['enrolled']))
            ->assertOk()
            ->assertSeeText('1. Check recent signals')
            ->assertSeeText('4. Escalate when needed')
            ->assertSee('Historical mentor message')
            ->assertSee('Historical mentor meeting')
            ->assertSee('Mentoring message replies are locked for inactive profiles.')
            ->assertSee('Meeting scheduling is locked for inactive profiles.')
            ->assertDontSee('Post Announcement');

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.mentor.message', $fixture['enrolled']), [
                'message' => 'Inactive mentor direct message',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.mentor.meeting', $fixture['enrolled']), [
                'meeting_date' => now()->addWeek()->toDateString(),
                'topic' => 'Inactive mentor meeting',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('mentor_messages', [
            'student_id' => $fixture['enrolled']->id,
            'message' => 'Inactive mentor direct message',
        ]);
        $this->assertDatabaseMissing('mentor_meetings', [
            'student_id' => $fixture['enrolled']->id,
            'topic' => 'Inactive mentor meeting',
        ]);
    }

    public function test_mentor_detail_empty_states_explain_next_teacher_action(): void
    {
        $fixture = $this->fixture();
        $fixture['enrolled']->update(['mentor_id' => $fixture['teacher']->user_id]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.mentee', $fixture['enrolled']))
            ->assertOk()
            ->assertSeeText('Use attendance and published results to decide the follow-up priority')
            ->assertSeeText('Keep notes specific enough for Program Chair, PMC, and Dean review')
            ->assertSeeText('No mentor messages are recorded yet')
            ->assertSeeText('Start with a short check-in message')
            ->assertSeeText('No mentor meetings are scheduled or recorded yet')
            ->assertSeeText('Use the schedule form to create the first progress review')
            ->assertSeeText('No published attendance is available yet')
            ->assertSeeText("student's published timetable")
            ->assertSeeText('No published exam results are available yet')
            ->assertSeeText('Draft or unpublished results stay hidden until CoE publication')
            ->assertDontSeeText('No messages yet. Start the conversation below.')
            ->assertDontSeeText('No meetings recorded yet.')
            ->assertDontSeeText('No attendance data.')
            ->assertDontSeeText('No exam results yet.')
            ->assertDontSeeText('N/A');
    }

    public function test_teacher_can_mark_attendance_and_results_for_enrolled_roster_only(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertRedirect(route('teacher.attendance.mark'));

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 80]],
            ])
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseHas('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('exam_results', [
            'student_id' => $fixture['enrolled']->id,
            'exam_id' => $exam->id,
            'marks_obtained' => 80,
        ]);
    }

    public function test_teacher_can_save_results_for_canonical_term_subject_roster_only(): void
    {
        $fixture = $this->fixture();
        $term = Term::factory()->create();
        $otherTerm = Term::factory()->create();
        $fixture['entry']->update(['term_id' => $term->id]);
        $canonicalStudent = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['enrolled']->course_id,
            'roll_number' => 'TERM-ROSTER-001',
        ]);
        $wrongTermStudent = Student::factory()->create([
            'program_id' => $fixture['program']->id,
            'course_id' => $fixture['enrolled']->course_id,
            'roll_number' => 'TERM-ROSTER-OUT',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $canonicalStudent->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $wrongTermStudent->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'term_id' => $otherTerm->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->subDay()->toDateString(),
            'total_marks' => 100,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.exams.results', $exam))
            ->assertOk()
            ->assertSee('TERM-ROSTER-001')
            ->assertDontSee('TERM-ROSTER-OUT');

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$canonicalStudent->id => ['marks_obtained' => 84]],
            ])
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseHas('exam_results', [
            'student_id' => $canonicalStudent->id,
            'exam_id' => $exam->id,
            'marks_obtained' => 84,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$wrongTermStudent->id => ['marks_obtained' => 75]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_results', [
            'student_id' => $wrongTermStudent->id,
            'exam_id' => $exam->id,
        ]);
    }

    public function test_teacher_cannot_mark_future_attendance(): void
    {
        $fixture = $this->fixture();
        $futureDate = now()->addDay()->toDateString();

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.attendance.mark'))
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => $futureDate,
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertRedirect(route('teacher.attendance.mark'))
            ->assertSessionHasErrors('date');

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'date' => $futureDate,
        ]);
    }

    public function test_teacher_attendance_requires_active_entry_and_matching_schedule_day(): void
    {
        $fixture = $this->fixture();
        $mismatchedDate = now()->subDay();

        if ((int) $mismatchedDate->dayOfWeekIso === (int) $fixture['entry']->day_of_week) {
            $mismatchedDate = now()->subDays(2);
        }

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.attendance.mark'))
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => $mismatchedDate->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertRedirect(route('teacher.attendance.mark'))
            ->assertSessionHasErrors('date');

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'date' => $mismatchedDate->toDateString(),
        ]);

        $fixture['entry']->update(['is_active' => false]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
            'date' => now()->toDateString(),
        ]);
    }

    public function test_teacher_attendance_requires_published_timetable_entry_and_version(): void
    {
        $fixture = $this->fixture();
        $fixture['entry']->update(['status' => 'draft']);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);

        $term = Term::factory()->create(['program_id' => $fixture['program']->id]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => $fixture['teacher']->user_id,
        ]);
        $fixture['entry']->update([
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.attendance.store'), [
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->toDateString(),
                'attendance' => [$fixture['enrolled']->id => 'present'],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendances', [
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $fixture['entry']->id,
        ]);
    }

    public function test_mentor_detail_shows_only_published_exam_results(): void
    {
        $fixture = $this->fixture();
        $fixture['enrolled']->update(['mentor_id' => $fixture['teacher']->user_id]);
        $publishedExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'published_at' => now(),
            'name' => 'Published Mentor Exam',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'published_at' => null,
            'name' => 'Draft Mentor Exam',
        ]);
        ExamResult::factory()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => 88,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => 18,
        ]);

        $response = $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.mentee', $fixture['enrolled']))
            ->assertOk()
            ->assertSee('Published Mentor Exam')
            ->assertDontSee('Draft Mentor Exam');

        $this->assertCount(1, $response->viewData('results'));
        $this->assertSame('Published Mentor Exam', $response->viewData('results')->first()->exam->name);
    }

    public function test_mentor_attendance_summaries_ignore_draft_timetable_history(): void
    {
        $fixture = $this->fixture();
        $fixture['enrolled']->update(['mentor_id' => $fixture['teacher']->user_id]);

        foreach (range(1, 2) as $day) {
            Attendance::create([
                'student_id' => $fixture['enrolled']->id,
                'timetable_entry_id' => $fixture['entry']->id,
                'date' => now()->subDays($day)->toDateString(),
                'status' => 'present',
                'marked_by' => $fixture['teacher']->user_id,
            ]);
        }

        $draftEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $fixture['entry']->course_id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'draft',
        ]);
        Attendance::create([
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $draftEntry->id,
            'date' => now()->subDays(10)->toDateString(),
            'status' => 'absent',
            'marked_by' => $fixture['teacher']->user_id,
        ]);

        $term = Term::factory()->create(['program_id' => $fixture['program']->id]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $fixture['program']->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $fixture['teacher']->user_id,
        ]);
        $draftVersionEntry = TimetableEntry::factory()->create([
            'semester_id' => $fixture['semester']->id,
            'course_id' => $fixture['entry']->course_id,
            'program_id' => $fixture['program']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'teacher_id' => $fixture['teacher']->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);
        Attendance::create([
            'student_id' => $fixture['enrolled']->id,
            'timetable_entry_id' => $draftVersionEntry->id,
            'date' => now()->subDays(11)->toDateString(),
            'status' => 'absent',
            'marked_by' => $fixture['teacher']->user_id,
        ]);

        $indexResponse = $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.index'))
            ->assertOk()
            ->assertSee('100%');

        $this->assertSame(100.0, (float) $indexResponse->viewData('mentees')->first()->att_pct);

        $detailResponse = $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.mentee', $fixture['enrolled']))
            ->assertOk();

        $subjectAttendance = $detailResponse->viewData('attBySubject')->first();
        $this->assertSame(2, $subjectAttendance->total);
        $this->assertSame(2, $subjectAttendance->present);
    }

    public function test_teacher_cannot_enter_results_before_exam_date(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'exam_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.exams.results', $exam))
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 80]],
            ])
            ->assertRedirect(route('teacher.exams.results', $exam))
            ->assertSessionHas('error', 'Exam results cannot be entered before the exam date.');

        $this->assertDatabaseMissing('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $fixture['enrolled']->id,
        ]);
    }

    public function test_teacher_result_entry_validates_marks_and_absent_state(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'total_marks' => 50,
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 51]],
            ])
            ->assertSessionHasErrors("results.{$fixture['enrolled']->id}.marks_obtained");

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['remarks' => 'Present but no marks']],
            ])
            ->assertSessionHasErrors("results.{$fixture['enrolled']->id}.marks_obtained");

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => 42,
            'is_absent' => false,
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [
                    $fixture['enrolled']->id => [
                        'is_absent' => '1',
                        'marks_obtained' => 49,
                        'remarks' => 'Medical absence',
                    ],
                ],
            ])
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => null,
            'is_absent' => true,
            'remarks' => 'Medical absence',
        ]);
    }

    public function test_teacher_cannot_change_results_after_exam_publication(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'published_at' => now(),
            'published_by' => $fixture['teacher']->user_id,
        ]);

        ExamResult::create([
            'exam_id' => $exam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => 42,
            'is_absent' => false,
            'remarks' => 'Published mark.',
        ]);

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.exams.results', $exam))
            ->assertOk()
            ->assertSee('Results published');

        $this->actingAs($fixture['teacher']->user)
            ->from(route('teacher.exams.results', $exam))
            ->post(route('teacher.exams.results.save', $exam), [
                'results' => [$fixture['enrolled']->id => ['marks_obtained' => 49, 'remarks' => 'Changed after publish']],
            ])
            ->assertRedirect(route('teacher.exams.results', $exam))
            ->assertSessionHas('error', 'Published results are locked. Contact Exam Cell for appeal or correction workflow.');

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $exam->id,
            'student_id' => $fixture['enrolled']->id,
            'marks_obtained' => 42,
            'remarks' => 'Published mark.',
        ]);
    }
}
