<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentNotificationPreferenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Notification Student']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id,
            'status' => 'active',
        ]);
    }

    public function test_student_notification_settings_use_portal_shell_and_explain_email_scope(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.notifications.edit'))
            ->assertOk()
            ->assertSee('Notification Settings')
            ->assertSee('Choose the email updates you want to receive')
            ->assertSee('These settings control email alerts only')
            ->assertSee('Your in-app notification inbox still shows official messages')
            ->assertSee(route('notifications.index'), false)
            ->assertSee(route('student.notices'), false)
            ->assertSee('Admission and application updates')
            ->assertSee('Payment confirmations and reminders')
            ->assertSee('Exam result publication')
            ->assertSee('Notices and announcements')
            ->assertDontSee('max-w-xl')
            ->assertDontSee('text-gray-800')
            ->assertDontSee('space-y-4');
    }

    public function test_student_can_update_notification_preferences(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->put(route('student.notifications.update'), [
                'email_payment_updates' => '1',
                'email_notices' => '1',
            ])
            ->assertRedirect();

        $pref = NotificationPreference::where('user_id', $student->user_id)->firstOrFail();

        $this->assertFalse($pref->email_application_updates);
        $this->assertTrue($pref->email_payment_updates);
        $this->assertFalse($pref->email_result_published);
        $this->assertTrue($pref->email_notices);
    }
}
