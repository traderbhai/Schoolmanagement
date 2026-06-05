<?php

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_component_can_be_created(): void
    {
        $result = ExamResult::factory()->create();

        $component = AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8.5,
            'max_marks'       => 10,
        ]);

        $this->assertDatabaseHas('assessment_components', [
            'exam_result_id' => $result->id,
            'component_type' => 'ia1',
            'marks_obtained' => 8.5,
        ]);
    }

    public function test_assessment_component_percentage(): void
    {
        $result = ExamResult::factory()->create();

        $component = AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 7.5,
            'max_marks'       => 10,
        ]);

        $this->assertEquals(75.0, $component->percentage);
    }

    public function test_assessment_component_names(): void
    {
        $result = ExamResult::factory()->create();

        $ia1 = AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8,
            'max_marks'       => 10,
        ]);

        $this->assertEquals('Internal Assessment 1 (IA1)', $ia1->component_name);
        $this->assertEquals('IA1', $ia1->component_short);
    }

    public function test_exam_result_has_assessment_components(): void
    {
        $result = ExamResult::factory()->create();

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8,
            'max_marks'       => 10,
        ]);

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia2',
            'marks_obtained'  => 7,
            'max_marks'       => 10,
        ]);

        $this->assertEquals(2, $result->assessmentComponents->count());
    }

    public function test_exam_result_composite_score(): void
    {
        $result = ExamResult::factory()->create();

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8.5,
            'max_marks'       => 10,
        ]);

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia2',
            'marks_obtained'  => 7.5,
            'max_marks'       => 10,
        ]);

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'end_semester',
            'marks_obtained'  => 16.0,
            'max_marks'       => 20,
        ]);

        $composite = $result->calculateCompositeScore();
        $this->assertEquals(32.0, $composite);
    }

    public function test_exam_result_has_all_components(): void
    {
        $result = ExamResult::factory()->create();

        $this->assertFalse($result->hasAllComponents());

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8,
            'max_marks'       => 10,
        ]);

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia2',
            'marks_obtained'  => 7,
            'max_marks'       => 10,
        ]);

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'end_semester',
            'marks_obtained'  => 15,
            'max_marks'       => 20,
        ]);

        $result->refresh();
        $this->assertTrue($result->hasAllComponents());
    }

    public function test_get_component_score(): void
    {
        $result = ExamResult::factory()->create();

        AssessmentComponent::create([
            'exam_result_id'  => $result->id,
            'component_type'  => 'ia1',
            'marks_obtained'  => 8.5,
            'max_marks'       => 10,
        ]);

        $this->assertEquals(8.5, $result->getComponentScore('ia1'));
    }
}
