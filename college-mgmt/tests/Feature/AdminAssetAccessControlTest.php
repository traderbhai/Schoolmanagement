<?php

namespace Tests\Feature;

use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\InstituteAsset;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAssetAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_director_can_open_asset_register(): void
    {
        foreach (['admin', 'director'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.assets.index'))
                ->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_asset_register(): void
    {
        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.assets.index'))
                ->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_assets_or_stock(): void
    {
        $asset = $this->asset();
        $item = $this->inventoryItem();
        $teacher = $this->userWithRole('teacher');
        $admin = $this->userWithRole('admin');
        $assignment = AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);
        $asset->update(['status' => 'assigned']);

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.assets.categories.store'), ['name' => "Blocked {$role}", 'code' => "B{$user->id}"])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.store'), [
                    'asset_category_id' => $asset->asset_category_id,
                    'asset_tag' => "BLOCKED-ASSET-{$user->id}",
                    'name' => "Blocked Asset {$role}",
                    'condition' => 'good',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.stock-items.store'), [
                    'asset_category_id' => $asset->asset_category_id,
                    'name' => "Blocked Stock {$role}",
                    'sku' => "BLOCKED-STOCK-{$user->id}",
                    'unit' => 'box',
                    'current_stock' => 5,
                    'reorder_level' => 1,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.stock-items.receive', $item), [
                    'quantity' => 5,
                    'reference_number' => "BLOCKED-PO-{$user->id}",
                    'movement_date' => now()->toDateString(),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.stock-items.issue', $item), [
                    'quantity' => 1,
                    'issued_to_user_id' => $teacher->id,
                    'movement_date' => now()->toDateString(),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.assign', $asset), [
                    'assigned_to_user_id' => $teacher->id,
                    'assigned_on' => now()->toDateString(),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.assets.assignments.return', $assignment), [
                    'returned_on' => now()->toDateString(),
                    'condition' => 'good',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('asset_categories', ['name' => 'Blocked dean_academics']);
        $this->assertDatabaseMissing('institute_assets', ['asset_tag' => 'BLOCKED-ASSET-1']);
        $this->assertDatabaseMissing('inventory_movements', ['reference_number' => 'BLOCKED-PO-1']);
        $this->assertSame(10, $item->fresh()->current_stock);
        $this->assertSame('assigned', $asset->fresh()->status);
        $this->assertSame('active', $assignment->fresh()->status);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function category(): AssetCategory
    {
        return AssetCategory::create([
            'name' => 'IT Equipment ' . uniqid(),
            'code' => 'IT' . uniqid(),
            'is_active' => true,
        ]);
    }

    private function asset(): InstituteAsset
    {
        return InstituteAsset::create([
            'asset_category_id' => $this->category()->id,
            'asset_tag' => 'IT-LAP-' . uniqid(),
            'name' => 'Dell Laptop',
            'condition' => 'good',
            'status' => 'available',
        ]);
    }

    private function inventoryItem(): InventoryItem
    {
        return InventoryItem::create([
            'asset_category_id' => $this->category()->id,
            'name' => 'A4 Paper',
            'sku' => 'PAPER-' . uniqid(),
            'unit' => 'ream',
            'current_stock' => 10,
            'reorder_level' => 2,
            'status' => 'active',
        ]);
    }
}
