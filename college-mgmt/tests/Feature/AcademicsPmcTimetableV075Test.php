<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableNotification;
use App\Models\DepartmentActivityLog;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV075Test extends TestCase
{
    use RefreshDatabase;

    public function test_pmc_can_retry_failed_notification_with_audited_retry_metadata(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $notification = AcademicPmcTimetableNotification::create([
            'notification_type' => 'publish',
            'recipient_type' => 'student',
            'recipient_user_id' => $chair->id,
            'title' => 'V075 failed notice',
            'message' => 'Publish notice failed.',
            'status' => 'failed',
            'source_type' => 'timetable_version',
            'source_key' => '750',
            'metadata' => [
                'generation_run_id' => 750,
                'quality_score' => 87,
                'retry_count' => 1,
                'status_history' => [
                    ['from' => 'queued', 'to' => 'failed', 'note' => 'Provider timeout.'],
                ],
            ],
        ]);

        $this->actingAs($chair)->post(route('academics.pmc.timetable-notifications.retry', $notification), [
            'retry_note' => 'Retry after provider recovery.',
        ])->assertRedirect();

        $notification->refresh();

        $this->assertSame('queued', $notification->status);
        $this->assertSame(2, $notification->metadata['retry_count']);
        $this->assertSame('Retry after provider recovery.', $notification->metadata['last_retry_note']);
        $this->assertNotEmpty($notification->metadata['next_retry_at']);
        $this->assertSame('failed', $notification->metadata['status_history'][1]['from']);
        $this->assertSame('queued', $notification->metadata['status_history'][1]['to']);
        $this->assertSame('retry', $notification->metadata['status_history'][1]['action']);
        $this->assertTrue(DepartmentActivityLog::where('action', 'academic_pmc_v075_notification_retry_queued')->where('subject_id', $notification->id)->exists());

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-reports.index', ['notification_type' => 'publish', 'recipient_type' => 'student']))
            ->assertOk()
            ->assertSee('V075 failed notice')
            ->assertSee('Retry 2')
            ->assertSee('Retry after provider recovery.');
    }

    public function test_only_failed_or_cancelled_notifications_can_be_retried(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        $notification = AcademicPmcTimetableNotification::create([
            'notification_type' => 'publish',
            'recipient_type' => 'faculty',
            'recipient_user_id' => $chair->id,
            'title' => 'V075 active notice',
            'message' => 'Publish notice queued.',
            'status' => 'queued',
            'source_type' => 'timetable_version',
            'source_key' => '751',
            'metadata' => ['generation_run_id' => 751],
        ]);

        $this->actingAs($chair)
            ->post(route('academics.pmc.timetable-notifications.retry', $notification), ['retry_note' => 'Wrong state.'])
            ->assertStatus(422);

        $notification->refresh();

        $this->assertSame('queued', $notification->status);
        $this->assertArrayNotHasKey('retry_count', $notification->metadata);
        $this->assertFalse(DepartmentActivityLog::where('action', 'academic_pmc_v075_notification_retry_queued')->where('subject_id', $notification->id)->exists());
    }
}
