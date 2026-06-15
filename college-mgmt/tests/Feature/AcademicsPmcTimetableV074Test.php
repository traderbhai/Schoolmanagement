<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableNotification;
use App\Models\DepartmentActivityLog;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV074Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_can_update_notification_delivery_status_with_audited_history(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $notification = AcademicPmcTimetableNotification::create([
            'notification_type' => 'publish',
            'recipient_type' => 'student',
            'recipient_user_id' => $chair->id,
            'title' => 'V074 recipient notice',
            'message' => 'Publish notice.',
            'status' => 'queued',
            'source_type' => 'timetable_version',
            'source_key' => '740',
            'metadata' => ['generation_run_id' => 740, 'quality_score' => 88],
        ]);

        $this->actingAs($chair)->patch(route('academics.pmc.timetable-notifications.update-status', $notification), [
            'status' => 'failed',
        ])->assertStatus(422);

        $this->actingAs($chair)->patch(route('academics.pmc.timetable-notifications.update-status', $notification), [
            'status' => 'sent',
            'status_note' => 'Delivered through campus app.',
        ])->assertRedirect();

        $notification->refresh();
        $this->assertSame('sent', $notification->status);
        $this->assertSame('Delivered through campus app.', $notification->metadata['latest_status_note']);
        $this->assertSame('queued', $notification->metadata['status_history'][0]['from']);
        $this->assertSame('sent', $notification->metadata['status_history'][0]['to']);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v074_notification_status_updated')->where('subject_id', $notification->id)->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-reports.index', ['notification_type' => 'publish', 'recipient_type' => 'student']))
            ->assertOk()
            ->assertSee('Delivery')
            ->assertSee('V074 recipient notice')
            ->assertSee('Delivered through campus app.')
            ->assertSee('Update');
    }
}
