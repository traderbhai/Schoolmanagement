<?php

namespace Tests\Unit;

use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private GradeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradeService();
    }

    public function test_grade_O_for_90_percent(): void
    {
        $grade = $this->service->getGrade(90);
        $this->assertEquals('O', $grade['letter']);
        $this->assertEquals(10.0, $grade['points']);
    }

    public function test_grade_F_for_below_35(): void
    {
        $grade = $this->service->getGrade(34);
        $this->assertEquals('F', $grade['letter']);
        $this->assertEquals(0.0, $grade['points']);
    }

    public function test_grade_A_for_70_percent(): void
    {
        $grade = $this->service->getGrade(70);
        $this->assertEquals('A', $grade['letter']);
        $this->assertEquals(8.0, $grade['points']);
    }

    public function test_grade_B_for_50_percent(): void
    {
        $grade = $this->service->getGrade(50);
        $this->assertEquals('B', $grade['letter']);
        $this->assertEquals(6.0, $grade['points']);
    }

    public function test_calculateCGPA_returns_float(): void
    {
        $cgpa = $this->service->calculateCGPA(999);
        $this->assertSame(0.0, $cgpa);
    }
}
