<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
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
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
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
            'day_of_week' => 1,
            'is_active' => true,
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
            ->assertRedirect(route('teacher.materials.index'))
            ->assertSessionHas('error', 'Only active teachers can upload study materials.');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.assignments.create'))
            ->assertRedirect(route('teacher.assignments.index'))
            ->assertSessionHas('error', 'Only active teachers can create assignments.');

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

    public function test_teacher_cannot_mark_attendance_or_results_for_students_outside_class_roster(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
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
            ->assertSee('Messaging and meeting scheduling are locked because this teacher profile is not active.')
            ->assertSee('Historical mentor meeting');

        $this->actingAs($fixture['teacher']->user)
            ->get(route('teacher.mentor.mentee', $fixture['enrolled']))
            ->assertOk()
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

    public function test_teacher_can_mark_attendance_and_results_for_enrolled_roster_only(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
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

    public function test_teacher_result_entry_validates_marks_and_absent_state(): void
    {
        $fixture = $this->fixture();
        $exam = Exam::factory()->create([
            'program_id' => $fixture['program']->id,
            'semester_id' => $fixture['semester']->id,
            'subject_id' => $fixture['assignedSubject']->id,
            'total_marks' => 50,
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
