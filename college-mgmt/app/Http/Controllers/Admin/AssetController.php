<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\InstituteAsset;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request)
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

        $assets = $query->paginate(20)->withQueryString();
        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $activeAssignments = AssetAssignment::with(['asset', 'assignedTo'])
            ->where('status', 'active')
            ->latest()
            ->limit(10)
            ->get();
        $inventoryItems = InventoryItem::with('category')
            ->orderBy('name')
            ->get();
        $recentMovements = InventoryMovement::with(['item', 'issuedTo'])
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'total' => InstituteAsset::count(),
            'available' => InstituteAsset::where('status', 'available')->count(),
            'assigned' => InstituteAsset::where('status', 'assigned')->count(),
            'maintenance' => InstituteAsset::where('status', 'maintenance')->count(),
            'value' => InstituteAsset::sum('purchase_cost'),
            'stock_items' => InventoryItem::count(),
            'low_stock' => InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(),
        ];

        return view('admin.assets.index', compact(
            'assets',
            'categories',
            'users',
            'activeAssignments',
            'inventoryItems',
            'recentMovements',
            'stats'
        ));
    }

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:asset_categories,name',
            'code' => 'required|string|max:30|unique:asset_categories,code',
        ]);

        AssetCategory::create($data + ['is_active' => true]);

        return back()->with('success', 'Asset category created.');
    }

    public function assetStore(Request $request)
    {
        $data = $request->validate([
            'asset_category_id' => 'nullable|exists:asset_categories,id',
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
            'status' => 'available',
        ]);

        return back()->with('success', 'Asset added to register.');
    }

    public function stockItemStore(Request $request)
    {
        $data = $request->validate([
            'asset_category_id' => 'nullable|exists:asset_categories,id',
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
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'vendor_name' => 'nullable|string|max:160',
            'reference_number' => 'nullable|string|max:120',
            'movement_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

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
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'issued_to_user_id' => 'nullable|exists:users,id',
            'reference_number' => 'nullable|string|max:120',
            'movement_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($item->current_stock < $data['quantity']) {
            return back()->withErrors(['quantity' => 'Issue quantity cannot exceed current stock.']);
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
        $data = $request->validate([
            'assigned_to_user_id' => 'required|exists:users,id',
            'assigned_on' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($asset->status !== 'available') {
            return back()->with('error', 'Only available assets can be assigned.');
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
        $data = $request->validate([
            'returned_on' => 'required|date',
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
            'status' => $data['condition'] === 'needs_repair' ? 'maintenance' : 'available',
            'condition' => $data['condition'],
        ]);

        return back()->with('success', 'Asset returned successfully.');
    }
}
