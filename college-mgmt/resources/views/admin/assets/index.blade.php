@extends('layouts.admin')

@section('title', 'Asset Register')
@section('page-title', 'Asset Register')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Assets</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Asset operating sequence</div>
            <div class="small text-muted">Start with stock and availability, then create categories/items, add assets, assign or return, and export the current register.</div>
            <div class="d-flex flex-wrap gap-2 mt-2 small">
                <span class="badge text-bg-light">Owner: Admin / Director operations</span>
                <span class="badge text-bg-light">Source: Asset register, custody assignments, inventory movements</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Check availability</span>
            <span class="badge text-bg-light">2. Review low stock</span>
            <span class="badge text-bg-light">3. Create item/asset</span>
            <span class="badge text-bg-light">4. Assign or return</span>
            <span class="badge text-bg-light">5. Export register</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="{{ route('admin.assets.index') }}#asset-register" aria-label="Open all assets in the register">
            <div class="card-body"><div class="text-muted small">Total Assets</div><div class="fs-3 fw-bold">{{ $stats['total'] }}</div><div class="small text-muted">Open register</div></div>
        </a>
    </div>
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="{{ route('admin.assets.index', ['status' => 'available']) }}#asset-register" aria-label="Open available assets">
            <div class="card-body"><div class="text-muted small">Available</div><div class="fs-3 fw-bold text-success">{{ $stats['available'] }}</div><div class="small text-muted">Ready to assign</div></div>
        </a>
    </div>
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="{{ route('admin.assets.index', ['status' => 'assigned']) }}#asset-register" aria-label="Open assigned assets">
            <div class="card-body"><div class="text-muted small">Assigned</div><div class="fs-3 fw-bold text-primary">{{ $stats['assigned'] }}</div><div class="small text-muted">Review custody</div></div>
        </a>
    </div>
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="{{ route('admin.assets.index', ['status' => 'maintenance']) }}#asset-register" aria-label="Open maintenance assets">
            <div class="card-body"><div class="text-muted small">Maintenance</div><div class="fs-3 fw-bold text-warning">{{ $stats['maintenance'] }}</div><div class="small text-muted">Repair queue</div></div>
        </a>
    </div>
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="#asset-consumable-stock" aria-label="Open consumable stock section">
            <div class="card-body"><div class="text-muted small">Stock Items</div><div class="fs-3 fw-bold">{{ $stats['stock_items'] }}</div><div class="small text-muted">Open stock table</div></div>
        </a>
    </div>
    <div class="col-md-2">
        <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" href="#asset-consumable-stock" aria-label="Open low-stock items in consumable stock section">
            <div class="card-body"><div class="text-muted small">Low Stock</div><div class="fs-3 fw-bold text-danger">{{ $stats['low_stock'] }}</div><div class="small text-muted">Review reorder list</div></div>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="asset-register">
    <div class="card-header bg-transparent fw-semibold">Create Consumable Stock Item</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assets.stock-items.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select aria-label="Asset Category" name="asset_category_id" class="form-select">
                    <option value="">- Category -</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Item</label><input aria-label="Printer Paper" name="name" class="form-control" placeholder="Printer Paper" required></div>
            <div class="col-md-2"><label class="form-label">SKU</label><input aria-label="STAT-PAPER-A4" name="sku" class="form-control" placeholder="STAT-PAPER-A4" required></div>
            <div class="col-md-1"><label class="form-label">Unit</label><input aria-label="Unit" name="unit" class="form-control" value="pcs" required></div>
            <div class="col-md-1"><label class="form-label">Stock</label><input aria-label="Current Stock" name="current_stock" type="number" min="0" value="0" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Reorder Level</label><input aria-label="Reorder Level" name="reorder_level" type="number" min="0" value="0" class="form-control" required></div>
            <div class="col-md-1"><label class="form-label">Location</label><input aria-label="Store" name="location" class="form-control" placeholder="Store"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Create</button></div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Create Category</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.assets.categories.store') }}" class="vstack gap-3">
                    @csrf
                    <input aria-label="Category name" name="name" class="form-control" placeholder="Category name" required>
                    <input aria-label="Asset code" name="code" class="form-control" placeholder="Code, e.g. IT" required>
                    <button class="btn btn-primary">Save Category</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Add Asset</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.assets.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3">
                        <select aria-label="Asset Category" name="asset_category_id" class="form-select">
                            <option value="">- Category -</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input aria-label="Asset tag" name="asset_tag" class="form-control" placeholder="Asset tag" required></div>
                    <div class="col-md-3"><input aria-label="Asset name" name="name" class="form-control" placeholder="Asset name" required></div>
                    <div class="col-md-3"><input aria-label="Serial number" name="serial_number" class="form-control" placeholder="Serial number"></div>
                    <div class="col-md-3"><input aria-label="Vendor" name="vendor_name" class="form-control" placeholder="Vendor"></div>
                    <div class="col-md-3"><input aria-label="Purchase Date" name="purchase_date" type="date" class="form-control"></div>
                    <div class="col-md-3"><input aria-label="Cost" name="purchase_cost" type="number" step="0.01" min="0" class="form-control" placeholder="Cost"></div>
                    <div class="col-md-3"><input aria-label="Location" name="location" class="form-control" placeholder="Location"></div>
                    <div class="col-md-3">
                        <select aria-label="Condition" name="condition" class="form-select" required>
                            <option value="new">New</option>
                            <option value="good" selected>Good</option>
                            <option value="needs_repair">Needs Repair</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="col-md-7"><input aria-label="Notes" name="notes" class="form-control" placeholder="Notes"></div>
                    <div class="col-md-2"><button class="btn btn-primary w-100">Add Asset</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h2 class="h6 fw-semibold mb-1">Asset Register</h2>
                <div class="small text-muted">
                    Showing {{ $assets->total() }} records. Filter: {{ $assetFilterSummary }}.
                </div>
            </div>
            <a href="{{ route('admin.assets.export', request()->query()) }}" class="btn btn-sm btn-outline-success">Export Current View</a>
        </div>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input aria-label="Tag, name, serial, location" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tag, name, serial, location">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select aria-label="Status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['available' => 'Available', 'assigned' => 'Assigned', 'maintenance' => 'Maintenance', 'retired' => 'Retired'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary">Filter</button>
                <a href="{{ route('admin.assets.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Asset</th>
                        <th scope="col">Category</th>
                        <th scope="col">Location</th>
                        <th scope="col">Status</th>
                        <th scope="col">Assigned To</th>
                        <th scope="col" class="text-end">Value</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $asset->name }}</div>
                                <div class="small text-muted"><code>{{ $asset->asset_tag }}</code> {{ $asset->serial_number ? '/ '.$asset->serial_number : '' }}</div>
                            </td>
                            <td>{{ $asset->category->name ?? 'Category not linked' }}</td>
                            <td>{{ $asset->location ?? 'Location not recorded' }}</td>
                            <td>
                                @if($asset->status === 'available')
                                    <span class="badge bg-success">Available</span>
                                @elseif($asset->status === 'assigned')
                                    <span class="badge bg-primary">Assigned</span>
                                @elseif($asset->status === 'maintenance')
                                    <span class="badge bg-warning text-dark">Maintenance</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($asset->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $asset->currentAssignment->assignedTo->name ?? 'Not assigned' }}</td>
                            <td class="text-end">Rs. {{ number_format($asset->purchase_cost, 2) }}</td>
                            <td class="text-end">
                                @if($asset->status === 'available')
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignAsset{{ $asset->id }}">Assign</button>
                                @else
                                    <span class="text-muted small">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No assets match this register view.</div>
                                <div class="small">Create a category and asset first, or clear the search/status filter to review the full institute asset register.</div>
                                <div class="mt-2">
                                    <a href="{{ route('admin.assets.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($assets->hasPages())
        <div class="card-footer bg-transparent">{{ $assets->links() }}</div>
    @endif
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="fw-semibold">Active Assignments</div>
            <div class="small text-muted">Showing latest {{ $activeAssignments->count() }} of {{ $stats['active_assignments'] }} active assignments.</div>
        </div>
        <a href="{{ route('admin.assets.assignments.export') }}" class="btn btn-sm btn-outline-success">Export Active Assignments</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Asset</th>
                        <th scope="col">Assigned To</th>
                        <th scope="col">Assigned On</th>
                        <th scope="col" class="text-end">Return</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->asset->name ?? 'Asset record missing' }} <span class="text-muted small">({{ $assignment->asset->asset_tag ?? 'Tag missing' }})</span></td>
                            <td>{{ $assignment->assignedTo->name ?? 'Custodian not linked' }}</td>
                            <td>{{ $assignment->assigned_on?->format('d M Y') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.assets.assignments.return', $assignment) }}" class="d-inline" onsubmit="return confirm('Return asset {{ addslashes($assignment->asset->asset_tag ?? $assignment->asset->name ?? 'this asset') }} from {{ addslashes($assignment->assignedTo->name ?? 'this custodian') }} in good condition? Confirm physical inspection, accessories, custody handover, and maintenance status before closing the assignment.')">
                                    @csrf
                                    <input type="hidden" name="returned_on" value="{{ now()->toDateString() }}">
                                    <input type="hidden" name="condition" value="good">
                                    <button class="btn btn-sm btn-outline-success">Return Good</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No active asset assignments.</div>
                                <div class="small">Available assets can be assigned from the register above. Returned or maintenance assets stay out of the active custody list.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4" id="asset-consumable-stock">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="fw-semibold">Consumable Stock</div>
            <div class="small text-muted">{{ $inventoryItems->count() }} stock items, {{ $stats['low_stock'] }} low-stock items.</div>
        </div>
        <a href="{{ route('admin.assets.stock-items.export') }}" class="btn btn-sm btn-outline-success">Export Stock Items</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Category</th>
                        <th scope="col">Location</th>
                        <th scope="col" class="text-end">Current Stock</th>
                        <th scope="col" class="text-end">Reorder Level</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inventoryItems as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item->name }}</div>
                                <div class="small text-muted"><code>{{ $item->sku }}</code> / {{ $item->unit }}</div>
                            </td>
                            <td>{{ $item->category->name ?? 'Category not linked' }}</td>
                            <td>{{ $item->location ?? 'Store location not recorded' }}</td>
                            <td class="text-end fw-semibold">{{ $item->current_stock }}</td>
                            <td class="text-end">{{ $item->reorder_level }}</td>
                            <td>
                                @if($item->status !== 'active')
                                    <span class="badge bg-secondary">Inactive</span>
                                @elseif($item->is_low_stock)
                                    <span class="badge bg-danger">Low Stock</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($item->status === 'active')
                                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#receiveStock{{ $item->id }}">Receive</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#issueStock{{ $item->id }}">Issue</button>
                                @else
                                    <span class="text-muted small">No stock movement</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No consumable stock items are configured.</div>
                                <div class="small">Create stationery, lab, housekeeping, or office stock items above before receiving or issuing inventory.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <div class="fw-semibold">Recent Stock Movements</div>
            <div class="small text-muted">Showing latest {{ $recentMovements->count() }} of {{ $stats['stock_movements'] }} recorded movements.</div>
        </div>
        <a href="{{ route('admin.assets.stock-movements.export') }}" class="btn btn-sm btn-outline-success">Export Stock Movements</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Item</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="text-end">Quantity</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Issued To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->movement_date?->format('d M Y') ?? 'Movement date missing' }}</td>
                            <td>{{ $movement->item->name ?? 'Stock item missing' }}</td>
                            <td>{{ ucfirst($movement->movement_type) }}</td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td>{{ $movement->reference_number ?? $movement->vendor_name ?? 'Reference not recorded' }}</td>
                            <td>{{ $movement->issuedTo->name ?? 'Not issued to a user' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No stock movements recorded yet.</div>
                                <div class="small">Receive stock from a vendor or issue stock to a user to create the first audited movement entry.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($assets as $asset)
    @if($asset->status === 'available')
    <div class="modal fade" id="assignAsset{{ $asset->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.assets.assign', $asset) }}" onsubmit="return confirm('Assign asset {{ addslashes($asset->asset_tag ?? $asset->name) }} to the selected user? Confirm custodian, handover date, accessories, and return expectations before changing asset custody.')">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Assign {{ $asset->asset_tag }}</h5>
                        <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select aria-label="Assigned To User" name="assigned_to_user_id" class="form-select" required>
                                <option value="">- Select User -</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned On</label>
                            <input aria-label="Assigned On" type="date" name="assigned_on" value="{{ now()->toDateString() }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea aria-label="Handover notes, accessories, or expected return terms" name="remarks" rows="3" class="form-control" placeholder="Handover notes, accessories, or expected return terms"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Assign Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
@endforeach

@foreach($inventoryItems as $item)
<div class="modal fade" id="receiveStock{{ $item->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.assets.stock-items.receive', $item) }}" onsubmit="return confirm('Receive stock for {{ addslashes($item->name) }} into inventory? Confirm quantity, vendor/reference, date, and storage location before increasing available stock.')">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Receive {{ $item->name }}</h5>
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Quantity</label><input aria-label="Quantity" name="quantity" type="number" min="1" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Vendor</label><input aria-label="Vendor Name" name="vendor_name" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Reference</label><input aria-label="Reference Number" name="reference_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Date</label><input aria-label="Movement Date" name="movement_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><textarea aria-label="Remarks" name="remarks" rows="3" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Receive Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="issueStock{{ $item->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.assets.stock-items.issue', $item) }}" onsubmit="return confirm('Issue stock for {{ addslashes($item->name) }} and reduce current inventory? Confirm quantity, recipient, purpose, and low-stock impact before recording the movement.')">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Issue {{ $item->name }}</h5>
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">Current stock: {{ $item->current_stock }} {{ $item->unit }}</div>
                    <div class="mb-3"><label class="form-label">Quantity</label><input aria-label="Quantity" name="quantity" type="number" min="1" class="form-control" required></div>
                    <div class="mb-3">
                        <label class="form-label">Issue To</label>
                        <select aria-label="Issued To User" name="issued_to_user_id" class="form-select">
                            <option value="">- Select User -</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Reference</label><input aria-label="Reference Number" name="reference_number" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Date</label><input aria-label="Movement Date" name="movement_date" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><textarea aria-label="Remarks" name="remarks" rows="3" class="form-control"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Issue Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
