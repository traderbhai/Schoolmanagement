<?php

namespace Tests\Feature;

use App\Models\AcademicCalendar;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_can_view_academic_calendar()
    {
        AcademicCalendar::factory()->count(5)->create();

        $response = $this->get('/academic/academic-calendars');

        $response->assertStatus(200);
    }

    public function test_can_create_calendar_event()
    {
        $term = Term::factory()->create();

        $data = [
            'term_id' => $term->id,
            'event_date' => now()->addMonth(),
            'event_name' => 'Semester Start',
            'event_type' => 'semester_start',
            'is_holiday' => false,
        ];

        $response = $this->post('/academic/academic-calendars', $data);

        $this->assertDatabaseHas('academic_calendars', ['event_name' => 'Semester Start']);
    }

    public function test_can_view_calendar_event_details()
    {
        $event = AcademicCalendar::factory()->create();

        $response = $this->get("/academic/academic-calendars/{$event->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_calendar_event()
    {
        $event = AcademicCalendar::factory()->create();

        $data = [
            'event_date' => now()->addMonth(),
            'event_name' => 'Updated Event',
            'event_type' => 'exam_week',
            'is_holiday' => false,
        ];

        $response = $this->put("/academic/academic-calendars/{$event->id}", $data);

        $this->assertEquals('Updated Event', $event->fresh()->event_name);
    }

    public function test_can_delete_calendar_event()
    {
        $event = AcademicCalendar::factory()->create();

        $response = $this->delete("/academic/academic-calendars/{$event->id}");

        $this->assertDatabaseMissing('academic_calendars', ['id' => $event->id]);
    }

    public function test_holiday_event_type()
    {
        $event = AcademicCalendar::factory()->create([
            'event_type' => 'holiday',
            'is_holiday' => true,
        ]);

        $this->assertTrue($event->isHoliday());
    }

    public function test_exam_week_event_type()
    {
        $event = AcademicCalendar::factory()->create([
            'event_type' => 'exam_week',
        ]);

        $this->assertTrue($event->isExamWeek());
    }

    public function test_past_event_detection()
    {
        $event = AcademicCalendar::factory()->create([
            'event_date' => now()->subDay(),
        ]);

        $this->assertTrue($event->isPast());
    }

    public function test_upcoming_event_detection()
    {
        $event = AcademicCalendar::factory()->create([
            'event_date' => now()->addDay(),
        ]);

        $this->assertTrue($event->isUpcoming());
    }

    public function test_can_filter_by_term()
    {
        $term1 = Term::factory()->create();
        $term2 = Term::factory()->create();

        AcademicCalendar::factory()->count(3)->create(['term_id' => $term1->id]);
        AcademicCalendar::factory()->count(2)->create(['term_id' => $term2->id]);

        $response = $this->get("/academic/academic-calendars?term_id={$term1->id}");

        $response->assertStatus(200);
    }
}
