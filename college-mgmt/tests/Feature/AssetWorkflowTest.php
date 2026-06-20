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

    public function test_asset_and_stock_item_creation_require_active_category(): void
    {
        $admin = $this->userWithRole('admin');
        $inactiveCategory = AssetCategory::create([
            'name' => 'Archived Equipment',
            'code' => 'ARCH',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.assets.index'))
            ->post(route('admin.assets.store'), [
                'asset_category_id' => $inactiveCategory->id,
                'asset_tag' => 'ARCH-ASSET-001',
                'name' => 'Archived Category Asset',
                'serial_number' => 'ARCH-SN-001',
                'purchase_date' => '2026-06-10',
                'purchase_cost' => 1000,
                'location' => 'Store',
                'condition' => 'good',
            ])
            ->assertRedirect(route('admin.assets.index'))
            ->assertSessionHasErrors('asset_category_id');

        $this->assertDatabaseMissing('institute_assets', [
            'asset_tag' => 'ARCH-ASSET-001',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.assets.index'))
            ->post(route('admin.assets.stock-items.store'), [
                'asset_category_id' => $inactiveCategory->id,
                'name' => 'Archived Category Stock',
                'sku' => 'ARCH-STOCK',
                'unit' => 'box',
                'current_stock' => 5,
                'reorder_level' => 2,
                'location' => 'Store',
            ])
            ->assertRedirect(route('admin.assets.index'))
            ->assertSessionHasErrors('asset_category_id');

        $this->assertDatabaseMissing('inventory_items', [
            'sku' => 'ARCH-STOCK',
        ]);
    }

    public function test_damaged_or_repair_assets_are_not_added_to_available_pool(): void
    {
        $admin = $this->userWithRole('admin');
        $category = $this->category();

        $this->actingAs($admin)
            ->post(route('admin.assets.store'), [
                'asset_category_id' => $category->id,
                'asset_tag' => 'DMG-ASSET-001',
                'name' => 'Damaged Projector',
                'serial_number' => 'DMG-SN-001',
                'purchase_date' => '2026-06-10',
                'purchase_cost' => 30000,
                'location' => 'AV Store',
                'condition' => 'damaged',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset added to register.');

        $this->assertDatabaseHas('institute_assets', [
            'asset_tag' => 'DMG-ASSET-001',
            'condition' => 'damaged',
            'status' => 'maintenance',
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
                'assigned_on' => now()->subWeek()->toDateString(),
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
                'returned_on' => now()->toDateString(),
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

    public function test_admin_cannot_assign_stale_available_asset_marked_damaged_or_needing_repair(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'available', 'condition' => 'damaged']);

        $this->actingAs($admin)
            ->post(route('admin.assets.assign', $asset), [
                'assigned_to_user_id' => $teacher->id,
                'assigned_on' => now()->toDateString(),
                'remarks' => 'Direct assignment attempt',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Assets marked damaged or needing repair cannot be assigned until repaired.');

        $this->assertSame(0, AssetAssignment::where('institute_asset_id', $asset->id)->count());
        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_admin_cannot_assign_asset_with_stale_active_assignment_even_if_status_is_available(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $nextUser = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'available']);

        AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.assign', $asset), [
                'assigned_to_user_id' => $nextUser->id,
                'assigned_on' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This asset already has an active assignment. Return the current assignment before reassigning.');

        $this->assertSame(1, AssetAssignment::where('institute_asset_id', $asset->id)->where('status', 'active')->count());
        $this->assertDatabaseMissing('asset_assignments', [
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $nextUser->id,
            'status' => 'active',
        ]);
        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_admin_cannot_future_date_active_asset_assignment(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'available']);

        $this->actingAs($admin)
            ->post(route('admin.assets.assign', $asset), [
                'assigned_to_user_id' => $teacher->id,
                'assigned_on' => now()->addWeek()->toDateString(),
                'remarks' => 'Schedule next week custody.',
            ])
            ->assertSessionHasErrors('assigned_on');

        $this->assertSame('available', $asset->fresh()->status);
        $this->assertSame(0, AssetAssignment::where('institute_asset_id', $asset->id)->count());
    }

    public function test_admin_cannot_return_asset_before_assignment_date(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'assigned']);

        $assignment = AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => '2026-06-13',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.assignments.return', $assignment), [
                'returned_on' => '2026-06-12',
                'condition' => 'good',
            ])
            ->assertSessionHasErrors('returned_on');

        $this->assertSame('active', $assignment->fresh()->status);
        $this->assertSame('assigned', $asset->fresh()->status);
    }

    public function test_admin_cannot_return_asset_with_future_date(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'assigned']);

        $assignment = AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.assignments.return', $assignment), [
                'returned_on' => now()->addWeek()->toDateString(),
                'condition' => 'good',
            ])
            ->assertSessionHasErrors('returned_on');

        $assignment->refresh();
        $asset->refresh();
        $this->assertSame('active', $assignment->status);
        $this->assertNull($assignment->returned_on);
        $this->assertSame('assigned', $asset->status);
    }

    public function test_returned_damaged_asset_goes_to_maintenance_not_available_pool(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $asset = $this->asset(['status' => 'assigned', 'condition' => 'good']);

        $assignment = AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.assignments.return', $assignment), [
                'returned_on' => now()->toDateString(),
                'condition' => 'damaged',
                'remarks' => 'Screen cracked during use.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Asset returned successfully.');

        $assignment->refresh();
        $asset->refresh();

        $this->assertSame('returned', $assignment->status);
        $this->assertSame('maintenance', $asset->status);
        $this->assertSame('damaged', $asset->condition);
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

    public function test_admin_cannot_record_duplicate_stock_receive_reference_for_same_item(): void
    {
        $admin = $this->userWithRole('admin');
        $item = $this->inventoryItem(['current_stock' => 5]);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'receive',
            'quantity' => 10,
            'performed_by' => $admin->id,
            'vendor_name' => 'Lab Supplier',
            'reference_number' => 'PO-DUP-001',
            'movement_date' => now()->subDay()->toDateString(),
        ]);
        $item->update(['current_stock' => 15]);

        $this->actingAs($admin)
            ->from(route('admin.assets.index'))
            ->post(route('admin.assets.stock-items.receive', $item), [
                'quantity' => 10,
                'vendor_name' => 'Lab Supplier',
                'reference_number' => 'PO-DUP-001',
                'movement_date' => now()->toDateString(),
                'remarks' => 'Duplicate receipt attempt.',
            ])
            ->assertRedirect(route('admin.assets.index'))
            ->assertSessionHasErrors('reference_number');

        $this->assertSame(15, $item->fresh()->current_stock);
        $this->assertSame(1, InventoryMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'receive')
            ->where('reference_number', 'PO-DUP-001')
            ->count());
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

    public function test_admin_cannot_record_duplicate_stock_issue_reference_for_same_item(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $item = $this->inventoryItem(['current_stock' => 10]);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => 4,
            'performed_by' => $admin->id,
            'issued_to_user_id' => $teacher->id,
            'reference_number' => 'REQ-DUP-001',
            'movement_date' => now()->subDay()->toDateString(),
        ]);
        $item->update(['current_stock' => 6]);

        $this->actingAs($admin)
            ->from(route('admin.assets.index'))
            ->post(route('admin.assets.stock-items.issue', $item), [
                'quantity' => 4,
                'issued_to_user_id' => $teacher->id,
                'reference_number' => 'REQ-DUP-001',
                'movement_date' => now()->toDateString(),
                'remarks' => 'Duplicate issue attempt.',
            ])
            ->assertRedirect(route('admin.assets.index'))
            ->assertSessionHasErrors('reference_number');

        $this->assertSame(6, $item->fresh()->current_stock);
        $this->assertSame(1, InventoryMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'issue')
            ->where('reference_number', 'REQ-DUP-001')
            ->count());
    }

    public function test_inactive_inventory_item_cannot_receive_or_issue_stock(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $item = $this->inventoryItem([
            'current_stock' => 8,
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.receive', $item), [
                'quantity' => 5,
                'movement_date' => '2026-06-13',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only active inventory items can receive stock.');

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.issue', $item), [
                'quantity' => 2,
                'issued_to_user_id' => $teacher->id,
                'movement_date' => '2026-06-13',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Only active inventory items can be issued.');

        $this->assertSame(8, $item->fresh()->current_stock);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_inventory_stock_movements_cannot_be_future_dated(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $item = $this->inventoryItem(['current_stock' => 8]);

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.receive', $item), [
                'quantity' => 5,
                'movement_date' => now()->addWeek()->toDateString(),
                'reference_number' => 'FUTURE-PO',
            ])
            ->assertSessionHasErrors('movement_date');

        $this->actingAs($admin)
            ->post(route('admin.assets.stock-items.issue', $item), [
                'quantity' => 2,
                'issued_to_user_id' => $teacher->id,
                'movement_date' => now()->addWeek()->toDateString(),
                'reference_number' => 'FUTURE-ISSUE',
            ])
            ->assertSessionHasErrors('movement_date');

        $this->assertSame(8, $item->fresh()->current_stock);
        $this->assertSame(0, InventoryMovement::count());
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

    public function test_admin_assets_exports_register_assignments_stock_and_movements(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $teacher->update(['name' => 'Export Asset Custodian']);
        $category = AssetCategory::create([
            'name' => 'Export Equipment',
            'code' => 'EXP-ASSET',
            'is_active' => true,
        ]);

        $matchingAsset = InstituteAsset::create([
            'asset_category_id' => $category->id,
            'asset_tag' => 'EXP-LAP-001',
            'name' => 'Export Laptop',
            'serial_number' => 'EXP-SN-001',
            'purchase_cost' => 45000,
            'location' => 'Export Store',
            'condition' => 'good',
            'status' => 'assigned',
        ]);
        InstituteAsset::create([
            'asset_category_id' => $category->id,
            'asset_tag' => 'OTHER-LAP-001',
            'name' => 'Other Laptop',
            'purchase_cost' => 30000,
            'location' => 'Other Store',
            'condition' => 'good',
            'status' => 'available',
        ]);
        AssetAssignment::create([
            'institute_asset_id' => $matchingAsset->id,
            'assigned_to_user_id' => $teacher->id,
            'assigned_by' => $admin->id,
            'assigned_on' => now()->subDay()->toDateString(),
            'status' => 'active',
            'remarks' => 'Export custody note',
        ]);
        $item = InventoryItem::create([
            'asset_category_id' => $category->id,
            'name' => 'Export Marker',
            'sku' => 'EXP-MARKER',
            'unit' => 'box',
            'current_stock' => 2,
            'reorder_level' => 5,
            'location' => 'Export Store',
            'status' => 'active',
        ]);
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'issue',
            'quantity' => 3,
            'performed_by' => $admin->id,
            'issued_to_user_id' => $teacher->id,
            'reference_number' => 'EXP-REQ-001',
            'movement_date' => now()->toDateString(),
            'remarks' => 'Export movement note',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.assets.index', ['search' => 'Export', 'status' => 'assigned']))
            ->assertOk()
            ->assertSee(route('admin.assets.export', ['search' => 'Export', 'status' => 'assigned']))
            ->assertSee(route('admin.assets.assignments.export'))
            ->assertSee(route('admin.assets.stock-items.export'))
            ->assertSee(route('admin.assets.stock-movements.export'))
            ->assertSee('Showing 1 records. Filter: search: Export; status: assigned.')
            ->assertSee('Export Laptop')
            ->assertDontSee('Other Laptop');

        $assetCsv = $this->actingAs($admin)
            ->get(route('admin.assets.export', ['search' => 'Export', 'status' => 'assigned']))
            ->streamedContent();
        $this->assertStringContainsString('EXP-LAP-001', $assetCsv);
        $this->assertStringContainsString('Export Asset Custodian', $assetCsv);
        $this->assertStringNotContainsString('OTHER-LAP-001', $assetCsv);

        $assignmentCsv = $this->actingAs($admin)
            ->get(route('admin.assets.assignments.export'))
            ->streamedContent();
        $this->assertStringContainsString('Export custody note', $assignmentCsv);
        $this->assertStringContainsString('Export Asset Custodian', $assignmentCsv);

        $stockCsv = $this->actingAs($admin)
            ->get(route('admin.assets.stock-items.export'))
            ->streamedContent();
        $this->assertStringContainsString('EXP-MARKER', $stockCsv);
        $this->assertStringContainsString('low_stock', $stockCsv);

        $movementCsv = $this->actingAs($admin)
            ->get(route('admin.assets.stock-movements.export'))
            ->streamedContent();
        $this->assertStringContainsString('EXP-REQ-001', $movementCsv);
        $this->assertStringContainsString('Export movement note', $movementCsv);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'Assets asset register exported: 1 rows; filters={"search":"Export","status":"assigned"}',
        ]);
    }
}
