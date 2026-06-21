<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedNotificationInboxUxTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_student_notification_inbox_uses_student_shell_and_explains_empty_state(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Student Portal')
            ->assertSee('Notification Inbox')
            ->assertSee('Your institute messages and action alerts')
            ->assertSee('Owner: Your account')
            ->assertSee('Source: workflow notifications, approvals, payments, documents, timetable, and institute messages assigned to you')
            ->assertSee('No notifications match the current inbox filters')
            ->assertSee('Official messages will appear here when an institute office sends an update')
            ->assertSee('Clear Filters')
            ->assertSee('meta name="csrf-token"', false)
            ->assertDontSee('Admin Portal')
            ->assertDontSee('<div class="p-4 text-center text-muted">No notifications</div>', false);
    }

    public function test_applicant_notification_inbox_uses_applicant_shell_and_opens_detail(): void
    {
        $applicant = $this->userWithRole('applicant');
        $notification = Notification::factory()->create([
            'user_id' => $applicant->id,
            'title' => 'Document review update',
            'message' => 'Your admission document has been reviewed.',
            'type' => 'info',
            'is_read' => false,
            'read_at' => null,
            'action_url' => null,
        ]);

        $this->actingAs($applicant)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Applicant Portal')
            ->assertSee('Document review update')
            ->assertSee('1</strong> unread', false)
            ->assertSee('matching messages')
            ->assertSee('Source: Info')
            ->assertDontSee('Includes action link')
            ->assertDontSee('Admin Portal');

        $this->actingAs($applicant)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Applicant Portal')
            ->assertSee('Notification Detail')
            ->assertSee('Owner: Your account')
            ->assertSee('Source: Info')
            ->assertSee('Read status updates when this page opens')
            ->assertSee('Document review update')
            ->assertSee('Back to Inbox')
            ->assertDontSee('Admin Portal');

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_teacher_notification_mark_all_read_uses_portal_csrf_shell(): void
    {
        $teacher = $this->userWithRole('teacher');
        Notification::factory()->count(2)->create([
            'user_id' => $teacher->id,
            'is_read' => false,
            'read_at' => null,
        ]);

        $this->actingAs($teacher)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Teacher Portal')
            ->assertSee('2</strong> unread', false)
            ->assertSee('meta name="csrf-token"', false)
            ->assertSee('Mark All Read')
            ->assertDontSee('Admin Portal');

        $this->actingAs($teacher)
            ->post(route('notifications.mark-all-read'))
            ->assertJson(['success' => true]);

        $this->assertSame(0, $teacher->notifications()->where('is_read', false)->count());
    }

    public function test_notification_inbox_filters_owned_messages_and_explains_no_match_state(): void
    {
        $student = $this->userWithRole('student');
        $other = $this->userWithRole('student');

        Notification::factory()->create([
            'user_id' => $student->id,
            'title' => 'Payment verification pending',
            'message' => 'Please review your payment proof status.',
            'type' => 'payment',
            'is_read' => false,
            'read_at' => null,
        ]);
        Notification::factory()->create([
            'user_id' => $student->id,
            'title' => 'Timetable published',
            'message' => 'Your timetable is ready.',
            'type' => 'timetable',
            'is_read' => true,
            'read_at' => now(),
        ]);
        Notification::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other student payment alert',
            'message' => 'This must not appear.',
            'type' => 'payment',
            'is_read' => false,
            'read_at' => null,
        ]);

        $this->actingAs($student)
            ->get(route('notifications.index', ['status' => 'unread', 'type' => 'payment', 'search' => 'Payment']))
            ->assertOk()
            ->assertSee('Showing 1 notification(s)')
            ->assertSee('with status "unread"', false)
            ->assertSee('and type "payment"', false)
            ->assertSee('Payment verification pending')
            ->assertSee('Source: Payment')
            ->assertDontSee('Timetable published')
            ->assertDontSee('Other student payment alert')
            ->assertDontSee('N/A')
            ->assertDontSee('â', false);

        $this->actingAs($student)
            ->get(route('notifications.index', ['search' => 'no-matching-message']))
            ->assertOk()
            ->assertSee('No notifications match the current inbox filters')
            ->assertSee('Clear filters to review older read messages');
    }
}
