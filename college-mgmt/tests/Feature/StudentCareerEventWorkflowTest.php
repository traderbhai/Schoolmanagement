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

        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'cancelled',
            'attended' => false,
        ]);
        $this->assertNotNull(CareerEventRegistration::where('career_event_id', $event->id)->where('student_id', $student->id)->firstOrFail()->cancelled_at);
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

    public function test_cancelled_career_event_registration_does_not_consume_capacity_and_can_be_reactivated(): void
    {
        $student = $this->student();
        $event = $this->event([
            'title' => 'Capacity Reopened Workshop',
            'seats' => 1,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $student->user_id,
        ]);

        $this->actingAs($student->user)
            ->post(route('student.career-events.register', $event))
            ->assertRedirect()
            ->assertSessionHas('success', 'Registered for "Capacity Reopened Workshop".');

        $this->assertSame(1, CareerEventRegistration::where('career_event_id', $event->id)->where('student_id', $student->id)->count());
        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered',
            'attended' => false,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);
    }

    public function test_student_cannot_reregister_and_reset_attended_career_event_registration(): void
    {
        $student = $this->student();
        $event = $this->event(['title' => 'Attendance Locked Workshop']);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => true,
            'status' => 'registered',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.career-events.register', $event))
            ->assertStatus(422);

        $registration = CareerEventRegistration::where('career_event_id', $event->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $this->assertTrue($registration->attended);
        $this->assertSame('registered', $registration->status);
    }

    public function test_inactive_student_can_view_events_but_cannot_register_or_cancel(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
        $event = $this->event(['title' => 'Read Only Career Workshop']);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $newEvent = $this->event(['title' => 'Inactive Student Cannot Register Event']);

        $this->actingAs($student->user)
            ->get(route('student.career-events.index'))
            ->assertOk()
            ->assertSee('Read Only Career Workshop')
            ->assertSee('Inactive Student Cannot Register Event')
            ->assertSee('Registered')
            ->assertSee('Active students only')
            ->assertDontSee('Register</button>', false)
            ->assertDontSee('Cancel</button>', false);

        $this->actingAs($student->user)
            ->post(route('student.career-events.register', $newEvent))
            ->assertStatus(422);

        $this->actingAs($student->user)
            ->delete(route('student.career-events.cancel', $event))
            ->assertStatus(422);

        $this->assertDatabaseMissing('career_event_registrations', [
            'career_event_id' => $newEvent->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('career_event_registrations', [
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
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

    public function test_student_career_events_empty_state_explains_cmc_next_step(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.career-events.index'))
            ->assertOk()
            ->assertSee('No upcoming career events are published right now')
            ->assertSee('CMC publishes workshops, mock interviews, company visits, and career fairs')
            ->assertSee('your registered')
            ->assertSee('attended events will appear here once available')
            ->assertDontSee('No upcoming career events.')
            ->assertDontSee('SERVICE ERROR');
    }

    public function test_cmc_can_mark_career_event_attendance_from_registration_list(): void
    {
        $student = $this->student();
        $event = $this->event(['event_date' => today()->toDateString()]);
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
            ->assertSee('Attendance locked')
            ->assertSee('1 attended')
            ->assertDontSee('Mark absent');

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.events.registrations.attendance', [$event, $registration]), [
                'attended' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Attended event records are locked. Use an audited correction workflow for attendance reversals.');

        $this->assertTrue($registration->fresh()->attended);
    }

    public function test_cmc_cannot_mark_cancelled_career_event_registration_attended(): void
    {
        $student = $this->student();
        $event = $this->event(['event_date' => today()->toDateString()]);
        $registration = CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $student->user_id,
        ]);

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.events.registrations.attendance', [$event, $registration]), [
                'attended' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Cancelled career event registrations cannot be marked attended.');

        $registration->refresh();
        $this->assertFalse($registration->attended);
        $this->assertSame('cancelled', $registration->status);
    }

    public function test_cmc_cannot_mark_attendance_before_career_event_date(): void
    {
        $student = $this->student();
        $event = $this->event(['event_date' => today()->addDays(3)->toDateString()]);
        $registration = CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($this->cmcUser())
            ->patch(route('cmc.events.registrations.attendance', [$event, $registration]), [
                'attended' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('attended');

        $this->assertFalse($registration->fresh()->attended);
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

    public function test_cmc_cannot_rewrite_registered_event_contract_fields(): void
    {
        $cmc = $this->cmcUser();
        $student = $this->student();
        $event = $this->event([
            'title' => 'Registered Contract Event',
            'event_type' => 'workshop',
            'organizer_id' => $cmc->id,
            'event_date' => today()->addDays(5)->toDateString(),
            'registration_deadline' => today()->addDays(2)->toDateString(),
            'seats' => 20,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($cmc)
            ->from(route('cmc.events.edit', $event))
            ->put(route('cmc.events.update', $event), [
                'title' => 'Registered Contract Event',
                'event_type' => 'mock_interview',
                'event_date' => $event->event_date->toDateString(),
                'venue' => 'Auditorium',
                'seats' => 20,
                'registration_deadline' => $event->registration_deadline->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events.edit', $event))
            ->assertSessionHasErrors('career_event');

        $this->actingAs($cmc)
            ->from(route('cmc.events.edit', $event))
            ->put(route('cmc.events.update', $event), [
                'title' => 'Registered Contract Event',
                'event_type' => 'workshop',
                'event_date' => today()->addDays(7)->toDateString(),
                'venue' => 'Auditorium',
                'seats' => 20,
                'registration_deadline' => $event->registration_deadline->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events.edit', $event))
            ->assertSessionHasErrors('career_event');

        $this->actingAs($cmc)
            ->from(route('cmc.events.edit', $event))
            ->put(route('cmc.events.update', $event), [
                'title' => 'Registered Contract Event',
                'event_type' => 'workshop',
                'event_date' => $event->event_date->toDateString(),
                'venue' => 'Auditorium',
                'seats' => 20,
                'registration_deadline' => today()->addDays(4)->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events.edit', $event))
            ->assertSessionHasErrors('career_event');

        $event->refresh();
        $this->assertSame('workshop', $event->event_type);
        $this->assertSame(today()->addDays(5)->toDateString(), $event->event_date->toDateString());
        $this->assertSame(today()->addDays(2)->toDateString(), $event->registration_deadline->toDateString());
    }

    public function test_cmc_can_update_registered_event_safe_descriptive_fields(): void
    {
        $cmc = $this->cmcUser();
        $student = $this->student();
        $event = $this->event([
            'title' => 'Registered Editable Event',
            'organizer_id' => $cmc->id,
            'event_date' => today()->addDays(3)->toDateString(),
            'registration_deadline' => today()->addDay()->toDateString(),
            'venue' => 'Old Venue',
            'seats' => 5,
        ]);
        CareerEventRegistration::create([
            'career_event_id' => $event->id,
            'student_id' => $student->id,
            'attended' => false,
        ]);

        $this->actingAs($cmc)
            ->put(route('cmc.events.update', $event), [
                'title' => 'Registered Editable Event Updated',
                'event_type' => $event->event_type,
                'event_date' => $event->event_date->toDateString(),
                'venue' => 'New Venue',
                'description' => 'Updated room directions.',
                'seats' => 10,
                'registration_deadline' => $event->registration_deadline->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('cmc.events'))
            ->assertSessionHas('success', 'Event updated.');

        $event->refresh();
        $this->assertSame('Registered Editable Event Updated', $event->title);
        $this->assertSame('New Venue', $event->venue);
        $this->assertSame(10, $event->seats);
        $this->assertSame('Updated room directions.', $event->description);
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
