<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\RoleFeatureAccess;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleFeatureAccessSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_seeder_seeds_admin_features(): void
    {
        $this->seed(\Database\Seeders\RoleFeatureAccessSeeder::class);

        $admin = Role::where('name', 'admin')->first();
        $this->assertNotNull($admin, 'admin role must exist after seeding');

        $count = RoleFeatureAccess::where('role_id', $admin->id)->count();
        $this->assertGreaterThanOrEqual(5, $count, 'admin should have at least 5 feature entries');
    }

    public function test_exam_cell_can_enter_marks(): void
    {
        $this->seed(\Database\Seeders\RoleFeatureAccessSeeder::class);

        $role = Role::where('name', 'exam_cell')->first();
        if (!$role) $this->markTestSkipped('exam_cell role not seeded');

        $access = RoleFeatureAccess::where('role_id', $role->id)
            ->where('feature_code', 'exam.enter_marks')
            ->first();

        $this->assertNotNull($access);
        $this->assertContains($access->access_level, ['edit', 'approve', 'delete']);
    }

    public function test_student_cannot_manage_roles(): void
    {
        $this->seed(\Database\Seeders\RoleFeatureAccessSeeder::class);

        $role = Role::where('name', 'student')->first();
        if (!$role) $this->markTestSkipped('student role not seeded');

        $access = RoleFeatureAccess::where('role_id', $role->id)
            ->where('feature_code', 'user.manage_roles')
            ->first();

        $this->assertNull($access);
    }
}
