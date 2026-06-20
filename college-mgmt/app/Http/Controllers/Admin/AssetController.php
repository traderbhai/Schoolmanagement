<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\InstituteAsset;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $assets = $this->assetQuery($request)->paginate(20)->withQueryString();
        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $activeAssignments = $this->assignmentQuery()
            ->limit(10)
            ->get();
        $inventoryItems = $this->inventoryItemQuery()->get();
        $recentMovements = $this->movementQuery()
            ->limit(10)
            ->get();
        $assetFilterSummary = $this->assetFilterSummary($request);

        $stats = [
            'total' => InstituteAsset::count(),
            'available' => InstituteAsset::where('status', 'available')->count(),
            'assigned' => InstituteAsset::where('status', 'assigned')->count(),
            'maintenance' => InstituteAsset::where('status', 'maintenance')->count(),
            'value' => InstituteAsset::sum('purchase_cost'),
            'stock_items' => InventoryItem::count(),
            'low_stock' => InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(),
            'asset_filtered_total' => $this->assetQuery($request)->count(),
            'active_assignments' => $this->assignmentQuery()->count(),
            'stock_movements' => $this->movementQuery()->count(),
        ];

        return view('admin.assets.index', compact(
            'assets',
            'categories',
            'users',
            'activeAssignments',
            'inventoryItems',
            'recentMovements',
            'assetFilterSummary',
            'stats'
        ));
    }

    public function exportAssets(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $assets = $this->assetQuery($request)->get();
        $this->recordExportActivity('asset register', $assets->count(), $request);

        return $this->csvDownload('assets-register-' . now()->format('Ymd') . '.csv', [
            ['Asset Tag', 'Name', 'Category', 'Serial Number', 'Location', 'Condition', 'Status', 'Assigned To', 'Purchase Cost'],
            ...$assets->map(fn (InstituteAsset $asset) => [
                $asset->asset_tag,
                $asset->name,
                $asset->category?->name,
                $asset->serial_number,
                $asset->location,
                $asset->condition,
                $asset->status,
                $asset->currentAssignment?->assignedTo?->name,
                $asset->purchase_cost,
            ]),
        ]);
    }

    public function exportAssignments(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $assignments = $this->assignmentQuery()->get();
        $this->recordExportActivity('active assignments', $assignments->count(), $request);

        return $this->csvDownload('assets-active-assignments-' . now()->format('Ymd') . '.csv', [
            ['Asset Tag', 'Asset', 'Assigned To', 'Assigned By', 'Assigned On', 'Status', 'Remarks'],
            ...$assignments->map(fn (AssetAssignment $assignment) => [
                $assignment->asset?->asset_tag,
                $assignment->asset?->name,
                $assignment->assignedTo?->name,
                $assignment->assignedBy?->name,
                $assignment->assigned_on?->toDateString(),
                $assignment->status,
                $assignment->remarks,
            ]),
        ]);
    }

    public function exportStockItems(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $items = $this->inventoryItemQuery()->get();
        $this->recordExportActivity('consumable stock', $items->count(), $request);

        return $this->csvDownload('assets-consumable-stock-' . now()->format('Ymd') . '.csv', [
            ['SKU', 'Item', 'Category', 'Unit', 'Location', 'Current Stock', 'Reorder Level', 'Stock State', 'Status'],
            ...$items->map(fn (InventoryItem $item) => [
                $item->sku,
                $item->name,
                $item->category?->name,
                $item->unit,
                $item->location,
                $item->current_stock,
                $item->reorder_level,
                $item->is_low_stock ? 'low_stock' : 'ok',
                $item->status,
            ]),
        ]);
    }

    public function exportMovements(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $movements = $this->movementQuery()->get();
        $this->recordExportActivity('stock movements', $movements->count(), $request);

        return $this->csvDownload('assets-stock-movements-' . now()->format('Ymd') . '.csv', [
            ['Date', 'SKU', 'Item', 'Type', 'Quantity', 'Reference', 'Vendor', 'Issued To', 'Performed By', 'Remarks'],
            ...$movements->map(fn (InventoryMovement $movement) => [
                $movement->movement_date?->toDateString(),
                $movement->item?->sku,
                $movement->item?->name,
                $movement->movement_type,
                $movement->quantity,
                $movement->reference_number,
                $movement->vendor_name,
                $movement->issuedTo?->name,
                $movement->performedBy?->name,
                $movement->remarks,
            ]),
        ]);
    }

    public function categoryStore(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $data = $request->validate([
            'name' => 'required|string|max:120|unique:asset_categories,name',
            'code' => 'required|string|max:30|unique:asset_categories,code',
        ]);

        AssetCategory::create($data + ['is_active' => true]);

        return back()->with('success', 'Asset category created.');
    }

    public function assetStore(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $data = $request->validate([
            'asset_category_id' => ['nullable', Rule::exists('asset_categories', 'id')->where('is_active', true)],
            'asset_tag' => 'required|string|max:60|unique:institute_assets,asset_tag',
            'name' => 'required|string|max:160',
            'serial_number' => 'nullable|string|max:120',
            'vendor_name' => 'nullable|string|max:160',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:160',
            'condition' => 'required|in:new,good,needs_repair,damaged',
            'notes' => 'nullable|string|max:1000',
        ]);

        InstituteAsset::create($data + [
            'purchase_cost' => $data['purchase_cost'] ?? 0,
            'status' => in_array($data['condition'], ['needs_repair', 'damaged'], true) ? 'maintenance' : 'available',
        ]);

        return back()->with('success', 'Asset added to register.');
    }

    public function stockItemStore(Request $request)
    {
        $this->authorizeAssetManagement($request);

        $data = $request->validate([
            'asset_category_id' => ['nullable', Rule::exists('asset_categories', 'id')->where('is_active', true)],
            'name' => 'required|string|max:160',
            'sku' => 'required|string|max:60|unique:inventory_items,sku',
            'unit' => 'required|string|max:30',
            'current_stock' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'location' => 'nullable|string|max:160',
        ]);

        InventoryItem::create($data + ['status' => 'active']);

        return back()->with('success', 'Inventory item created.');
    }

    public function stockReceive(Request $request, InventoryItem $item)
    {
        $this->authorizeAssetManagement($request);

        if ($item->status !== 'active') {
            return back()->with('error', 'Only active inventory items can receive stock.');
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'vendor_name' => 'nullable|string|max:160',
            'reference_number' => 'nullable|string|max:120',
            'movement_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $reference = trim((string) ($data['reference_number'] ?? ''));
        if ($reference !== '' && InventoryMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'receive')
            ->where('reference_number', $reference)
            ->exists()) {
            return back()->withErrors(['reference_number' => 'This receive reference has already been recorded for this inventory item.']);
        }

        DB::transaction(function () use ($item, $data) {
            $item->increment('current_stock', $data['quantity']);
            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type' => 'receive',
                'quantity' => $data['quantity'],
                'performed_by' => auth()->id(),
                'vendor_name' => $data['vendor_name'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'movement_date' => $data['movement_date'],
                'remarks' => $data['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Stock received successfully.');
    }

    public function stockIssue(Request $request, InventoryItem $item)
    {
        $this->authorizeAssetManagement($request);

        if ($item->status !== 'active') {
            return back()->with('error', 'Only active inventory items can be issued.');
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'issued_to_user_id' => 'nullable|exists:users,id',
            'reference_number' => 'nullable|string|max:120',
            'movement_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($item->current_stock < $data['quantity']) {
            return back()->withErrors(['quantity' => 'Issue quantity cannot exceed current stock.']);
        }

        $reference = trim((string) ($data['reference_number'] ?? ''));
        if ($reference !== '' && InventoryMovement::where('inventory_item_id', $item->id)
            ->where('movement_type', 'issue')
            ->where('reference_number', $reference)
            ->exists()) {
            return back()->withErrors(['reference_number' => 'This issue reference has already been recorded for this inventory item.']);
        }

        DB::transaction(function () use ($item, $data) {
            $item->decrement('current_stock', $data['quantity']);
            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type' => 'issue',
                'quantity' => $data['quantity'],
                'performed_by' => auth()->id(),
                'issued_to_user_id' => $data['issued_to_user_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'movement_date' => $data['movement_date'],
                'remarks' => $data['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Stock issued successfully.');
    }

    public function assign(Request $request, InstituteAsset $asset)
    {
        $this->authorizeAssetManagement($request);

        $data = $request->validate([
            'assigned_to_user_id' => 'required|exists:users,id',
            'assigned_on' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($asset->status !== 'available') {
            return back()->with('error', 'Only available assets can be assigned.');
        }

        if (in_array($asset->condition, ['needs_repair', 'damaged'], true)) {
            return back()->with('error', 'Assets marked damaged or needing repair cannot be assigned until repaired.');
        }

        if ($asset->assignments()->where('status', 'active')->exists()) {
            return back()->with('error', 'This asset already has an active assignment. Return the current assignment before reassigning.');
        }

        AssetAssignment::create([
            'institute_asset_id' => $asset->id,
            'assigned_to_user_id' => $data['assigned_to_user_id'],
            'assigned_by' => auth()->id(),
            'assigned_on' => $data['assigned_on'],
            'status' => 'active',
            'remarks' => $data['remarks'] ?? null,
        ]);

        $asset->update(['status' => 'assigned']);

        return back()->with('success', 'Asset assigned successfully.');
    }

    public function returnAssignment(Request $request, AssetAssignment $assignment)
    {
        $this->authorizeAssetManagement($request);

        $data = $request->validate([
            'returned_on' => 'required|date|after_or_equal:'.$assignment->assigned_on->toDateString().'|before_or_equal:today',
            'condition' => 'required|in:new,good,needs_repair,damaged',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($assignment->status !== 'active') {
            return back()->with('error', 'Only active asset assignments can be returned.');
        }

        $assignment->update([
            'status' => 'returned',
            'returned_on' => $data['returned_on'],
            'remarks' => $data['remarks'] ?? $assignment->remarks,
        ]);

        $assignment->asset->update([
            'status' => in_array($data['condition'], ['needs_repair', 'damaged'], true) ? 'maintenance' : 'available',
            'condition' => $data['condition'],
        ]);

        return back()->with('success', 'Asset returned successfully.');
    }

    private function authorizeAssetManagement(Request $request): void
    {
        abort_unless(
            $request->user() && AccessControl::canManageInstitutionAssets($request->user()),
            403
        );
    }

    private function assetQuery(Request $request)
    {
        $query = InstituteAsset::with(['category', 'currentAssignment.assignedTo'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function assignmentQuery()
    {
        return AssetAssignment::with(['asset', 'assignedTo', 'assignedBy'])
            ->where('status', 'active')
            ->latest();
    }

    private function inventoryItemQuery()
    {
        return InventoryItem::with('category')
            ->orderBy('name');
    }

    private function movementQuery()
    {
        return InventoryMovement::with(['item', 'issuedTo', 'performedBy'])
            ->latest();
    }

    private function assetFilterSummary(Request $request): string
    {
        $parts = [];

        if ($request->filled('search')) {
            $parts[] = 'search: ' . $request->search;
        }

        if ($request->filled('status')) {
            $parts[] = 'status: ' . $request->status;
        }

        return empty($parts) ? 'All assets' : implode('; ', $parts);
    }

    private function csvDownload(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function recordExportActivity(string $surface, int $rowCount, Request $request): void
    {
        $filters = $request->query();
        $filterSummary = empty($filters) ? 'none' : json_encode($filters, JSON_UNESCAPED_SLASHES);

        ActivityLog::record('export', "Assets {$surface} exported: {$rowCount} rows; filters={$filterSummary}");
    }
}
