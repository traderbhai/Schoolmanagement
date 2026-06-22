<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcCourseGroupMember;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use App\Models\SubjectDiscussion;
use App\Models\SubjectDiscussionReply;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCourseContentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $student = Student::factory()->create();
        $student->user->assignRole('student');

        return $student;
    }

    private function teacher(): User
    {
        return User::factory()->create();
    }

    private function enroll(Student $student, Subject $subject, string $status = 'active'): void
    {
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => $status,
        ]);
    }

    public function test_student_course_surfaces_require_active_subject_enrollment(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $teacher = $this->teacher();

        StudyMaterial::create([
            'subject_id' => $subject->id,
            'uploaded_by' => $teacher->id,
            'title' => 'Restricted Notes',
            'type' => 'notes',
            'is_published' => true,
        ]);
        SubjectAnnouncement::create([
            'subject_id' => $subject->id,
            'posted_by' => $teacher->id,
            'title' => 'Restricted Announcement',
            'body' => 'Only enrolled students should read this.',
        ]);

        $this->actingAs($student->user)->get(route('student.courses.show', $subject))->assertForbidden();
        $this->actingAs($student->user)->get(route('student.materials.index', $subject))->assertForbidden();
        $this->actingAs($student->user)->get(route('student.announcements.index', $subject))->assertForbidden();

        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->get(route('student.courses.show', $subject))
            ->assertStatus(200)
            ->assertSee('Restricted Notes')
            ->assertSee('Restricted Announcement');
        $this->actingAs($student->user)->get(route('student.materials.index', $subject))->assertStatus(200);
        $this->actingAs($student->user)->get(route('student.announcements.index', $subject))->assertStatus(200);
    }

    public function test_student_materials_and_announcements_support_compatible_legacy_enrollment(): void
    {
        $student = $this->student();
        $outsider = $this->student();
        $subject = Subject::factory()->create();
        $semester = Semester::factory()->create();
        $teacher = $this->teacher();

        StudyMaterial::create([
            'subject_id' => $subject->id,
            'uploaded_by' => $teacher->id,
            'title' => 'Legacy Visible Notes',
            'type' => 'notes',
            'is_published' => true,
        ]);
        StudyMaterial::create([
            'subject_id' => $subject->id,
            'uploaded_by' => $teacher->id,
            'title' => 'Draft Notes',
            'type' => 'notes',
            'is_published' => false,
        ]);
        SubjectAnnouncement::create([
            'subject_id' => $subject->id,
            'posted_by' => $teacher->id,
            'title' => 'Legacy Visible Announcement',
            'body' => 'This should be visible.',
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'status' => 'enrolled',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.materials.index', $subject))
            ->assertOk()
            ->assertSee('Legacy Visible Notes')
            ->assertDontSee('Draft Notes');

        $this->actingAs($student->user)
            ->get(route('student.announcements.index', $subject))
            ->assertOk()
            ->assertSee('Legacy Visible Announcement');

        $this->actingAs($outsider->user)->get(route('student.materials.index', $subject))->assertForbidden();
        $this->actingAs($outsider->user)->get(route('student.announcements.index', $subject))->assertForbidden();
    }

    public function test_student_course_index_lists_only_enrolled_subjects_with_real_faculty_names(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $enrolledSubject = Subject::factory()->create(['name' => 'Applied Analytics']);
        $unenrolledSubject = Subject::factory()->create(['name' => 'Private Finance']);
        $teacher = Teacher::factory()->create();
        $teacher->user->update(['name' => 'Dr Course Faculty']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $enrolledSubject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        TimetableEntry::factory()->create([
            'teacher_id' => $teacher->id,
            'course_id' => Course::factory()->create()->id,
            'batch_id' => Batch::factory()->create()->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $enrolledSubject->id,
            'is_active' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Applied Analytics')
            ->assertSee('Dr Course Faculty')
            ->assertDontSee('Private Finance');

        $this->actingAs($student->user)
            ->get(route('student.courses.show', $unenrolledSubject))
            ->assertForbidden();
    }

    public function test_student_course_index_hides_faculty_from_draft_timetable_entries(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['name' => 'Published Faculty Course']);
        $publishedTeacher = Teacher::factory()->create();
        $publishedTeacher->user->update(['name' => 'Published Course Faculty']);
        $draftTeacher = Teacher::factory()->create();
        $draftTeacher->user->update(['name' => 'Draft Staffing Faculty']);
        $publishedVersion = TimetableVersion::create([
            'program_id' => $subject->program_id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $publishedTeacher->user_id,
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $subject->program_id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $draftTeacher->user_id,
        ]);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        TimetableEntry::factory()->create([
            'teacher_id' => $publishedTeacher->id,
            'course_id' => Course::factory()->create()->id,
            'batch_id' => Batch::factory()->create()->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 1])->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $publishedVersion->id,
        ]);

        TimetableEntry::factory()->create([
            'teacher_id' => $draftTeacher->id,
            'course_id' => Course::factory()->create()->id,
            'batch_id' => Batch::factory()->create()->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 2])->id,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Published Faculty Course')
            ->assertSee('Published Course Faculty')
            ->assertDontSee('Draft Staffing Faculty');
    }

    public function test_student_course_index_uses_canonical_pmc_group_faculty_over_legacy_rows(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $student = $this->student();
        $student->update(['program_id' => $program->id, 'batch_id' => $batch->id]);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Canonical Course Hub']);
        $slot = TimetableSlot::factory()->create(['start_time' => '08:00:00', 'end_time' => '09:00:00', 'sort_order' => 1]);
        $room = Classroom::factory()->create();
        $canonicalTeacher = Teacher::factory()->create();
        $canonicalTeacher->user->update(['name' => 'Canonical Hub Faculty']);
        $otherSectionTeacher = Teacher::factory()->create();
        $otherSectionTeacher->user->update(['name' => 'Other Section Hub Faculty']);
        $legacyTeacher = Teacher::factory()->create();
        $legacyTeacher->user->update(['name' => 'Legacy Hub Faculty']);
        $draftTeacher = Teacher::factory()->create();
        $draftTeacher->user->update(['name' => 'Draft Hub Faculty']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        $studentGroup = AcademicPmcCourseGroup::create([
            'name' => 'Student Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $otherGroup = AcademicPmcCourseGroup::create([
            'name' => 'Other Section B',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcCourseGroupMember::create([
            'course_group_id' => $studentGroup->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $publishedVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $canonicalTeacher->user_id,
            'published_by' => $canonicalTeacher->user_id,
            'published_at' => now(),
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'version_number' => 2,
            'status' => 'draft',
            'created_by' => $draftTeacher->user_id,
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Course Hub Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $publishedVersion->id,
            'created_by' => $canonicalTeacher->user_id,
            'status' => 'published',
            'scheduled_count' => 2,
            'quality_score' => 100,
        ]);

        foreach (
            [
                [$studentGroup, $canonicalTeacher, $publishedVersion, 1, 'locked'],
                [$otherGroup, $otherSectionTeacher, $publishedVersion, 2, 'published'],
                [$studentGroup, $draftTeacher, $draftVersion, 3, 'scheduled'],
            ] as [$group, $teacher, $version, $index, $status]
        ) {
            AcademicPmcTimetableGenerationItem::create([
                'generation_run_id' => $run->id,
                'timetable_version_id' => $version->id,
                'course_group_id' => $group->id,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'session_index' => $index,
                'session_type' => 'lecture',
                'duration_slots' => 1,
                'teacher_id' => $teacher->id,
                'classroom_id' => $room->id,
                'day_of_week' => 1,
                'timetable_slot_id' => $slot->id,
                'status' => $status,
                'official_status' => 'published',
                'source_type' => 'generated',
                'published_at' => $version->status === 'published' ? now() : null,
                'published_by' => $version->status === 'published' ? $teacher->user_id : null,
            ]);
        }

        TimetableEntry::factory()->create([
            'teacher_id' => $legacyTeacher->id,
            'course_id' => Course::factory()->create()->id,
            'batch_id' => $batch->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'timetable_slot_id' => $slot->id,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Canonical Course Hub')
            ->assertSee('Canonical Hub Faculty')
            ->assertDontSee('Other Section Hub Faculty')
            ->assertDontSee('Legacy Hub Faculty')
            ->assertDontSee('Draft Hub Faculty');
    }

    public function test_student_course_hub_supports_legacy_active_enrollments(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 2, 'name' => 'Term 2']);
        $semester = Semester::factory()->create(['number' => 2, 'name' => 'Term 2']);
        $subject = Subject::factory()->create(['name' => 'Legacy Operations']);

        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Legacy Operations');

        $this->actingAs($student->user)
            ->get(route('student.courses.show', $subject))
            ->assertOk()
            ->assertSee('Legacy Operations');
    }

    public function test_student_course_hub_empty_states_explain_published_content_next_steps(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['name' => 'Empty Course Hub']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.courses.show', $subject))
            ->assertOk()
            ->assertSeeText('No course announcements are posted yet')
            ->assertSeeText('after they publish them for this course')
            ->assertSeeText('No study materials are available yet')
            ->assertSeeText('after your faculty uploads them')
            ->assertSeeText('No assignments are open for this course yet')
            ->assertSeeText('it will appear here with its due date and submission status')
            ->assertDontSeeText('No announcements yet.')
            ->assertDontSeeText('No materials uploaded yet.')
            ->assertDontSeeText('No assignments yet.');
    }

    public function test_student_assignment_list_empty_state_explains_published_assignment_visibility(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['name' => 'Assignment Empty State Subject']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertSeeText('No assignments are published for this view yet')
            ->assertSeeText('Assignments appear here only after faculty publish them for your enrolled subjects')
            ->assertSeeText('clear dashboard filters if you opened a filtered assignment queue')
            ->assertDontSeeText('No assignments found.');
    }

    public function test_student_course_material_and_announcement_empty_pages_explain_published_content_timing(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['name' => 'Empty Course Content Pages']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.announcements.index', $subject))
            ->assertOk()
            ->assertSeeText('No course announcements are published yet')
            ->assertSeeText('Faculty or the program office will post notices here')
            ->assertDontSeeText('No announcements for this course yet.');

        $this->actingAs($student->user)
            ->get(route('student.materials.index', $subject))
            ->assertOk()
            ->assertSeeText('No published study materials are available yet')
            ->assertSeeText('after your faculty publishes them for this enrolled course')
            ->assertDontSeeText('No study materials uploaded yet for this course.');
    }

    public function test_student_discussion_empty_state_explains_when_to_start_thread(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $subject = Subject::factory()->create(['name' => 'Empty Discussion Subject']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.discussions.index', $subject))
            ->assertOk()
            ->assertSeeText('No discussion threads are open for this subject yet')
            ->assertSeeText('Ask a question when you need clarification on class topics')
            ->assertSeeText('Ask a Question')
            ->assertDontSeeText('No discussions yet. Be the first to ask a question!');
    }

    public function test_student_discussions_require_active_enrollment_and_support_legacy_enrollment(): void
    {
        $student = $this->student();
        $term = Term::factory()->create(['term_number' => 1, 'name' => 'Term 1']);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $canonicalSubject = Subject::factory()->create(['name' => 'Discussion Strategy']);
        $legacySubject = Subject::factory()->create(['name' => 'Legacy Discussion']);
        $unenrolledSubject = Subject::factory()->create(['name' => 'Private Discussion']);

        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $canonicalSubject->id,
            'term_id' => $term->id,
            'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'term_id' => $term->id,
            'subject_id' => $legacySubject->id,
            'status' => 'active',
        ]);

        $privateDiscussion = SubjectDiscussion::create([
            'subject_id' => $unenrolledSubject->id,
            'posted_by' => $student->user_id,
            'title' => 'Private Thread',
            'body' => 'Should remain inaccessible.',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.discussions.index', $unenrolledSubject))
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post(route('student.discussions.store', $unenrolledSubject), [
                'title' => 'Unauthorized Question',
                'body' => 'This should not be posted.',
            ])
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post(route('student.discussions.resolve', [$unenrolledSubject, $privateDiscussion]))
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post(route('student.discussions.store', $canonicalSubject), [
                'title' => 'How should I prepare?',
                'body' => 'Looking for guidance on the next case.',
            ])
            ->assertRedirect();

        $discussion = SubjectDiscussion::where('subject_id', $canonicalSubject->id)->first();
        $this->assertNotNull($discussion);
        $this->assertSame($term->id, $discussion->term_id);

        $this->actingAs($student->user)
            ->get(route('student.discussions.index', $legacySubject))
            ->assertOk()
            ->assertSee('Legacy Discussion');
    }

    public function test_student_can_view_reply_and_resolve_own_enrolled_discussion(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create(['name' => 'Resolved Discussion Subject']);
        $this->enroll($student, $subject);
        $discussion = SubjectDiscussion::create([
            'subject_id' => $subject->id,
            'posted_by' => $student->user_id,
            'title' => 'Need clarification',
            'body' => 'What should I revise?',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.discussions.show', [$subject, $discussion]))
            ->assertOk()
            ->assertSee('Need clarification');

        $this->actingAs($student->user)
            ->post(route('student.discussions.reply', [$subject, $discussion]), [
                'body' => 'Adding more context.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subject_discussion_replies', [
            'discussion_id' => $discussion->id,
            'posted_by' => $student->user_id,
            'body' => 'Adding more context.',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.discussions.resolve', [$subject, $discussion]))
            ->assertRedirect();

        $this->assertTrue($discussion->fresh()->is_resolved);
    }

    public function test_inactive_student_can_view_discussions_but_cannot_participate(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $subject = Subject::factory()->create(['name' => 'Archived Discussion Subject']);
        $this->enroll($student, $subject);
        $discussion = SubjectDiscussion::create([
            'subject_id' => $subject->id,
            'posted_by' => $student->user_id,
            'title' => 'Historical question',
            'body' => 'This thread remains visible.',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.discussions.index', $subject))
            ->assertOk()
            ->assertSee('Historical question')
            ->assertSee('Discussion posting is locked')
            ->assertSee('Active students only')
            ->assertDontSee('Ask a Question');

        $this->actingAs($student->user)
            ->get(route('student.discussions.show', [$subject, $discussion]))
            ->assertOk()
            ->assertSee('Historical question')
            ->assertSee('Discussion replies and status updates are locked')
            ->assertDontSee('Mark Resolved')
            ->assertDontSee('Post Reply');

        $this->actingAs($student->user)
            ->post(route('student.discussions.store', $subject), [
                'title' => 'Inactive question',
                'body' => 'Inactive profile should not post.',
            ])
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post(route('student.discussions.reply', [$subject, $discussion]), [
                'body' => 'Inactive reply should not be accepted.',
            ])
            ->assertForbidden();

        $this->actingAs($student->user)
            ->post(route('student.discussions.resolve', [$subject, $discussion]))
            ->assertForbidden();

        $this->assertSame(1, SubjectDiscussion::where('posted_by', $student->user_id)->count());
        $this->assertSame(0, SubjectDiscussionReply::where('discussion_id', $discussion->id)->count());
        $this->assertFalse($discussion->fresh()->is_resolved);
    }

    public function test_student_cannot_view_or_submit_assignment_outside_enrolled_subject(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Private Assignment',
            'description' => 'Not for non-enrolled students.',
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        $this->actingAs($student->user)->get(route('student.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($student->user)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'I should not submit this.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->get(route('student.assignments.show', $assignment))
            ->assertStatus(200)
            ->assertSee('Private Assignment');
    }

    public function test_student_assignment_access_supports_compatible_legacy_enrollment(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $semester = Semester::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Legacy Enrollment Assignment',
            'description' => 'Visible through legacy enrollment.',
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertSee('Legacy Enrollment Assignment');

        $this->actingAs($student->user)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'Submitted from legacy enrollment.'])
            ->assertRedirect(route('student.assignments.show', $assignment));

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer_text' => 'Submitted from legacy enrollment.',
        ]);
    }

    public function test_student_cannot_submit_empty_assignment_response(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Evidence Required Assignment',
            'description' => 'Submission must include answer text or a file.',
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);
        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => '   '])
            ->assertSessionHasErrors('answer_text');

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_inactive_student_can_view_assignment_but_cannot_submit_coursework(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $subject = Subject::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Archived Student Assignment',
            'description' => 'Visible but not submittable by inactive students.',
            'due_at' => now()->addDays(3),
            'is_published' => true,
        ]);
        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->get(route('student.assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Archived Student Assignment');

        $this->actingAs($student->user)
            ->post(route('student.assignments.submit', $assignment), [
                'answer_text' => 'Inactive student should not submit.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_cannot_resubmit_assignment_after_it_is_graded(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $assignment = Assignment::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Graded Assignment',
            'description' => 'Graded submissions are locked.',
            'due_at' => now()->addDays(3),
            'max_marks' => 100,
            'is_published' => true,
        ]);
        $this->enroll($student, $subject);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer_text' => 'Original final answer.',
            'marks_obtained' => 82,
            'feedback' => 'Good structure.',
            'graded_by' => $this->teacher()->id,
            'graded_at' => now(),
            'status' => 'graded',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.assignments.submit', $assignment), ['answer_text' => 'Overwrite after grading.'])
            ->assertRedirect()
            ->assertSessionHas('error', 'This assignment has already been graded and cannot be resubmitted.');

        $submission->refresh();
        $this->assertSame('graded', $submission->status);
        $this->assertSame('Original final answer.', $submission->answer_text);
        $this->assertEquals(82, (float) $submission->marks_obtained);
        $this->assertSame('Good structure.', $submission->feedback);
    }

    public function test_student_cannot_view_start_or_result_quiz_outside_enrolled_subject(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Private Quiz',
            'description' => 'Not for non-enrolled students.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
        ]);

        $this->actingAs($student->user)->get(route('student.quizzes.show', $quiz))->assertForbidden();
        $this->actingAs($student->user)->post(route('student.quizzes.start', $quiz))->assertForbidden();
        $this->actingAs($student->user)->get(route('student.quizzes.result', $quiz))->assertForbidden();

        $this->assertDatabaseMissing('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
        ]);

        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->get(route('student.quizzes.show', $quiz))
            ->assertStatus(200)
            ->assertSee('Private Quiz');
    }

    public function test_student_quiz_access_supports_compatible_legacy_enrollment(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $semester = Semester::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Legacy Enrollment Quiz',
            'description' => 'Visible through legacy enrollment.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'show_result_immediately' => true,
            'total_marks' => 1,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Pick the correct answer.',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 1,
        ]);
        $correct = QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Correct',
            'is_correct' => true,
            'order' => 1,
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'status' => 'active',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.quizzes.index'))
            ->assertOk()
            ->assertSee('Legacy Enrollment Quiz');

        $this->actingAs($student->user)
            ->post(route('student.quizzes.start', $quiz))
            ->assertOk()
            ->assertSee('Pick the correct answer.');

        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), [
                'answers' => [$question->id => $correct->id],
            ])
            ->assertRedirect(route('student.quizzes.result', $quiz));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'is_completed' => true,
            'score' => 1,
        ]);
    }

    public function test_inactive_student_can_view_quiz_but_cannot_start_or_submit_attempt(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Archived Student Quiz',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'show_result_immediately' => true,
            'total_marks' => 1,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Can inactive students attempt?',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 1,
        ]);
        $correct = QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'No',
            'is_correct' => true,
            'order' => 1,
        ]);
        $this->enroll($student, $subject);

        $this->actingAs($student->user)
            ->get(route('student.quizzes.show', $quiz))
            ->assertOk()
            ->assertSee('Archived Student Quiz');

        $this->actingAs($student->user)
            ->post(route('student.quizzes.start', $quiz))
            ->assertForbidden();

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(10),
            'is_completed' => false,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), [
                'answers' => [$question->id => $correct->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'is_completed' => false,
            'score' => null,
        ]);
        $this->assertDatabaseMissing('quiz_answers', [
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $correct->id,
        ]);
    }

    public function test_quiz_submission_does_not_store_option_from_another_question(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Option Integrity Quiz',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'total_marks' => 1,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Original question.',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 1,
        ]);
        QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Correct',
            'is_correct' => true,
            'order' => 1,
        ]);
        $otherQuestion = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Other question.',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 2,
        ]);
        $otherOption = QuizOption::create([
            'quiz_question_id' => $otherQuestion->id,
            'option_text' => 'Other option',
            'is_correct' => true,
            'order' => 1,
        ]);
        $this->enroll($student, $subject);

        $this->actingAs($student->user)->post(route('student.quizzes.start', $quiz))->assertOk();
        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), [
                'answers' => [$question->id => $otherOption->id],
            ])
            ->assertRedirect(route('student.quizzes.result', $quiz));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'score' => 0,
            'is_completed' => true,
        ]);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_question_id' => $question->id,
            'quiz_option_id' => null,
            'is_correct' => false,
        ]);
        $this->assertDatabaseMissing('quiz_answers', [
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $otherOption->id,
        ]);
    }

    public function test_student_cannot_submit_quiz_after_it_is_closed_or_unpublished(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Closing Quiz',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'total_marks' => 1,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Answer before close.',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 1,
        ]);
        $correct = QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Correct',
            'is_correct' => true,
            'order' => 1,
        ]);
        $this->enroll($student, $subject);

        $this->actingAs($student->user)->post(route('student.quizzes.start', $quiz))->assertOk();

        $quiz->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => $correct->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'is_completed' => false,
            'score' => null,
        ]);
        $this->assertDatabaseMissing('quiz_answers', [
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $correct->id,
        ]);

        $quiz->forceFill(['ends_at' => now()->addHour(), 'is_published' => false])->save();

        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => $correct->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'is_completed' => false,
            'score' => null,
        ]);
    }

    public function test_student_cannot_submit_quiz_after_attempt_duration_expires(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Timed Quiz',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 10,
            'is_published' => true,
            'total_marks' => 1,
        ]);
        $question = QuizQuestion::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Answer before timer expires.',
            'type' => 'mcq',
            'marks' => 1,
            'order' => 1,
        ]);
        $correct = QuizOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Correct',
            'is_correct' => true,
            'order' => 1,
        ]);
        $this->enroll($student, $subject);

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(11),
            'is_completed' => false,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.quizzes.submit', $quiz), ['answers' => [$question->id => $correct->id]])
            ->assertForbidden();

        $attempt->refresh();
        $this->assertFalse($attempt->is_completed);
        $this->assertNull($attempt->score);
        $this->assertDatabaseMissing('quiz_answers', [
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'quiz_option_id' => $correct->id,
        ]);
    }

    public function test_student_cannot_view_quiz_result_after_quiz_is_unpublished(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Unpublished Result Quiz',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'is_published' => true,
            'show_result_immediately' => true,
            'total_marks' => 1,
        ]);
        $this->enroll($student, $subject);
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'score' => 1,
            'is_completed' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.quizzes.result', $quiz))
            ->assertOk()
            ->assertSee('Unpublished Result Quiz');

        $quiz->forceFill(['is_published' => false])->save();

        $this->actingAs($student->user)
            ->get(route('student.quizzes.result', $quiz))
            ->assertNotFound();
    }

    public function test_student_cannot_view_quiz_result_until_results_are_released(): void
    {
        $student = $this->student();
        $subject = Subject::factory()->create();
        $quiz = Quiz::create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher()->id,
            'title' => 'Delayed Result Quiz',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_published' => true,
            'show_result_immediately' => false,
            'total_marks' => 1,
        ]);
        $this->enroll($student, $subject);
        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinute(),
            'score' => 1,
            'is_completed' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.quizzes.result', $quiz))
            ->assertRedirect(route('student.quizzes.index'))
            ->assertSessionHas('error', 'Quiz results are not available yet.');

        $quiz->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->actingAs($student->user)
            ->get(route('student.quizzes.result', $quiz))
            ->assertOk()
            ->assertSee('Delayed Result Quiz');
    }
}
