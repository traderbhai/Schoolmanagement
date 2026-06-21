<?php

namespace Tests\Feature;

use App\Models\MentorMeeting;
use App\Models\MentorMessage;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentMentorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function studentWithMentor(string $studentStatus = 'active'): array
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $mentor = Teacher::factory()->create(['status' => 'active']);
        $student = Student::factory()->create([
            'status' => $studentStatus,
            'mentor_id' => $mentor->user_id,
        ]);
        $student->user->assignRole('student');

        return compact('student', 'mentor');
    }

    public function test_active_student_can_message_mentor_and_request_meeting(): void
    {
        $fixture = $this->studentWithMentor();

        $this->actingAs($fixture['student']->user)
            ->post(route('student.mentor.message'), [
                'message' => 'I need academic guidance.',
            ])
            ->assertRedirect();

        $this->actingAs($fixture['student']->user)
            ->post(route('student.mentor.meeting'), [
                'meeting_date' => now()->addDays(2)->toDateString(),
                'topic' => 'Academic improvement plan',
                'notes' => 'Discuss attendance and course progress.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mentor_messages', [
            'student_id' => $fixture['student']->id,
            'sender_id' => $fixture['student']->user_id,
            'message' => 'I need academic guidance.',
        ]);
        $this->assertDatabaseHas('mentor_meetings', [
            'student_id' => $fixture['student']->id,
            'mentor_id' => $fixture['mentor']->user_id,
            'topic' => 'Academic improvement plan',
        ]);
    }

    public function test_student_mentor_empty_states_explain_assignment_message_and_meeting_next_steps(): void
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student = Student::factory()->create(['status' => 'active', 'mentor_id' => null]);
        $student->user->assignRole('student');

        $this->actingAs($student->user)
            ->get(route('student.mentor.index'))
            ->assertOk()
            ->assertSeeText('No faculty mentor is assigned yet')
            ->assertSeeText('Your department or program office assigns mentors')
            ->assertDontSeeText('No mentor assigned yet. Contact your department for assignment.');

        $fixture = $this->studentWithMentor();

        $this->actingAs($fixture['student']->user)
            ->get(route('student.mentor.index'))
            ->assertOk()
            ->assertSeeText('No mentor messages are recorded yet')
            ->assertSeeText('Start with a short update about attendance')
            ->assertSeeText('No mentor meetings are scheduled or recorded yet')
            ->assertSeeText('Request a meeting when you need academic guidance')
            ->assertDontSeeText('No messages yet. Start the conversation below.')
            ->assertDontSeeText('No meetings yet.');
    }

    public function test_inactive_student_can_view_mentor_history_but_cannot_create_new_mentor_activity(): void
    {
        $fixture = $this->studentWithMentor('inactive');

        MentorMessage::create([
            'student_id' => $fixture['student']->id,
            'sender_id' => $fixture['mentor']->user_id,
            'message' => 'Historical mentor guidance',
        ]);
        MentorMeeting::create([
            'student_id' => $fixture['student']->id,
            'mentor_id' => $fixture['mentor']->user_id,
            'meeting_date' => now()->addWeek()->toDateString(),
            'topic' => 'Historical mentor meeting',
            'status' => 'scheduled',
        ]);

        $this->actingAs($fixture['student']->user)
            ->get(route('student.mentor.index'))
            ->assertOk()
            ->assertSee('Historical mentor guidance')
            ->assertSee('Historical mentor meeting')
            ->assertSee('Mentor messaging and meeting requests are locked because this student profile is not active.')
            ->assertSee('Mentor replies are locked for inactive student profiles.')
            ->assertSee('Meeting requests are locked for inactive student profiles.')
            ->assertDontSee('Send Request');

        $this->actingAs($fixture['student']->user)
            ->post(route('student.mentor.message'), [
                'message' => 'Inactive student direct message',
            ])
            ->assertForbidden();

        $this->actingAs($fixture['student']->user)
            ->post(route('student.mentor.meeting'), [
                'meeting_date' => now()->addDays(2)->toDateString(),
                'topic' => 'Inactive student meeting',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('mentor_messages', [
            'student_id' => $fixture['student']->id,
            'message' => 'Inactive student direct message',
        ]);
        $this->assertDatabaseMissing('mentor_meetings', [
            'student_id' => $fixture['student']->id,
            'topic' => 'Inactive student meeting',
        ]);
    }
}
