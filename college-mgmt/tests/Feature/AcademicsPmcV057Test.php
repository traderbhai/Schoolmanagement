<?php

namespace Tests\Feature;

use App\Models\AcademicPmcParentEscalation;
use App\Models\AcademicPmcStudentIntervention;
use App\Models\AcademicPmcStudentSuccessPlan;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentGrievance;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcV057Test extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        $department = Department::factory()->create(['code' => 'MGT', 'name' => 'Management Studies']);
        $program = Program::factory()->create(['department_id' => $department->id, 'code' => 'PGDM', 'name' => 'PGDM', 'is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'code' => 'PGDM-26', 'name' => 'PGDM 2026', 'status' => 'active']);
        Term::factory()->create(['program_id' => $program->id, 'batch_id' => $batch->id, 'term_number' => 1, 'name' => 'Term 1', 'is_current' => true]);
        Subject::factory()->create(['department_id' => $department->id, 'program_id' => $program->id, 'code' => 'MGT401', 'name' => 'Management Analytics', 'credits' => 3, 'is_active' => true]);

        $this->seed(AcademicsOperatingDemoSeeder::class);

        $studentUser = User::factory()->create(['name' => 'PMC v057 Risk Student']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'guardian_name' => 'Risk Parent',
            'guardian_phone' => '9999990000',
            'status' => 'active',
        ]);
        $subject = Subject::where('program_id', $program->id)->firstOrFail();
        foreach (range(1, 2) as $index) {
            $exam = Exam::factory()->create([
                'program_id' => $program->id,
                'subject_id' => $subject->id,
                'published_at' => now(),
                'name' => 'Published Risk Exam '.$index,
            ]);
            ExamResult::factory()->create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'marks_obtained' => 30,
                'grade' => 'F',
                'is_absent' => true,
            ]);
        }
        StudentGrievance::create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'category' => 'academic',
            'title' => 'Academic risk grievance',
            'description' => 'Needs PMC intervention.',
            'status' => 'open',
            'priority' => 'high',
        ]);

        return [User::where('email', 'chair@college.com')->firstOrFail(), $student];
    }

    public function test_pmc_student_success_refresh_intervention_and_parent_escalation_workflow(): void
    {
        [$chair, $student] = $this->seedFixture();

        $this->actingAs($chair)
            ->post(route('academics.pmc.student-success-v004.refresh'))
            ->assertRedirect();

        $plan = AcademicPmcStudentSuccessPlan::where('student_id', $student->id)->where('risk_type', 'retention_risk')->firstOrFail();
        $this->assertContains($plan->risk_band, ['high', 'critical']);
        $this->assertTrue((bool) $plan->parent_escalation_required);
        $this->assertGreaterThanOrEqual(70, $plan->signals['risk_score']);

        $this->actingAs($chair)
            ->post(route('academics.pmc.student-success-v004.interventions.store', $plan), [
                'intervention_type' => 'mentor_meeting',
                'priority' => 'critical',
                'reason' => 'Critical attendance-performance risk.',
                'action_plan' => 'Mentor meeting, remedial class, parent call.',
                'due_at' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $intervention = AcademicPmcStudentIntervention::where('student_success_plan_id', $plan->id)->firstOrFail();
        $this->assertSame('open', $intervention->status);

        $this->actingAs($chair)
            ->patch(route('academics.pmc.interventions.update', $intervention), [
                'status' => 'resolved',
                'evidence_note' => 'Mentor note and remedial plan submitted.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('academic_pmc_student_interventions', ['id' => $intervention->id, 'status' => 'resolved']);

        $this->actingAs($chair)
            ->post(route('academics.pmc.student-success-v004.parent-escalations.store', $plan), [
                'reason' => 'attendance_performance_risk',
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();
        $this->assertTrue(AcademicPmcParentEscalation::where('student_success_plan_id', $plan->id)->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.student-success-v004.index'))
            ->assertOk()
            ->assertSee('PMC Student Risk Command')
            ->assertSee('Intervention Lifecycle')
            ->assertSee('Parent / Guardian Escalations')
            ->assertSee('Refresh Risk Signals');
    }

    public function test_pmc_student_success_refresh_ignores_unpublished_exam_results(): void
    {
        [$chair] = $this->seedFixture();
        $studentUser = User::factory()->create(['name' => 'PMC Draft Marks Only']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'department_id' => Department::where('code', 'MGT')->firstOrFail()->id,
            'program_id' => Program::where('code', 'PGDM')->firstOrFail()->id,
            'batch_id' => Batch::where('code', 'PGDM-26')->firstOrFail()->id,
            'status' => 'active',
        ]);
        $draftExam = Exam::factory()->create([
            'program_id' => $student->program_id,
            'subject_id' => Subject::where('program_id', $student->program_id)->firstOrFail()->id,
            'published_at' => null,
        ]);
        ExamResult::factory()->create([
            'exam_id' => $draftExam->id,
            'student_id' => $student->id,
            'marks_obtained' => 20,
            'grade' => 'F',
            'is_absent' => true,
        ]);

        $this->actingAs($chair)
            ->post(route('academics.pmc.student-success-v004.refresh'))
            ->assertRedirect();

        $plan = AcademicPmcStudentSuccessPlan::where('student_id', $student->id)->where('risk_type', 'retention_risk')->firstOrFail();
        $this->assertSame(0.0, (float) $plan->signals['average_marks']);
        $this->assertSame(0, (int) $plan->signals['exam_absences']);
        $this->assertNotContains('Average marks below 45', $plan->signals['reasons']);
        $this->assertNotContains('Exam/internal assessment absence', $plan->signals['reasons']);
    }
}
