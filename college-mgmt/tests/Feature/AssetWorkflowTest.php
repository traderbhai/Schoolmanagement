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

class AssetWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'IT Equipment',
            'code' => 'IT',
            'is_active' => true,
        ]);
    }

    private function asset(array $overrides = []): InstituteAsset
    {
        return InstituteAsset::create(array_merge([
            'asset_category_id' => $this->category()->id,
            'asset_tag' => 'IT-LAP-001',
            'name' => 'Dell Laptop',
            'serial_number' => 'SN-001',
            'vendor_name' => 'Dell Partner',
            'purchase_date' => '2026-06-01',
            'purchase_cost' => 65000,
            'location' => 'IT Store',
            'condition' => 'good',
            'status' => 'available',
        ], $overrides));
    }

    private function inventoryItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'asset_category_id' => $this->category()->id,
            'name' => 'A4 Paper Ream',
            'sku' => 'STAT-A4',
            'unit' => 'ream',
            'current_stock' => 10,
            'reorder_level' => 5,
            'location' => 'Admin Store',
            'status' => 'active',
        ], $overrides));
    }

    public function test_admin_can_create_category_and_asset(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.assets.categories.store'), [
                'name' => 'Lab Equipment',
                'code' => 'LAB',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset category created.');

        $category = AssetCategory::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.assets.store'), [
                'asset_category_id' => $category->id,
                'asset_tag' => 'LAB-MIC-001',
                'name' => 'Microscope',
                'serial_number' => 'MIC-123',
                'vendor_name' => 'Science Vendor',
                'purchase_date' => '2026-06-10',
                'purchase_cost' => 12500,
                'location' => 'Physics Lab',
                'condition' => 'new',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset added to register.');

        $this->assertDatabaseHas('institute_assets', [
            'asset_tag' => 'LAB-MIC-001',
            'name' => 'Microscope',
            'status' => 'available',
        ]);
    }

    public function test_admin_can_assign_and_return_asset(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset();

        $this->actingAs($admin)
            ->post(route('admin.assets.assign', $asset), [
                'assigned_to_user_id' => $teacher->id,
                'assigned_on' => '2026-06-13',
                'remarks' => 'Issued with charger.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset assigned successfully.');

        $asset->refresh();
        $this->assertSame('assigned', $asset->status);

        $assignment = AssetAssignment::firstOrFail();
        $this->assertSame('active', $assignment->status);
        $this->assertSame($teacher->id, $assignment->assigned_to_user_id);

        $this->actingAs($admin)
            ->post(route('admin.assets.assignments.return', $assignment), [
                'returned_on' => '2026-06-20',
                'condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset returned successfully.');

        $assignment->refresh();
        $this->assertSame('returned', $assignment->status);
        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_admin_cannot_assign_asset_that_is_not_available(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'maintenance']);

        $this->actingAs($admin)
            ->post(route('admin.assets.assign', $asset), [
                'assigned_to_user_id' => $teacher->id,
                'assigned_on' => '2026-06-13',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only available assets can be assigned.');

        $this->assertSame(0, AssetAssignment::count());
    }

    public function test_asset_register_page_shows_assets_and_assignment_context(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['name' => 'Assigned Teacher']);
        $asset = $this->asset();

        AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => '2026-06-13',
            'status' => 'active',
        ]);
        $asset->update(['status' => 'assigned']);

        $this->actingAs($admin)
            ->get(route('admin.assets.index'))
            ->assertStatus(200)
            ->assertSee('Asset Register')
            ->assertSee('IT-LAP-001')
            ->assertSee('Dell Laptop')
            ->assertSee('Assigned Teacher')
            ->assertSee('Rs. 65,000.00');
    }

    public function test_admin_can_create_inventory_item_and_receive_stock(): void
    {
        $admin = $this->userWithRole('admin');
        $category = $this->category();

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.store'), [
                'asset_category_id' => $category->id,
                'name' => 'Lab Gloves',
                'sku' => 'LAB-GLOVES',
                'unit' => 'box',
                'current_stock' => 3,
                'reorder_level' => 5,
                'location' => 'Chemistry Store',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Inventory item created.');

        $item = InventoryItem::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.receive', $item), [
                'quantity' => 12,
                'vendor_name' => 'Lab Supplier',
                'reference_number' => 'PO-1001',
                'movement_date' => '2026-06-13',
                'remarks' => 'Monthly stock replenishment.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Stock received successfully.');

        $this->assertSame(15, $item->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'receive',
            'quantity' => 12,
            'vendor_name' => 'Lab Supplier',
            'reference_number' => 'PO-1001',
        ]);
    }

    public function test_admin_can_issue_stock_and_cannot_issue_more_than_available(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $item = $this->inventoryItem(['current_stock' => 8, 'reorder_level' => 4]);

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.issue', $item), [
                'quantity' => 3,
                'issued_to_user_id' => $teacher->id,
                'reference_number' => 'REQ-2001',
                'movement_date' => '2026-06-13',
                'remarks' => 'Issued for class handouts.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Stock issued successfully.');

        $this->assertSame(5, $item->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => 3,
            'issued_to_user_id' => $teacher->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.issue', $item), [
                'quantity' => 99,
                'movement_date' => '2026-06-13',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(5, $item->fresh()->current_stock);
    }

    public function test_asset_register_page_shows_low_stock_and_recent_stock_movements(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['name' => 'Stock Receiver']);
        $item = $this->inventoryItem([
            'name' => 'Whiteboard Marker',
            'sku' => 'STAT-MARKER',
            'current_stock' => 2,
            'reorder_level' => 5,
        ]);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => 4,
            'performed_by' => $admin->id,
            'issued_to_user_id' => $teacher->id,
            'reference_number' => 'REQ-3001',
            'movement_date' => '2026-06-13',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.assets.index'))
            ->assertStatus(200)
            ->assertSee('Consumable Stock')
            ->assertSee('Whiteboard Marker')
            ->assertSee('STAT-MARKER')
            ->assertSee('Low Stock')
            ->assertSee('Recent Stock Movements')
            ->assertSee('REQ-3001')
            ->assertSee('Stock Receiver');
    }
}
