<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user);
    }

    public function test_can_view_notifications()
    {
        Notification::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->get('/notifications');

        $response->assertStatus(200);
    }

    public function test_can_view_notification_details()
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get("/notifications/{$notification->id}");

        $response->assertStatus(200);
    }

    public function test_can_mark_notification_as_read()
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id, 'is_read' => false]);

        $this->post("/notifications/{$notification->id}/mark-read");

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_can_mark_all_notifications_as_read()
    {
        Notification::factory()->count(5)->create(['user_id' => $this->user->id, 'is_read' => false]);

        $this->post('/notifications/mark-all-read');

        $unreadCount = $this->user->notifications()->where('is_read', false)->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_can_get_unread_count()
    {
        Notification::factory()->count(3)->create(['user_id' => $this->user->id, 'is_read' => false]);
        Notification::factory()->count(2)->create(['user_id' => $this->user->id, 'is_read' => true]);

        $response = $this->get('/notifications/unread-count');

        $response->assertJson(['unread_count' => 3]);
    }

    public function test_can_delete_notification()
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $this->post("/notifications/{$notification->id}/delete");

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notification_mark_as_read_sets_timestamp()
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id, 'is_read' => false, 'read_at' => null]);

        $notification->markAsRead();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_only_own_notifications_visible()
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $otherUser = User::factory()->create();
        $otherUser->assignRole('student');
        $notification = Notification::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->get("/notifications/{$notification->id}");

        $response->assertStatus(403);
    }
}
