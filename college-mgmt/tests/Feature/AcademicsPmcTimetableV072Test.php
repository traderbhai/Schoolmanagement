<?php

namespace Tests\Feature;

use App\Models\AcademicPmcTimetableNotification;
use App\Models\User;
use Database\Seeders\AcademicsOperatingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicsPmcTimetableV072Test extends TestCase
{
    use RefreshDatabase;

    public function test_timetable_notification_report_shows_publish_impact_context_and_filters(): void
    {
        $this->seed(AcademicsOperatingDemoSeeder::class);
        $chair = User::where('email', 'chair@college.com')->firstOrFail();

        AcademicPmcTimetableNotification::create([
            'notification_type' => 'publish',
            'recipient_type' => 'students',
            'title' => 'V072 Published timetable notice',
            'message' => 'Timetable published.',
            'status' => 'queued',
            'source_type' => 'timetable_version',
            'source_key' => '720',
            'metadata' => [
                'version' => 'PMC OS v0.071',
                'generation_run_id' => 720,
                'audience_count' => 48,
                'quality_score' => 91,
                'hard_conflicts' => 0,
                'soft_warnings' => 2,
                'operational_entries_synced' => 18,
                'impact_preview' => [
                    'affected_students' => 48,
                    'affected_faculty' => 6,
                    'affected_rooms' => 4,
                    'affected_groups' => 8,
                ],
            ],
        ]);
        AcademicPmcTimetableNotification::create([
            'notification_type' => 'freeze',
            'recipient_type' => 'faculty',
            'title' => 'V072 Freeze notice should be filtered',
            'status' => 'queued',
        ]);

        $this->actingAs($chair)
            ->get(route('academics.pmc.timetable-reports.index', ['notification_type' => 'publish', 'recipient_type' => 'students']))
            ->assertOk()
            ->assertSee('Visible filter summary: type=publish | recipient=students')
            ->assertSee('V072 Published timetable notice')
            ->assertSee('48 recipient')
            ->assertSee('Students 48 | Faculty 6')
            ->assertSee('Rooms 4 | Groups 8')
            ->assertSee('Synced 18 | Run #720')
            ->assertSee('91%')
            ->assertDontSee('V072 Freeze notice should be filtered');
    }
}
