<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherProfileMissingGracefulTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_teacher_pages_do_not_fail_when_teacher_profile_is_missing(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $routes = [
            'teacher.leaves.index',
            'teacher.profile',
            'teacher.timetable.index',
            'teacher.mentor.index',
            'teacher.feedback.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($teacherUser)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Teacher profile not linked');
        }

        foreach ([
            'teacher.attendance.mark' => 'before marking attendance',
            'teacher.materials.create' => 'before uploading study materials',
            'teacher.assignments.create' => 'before creating assignments',
        ] as $route => $expectedText) {
            $this->actingAs($teacherUser)
                ->get(route($route))
                ->assertOk()
                ->assertSee($expectedText);
        }
    }

    public function test_teacher_action_pages_explain_missing_profile_instead_of_redirecting(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $this->actingAs($teacherUser)
            ->get(route('teacher.attendance.mark'))
            ->assertOk()
            ->assertSee('before marking attendance')
            ->assertSee('No Classes Scheduled');

        $this->actingAs($teacherUser)
            ->get(route('teacher.materials.create'))
            ->assertOk()
            ->assertSee('before uploading study materials')
            ->assertSee('Upload Study Material')
            ->assertSee('disabled', false);

        $this->actingAs($teacherUser)
            ->get(route('teacher.assignments.create'))
            ->assertOk()
            ->assertSee('before creating assignments')
            ->assertSee('Create Assignment')
            ->assertSee('disabled', false);
    }

    public function test_teacher_without_profile_cannot_submit_leave(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');

        $this->actingAs($teacherUser)
            ->post(route('teacher.leaves.store'), [
                'leave_type' => 'casual',
                'from_date' => now()->addDay()->toDateString(),
                'to_date' => now()->addDays(2)->toDateString(),
                'reason' => 'Family work',
            ])
            ->assertRedirect(route('teacher.leaves.index'))
            ->assertSessionHas('error');
    }
}
