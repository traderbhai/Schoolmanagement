<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcSubstitutionRecommendation;
use App\Models\AcademicPmcTimetableChangeRequest;
use App\Models\AcademicPmcTimetableNotification;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV083Test extends TestCase
{
    use RefreshDatabase;

    public function test_substitution_emergency_desk_renders_on_dashboard_and_substitution_page(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Substitution Emergency Desk')
            ->assertSee('Uncovered Today')
            ->assertSee('Open substitution desk');

        $this->actingAs($chair)
            ->get(route('academics.pmc.substitution-intelligence.index'))
            ->assertOk()
            ->assertSee('Substitution Emergency Desk')
            ->assertSee('Repeat Faculty')
            ->assertSee('Substitution Recommendations');
    }

    public function test_substitution_emergency_blockers_are_counted(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'V083', 'name' => 'v083 Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V083-P', 'name' => 'v083 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V083-B', 'name' => 'v083 Batch', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'v083 Term', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V083-S', 'name' => 'v083 Subject', 'is_active' => true]);
        $original = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $substitute = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'v083 Core Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 35,
            'status' => 'active',
        ]);

        AcademicPmcSubstitutionRecommendation::create([
            'course_group_id' => $group->id,
            'original_teacher_id' => $original->id,
            'substitute_teacher_id' => null,
            'substitution_date' => now()->toDateString(),
            'status' => 'uncovered',
            'score' => 0,
            'reasons' => ['no_available_faculty'],
            'conflict_checks' => ['faculty' => 'uncovered'],
        ]);
        AcademicPmcSubstitutionRecommendation::create([
            'course_group_id' => $group->id,
            'original_teacher_id' => $original->id,
            'substitute_teacher_id' => $substitute->id,
            'substitution_date' => now()->addDay()->toDateString(),
            'status' => 'recommended',
            'score' => 45,
            'reasons' => ['weak_fit'],
            'conflict_checks' => ['faculty' => 'clear'],
        ]);
        AcademicPmcTimetableChangeRequest::create([
            'change_type' => 'substitution',
            'status' => 'requested',
            'requested_by' => $chair->id,
            'reason' => 'v083 same-day substitution fixture',
        ]);
        AcademicPmcTimetableNotification::create([
            'notification_type' => 'substitution',
            'recipient_type' => 'faculty',
            'recipient_user_id' => $chair->id,
            'title' => 'v083 failed substitution notice',
            'message' => 'Substitution notice failed.',
            'status' => 'failed',
            'source_type' => 'substitution',
            'source_key' => 'v083',
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-os.index'))
            ->assertOk()
            ->assertSee('Uncovered Today')
            ->assertSee('Low Score')
            ->assertSee('Failed Notices')
            ->assertSee('Same-Day Changes')
            ->assertSee('Repeat Faculty')
            ->assertSee('Repeat Groups')
            ->assertSee('Resolve uncovered classes, weak recommendations, same-day changes, and failed substitution notifications before class time.');
    }
}
