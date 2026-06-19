<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use App\Jobs\SendBulkNoticeEmail;
use App\Mail\NoticeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
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

    public function test_admin_notice_creation_only_dispatches_student_email_for_current_visible_student_notice(): void
    {
        Bus::fake();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => 'Draft Notice',
                'content' => 'Draft should not email.',
                'audience' => 'students',
                'publish_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => 'Future Student Notice',
                'content' => 'Future should not email today.',
                'audience' => 'students',
                'publish_date' => now()->addDay()->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'));

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => 'Admin Internal Notice',
                'content' => 'Admin-only should not email students.',
                'audience' => 'admin',
                'publish_date' => now()->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'));

        Bus::assertNotDispatched(SendBulkNoticeEmail::class);

        $this->actingAs($admin)
            ->post(route('admin.notices.store'), [
                'title' => 'Current Student Notice',
                'content' => 'Current visible student notice.',
                'audience' => 'students',
                'publish_date' => now()->toDateString(),
                'expiry_date' => now()->addWeek()->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'));

        Bus::assertDispatched(SendBulkNoticeEmail::class, 1);
    }

    public function test_admin_cannot_rewrite_visible_published_notice_contract(): void
    {
        Bus::fake();
        $admin = $this->userWithRole('admin');
        $notice = $this->notice([
            'user_id' => $admin->id,
            'title' => 'Published Fee Notice',
            'content' => 'Original published content.',
            'audience' => 'students',
            'publish_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addWeek()->toDateString(),
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => 'Changed Fee Notice',
                'content' => 'Changed content.',
                'audience' => 'admin',
                'publish_date' => now()->toDateString(),
                'expiry_date' => now()->addWeeks(2)->toDateString(),
                'is_published' => '1',
            ])
            ->assertSessionHasErrors('notice');

        $notice->refresh();
        $this->assertSame('Published Fee Notice', $notice->title);
        $this->assertSame('Original published content.', $notice->content);
        $this->assertSame('students', $notice->audience);

        $this->actingAs($admin)
            ->put(route('admin.notices.update', $notice), [
                'title' => 'Published Fee Notice',
                'content' => 'Original published content.',
                'audience' => 'students',
                'publish_date' => $notice->publish_date->toDateString(),
                'expiry_date' => now()->addWeeks(2)->toDateString(),
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.notices.index'))
            ->assertSessionHas('success', 'Notice updated.');

        $this->assertSame(now()->addWeeks(2)->toDateString(), $notice->fresh()->expiry_date->toDateString());
    }

    public function test_bulk_notice_email_job_revalidates_notice_visibility_before_sending(): void
    {
        Mail::fake();
        $studentUser = $this->userWithRole('student');
        Student::factory()->create([
            'user_id' => $studentUser->id,
            'status' => 'active',
        ]);

        $archivedNotice = $this->notice([
            'title' => 'Archived Queued Notice',
            'audience' => 'students',
            'is_published' => true,
        ]);
        $archivedNotice->delete();

        (new SendBulkNoticeEmail($archivedNotice))->handle();

        Mail::assertNothingQueued();

        $internalNotice = $this->notice([
            'title' => 'Internal Queued Notice',
            'audience' => 'admin',
            'is_published' => true,
        ]);

        (new SendBulkNoticeEmail($internalNotice))->handle();

        Mail::assertNothingQueued();
    }

    public function test_bulk_notice_email_job_still_sends_current_visible_student_notice(): void
    {
        Mail::fake();
        $studentUser = $this->userWithRole('student');
        Student::factory()->create([
            'user_id' => $studentUser->id,
            'status' => 'active',
        ]);

        $notice = $this->notice([
            'title' => 'Visible Queued Student Notice',
            'audience' => 'students',
            'publish_date' => now()->subDay()->toDateString(),
            'expiry_date' => now()->addDay()->toDateString(),
            'is_published' => true,
        ]);

        (new SendBulkNoticeEmail($notice))->handle();

        Mail::assertQueued(NoticeMail::class, 1);
    }
}
