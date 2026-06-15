<?php

namespace Tests\Feature;

use App\Models\AcademicPmcCourseDeliveryCheckpoint;
use App\Models\AcademicPmcCourseGroup;
use App\Models\AcademicPmcGroupDeliveryTracker;
use App\Models\AcademicPmcRemedialAction;
use App\Models\AcademicPmcSessionDeliveryLog;
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

class AcademicsPmcV084Test extends TestCase
{
    use RefreshDatabase;

    public function test_course_delivery_execution_diagnostics_render_on_pmc_delivery_pages(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-delivery.index'))
            ->assertOk()
            ->assertSee('Course Delivery Execution Diagnostics')
            ->assertSee('Pending Faculty Logs')
            ->assertSee('Topic Covered Missing')
            ->assertSee('Session Delivery Log Queue');

        $this->actingAs($chair)
            ->get(route('academics.pmc.delivery-risk.index'))
            ->assertOk()
            ->assertSee('Course Delivery Execution Diagnostics')
            ->assertSee('Behind Groups');
    }

    public function test_course_delivery_execution_blockers_are_counted(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $department = Department::factory()->create(['code' => 'V084', 'name' => 'v084 Department']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'V084-P', 'name' => 'v084 Program', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'V084-B', 'name' => 'v084 Batch', 'status' => 'active']);
        $term = Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'v084 Term', 'is_current' => true]);
        $subject = Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'V084-S', 'name' => 'v084 Subject', 'is_active' => true]);
        $teacher = Teacher::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $group = AcademicPmcCourseGroup::create([
            'name' => 'v084 Core Section',
            'group_type' => 'core_section',
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'owner_user_id' => $chair->id,
            'min_capacity' => 1,
            'max_capacity' => 60,
            'current_strength' => 40,
            'status' => 'active',
        ]);
        $checkpoint = AcademicPmcCourseDeliveryCheckpoint::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'owner_user_id' => $chair->id,
            'planned_sessions' => 8,
            'conducted_sessions' => 3,
            'missed_sessions' => 2,
            'marks_pending_count' => 4,
            'attendance_percent' => 62,
            'delivery_score' => 45,
            'risk_band' => 'critical',
            'status' => 'open',
            'next_review_at' => now()->subDay(),
            'signals' => ['reasons' => ['Planned sessions not conducted']],
        ]);
        $tracker = AcademicPmcGroupDeliveryTracker::create([
            'course_group_id' => $group->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'owner_user_id' => $chair->id,
            'planned_sessions' => 8,
            'conducted_sessions' => 3,
            'missed_sessions' => 2,
            'rescheduled_sessions' => 1,
            'cancelled_sessions' => 1,
            'pending_session_logs' => 3,
            'delivery_progress' => 38,
            'risk_score' => 80,
            'risk_band' => 'critical',
            'status' => 'open',
            'next_review_at' => now()->subDay(),
            'risk_reasons' => ['Session delivery logs pending'],
            'recommended_actions' => ['collect faculty log'],
        ]);
        AcademicPmcSessionDeliveryLog::create([
            'group_delivery_tracker_id' => $tracker->id,
            'course_group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'scheduled_date' => now()->toDateString(),
            'session_status' => 'conducted',
            'delivery_type' => 'lecture',
            'attendance_marked' => false,
            'lesson_plan_updated' => false,
            'material_uploaded' => false,
            'topic_planned' => null,
            'topic_covered' => null,
            'gap_reason' => 'Faculty log pending',
        ]);
        AcademicPmcSessionDeliveryLog::create([
            'group_delivery_tracker_id' => $tracker->id,
            'course_group_id' => $group->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'scheduled_date' => now()->subDay()->toDateString(),
            'session_status' => 'missed',
            'delivery_type' => 'lecture',
            'attendance_marked' => false,
            'lesson_plan_updated' => false,
            'material_uploaded' => false,
            'topic_planned' => 'Demand forecasting',
            'gap_reason' => 'Faculty unavailable',
        ]);
        AcademicPmcRemedialAction::create([
            'checkpoint_id' => $checkpoint->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'owner_user_id' => $chair->id,
            'created_by' => $chair->id,
            'action_type' => 'makeup_session',
            'status' => 'open',
            'priority' => 'high',
            'reason' => 'v084 delivery blocker',
            'action_plan' => 'Schedule makeup and collect evidence.',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.course-delivery.index'))
            ->assertOk()
            ->assertSee('Course Delivery Execution Diagnostics')
            ->assertSee('Pending Faculty Logs')
            ->assertSee('Attendance Pending')
            ->assertSee('Lesson Plan Pending')
            ->assertSee('Material Pending')
            ->assertSee('Topic Planned Missing')
            ->assertSee('Topic Covered Missing')
            ->assertSee('Overdue Reviews')
            ->assertSee('Collect pending faculty logs, complete topic/attendance/material updates, and close delivery remedial blockers.');
    }
}
