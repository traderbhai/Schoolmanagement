<?php

namespace Tests\Feature;

use App\Models\PmcAssessmentComponentConfig;
use App\Models\AcademicPmcElectiveChoice;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ElectiveRegistrationWindow;
use App\Models\Program;
use App\Models\ProgramSubject;
use App\Models\RoleProgramAssignment;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\SubjectFacultyAssignment;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PmcAssessmentSchemeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function chair(?Program $program = null): User
    {
        Role::firstOrCreate(['name' => 'program_chair', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('program_chair');

        if ($program) {
            RoleProgramAssignment::create([
                'user_id' => $user->id,
                'role_name' => 'program_chair',
                'program_id' => $program->id,
                'is_active' => true,
                'assigned_by' => User::factory()->create()->id,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }

    private function curriculumSet(): array
    {
        $program = Program::factory()->create(['is_active' => true]);
        $term = Term::factory()->create(['program_id' => $program->id, 'name' => 'Assessment Term']);
        $subject = Subject::factory()->create(['program_id' => $program->id, 'name' => 'Assessment Design']);
        $programSubject = ProgramSubject::create([
            'program_id' => $program->id,
            'subject_id' => $subject->id,
            'term_id' => $term->id,
            'type' => 'compulsory',
            'credits' => 3,
            'is_active' => true,
        ]);

        return compact('program', 'term', 'subject', 'programSubject');
    }

    public function test_pmc_assessment_scheme_page_uses_database_backed_config_rows(): void
    {
        $set = $this->curriculumSet();

        PmcAssessmentComponentConfig::create([
            'program_subject_id' => $set['programSubject']->id,
            'program_id' => $set['program']->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'name' => 'IA1',
            'max_marks' => 20,
            'weightage' => 20,
        ]);

        $this->actingAs($this->chair($set['program']))
            ->get(route('chair.curriculum.assessment', [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
            ]))
            ->assertOk()
            ->assertSee('Assessment Design')
            ->assertSee('IA1')
            ->assertSee('20.00');
    }

    public function test_pmc_assessment_component_save_writes_curriculum_config_not_result_marks(): void
    {
        $set = $this->curriculumSet();

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.curriculum.assessment.save'), [
                'program_subject_id' => $set['programSubject']->id,
                'subject_id' => $set['subject']->id,
                'term_id' => $set['term']->id,
                'name' => 'End-Sem',
                'max_marks' => 100,
                'weightage' => 60,
            ])
            ->assertSessionHas('success', 'Assessment component saved.');

        $this->assertDatabaseHas('pmc_assessment_component_configs', [
            'program_subject_id' => $set['programSubject']->id,
            'program_id' => $set['program']->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'name' => 'End-Sem',
            'max_marks' => 100,
            'weightage' => 60,
        ]);
        $this->assertDatabaseCount('assessment_components', 0);
    }

    public function test_pmc_assessment_component_weightage_cannot_exceed_one_hundred_percent(): void
    {
        $set = $this->curriculumSet();

        PmcAssessmentComponentConfig::create([
            'program_subject_id' => $set['programSubject']->id,
            'program_id' => $set['program']->id,
            'subject_id' => $set['subject']->id,
            'term_id' => $set['term']->id,
            'name' => 'IA1',
            'max_marks' => 20,
            'weightage' => 60,
        ]);

        $this->actingAs($this->chair($set['program']))
            ->post(route('chair.curriculum.assessment.save'), [
                'program_subject_id' => $set['programSubject']->id,
                'subject_id' => $set['subject']->id,
                'term_id' => $set['term']->id,
                'name' => 'IA2',
                'max_marks' => 20,
                'weightage' => 50,
            ])
            ->assertSessionHasErrors('weightage');

        $this->assertSame(1, PmcAssessmentComponentConfig::count());
    }

    public function test_pmc_assessment_component_save_rejects_mismatched_curriculum_subject(): void
    {
        $assigned = $this->curriculumSet();
        $other = $this->curriculumSet();

        $this->actingAs($this->chair($assigned['program']))
            ->post(route('chair.curriculum.assessment.save'), [
                'program_subject_id' => $other['programSubject']->id,
                'subject_id' => $assigned['subject']->id,
                'term_id' => $assigned['term']->id,
                'name' => 'IA1',
                'max_marks' => 20,
                'weightage' => 20,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('pmc_assessment_component_configs', 0);
    }

    public function test_pmc_curriculum_subject_writes_require_assigned_program_and_matching_active_subject(): void
    {
        $assigned = $this->curriculumSet();
        $other = $this->curriculumSet();
        $inactiveSubject = Subject::factory()->create([
            'program_id' => $assigned['program']->id,
            'is_active' => false,
        ]);
        $newSubject = Subject::factory()->create([
            'program_id' => $assigned['program']->id,
            'is_active' => true,
        ]);
        $chair = $this->chair($assigned['program']);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.add-subject'), [
                'program_id' => $other['program']->id,
                'subject_id' => $other['subject']->id,
                'term_id' => $other['term']->id,
                'type' => 'compulsory',
                'credits' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->post(route('chair.curriculum.add-subject'), [
                'program_id' => $assigned['program']->id,
                'subject_id' => $other['subject']->id,
                'term_id' => $assigned['term']->id,
                'type' => 'compulsory',
                'credits' => 3,
            ])
            ->assertStatus(422);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.add-subject'), [
                'program_id' => $assigned['program']->id,
                'subject_id' => $inactiveSubject->id,
                'term_id' => $assigned['term']->id,
                'type' => 'compulsory',
                'credits' => 3,
            ])
            ->assertStatus(422);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.add-subject'), [
                'program_id' => $assigned['program']->id,
                'subject_id' => $newSubject->id,
                'term_id' => $assigned['term']->id,
                'type' => 'compulsory',
                'credits' => 3,
            ])
            ->assertSessionHas('success', 'Subject added to curriculum.');

        $this->assertSame(1, ProgramSubject::where('program_id', $other['program']->id)
            ->where('subject_id', $other['subject']->id)
            ->where('term_id', $other['term']->id)
            ->count());
        $this->assertDatabaseHas('program_subjects', [
            'program_id' => $assigned['program']->id,
            'subject_id' => $newSubject->id,
            'term_id' => $assigned['term']->id,
        ]);
    }

    public function test_pmc_faculty_assignment_requires_assigned_active_curriculum_subject(): void
    {
        $assigned = $this->curriculumSet();
        $other = $this->curriculumSet();
        $unmappedSubject = Subject::factory()->create([
            'program_id' => $assigned['program']->id,
            'is_active' => true,
        ]);
        $teacher = Teacher::factory()->create(['status' => 'active']);
        $chair = $this->chair($assigned['program']);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.assign-faculty'), [
                'program_id' => $other['program']->id,
                'subject_id' => $other['subject']->id,
                'term_id' => $other['term']->id,
                'teacher_id' => $teacher->id,
                'is_primary' => true,
            ])
            ->assertForbidden();

        $this->actingAs($chair)
            ->post(route('chair.curriculum.assign-faculty'), [
                'program_id' => $assigned['program']->id,
                'subject_id' => $unmappedSubject->id,
                'term_id' => $assigned['term']->id,
                'teacher_id' => $teacher->id,
                'is_primary' => true,
            ])
            ->assertSessionHasErrors('subject_id');

        $this->actingAs($chair)
            ->post(route('chair.curriculum.assign-faculty'), [
                'program_id' => $assigned['program']->id,
                'subject_id' => $assigned['subject']->id,
                'term_id' => $assigned['term']->id,
                'teacher_id' => $teacher->id,
                'is_primary' => true,
            ])
            ->assertSessionHas('success', 'Faculty assigned to subject.');

        $this->assertSame(1, SubjectFacultyAssignment::count());
        $this->assertDatabaseHas('subject_faculty_assignments', [
            'program_id' => $assigned['program']->id,
            'subject_id' => $assigned['subject']->id,
            'term_id' => $assigned['term']->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_pmc_faculty_assignment_workload_prefers_official_canonical_sessions(): void
    {
        $set = $this->curriculumSet();
        $chair = $this->chair($set['program']);
        $batch = Batch::factory()->create(['program_id' => $set['program']->id]);
        $semester = Semester::factory()->create(['number' => 1, 'name' => $set['term']->name]);
        $course = Course::factory()->create();
        $teacherUser = User::factory()->create(['name' => 'Canonical Assignment Faculty']);
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'status' => 'active',
        ]);
        $slot = TimetableSlot::factory()->create([
            'name' => 'Assignment Workload Period',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'sort_order' => 1,
        ]);
        $room = Classroom::factory()->create();
        $version = TimetableVersion::create([
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $batch->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $chair->id,
            'published_by' => $chair->id,
            'published_at' => now(),
        ]);
        $run = AcademicPmcTimetableGenerationRun::create([
            'title' => 'Assignment Workload Canonical Run',
            'strategy' => 'balanced',
            'program_id' => $set['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $set['term']->id,
            'timetable_version_id' => $version->id,
            'created_by' => $chair->id,
            'status' => 'published',
            'scheduled_count' => 1,
            'quality_score' => 100,
        ]);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'Assignment Workload Section A',
            'group_type' => 'core_section',
            'program_id' => $set['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
            'is_locked' => true,
        ]);
        AcademicPmcTimetableGenerationItem::create([
            'generation_run_id' => $run->id,
            'timetable_version_id' => $version->id,
            'course_group_id' => $group->id,
            'program_id' => $set['program']->id,
            'batch_id' => $batch->id,
            'term_id' => $set['term']->id,
            'subject_id' => $set['subject']->id,
            'session_index' => 1,
            'session_type' => 'lab',
            'duration_slots' => 2,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'day_of_week' => 1,
            'timetable_slot_id' => $slot->id,
            'status' => 'scheduled',
            'official_status' => 'published',
            'source_type' => 'generated',
            'published_at' => now(),
            'published_by' => $chair->id,
        ]);

        TimetableEntry::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => $course->id,
            'program_id' => $set['program']->id,
            'term_id' => $set['term']->id,
            'batch_id' => $batch->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $room->id,
            'timetable_slot_id' => TimetableSlot::factory()->create(['sort_order' => 5])->id,
            'day_of_week' => 3,
            'is_active' => true,
            'status' => 'published',
        ]);

        SubjectFacultyAssignment::create([
            'program_id' => $set['program']->id,
            'subject_id' => $set['subject']->id,
            'teacher_id' => $teacher->id,
            'term_id' => $set['term']->id,
            'batch_id' => $batch->id,
            'is_primary' => true,
            'assigned_by' => $chair->id,
        ]);

        $response = $this->actingAs($chair)
            ->get(route('chair.curriculum.assignments', [
                'program_id' => $set['program']->id,
                'term_id' => $set['term']->id,
            ]));

        $response
            ->assertOk()
            ->assertSee('Canonical Assignment Faculty')
            ->assertSee('2 sessions')
            ->assertDontSee('3 sessions');

        $this->assertSame(2, $response->viewData('workload')[$teacher->id]);
    }

    public function test_pmc_elective_window_writes_require_scope_and_lock_finalized_history(): void
    {
        $assigned = $this->curriculumSet();
        $other = $this->curriculumSet();
        $chair = $this->chair($assigned['program']);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.electives.window'), [
                'program_id' => $other['program']->id,
                'term_id' => $other['term']->id,
                'opens_at' => now()->addDay()->toDateTimeString(),
                'closes_at' => now()->addDays(7)->toDateTimeString(),
                'max_selections' => 2,
                'instructions' => 'Out of scope window.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('elective_registration_windows', [
            'program_id' => $other['program']->id,
            'term_id' => $other['term']->id,
            'instructions' => 'Out of scope window.',
        ]);

        $window = ElectiveRegistrationWindow::create([
            'program_id' => $assigned['program']->id,
            'term_id' => $assigned['term']->id,
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->subDay(),
            'max_selections' => 2,
            'status' => 'finalized',
            'created_by' => $chair->id,
        ]);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.electives.window.status', $window), [
                'status' => 'open',
            ])
            ->assertSessionHas('error', 'Finalized elective windows are locked. Create a new add/drop or revision window instead.');

        $this->assertSame('finalized', $window->fresh()->status);
    }

    public function test_pmc_closed_elective_window_with_submitted_choices_cannot_be_reopened(): void
    {
        $assigned = $this->curriculumSet();
        $chair = $this->chair($assigned['program']);
        $window = ElectiveRegistrationWindow::create([
            'program_id' => $assigned['program']->id,
            'term_id' => $assigned['term']->id,
            'opens_at' => now()->subDays(10),
            'closes_at' => now()->subDay(),
            'max_selections' => 2,
            'status' => 'closed',
            'created_by' => $chair->id,
        ]);
        AcademicPmcElectiveChoice::create([
            'student_id' => \App\Models\Student::factory()->create(['program_id' => $assigned['program']->id])->id,
            'program_id' => $assigned['program']->id,
            'term_id' => $assigned['term']->id,
            'subject_id' => $assigned['subject']->id,
            'preference_rank' => 1,
            'status' => 'submitted',
            'choice_source' => 'student_choice',
        ]);

        $this->actingAs($chair)
            ->post(route('chair.curriculum.electives.window.status', $window), [
                'status' => 'open',
            ])
            ->assertSessionHas('error', 'Closed elective windows with submitted choices cannot be reopened. Create a new revision window instead.');

        $this->assertSame('closed', $window->fresh()->status);
    }
}
