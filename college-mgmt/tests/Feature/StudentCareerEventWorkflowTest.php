<?php

namespace Tests\Feature;

use App\Models\CareerEvent;
use App\Models\CareerEventRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCareerEventWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function organizer(): User
    {
        Role::firstOrCreate(['name' => 'placement_officer', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('placement_officer');

        return $user;
    }

    private function cmcUser(): User
    {
        Role::firstOrCreate(['name' => 'cmc', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('cmc');

        return $user;
    }

    private function event(array $overrides = []): CareerEvent
    {
        return CareerEvent::create(array_merge([
            'title' => 'Career Readiness Workshop',
            'event_type' => 'workshop',
            'organizer_id' => $this->organizer()->id,
            'event_date' => today()->addDays(3)->toDateString(),
            'venue' => 'Auditorium',
            'description' => 'Resume and interview preparation.',
            'seats' => 30,
            'registration_deadline' => today()->addDay()->toDateString(),
            'is_published' => true,
        ], $overrides));
    }

    public function test_student_can_register_for_open_published_career_event(): void
    {
        $student = $this->student();
        $event = $this->event();

        $this->actingAs($student->user)
            ->post(route('student.career-events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('success', 'Registered for "Career Readiness Workshop".');

        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_can_cancel_open_unattended_career_event_registration(): void
    {
        $student = $this->student();
        $event = $this->event();
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($student->user)
            ->delete(route('student.career-events.cancel', $event))
            ->assertRedirect()
            ->assertSessionHas('success', 'Registration cancelled for "Career Readiness Workshop".');

        $this->assertDatabaseMissing('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_cannot_cancel_attended_or_closed_career_event_registration(): void
    {
        $student = $this->student();
        $attended = $this->event(['title' => 'Attended Workshop']);
        CareerEventRegistration::create([
            'career_event_id' => $attended->id,
            'student_id' => $student->id,
            'attended' => true,
        ]);

        $closed = $this->event([
            'title' => 'Closed Workshop',
            'event_date' => today()->addDays(3)->toDateString(),
            'registration_deadline' => today()->subDay()->toDateString(),
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $closed->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($student->user)
            ->delete(route('student.career-events.cancel', $attended))
            ->assertStatus(422);

        $this->actingAs($student->user)
            ->delete(route('student.career-events.cancel', $closed))
            ->assertStatus(422);

        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $attended->id,
            'student_id' => $student->id,
            'attended' => true,
        ]);
        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $closed->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);
    }

    public function test_registration_deadline_today_is_still_open(): void
    {
        $student = $this->student();
        $event = $this->event([
            'registration_deadline' => today()->toDateString(),
        ]);

        $this->actingAs($student->user)
            ->post(route('student.career-events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_cannot_register_for_past_unpublished_or_full_career_events(): void
    {
        $student = $this->student();
        $past = $this->event([
            'title' => 'Past Career Fair',
            'event_date' => today()->subDay()->toDateString(),
            'registration_deadline' => today()->addDay()->toDateString(),
        ]);
        $unpublished = $this->event([
            'title' => 'Draft Company Visit',
            'is_published' => false,
        ]);
        $full = $this->event([
            'title' => 'Full Mock Interview',
            'seats' => 1,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $full->id,
            'student_id' => Student::factory()->create(['status' => 'active'])->id,
            'attended' => false,
        ]);

        foreach ([$past, $unpublished, $full] as $event) {
            $this->actingAs($student->user)
                ->post(route('student.career-events.register', $event))
                ->assertStatus(422);
        }

        $this->assertDatabaseMissing('career_event_registrations', [
            'student_id' => $student->id,
        ]);
    }

    public function test_student_event_list_only_shows_published_upcoming_events_and_registration_status(): void
    {
        $student = $this->student();
        $visible = $this->event(['title' => 'Visible Workshop']);
        $this->event([
            'title' => 'Draft Hidden Event',
            'is_published' => false,
        ]);
        $this->event([
            'title' => 'Past Published Event',
            'event_date' => today()->subDays(2)->toDateString(),
            'registration_deadline' => today()->subDays(3)->toDateString(),
        ]);
        $this->event([
            'title' => 'Past Draft Hidden Event',
            'event_date' => today()->subDays(2)->toDateString(),
            'registration_deadline' => today()->subDays(3)->toDateString(),
            'is_published' => false,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $visible->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.career-events.index'))
            ->assertStatus(200)
            ->assertSee('Visible Workshop')
            ->assertSee('Registered')
            ->assertSee('Past Published Event')
            ->assertDontSee('Draft Hidden Event')
            ->assertDontSee('Past Draft Hidden Event');
    }

    public function test_cmc_can_mark_career_event_attendance_from_registration_list(): void
    {
        $student = $this->student();
        $event = $this->event();
        $registration = CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.events.registrations', $event))
            ->assertStatus(200)
            ->assertSee('Mark attended')
            ->assertSee('1 pending attendance');

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.events.registrations.attendance', [$event, $registration]), [
                'attended' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Event attendance updated.');

        $this->assertTrue($registration->fresh()->attended);

        $this->actingAs($this->cmcUser())
            ->get(route('cmc.events.registrations', $event))
            ->assertStatus(200)
            ->assertSee('Mark absent')
            ->assertSee('1 attended');
    }

    public function test_cmc_cannot_update_attendance_through_wrong_event_url(): void
    {
        $student = $this->student();
        $event = $this->event(['title' => 'Correct Event']);
        $otherEvent = $this->event(['title' => 'Wrong Event']);
        $registration = CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.events.registrations.attendance', [$otherEvent, $registration]), [
                'attended' => true,
            ])
            ->assertNotFound();

        $this->assertFalse($registration->fresh()->attended);
    }

    public function test_cmc_event_forms_use_database_safe_event_types(): void
    {
        $this->actingAs($this->cmcUser())
            ->get(route('cmc.events.create'))
            ->assertStatus(200)
            ->assertSee('Mock Interview')
            ->assertSee('Company Visit')
            ->assertSee('Career Fair')
            ->assertDontSee('job-fair')
            ->assertDontSee('guest-lecture');
    }

    public function test_cmc_can_create_and_filter_career_event_with_canonical_type(): void
    {
        $cmc = $this->cmcUser();

        $this->actingAs($cmc)
            ->post(route('cmc.events.store'), [
                'title' => 'Industry Immersion Visit',
                'event_type' => 'company_visit',
                'event_date' => today()->addWeek()->toDateString(),
                'venue' => 'Innovation Park',
                'description' => 'Visit to industry partner campus.',
                'seats' => 45,
                'registration_deadline' => today()->addDays(3)->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events'))
            ->assertSessionHas('success', 'Event created.');

        $this->assertDatabaseHas('career_events', [
            'title' => 'Industry Immersion Visit',
            'event_type' => 'company_visit',
            'organizer_id' => $cmc->id,
            'is_published' => true,
        ]);

        $this->actingAs($cmc)
            ->get(route('cmc.events', ['type' => 'company_visit']))
            ->assertStatus(200)
            ->assertSee('Industry Immersion Visit')
            ->assertSee('Company Visit');
    }

    public function test_cmc_cannot_create_career_event_with_legacy_invalid_type_value(): void
    {
        $this->actingAs($this->cmcUser())
            ->from(route('cmc.events.create'))
            ->post(route('cmc.events.store'), [
                'title' => 'Legacy Invalid Event',
                'event_type' => 'job-fair',
                'event_date' => today()->addWeek()->toDateString(),
                'venue' => 'Auditorium',
            ])
            ->assertRedirect(route('cmc.events.create'))
            ->assertSessionHasErrors('event_type');

        $this->assertDatabaseMissing('career_events', [
            'title' => 'Legacy Invalid Event',
        ]);
    }

    public function test_cmc_cannot_create_event_with_registration_deadline_after_event_date_or_past_event_date(): void
    {
        $cmc = $this->cmcUser();

        $this->actingAs($cmc)
            ->from(route('cmc.events.create'))
            ->post(route('cmc.events.store'), [
                'title' => 'Invalid Deadline Event',
                'event_type' => 'workshop',
                'event_date' => today()->addDays(2)->toDateString(),
                'registration_deadline' => today()->addDays(3)->toDateString(),
            ])
            ->assertRedirect(route('cmc.events.create'))
            ->assertSessionHasErrors('registration_deadline');

        $this->actingAs($cmc)
            ->from(route('cmc.events.create'))
            ->post(route('cmc.events.store'), [
                'title' => 'Past New Event',
                'event_type' => 'workshop',
                'event_date' => today()->subDay()->toDateString(),
            ])
            ->assertRedirect(route('cmc.events.create'))
            ->assertSessionHasErrors('event_date');

        $this->assertDatabaseMissing('career_events', ['title' => 'Invalid Deadline Event']);
        $this->assertDatabaseMissing('career_events', ['title' => 'Past New Event']);
    }

    public function test_cmc_cannot_reduce_event_capacity_below_existing_registrations(): void
    {
        $cmc = $this->cmcUser();
        $studentOne = $this->student();
        $studentTwo = $this->student();
        $event = $this->event([
            'title' => 'Capacity Protected Event',
            'organizer_id' => $cmc->id,
            'seats' => 5,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $studentOne->id,
            'attended' => false,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $studentTwo->id,
            'attended' => false,
        ]);

        $this->actingAs($cmc)
            ->from(route('cmc.events.edit', $event))
            ->put(route('cmc.events.update', $event), [
                'title' => 'Capacity Protected Event',
                'event_type' => 'workshop',
                'event_date' => today()->addDays(3)->toDateString(),
                'venue' => 'Auditorium',
                'seats' => 1,
                'registration_deadline' => today()->addDay()->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events.edit', $event))
            ->assertSessionHasErrors('seats');

        $this->assertSame(5, $event->fresh()->seats);
    }

    public function test_cmc_can_update_event_capacity_when_it_still_covers_registrations(): void
    {
        $cmc = $this->cmcUser();
        $student = $this->student();
        $event = $this->event([
            'title' => 'Capacity Update Event',
            'organizer_id' => $cmc->id,
            'seats' => 5,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($cmc)
            ->put(route('cmc.events.update', $event), [
                'title' => 'Capacity Update Event',
                'event_type' => 'workshop',
                'event_date' => today()->addDays(3)->toDateString(),
                'venue' => 'Auditorium',
                'seats' => 1,
                'registration_deadline' => today()->addDay()->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events'))
            ->assertSessionHas('success', 'Event updated.');

        $this->assertSame(1, $event->fresh()->seats);
    }

    public function test_cmc_can_delete_event_without_registrations(): void
    {
        $cmc = $this->cmcUser();
        $event = $this->event([
            'title' => 'Draft Empty Event',
            'organizer_id' => $cmc->id,
            'is_published' => false,
        ]);

        $this->actingAs($cmc)
            ->delete(route('cmc.events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('success', 'Event deleted.');

        $this->assertDatabaseMissing('career_events', [
            'id' => $event->id,
        ]);
    }

    public function test_cmc_cannot_delete_event_after_students_have_registered(): void
    {
        $student = $this->student();
        $cmc = $this->cmcUser();
        $event = $this->event([
            'title' => 'Registered Event',
            'organizer_id' => $cmc->id,
        ]);
        $registration = CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => true,
        ]);

        $this->actingAs($cmc)
            ->delete(route('cmc.events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot delete a career event after students have registered. Unpublish it instead to preserve registration and attendance history.');

        $this->assertDatabaseHas('career_events', [
            'id' => $event->id,
            'title' => 'Registered Event',
        ]);
        $this->assertDatabaseHas('career_event_registrations', [
            'id' => $registration->id,
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => true,
        ]);

        $this->actingAs($cmc)
            ->get(route('cmc.events'))
            ->assertStatus(200)
            ->assertSee('Registered Event')
            ->assertSee('Registered events cannot be deleted');
    }
}
