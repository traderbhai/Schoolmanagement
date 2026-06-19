<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OrgReportingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrgHierarchyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_reporting_line_removal_preserves_history_and_removes_active_visibility(): void
    {
        $admin = $this->admin();
        $line = OrgReportingLine::where('parent_role', 'dean_academics')
            ->where('child_role', 'program_chair')
            ->firstOrFail();

        $this->assertTrue(OrgReportingLine::canView('dean_academics', 'program_chair'));
        $this->assertTrue(OrgReportingLine::canView('dean_academics', 'program_chair', true));

        $this->actingAs($admin)
            ->delete(route('admin.org-hierarchy.destroy', $line))
            ->assertRedirect()
            ->assertSessionHas('success', 'Reporting line removed.');

        $line->refresh();
        $this->assertFalse($line->is_active);
        $this->assertSame($admin->id, $line->revoked_by);
        $this->assertNotNull($line->revoked_at);
        $this->assertFalse(OrgReportingLine::canView('dean_academics', 'program_chair'));
        $this->assertFalse(OrgReportingLine::canView('dean_academics', 'program_chair', true));
        $this->assertDatabaseHas('org_reporting_lines', ['id' => $line->id, 'is_active' => false]);
        $this->assertTrue(AuditLog::where('action', 'org_reporting_line_revoked')->where('target_id', $line->id)->exists());
    }

    public function test_readding_removed_reporting_line_reactivates_existing_row(): void
    {
        $admin = $this->admin();
        $line = OrgReportingLine::where('parent_role', 'director')
            ->where('child_role', 'exam_cell')
            ->firstOrFail();
        $line->update([
            'is_active' => false,
            'revoked_by' => $admin->id,
            'revoked_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.org-hierarchy.store'), [
                'parent_role' => 'director',
                'child_role' => 'exam_cell',
                'can_view_summary' => '1',
                'can_view_full' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Reporting line added.');

        $line->refresh();
        $this->assertTrue($line->is_active);
        $this->assertTrue($line->can_view_summary);
        $this->assertTrue($line->can_view_full);
        $this->assertNull($line->revoked_by);
        $this->assertNull($line->revoked_at);
        $this->assertSame(1, OrgReportingLine::where('parent_role', 'director')->where('child_role', 'exam_cell')->count());
        $this->assertTrue(OrgReportingLine::canView('director', 'exam_cell', true));
    }
}
