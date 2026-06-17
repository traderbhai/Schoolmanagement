<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NoticeVisibilityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function notice(array $overrides = []): Notice
    {
        return Notice::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Visible Student Notice',
            'content' => 'Notice body',
            'audience' => 'students',
            'publish_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDay()->toDateString(),
            'is_published' => true,
        ], $overrides));
    }

    public function test_student_notice_pages_hide_future_expired_and_wrong_audience_notices(): void
    {
        $student = $this->userWithRole('student');
        $visible = $this->notice(['title' => 'Visible Student Notice']);
        $future = $this->notice(['title' => 'Future Student Notice', 'publish_date' => now()->addDay()->toDateString()]);
        $expired = $this->notice(['title' => 'Expired Student Notice', 'expiry_date' => now()->subDay()->toDateString()]);
        $adminOnly = $this->notice(['title' => 'Admin Internal Notice', 'audience' => 'admin']);

        $this->actingAs($student)
            ->get(route('student.notices'))
            ->assertOk()
            ->assertSee($visible->title)
            ->assertDontSee($future->title)
            ->assertDontSee($expired->title)
            ->assertDontSee($adminOnly->title);

        $this->actingAs($student)->get(route('student.notices.show', $visible))->assertOk();
        $this->actingAs($student)->get(route('student.notices.show', $future))->assertNotFound();
        $this->actingAs($student)->get(route('student.notices.show', $expired))->assertNotFound();
        $this->actingAs($student)->get(route('student.notices.show', $adminOnly))->assertNotFound();
    }

    public function test_parent_notice_list_uses_same_student_family_visibility_scope(): void
    {
        $parentUser = $this->userWithRole('parent');
        ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);

        $familyNotice = $this->notice(['title' => 'Family Visible Notice', 'audience' => 'students']);
        $allNotice = $this->notice(['title' => 'All Campus Notice', 'audience' => 'all']);
        $teacherNotice = $this->notice(['title' => 'Teacher Internal Notice', 'audience' => 'teachers']);
        $adminNotice = $this->notice(['title' => 'Admin Internal Notice', 'audience' => 'admin']);

        $this->actingAs($parentUser)
            ->get(route('parent.notices'))
            ->assertOk()
            ->assertSee($familyNotice->title)
            ->assertSee($allNotice->title)
            ->assertDontSee($teacherNotice->title)
            ->assertDontSee($adminNotice->title);
    }

    public function test_api_notice_endpoints_apply_active_date_and_audience_visibility(): void
    {
        $student = $this->userWithRole('student');
        $visible = $this->notice(['title' => 'API Visible Student Notice']);
        $future = $this->notice(['title' => 'API Future Student Notice', 'publish_date' => now()->addDay()->toDateString()]);
        $adminOnly = $this->notice(['title' => 'API Admin Internal Notice', 'audience' => 'admin']);

        Sanctum::actingAs($student);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonFragment(['title' => $visible->title])
            ->assertJsonMissing(['title' => $future->title])
            ->assertJsonMissing(['title' => $adminOnly->title]);

        $this->getJson('/api/v1/notices/' . $visible->id)->assertOk();
        $this->getJson('/api/v1/notices/' . $future->id)->assertNotFound();
        $this->getJson('/api/v1/notices/' . $adminOnly->id)->assertNotFound();
    }

    public function test_admin_notice_delete_archives_official_communication_history(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $notice = $this->notice([
            'user_id' => $admin->id,
            'title' => 'Archive Official Notice',
            'audience' => 'students',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.notices.destroy', $notice))
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('success', 'Notice archived. Communication history was preserved.');

        $this->assertSoftDeleted('notices', ['id' => $notice->id]);
        $this->assertSame('Archive Official Notice', Notice::withTrashed()->findOrFail($notice->id)->title);
        $this->actingAs($student)->get(route('student.notices.show', $notice))->assertNotFound();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/notices/' . $notice->id)->assertNotFound();
    }
}
