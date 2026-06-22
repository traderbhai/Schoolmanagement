<?php

namespace Tests\Feature;

use App\Models\{AcademicPmcCourseGroup, AcademicPmcSubstitutionRecommendation, AcademicPmcTimetableChangeRequest, AcademicPmcTimetableGenerationItem, AcademicPmcTimetableGenerationRun, Batch, Classroom, Course, Program, Semester, Student, StudentSubjectEnrollment, Subject, Teacher, Term, TimetableEntry, TimetableSlot, TimetableVersion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentTimetableWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_timetable_empty_state_explains_registration_and_publication_next_steps(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('student.timetable'))
            ->assertOk()
            ->assertSee('No published timetable is available for your enrolled subjects yet')
            ->assertSee('review subject registration first')
            ->assertSee('PMC/academic office to publish the official timetable')
            ->assertSee('Review subject registration')
            ->assertDontSee('No timetable entries found for your enrolled subjects');
    }

    public function test_student_timetable_uses_canonical_subject_enrollments_and_numeric_weekdays(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical Timetable Subject',
            'code' => 'CTS101',
        ]);
        $hiddenSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Unenrolled Timetable Subject',
            'code' => 'UTS101',
        ]);
        $draftSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Draft Timetable Subject',
            'code' => 'DTS101',
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Draft Version Timetable Subject',
            'code' => 'DVS101',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        foreach ([$draftSubject, $draftVersionSubject] as $enrolledButUnpublishedSubject) {
            StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'subject_id' => $enrolledButUnpublishedSubject->id,
                'term_id' => $term->id,
                'enrollment_type' => 'compulsory',
                'status' => 'active',
            ]);
        }
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'Morning Slot', 'sort_order' => 1]);
        $room = Classroom::factory()->create(['name' => 'Room T1']);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);

        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => $slot->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $hiddenSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $draftSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'draft',
        ]);
        TimetableEntry::create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $draftVersionSubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create()->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 4,
            'is_active' => true,
            'status' => 'published',
            'timetable_version_id' => $draftVersion->id,
        ]);

        $this->actingAs($user)
            ->get(route('student.timetable'))
            ->assertOk()
            ->assertSee('Monday')
            ->assertSee('Canonical Timetable Subject')
            ->assertSee('Room T1')
            ->assertDontSee('Unenrolled Timetable Subject')
            ->assertDontSee('Draft Timetable Subject')
            ->assertDontSee('Draft Version Timetable Subject');
    }

    public function test_student_timetable_prefers_published_pmc_official_group_sessions(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'PMC Term 1',
        ]);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'course_id' => $course->id,
            'current_term_id' => $term->id,
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical PMC Student Subject',
            'code' => 'PMS101',
        ]);
        $legacyOnlySubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Legacy Flattened Student Subject',
            'code' => 'LFS101',
        ]);
        StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'enrollment_type' => 'compulsory',
            'status' => 'active',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical PMC Section A',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $group->members()->create(['student_id' => $student->id, 'status' => 'active']);
        $teacher = Teacher::factory()->create();
        $slot = TimetableSlot::factory()->create(['name' => 'PMC Morning Slot', 'sort_order' => 1]);
        $room = Classroom::factory()->create(['name' => 'PMC Student Room']);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Student Canonical PMC Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Draft Canonical Student Room'])->id,
            'day_of_week' => 3,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'draft',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);
        TimetableEntry::create([
            'semester_id' => Semester::factory()->create(['number' => 1])->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $legacyOnlySubject->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Legacy Student Room'])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('student.timetable'))
            ->assertOk()
            ->assertSee('Canonical PMC Student Subject')
            ->assertSee('Canonical PMC Section A')
            ->assertSee('PMC Student Room')
            ->assertDontSee('Draft Canonical Student Room')
            ->assertDontSee('Legacy Flattened Student Subject')
            ->assertDontSee('Legacy Student Room');
    }

    public function test_teacher_timetable_hides_draft_entries_and_draft_versions(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term 1',
            'start_date' => now()->subDay(),
        ]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => 'Term 1']);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('teacher');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $publishedSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Published Teacher Timetable Subject',
            'code' => 'PTT101',
        ]);
        $draftSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Draft Teacher Timetable Subject',
            'code' => 'DTT101',
        ]);
        $draftVersionSubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Draft Version Teacher Timetable Subject',
            'code' => 'DVT101',
        ]);
        $draftVersion = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
        ]);

        foreach ([
            [$publishedSubject, 'Monday Teacher Room', 1, 'published', null],
            [$draftSubject, 'Draft Teacher Room', 2, 'draft', null],
            [$draftVersionSubject, 'Draft Version Teacher Room', 3, 'published', $draftVersion->id],
        ] as [$subject, $roomName, $day, $status, $versionId]) {
            TimetableEntry::create([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'classroom_id' => Classroom::factory()->create(['name' => $roomName])->id,
                'timetable_slot_id' => TimetableSlot::factory()->create()->id,
                'day_of_week' => $day,
                'is_active' => true,
                'status' => $status,
                'timetable_version_id' => $versionId,
            ]);
        }

        $this->actingAs($user)
            ->get(route('teacher.timetable.index'))
            ->assertOk()
            ->assertSee('Published Teacher Timetable Subject')
            ->assertSee('Monday Teacher Room')
            ->assertDontSee('Draft Teacher Timetable Subject')
            ->assertDontSee('Draft Version Teacher Timetable Subject');
    }

    public function test_teacher_timetable_prefers_published_pmc_official_group_sessions(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'PMC Teacher Term',
            'start_date' => now()->subDay(),
        ]);
        $course = Course::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('teacher');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical PMC Teacher Subject',
            'code' => 'PMT101',
        ]);
        $legacyOnlySubject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Legacy Flattened Teacher Subject',
            'code' => 'LFT101',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical PMC Teacher Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $slot = TimetableSlot::factory()->create(['name' => 'PMC Teacher Slot', 'sort_order' => 1]);
        $room = Classroom::factory()->create(['name' => 'PMC Teacher Room']);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Teacher Canonical PMC Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);

        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'locked',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'session_index' => 2,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Draft Canonical Teacher Room'])->id,
            'day_of_week' => 3,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'status' => 'draft',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);
        TimetableEntry::create([
            'semester_id' => Semester::factory()->create(['number' => 1])->id,
            'course_id' => $course->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $legacyOnlySubject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => Classroom::factory()->create(['name' => 'Legacy Teacher Room'])->id,
            'timetable_slot_id' => TimetableSlot::factory()->create()->id,
            'day_of_week' => 2,
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('teacher.timetable.index'))
            ->assertOk()
            ->assertSee('Canonical PMC Teacher Subject')
            ->assertSee('Canonical PMC Teacher Section')
            ->assertSee('PMC Teacher Room')
            ->assertDontSee('Draft Canonical Teacher Room')
            ->assertDontSee('Legacy Flattened Teacher Subject')
            ->assertDontSee('Legacy Teacher Room');
    }

    public function test_teacher_timetable_shows_canonical_substitution_and_change_alerts_for_today(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $term = Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'PMC Alert Term',
            'start_date' => now()->subDay(),
        ]);
        $user = User::factory()->create();
        $user->assignRole('teacher');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $substituteUser = User::factory()->create(['name' => 'Cover Faculty']);
        $substituteTeacher = Teacher::factory()->create(['user_id' => $substituteUser->id]);
        $subject = Subject::factory()->create([
            'program_id' => $program->id,
            'term_number' => 1,
            'name' => 'Canonical Alert Subject',
            'code' => 'CAS101',
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Canonical Alert Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 1,
            'status' => 'active',
            'is_locked' => true,
        ]);
        $slot = TimetableSlot::factory()->create(['name' => 'Alert Slot', 'sort_order' => 1]);
        $room = Classroom::factory()->create(['name' => 'Alert Room']);
        $version = TimetableVersion::create([
            'program_id' => $program->id,
            'term_id' => $term->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => User::factory()->create()->id,
            'published_by' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Teacher Canonical Alert Run',
            'strategy' => 'balanced',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'timetable_version_id' => $version->id,
            'created_by' => $version->created_by,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $item = AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'session_index' => 1,
            'session_type' => 'lecture',
            'duration_slots' => 1,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $version->published_by,
        ]);

        AcademicPmcSubstitutionRecommendation::create([
            'pmc_generation_item_id' => $item->id,
            'course_group_id' => $group->id,
            'original_teacher_id' => $teacher->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'substitution_date' => today()->toDateString(),
            'status' => 'recorded',
            'score' => 100,
            'reasons' => ['faculty unavailable'],
            'conflict_checks' => ['source' => 'test'],
        ]);
        AcademicPmcTimetableChangeRequest::create([
            'timetable_version_id' => $version->id,
            'pmc_generation_item_id' => $item->id,
            'change_type' => 'cancellation',
            'status' => 'pending',
            'requested_by' => $version->created_by,
            'reason' => 'Room maintenance.',
            'impact_summary' => [
                'requested_date' => today()->toDateString(),
                'session_label' => $group->name,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('teacher.timetable.index'))
            ->assertOk()
            ->assertSee('Substitutions Today')
            ->assertSee('Canonical Alert Subject')
            ->assertSee('Canonical Alert Section')
            ->assertSee('Substituted by Cover Faculty')
            ->assertSee('Official PMC session')
            ->assertSee('Cancellation requested')
            ->assertSee('Official PMC change request')
            ->assertSee('Room maintenance.');
    }

    public function test_teacher_timetable_empty_state_explains_published_assignment_source(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        Term::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_number' => 1,
            'name' => 'Term Without Teacher Allocation',
            'start_date' => now()->subDay(),
        ]);
        TimetableSlot::factory()->create([
            'name' => 'Period 1',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'sort_order' => 1,
            'is_active' => true,
            'is_break' => false,
        ]);

        $user = User::factory()->create();
        $user->assignRole('teacher');
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('teacher.timetable.index'))
            ->assertOk()
            ->assertSee('No published teaching timetable is assigned to you yet.')
            ->assertSee('PMC or the academic office must publish your subject allocation in the official timetable before classes appear here.')
            ->assertSee('Draft timetable entries and unpublished timetable versions stay hidden from teacher self-service.')
            ->assertSee('Free')
            ->assertDontSee('N/A')
            ->assertDontSee('â', false)
            ->assertDontSee('&mdash;', false)
            ->assertDontSee('&ndash;', false);

        $template = file_get_contents(resource_path('views/teacher/timetable/index.blade.php'));

        $this->assertStringNotContainsString('N/A', $template);
        $this->assertStringNotContainsString('â', $template);
        $this->assertStringNotContainsString('&mdash;', $template);
        $this->assertStringNotContainsString('&ndash;', $template);
    }
}
