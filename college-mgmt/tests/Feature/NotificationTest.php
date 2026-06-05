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

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_can_view_notifications()
    {
        $user = auth()->user();
        Notification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->get('/notifications');

        $response->assertStatus(200);
    }

    public function test_can_view_notification_details()
    {
        $user = auth()->user();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->get("/notifications/{$notification->id}");

        $response->assertStatus(200);
    }

    public function test_can_mark_notification_as_read()
    {
        $user = auth()->user();
        $notification = Notification::factory()->create(['user_id' => $user->id, 'is_read' => false]);

        $response = $this->post("/notifications/{$notification->id}/mark-read");

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_can_mark_all_notifications_as_read()
    {
        $user = auth()->user();
        Notification::factory()->count(5)->create(['user_id' => $user->id, 'is_read' => false]);

        $response = $this->post('/notifications/mark-all-read');

        $unreadCount = $user->notifications()->where('is_read', false)->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_can_get_unread_count()
    {
        $user = auth()->user();
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'is_read' => false]);
        Notification::factory()->count(2)->create(['user_id' => $user->id, 'is_read' => true]);

        $response = $this->get('/notifications/unread-count');

        $response->assertJson(['unread_count' => 3]);
    }

    public function test_can_delete_notification()
    {
        $user = auth()->user();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->post("/notifications/{$notification->id}/delete");

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notification_mark_as_read_sets_timestamp()
    {
        $user = auth()->user();
        $notification = Notification::factory()->create(['user_id' => $user->id, 'is_read' => false, 'read_at' => null]);

        $notification->markAsRead();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_only_own_notifications_visible()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);
        $response = $this->get("/notifications/{$notification->id}");

        $response->assertStatus(403);
    }
}
