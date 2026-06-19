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
        $event = AcademicCalendar::factory()->create([
            'event_date' => now()->addWeek()->toDateString(),
        ]);

        $data = [
            'event_date' => now()->addMonth(),
            'event_name' => 'Updated Event',
            'event_type' => 'exam_week',
            'is_holiday' => false,
        ];

        $this->put("/academic/academic-calendars/{$event->id}", $data)
            ->assertRedirect(route('academic.academic-calendars.show', $event));

        $this->assertEquals('Updated Event', $event->fresh()->event_name);
    }

    public function test_can_archive_future_calendar_event()
    {
        $event = AcademicCalendar::factory()->create([
            'event_date' => now()->addMonth()->toDateString(),
        ]);

        $this->delete("/academic/academic-calendars/{$event->id}")
            ->assertRedirect('/academic/academic-calendars')
            ->assertSessionHas('success', 'Calendar event archived successfully');

        $this->assertSoftDeleted('academic_calendars', ['id' => $event->id]);
    }

    public function test_past_calendar_event_cannot_be_edited_or_deleted(): void
    {
        $event = AcademicCalendar::factory()->create([
            'event_date' => now()->subDay()->toDateString(),
            'event_name' => 'Completed Exam Week',
            'event_type' => 'exam_week',
        ]);

        $this->put("/academic/academic-calendars/{$event->id}", [
            'event_date' => now()->addMonth()->toDateString(),
            'event_name' => 'Rewritten Completed Event',
            'event_type' => 'holiday',
            'is_holiday' => true,
        ])->assertSessionHas('error', 'Past academic calendar events are locked for academic history. Add a new revision event instead of editing history.');

        $this->delete("/academic/academic-calendars/{$event->id}")
            ->assertSessionHas('error', 'Past academic calendar events are locked for academic history and cannot be deleted.');

        $event->refresh();
        $this->assertSame('Completed Exam Week', $event->event_name);
        $this->assertSame('exam_week', $event->event_type);
        $this->assertNull($event->deleted_at);
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
