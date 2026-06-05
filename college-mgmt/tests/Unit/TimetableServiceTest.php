<?php

namespace Tests\Unit;

use App\Services\TimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableServiceTest extends TestCase
{
    use RefreshDatabase;

    private TimetableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TimetableService();
    }

    public function test_no_conflicts_returns_empty_array(): void
    {
        // Use a semester_id/day/slot that won't exist in a fresh test DB
        $data = [
            'semester_id'       => 9999,
            'day_of_week'       => 1,
            'timetable_slot_id' => 9999,
            'classroom_id'      => 9999,
            'teacher_id'        => 9999,
            'course_id'         => 9999,
        ];
        $conflicts = $this->service->checkConflicts($data);
        $this->assertSame([], $conflicts);
    }

    public function test_buildWeeklyGrid_returns_array(): void
    {
        $grid = $this->service->buildWeeklyGrid(999);
        $this->assertIsArray($grid);
        foreach (range(1, 6) as $day) {
            $this->assertArrayHasKey($day, $grid);
        }
    }
}
