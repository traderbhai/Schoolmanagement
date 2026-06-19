<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNoticeFacultyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function notice(): Notice
    {
        return Notice::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Official Academic Notice',
            'content' => 'Original notice body',
            'audience' => 'students',
            'publish_date' => now()->addDay()->toDateString(),
            'expiry_date' => now()->addWeek()->toDateString(),
            'is_published' => false,
        ]);
    }

    public function test_broad_academic_role_cannot_manage_admin_notices_by_direct_route(): void
    {
        $programChair = $this->userWithRole('program_chair');
        $notice = $this->notice();

        $this->actingAs($programChair)->get(route('admin.notices.index'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.notices.create'))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.notices.show', $notice))->assertForbidden();
        $this->actingAs($programChair)->get(route('admin.notices.edit', $notice))->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.notices.store'), [
                'title' => 'Unauthorized Notice',
                'content' => 'Should not be created.',
                'audience' => 'students',
                'publish_date' => now()->toDateString(),
                'is_published' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->put(route('admin.notices.update', $notice), [
                'title' => 'Changed Notice',
                'content' => 'Changed body',
                'audience' => 'admin',
                'publish_date' => now()->toDateString(),
                'is_published' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertForbidden();

        $this->assertDatabaseMissing('notices', ['title' => 'Unauthorized Notice']);
        $this->assertSame('Official Academic Notice', $notice->fresh()->title);
        $this->assertFalse(Notice::withTrashed()->findOrFail($notice->id)->trashed());
    }

    public function test_dean_can_manage_admin_notices_and_view_global_faculty_workload(): void
    {
        $dean = $this->userWithRole('dean_academics');

        $this->actingAs($dean)
            ->get(route('admin.notices.index'))
            ->assertOk();

        $this->actingAs($dean)
            ->get(route('admin.faculty.workload'))
            ->assertOk();
    }

    public function test_broad_academic_role_cannot_view_global_faculty_workload_report(): void
    {
        $programChair = $this->userWithRole('program_chair');

        $this->actingAs($programChair)
            ->get(route('admin.faculty.workload'))
            ->assertForbidden();
    }
}
