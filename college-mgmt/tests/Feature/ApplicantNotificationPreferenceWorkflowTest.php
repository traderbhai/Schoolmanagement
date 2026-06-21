<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantNotificationPreferenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): User
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Preference Applicant']);
        $user->assignRole('applicant');

        return $user;
    }

    public function test_applicant_notification_settings_use_portal_shell_and_explain_admission_scope(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)
            ->get(route('applicant.notifications.edit'))
            ->assertOk()
            ->assertSee('Notification Settings')
            ->assertSee('Choose the email updates you want during admission')
            ->assertSee('These settings control email alerts only')
            ->assertSee('Your applicant portal still shows official status')
            ->assertSee(route('applicant.status'), false)
            ->assertSee(route('applicant.checklist'), false)
            ->assertSee('Application status updates')
            ->assertSee('Payment confirmations and reminders')
            ->assertSee('Exam and selection results')
            ->assertSee('Notices and announcements')
            ->assertDontSee('max-w-xl')
            ->assertDontSee('text-gray-800')
            ->assertDontSee('space-y-4');
    }

    public function test_applicant_can_update_notification_preferences(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)
            ->put(route('applicant.notifications.update'), [
                'email_application_updates' => '1',
                'email_result_published' => '1',
            ])
            ->assertRedirect();

        $pref = NotificationPreference::where('user_id', $applicant->id)->firstOrFail();

        $this->assertTrue($pref->email_application_updates);
        $this->assertFalse($pref->email_payment_updates);
        $this->assertTrue($pref->email_result_published);
        $this->assertFalse($pref->email_notices);
    }
}
