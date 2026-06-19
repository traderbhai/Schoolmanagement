<?php

namespace Tests\Feature;

use App\Models\OrgReportingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSystemConfigurationAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_non_system_admin_cannot_access_or_update_system_settings_by_direct_route(): void
    {
        $programChair = User::factory()->create();
        $programChair->assignRole($this->role('program_chair'));

        $this->actingAs($programChair)
            ->get(route('admin.settings'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.settings.branding'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->get(route('admin.api-docs'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.settings.update'), [
                'institute_name' => 'Unauthorized Institute',
                'short_name' => 'BAD',
                'email' => 'bad@example.com',
                'phone' => '1234567890',
                'website' => 'https://example.com',
            ])
            ->assertForbidden();
    }

    public function test_non_system_admin_cannot_manage_org_hierarchy_by_direct_route(): void
    {
        $programChair = User::factory()->create();
        $programChair->assignRole($this->role('program_chair'));
        $line = OrgReportingLine::where('parent_role', 'dean_academics')
            ->where('child_role', 'program_chair')
            ->firstOrFail();

        $this->actingAs($programChair)
            ->get(route('admin.org-hierarchy.index'))
            ->assertForbidden();

        $this->actingAs($programChair)
            ->post(route('admin.org-hierarchy.store'), [
                'parent_role' => 'program_chair',
                'child_role' => 'exam_cell',
                'can_view_summary' => '1',
                'can_view_full' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->patch(route('admin.org-hierarchy.update', $line), [
                'can_view_summary' => '0',
                'can_view_full' => '0',
            ])
            ->assertForbidden();

        $this->actingAs($programChair)
            ->delete(route('admin.org-hierarchy.destroy', $line))
            ->assertForbidden();

        $line->refresh();
        $this->assertTrue($line->is_active);
        $this->assertTrue($line->can_view_summary);
        $this->assertTrue($line->can_view_full);
        $this->assertNull($line->revoked_by);
        $this->assertNull($line->revoked_at);
        $this->assertFalse(OrgReportingLine::where('parent_role', 'program_chair')
            ->where('child_role', 'exam_cell')
            ->exists());
    }
}
